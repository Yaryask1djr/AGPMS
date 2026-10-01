<?php
require_once 'includes/auth.php';
require_role('admin');
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Pagination setup
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$total_shifts = $conn->query("SELECT COUNT(*) as total FROM shifts")->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_shifts / $per_page));

$stmt = $conn->prepare("
    SELECT s.*, u.name, u.phone 
    FROM shifts s 
    JOIN users u ON s.security_id = u.id 
    ORDER BY s.clock_in DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$logs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Shift Logs - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm border-secondary border-start border-4">
            <h2 class="mb-0 brand-font text-secondary"><i class="bi bi-clock-history"></i> Security Shift Logs</h2>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left"></i> Dashboard</a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Security Guard</th>
                            <th>Phone</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Status</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs && $logs->num_rows > 0): ?>
                            <?php while ($s = $logs->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($s['clock_in'])); ?></td>
                                <td><?php echo $s['clock_out'] ? date('M d, Y h:i A', strtotime($s['clock_out'])) : '<span class="badge bg-warning text-dark">Active on Duty</span>'; ?></td>
                                <td>
                                    <?php if ($s['status'] == 'active'): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-shield-fill-check"></i> On Duty</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary rounded-pill px-3 py-2">Completed</span>
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
                                            echo '<span class="text-muted">Ongoing...</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No security shift records found yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="d-flex justify-content-center mt-4">
              <ul class="pagination">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?page=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i> Previous</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                  <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?page=<?= $page + 1 ?>">Next <i class="bi bi-chevron-right"></i></a>
                </li>
              </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
