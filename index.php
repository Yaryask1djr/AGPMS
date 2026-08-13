<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IKHE-GPMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body {
        background: url('T-IKHEDA.jpg') no-repeat center center fixed;
        background-size: cover;
        position: relative;
    }
    body::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(135deg, rgba(30,30,40,0.9) 0%, rgba(15,15,20,0.8) 100%);
        z-index: -1;
    }
    .landing-wrapper {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    .navbar-glass {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .hero-content {
        color: white;
        text-align: center;
        margin-top: 5rem;
    }
    .login-hub {
        max-width: 450px;
        margin: 3rem auto;
    }
    .feature-grid {
        color: rgba(255,255,255,0.8);
        margin-top: 4rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        text-align: left;
    }
    .feature-item i {
        font-size: 2rem;
        color: #a855f7;
        margin-bottom: 1rem;
    }
  </style>
</head>
<body>

  <div class="landing-wrapper">
    <nav class="navbar navbar-expand-lg navbar-dark navbar-glass py-3">
      <div class="container">
        <a class="navbar-brand brand-font fs-3 text-white fw-bold" href="#">
          <i class="bi bi-shield-check text-primary"></i> IKHE
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
          <ul class="navbar-nav align-items-center gap-3">
            <li class="nav-item">
              <a class="nav-link text-white fw-medium" href="register.php">Register</a>
            </li>
            <li class="nav-item">
              <a class="btn btn-outline-light px-4 rounded-pill" href="#login-hub">Login Hub</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="container flex-grow-1">
      <div class="hero-content">
        <h1 class="display-4 brand-font mb-3">Welcome to IKHE</h1>
        <p class="lead mb-4 text-light">The automated, highly-secure Gate Pass Management System for Ibrahim Kunya Housing Estate.</p>
        
        <div id="login-hub" class="glass-card login-hub p-5 hover-lift">
            <h3 class="mb-4 text-center brand-font text-white">Select Your Portal</h3>
            <div class="d-grid gap-3">
                <a href="login_resident.php" class="btn btn-light btn-lg text-start rounded-pill position-relative fw-bold shadow-sm">
                    <i class="bi bi-house-door-fill me-2 text-primary"></i> Resident Portal
                    <i class="bi bi-chevron-right position-absolute end-0 top-50 translate-middle-y me-3"></i>
                </a>
                <a href="login_security.php" class="btn btn-outline-light btn-lg text-start rounded-pill position-relative fw-bold">
                    <i class="bi bi-shield-lock-fill me-2 text-warning"></i> Security Post
                    <i class="bi bi-chevron-right position-absolute end-0 top-50 translate-middle-y me-3"></i>
                </a>
                <a href="login_admin.php" class="btn btn-outline-light btn-lg text-start rounded-pill position-relative fw-bold">
                    <i class="bi bi-person-badge-fill me-2 text-danger"></i> Admin Control
                    <i class="bi bi-chevron-right position-absolute end-0 top-50 translate-middle-y me-3"></i>
                </a>
            </div>
            <hr class="border-secondary my-4">
            <div class="text-center text-white-50 small">
                Don't have an account? <a href="register.php" class="text-info text-decoration-none fw-bold">Register as a Resident</a>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="feature-grid container pb-5">
            <div class="feature-item glass-card p-4 hover-lift">
                <i class="bi bi-qr-code-scan"></i>
                <h5 class="text-white brand-font">Instant Scanning</h5>
                <p class="small">Security guards can scan digital passes instantly from their devices, eliminating paperwork.</p>
            </div>
            <div class="feature-item glass-card p-4 hover-lift">
                <i class="bi bi-shield-check"></i>
                <h5 class="text-white brand-font">Secure Watchlist</h5>
                <p class="small">Automatic blocking of blacklisted visitors before they can even request entry.</p>
            </div>
            <div class="feature-item glass-card p-4 hover-lift">
                <i class="bi bi-phone-vibrate"></i>
                <h5 class="text-white brand-font">Digital Delivery</h5>
                <p class="small">Easily share passes via WhatsApp or download them as high-quality PDFs.</p>
            </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
