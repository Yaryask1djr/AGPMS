<?php
require_once __DIR__.'/includes/auth.php';
require_role('security');
require_once __DIR__.'/includes/scan_service.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {header('Allow: POST');http_response_code(405);exit;}
$input=$_POST;
$input['event_id']=$input['event_id']??vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex(random_bytes(16)),4));
$result=process_gate_scan($conn,current_session_user(),$input);
header('Location: security_dashboard.php?alert='.urlencode($result['message']),true,303);
