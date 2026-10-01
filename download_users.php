<?php
require_once __DIR__."/includes/auth.php";
require_role("admin");
header("Cache-Control: no-store");
// filepath: c:\xampp\htdocs\NewAGPMS\download_users.php
require_once 'includes/db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=users.csv');

log_admin_activity($conn, $_SESSION['user_id'], 'User CSV export');
$output = fopen('php://output', 'w');
fputcsv($output, ['Name', 'Phone', 'House No', 'Role', 'Status', 'Approved', 'Registered At']);

$result = $conn->query("SELECT name, phone, house_no, role, status, approved, created_at FROM users ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        csv_safe($row['name']),
        csv_safe($row['phone']),
        csv_safe($row['house_no']),
        $row['role'],
        $row['status'],
        $row['approved'] ? 'Yes' : 'No',
        $row['created_at']
    ]);
}
fclose($output);
exit;