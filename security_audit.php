<?php
require_once 'includes/auth.php';
require_role('admin');
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Pagination setup
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Search term
$search = trim($_GET['q'] ?? '');
$search_param = "%{$search}%";

if (!empty($search)) {
    // Count filtered
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total 
        FROM visit_logs vl
        JOIN gate_passes gp ON vl.pass_id = gp.id
        JOIN users u ON gp.user_id = u.id
        LEFT JOIN users sec ON vl.security_id = sec.id
        WHERE gp.visitor_name LIKE ? OR u.name LIKE ? OR gp.token LIKE ?");
    $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $count_stmt->execute();
    $total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    // Query filtered
    $stmt = $conn->prepare("SELECT vl.*, gp.visitor_name, gp.token, u.name AS resident_name, u.house_no, sec.name AS security_name
        FROM visit_logs vl
        JOIN gate_passes gp ON vl.pass_id = gp.id
        JOIN users u ON gp.user_id = u.id
        LEFT JOIN users sec ON vl.security_id = sec.id
        WHERE gp.visitor_name LIKE ? OR u.name LIKE ? OR gp.token LIKE ?
        ORDER BY vl.check_in_time DESC
        LIMIT ? OFFSET ?");
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $per_page, $offset);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    // Total count
    $total_rows = $conn->query("SELECT COUNT(*) as total FROM visit_logs")->fetch_assoc()['total'];

    // Query paginated
    $stmt = $conn->prepare("SELECT vl.*, gp.visitor_name, gp.token, u.name AS resident_name, u.house_no, sec.name AS security_name
        FROM visit_logs vl
        JOIN gate_passes gp ON vl.pass_id = gp.id
        JOIN users u ON gp.user_id = u.id
        LEFT JOIN users sec ON vl.security_id = sec.id
        ORDER BY vl.check_in_time DESC
        LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $per_page, $offset);
    $stmt->execute();
    $logs = $stmt->get_result();
}

$total_pages = max(1, ceil($total_rows / $per_page));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Security Audit Log - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm border-info border-start border-4">
    <h2 class="mb-0 brand-font text-info"><i class="bi bi-clipboard-data"></i> Security Audit Log</h2>
    <div class="d-flex gap-2">
      <a href="download_audit.php" class="btn btn-outline-primary rounded-pill"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV</a>
      <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>
  </div>

  <div class="card p-4 shadow-sm border-0 rounded-4">
    <!-- Filter Bar -->
    <form method="GET" class="row g-2 mb-4 align-items-center">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Search visitor, host resident, token..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary rounded-pill px-4">Search</button>
        <?php if (!empty($search)): ?>
          <a href="security_audit.php" class="btn btn-outline-secondary rounded-pill px-3">Clear</a>
        <?php endif; ?>
      </div>
      <div class="col text-end text-muted small">
        Showing <?php echo min($offset + 1, $total_rows); ?> - <?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?> entries
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-dark">
          <tr>
            <th>Log ID</th>
            <th>Visitor Name</th>
            <th>Resident (House)</th>
            <th>Check-in Time</th>
            <th>Check-out Time</th>
            <th>Gate Security</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($logs && $logs->num_rows > 0): ?>
            <?php while($row = $logs->fetch_assoc()): ?>
            <tr>
              <td><span class="badge bg-secondary">#<?= htmlspecialchars($row['id']) ?></span></td>
              <td>
                <strong><?= htmlspecialchars($row['visitor_name']) ?></strong><br>
                <code class="small text-muted"><?= htmlspecialchars($row['token']) ?></code>
              </td>
              <td><?= htmlspecialchars($row['resident_name']) ?> <span class="badge bg-light text-dark border">House <?= htmlspecialchars($row['house_no']) ?></span></td>
              <td><?= htmlspecialchars($row['check_in_time'] ? date('M d, Y h:i A', strtotime($row['check_in_time'])) : 'N/A') ?></td>
              <td>
                <?php if ($row['check_out_time']): ?>
                  <span class="text-success"><i class="bi bi-check-circle"></i> <?= date('M d, Y h:i A', strtotime($row['check_out_time'])) ?></span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> On-Site</span>
                <?php endif; ?>
              </td>
              <td><strong><?= htmlspecialchars($row['security_name'] ?? 'Main Gate Security') ?></strong></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" class="text-center py-4 text-muted">No audit logs found matching your criteria.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
    <nav class="d-flex justify-content-center mt-4">
      <ul class="pagination pagination-rounded">
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>"><i class="bi bi-chevron-left"></i> Previous</a>
        </li>
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
          <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>">Next <i class="bi bi-chevron-right"></i></a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
