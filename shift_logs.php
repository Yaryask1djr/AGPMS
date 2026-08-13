<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login_admin.php");
    exit();
}
include 'includes/db.php';

$logs = $conn->query("
    SELECT s.*, u.name, u.phone 
    FROM shifts s 
    JOIN users u ON s.security_id = u.id 
    ORDER BY s.clock_in DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Shift Logs - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Security Shift Logs</h2>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Security Guard</th>
                            <th>Phone</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Status</th>
                            <th>Duration (Hrs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while ($s = $logs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($s['clock_in'])); ?></td>
                                <td><?php echo $s['clock_out'] ? date('M d, Y h:i A', strtotime($s['clock_out'])) : '-'; ?></td>
                                <td>
                                    <?php if ($s['status'] == 'active'): ?>
                                        <span class="badge bg-success">On Duty</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        if ($s['clock_out']) {
                                            $in = new DateTime($s['clock_in']);
                                            $out = new DateTime($s['clock_out']);
                                            $diff = $in->diff($out);
                                            echo $diff->h . 'h ' . $diff->i . 'm';
                                        } else {
                                            echo '...';
                                        }
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted">No shift logs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
