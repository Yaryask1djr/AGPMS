<?php
/** One transaction per event; the pass lock serializes all gate writers. */
function process_gate_scan(mysqli $conn, array $actor, array $item): array {
 $event=$item['event_id'] ?? '';
 $token=$item['token'] ?? '';$id=$item['id'] ?? 0;$action=$item['action'] ?? 'checkin';
 if (!is_string($event) || !preg_match('/^[a-f0-9-]{36}$/D',$event)
  || !is_string($token) || !in_array($action,['checkin','checkout'],true)
  || (!preg_match('/^[a-f0-9]{16,64}$/D',$token) && !filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])))
  return ['success'=>false,'acknowledged'=>false,'event_id'=>$event,'message'=>'Invalid scan payload.'];
 $observed=null;
 if (!empty($item['scanned_at'])) {
  try {
   if (!is_string($item['scanned_at']) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/D',$item['scanned_at'])) throw new Exception();
   $d=new DateTimeImmutable($item['scanned_at']);
   if ($d->getTimestamp()>time()+300 || $d->getTimestamp()<time()-86400) throw new Exception();
   $observed=$d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  } catch (Exception $e) { return ['success'=>false,'acknowledged'=>false,'event_id'=>$event,'message'=>'Invalid or stale device timestamp.']; }
 }
 // Device time is metadata. Authorization uses server receipt time exclusively.
 $payload=hash('sha256',json_encode([$token,(int)$id,$action,$item['scanned_at']??null]));
 $actorId=(int)$actor['id'];
 try {
  $conn->begin_transaction();
  $s=$conn->prepare('SELECT actor_id,payload_hash,result_json FROM agpms_scan_events WHERE event_id=?');
  $s->bind_param('s',$event);$s->execute();$prior=$s->get_result()->fetch_assoc();$s->close();
  if ($prior) {
   $conn->rollback();
   if ((int)$prior['actor_id']!==$actorId || !hash_equals($prior['payload_hash'],$payload)) return ['success'=>false,'acknowledged'=>false,'event_id'=>$event,'message'=>'Event ID conflict.'];
   return json_decode($prior['result_json'],true,64,JSON_THROW_ON_ERROR);
  }
  $s=$conn->prepare('SELECT * FROM gate_passes WHERE '.($token!==''?'token=?':'id=?').' FOR UPDATE');
  if ($token!=='')$s->bind_param('s',$token);else{$id=(int)$id;$s->bind_param('i',$id);}
  $s->execute();$pass=$s->get_result()->fetch_assoc();$s->close();
  $result=['success'=>false,'acknowledged'=>true,'event_id'=>$event,'message'=>'Pass not found.'];
  if ($pass) {
   $pid=(int)$pass['id'];
   $s=$conn->prepare('SELECT NOW() AS now');$s->execute();$now=$s->get_result()->fetch_assoc()['now'];$s->close();
   $s=$conn->prepare('SELECT id,check_in_time FROM visit_logs WHERE pass_id=? AND check_out_time IS NULL FOR UPDATE');$s->bind_param('i',$pid);$s->execute();$open=$s->get_result()->fetch_assoc();$s->close();
   if ($action==='checkout') {
    // Revocation/expiry/blacklisting must not block recording a visitor's exit.
    if ($open) {
     $s=$conn->prepare('UPDATE visit_logs SET check_out_time=? WHERE id=? AND check_out_time IS NULL');$s->bind_param('si',$now,$open['id']);$s->execute();$s->close();
     if ($pass['pass_type']!=='recurring'){$s=$conn->prepare("UPDATE gate_passes SET status='checked_out' WHERE id=?");$s->bind_param('i',$pid);$s->execute();$s->close();}
     $result['success']=true;$result['message']='Checkout recorded.';
    } else $result['message']='No open visit.';
   } else {
    $s=$conn->prepare("SELECT id FROM users WHERE id=? AND status='active' AND approved=1");$s->bind_param('i',$pass['user_id']);$s->execute();$host=$s->get_result()->fetch_assoc();$s->close();
    $s=$conn->prepare('SELECT id FROM blacklist WHERE phone=?');$s->bind_param('s',$pass['contact']);$s->execute();$blocked=$s->get_result()->fetch_assoc();$s->close();
    if (!$host || $blocked || !in_array($pass['status'],['pending','active'],true)) $result['message']='Entry denied by current authorization.';
    elseif ($now<$pass['entry_time'] || $now>$pass['exit_time']) $result['message']='Entry denied outside valid time window.';
    elseif ($open) $result['message']='Visitor is already inside.';
    else {
     $s=$conn->prepare('INSERT INTO visit_logs(pass_id,check_in_time,security_id) VALUES(?,?,?)');$s->bind_param('isi',$pid,$now,$actorId);$s->execute();$s->close();
     $state=$pass['pass_type']==='recurring'?'active':'checked_in';
     $s=$conn->prepare('UPDATE gate_passes SET status=? WHERE id=?');$s->bind_param('si',$state,$pid);$s->execute();$s->close();
     create_notification($conn,(int)$pass['user_id'],'Visitor arrived',$pass['visitor_name'].' checked in at the gate.');
     $result['success']=true;$result['message']='Check-in recorded.';
    }
   }
  }
  $result['observed_at']=$observed;$result['sound']=$result['success']?'grant':'deny';
  $json=json_encode($result,JSON_THROW_ON_ERROR);
  $s=$conn->prepare('INSERT INTO agpms_scan_events(event_id,actor_id,payload_hash,result_json) VALUES(?,?,?,?)');$s->bind_param('siss',$event,$actorId,$payload,$json);$s->execute();$s->close();
  $conn->commit();return $result;
 } catch (Throwable $e) {
  $conn->rollback();error_log('Gate event failed: '.$e->getMessage());
  // A concurrent duplicate may have committed. Return its stable acknowledgement.
  $s=$conn->prepare('SELECT actor_id,payload_hash,result_json FROM agpms_scan_events WHERE event_id=?');$s->bind_param('s',$event);$s->execute();$prior=$s->get_result()->fetch_assoc();$s->close();
  if ($prior && (int)$prior['actor_id']===$actorId && hash_equals($prior['payload_hash'],$payload)) return json_decode($prior['result_json'],true);
  return ['success'=>false,'acknowledged'=>false,'event_id'=>$event,'message'=>'Temporary scan failure; retry this event.'];
 }
}
