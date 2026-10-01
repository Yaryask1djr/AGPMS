<?php
/**
 * Sanitize user input for display
 */
function sanitize($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or retrieve the CSRF token for the current session
 */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF token input field
 */
function csrf_field() {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate submitted CSRF token
 */
function verify_csrf_token($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $submitted = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!is_string($submitted) || empty($_SESSION['csrf_token']) || empty($submitted)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Enforce CSRF protection or terminate request
 */
function require_csrf() {
    if (!verify_csrf_token()) {
        http_response_code(403);
        die("Invalid or expired security token (CSRF). Please go back, refresh the page, and try again.");
    }
}

/**
 * Audit log for admin actions
 */
function log_admin_activity($conn, $admin_id, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $admin_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}

/**
 * Push an in-app notification to a user
 */
function create_notification($conn, $user_id, $title, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $title, $message);
    $stmt->execute();
    $stmt->close();
}

function csv_safe($value) {
 $value=(string)$value; return preg_match('/^[=+@\-\t\r\n]/',$value) ? "'".$value : $value;
}
