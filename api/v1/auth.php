<?php
// api/v1/auth.php - Mobile & external authentication endpoint
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_response(false, null, 'Method not allowed. Use POST.', 405);
}

$input = get_api_input();
$phone = trim($input['phone'] ?? '');
$password = $input['password'] ?? '';

if (empty($phone) || empty($password)) {
    api_response(false, null, 'Phone and password are required.', 400);
}

$stmt = $conn->prepare("SELECT id, name, phone, password, role, status, approved, house_no, dues_status FROM users WHERE phone = ? LIMIT 1");
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    api_response(false, null, 'Invalid credentials. User not found.', 401);
}

$user = $result->fetch_assoc();
$stmt->close();

if ($user['status'] !== 'active' || (int)$user['approved'] !== 1) {
    api_response(false, null, 'This account has been revoked. Contact estate admin.', 403);
}

if ($user['role'] === 'resident' && !$user['approved']) {
    api_response(false, null, 'Account pending administrator approval.', 403);
}

if (!password_verify($password, $user['password'])) {
    api_response(false, null, 'Invalid credentials. Incorrect password.', 401);
}

// Generate secure API token (64 hex characters)
$api_token = bin2hex(random_bytes(32));
$digest = hash("sha256", $api_token);
$up = $conn->prepare("UPDATE users SET api_token=NULL, api_token_hash=?, api_token_expires_at=DATE_ADD(NOW(), INTERVAL 8 HOUR) WHERE id=?");
$up->bind_param("si", $digest, $user['id']);
$up->execute();
$up->close();

unset($user['password']);

api_response(true, [
    'token' => $api_token,
    'user'  => $user
], 'Authentication successful.');
