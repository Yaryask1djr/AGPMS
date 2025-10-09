<?php
include 'includes/auth.php';
require_role('resident');
include 'includes/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "Invalid or unauthorized access to pass.";
    exit;
}
$pass_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM gate_passes WHERE id = ?");
$stmt->bind_param("i", $pass_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Invalid or unauthorized access to pass.";
    exit;
}
$pass = $result->fetch_assoc();

$qr_data = $pass['token'];
include 'phpqrcode/qrlib.php';
$qr_path = "temp_qr/qr_{$pass['id']}.png";
QRcode::png($qr_data, $qr_path, QR_ECLEVEL_L, 4);

// Insert into visit_logs if not already present
$check_log = $conn->prepare("SELECT id FROM visit_logs WHERE pass_id = ?");
$check_log->bind_param("i", $pass['id']);
$check_log->execute();
$check_log->store_result();

if ($check_log->num_rows === 0) {
    $now = date('Y-m-d H:i:s');
    $insert_log = $conn->prepare("INSERT INTO visit_logs (pass_id, check_in_time) VALUES (?, ?)");
    $insert_log->bind_param("is", $pass['id'], $now);
    $insert_log->execute();
    $insert_log->close();
}
$check_log->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Pass - AGPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
  <div class="container mt-5">
    <h2>Gate Pass Details</h2>
    <table class="table">
      <tr><th>Visitor</th><td><?php echo $pass['visitor_name']; ?></td></tr>
      <tr><th>Contact</th><td><?php echo $pass['contact']; ?></td></tr>
      <tr><th>Purpose</th><td><?php echo $pass['purpose']; ?></td></tr>
      <tr><th>Vehicle</th><td><?php echo $pass['vehicle_details']; ?></td></tr>
      <tr><th>Entry</th><td><?php echo $pass['entry_time']; ?></td></tr>
      <tr><th>Exit</th><td><?php echo $pass['exit_time']; ?></td></tr>
      <tr><th>Status</th><td><?php echo $pass['status']; ?></td></tr>
      <tr>
        <th>QR Code</th>
        <td>
          <img src="<?php echo $qr_path; ?>" alt="QR Code"><br>
          <a href="<?php echo $qr_path; ?>" download="gate_pass_qr_<?php echo $pass['id']; ?>.png" class="btn btn-primary btn-sm mt-2">Download QR Code</a>
          <div class="mt-2">
            <strong>Manual Token:</strong>
            <span class="badge bg-secondary"><?php echo htmlspecialchars($pass['token']); ?></span>
          </div>
        </td>
      </tr>
    </table>
    <div class="mb-3">
      <button class="btn btn-success" onclick="sendPassPrompt(<?php echo $pass['id']; ?>)">Send Pass</button>
    </div>
    <a href="resident_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>

  <script>
    function sendPassPrompt(passId) {
      const recipient = prompt("Enter recipient email or phone number to send the pass:");
      if (recipient) {
        window.location.href = `send_pass.php?id=${passId}&recipient=${encodeURIComponent(recipient)}`;
      }
    }
  </script>
</body>
</html>
