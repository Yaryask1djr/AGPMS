<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
require_once 'includes/auth.php';
require_role('resident');
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$pass_id = intval($_POST['id'] ?? 0);
$token = $_POST['csrf_token'] ?? '';

if (!verify_csrf_token($token)) {
    http_response_code(403);
    die("Security verification failed (CSRF token missing or invalid).");
}

if ($pass_id > 0) {
    // Only allow deleting pending passes belonging to the user OR active recurring passes
    $stmt = $conn->prepare("UPDATE gate_passes SET status='revoked' WHERE id = ? AND user_id = ? AND (status = 'pending' OR (status = 'active' AND pass_type = 'recurring'))");
    $stmt->bind_param("ii", $pass_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        header("Location: resident_dashboard.php?msg=" . urlencode("Gate pass cancelled successfully."));
        exit();
    } else {
        header("Location: resident_dashboard.php?error=" . urlencode("Could not cancel pass. Pass may already be in use or checked in."));
        exit();
    }
}

header("Location: resident_dashboard.php");
exit();