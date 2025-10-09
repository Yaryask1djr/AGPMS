<?php
include 'includes/auth.php';
require_role('admin');
include 'includes/db.php';

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
$conn->query("UPDATE gate_passes SET status = 'expired' WHERE status != 'expired' AND exit_time < '$now'");

// Get the most recent month volume
$monthly_volume_row = $conn->query("SELECT COUNT(*) AS volume FROM gate_passes WHERE DATE_FORMAT(entry_time, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetch_assoc();
$monthly_volume = $monthly_volume_row ? $monthly_volume_row['volume'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
  <div class="container mt-5">
    <div class="row mb-4">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
          <!-- Centered Admin Profile Info at the top left-hand side -->
          <div class="mx-auto" style="min-width:260px;max-width:320px;">
            <div class="card h-100">
              <div class="card-body text-center">
                <h5 class="card-title mb-2">Admin Profile</h5>
                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></p>
                <p class="mb-1"><strong>Role:</strong> Admin</p>
                <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($_SESSION['admin_phone'] ?? 'N/A'); ?></p>
              </div>
            </div>
          </div>
          <!-- Admin Actions Button at the top right -->
          <div>
            <button class="btn btn-primary ms-3" style="z-index:1050;" data-bs-toggle="offcanvas" data-bs-target="#adminActionsPanel">
              <i class="bi bi-plus-circle"></i> Admin Actions
            </button>
          </div>
        </div>
      </div>
    </div>
    <div class="row mb-4">
      <!-- Dashboard Analytics and Content -->
      
        <!-- Analytics -->
        <div class="row mb-4">
          <div class="col-md-4">
            <div class="card text-bg-primary mb-3">
              <div class="card-body">
                <h5 class="card-title">Total Passes</h5>
                <p class="card-text fs-3"><?php echo $total_passes; ?></p>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#totalPassesModal">
                  View All Passes
                </button>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card text-bg-success mb-3">
              <div class="card-body">
                <h5 class="card-title">Today's Passes</h5>
                <p class="card-text fs-3"><?php echo $today_passes; ?></p>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#todayPassesModal">
                  View Passes
                </button>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card text-bg-info mb-3">
              <div class="card-body">
                <h5 class="card-title">Recent Monthly Volume</h5>
                <p class="card-text fs-3"><?php echo $monthly_volume; ?></p>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#monthlyVolumeModal">
                  View Statistics
                </button>
              </div>
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
                <a href="approve_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success">Approve</a>
                <a href="revoke_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger">Revoke</a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>

        <!-- All Registered Users -->
        <h4 class="mt-5">All Registered Users</h4>
        <div class="mb-2">
          <a href="download_users.php" class="btn btn-outline-secondary btn-sm">
            Download Users
          </a>
        </div>
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>Name</th>
              <th>Phone</th>
              <th>House No</th>
              <th>Role</th>
              <th>Status</th>
              <th>Approved</th>
              <th>Registered At</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $all_users = $conn->query("SELECT name, phone, house_no, role, status, approved, created_at FROM users ORDER BY created_at DESC");
            while ($user = $all_users->fetch_assoc()):
            ?>
            <tr>
              <td><?php echo htmlspecialchars($user['name']); ?></td>
              <td><?php echo htmlspecialchars($user['phone']); ?></td>
              <td><?php echo htmlspecialchars($user['house_no']); ?></td>
              <td><?php echo htmlspecialchars($user['role']); ?></td>
              <td><?php echo htmlspecialchars($user['status']); ?></td>
              <td><?php echo $user['approved'] ? 'Yes' : 'No'; ?></td>
              <td><?php echo htmlspecialchars($user['created_at']); ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>

        <!-- Offcanvas Dialog for Admin Actions -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="adminActionsPanel" aria-labelledby="adminActionsPanelLabel">
          <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="adminActionsPanelLabel">Admin Actions</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body">
            <!-- Create Resident Account -->
            <h5>Create Resident Account</h5>
            <form method="POST" action="create_resident.php" class="mb-4">
              <div class="mb-2">
                <input type="text" name="name" class="form-control" placeholder="Full Name" required>
              </div>
              <div class="mb-2">
                <input type="text" name="house_no" class="form-control" placeholder="House Number" required>
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
            <canvas id="monthlyVolumeChart" width="400" height="250"></canvas>
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
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
              document.addEventListener("DOMContentLoaded", function() {
                var ctx = document.getElementById('monthlyVolumeChart').getContext('2d');
                new Chart(ctx, {
                  type: 'bar',
                  data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                      label: 'Gate Pass Volume',
                      data: <?php echo json_encode($volumes); ?>,
                      backgroundColor: 'rgba(54, 162, 235, 0.7)',
                      borderColor: 'rgba(54, 162, 235, 1)',
                      borderWidth: 1
                    }]
                  },
                  options: {
                    scales: {
                      y: {
                        beginAtZero: true,
                        precision: 0
                      }
                    }
                  }
                });
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
    <!-- Optionally, add Bootstrap Icons CDN for the plus icon -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  </div>
</body>
</html>
