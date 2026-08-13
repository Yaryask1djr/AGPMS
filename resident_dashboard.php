<?php
include 'includes/auth.php';
require_role('resident');
include 'includes/db.php';

$user_id = $_SESSION['user_id'];

// Check if resident is approved
$stmt = $conn->prepare("SELECT approved FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($approved);
$stmt->fetch();
$stmt->close();

if (!$approved) {
  // Not approved, show message and exit
  echo "<!DOCTYPE html>
  <html lang='en'>
  <head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Approval Required - AGPMS</title>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'>
    <link rel='manifest' href='manifest.json'>
  </head>
  <body>
    <div class='container mt-5'>
      <div class='alert alert-warning text-center'>
        Your account is pending admin approval. Please wait for approval before accessing the dashboard.
      </div>
    </div>
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('sw.js');
        });
      }
    </script>
  </body>
  </html>";
  exit();
}

// Fetch resident profile info
$stmt = $conn->prepare("SELECT name, house_no, phone, profile_pic, dues_status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $house_no, $phone, $profile_pic, $dues_status);
$stmt->fetch();
$stmt->close();

$is_defaulter = ($dues_status === 'defaulter');

$result = $conn->query("SELECT * FROM gate_passes WHERE user_id = $user_id ORDER BY created_at DESC");
$complaints = $conn->query("SELECT * FROM complaints WHERE user_id = $user_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Dashboard - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="manifest" href="manifest.json">
  <script src="assets/js/dark-mode.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4 glass-card p-3 hover-lift shadow-sm">
    <h2 class="mb-0 brand-font"><i class="bi bi-person-circle text-primary"></i> Resident Dashboard</h2>
    <div class="d-flex gap-2 align-items-center">
        <div class="position-relative">
          <button id="notifBell" class="btn btn-outline-warning position-relative" title="Notifications">
            <i class="bi bi-bell-fill"></i>
            <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
          </button>
          <div id="notifDropdown" class="card shadow-lg position-absolute end-0 mt-2" style="display:none; width:340px; max-height:400px; overflow-y:auto; z-index:1050;">
            <div class="card-header fw-bold d-flex justify-content-between align-items-center">
              <span><i class="bi bi-bell"></i> Notifications</span>
              <button id="markAllRead" class="btn btn-sm btn-outline-secondary">Mark all read</button>
            </div>
            <ul id="notifList" class="list-group list-group-flush">
              <li class="list-group-item text-center text-muted">No new notifications</li>
            </ul>
          </div>
        </div>
        <button id="darkModeToggle" class="btn btn-outline-secondary"><i class="bi bi-moon-fill"></i></button>
        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
        <button id="panicBtn" class="btn btn-danger" title="Emergency SOS"><i class="bi bi-exclamation-triangle-fill"></i> SOS</button>
    </div>
</div>

    <!-- Resident Profile Info (Digital ID Card) -->
    <div class="d-flex justify-content-center">
      <div class="card mb-4 digital-id-card hover-lift p-4" style="max-width: 400px; width: 100%;">
        <div class="d-flex align-items-center">
          <div class="me-3">
            <?php if (!empty($profile_pic)): ?>
                <img src="uploads/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="rounded-circle border border-2 border-white shadow" style="width: 80px; height: 80px; object-fit: cover;">
            <?php else: ?>
                <div class="bg-white text-primary rounded-circle d-flex justify-content-center align-items-center shadow" style="width: 80px; height: 80px; font-size: 2.5rem;">
                    <i class="bi bi-person-fill"></i>
                </div>
            <?php endif; ?>
          </div>
          <div>
            <h4 class="mb-1 fw-bold brand-font"><?php echo htmlspecialchars($name); ?></h4>
            <p class="mb-0 opacity-75"><i class="bi bi-house-door-fill"></i> House No: <strong><?php echo htmlspecialchars($house_no); ?></strong></p>
            <p class="mb-0 opacity-75"><i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($phone); ?></p>
          </div>
        </div>
        <div class="mt-3 text-end">
            <a href="resident_settings.php" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm text-primary fw-bold">Edit Profile</a>
        </div>
      </div>
    </div>
    <!-- End Resident Profile Info -->

    <?php if ($is_defaulter): ?>
    <div class="alert alert-danger shadow-sm rounded-3 d-flex align-items-center gap-3 mb-4" role="alert">
        <i class="bi bi-exclamation-octagon-fill fs-3"></i>
        <div>
            <strong>Account Restricted</strong><br>
            Your account has been flagged due to unpaid estate dues. You cannot create new gate passes until your dues are cleared. Please contact the estate admin.
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['alert']) && $_GET['alert'] === 'defaulter'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Access Blocked',
                text: 'You cannot create gate passes while your estate dues are unpaid.',
                confirmButtonColor: '#dc3545'
            });
        });
    </script>
    <?php endif; ?>

    <?php if (!$is_defaulter): ?>
        <a href="create_pass.php" class="btn btn-success mb-3 rounded-pill shadow-sm hover-lift"><i class="bi bi-plus-circle"></i> Create New Pass</a>
    <?php else: ?>
        <button class="btn btn-secondary mb-3 rounded-pill" disabled><i class="bi bi-lock-fill"></i> Create New Pass (Blocked)</button>
    <?php endif; ?>

    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Visitor</th>
          <th>Purpose</th>
          <th>Vehicle</th>
          <th>Entry</th>
          <th>Exit</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?php echo $row['visitor_name']; ?></td>
          <td><?php echo $row['purpose']; ?></td>
          <td><?php echo $row['vehicle_details']; ?></td>
          <td><?php echo $row['entry_time']; ?></td>
          <td><?php echo $row['exit_time']; ?></td>
          <td><?php echo $row['status']; ?></td>
          <td>
            <a href="view_pass.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">View</a>
            <?php if ($row['status'] == 'pending' || ($row['status'] == 'active' && $row['pass_type'] == 'recurring')): ?>
              <a href="edit_pass.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
              <a href="cancel_pass.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger">Cancel</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-5 mb-3">
      <h4 class="brand-font"><i class="bi bi-headset text-warning"></i> My Complaints & Support Tickets</h4>
      <a href="submit_complaint.php" class="btn btn-secondary rounded-pill px-3 shadow-sm hover-lift">Report an Issue</a>
    </div>
    <div class="glass-card p-3 hover-lift">
    <table class="table table-hover mb-0">
      <thead class="table-dark">
        <tr>
          <th>Subject</th>
          <th>Description</th>
          <th>Status</th>
          <th>Submitted On</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($complaints->num_rows > 0): ?>
          <?php while ($c = $complaints->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($c['subject']); ?></td>
            <td><?php echo htmlspecialchars($c['description']); ?></td>
            <td>
              <?php
                if ($c['status'] == 'pending') echo '<span class="badge bg-warning text-dark">Pending</span>';
                elseif ($c['status'] == 'in_progress') echo '<span class="badge bg-info text-dark">In Progress</span>';
                else echo '<span class="badge bg-success">Resolved</span>';
              ?>
            </td>
            <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="4" class="text-center text-muted">No complaints submitted.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
<script>
document.getElementById('panicBtn').addEventListener('click', function(){
    fetch('panic_alert.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
    })
    .then(r => r.json())
    .then(data => {
        if(data.success){
            Swal.fire({icon:'success', title:'SOS Sent', text:'Your emergency alert has been dispatched to security.'});
        } else {
            Swal.fire({icon:'error', title:'Error', text: data.error || 'Failed to send alert.'});
        }
    })
    .catch(()=>{ Swal.fire({icon:'error',title:'Error',text:'Network error.'}); });
});
});

// === Visitor Arrival Notification System ===
let lastNotifIds = new Set();
const notifBell = document.getElementById('notifBell');
const notifDropdown = document.getElementById('notifDropdown');
const notifBadge = document.getElementById('notifBadge');
const notifList = document.getElementById('notifList');

// Toggle dropdown
notifBell.addEventListener('click', function(e) {
  e.stopPropagation();
  notifDropdown.style.display = notifDropdown.style.display === 'none' ? 'block' : 'none';
});
document.addEventListener('click', function(e) {
  if (!notifDropdown.contains(e.target) && e.target !== notifBell) {
    notifDropdown.style.display = 'none';
  }
});

// Request browser notification permission
if ('Notification' in window && Notification.permission === 'default') {
  Notification.requestPermission();
}

function loadNotifications() {
  fetch('fetch_notifications.php')
    .then(r => r.json())
    .then(data => {
      if (data.length === 0) {
        notifList.innerHTML = '<li class="list-group-item text-center text-muted">No new notifications</li>';
        notifBadge.style.display = 'none';
        return;
      }
      notifBadge.textContent = data.length;
      notifBadge.style.display = 'inline-block';

      notifList.innerHTML = '';
      data.forEach(n => {
        const li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action';
        li.innerHTML = `<div class="d-flex justify-content-between align-items-start">
          <div><strong>${n.title}</strong><br><small>${n.message}</small></div>
          <small class="text-muted text-nowrap ms-2">${n.time_ago}</small>
        </div>`;
        li.style.cursor = 'pointer';
        li.addEventListener('click', () => markRead(n.id, li));
        notifList.appendChild(li);

        // Browser push notification for NEW items only
        if (!lastNotifIds.has(n.id) && 'Notification' in window && Notification.permission === 'granted') {
          new Notification(n.title, { body: n.message, icon: 'assets/icons/icon-192.png' });
        }
      });
      // Update known IDs
      lastNotifIds = new Set(data.map(n => n.id));
    })
    .catch(() => { console.error('Failed to load notifications'); });
}

function markRead(id, el) {
  fetch('mark_notification_read.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'id=' + id
  }).then(() => {
    el.remove();
    const remaining = notifList.querySelectorAll('.list-group-item-action').length;
    if (remaining === 0) {
      notifList.innerHTML = '<li class="list-group-item text-center text-muted">No new notifications</li>';
      notifBadge.style.display = 'none';
    } else {
      notifBadge.textContent = remaining;
    }
  });
}

// Mark all as read
document.getElementById('markAllRead').addEventListener('click', function() {
  const items = notifList.querySelectorAll('.list-group-item-action');
  items.forEach(li => {
    const id = li.querySelector('strong') ? li.dataset.nid : null;
  });
  // Batch mark via individual calls (simple approach)
  fetch('mark_notification_read.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'all=1'
  }).then(() => {
    notifList.innerHTML = '<li class="list-group-item text-center text-muted">No new notifications</li>';
    notifBadge.style.display = 'none';
  });
});

// Poll every 15 seconds
loadNotifications();
setInterval(loadNotifications, 15000);
</script>
</body>
</html>
