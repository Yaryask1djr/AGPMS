<?php
// filepath: c:\xampp\htdocs\NewAGPMS\create_security.php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Only allow admin to create security accounts
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if phone already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "Phone number already registered.";
    } else {
        // Insert new security user
        $insert = $conn->prepare("INSERT INTO users (name, phone, password, role, status, approved) VALUES (?, ?, ?, 'security', 'approved', 1)");
        $insert->bind_param("sss", $name, $phone, $password);
        if ($insert->execute()) {
            // Get the new user's ID
            $user_id = $insert->insert_id;
            // Insert into residents table with role as 'security'
            $res_stmt = $conn->prepare("INSERT INTO residents (user_id, name, phone, role) VALUES (?, ?, ?, 'security')");
            $res_stmt->bind_param("iss", $user_id, $name, $phone);
            $res_stmt->execute();
            $res_stmt->close();

            echo "Security account created successfully.";
        } else {
            echo "Error creating security account.";
        }
        $insert->close();
    }
    $stmt->close();
}
?>