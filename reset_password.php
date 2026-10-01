<?php
require_once __DIR__.'/includes/auth.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$token=$_GET['token']??'';$message='';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) {http_response_code(400);exit('Invalid recovery link.');}
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $password=$_POST['password']??'';
 if (!is_string($password) || strlen($password)<12 || strlen($password)>72) $message='Use a password between 12 and 72 bytes.';
 else {
  $digest=hash('sha256',$token);
  try {
   $conn->begin_transaction();
   $s=$conn->prepare('SELECT user_id FROM agpms_password_resets WHERE token_hash=? AND consumed_at IS NULL AND expires_at>NOW() FOR UPDATE');$s->bind_param('s',$digest);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();
   if (!$r) {$conn->rollback();$message='Invalid or expired recovery link.';}
   else {
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $s=$conn->prepare('UPDATE users SET password=?,auth_version=auth_version+1,api_token=NULL,api_token_hash=NULL,api_token_expires_at=NULL WHERE id=?');$s->bind_param('si',$hash,$r['user_id']);$s->execute();$s->close();
    $s=$conn->prepare('UPDATE agpms_password_resets SET consumed_at=NOW() WHERE user_id=? AND consumed_at IS NULL');$s->bind_param('i',$r['user_id']);$s->execute();$s->close();
    $conn->commit();$_SESSION=[];$message='Password changed. Please log in again.';
   }
  } catch (Throwable $e) {$conn->rollback();error_log('Password reset transaction failed.');$message='Recovery temporarily unavailable.';}
 }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset password</title></head><body><main><h1>Reset password</h1><p role="status"><?=htmlspecialchars($message)?></p><form method="POST"><?=csrf_field()?><label for="password">New password (12–72 bytes)</label><input type="password" id="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"><button>Change password</button></form><a href="login_resident.php">Log in</a></main></body></html>
