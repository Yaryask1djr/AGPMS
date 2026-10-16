<?php
require_once 'includes/auth.php';
require_role('resident');
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$alert = '';

// Fetch current info
$stmt = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $password = (string)($_POST['password'] ?? '');
    
    if (!empty($password)) {
        // Update with password
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET name = ?, phone = ?, password = ?, auth_version = auth_version + 1, api_token_hash = NULL, api_token = NULL WHERE id = ?");
        $update->bind_param("sssi", $name, $phone, $hashed, $user_id);
    } else {
        // Update without password
        $update = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $update->bind_param("ssi", $name, $phone, $user_id);
    }
    
    if ($update->execute()) {
        $alert = "<div class='alert alert-success'>Profile updated successfully!</div>";
        if (!empty($password)) { $_SESSION['auth_version']++; }
        $_SESSION['user_name'] = $name; // Update session
        $user['name'] = $name;
        $user['phone'] = $phone;
    } else {
        $alert = "<div class='alert alert-danger'>Error updating profile.</div>";
    }
    $update->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">`n  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Settings - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container mt-5" style="max-width: 600px;">
  <div class="card p-4 shadow-sm">
    <h3 class="mb-4">Profile Settings</h3>
    <?= $alert ?>
    <form method="POST">
<?php echo csrf_field(); ?>
      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Phone Number</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">New Password (leave blank to keep current)</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••">
      </div>
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="resident_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
