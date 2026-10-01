<?php
require_once 'includes/auth.php';
require_role('admin');
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Resolve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_id'])) {
    require_csrf();
    $iid = intval($_POST['resolve_id']);
    $new_status = in_array($_POST['new_status'] ?? '', ['investigating', 'resolved']) ? $_POST['new_status'] : 'resolved';
    $now = date('Y-m-d H:i:s');
    $adm = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE incident_reports SET status = ?, resolved_by = ?, resolved_at = ? WHERE id = ?");
    $stmt->bind_param("sisi", $new_status, $adm, $now, $iid);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_incidents.php?msg=" . urlencode("Incident #$iid status updated to $new_status."));
    exit();
}

$page = max(1, intval($_GET['page'] ?? 1));
$per = 15;
$offset = ($page - 1) * $per;
$filter = $_GET['status'] ?? '';

if (!empty($filter)) {
    $count = $conn->prepare("SELECT COUNT(*) FROM incident_reports WHERE status = ?");
    $count->bind_param("s", $filter);
    $count->execute();
    $total = $count->get_result()->fetch_row()[0];
    $count->close();
    $stmt = $conn->prepare("SELECT ir.*, u.name as guard_name, u.phone as guard_phone FROM incident_reports ir JOIN users u ON ir.security_id = u.id WHERE ir.status = ? ORDER BY ir.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("sii", $filter, $per, $offset);
} else {
    $total = $conn->query("SELECT COUNT(*) FROM incident_reports")->fetch_row()[0];
    $stmt = $conn->prepare("SELECT ir.*, u.name as guard_name, u.phone as guard_phone FROM incident_reports ir JOIN users u ON ir.security_id = u.id ORDER BY ir.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $per, $offset);
}
$stmt->execute();
$incidents = $stmt->get_result();
$total_pages = max(1, ceil($total / $per));

$severity_badge = ['low' => 'success', 'medium' => 'warning text-dark', 'high' => 'danger', 'critical' => 'dark'];
$status_badge = ['reported' => 'info text-dark', 'investigating' => 'warning text-dark', 'resolved' => 'success'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Incidents - AGPMS Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
<div class="container mt-4 mb-5">

  <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm border-danger border-start border-4">
    <h2 class="mb-0 brand-font text-danger"><i class="bi bi-shield-exclamation"></i> Security Incidents</h2>
    <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3"><i class="bi bi-arrow-left"></i> Dashboard</a>
  </div>

  <?php if (isset($_GET['msg'])): ?>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '<?php echo htmlspecialchars($_GET['msg']); ?>', timer: 4000, showConfirmButton: false });
    });
  </script>
  <?php endif; ?>

  <!-- Filter tabs -->
  <div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="manage_incidents.php" class="btn btn-sm rounded-pill px-3 <?php echo empty($filter) ? 'btn-dark' : 'btn-outline-secondary'; ?>">All</a>
    <a href="?status=reported" class="btn btn-sm rounded-pill px-3 <?php echo $filter === 'reported' ? 'btn-info text-dark' : 'btn-outline-info'; ?>"><i class="bi bi-flag-fill"></i> Reported</a>
    <a href="?status=investigating" class="btn btn-sm rounded-pill px-3 <?php echo $filter === 'investigating' ? 'btn-warning text-dark' : 'btn-outline-warning'; ?>"><i class="bi bi-search"></i> Investigating</a>
    <a href="?status=resolved" class="btn btn-sm rounded-pill px-3 <?php echo $filter === 'resolved' ? 'btn-success' : 'btn-outline-success'; ?>"><i class="bi bi-check2-circle"></i> Resolved</a>
  </div>

  <div class="card glass-card p-4 shadow-sm border-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Guard</th>
            <th>Incident Title</th>
            <th>Category</th>
            <th>Severity</th>
            <th>Status</th>
            <th>Filed</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($incidents && $incidents->num_rows > 0): ?>
            <?php while ($inc = $incidents->fetch_assoc()): ?>
            <tr>
              <td><span class="badge bg-secondary">#<?php echo $inc['id']; ?></span></td>
              <td>
                <strong><?php echo htmlspecialchars($inc['guard_name']); ?></strong><br>
                <small class="text-muted"><?php echo htmlspecialchars($inc['guard_phone']); ?></small>
              </td>
              <td>
                <strong><?php echo htmlspecialchars($inc['title']); ?></strong><br>
                <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($inc['description'], 0, 80, '...')); ?></small>
              </td>
              <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $inc['category']))); ?></span></td>
              <td><span class="badge bg-<?php echo $severity_badge[$inc['severity']] ?? 'secondary'; ?> rounded-pill px-3"><?php echo strtoupper($inc['severity']); ?></span></td>
              <td><span class="badge bg-<?php echo $status_badge[$inc['status']] ?? 'secondary'; ?> rounded-pill px-3"><?php echo ucfirst($inc['status']); ?></span></td>
              <td><?php echo date('M d, Y h:i A', strtotime($inc['created_at'])); ?></td>
              <td class="text-end">
                <?php if ($inc['status'] !== 'resolved'): ?>
                <form method="POST" class="d-inline">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="resolve_id" value="<?php echo $inc['id']; ?>">
                  <select name="new_status" class="form-select form-select-sm d-inline w-auto mb-1">
                    <option value="investigating">Investigating</option>
                    <option value="resolved">Resolved</option>
                  </select>
                  <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Update</button>
                </form>
                <?php else: ?>
                  <span class="text-success small"><i class="bi bi-check-circle-fill"></i> Resolved</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">No incidents found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="d-flex justify-content-center mt-4">
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
          <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $filter; ?>"><i class="bi bi-chevron-left"></i></a>
        </li>
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
          <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $filter; ?>"><?php echo $i; ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
          <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $filter; ?>"><i class="bi bi-chevron-right"></i></a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
