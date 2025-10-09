<?php
// filepath: c:\xampp\htdocs\NewAGPMS\download_audit.php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Only allow admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=audit_trail_' . date('Ymd_His') . '.csv');

// Output buffer
$output = fopen('php://output', 'w');

// CSV column headers
fputcsv($output, [
    'Pass ID', 'Resident Name', 'Visitor Name', 'Purpose', 'Vehicle', 'Entry Time', 'Exit Time',
    'Status', 'Check-In Time', 'Check-Out Time'
]);

// Query for audit trail (join gate_passes, users, visit_logs)
$sql = "SELECT gp.id, u.name AS resident_name, gp.visitor_name, gp.purpose, gp.vehicle_details, 
               gp.entry_time, gp.exit_time, gp.status, vl.check_in_time, vl.check_out_time
        FROM gate_passes gp
        JOIN users u ON gp.user_id = u.id
        LEFT JOIN visit_logs vl ON gp.id = vl.pass_id
        ORDER BY gp.created_at DESC";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['resident_name'],
        $row['visitor_name'],
        $row['purpose'],
        $row['vehicle_details'],
        $row['entry_time'],
        $row['exit_time'],
        $row['status'],
        $row['check_in_time'],
        $row['check_out_time']
    ]);
}

fclose($output);
exit;