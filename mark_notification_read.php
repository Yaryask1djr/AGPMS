<?php
session_start();
include 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'resident') {
    echo json_encode(["success" => false]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['all'])) {
    // Mark all notifications as read for this user
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["success" => true]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false]);
}
?>
