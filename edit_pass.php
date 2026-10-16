<?php
// filepath: c:\xampp\htdocs\NewAGPMS\edit_pass.php
require_once 'includes/auth.php';
require_role('resident');
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$pass_id = intval($_GET['id'] ?? 0);

// Fetch the pass
$stmt = $conn->prepare("SELECT * FROM gate_passes WHERE id = ? AND user_id = ? AND (status = 'pending' OR (status = 'active' AND pass_type = 'recurring'))");
$stmt->bind_param("ii", $pass_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Invalid or unauthorized access to pass.");
}
$pass = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_name = sanitize($_POST['visitor_name']);
    $contact = sanitize($_POST['contact']);
    $purpose = sanitize($_POST['purpose']);
    $vehicle_details = sanitize($_POST['vehicle_details']);
    if ($pass['pass_type'] === 'recurring') {
        $update = $conn->prepare("UPDATE gate_passes SET visitor_name=?, contact=?, purpose=?, vehicle_details=? WHERE id=? AND user_id=?");
        $update->bind_param("ssssii", $visitor_name, $contact, $purpose, $vehicle_details, $pass_id, $user_id);
    } else {
        $entry_time = sanitize($_POST['entry_time']);
        $exit_time = sanitize($_POST['exit_time']);
        $update = $conn->prepare("UPDATE gate_passes SET visitor_name=?, contact=?, purpose=?, vehicle_details=?, entry_time=?, exit_time=? WHERE id=? AND user_id=?");
        $update->bind_param("ssssssii", $visitor_name, $contact, $purpose, $vehicle_details, $entry_time, $exit_time, $pass_id, $user_id);
    }

    if ($update->execute()) {
        header("Location: resident_dashboard.php");
        exit();
    } else {
        $error = "Failed to update pass.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">`n  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gate Pass</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Gate Pass</h2>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST">
<?php echo csrf_field(); ?>
        <div class="mb-3">
            <label class="form-label">Visitor Name</label>
            <input type="text" name="visitor_name" class="form-control" value="<?php echo htmlspecialchars($pass['visitor_name']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Contact</label>
            <input type="text" name="contact" class="form-control" value="<?php echo htmlspecialchars($pass['contact']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Purpose</label>
            <input type="text" name="purpose" class="form-control" value="<?php echo htmlspecialchars($pass['purpose']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Vehicle Details</label>
            <input type="text" name="vehicle_details" class="form-control" value="<?php echo htmlspecialchars($pass['vehicle_details']); ?>">
        </div>
        <?php if ($pass['pass_type'] !== 'recurring'): ?>
        <div class="mb-3">
            <label class="form-label">Entry Time</label>
            <input type="datetime-local" name="entry_time" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($pass['entry_time'])); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Exit Time</label>
            <input type="datetime-local" name="exit_time" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($pass['exit_time'])); ?>" required>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Update Pass</button>
        <a href="resident_dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>