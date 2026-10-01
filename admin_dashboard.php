<?php
require_once 'includes/auth.php';
require_role('admin');
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Pending users (status = 'pending')
$pending_users = $conn->query("SELECT * FROM users WHERE status = 'pending'");

// Analytics
$total_passes = $conn->query("SELECT COUNT(*) AS total FROM gate_passes")->fetch_assoc()['total'];
$today_passes = $conn->query("SELECT COUNT(*) AS total FROM gate_passes WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$monthly_passes = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count FROM gate_passes GROUP BY month ORDER BY month DESC LIMIT 6");

// Update pass statuses
$now = date('Y-m-d H:i:s');

// Set passes to 'active' if entry_time <= now and status is 'pending'
$conn->query("UPDATE gate_passes SET status = 'active' WHERE status = 'pending' AND entry_time <= '$now'");

// Set passes to 'expired' if exit_time < now and status is not 'expired'
$conn->query("UPDATE gate_passes SET status = 'expired' WHERE status IN ('pending','active') AND exit_time < '$now'");

// Get the most recent month volume
$monthly_volume_row = $conn->query("SELECT COUNT(*) AS volume FROM gate_passes WHERE DATE_FORMAT(entry_time, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetch_assoc();
$monthly_volume = $monthly_volume_row ? $monthly_volume_row['volume'] : 0;

// Fetch Overstays Count
$overstay_row = $conn->query("SELECT COUNT(*) AS cnt FROM gate_passes gp JOIN visit_logs vl ON gp.id = vl.pass_id WHERE vl.check_out_time IS NULL AND gp.status = 'expired'")->fetch_assoc();
$overstay_count = $overstay_row ? $overstay_row['cnt'] : 0;

// Fetch data for Chart.js
$chart_data = [];
$res = $conn->query("SELECT pass_type, COUNT(*) as count FROM gate_passes GROUP BY pass_type");
while ($row = $res->fetch_assoc()) {
    $chart_data[$row['pass_type']] = $row['count'];
}
$one_time = $chart_data['one_time'] ?? 0;
$delivery = $chart_data['delivery'] ?? 0;
$recurring = $chart_data['recurring'] ?? 0;

// User Pagination & Search
$u_search = trim($_GET['u_q'] ?? '');
$u_page = max(1, intval($_GET['u_page'] ?? 1));
$u_per_page = 10;
$u_offset = ($u_page - 1) * $u_per_page;

if (!empty($u_search)) {
    $u_like = "%{$u_search}%";
    $c_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE name LIKE ? OR phone LIKE ? OR house_no LIKE ?");
    $c_stmt->bind_param("sss", $u_like, $u_like, $u_like);
    $c_stmt->execute();
    $total_users_count = $c_stmt->get_result()->fetch_assoc()['cnt'];
    $c_stmt->close();

    $u_stmt = $conn->prepare("SELECT id, name, phone, house_no, role, status, approved, created_at FROM users WHERE name LIKE ? OR phone LIKE ? OR house_no LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $u_stmt->bind_param("sssii", $u_like, $u_like, $u_like, $u_per_page, $u_offset);
    $u_stmt->execute();
    $all_users = $u_stmt->get_result();
} else {
    $total_users_count = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
    $u_stmt = $conn->prepare("SELECT id, name, phone, house_no, role, status, approved, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $u_stmt->bind_param("ii", $u_per_page, $u_offset);
    $u_stmt->execute();
    $all_users = $u_stmt->get_result();
}
$u_total_pages = max(1, ceil($total_users_count / $u_per_page));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="manifest" href="manifest.json">
  <script src="assets/js/dark-mode.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body>
  <div class="container mt-5">
    
    <!-- Top Header Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm border-primary border-start border-4">
      <h2 class="mb-0 brand-font text-primary"><i class="bi bi-person-badge"></i> Admin Control Center</h2>
      
      <div class="d-flex gap-3 align-items-center">
        <!-- Admin Profile Snippet -->
        <div class="d-none d-md-block text-end me-3 border-end pe-3">
            <span class="d-block fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
            <small class="text-muted"><?php echo htmlspecialchars($_SESSION['admin_phone'] ?? ''); ?></small>
        </div>

        <!-- Action Buttons -->
        <button class="btn btn-primary rounded-pill shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#adminActionsPanel">
            <i class="bi bi-grid-fill"></i> Menu
        </button>
        <button id="darkModeToggle" class="btn btn-outline-secondary rounded-circle shadow-sm" style="width: 40px; height: 40px; padding: 0;"><i class="bi bi-moon-fill"></i></button>
        <a href="logout.php" class="btn btn-outline-danger rounded-circle shadow-sm" style="width: 40px; height: 40px; padding: 0; line-height: 28px;"><i class="bi bi-box-arrow-right"></i></a>
    </div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav">
  <a href="admin_dashboard.php" class="nav-item active"><i class="bi bi-speedometer2"></i>Dashboard</a>
  <a href="analytics.php" class="nav-item"><i class="bi bi-bar-chart-fill"></i>Analytics</a>
  <a href="manage_incidents.php" class="nav-item danger"><i class="bi bi-shield-exclamation"></i>Incidents</a>
  <button class="nav-item" data-bs-toggle="offcanvas" data-bs-target="#adminActionsPanel"><i class="bi bi-grid-fill"></i>Menu</button>
  <a href="logout.php" class="nav-item"><i class="bi bi-box-arrow-right"></i>Logout</a>
</nav>

    <!-- Dashboard Analytics and Content -->

      <!-- Analytics Cards -->
      <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
          <div class="card glass-card hover-lift border-primary border-start border-4 h-100">
            <div class="card-body">
              <h5 class="card-title text-primary fw-bold brand-font" style="font-size:0.9rem;">Total Passes</h5>
              <p class="card-text fs-2 mb-2 kpi-card-value"><?php echo $total_passes; ?></p>
              <button type="button" class="btn btn-primary btn-sm rounded-pill shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#totalPassesModal">
                View <i class="bi bi-arrow-right"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card glass-card hover-lift border-success border-start border-4 h-100">
            <div class="card-body">
              <h5 class="card-title text-success fw-bold brand-font" style="font-size:0.9rem;">Today's Passes</h5>
              <p class="card-text fs-2 mb-2 kpi-card-value"><?php echo $today_passes; ?></p>
              <button type="button" class="btn btn-success btn-sm rounded-pill shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#todayPassesModal">
                View <i class="bi bi-arrow-right"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card glass-card hover-lift border-info border-start border-4 h-100">
            <div class="card-body">
              <h5 class="card-title text-info fw-bold brand-font" style="font-size:0.9rem;">Monthly Volume</h5>
              <p class="card-text fs-2 mb-2 kpi-card-value"><?php echo $monthly_volume; ?></p>
              <button type="button" class="btn btn-info btn-sm rounded-pill shadow-sm px-3 text-white" data-bs-toggle="modal" data-bs-target="#monthlyVolumeModal">
                Stats <i class="bi bi-arrow-right"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card glass-card hover-lift border-danger border-start border-4 position-relative h-100">
            <div class="card-body">
              <h5 class="card-title text-danger fw-bold brand-font" style="font-size:0.9rem;">Active Overstays</h5>
              <p class="card-text fs-2 mb-2 kpi-card-value"><?php echo $overstay_count; ?></p>
            </div>
            <?php if($overstay_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle p-2 bg-danger border border-light rounded-circle shadow-sm" style="animation: pulse-neon 2s infinite;"></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Visual Analytics -->
      <div class="row mb-5">
        <div class="col-md-12">
          <div class="card glass-card p-4 hover-lift">
            <h5 class="mb-4 brand-font text-primary"><i class="bi bi-pie-chart-fill"></i> Pass Type Distribution</h5>
            <div id="passTypeApexChart"></div>
          </div>
        </div>
      </div>

      <!-- Pending Account Approvals -->
      <h4>Pending Account Approvals</h4>
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>House No</th>
            <th>Role</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($user = $pending_users->fetch_assoc()): ?>
            <tr>
              <td><?php echo $user['name']; ?></td>
              <td><?php echo $user['phone']; ?></td>
              <td><?php echo $user['house_no']; ?></td>
              <td><?php echo $user['role']; ?></td>
              <td>
                <form method="POST" action="approve_user.php" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $user['id']; ?>"><button class="btn btn-sm btn-outline-primary" type="submit">Approve</button></form>
                <form method="POST" action="revoke_user.php" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $user['id']; ?>"><button class="btn btn-sm btn-outline-primary" type="submit">Revoke</button></form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <!-- All Registered Users -->
      <div class="card glass-card p-4 hover-lift mt-5 shadow-sm">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <h4 class="brand-font mb-0 text-primary"><i class="bi bi-people-fill"></i> All Registered Users</h4>
          <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
              <input type="text" name="u_q" class="form-control form-control-sm" placeholder="Search users..." value="<?php echo htmlspecialchars($u_search); ?>">
              <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Filter</button>
              <?php if (!empty($u_search)): ?>
                <a href="admin_dashboard.php" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
              <?php endif; ?>
            </form>
            <a href="download_users.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
              <i class="bi bi-download"></i> Export CSV
            </a>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>Name</th>
                <th>Phone (Username)</th>
                <th>House No</th>
                <th>Role</th>
                <th>Status</th>
                <th>Approved</th>
                <th>Registered At</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($all_users && $all_users->num_rows > 0): ?>
                <?php while ($user = $all_users->fetch_assoc()): ?>
                  <tr>
                    <td class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                    <td><?php echo $user['house_no'] ? 'House ' . htmlspecialchars($user['house_no']) : '-'; ?></td>
                    <td>
                      <span class="badge bg-<?php echo ($user['role'] === 'admin') ? 'danger' : (($user['role'] === 'security') ? 'warning text-dark' : 'primary'); ?>">
                        <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-<?php echo ($user['status'] === 'active') ? 'success' : (($user['status'] === 'pending') ? 'warning text-dark' : 'danger'); ?>">
                        <?php echo htmlspecialchars(ucfirst($user['status'])); ?>
                      </span>
                    </td>
                    <td><?php echo $user['approved'] ? '<i class="bi bi-check-circle-fill text-success"></i> Yes' : '<i class="bi bi-clock-fill text-warning"></i> Pending'; ?></td>
                    <td><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($u_total_pages > 1): ?>
        <nav class="d-flex justify-content-center mt-4">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?php echo ($u_page <= 1) ? 'disabled' : ''; ?>">
              <a class="page-link" href="?u_page=<?php echo $u_page - 1; ?>&u_q=<?php echo urlencode($u_search); ?>"><i class="bi bi-chevron-left"></i></a>
            </li>
            <?php for ($i = max(1, $u_page - 2); $i <= min($u_total_pages, $u_page + 2); $i++): ?>
              <li class="page-item <?php echo ($i === $u_page) ? 'active' : ''; ?>">
                <a class="page-link" href="?u_page=<?php echo $i; ?>&u_q=<?php echo urlencode($u_search); ?>"><?php echo $i; ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($u_page >= $u_total_pages) ? 'disabled' : ''; ?>">
              <a class="page-link" href="?u_page=<?php echo $u_page + 1; ?>&u_q=<?php echo urlencode($u_search); ?>"><i class="bi bi-chevron-right"></i></a>
            </li>
          </ul>
        </nav>
        <?php endif; ?>
      </div>

      <!-- Offcanvas Dialog for Admin Actions -->
      <div class="offcanvas offcanvas-end" tabindex="-1" id="adminActionsPanel" aria-labelledby="adminActionsPanelLabel">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title" id="adminActionsPanelLabel">Admin Actions</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
          <div class="d-grid gap-2 mb-4">
            <a href="analytics.php" class="btn btn-primary text-start"><i class="bi bi-bar-chart-fill"></i> Analytics &amp; Reports</a>
            <a href="manage_incidents.php" class="btn btn-outline-danger text-start"><i class="bi bi-shield-exclamation"></i> Security Incidents</a>
            <a href="manage_complaints.php" class="btn btn-warning text-dark text-start"><i class="bi bi-ticket-detailed"></i> Manage Complaints</a>
            <a href="manage_dues.php" class="btn btn-outline-warning text-start"><i class="bi bi-cash-coin"></i> Manage Estate Dues</a>
            <a href="shift_logs.php" class="btn btn-secondary text-start"><i class="bi bi-clock-history"></i> Security Shift Logs</a>
            <a href="manage_blacklist.php" class="btn btn-danger text-start"><i class="bi bi-shield-lock"></i> Manage Blacklist</a>
            <a href="security_audit.php" class="btn btn-info text-dark text-start"><i class="bi bi-clipboard-data"></i> Security Audit Log</a>
          </div>
          <hr>
          <!-- Create Resident Account -->
          <h5>Create Resident Account</h5>
          <form method="POST" action="create_resident.php" class="mb-4">
<?php echo csrf_field(); ?>
            <div class="mb-2">
              <input type="text" name="name" class="form-control" placeholder="Full Name" required>
            </div>
            <div class="mb-2">
              <input type="number" name="house_no" class="form-control" placeholder="House Number (1-99)" min="1" max="99" required>
            </div>
            <div class="mb-2">
              <input type="text" name="phone" class="form-control" placeholder="Phone" required>
            </div>
            <div class="mb-2">
              <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Create Resident</button>
          </form>

          <!-- Create Security Account -->
          <h5>Create Security Account</h5>
          <form method="POST" action="create_security.php" class="mb-4">
<?php echo csrf_field(); ?>
            <div class="mb-2">
              <input type="text" name="name" class="form-control" placeholder="Full Name" required>
            </div>
            <div class="mb-2">
              <input type="text" name="phone" class="form-control" placeholder="Phone" required>
            </div>
            <div class="mb-2">
              <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Create Security</button>
          </form>

          <!-- Download Audit Trail -->
          <h5>Audit Trail</h5>
          <a href="download_audit.php" class="btn btn-outline-secondary w-100 mb-2">
            Download Audit Report
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Monthly Volume Modal -->
  <div class="modal fade" id="monthlyVolumeModal" tabindex="-1" aria-labelledby="monthlyVolumeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="monthlyVolumeModalLabel">Monthly Volume Statistics</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="monthlyVolumeChart"></div>
          <?php
          // Prepare data for the chart
          $stats = $conn->query("SELECT DATE_FORMAT(entry_time, '%Y-%m') AS month, COUNT(*) AS volume FROM gate_passes GROUP BY month ORDER BY month DESC LIMIT 6");
          $months = [];
          $volumes = [];
          while ($row = $stats->fetch_assoc()) {
            $months[] = $row['month'];
            $volumes[] = $row['volume'];
          }
          // Reverse to show oldest first
          $months = array_reverse($months);
          $volumes = array_reverse($volumes);
          ?>
          <script>
            document.addEventListener("DOMContentLoaded", function() {
              var options = {
                series: [{
                  name: 'Gate Pass Volume',
                  data: <?php echo json_encode($volumes); ?>
                }],
                chart: {
                  type: 'bar',
                  height: 350,
                  toolbar: { show: false }
                },
                colors: ['#0d6efd'],
                plotOptions: {
                  bar: {
                    borderRadius: 4,
                    horizontal: false,
                  }
                },
                dataLabels: { enabled: false },
                xaxis: {
                  categories: <?php echo json_encode($months); ?>,
                }
              };

              var chart = new ApexCharts(document.querySelector("#monthlyVolumeChart"), options);
              chart.render();
            });
          </script>
        </div>
      </div>
    </div>
  </div>

  <!-- Today's Passes Modal -->
  <div class="modal fade" id="todayPassesModal" tabindex="-1" aria-labelledby="todayPassesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="todayPassesModalLabel">Today's Generated Passes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Resident Name</th>
                <th>House No</th>
                <th>Visitor Name</th>
                <th>Entry Time</th>
                <th>Exit Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $today_passes_result = $conn->query(
                "SELECT gp.id, u.name AS resident_name, u.house_no, gp.visitor_name, gp.entry_time, gp.exit_time, gp.status
                   FROM gate_passes gp
                   JOIN users u ON gp.user_id = u.id
                   WHERE DATE(gp.created_at) = CURDATE()
                   ORDER BY gp.created_at DESC"
              );
              while ($row = $today_passes_result->fetch_assoc()):
              ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['id']); ?></td>
                  <td><?php echo htmlspecialchars($row['resident_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['house_no']); ?></td>
                  <td><?php echo htmlspecialchars($row['visitor_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['entry_time']); ?></td>
                  <td><?php echo htmlspecialchars($row['exit_time']); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Total Passes Modal -->
  <div class="modal fade" id="totalPassesModal" tabindex="-1" aria-labelledby="totalPassesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="totalPassesModalLabel">All Generated Passes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Resident Name</th>
                <th>House No</th>
                <th>Visitor Name</th>
                <th>Entry Time</th>
                <th>Exit Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $all_passes_result = $conn->query(
                "SELECT gp.id, u.name AS resident_name, u.house_no, gp.visitor_name, gp.entry_time, gp.exit_time, gp.status
                   FROM gate_passes gp
                   JOIN users u ON gp.user_id = u.id
                   ORDER BY gp.created_at DESC
                   LIMIT 100"
              );
              while ($row = $all_passes_result->fetch_assoc()):
              ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['id']); ?></td>
                  <td><?php echo htmlspecialchars($row['resident_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['house_no']); ?></td>
                  <td><?php echo htmlspecialchars($row['visitor_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['entry_time']); ?></td>
                  <td><?php echo htmlspecialchars($row['exit_time']); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <small class="text-muted">Showing latest 100 passes.</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS (required for offcanvas) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    var options = {
      series: [<?php echo $one_time; ?>, <?php echo $delivery; ?>, <?php echo $recurring; ?>],
      labels: ['One Time', 'Delivery', 'Recurring'],
      chart: {
        type: 'donut',
        height: 350,
        animations: { enabled: true }
      },
      colors: ['#0d6efd', '#ffc107', '#198754'],
      plotOptions: {
        pie: {
          donut: {
            size: '65%',
            labels: {
              show: true,
              name: { show: true, fontSize: '22px', fontFamily: 'Outfit, sans-serif' },
              value: { show: true, fontSize: '16px', fontFamily: 'Inter, sans-serif' }
            }
          }
        }
      },
      legend: { position: 'bottom' },
      dataLabels: { enabled: false }
    };
    var chart = new ApexCharts(document.querySelector("#passTypeApexChart"), options);
    chart.render();
  </script>
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js');
      });
    }
  </script>
  <!-- Optionally, add Bootstrap Icons CDN for the plus icon -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  </div>
</body>

</html>