<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") { http_response_code(405); exit; }
require_once __DIR__ . "/includes/auth.php";
require_role("security");
require_once 'includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'security') {
    header('Location: login_security.php');
    exit();
}

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
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
