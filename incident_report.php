<?php
require_once 'includes/auth.php';
require_role('security');
require_once 'includes/db.php';
require_once 'includes/functions.php';

$sec_id = (int)$_SESSION['user_id'];
$sec_name = $_SESSION['sec_name'] ?? 'Security';
$alert = '';
$alert_type = 'success';

// Handle POST: file new incident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    require_csrf();
    $title = sanitize($_POST['title']);
    $category = in_array($_POST['category'], ['trespassing', 'gate_damage', 'suspicious_activity', 'argument', 'other']) ? $_POST['category'] : 'other';
    $severity = in_array($_POST['severity'], ['low', 'medium', 'high', 'critical']) ? $_POST['severity'] : 'medium';
    $description = sanitize($_POST['description']);

    if (!empty($title) && !empty($description)) {
        $stmt = $conn->prepare("INSERT INTO incident_reports (security_id, title, category, description, severity, status) VALUES (?, ?, ?, ?, ?, 'reported')");
        $stmt->bind_param("issss", $sec_id, $title, $category, $description, $severity);
        if ($stmt->execute()) {
            // Notify admins for high/critical
            if ($severity === 'high' || $severity === 'critical') {
                $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'");
                while ($adm = $admins->fetch_assoc()) {
                    create_notification($conn, (int)$adm['id'], "🚨 Urgent Incident: {$title}", "Severity: " . strtoupper($severity) . " filed by {$sec_name}.");
                }
            }
            $alert = "Incident report filed successfully.";
            $alert_type = 'success';
        } else {
            $alert = "Failed to file incident: " . $stmt->error;
            $alert_type = 'danger';
        }
        $stmt->close();
    } else {
        $alert = "Title and description are required.";
        $alert_type = 'warning';
    }
}

// Fetch my recent incidents
$incidents = $conn->prepare("SELECT * FROM incident_reports WHERE security_id = ? ORDER BY created_at DESC LIMIT 20");
$incidents->bind_param("i", $sec_id);
$incidents->execute();
$my_incidents = $incidents->get_result();
$incidents->close();

$severity_badge = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'critical' => 'dark'];
$status_badge = ['reported' => 'info', 'investigating' => 'warning', 'resolved' => 'success'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Report Incident - AGPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
<div class="container mt-4 mb-5">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm border-danger border-start border-4">
    <h2 class="mb-0 brand-font text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Incident Report</h2>
    <div class="d-flex gap-2">
      <span class="badge bg-secondary rounded-pill px-3 py-2"><i class="bi bi-shield-fill"></i> <?php echo htmlspecialchars($sec_name); ?></span>
      <a href="security_dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3"><i class="bi bi-arrow-left"></i> HUD</a>
    </div>
  </div>

  <?php if ($alert): ?>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      Swal.fire({ toast: true, position: 'top-end', icon: '<?php echo $alert_type; ?>', title: '<?php echo htmlspecialchars($alert); ?>', timer: 4000, showConfirmButton: false });
    });
  </script>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Form -->
    <div class="col-lg-5">
      <div class="card glass-card shadow-sm border-0 p-4 hover-lift">
        <h5 class="brand-font mb-4 text-danger"><i class="bi bi-pen-fill"></i> File New Report</h5>
        <form method="POST" novalidate>
          <?php echo csrf_field(); ?>
          <div class="mb-3">
            <label class="form-label fw-bold">Incident Title *</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Unauthorised vehicle at gate" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Category</label>
            <select name="category" class="form-select">
              <option value="trespassing">Trespassing</option>
              <option value="gate_damage">Gate Damage</option>
              <option value="suspicious_activity" selected>Suspicious Activity</option>
              <option value="argument">Argument / Altercation</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Severity Level</label>
            <select name="severity" class="form-select">
              <option value="low">🟢 Low</option>
              <option value="medium" selected>🟡 Medium</option>
              <option value="high">🔴 High</option>
              <option value="critical">⚫ Critical — Notify Admin Immediately</option>
            </select>
            <small class="text-muted">High/Critical alerts will notify estate admin instantly.</small>
          </div>
          <div class="mb-4">
            <label class="form-label fw-bold">Description *</label>
            <textarea name="description" class="form-control" rows="5" placeholder="Describe the incident in detail: time, people involved, actions taken..." required></textarea>
          </div>
          <button type="submit" class="btn btn-danger w-100 rounded-pill fw-bold py-2 hover-lift">
            <i class="bi bi-send-fill me-2"></i> Submit Report
          </button>
        </form>
      </div>
    </div>

    <!-- My Recent Incidents -->
    <div class="col-lg-7">
      <div class="card glass-card shadow-sm border-0 p-4">
        <h5 class="brand-font mb-4"><i class="bi bi-clock-history text-warning"></i> My Recent Reports</h5>
        <?php if ($my_incidents && $my_incidents->num_rows > 0): ?>
          <div class="d-flex flex-column gap-3">
          <?php while ($inc = $my_incidents->fetch_assoc()): ?>
            <div class="card border-0 shadow-sm rounded-3 p-3">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                  <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($inc['title']); ?></h6>
                  <p class="text-muted small mb-2"><?php echo htmlspecialchars(ucfirst($inc['category'])); ?> · <?php echo date('M d, Y h:i A', strtotime($inc['created_at'])); ?></p>
                  <p class="mb-0 small"><?php echo htmlspecialchars(mb_strimwidth($inc['description'], 0, 120, '...')); ?></p>
                </div>
                <div class="d-flex flex-column gap-1 align-items-end">
                  <span class="badge bg-<?php echo $severity_badge[$inc['severity']] ?? 'secondary'; ?> rounded-pill px-3">
                    <?php echo strtoupper($inc['severity']); ?>
                  </span>
                  <span class="badge bg-<?php echo $status_badge[$inc['status']] ?? 'secondary'; ?> text-dark rounded-pill px-3">
                    <?php echo ucfirst($inc['status']); ?>
                  </span>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
          </div>
        <?php else: ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-shield-check fs-1 d-block mb-3 text-success opacity-50"></i>
            No incidents filed yet. Stay vigilant!
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
