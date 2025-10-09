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
  </head>
  <body>
    <div class='container mt-5'>
      <div class='alert alert-warning text-center'>
        Your account is pending admin approval. Please wait for approval before accessing the dashboard.
      </div>
    </div>
  </body>
  </html>";
  exit();
}

// Fetch resident profile info
$stmt = $conn->prepare("SELECT name, house_no, phone, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $house_no, $phone, $profile_pic);
$stmt->fetch();
$stmt->close();

$result = $conn->query("SELECT * FROM gate_passes WHERE user_id = $user_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Dashboard - GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">Welcome to Your Dashboard</h2>
      <a href="logout.php" class="btn btn-outline-danger">Logout</a>
    </div>

    <!-- Resident Profile Info -->
    <div class="container mb-4" style="max-width: 500px;">
      <div class="card shadow-sm border-0">
        <div class="row g-0 align-items-center">
          <div class="col-auto p-3">
          </div>
          <div class="col">
            <div class="card-body">
              <h5 class="card-title mb-1"><?php echo htmlspecialchars($name); ?></h5>
              <p class="mb-1"><strong>House No:</strong> <?php echo htmlspecialchars($house_no); ?></p>
              <p class="mb-0"><strong>Phone Number:</strong> <?php echo htmlspecialchars($phone); ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- End Resident Profile Info -->

    <a href="create_pass.php" class="btn btn-success mb-3">Create New Pass</a>
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
            <?php if ($row['status'] == 'pending'): ?>
              <a href="edit_pass.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
              <a href="cancel_pass.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger">Cancel</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
