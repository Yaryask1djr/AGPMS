<?php
// filepath: c:\xampp\htdocs\NewAGPMS\reset_password.php
include 'includes/db.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?");
        $stmt->bind_param("ss", $new_password, $token);
        if ($stmt->execute() && $stmt->affected_rows) {
            echo "Password reset successful. <a href='login.php'>Login</a>";
            exit;
        } else {
            echo "Invalid or expired token.";
        }
        $stmt->close();
    }
    ?>
    <form method="POST">
      <label>Enter new password:</label>
      <input type="password" name="password" required class="form-control mb-2">
      <button type="submit" class="btn btn-success">Reset Password</button>
    </form>
    <?php
} else {
    echo "No token provided.";
}
?>