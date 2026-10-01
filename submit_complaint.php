<?php
require_once __DIR__ . "/includes/auth.php";
require_role("resident");
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'resident') {
    header("Location: login_resident.php");
    exit();
}
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = sanitize($_POST['subject']);
    $description = sanitize($_POST['description']);
    
    $stmt = $conn->prepare("INSERT INTO complaints (user_id, subject, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $subject, $description);
    
    if ($stmt->execute()) {
        $msg = "Complaint submitted successfully.";
    } else {
        $error = "Failed to submit complaint.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - GPMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
</head>
<body>
    <div class="container mt-5" style="max-width: 600px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Submit a Complaint</h2>
            <a href="resident_dashboard.php" class="btn btn-outline-secondary">Back</a>
        </div>

        <?php if (isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>
        <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form method="POST">
<?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" required placeholder="e.g. Broken Streetlight">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="5" required placeholder="Provide details..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Submit</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
