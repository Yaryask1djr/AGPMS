<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$redirect_url = ($_SESSION['role'] === 'resident') ? 'resident_settings.php' : 'admin_dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload error occurred. Code: " . $file['error'];
        header("Location: {$redirect_url}?error=" . urlencode($error));
        exit();
    }

    // Limit size to 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        $error = "File is too large. Maximum allowed size is 5MB.";
        header("Location: {$redirect_url}?error=" . urlencode($error));
        exit();
    }

    // Verify MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mime, $allowed_mimes)) {
        $error = "Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.";
        header("Location: {$redirect_url}?error=" . urlencode($error));
        exit();
    }

    $ext = $allowed_mimes[$mime];
    $filename = 'profile_' . $user_id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target_dir = __DIR__ . '/uploads';

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $target_file = $target_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Fetch and remove old profile picture file if exists
        $stmt_old = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $stmt_old->bind_param("i", $user_id);
        $stmt_old->execute();
        $stmt_old->bind_result($old_pic);
        if ($stmt_old->fetch() && !empty($old_pic)) {
            $old_path = $target_dir . '/' . basename($old_pic);
            if (file_exists($old_path) && is_file($old_path)) {
                @unlink($old_path);
            }
        }
        $stmt_old->close();

        // Store clean filename in database
        $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
        $stmt->bind_param("si", $filename, $user_id);
        $stmt->execute();
        $stmt->close();

        header("Location: {$redirect_url}?success=" . urlencode("Profile picture updated successfully!"));
        exit();
    } else {
        $error = "Failed to save uploaded file. Please check folder permissions.";
        header("Location: {$redirect_url}?error=" . urlencode($error));
        exit();
    }
}

header("Location: {$redirect_url}");
exit();