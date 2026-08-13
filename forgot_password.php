<?php
// filepath: c:\xampp\htdocs\NewAGPMS\forgot_password.php
include 'includes/db.php';
include 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - AGPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card modern-card">
      <h2 class="text-center">Forgot Password</h2>

      <?php
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
          $phone = sanitize($_POST['phone']);
          $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
          $stmt->bind_param("s", $phone);
          $stmt->execute();
          $stmt->store_result();

          if ($stmt->num_rows === 1) {
              $token = bin2hex(random_bytes(16));
              $stmt->bind_result($user_id);
              $stmt->fetch();

              $update = $conn->prepare("UPDATE users SET reset_token = ? WHERE id = ?");
              $update->bind_param("si", $token, $user_id);
              $update->execute();

              echo "<div class='alert alert-success'>Reset link: <a href='reset_password.php?token=$token'>Reset Password</a></div>";
          } else {
              echo "<div class='alert alert-danger'>Phone number not found.</div>";
          }
          $stmt->close();
      }
      ?>

      <form method="POST">
        <div class="mb-4">
          <label class="form-label">Enter your phone number</label>
          <input type="text" name="phone" required class="form-control" placeholder="Enter phone number">
        </div>
        <button type="submit" class="btn btn-primary w-100">Request Reset</button>
        <div class="text-center mt-3 d-flex justify-content-between">
          <a href="login_resident.php" class="text-muted text-decoration-none small">Back to Login</a>
          <a href="index.php" class="text-muted text-decoration-none small">Back to Home</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>