<?php
include 'includes/auth.php';
require_role('resident');
include 'includes/db.php';
include 'includes/functions.php';

// Check if resident is a defaulter
$dues_stmt = $conn->prepare("SELECT dues_status FROM users WHERE id = ?");
$dues_stmt->bind_param("i", $_SESSION['user_id']);
$dues_stmt->execute();
$dues_stmt->bind_result($dues_status);
$dues_stmt->fetch();
$dues_stmt->close();

if ($dues_status === 'defaulter') {
    header("Location: resident_dashboard.php?alert=defaulter");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $visitor_name = sanitize($_POST['visitor_name']);
  $contact = sanitize($_POST['contact']);
  $purpose = sanitize($_POST['purpose']);
  $vehicle = sanitize($_POST['vehicle_details']);
  $entry_time = sanitize($_POST['entry_time']);
  $exit_time = sanitize($_POST['exit_time']);
  $token = bin2hex(random_bytes(8)); // Generates a 16-character unique token
  $user_id = $_SESSION['user_id'];

  // Check Blacklist first
  $bl_stmt = $conn->prepare("SELECT id FROM blacklist WHERE phone = ? LIMIT 1");
  $bl_stmt->bind_param("s", $contact);
  $bl_stmt->execute();
  $bl_stmt->store_result();
  if ($bl_stmt->num_rows > 0) {
      $error = "Access Denied: This contact is on the estate watchlist.";
  } else {
      $pass_type = isset($_POST['pass_type']) ? sanitize($_POST['pass_type']) : 'one_time';
      
      // Auto-set exit time for deliveries (2 hours from entry)
      if ($pass_type === 'delivery') {
          $exit_time = date('Y-m-d H:i:s', strtotime($entry_time . ' +2 hours'));
      } elseif ($pass_type === 'recurring') {
          $entry_time = date('Y-m-d H:i:s');
          $exit_time = '2099-12-31 23:59:59';
      }

      // Check for scheduling conflicts (only for non-delivery passes for simplicity, or keep same)
      $conflict = $conn->prepare("SELECT id FROM gate_passes WHERE user_id = ? AND ((entry_time <= ? AND exit_time >= ?) OR (entry_time <= ? AND exit_time >= ?))");
      $conflict->bind_param("issss", $user_id, $entry_time, $entry_time, $exit_time, $exit_time);
      $conflict->execute();
      $conflict->store_result();
      if ($conflict->num_rows > 0) {
          $error = "Scheduling conflict: You already have a pass during this time.";
      } else {
          $sql = "INSERT INTO gate_passes (user_id, visitor_name, contact, purpose, pass_type, vehicle_details, entry_time, exit_time, token, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("issssssss", $user_id, $visitor_name, $contact, $purpose, $pass_type, $vehicle, $entry_time, $exit_time, $token);

      if ($stmt->execute()) {
        header("Location: resident_dashboard.php");
        exit();
      } else {
        $error = "Error creating pass: " . $stmt->error;
      }
      }
      $conflict->close();
  }
  $bl_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Gate Pass</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container mt-5">
  <h2>Create Gate Pass</h2>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
  <form method="POST" class="p-4 border bg-light">
    <div class="mb-3">
      <label class="form-label">Visitor Name</label>
      <input type="text" name="visitor_name" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Contact Phone</label>
      <input type="text" name="contact" class="form-control" placeholder="Required for notifications" required>
    </div>
    <div class="mb-3">
      <label class="form-label fw-bold">Pass Type</label>
      <select name="pass_type" class="form-select border-primary" id="passTypeSelect" required>
        <option value="one_time">One-Time Visit</option>
        <option value="delivery">Quick Delivery (Auto-expires in 2 hours)</option>
        <option value="recurring">Recurring (e.g. Maid, Driver)</option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Purpose</label>
      <textarea name="purpose" class="form-control" required></textarea>
    </div>
    <div class="mb-3 bg-light border p-3 rounded">
      <label class="form-label fw-bold text-primary">Vehicle License Plate (Optional)</label>
      <input type="text" name="vehicle_details" class="form-control" placeholder="e.g., KAN-123-AB (Speeds up security check)">
    </div>
    <div class="mb-3 time-inputs">
      <label class="form-label">Entry Time</label>
      <input type="datetime-local" name="entry_time" id="entry_time" class="form-control" required>
    </div>
    <div class="mb-3 time-inputs">
      <label class="form-label">Exit Time</label>
      <input type="datetime-local" name="exit_time" id="exit_time" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-success w-100">Create Pass</button>
  </form>
</div>
<script>
  document.getElementById('passTypeSelect').addEventListener('change', function() {
    const timeInputs = document.querySelectorAll('.time-inputs');
    const entryTime = document.getElementById('entry_time');
    const exitTime = document.getElementById('exit_time');
    
    if (this.value === 'recurring') {
      timeInputs.forEach(el => el.style.display = 'none');
      entryTime.removeAttribute('required');
      exitTime.removeAttribute('required');
    } else {
      timeInputs.forEach(el => el.style.display = 'block');
      entryTime.setAttribute('required', 'required');
      exitTime.setAttribute('required', 'required');
    }
  });
</script>
</body>
</html>
