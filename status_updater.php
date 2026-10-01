<?php
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
// Can be run via cron job or manually via browser
require_once 'includes/db.php';

$now = date('Y-m-d H:i:s');
$conn->query("UPDATE gate_passes SET status = 'expired' WHERE status = 'active' AND exit_time < '$now'");
$conn->query("UPDATE gate_passes SET status = 'active' WHERE status = 'pending' AND entry_time <= '$now' AND exit_time > '$now'");
echo "Pass statuses updated successfully.";
