<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

$error = "";

// Demo security credentials
$demo_security_username = "security";
$demo_security_password = "security123";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $phone = sanitize($_POST['phone']);
  $password = $_POST['password'];

  // Demo security login (bypass database)
  if ($phone === $demo_security_username && $password === $demo_security_password) {
    $_SESSION['user_id'] = 0; // or any fixed value for demo
    $_SESSION['role'] = 'security';
    header("Location: security_dashboard.php");
    exit();
  }

  // Query for security user
  $sql = "SELECT * FROM users WHERE phone = ? AND role = 'security' LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $phone);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    if ($user['approved'] != 1) {
      $error = "Account not yet approved by admin.";
    } elseif (password_verify($password, $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['role'] = 'security';
      header("Location: security_dashboard.php");
      exit();
    } else {
      $error = "Incorrect password.";
    }
  } else {
    $error = "Security account not found.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Security Login - AGPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
        background: url('T-IKHEDA.jpg') no-repeat center center fixed;
        background-size: cover;
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    body::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(135deg, rgba(30,30,40,0.95) 0%, rgba(15,15,20,0.85) 100%);
        z-index: -1;
    }
    .auth-card {
        width: 100%;
        max-width: 400px;
        padding: 2.5rem;
    }
  </style>
</head>
<body>
  <div class="auth-card glass-card hover-lift border-warning">
    <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill text-warning" style="font-size: 3rem;"></i>
        <h3 class="brand-font text-warning mt-2">Security Post</h3>
        <p class="text-muted small">IKHE-GPMS Access Terminal</p>
    </div>

    <?php if ($error): ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: '<?php echo htmlspecialchars($error); ?>',
                showConfirmButton: false,
                timer: 3000
            });
        });
      </script>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label for="phone" class="form-label text-light">Phone Number</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-warning text-warning"><i class="bi bi-telephone"></i></span>
            <input type="text" name="phone" class="form-control bg-dark border-warning text-light" placeholder="Enter phone number" required>
        </div>
      </div>
      <div class="mb-4">
        <label for="password" class="form-label text-light">Password</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-warning text-warning"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" class="form-control bg-dark border-warning text-light" placeholder="Enter password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-warning w-100 rounded-pill fw-bold hover-lift shadow">Access Terminal</button>
      <div class="text-center mt-4">
        <a href="index.php" class="text-secondary text-decoration-none small"><i class="bi bi-arrow-left"></i> Back to Home</a>
      </div>
    </form>
  </div>
</body>
</html>
