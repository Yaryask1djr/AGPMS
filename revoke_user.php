<?php
include 'includes/auth.php';
require_role('admin');
include 'includes/db.php';

$id = intval($_GET['id']);
$conn->query("UPDATE users SET status = 'revoked' WHERE id = $id");
header("Location: admin_dashboard.php");
exit();
