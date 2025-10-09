<?php
include 'includes/auth.php';
require_role('security');
include 'includes/db.php';

// Fetch security profile info
$security_id = $_SESSION['user_id'];
$profile_stmt = $conn->prepare("SELECT id, name, phone FROM users WHERE id = ?");
$profile_stmt->bind_param("i", $security_id);
$profile_stmt->execute();
$profile_stmt->bind_result($sec_id, $sec_name, $sec_phone);
$profile_stmt->fetch();
$profile_stmt->close();

// Fetch live active visitors (checked in, not checked out)
$sql = "SELECT gp.*, u.name AS resident_name, u.house_no, gp.visitor_name, gp.visitor_phone, vl.check_in_time
        FROM gate_passes gp
        JOIN users u ON gp.user_id = u.id
        JOIN visit_logs vl ON gp.id = vl.pass_id
        WHERE vl.check_out_time IS NULL
        ORDER BY vl.check_in_time DESC";
$result = $conn->query($sql);

$now = date('Y-m-d H:i:s');
// Auto-update pass statuses
$conn->query("UPDATE gate_passes SET status = 'active' WHERE status = 'pending' AND entry_time <= '$now'");
$conn->query("UPDATE gate_passes SET status = 'expired' WHERE status != 'expired' AND exit_time < '$now'");

// Fetch checked-out visitors
$checked_out_sql = "SELECT gp.*, u.name AS resident_name, u.house_no, vl.check_in_time, vl.check_out_time
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
  <meta charset="UTF-8">
  <title>Security Dashboard - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
  <div class="container mt-5">
    <h2 class="mb-4">Security Dashboard</h2>

    <!-- Security Profile Info -->
    <div class="d-flex justify-content-center">
      <div class="card mb-4" style="max-width: 400px;">
        <div class="card-body">
          <h5 class="card-title mb-2">Security Profile</h5>
          <p class="mb-1"><strong>ID:</strong> <?php echo htmlspecialchars($sec_id); ?></p>
          <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($sec_name); ?></p>
          <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($sec_phone); ?></p>
        </div>
      </div>
    </div>

    <!-- Alert area -->
    <?php if (isset($_GET['alert'])): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['alert']); ?></div>
    <?php endif; ?>

    <!-- Scanner Panel -->
    <div class="row mb-4">
      <div class="col-md-6">
        <div class="card p-3">
          <h5 class="mb-3">QR Scanner (Webcam)</h5>
          <div id="qr-reader" style="width:100%"></div>
          <script>
            function onScanSuccess(decodedText, decodedResult) {
              window.location.href = "scan_pass.php?token=" + encodeURIComponent(decodedText);
            }
            let html5QrcodeScanner = new Html5QrcodeScanner(
              "qr-reader", { fps: 10, qrbox: 250 });
            html5QrcodeScanner.render(onScanSuccess);
          </script>
          <form class="mt-3" method="GET" action="scan_pass.php">
            <div class="input-group">
              <input type="text" name="token" placeholder="Scan or Enter Token" class="form-control" required>
              <button class="btn btn-primary">Submit</button>
            </div>
          </form>
        </div>
      </div>
      <div class="col-md-6">
        <h5 class="mb-3">Live Active Visitors</h5>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>House #</th>
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
              <td><?php echo htmlspecialchars($row['check_in_time']); ?></td>
              <td><span class="badge bg-success">Checked In</span></td>
              <td>
                <a href="scan_pass.php?action=checkout&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm">Check-Out</a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recently Checked-Out Visitors -->
    <h5 class="mb-3 mt-5">Checked-Out Visitors</h5>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>ID</th>
          <th>House #</th>
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
          <td><?php echo htmlspecialchars($row['check_in_time']); ?></td>
          <td><?php echo htmlspecialchars($row['check_out_time']); ?></td>
          <td><span class="badge bg-secondary">Checked Out</span></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <script>
    // Auto-refresh every 30 seconds for real-time updates
    setTimeout(() => { location.reload(); }, 30000);
  </script>
</body>
</html>
