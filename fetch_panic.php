<?php
session_start();
include 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'security') {
    echo json_encode([]);
    exit();
}

$result = $conn->query("SELECT pa.*, u.name, u.house_no FROM panic_alerts pa JOIN users u ON pa.user_id = u.id WHERE pa.status = 'active' ORDER BY pa.created_at DESC");
$alerts = [];
while ($row = $result->fetch_assoc()) {
    $alerts[] = $row;
}

echo json_encode($alerts);
?>
