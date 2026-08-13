<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Only allow security role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'security') {
    header('Location: security_dashboard.php?alert=Access denied.');
    exit();
}

$now = date('Y-m-d H:i:s');

// 1. Find pass by token or ID
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $stmt = $conn->prepare("SELECT * FROM gate_passes WHERE token = ? AND status = 'active'");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $pass = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pass) {
        // Invalid or expired token
        header('Location: security_dashboard.php?alert=Pass not recognized or not active.');
        exit();
    }
    $pass_id = $pass['id'];
} elseif (isset($_GET['id'])) {
    $pass_id = intval($_GET['id']);
    $pass = $conn->query("SELECT * FROM gate_passes WHERE id = $pass_id")->fetch_assoc();
    if (!$pass) {
        header('Location: security_dashboard.php?alert=Pass not recognized.');
        exit();
    }
} else {
    header('Location: security_dashboard.php?alert=No pass specified.');
    exit();
}

// Example: Prevent early/late check-in
if ($now < $pass['entry_time']) {
    // Too early
    echo "<div class='alert alert-warning'>Check-in not allowed before scheduled entry time.</div>";
    exit;
}
if ($now > $pass['exit_time']) {
    // Too late
    echo "<div class='alert alert-danger'>This pass has expired. Check-in denied.</div>";
    exit;
}

// 2. Check visit_logs for this pass (where check_out_time is NULL)
$log = $conn->query("SELECT * FROM visit_logs WHERE pass_id = $pass_id AND check_out_time IS NULL")->fetch_assoc();

if (isset($_GET['action']) && $_GET['action'] === 'checkout') {
    // Check-out
    if ($log) {
        $conn->query("UPDATE visit_logs SET check_out_time = '$now' WHERE id = {$log['id']}");
        if ($pass['pass_type'] !== 'recurring') {
            $conn->query("UPDATE gate_passes SET status = 'checked_out' WHERE id = $pass_id");
        }
        $alert = 'Visitor checked out successfully.';
    } else {
        $alert = 'Visitor is not checked in or already checked out.';
    }
} else {
    // Check-in
    if (!$log) {
        $sec_id = $_SESSION['user_id'];
        $conn->query("INSERT INTO visit_logs (pass_id, check_in_time, security_id) VALUES ($pass_id, '$now', $sec_id)");
        if ($pass['pass_type'] !== 'recurring') {
            $conn->query("UPDATE gate_passes SET status = 'checked_in' WHERE id = $pass_id");
        }

        // Notify the resident that their visitor has arrived
        $visitor_name = $pass['visitor_name'];
        $resident_id = $pass['user_id'];
        $notif_title = "Visitor Arrived";
        $notif_message = htmlspecialchars($visitor_name) . " has been checked in at the gate.";
        create_notification($conn, $resident_id, $notif_title, $notif_message);

        $alert = 'Visitor checked in successfully.';

    } else {
        $alert = 'Visitor already checked in.';
    }
}

header('Location: security_dashboard.php?alert=' . urlencode($alert));
exit();
?>
