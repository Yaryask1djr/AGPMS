<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'security') {
    header('Location: login_security.php');
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sec_id = $_SESSION['user_id'];
    $now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE panic_alerts SET status = 'resolved', resolved_by = ?, resolved_at = ? WHERE id = ? AND status = 'active'");
    $stmt->bind_param("isi", $sec_id, $now, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: security_dashboard.php?alert=" . urlencode("Panic alert resolved."));
    exit();
}

header("Location: security_dashboard.php");
?>
