<?php
require_once __DIR__.'/includes/auth.php';
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $message='If recovery is available for this account, instructions will be delivered to its verified contact.';
 $phone=trim((string)($_POST['phone']??''));
 // Delivery adapter is deployment-controlled and must reside outside the web root.
 // It must send ONLY to a pre-verified contact looked up by user ID, never submitted phone.
 $adapter=getenv('AGPMS_RESET_DELIVERY_ADAPTER');
 $real=$adapter?realpath($adapter):false;$root=realpath(__DIR__).DIRECTORY_SEPARATOR;
 if ($real && strpos($real,$root)!==0 && is_file($real)) {
  try {
   $deliver=require $real;
   if (!is_callable($deliver)) throw new RuntimeException('Reset adapter is invalid');
   $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'').':'.$phone);
   $s=$conn->prepare("INSERT INTO agpms_reset_requests(request_key,window_start,attempts) VALUES(?,NOW(),1) ON DUPLICATE KEY UPDATE attempts=IF(window_start<DATE_SUB(NOW(),INTERVAL 1 HOUR),1,attempts+1),window_start=IF(window_start<DATE_SUB(NOW(),INTERVAL 1 HOUR),NOW(),window_start)");$s->bind_param('s',$key);$s->execute();$s->close();
   $s=$conn->prepare('SELECT attempts FROM agpms_reset_requests WHERE request_key=?');$s->bind_param('s',$key);$s->execute();$attempts=$s->get_result()->fetch_assoc()['attempts'];$s->close();
   if ($attempts<=5) {
    $s=$conn->prepare("SELECT id FROM users WHERE phone=? AND status='active' AND approved=1");$s->bind_param('s',$phone);$s->execute();$u=$s->get_result()->fetch_assoc();$s->close();
    if ($u) {
     $token=bin2hex(random_bytes(32));$digest=hash('sha256',$token);
     $s=$conn->prepare('INSERT INTO agpms_password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))');$s->bind_param('is',$u['id'],$digest);$s->execute();$s->close();
     if ($deliver((int)$u['id'],$token)!==true) {
      $s=$conn->prepare('DELETE FROM agpms_password_resets WHERE token_hash=?');$s->bind_param('s',$digest);$s->execute();$s->close();
     }
    }
   }
  } catch (Throwable $e) { error_log('AGPMS recovery delivery unavailable.'); }
 }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account recovery</title><link rel="stylesheet" href="assets/css/bootstrap.min.css"></head><body><main class="container py-5"><h1>Account recovery</h1>
<?php if ($message): ?><p role="status"><?=htmlspecialchars($message)?></p><?php endif; ?>
<form method="POST"><?=csrf_field()?><label for="phone">Registered phone</label><input id="phone" name="phone" required maxlength="20"><button>Request recovery</button></form><a href="index.php">Home</a></main></body></html>
