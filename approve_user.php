<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Only allow admin to approve users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    // Get the user's role
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();

    // Approve the user
    $stmt = $conn->prepare("UPDATE users SET approved = 1, status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Redirect based on role
    if ($role === 'security') {
        header("Location: login_security.php");
        exit();
    } else {
        header("Location: admin_dashboard.php?msg=Resident+approved+successfully");
        exit();
    }
} else {
    echo "<div class='alert alert-warning'>No user specified.</div>";
}
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">