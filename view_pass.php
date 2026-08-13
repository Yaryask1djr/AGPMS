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
  <link rel="stylesheet" href="assets/css/style.css">
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
    <div class="mb-3 d-flex gap-2">
      <?php
        $wa_msg = urlencode("Hello! Here is your Gate Pass for IKHE.\nName: {$pass['visitor_name']}\nToken: {$pass['token']}\nEntry: {$pass['entry_time']}\nPlease show this token or QR code at the main gate.");
      ?>
      <a href="https://wa.me/?text=<?php echo $wa_msg; ?>" target="_blank" class="btn btn-success">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-whatsapp me-2" viewBox="0 0 16 16">
          <path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.49.652.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/>
        </svg>Share via WhatsApp
      </a>
      <button class="btn btn-outline-primary" onclick="sendPassPrompt(<?php echo $pass['id']; ?>)">Send via Email</button>
      <button class="btn btn-dark" onclick="window.print()">Download as PDF</button>
    </div>
    <a href="resident_dashboard.php" class="btn btn-secondary mt-2">Back to Dashboard</a>
  </div>

  <script>
    function sendPassPrompt(passId) {
      const recipient = prompt("Enter recipient email to send the pass:");
      if (recipient) {
        window.location.href = `send_pass.php?id=${passId}&recipient=${encodeURIComponent(recipient)}`;
      }
    }
  </script>
</body>
</html>
