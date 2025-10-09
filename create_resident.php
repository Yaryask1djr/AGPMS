<?php
session_start();
include 'includes/db.php';

// Only allow admin to create resident accounts
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $house_no = trim($_POST['house_no']);
    $phone = trim($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if phone already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Redirect back with error
        header("Location: admin_dashboard.php?error=Phone+number+already+registered");
        exit();
    } else {
        // Insert new resident user (approved by default)
        $insert = $conn->prepare("INSERT INTO users (name, house_no, phone, password, role, status, approved) VALUES (?, ?, ?, ?, 'resident', 'approved', 1)");
        $insert->bind_param("ssss", $name, $house_no, $phone, $password);
        if ($insert->execute()) {
            // Optionally, add to residents table if you use it
            $user_id = $insert->insert_id;
            if ($conn->query("SHOW TABLES LIKE 'residents'")->num_rows) {
                $res_stmt = $conn->prepare("INSERT INTO residents (user_id, name, house_no, phone) VALUES (?, ?, ?, ?)");
                $res_stmt->bind_param("isss", $user_id, $name, $house_no, $phone);
                $res_stmt->execute();
                $res_stmt->close();
            }
            header("Location: admin_dashboard.php?msg=Resident+account+created+successfully");
            $insert->close();
            $stmt->close();
            exit();
        } else {
            header("Location: admin_dashboard.php?error=Error+creating+resident+account");
            $insert->close();
            $stmt->close();
            exit();
        }
    }
}
?>