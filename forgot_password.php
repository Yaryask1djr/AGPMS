<?php
// filepath: c:\xampp\htdocs\NewAGPMS\forgot_password.php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone']);
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        // Generate a reset token
        $token = bin2hex(random_bytes(16));
        $stmt->bind_result($user_id);
        $stmt->fetch();

        // Store token in DB (add a reset_token column to users table)
        $update = $conn->prepare("UPDATE users SET reset_token = ? WHERE id = ?");
        $update->bind_param("si", $token, $user_id);
        $update->execute();

        // Show reset link (for demo; in production, send via SMS/email)
        echo "Reset link: <a href='reset_password.php?token=$token'>Reset Password</a>";
    } else {
        echo "Phone number not found.";
    }
    $stmt->close();
}
?>
<form method="POST">
  <label>Enter your phone number:</label>
  <input type="text" name="phone" required class="form-control mb-2">
  <button type="submit" class="btn btn-primary">Request Reset</button>
</form>