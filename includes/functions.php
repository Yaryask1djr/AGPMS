<?php
function sanitize($data) {
  return htmlspecialchars(trim($data));
}

function log_admin_activity($conn, $admin_id, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $admin_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}

function create_notification($conn, $user_id, $title, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $title, $message);
    $stmt->execute();
    $stmt->close();
}
?>
