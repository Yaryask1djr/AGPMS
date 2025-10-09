<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Define the standard admin phone number
$standard_admin_phone = '08012345678'; // <-- Replace with your actual admin phone

$error = "";


$demo_admin_username = "admin";
$demo_admin_password = "admin123";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $phone = sanitize($_POST['phone']);
  $password = $_POST['password'];

  if ($phone === $demo_admin_username && $password === $demo_admin_password) {
    $_SESSION['user_id'] = 0; // 0 or any fixed value for demo
    $_SESSION['role'] = 'admin';
    header("Location: admin_dashboard.php");
    exit();
  }

  // Only allow login for the standard admin phone as resident
  if ($phone === $standard_admin_phone) {
    $sql = "SELECT * FROM users WHERE phone = ? AND role = 'resident' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $user = $result->fetch_assoc();
      if (password_verify($password, $user['password']) && $password === $standard_admin_password) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = 'resident';
        header("Location: admin_dashboard.php"); // Redirect to admin dashboard
        exit();
      } else {
        $error = "Incorrect password.";
      }
    } else {
      $error = "Resident account not found.";
    }
    $stmt->close();
  } else if ($phone !== $demo_admin_username) {
    $error = "Access denied. Only the standard admin resident or demo admin can log in here.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
  <div class="container mt-5">
    <h2 class="text-center mb-4">Admin</h2>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="border p-4 rounded bg-light">
      <div class="mb-3">
        <label for="phone" class="form-label">Username</label>
        <input type="text" name="phone" class="form-control">
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
  </div>
</body>
</html>