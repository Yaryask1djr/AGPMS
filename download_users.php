<?php
// filepath: c:\xampp\htdocs\NewAGPMS\download_users.php
include 'includes/db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=users.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Name', 'Phone', 'House No', 'Role', 'Status', 'Approved', 'Registered At']);

$result = $conn->query("SELECT name, phone, house_no, role, status, approved, created_at FROM users ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['name'],
        $row['phone'],
        $row['house_no'],
        $row['role'],
        $row['status'],
        $row['approved'] ? 'Yes' : 'No',
        $row['created_at']
    ]);
}
fclose($output);
exit;