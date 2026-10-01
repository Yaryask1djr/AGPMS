<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
require_once 'includes/auth.php';
require_role('admin');
require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = intval($_POST['id'] ?? 0);
$token = $_POST['csrf_token'] ?? '';

if (!verify_csrf_token($token)) {
    http_response_code(403);
    die("Security verification failed (CSRF token missing or invalid).");
}

if ($id > 0) {
    // Prevent admin from revoking themselves
    if ($id === (int)$_SESSION['user_id']) {
        header("Location: admin_dashboard.php?error=" . urlencode("You cannot revoke your own administrator account."));
        exit();
    }

    $stmt = $conn->prepare("UPDATE users SET status = 'revoked' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    log_admin_activity($conn, $_SESSION['user_id'], "Revoke User", "Revoked account access for user ID: {$id}");
    header("Location: admin_dashboard.php?msg=" . urlencode("User account revoked successfully."));
    exit();
}

header("Location: admin_dashboard.php");
exit();
