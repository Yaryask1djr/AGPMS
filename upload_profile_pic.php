<?php
// filepath: c:\xampp\htdocs\NewAGPMS\upload_profile_pic.php
session_start();
include 'includes/db.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] === 0 && in_array($ext, $allowed)) {
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
        $target = 'uploads/' . $filename;

        // Create uploads directory if not exists
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $target)) {
            // Update the user's profile_pic in the database
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->bind_param("si", $target, $user_id);
            $stmt->execute();
            $stmt->close();
            header("Location: resident_dashboard.php");
            exit();
        } else {
            $error = "Failed to upload file.";
        }
    } else {
        $error = "Invalid file type or upload error.";
    }
} else {
    $error = "No file uploaded.";
}

if (isset($error)) {
    header("Location: resident_dashboard.php?error=" . urlencode($error));
    exit();
}
?>
<div class="col-auto p-3">
  <img src="<?php echo $profile_pic ? htmlspecialchars($profile_pic) : 'default_avatar.png'; ?>" alt="Profile Picture" class="rounded-circle" width="80" height="80">
  <?php if (empty($profile_pic)): ?>
    <!-- show form -->
  <?php endif; ?>
</div>