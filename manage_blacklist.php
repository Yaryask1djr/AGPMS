<?php
require_once 'includes/auth.php';
require_role('admin');
require_once 'includes/db.php';
require_once 'includes/functions.php';

$admin_id = $_SESSION['user_id'];
$alert = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone']);
        $reason = sanitize($_POST['reason']);
        
        $stmt = $conn->prepare("INSERT INTO blacklist (name, phone, reason, added_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $phone, $reason, $admin_id);
        if ($stmt->execute()) {
            $alert = "<div class='alert alert-success'>Successfully added to blacklist.</div>";
        } else {
            $alert = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'remove') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM blacklist WHERE id = $id");
        $alert = "<div class='alert alert-success'>Successfully removed from blacklist.</div>";
    }
}

$blacklist_items = $conn->query("SELECT * FROM blacklist ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">`n  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Blacklist - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container mt-5">
  <h2>Manage Watchlist / Blacklist</h2>
  <?= $alert ?>
  <div class="row">
    <div class="col-md-4">
      <div class="card p-3 shadow-sm">
        <h5>Add to Blacklist</h5>
        <form method="POST">
<?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <textarea name="reason" class="form-control" required></textarea>
          </div>
          <button type="submit" class="btn btn-danger w-100">Block Number</button>
        </form>
      </div>
      <a href="admin_dashboard.php" class="btn btn-secondary mt-3 w-100">Back to Dashboard</a>
    </div>
    <div class="col-md-8">
      <div class="card p-3 shadow-sm">
        <h5>Blocked Numbers</h5>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Name</th>
              <th>Phone</th>
              <th>Reason</th>
              <th>Added</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $blacklist_items->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['phone']) ?></td>
              <td><?= htmlspecialchars($row['reason']) ?></td>
              <td><?= $row['created_at'] ?></td>
              <td>
                <form method="POST" class="d-inline">
<?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <button class="btn btn-sm btn-outline-success">Unblock</button>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if($blacklist_items->num_rows === 0): ?>
            <tr><td colspan="5" class="text-center">No numbers currently blacklisted.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
