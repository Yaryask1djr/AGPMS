<?php
require_once __DIR__ . "/includes/auth.php";
require_role("resident");
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_role('resident');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "error" => "Invalid method"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$message = isset($_POST['message']) ? sanitize($_POST['message']) : 'Emergency SOS triggered!';

// Get house_no
$stmt = $conn->prepare("SELECT house_no FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($house_no);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("INSERT INTO panic_alerts (user_id, house_no, message, status) VALUES (?, ?, ?, 'active')");
$stmt->bind_param("iss", $user_id, $house_no, $message);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Database error"]);
}
$stmt->close();
?>
