<?php
require_once 'includes/auth.php';
require_role('resident');
require_once 'includes/db.php';
require_once 'includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Allow: POST"); http_response_code(405); exit; }
$pass_id = intval($_POST['id'] ?? 0);
$recipient = filter_var($_POST['recipient'] ?? '', FILTER_VALIDATE_EMAIL);
$user_id = $_SESSION['user_id'];

if ($pass_id <= 0 || !$recipient) {
    header("Location: resident_dashboard.php");
    exit();
}

// Verify pass ownership
$stmt = $conn->prepare("SELECT * FROM gate_passes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $pass_id, $user_id);
$stmt->execute();
$pass = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pass) {
    header("Location: resident_dashboard.php?error=" . urlencode("Pass not found or unauthorized."));
    exit();
}

$subject = "IKHE Gate Pass for " . $pass['visitor_name'];
$body = "Dear Visitor,\n\nHere are your access details for Ibrahim Kunya Housing Estate:\n\n" .
        "Visitor Name: " . $pass['visitor_name'] . "\n" .
        "Access Token: " . $pass['token'] . "\n" .
        "Purpose: " . $pass['purpose'] . "\n" .
        "Valid From: " . $pass['entry_time'] . "\n" .
        "Valid Until: " . $pass['exit_time'] . "\n\n" .
        "Please present this token or your QR code at the security gate upon arrival.\n";

$headers = "From: no-reply@ikhe-gpms.local\r\n" .
           "Reply-To: no-reply@ikhe-gpms.local\r\n" .
           "X-Mailer: PHP/" . phpversion();

// Attempt mail sending; in dev environment without sendmail it might return false, but we handle gracefully
$accepted = mail($recipient, $subject, $body, $headers);

header("Location: view_pass.php?id={$pass_id}&sent=" . ($accepted ? "accepted" : "failed"), true, 303);
exit();