<?php
// api/v1/analytics.php - Advanced analytics data engine
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/functions.php';

$current_user = authenticate_api_user($conn, ['admin', 'security']);

// 1. 24-Hour Entry Density Distribution
$hourly_distribution = array_fill(0, 24, 0);
$h_res = $conn->query("
    SELECT HOUR(check_in_time) as hr, COUNT(*) as cnt 
    FROM visit_logs 
    WHERE check_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY hr
");
while ($row = $h_res->fetch_assoc()) {
    $hourly_distribution[(int)$row['hr']] = (int)$row['cnt'];
}

// 2. 7-Day Visitor Inflow Trends
$daily_trends = [];
$d_res = $conn->query("
    SELECT DATE(check_in_time) as dt, COUNT(*) as cnt 
    FROM visit_logs 
    WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
    GROUP BY dt 
    ORDER BY dt ASC
");
while ($row = $d_res->fetch_assoc()) {
    $daily_trends[$row['dt']] = (int)$row['cnt'];
}

// 3. Pass Type Breakdown
$type_breakdown = ['one_time' => 0, 'delivery' => 0, 'recurring' => 0];
$t_res = $conn->query("SELECT pass_type, COUNT(*) as cnt FROM gate_passes GROUP BY pass_type");
while ($row = $t_res->fetch_assoc()) {
    $type_breakdown[$row['pass_type']] = (int)$row['cnt'];
}

// 4. Top 5 Visited Houses
$top_houses = [];
$th_res = $conn->query("
    SELECT u.house_no, COUNT(*) as total_visits
    FROM visit_logs vl
    JOIN gate_passes gp ON vl.pass_id = gp.id
    JOIN users u ON gp.user_id = u.id
    WHERE u.house_no IS NOT NULL AND u.house_no != ''
    GROUP BY u.house_no
    ORDER BY total_visits DESC
    LIMIT 5
");
while ($row = $th_res->fetch_assoc()) {
    $top_houses[] = $row;
}

// 5. Total statistics
$stats = [
    'total_passes'    => (int)$conn->query("SELECT COUNT(*) FROM gate_passes")->fetch_row()[0],
    'total_visits'    => (int)$conn->query("SELECT COUNT(*) FROM visit_logs")->fetch_row()[0],
    'active_on_site'  => (int)$conn->query("SELECT COUNT(*) FROM visit_logs WHERE check_out_time IS NULL")->fetch_row()[0],
    'active_overstay' => (int)$conn->query("SELECT COUNT(*) FROM gate_passes gp JOIN visit_logs vl ON gp.id = vl.pass_id WHERE vl.check_out_time IS NULL AND gp.status = 'expired'")->fetch_row()[0]
];

api_response(true, [
    'summary'             => $stats,
    'hourly_distribution' => $hourly_distribution,
    'daily_trends'        => $daily_trends,
    'pass_types'          => $type_breakdown,
    'top_houses'          => $top_houses
], 'Analytics data retrieved.');
