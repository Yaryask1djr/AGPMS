<?php
// filepath: c:\xampp\htdocs\NewAGPMS\reset_password.php
include 'includes/db.php';

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - AGPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card modern-card">
      <h2 class="text-center">Reset Password</h2>
<?php
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?");
        $stmt->bind_param("ss", $new_password, $token);
        if ($stmt->execute() && $stmt->affected_rows) {
            echo "<div class='alert alert-success'>Password reset successful. <a href='login_resident.php'>Login</a></div>";
            exit;
        } else {
            echo "<div class='alert alert-danger'>Invalid or expired token.</div>";
        }
        $stmt->close();
    }
    ?>
    <form method="POST">
      <div class="mb-4">
        <label class="form-label">Enter new password</label>
        <input type="password" name="password" required class="form-control" placeholder="New password">
      </div>
      <button type="submit" class="btn btn-primary w-100">Reset Password</button>
    </form>
    <?php
} else {
    echo "<div class='alert alert-warning text-center'>No token provided.</div>";
}
?>
    </div>
  </div>
</body>
</html>