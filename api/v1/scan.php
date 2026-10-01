<?php
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/api_helper.php';
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/scan_service.php';
$user=authenticate_api_user($conn,['security','admin']);
if ($_SERVER['REQUEST_METHOD']!=='POST') {header('Allow: POST');api_response(false,null,'Method not allowed.',405);}
$input=get_api_input();
if (isset($input['batch'])) {
 if (!is_array($input['batch']) || count($input['batch'])>100) api_response(false,null,'Batch limit is 100.',422);
 $results=[];
 foreach($input['batch'] as $item) {
  $results[]=is_array($item)?process_gate_scan($conn,$user,$item):['success'=>false,'acknowledged'=>false,'message'=>'Invalid event.'];
 }
 api_response(true,['synced'=>$results],'Per-event results returned.');
}
$r=process_gate_scan($conn,$user,$input);
api_response($r['acknowledged'],$r,$r['message'],$r['acknowledged']?200:422);
