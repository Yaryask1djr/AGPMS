<?php
require_once __DIR__ . "/includes/auth.php";
require_role("admin");
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login_admin.php");
    exit();
}
require_once 'includes/db.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_id'])) {
    $c_id = intval($_POST['complaint_id']);
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $c_id);
    if ($stmt->execute()) {
        $msg = "Complaint status updated.";
    } else {
        $error = "Failed to update status.";
    }
    $stmt->close();
}

$complaints = $conn->query("
    SELECT c.*, u.name, u.house_no, u.phone 
    FROM complaints c 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaints - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Resident Complaints</h2>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if (isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>
        <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Resident</th>
                            <th>House</th>
                            <th>Subject</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($complaints->num_rows > 0): ?>
                            <?php while ($c = $complaints->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['name']); ?></td>
                                <td><?php echo htmlspecialchars($c['house_no']); ?></td>
                                <td><?php echo htmlspecialchars($c['subject']); ?></td>
                                <td><?php echo htmlspecialchars($c['description']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                                <td>
                                    <?php
                                        if ($c['status'] == 'pending') echo '<span class="badge bg-warning text-dark">Pending</span>';
                                        elseif ($c['status'] == 'in_progress') echo '<span class="badge bg-info text-dark">In Progress</span>';
                                        else echo '<span class="badge bg-success">Resolved</span>';
                                    ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-2">
<?php echo csrf_field(); ?>
                                        <input type="hidden" name="complaint_id" value="<?php echo $c['id']; ?>">
                                        <select name="status" class="form-select form-select-sm">
                                            <option value="pending" <?php if($c['status']=='pending') echo 'selected'; ?>>Pending</option>
                                            <option value="in_progress" <?php if($c['status']=='in_progress') echo 'selected'; ?>>In Progress</option>
                                            <option value="resolved" <?php if($c['status']=='resolved') echo 'selected'; ?>>Resolved</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted">No complaints found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
