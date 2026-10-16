<?php
require_once 'includes/auth.php';
require_role('admin');
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Raw data for charts
$hourly = array_fill(0, 24, 0);
$h = $conn->query("SELECT HOUR(check_in_time) as hr, COUNT(*) as cnt FROM visit_logs WHERE check_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY hr");
while ($r = $h->fetch_assoc()) $hourly[(int)$r['hr']] = (int)$r['cnt'];

$daily = [];
$d = $conn->query("SELECT DATE(check_in_time) as dt, COUNT(*) as cnt FROM visit_logs WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY dt ORDER BY dt ASC");
while ($r = $d->fetch_assoc()) $daily[$r['dt']] = (int)$r['cnt'];

// Fill missing days
$day_labels = [];
$day_values = [];
for ($i = 13; $i >= 0; $i--) {
    $dt = date('Y-m-d', strtotime("-{$i} days"));
    $day_labels[] = date('M d', strtotime($dt));
    $day_values[] = $daily[$dt] ?? 0;
}

$pass_types = ['one_time' => 0, 'delivery' => 0, 'recurring' => 0];
$pt = $conn->query("SELECT pass_type, COUNT(*) as cnt FROM gate_passes GROUP BY pass_type");
while ($r = $pt->fetch_assoc()) if (isset($pass_types[$r['pass_type']])) $pass_types[$r['pass_type']] = (int)$r['cnt'];

$top_houses = [];
$th = $conn->query("SELECT u.house_no, COUNT(*) as total FROM visit_logs vl JOIN gate_passes gp ON vl.pass_id = gp.id JOIN users u ON gp.user_id = u.id WHERE u.house_no IS NOT NULL AND u.house_no != '' GROUP BY u.house_no ORDER BY total DESC LIMIT 8");
while ($r = $th->fetch_assoc()) $top_houses[] = $r;

$totals = [
    'passes'    => (int)$conn->query("SELECT COUNT(*) FROM gate_passes")->fetch_row()[0],
    'visits'    => (int)$conn->query("SELECT COUNT(*) FROM visit_logs")->fetch_row()[0],
    'on_site'   => (int)$conn->query("SELECT COUNT(*) FROM visit_logs WHERE check_out_time IS NULL")->fetch_row()[0],
    'overstay'  => (int)$conn->query("SELECT COUNT(*) FROM gate_passes gp JOIN visit_logs vl ON gp.id=vl.pass_id WHERE vl.check_out_time IS NULL AND gp.status='expired'")->fetch_row()[0],
    'residents' => (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='resident'")->fetch_row()[0],
    'incidents' => (int)$conn->query("SELECT COUNT(*) FROM incident_reports WHERE status='reported'")->fetch_row()[0],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics & Reports - AGPMS Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body class="bg-light">
<div class="container-fluid px-4 mt-4 mb-5">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm border-primary border-start border-4">
    <h2 class="mb-0 brand-font text-primary"><i class="bi bi-bar-chart-fill"></i> Analytics &amp; Traffic Reports</h2>
    <div class="d-flex gap-2">
      <a href="download_audit.php" class="btn btn-outline-primary rounded-pill btn-sm px-3"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV</a>
      <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <?php
    $kpis = [
      ['label' => 'Total Gate Passes', 'value' => $totals['passes'], 'icon' => 'bi-card-checklist', 'color' => 'primary'],
      ['label' => 'Total Visits Logged', 'value' => $totals['visits'], 'icon' => 'bi-door-open-fill', 'color' => 'info'],
      ['label' => 'Currently On-Site', 'value' => $totals['on_site'], 'icon' => 'bi-people-fill', 'color' => 'success'],
      ['label' => 'Overstay Alerts', 'value' => $totals['overstay'], 'icon' => 'bi-alarm-fill', 'color' => 'warning'],
      ['label' => 'Residents Registered', 'value' => $totals['residents'], 'icon' => 'bi-house-fill', 'color' => 'secondary'],
      ['label' => 'Open Incidents', 'value' => $totals['incidents'], 'icon' => 'bi-exclamation-triangle-fill', 'color' => 'danger'],
    ];
    foreach ($kpis as $k): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card glass-card hover-lift shadow-sm border-<?php echo $k['color']; ?> border-start border-3 h-100">
        <div class="card-body text-center py-3">
          <i class="bi <?php echo $k['icon']; ?> text-<?php echo $k['color']; ?> fs-2 d-block mb-2"></i>
          <p class="fs-2 fw-bold mb-0 text-<?php echo $k['color']; ?>"><?php echo $k['value']; ?></p>
          <small class="text-muted"><?php echo $k['label']; ?></small>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts Row 1: Hourly Heatmap + Daily Trend -->
  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <div class="card glass-card shadow-sm border-0 p-4 h-100">
        <h5 class="brand-font mb-1"><i class="bi bi-clock-history text-primary"></i> 24-Hour Entry Density (Last 30 Days)</h5>
        <p class="text-muted small mb-3">Shows which hours of the day have the highest visitor entry activity.</p>
        <div id="chartHourly"></div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card glass-card shadow-sm border-0 p-4 h-100">
        <h5 class="brand-font mb-1"><i class="bi bi-graph-up text-success"></i> 14-Day Visitor Inflow</h5>
        <p class="text-muted small mb-3">Daily visitor arrivals over the past two weeks.</p>
        <div id="chartDaily"></div>
      </div>
    </div>
  </div>

  <!-- Charts Row 2: Pass Types + Top Houses -->
  <div class="row g-4">
    <div class="col-md-5">
      <div class="card glass-card shadow-sm border-0 p-4 h-100">
        <h5 class="brand-font mb-1"><i class="bi bi-pie-chart-fill text-info"></i> Pass Type Breakdown</h5>
        <p class="text-muted small mb-3">Distribution of one-time, delivery, and recurring passes.</p>
        <div id="chartPassTypes"></div>
      </div>
    </div>
    <div class="col-md-7">
      <div class="card glass-card shadow-sm border-0 p-4 h-100">
        <h5 class="brand-font mb-1"><i class="bi bi-house-fill text-warning"></i> Top 8 Most Visited Houses</h5>
        <p class="text-muted small mb-3">Houses receiving the most gate visitors this period.</p>
        <div id="chartTopHouses"></div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 1. Hourly heatmap (bar chart)
const hourlyData = <?php echo json_encode(array_values($hourly)); ?>;
new ApexCharts(document.getElementById('chartHourly'), {
  series: [{ name: 'Entries', data: hourlyData }],
  chart: { type: 'bar', height: 280, toolbar: { show: false } },
  colors: ['#4f46e5'],
  xaxis: {
    categories: Array.from({ length: 24 }, (_, i) => i.toString().padStart(2, '0') + ':00'),
    labels: { rotate: -45, style: { fontSize: '10px' } }
  },
  yaxis: { title: { text: 'Visitor Count' } },
  tooltip: { x: { formatter: v => v + ':00 – ' + (parseInt(v) + 1).toString().padStart(2, '0') + ':00' } },
  plotOptions: {
    bar: {
      borderRadius: 4,
      columnWidth: '70%',
      colors: {
        ranges: [
          { from: 0, to: 3, color: '#c7d2fe' },
          { from: 4, to: 10, color: '#818cf8' },
          { from: 11, to: 999, color: '#4f46e5' }
        ]
      }
    }
  }
}).render();

// 2. 14-Day daily trend
const dayLabels = <?php echo json_encode($day_labels); ?>;
const dayValues = <?php echo json_encode($day_values); ?>;
new ApexCharts(document.getElementById('chartDaily'), {
  series: [{ name: 'Visitors', data: dayValues }],
  chart: { type: 'area', height: 280, toolbar: { show: false }, sparkline: { enabled: false } },
  stroke: { curve: 'smooth', width: 3 },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
  colors: ['#10b981'],
  xaxis: { categories: dayLabels, labels: { rotate: -45, style: { fontSize: '10px' } } },
  yaxis: { min: 0 },
  dataLabels: { enabled: false }
}).render();

// 3. Pass Types donut
const ptData = <?php echo json_encode(array_values($pass_types)); ?>;
new ApexCharts(document.getElementById('chartPassTypes'), {
  series: ptData,
  labels: ['One-Time', 'Delivery', 'Recurring'],
  chart: { type: 'donut', height: 280 },
  colors: ['#4f46e5', '#f59e0b', '#10b981'],
  legend: { position: 'bottom' },
  plotOptions: { pie: { donut: { size: '60%' } } },
  dataLabels: { enabled: true, formatter: (v, o) => o.w.config.labels[o.seriesIndex] + ': ' + Math.round(v) + '%' }
}).render();

// 4. Top houses horizontal bar
const houseLabels = <?php echo json_encode(array_map(fn($h) => 'House ' . $h['house_no'], $top_houses)); ?>;
const houseValues = <?php echo json_encode(array_map(fn($h) => (int)$h['total'], $top_houses)); ?>;
new ApexCharts(document.getElementById('chartTopHouses'), {
  series: [{ name: 'Total Visits', data: houseValues }],
  chart: { type: 'bar', height: 280, toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  colors: ['#f59e0b'],
  xaxis: { categories: houseLabels },
  dataLabels: { enabled: true, style: { colors: ['#1e293b'] } }
}).render();
</script>
</body>
</html>
