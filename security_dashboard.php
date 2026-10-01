<?php
require_once __DIR__ . "/includes/functions.php";
require_once 'includes/auth.php';
require_role('security');
require_once 'includes/db.php';

// Fetch security profile info
$security_id = $_SESSION['user_id'];
$profile_stmt = $conn->prepare("SELECT id, name, phone FROM users WHERE id = ?");
$profile_stmt->bind_param("i", $security_id);
$profile_stmt->execute();
$profile_stmt->bind_result($sec_id, $sec_name, $sec_phone);
$profile_stmt->fetch();
$profile_stmt->close();

// Check if currently clocked in
$shift_stmt = $conn->prepare("SELECT id FROM shifts WHERE security_id = ? AND clock_out IS NULL AND status = 'active'");
$shift_stmt->bind_param("i", $security_id);
$shift_stmt->execute();
$shift_stmt->store_result();
$is_clocked_in = $shift_stmt->num_rows > 0;
$shift_stmt->close();

// Handle Clock In / Clock Out POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shift_action'])) {
    if ($_POST['shift_action'] === 'clock_in' && !$is_clocked_in) {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO shifts (security_id, clock_in) VALUES (?, ?)");
        $stmt->bind_param("is", $security_id, $now);
        $stmt->execute();
        $stmt->close();
        header("Location: security_dashboard.php");
        exit();
    } elseif ($_POST['shift_action'] === 'clock_out' && $is_clocked_in) {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE shifts SET clock_out = ?, status = 'completed' WHERE security_id = ? AND clock_out IS NULL");
        $stmt->bind_param("si", $now, $security_id);
        $stmt->execute();
        $stmt->close();
        header("Location: security_dashboard.php");
        exit();
    }
}

// Fetch live active visitors (checked in, not checked out)
$sql = "SELECT gp.*, u.name AS resident_name, u.house_no, gp.visitor_name, gp.contact, gp.pass_type, gp.vehicle_details, vl.check_in_time
        FROM gate_passes gp
        JOIN users u ON gp.user_id = u.id
        JOIN visit_logs vl ON gp.id = vl.pass_id
        WHERE vl.check_out_time IS NULL
        ORDER BY vl.check_in_time DESC";
$result = $conn->query($sql);

$now = date('Y-m-d H:i:s');
// Auto-update pass statuses
$conn->query("UPDATE gate_passes SET status = 'active' WHERE status = 'pending' AND entry_time <= '$now'");
$conn->query("UPDATE gate_passes SET status = 'expired' WHERE status IN ('pending','active') AND exit_time < '$now'");

// Fetch Overstays (expired passes that haven't been checked out)
$overstay_sql = "SELECT gp.*, u.name AS resident_name, u.house_no, gp.visitor_name, gp.contact, gp.pass_type, gp.vehicle_details, vl.check_in_time
        FROM gate_passes gp
        JOIN users u ON gp.user_id = u.id
        JOIN visit_logs vl ON gp.id = vl.pass_id
        WHERE vl.check_out_time IS NULL AND gp.status = 'expired'
        ORDER BY vl.check_in_time ASC";
$overstays = $conn->query($overstay_sql);

// Fetch checked-out visitors
$checked_out_sql = "SELECT gp.*, u.name AS resident_name, u.house_no, gp.pass_type, gp.vehicle_details, vl.check_in_time, vl.check_out_time
        FROM gate_passes gp
        JOIN users u ON gp.user_id = u.id
        JOIN visit_logs vl ON gp.id = vl.pass_id
        WHERE vl.check_out_time IS NOT NULL
        ORDER BY vl.check_out_time DESC
        LIMIT 50";
$checked_out_result = $conn->query($checked_out_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="csrf-token" content="<?=htmlspecialchars(csrf_token(), ENT_QUOTES)?>">
<meta name="scan-owner" content="<?=(int)$_SESSION['user_id']?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Security Dashboard - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="manifest" href="manifest.json">
  <script src="assets/js/dark-mode.js"></script>
  <script src="assets/js/offline-scanner.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/html5-qrcode"></script>
  <style>
    /* Security Command HUD styling */
    .security-terminal .glass-card { border-left: 4px solid #0dcaf0; }
    body.dark-mode { background-color: #0b0c10 !important; color: #c5c6c7 !important; }
    body.dark-mode .card { background-color: #1a222d !important; border: 1px solid #45a29e !important; color: #c5c6c7 !important; }
    body.dark-mode .table { color: #c5c6c7 !important; }
    body.dark-mode .table-dark th { background-color: #0b0c10 !important; color: #66fcf1 !important; border-color: #45a29e !important; }
  </style>
</head>
<body>
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3 glass-card p-3 hover-lift shadow-sm border-info">
      <div>
        <h2 class="mb-0 brand-font text-info"><i class="bi bi-shield-check"></i> Security Command HUD</h2>
        <div class="mt-1">
          <span id="networkStatusIndicator" class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-wifi"></i> Online Terminal</span>
          <span id="offlineQueueBadge" class="badge bg-warning text-dark rounded-pill px-3 py-2 ms-2" style="display:none;"></span>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="incident_report.php" class="btn btn-outline-danger rounded-pill btn-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i> Report Incident</a>
        <button id="darkModeToggle" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-moon-fill"></i></button>
        <button id="panicRefresh" class="btn btn-warning btn-sm rounded-pill" title="Refresh Panic Alerts"><i class="bi bi-arrow-clockwise"></i></button>
        <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
    <div id="panicAlerts" class="mb-4"></div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav">
  <a href="security_dashboard.php" class="nav-item active"><i class="bi bi-shield-fill"></i>HUD</a>
  <a href="scan_pass.php" class="nav-item"><i class="bi bi-qr-code-scan"></i>Scan</a>
  <a href="incident_report.php" class="nav-item danger"><i class="bi bi-exclamation-triangle-fill"></i>Incident</a>
  <a href="shift_logs.php" class="nav-item"><i class="bi bi-clock-history"></i>Shifts</a>
  <a href="logout.php" class="nav-item"><i class="bi bi-box-arrow-right"></i>Logout</a>
</nav>

    <!-- Security Profile Info -->
    <div class="d-flex justify-content-center">
      <div class="card mb-4" style="max-width: 400px;">
        <div class="card-body">
          <h5 class="card-title mb-2">Security Profile</h5>
          <p class="mb-1"><strong>ID:</strong> <?php echo htmlspecialchars($sec_id); ?></p>
          <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($sec_name); ?></p>
          <p class="mb-3 text-info"><i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($sec_phone); ?></p>
          
          <form method="POST" class="text-center">
<?php echo csrf_field(); ?>
            <?php if ($is_clocked_in): ?>
              <button type="submit" name="shift_action" value="clock_out" class="btn btn-outline-danger w-100 fw-bold py-3 shadow hover-lift rounded-pill">CLOCK OUT OF SHIFT</button>
            <?php else: ?>
              <button type="submit" name="shift_action" value="clock_in" class="btn btn-outline-success w-100 fw-bold py-3 shadow hover-lift rounded-pill">CLOCK IN FOR SHIFT</button>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>

    <?php if (!$is_clocked_in): ?>
      <div class="alert alert-warning text-center fw-bold mt-4 shadow-sm glass-card border-warning text-warning p-4 hover-lift">
        <i class="bi bi-exclamation-triangle" style="font-size: 2rem; display: block;"></i> 
        <span class="fs-5">You must Clock In to activate the scanning systems.</span>
      </div>
    <?php else: ?>

    <!-- Overstay Alerts -->
    <?php if ($overstays->num_rows > 0): ?>
      <div class="card mb-5 neon-alert">
        <div class="card-header border-0 pb-0">
          <h4 class="mb-0 fw-bold brand-font"><i class="bi bi-exclamation-octagon-fill"></i> ACTIVE OVERSTAYS (<?php echo $overstays->num_rows; ?>)</h4>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-dark table-striped mb-0 border-danger">
              <thead>
                <tr>
                  <th class="text-danger">Visitor</th>
                  <th class="text-danger">Resident &amp; House</th>
                  <th class="text-danger">Vehicle</th>
                  <th class="text-danger">In Since</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($os = $overstays->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($os['visitor_name']); ?> <br> <small><?php echo htmlspecialchars($os['contact']); ?></small></td>
                  <td><?php echo htmlspecialchars($os['resident_name']); ?> (<?php echo htmlspecialchars($os['house_no']); ?>)</td>
                  <td><?php echo htmlspecialchars($os['vehicle_details']); ?></td>
                  <td><?php echo date('h:i A', strtotime($os['check_in_time'])); ?></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- SweetAlert area -->
    <?php if (isset($_GET['alert'])): ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?php echo (strpos(strtolower($_GET["alert"]), "denied") !== false || strpos(strtolower($_GET["alert"]), "expired") !== false) ? "error" : "success"; ?>',
                title: '<?php echo htmlspecialchars($_GET['alert']); ?>',
                showConfirmButton: false,
                timer: 4000,
                background: '#1f2833',
                color: '#66fcf1'
            });
        });
      </script>
    <?php endif; ?>

    <!-- Scanner Panel -->
    <div class="row mb-5">
      <div class="col-md-5">
        <div class="card p-4 hover-lift">
          <h5 class="mb-3 brand-font text-info"><i class="bi bi-upc-scan"></i> QR Scanner</h5>
          <div id="qr-reader" style="width:100%"></div>
          <script>
            function onScanSuccess(decodedText, decodedResult) {
              if (window.scanBusy) return; window.scanBusy = true;
              window.OfflineScanner.submit(decodedText).catch(error => { window.scanBusy = false; alert(error.message); });
            }
            let html5QrcodeScanner = new Html5QrcodeScanner(
              "qr-reader", { fps: 10, qrbox: 250 });
            html5QrcodeScanner.render(onScanSuccess);
          </script>
          <form class="mt-4" method="POST" action="scan_pass.php" onsubmit="event.preventDefault(); window.OfflineScanner.submit(this.token.value).catch(error => alert(error.message));">
            <?php echo csrf_field(); ?>
            <div class="input-group input-group-lg">
              <input type="text" name="token" placeholder="Scan or Enter Token..." class="form-control bg-dark text-info border-info" required>
              <button class="btn btn-info fw-bold">Scan</button>
            </div>
          </form>
        </div>
      </div>
      <div class="col-md-7">
        <div class="card p-4 hover-lift h-100">
        <h5 class="mb-3 brand-font text-success"><i class="bi bi-activity"></i> Live Active Visitors</h5>
        <div class="table-responsive">
        <table class="table table-dark table-hover border-success mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>House #</th>
              <th>Type / Vehicle</th>
              <th>Entry Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['id']); ?></td>
              <td><?php echo htmlspecialchars($row['house_no']); ?></td>
              <td>
                <span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['pass_type']))); ?></span><br>
                <small class="text-muted"><?php echo htmlspecialchars($row['vehicle_details'] ?: 'No Vehicle'); ?></small>
              </td>
              <td><?php echo htmlspecialchars($row['check_in_time']); ?></td>
              <td><span class="badge bg-success">Checked In</span></td>
              <td>
                <form method="POST" action="scan_pass.php" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $row['id']; ?>"><input type="hidden" name="action" value="checkout"><button class="btn btn-sm btn-outline-primary" type="submit">Check-Out</button></form>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recently Checked-Out Visitors -->
    <div class="card p-4 hover-lift mt-5 border-secondary">
      <h5 class="mb-3 brand-font text-secondary"><i class="bi bi-clock-history"></i> Checked-Out Visitors</h5>
      <div class="table-responsive">
        <table class="table table-dark table-bordered border-secondary mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>House #</th>
              <th>Type / Vehicle</th>
              <th>Check-In Time</th>
              <th>Check-Out Time</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $checked_out_result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['id']); ?></td>
              <td><?php echo htmlspecialchars($row['house_no']); ?></td>
              <td>
                <span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['pass_type']))); ?></span><br>
                <small class="text-muted"><?php echo htmlspecialchars($row['vehicle_details'] ?: 'No Vehicle'); ?></small>
              </td>
              <td><?php echo htmlspecialchars($row['check_in_time']); ?></td>
              <td><?php echo htmlspecialchars($row['check_out_time']); ?></td>
              <td><span class="badge bg-secondary">Checked Out</span></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?> <!-- End of is_clocked_in check -->
  </div>
  
<script>
  // Function to fetch active panic alerts and render them
  function loadPanicAlerts(){
    fetch('fetch_panic.php')
      .then(r=>r.json())
      .then(data=>{
        const container=document.getElementById('panicAlerts');
        if(data.length===0){
          container.innerHTML='<div class="alert alert-success" role="alert">No active SOS alerts.</div>';
          return;
        }
        let html='<div class="card bg-danger text-white mb-3"><div class="card-header"><i class="bi bi-exclamation-triangle-fill"></i> Active SOS Alerts</div><div class="card-body"><ul class="list-group list-group-flush">';
        data.forEach(alert => {
          html+=`<li class="list-group-item bg-danger text-white"><strong>${alert.name}</strong> (House ${alert.house_no})<br/>${alert.message}<br/><button class="btn btn-sm btn-success mt-2 resolve-btn" data-id="${alert.id}">Resolve</button></li>`;
        });
        html+='</ul></div></div>';
        container.innerHTML=html;
        // Add click handlers for resolve buttons
        container.querySelectorAll('.resolve-btn').forEach(btn => {
          btn.addEventListener('click', e => {
            const id = e.target.getAttribute('data-id');
            fetch('resolve_panic.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':document.querySelector('meta[name=csrf-token]').content},body:new URLSearchParams({id})})
              .then(() => loadPanicAlerts())
              .catch(() => console.error('Failed to resolve panic alert'));
          });
        });
      })
      .catch(()=>{console.error('Failed to load panic alerts');});
  }
  // Auto-refresh panic alerts every 30 seconds (no page reload)
  loadPanicAlerts();
  setInterval(loadPanicAlerts, 30000);
  document.getElementById('panicRefresh').addEventListener('click', loadPanicAlerts);

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js');
    });
  }

  // ── Offline Scanner HUD wiring ─────────────────────────
  function updateNetworkBadge() {
    const indicator = document.getElementById('networkStatusIndicator');
    const queueBadge = document.getElementById('offlineQueueBadge');
    if (!indicator) return;
    if (navigator.onLine) {
      indicator.className = 'badge bg-success rounded-pill px-3 py-2';
      indicator.innerHTML = '<i class="bi bi-wifi"></i> Online Terminal';
    } else {
      indicator.className = 'badge bg-danger rounded-pill px-3 py-2';
      indicator.innerHTML = '<i class="bi bi-wifi-off"></i> OFFLINE MODE';
    }
    // Show queued scan count from OfflineScanner if available
    if (window.OfflineScanner) {
      window.OfflineScanner.getQueueCount().then(count => {
        if (count > 0) {
          queueBadge.style.display = '';
          queueBadge.innerHTML = '<i class="bi bi-clock-history"></i> ' + count + ' scan' + (count !== 1 ? 's' : '') + ' queued';
        } else {
          queueBadge.style.display = 'none';
        }
      });
    }
  }
  window.addEventListener('online', updateNetworkBadge);
  window.addEventListener('offline', updateNetworkBadge);
  setInterval(updateNetworkBadge, 5000);
  updateNetworkBadge();
</script>
</body>
</html>
