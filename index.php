<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IKHE-GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="d-flex flex-column min-vh-100">

  <header class="bg-primary text-white py-3">
    <div class="container d-flex justify-content-between align-items-center">
      <h1 class="h3 mb-0">Ibrahim Kunya Housing Estate</h1>
      <nav>
        <a href="register.php" class="text-white me-3">Register</a>
        <a href="login_security.php" class="text-white me-3">Security Login</a>
        <a href="login_resident.php" class="text-white me-3">Resident Login</a>
        <a href="login_admin.php" class="text-white">Admin Login</a>
      </nav>
    </div>
  </header>

  <main class="flex-grow-1 d-flex align-items-center justify-content-center bg-light">
    <div class="text-center">
      <div class="rounded-circle mx-auto mb-3 overflow-hidden" style="width: 200px; height: 200px; background: #eee;">
        <img src="T-IKHEDA.jpg" alt="IKHE Housing Estate" style="width: 100%; height: 100%; object-fit: cover;">
      </div>
      <h2>Welcome to IKHE-GPMS</h2>
      <p class="text-muted">Gate Pass Management System</p>
    </div>
  </main>

  <footer class="bg-primary text-white text-center py-3">
    &copy; <?php echo date('Y'); ?>  IKHE - GPMS. All rights reserved.
  </footer>

</body>
</html>
