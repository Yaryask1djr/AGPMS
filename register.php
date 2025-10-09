<?php include 'includes/db.php'; ?>
<?php include 'includes/functions.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration - AGPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
  <div class="container mt-5">
    <h2 class="text-center mb-4">Registration</h2>

    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      $name = trim($_POST['name']);
      $house_no = trim($_POST['house_no']);
      $phone = trim($_POST['phone']);
      $role = trim($_POST['role']);
      $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

      // Check if phone already exists
      $check_sql = "SELECT id FROM users WHERE phone = ?";
      $check_stmt = $conn->prepare($check_sql);
      $check_stmt->bind_param("s", $phone);
      $check_stmt->execute();
      $check_stmt->store_result();

      if ($check_stmt->num_rows > 0) {
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
    }
    ?>

    <form method="POST" class="border p-4 rounded bg-light">
      <div class="mb-3">
        <label for="name" class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="house_no" class="form-label">House Number</label>
        <input type="text" name="house_no" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="phone" class="form-label">Phone Number</label>
        <input type="text" name="phone" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="role" class="form-label">Register As</label>
        <select name="role" class="form-select" required>
          <option value="">Select Role</option>
          <option value="resident">Resident</option>
          <option value="security">Security</option>
        </select>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Register</button>
    </form>
  </div>
</body>
</html>
