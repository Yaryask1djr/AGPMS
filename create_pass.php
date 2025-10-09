<?php
include 'includes/auth.php';
require_role('resident');
include 'includes/db.php';
include 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $visitor_name = ($_POST['visitor_name']);
  $contact = ($_POST['contact']);
  $purpose = ($_POST['purpose']);
  $vehicle = ($_POST['vehicle_details']);
  $entry_time = $_POST['entry_time'];
  $exit_time = $_POST['exit_time'];
  $token = bin2hex(random_bytes(8)); // Generates a 16-character unique token
  $user_id = $_SESSION['user_id'];

  // Check for scheduling conflicts
  $conflict = $conn->prepare("SELECT id FROM gate_passes WHERE user_id = ? AND ((entry_time <= ? AND exit_time >= ?) OR (entry_time <= ? AND exit_time >= ?))");
  $conflict->bind_param("issss", $user_id, $entry_time, $entry_time, $exit_time, $exit_time);
  $conflict->execute();
  $conflict->store_result();
  if ($conflict->num_rows > 0) {
      $error = "Scheduling conflict: You already have a pass during this time.";
  } else {
      $sql = "INSERT INTO gate_passes (user_id, visitor_name, contact, purpose, vehicle_details, entry_time, exit_time, token, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("isssssss", $user_id, $visitor_name, $contact, $purpose, $vehicle, $entry_time, $exit_time, $token);

      if ($stmt->execute()) {
        header("Location: resident_dashboard.php");
        exit();
      } else {
        $error = "Error creating pass: " . $stmt->error;
      }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Gate Pass</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
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
      <label class="form-label">Contact</label>
      <input type="text" name="contact" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Purpose</label>
      <textarea name="purpose" class="form-control" required></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Vehicle Details (Optional)</label>
      <input type="text" name="vehicle_details" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">Entry Time</label>
      <input type="datetime-local" name="entry_time" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Exit Time</label>
      <input type="datetime-local" name="exit_time" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-success w-100">Create Pass</button>
  </form>
</div>
</body>
</html>
