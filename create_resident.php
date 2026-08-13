<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Only allow admin to create resident accounts
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied.";
    exit();
}

$alert = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $house_no = sanitize($_POST['house_no']);
    $phone = sanitize($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // House limits check
    $house_val = intval($house_no);
    $slot_sql = "SELECT id FROM users WHERE house_no = ?";
    $slot_stmt = $conn->prepare($slot_sql);
    $slot_stmt->bind_param("s", $house_no);
    $slot_stmt->execute();
    $slot_stmt->store_result();

    if ($house_val < 1 || $house_val > 99) {
        $alert = "Invalid house number.";
        $alert_type = "error";
    } elseif ($slot_stmt->num_rows >= 3) {
        $alert = "House already has 3 registered users.";
        $alert_type = "error";
    } else {
        // Check if phone already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $alert = "Phone number already registered.";
            $alert_type = "error";
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
                
                $_SESSION['new_user_name'] = $name;
                $_SESSION['new_user_phone'] = $phone;
                $_SESSION['new_user_role'] = 'resident';
                $_SESSION['new_user_house'] = $house_no;
                header("Location: creation_success.php");
                exit();
            } else {
                $alert = "Error creating resident account.";
                $alert_type = "error";
            }
            $insert->close();
        }
        $stmt->close();
    }
    $slot_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Resident Account - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
  
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm border-primary border-start border-4">
      <h2 class="mb-0 brand-font text-primary"><i class="bi bi-person-plus-fill"></i> Register New Resident</h2>
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
                    <div class="mb-4 text-center">
                        <i class="bi bi-house-door text-primary" style="font-size: 3rem;"></i>
                        <h4 class="brand-font mt-2">Resident Profile</h4>
                        <p class="text-muted small">Create credentials for a new resident to access the portal.</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-primary"><i class="bi bi-person"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="e.g., John Doe" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">House Number (1-99)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-primary"><i class="bi bi-house"></i></span>
                            <input type="number" name="house_no" class="form-control" min="1" max="99" placeholder="e.g., 42" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone Number (Username)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-primary"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="phone" class="form-control" placeholder="e.g., 08012345678" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-primary"><i class="bi bi-key"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Create a strong password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-pill shadow-sm hover-lift"><i class="bi bi-person-check-fill me-2"></i> Register Resident</button>
                </form>
            </div>
        </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>