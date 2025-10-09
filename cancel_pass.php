<?php
// filepath: c:\xampp\htdocs\NewAGPMS\cancel_pass.php
include 'includes/auth.php';
require_role('resident');
include 'includes/db.php';

$user_id = $_SESSION['user_id'];
$pass_id = intval($_GET['id'] ?? 0);

// Only allow deleting pending passes belonging to the user
$stmt = $conn->prepare("DELETE FROM gate_passes WHERE id = ? AND user_id = ? AND status = 'pending'");
$stmt->bind_param("ii", $pass_id, $user_id);

if ($stmt->execute()) {
    header("Location: resident_dashboard.php?msg=Pass+cancelled+successfully");
    exit();
} else {
    echo "Error cancelling pass.";
}