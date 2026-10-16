<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
set_exception_handler(function(Throwable $error) { error_log('API request failed: '.$error->getMessage()); api_response(false,null,'Request temporarily unavailable.',500); });
function api_response(bool $success, $data=null, string $message='', int $status_code=200) {
 http_response_code($status_code);
 echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data], JSON_THROW_ON_ERROR); exit;
}
function get_api_input(): array {
 $type = strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]);
 if ($type === 'application/json') {
  try { $value=json_decode(file_get_contents('php://input'),true,64,JSON_THROW_ON_ERROR); }
  catch (JsonException $e) { api_response(false,null,'Invalid JSON.',400); }
  if (!is_array($value)) api_response(false,null,'Object payload required.',400);
  return $value;
 }
 return $_POST;
}
function authenticate_api_user($conn, array $allowed_roles=[]): array {
 $auth=$_SERVER['HTTP_AUTHORIZATION'] ?? '';
 if ($auth !== '') {
  if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$auth,$m)) api_response(false,null,'Unauthorized.',401);
  $hash=hash('sha256',$m[1]);
  $s=$conn->prepare("SELECT id,name,phone,role,status,approved,dues_status FROM users WHERE api_token_hash=? AND api_token_expires_at>NOW() AND status='active' AND approved=1");
  $s->bind_param('s',$hash);$s->execute();$user=$s->get_result()->fetch_assoc();$s->close();
 } else {
  // Session bootstrap verifies CSRF for cookie-authenticated POSTs.
  if (!defined('AGPMS_API_BOOTSTRAP')) define('AGPMS_API_BOOTSTRAP',true);
  require_once __DIR__.'/auth.php';
  $user=current_session_user();
  if (!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD','OPTIONS'],true) && !verify_csrf_token()) api_response(false,null,'Invalid CSRF token.',403);
 }
 if (!$user) api_response(false,null,'Unauthorized.',401);
 if ($allowed_roles && !in_array($user['role'],$allowed_roles,true)) api_response(false,null,'Forbidden.',403);
 return $user;
}
