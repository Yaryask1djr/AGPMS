<?php
// filepath: c:\xampp\htdocs\NewAGPMS\create_security.php
require_once __DIR__ . "/includes/auth.php";
require_role("admin");
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Only allow admin to create security accounts
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied.";
    exit();
}

$alert = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if phone already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $alert = "Phone number already registered.";
        $alert_type = "error";
    } else {
        // Insert new security user (approved and active)
        $insert = $conn->prepare("INSERT INTO users (name, phone, password, role, status, approved) VALUES (?, ?, ?, 'security', 'active', 1)");
        $insert->bind_param("sss", $name, $phone, $password);
        if ($insert->execute()) {
            $_SESSION['new_user_name'] = $name;
            $_SESSION['new_user_phone'] = $phone;
            $_SESSION['new_user_role'] = 'security';
            header("Location: creation_success.php");
            exit();
        } else {
            $alert = "Error creating security account.";
            $alert_type = "error";
        }
        $insert->close();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Security Account - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
  
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm border-danger border-start border-4">
      <h2 class="mb-0 brand-font text-danger"><i class="bi bi-person-plus-fill"></i> Register Security Personnel</h2>
      <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if ($alert): ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?php echo $alert_type; ?>',
                title: '<?php echo htmlspecialchars($alert); ?>',
                showConfirmButton: false,
                timer: 4000
            });
        });
      </script>
    <?php endif; ?>

    <div class="row justify-content-center mt-5">
        <div class="col-md-6">
            <div class="card glass-card hover-lift p-4 shadow-lg border-0">
                <form method="POST">
<?php echo csrf_field(); ?>
                    <div class="mb-4 text-center">
                        <i class="bi bi-shield-lock text-danger" style="font-size: 3rem;"></i>
                        <h4 class="brand-font mt-2">New Guard Profile</h4>
                        <p class="text-muted small">Create credentials for a new security guard to access the terminal.</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-danger"><i class="bi bi-person"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="e.g., Musa Ibrahim" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone Number (Username)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-danger"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="phone" class="form-control" placeholder="e.g., 08012345678" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-danger"><i class="bi bi-key"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Create a strong password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 py-2 fw-bold rounded-pill shadow-sm hover-lift"><i class="bi bi-person-check-fill me-2"></i> Register Security Guard</button>
                </form>
            </div>
        </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>