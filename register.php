<?php include 'includes/db.php'; ?>
<?php include 'includes/functions.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration - AGPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card modern-card">
      <h2 class="text-center">Registration</h2>

      <?php
      if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = sanitize($_POST['name']);
        $house_no = sanitize($_POST['house_no']);
        $phone = sanitize($_POST['phone']);
        $role = 'resident'; // Default to resident, admin creates security
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $house_val = intval($house_no);

        // Check if phone already exists
        $check_sql = "SELECT id FROM users WHERE phone = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $phone);
        $check_stmt->execute();
        $check_stmt->store_result();

        // Check house number slot limits
        $slot_sql = "SELECT id FROM users WHERE house_no = ?";
        $slot_stmt = $conn->prepare($slot_sql);
        $slot_stmt->bind_param("s", $house_no);
        $slot_stmt->execute();
        $slot_stmt->store_result();

        if ($house_val < 1 || $house_val > 99) {
          echo '<div class="alert alert-danger">Invalid house number. Must be between 1 and 99.</div>';
        } elseif ($slot_stmt->num_rows >= 3) {
          echo '<div class="alert alert-danger">Registration failed: This house already has the maximum of 3 registered users.</div>';
        } elseif ($check_stmt->num_rows > 0) {
          echo '<div class="alert alert-danger">Phone number already registered.</div>';
        } else {
          // Set approved to 0 (not approved) for new registrations
          $sql = "INSERT INTO users (name, house_no, phone, password, role, approved, status) VALUES (?, ?, ?, ?, ?, 0, 'pending')";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("sssss", $name, $house_no, $phone, $password, $role);

          if ($stmt->execute()) {
            // Optionally, insert into a separate residents table if role is resident
            if ($role === 'resident') {
              $user_id = $stmt->insert_id;
              $res_stmt = $conn->prepare("INSERT INTO residents (user_id, name, house_no, phone) VALUES (?, ?, ?, ?)");
              $res_stmt->bind_param("isss", $user_id, $name, $house_no, $phone);
              $res_stmt->execute();
              $res_stmt->close();
            }
            echo '<div class="alert alert-success"><strong>Registration successful!</strong> Please wait for admin approval before logging in.</div>';
          } else {
            echo '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
          }
          $stmt->close();
        }
        $check_stmt->close();
        $slot_stmt->close();
      }
      ?>

      <form method="POST">
        <div class="mb-3">
          <label for="name" class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" placeholder="Enter your full name" required>
        </div>
        <div class="mb-3">
          <label for="house_no" class="form-label">House Number (1 - 99)</label>
          <input type="number" name="house_no" class="form-control" placeholder="e.g. 12" min="1" max="99" required>
        </div>
        <div class="mb-3">
          <label for="phone" class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" placeholder="Enter your phone number" required>
        </div>
        <div class="mb-4">
          <label for="password" class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="Create a password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Register</button>
        <div class="text-center mt-3">
          <a href="index.php" class="text-muted text-decoration-none small">Back to Home</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
