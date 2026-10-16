<?php
// CLI-only. Creates a unique temporary database; NEVER uses the application DB.
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/scan_service.php';
function check($ok,$message) {if (!$ok) throw new RuntimeException($message);echo "PASS: $message\n";}
$conn=new mysqli(getenv('AGPMS_TEST_HOST')?:'127.0.0.1',getenv('AGPMS_TEST_USER')?:'root',getenv('AGPMS_TEST_PASSWORD')?:'', '',(int)(getenv('AGPMS_TEST_PORT')?:3306));
$db='agpms_test_'.bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4");$conn->select_db($db);
function sql_file($conn,$sql) { $conn->multi_query($sql); do {if ($r=$conn->store_result())$r->free();} while($conn->more_results() && $conn->next_result()); }
try {
 $schema=file_get_contents(__DIR__.'/fixtures/schema.sql');
 $schema=preg_replace('/CREATE DATABASE IF NOT EXISTS agpms;|USE agpms;/','',$schema);
 sql_file($conn,$schema);
 sql_file($conn,file_get_contents(__DIR__.'/../migrations/20261001_security.sql'));
 // Same additive migration can be run again without losing tables/records.
 sql_file($conn,file_get_contents(__DIR__.'/../migrations/20261001_security.sql'));
 $conn->query("INSERT INTO users(id,name,phone,password,role,status,approved) VALUES(1,'Guard','test-guard','unused','security','active',1),(2,'Resident','test-resident','unused','resident','active',1)");
 $actor=['id'=>1,'role'=>'security'];$token=bin2hex(random_bytes(8));
 $s=$conn->prepare("INSERT INTO gate_passes(user_id,visitor_name,contact,purpose,entry_time,exit_time,token,status) VALUES(2,'Visitor','test-visitor','Testing',DATE_SUB(NOW(),INTERVAL 1 HOUR),DATE_ADD(NOW(),INTERVAL 1 HOUR),?,'active')");$s->bind_param('s',$token);$s->execute();$pid=$s->insert_id;$s->close();
 function event($token,$action='checkin') {return ['event_id'=>vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex(random_bytes(16)),4)),'token'=>$token,'action'=>$action];}
 $input=event($token);$r=process_gate_scan($conn,$actor,$input);check($r['success'],'valid check-in');
 $again=process_gate_scan($conn,$actor,$input);check($r===$again,'same event returns same result');
 $count=$conn->query("SELECT COUNT(*) n FROM visit_logs WHERE pass_id=$pid")->fetch_assoc()['n'];check((int)$count===1,'retry creates no duplicate visit');
 check(!process_gate_scan($conn,$actor,event($token))['success'],'second event cannot check in while inside');
 check(process_gate_scan($conn,$actor,event($token,'checkout'))['success'],'checkout succeeds');
 check(!process_gate_scan($conn,$actor,event($token))['success'],'one-time pass cannot re-enter after checkout');
 $conn->query("UPDATE gate_passes SET status='active',exit_time=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=$pid");
 $forged=event($token);$forged['scanned_at']=gmdate('Y-m-d\TH:i:s\Z',time()-1800);
 check(!process_gate_scan($conn,$actor,$forged)['success'],'forged device time cannot revive expired pass');
 $conn->query("UPDATE gate_passes SET status='active',exit_time=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE id=$pid");
 $conn->query("UPDATE users SET status='revoked' WHERE id=2");
 check(!process_gate_scan($conn,$actor,event($token))['success'],'revoked host blocks entry');
 $conn->query("UPDATE users SET status='active' WHERE id=2");
 check(process_gate_scan($conn,$actor,event($token))['success'],'newly active pass permits entry');
 $conn->query("UPDATE gate_passes SET status='revoked' WHERE id=$pid");
 check(process_gate_scan($conn,$actor,event($token,'checkout'))['success'],'revoked pass still permits recording exit');
 $changed=$input;$changed['action']='checkout';check(!process_gate_scan($conn,$actor,$changed)['acknowledged'],'event ID payload conflict is rejected');
 check(csv_safe('=1+1')==="'=1+1",'CSV formulas neutralized');
 echo "Security service integration suite completed.\n";
} finally {$conn->select_db('mysql');$conn->query("DROP DATABASE `$db`");}
