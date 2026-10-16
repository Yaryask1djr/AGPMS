<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid pass ID specified.");
}

$pass_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Fetch pass with user ownership check unless admin/security
$stmt = $conn->prepare("SELECT gp.*, u.name as resident_name, u.house_no FROM gate_passes gp JOIN users u ON gp.user_id = u.id WHERE gp.id = ?");
$stmt->bind_param("i", $pass_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Gate pass not found.");
}

$pass = $result->fetch_assoc();
$stmt->close();

// Enforce ownership: residents can only view their own passes
if ($user_role === 'resident' && $pass['user_id'] != $user_id) {
    die("Unauthorized access to this pass.");
}

// Generate QR code if needed
$qr_data = $pass['token'];
if (!is_dir('temp_qr')) {
    mkdir('temp_qr', 0777, true);
}
include_once 'phpqrcode/qrlib.php';
$qr_path = "temp_qr/qr_{$pass['id']}.png";
QRcode::png($qr_data, $qr_path, QR_ECLEVEL_L, 4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Pass #<?php echo htmlspecialchars($pass['id']); ?> - AGPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: white !important; }
      .pass-card { box-shadow: none !important; border: 1px solid #000 !important; }
    }
  </style>
</head>
<body class="bg-light py-4">
  <div class="container" style="max-width: 650px;">
    
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
      <h3 class="brand-font mb-0"><i class="bi bi-qr-code text-primary"></i> Gate Pass #<?php echo htmlspecialchars($pass['id']); ?></h3>
      <?php if ($user_role === 'resident'): ?>
        <a href="resident_dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3"><i class="bi bi-arrow-left"></i> Dashboard</a>
      <?php else: ?>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3"><i class="bi bi-arrow-left"></i> Dashboard</a>
      <?php endif; ?>
    </div>

    <?php if (isset($_GET['sent'])): ?>
      <div class="alert alert-success alert-dismissible fade show no-print rounded-pill px-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_GET["sent"] === "accepted" ? "Mail accepted for delivery; delivery is not confirmed." : "Mail submission failed. Please try again later."; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 pass-card rounded-4 overflow-hidden mb-4">
      <div class="bg-primary text-white p-3 text-center">
        <h4 class="mb-0 fw-bold">Ibrahim Kunya Housing Estate</h4>
        <small class="opacity-75">Visitor Access Authorization Pass</small>
      </div>
      <div class="card-body p-4">
        <div class="text-center mb-4">
          <img src="<?php echo htmlspecialchars($qr_path); ?>" alt="QR Code" class="img-thumbnail shadow-sm mb-2" style="width: 180px; height: 180px;">
          <div class="mt-2">
            <span class="text-muted small d-block">Access Token:</span>
            <span class="badge bg-dark fs-6 px-3 py-2 font-monospace tracking-wide"><?php echo htmlspecialchars($pass['token']); ?></span>
          </div>
        </div>

        <table class="table table-bordered mb-0">
          <tbody>
            <tr>
              <th class="bg-light text-muted" style="width: 35%;">Visitor Name</th>
              <td class="fw-bold"><?php echo htmlspecialchars($pass['visitor_name']); ?></td>
            </tr>
            <tr>
              <th class="bg-light text-muted">Contact Phone</th>
              <td><?php echo htmlspecialchars($pass['contact']); ?></td>
            </tr>
            <tr>
              <th class="bg-light text-muted">Host Resident</th>
              <td><?php echo htmlspecialchars($pass['resident_name']); ?> (House <?php echo htmlspecialchars($pass['house_no']); ?>)</td>
            </tr>
            <tr>
              <th class="bg-light text-muted">Pass Type</th>
              <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pass['pass_type']))); ?></span></td>
            </tr>
            <tr>
              <th class="bg-light text-muted">Purpose</th>
              <td><?php echo htmlspecialchars($pass['purpose']); ?></td>
            </tr>
            <tr>
              <th class="bg-light text-muted">Vehicle Details</th>
              <td><?php echo htmlspecialchars($pass['vehicle_details'] ?: 'None (Pedestrian)'); ?></td>
            </tr>
            <tr>
              <th class="bg-light text-muted">Valid From</th>
              <td><?php echo date('M d, Y h:i A', strtotime($pass['entry_time'])); ?></td>
            </tr>
            <tr>
              <th class="bg-light text-muted">Valid Until</th>
              <td><?php echo ($pass['pass_type'] === 'recurring') ? 'Permanent / Recurring' : date('M d, Y h:i A', strtotime($pass['exit_time'])); ?></td>
            </tr>
            <tr>
              <th class="bg-light text-muted">Status</th>
              <td>
                <?php
                  $badge = 'secondary';
                  if ($pass['status'] === 'active') $badge = 'success';
                  elseif ($pass['status'] === 'checked_in') $badge = 'primary';
                  elseif ($pass['status'] === 'pending') $badge = 'warning text-dark';
                  elseif ($pass['status'] === 'expired') $badge = 'danger';
                ?>
                <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pass['status']))); ?></span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($user_role === 'resident'): ?>
    <form method="POST" action="send_pass.php" class="no-print">
      <?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$pass_id?>">
      <label for="pass-recipient">Email recipient</label><input type="email" id="pass-recipient" name="recipient" required maxlength="254"><button type="submit">Send pass</button>
    </form>
    <?php endif; ?>
    <!-- Action Bar -->
    <div class="d-flex flex-wrap gap-2 justify-content-center no-print mb-4">
      <a href="<?php echo htmlspecialchars($qr_path); ?>" download="gate_pass_qr_<?php echo $pass['id']; ?>.png" class="btn btn-outline-primary rounded-pill px-3">
        <i class="bi bi-download me-1"></i> Download QR
      </a>
      <?php
        $wa_msg = urlencode("Hello! Here is your Gate Pass for Ibrahim Kunya Housing Estate:\nVisitor: {$pass['visitor_name']}\nHost: {$pass['resident_name']} (House {$pass['house_no']})\nToken: {$pass['token']}\nValid From: " . date('M d, Y h:i A', strtotime($pass['entry_time'])) . "\nPlease show this token or QR code to security at the gate.");
      ?>
      <a href="https://wa.me/?text=<?php echo $wa_msg; ?>" target="_blank" class="btn btn-success rounded-pill px-3">
        <i class="bi bi-whatsapp me-1"></i> Share via WhatsApp
      </a>
      <button class="btn btn-dark rounded-pill px-3" onclick="window.print()">
        <i class="bi bi-printer me-1"></i> Print Pass
      </button>
    </div>

  </div>
</body>
</html>
