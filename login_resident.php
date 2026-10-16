<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Redirect if already logged in as resident
if (is_logged_in() && $_SESSION['role'] === 'resident') {
    header("Location: resident_dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'] ?? '';

    $sql = "SELECT * FROM users WHERE phone = ? AND role = 'resident' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($user['status'] !== 'active' || (int)$user['approved'] !== 1) {
            $error = "Your account has been revoked. Please contact estate administration.";
        } elseif (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['auth_version'] = (int)$user['auth_version'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['role'] = 'resident';
            $_SESSION['user_name'] = $user['name'];
            header("Location: resident_dashboard.php");
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "Resident account not found with this phone number.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Login - AGPMS</title>
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
        max-width: 420px;
        padding: 2.5rem;
    }
  </style>
</head>
<body>
  <div class="auth-card glass-card hover-lift">
    <div class="text-center mb-4">
        <i class="bi bi-house-door-fill text-primary" style="font-size: 3rem;"></i>
        <h3 class="brand-font text-white mt-2">Resident Portal</h3>
        <p class="text-muted small">Welcome back to IKHE-GPMS</p>
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
                timer: 3500
            });
        });
      </script>
    <?php endif; ?>

    <form method="POST">
      <?php echo csrf_field(); ?>
      <div class="mb-3">
        <label for="phone" class="form-label text-light">Phone Number</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-primary"><i class="bi bi-telephone"></i></span>
            <input type="text" name="phone" id="phone" class="form-control bg-dark border-secondary text-light" placeholder="e.g. 08012345678" required autocomplete="username">
        </div>
      </div>
      <div class="mb-4">
        <label for="password" class="form-label text-light">Password</label>
        <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-primary"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="password" class="form-control bg-dark border-secondary text-light" placeholder="Enter password" required autocomplete="current-password">
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold hover-lift shadow">Login to Dashboard</button>
      <div class="text-center mt-4 d-flex flex-column gap-2">
        <a href="forgot_password.php" class="text-info text-decoration-none small">Forgot Password?</a>
        <a href="register.php" class="text-white-50 text-decoration-none small">New resident? <span class="text-info fw-bold">Register here</span></a>
        <a href="index.php" class="text-secondary text-decoration-none small"><i class="bi bi-arrow-left"></i> Back to Home</a>
      </div>
    </form>
  </div>
</body>
</html>
