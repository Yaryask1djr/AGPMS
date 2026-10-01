<?php
require_once __DIR__ . "/includes/auth.php";
require_role("admin");
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Only allow admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied.";
    exit();
}

$alert = '';
$alert_type = '';

// Handle toggle action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $toggle_id = intval($_POST['toggle_user_id']);
    $new_status = sanitize($_POST['new_status']);
    
    if (in_array($new_status, ['cleared', 'defaulter'])) {
        $stmt = $conn->prepare("UPDATE users SET dues_status = ? WHERE id = ? AND role = 'resident'");
        $stmt->bind_param("si", $new_status, $toggle_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $alert = ($new_status === 'defaulter') ? "Resident flagged as defaulter." : "Resident cleared successfully.";
            $alert_type = ($new_status === 'defaulter') ? "warning" : "success";
        } else {
            $alert = "No changes made. User may not exist.";
            $alert_type = "info";
        }
        $stmt->close();
    }
}

// Fetch all residents
$residents = $conn->query("SELECT id, name, phone, house_no, dues_status FROM users WHERE role = 'resident' ORDER BY house_no ASC, name ASC");

// Count stats
$total_residents = 0;
$total_defaulters = 0;
$resident_list = [];
while ($r = $residents->fetch_assoc()) {
    $resident_list[] = $r;
    $total_residents++;
    if ($r['dues_status'] === 'defaulter') $total_defaulters++;
}
$total_cleared = $total_residents - $total_defaulters;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Estate Dues - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
  
  <div class="container mt-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm border-warning border-start border-4">
      <h2 class="mb-0 brand-font text-warning"><i class="bi bi-cash-coin"></i> Manage Estate Dues</h2>
      <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if ($alert): ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?php echo $alert_type; ?>',
                title: '<?php echo htmlspecialchars($alert); ?>',
                showConfirmButton: false,
                timer: 3500
            });
        });
      </script>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card glass-card hover-lift border-primary border-start border-4">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Total Residents</h6>
                    <p class="fs-2 fw-bold text-primary mb-0"><?php echo $total_residents; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card glass-card hover-lift border-success border-start border-4">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Cleared</h6>
                    <p class="fs-2 fw-bold text-success mb-0"><?php echo $total_cleared; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card glass-card hover-lift border-danger border-start border-4 position-relative">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Defaulters</h6>
                    <p class="fs-2 fw-bold text-danger mb-0"><?php echo $total_defaulters; ?></p>
                </div>
                <?php if($total_defaulters > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle p-2 bg-danger border border-light rounded-circle shadow-sm" style="animation: pulse-neon 2s infinite;"></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Residents Table -->
    <div class="card glass-card p-4 hover-lift shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="brand-font mb-0"><i class="bi bi-people-fill text-primary"></i> Resident Dues Status</h5>
            <input type="text" id="searchInput" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Search by name, phone, or house...">
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="duesTable">
                <thead class="table-dark">
                    <tr>
                        <th>House</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resident_list as $r): ?>
                    <tr>
                        <td><span class="badge bg-secondary rounded-pill">House <?php echo htmlspecialchars($r['house_no']); ?></span></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo htmlspecialchars($r['phone']); ?></td>
                        <td>
                            <?php if ($r['dues_status'] === 'defaulter'): ?>
                                <span class="badge bg-danger rounded-pill px-3 py-2"><i class="bi bi-x-circle-fill"></i> Defaulter</span>
                            <?php else: ?>
                                <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill"></i> Cleared</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <form method="POST" class="d-inline" onsubmit="return confirmToggle(this, '<?php echo htmlspecialchars($r['name']); ?>
<?php echo csrf_field(); ?>', '<?php echo ($r['dues_status'] === 'defaulter') ? 'cleared' : 'defaulter'; ?>');">
                                <input type="hidden" name="toggle_user_id" value="<?php echo $r['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo ($r['dues_status'] === 'defaulter') ? 'cleared' : 'defaulter'; ?>">
                                <?php if ($r['dues_status'] === 'defaulter'): ?>
                                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm">
                                        <i class="bi bi-check-lg"></i> Mark Cleared
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                        <i class="bi bi-slash-circle"></i> Flag as Defaulter
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($resident_list)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No residents registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Confirm toggle with SweetAlert
    function confirmToggle(form, name, newStatus) {
        event.preventDefault();
        const action = (newStatus === 'defaulter') ? 'flag as a defaulter' : 'clear dues for';
        const icon = (newStatus === 'defaulter') ? 'warning' : 'question';
        const confirmText = (newStatus === 'defaulter') ? 'Yes, Flag Defaulter' : 'Yes, Clear Dues';
        const confirmColor = (newStatus === 'defaulter') ? '#dc3545' : '#198754';

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to ${action} "${name}". This will ${newStatus === 'defaulter' ? 'block them from creating gate passes' : 'restore their gate pass access'}.`,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
        return false;
    }

    // Live search filter
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#duesTable tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
  </script>
</body>
</html>
