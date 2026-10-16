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
    // Fetch user details for notification & logging
    $stmt = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($user_name, $role);
    $exists = $stmt->fetch();
    $stmt->close();

    if ($exists) {
        $update = $conn->prepare("UPDATE users SET approved = 1, status = 'active' WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();
        $update->close();

        // Send a welcome notification
        create_notification($conn, $id, "Account Approved", "Welcome to IKHE-GPMS! Your account has been approved by the administrator.");

        // Log admin activity
        log_admin_activity($conn, $_SESSION['user_id'], "Approve User", "Approved {$role} account for {$user_name} (ID: {$id})");

        header("Location: admin_dashboard.php?msg=" . urlencode("{$user_name} has been approved successfully."));
        exit();
    } else {
        header("Location: admin_dashboard.php?error=" . urlencode("User not found."));
        exit();
    }
}

header("Location: admin_dashboard.php");
exit();