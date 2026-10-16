<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax',
        'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
function current_session_user() {
    global $conn;
    if (!isset($_SESSION['user_id'], $_SESSION['auth_version'])) return null;
    $id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT id, name, phone, role, status, approved, dues_status, auth_version FROM users WHERE id = ?');
    $stmt->bind_param('i', $id); $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$user || $user['status'] !== 'active' || (int)$user['approved'] !== 1
        || (int)$user['auth_version'] !== (int)$_SESSION['auth_version']) {
        $_SESSION = []; return null;
    }
    $_SESSION['role'] = $user['role'];
    return $user;
}
function require_role($roles) {
    $user = current_session_user();
    if (!$user) { http_response_code(401); exit('Please log in again.'); }
    if (!in_array($user['role'], (array)$roles, true)) { http_response_code(403); exit('Access denied.'); }
}
function is_logged_in() { return current_session_user() !== null; }
function logout() {
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires'=>time()-3600, 'path'=>$p['path'],
        'secure'=>$p['secure'], 'httponly'=>true, 'samesite'=>'Lax']);
    session_destroy(); header('Location: index.php'); exit;
}
// Every form handler using this bootstrap gets the same mutation protection.
if (!defined('AGPMS_API_BOOTSTRAP') && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') require_csrf();
