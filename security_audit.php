<?php
include 'includes/auth.php';
require_role('admin');
include 'includes/db.php';
include 'includes/functions.php';

$sql = "SELECT vl.*, gp.visitor_name, gp.token, u.name AS resident_name, u.house_no, sec.name AS security_name
        FROM visit_logs vl
        JOIN gate_passes gp ON vl.pass_id = gp.id
        JOIN users u ON gp.user_id = u.id
        LEFT JOIN users sec ON vl.security_id = sec.id
        ORDER BY vl.check_in_time DESC";
$logs = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Security Audit Log - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Security Audit Log</h2>
    <div>
      <a href="download_audit.php" class="btn btn-primary me-2">Download Report</a>
      <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
  </div>
  <div class="card p-4 shadow-sm modern-table-wrapper">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Log ID</th>
          <th>Visitor Name</th>
          <th>Resident</th>
          <th>Check-in Time</th>
          <th>Check-out Time</th>
          <th>Processed By (Security)</th>
        </tr>
      </thead>
      <tbody>
        <?php while($row = $logs->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['id']) ?></td>
          <td><?= htmlspecialchars($row['visitor_name']) ?><br><small class="text-muted"><?= htmlspecialchars($row['token']) ?></small></td>
          <td><?= htmlspecialchars($row['resident_name']) ?> (<?= htmlspecialchars($row['house_no']) ?>)</td>
          <td><?= htmlspecialchars($row['check_in_time'] ?? 'N/A') ?></td>
          <td><?= htmlspecialchars($row['check_out_time'] ?? 'Not Checked Out') ?></td>
          <td><strong><?= htmlspecialchars($row['security_name'] ?? 'Unknown / Auto') ?></strong></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
