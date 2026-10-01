<?php
// api/v1/passes.php - Gate pass CRUD API endpoint
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/functions.php';

$current_user = authenticate_api_user($conn);
$method = $_SERVER['REQUEST_METHOD'];

// GET: Fetch passes
if ($method === 'GET') {
    $user_id = $current_user['id'];
    $role = $current_user['role'];
    $status = trim($_GET['status'] ?? '');
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));

    if ($role === 'resident') {
        if (!empty($status)) {
            $stmt = $conn->prepare("SELECT * FROM gate_passes WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->bind_param("isi", $user_id, $status, $limit);
        } else {
            $stmt = $conn->prepare("SELECT * FROM gate_passes WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->bind_param("ii", $user_id, $limit);
        }
    } else {
        // Admin or Security
        if (!empty($status)) {
            $stmt = $conn->prepare("SELECT gp.*, u.name as resident_name, u.house_no FROM gate_passes gp JOIN users u ON gp.user_id = u.id WHERE gp.status = ? ORDER BY gp.created_at DESC LIMIT ?");
            $stmt->bind_param("si", $status, $limit);
        } else {
            $stmt = $conn->prepare("SELECT gp.*, u.name as resident_name, u.house_no FROM gate_passes gp JOIN users u ON gp.user_id = u.id ORDER BY gp.created_at DESC LIMIT ?");
            $stmt->bind_param("i", $limit);
        }
    }

    $stmt->execute();
    $passes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    api_response(true, ['passes' => $passes, 'count' => count($passes)], 'Passes retrieved successfully.');
}

// POST: Create pass
if ($method === 'POST') {
    if ($current_user['role'] !== 'resident') {
        api_response(false, null, 'Only residents can generate gate passes.', 403);
    }

    if ($current_user['dues_status'] === 'defaulter') {
        api_response(false, null, 'Access restricted: Unpaid estate dues prevent generating new passes.', 403);
    }

    $input = get_api_input();
    $visitor_name = sanitize($input['visitor_name'] ?? '');
    $contact = sanitize($input['contact'] ?? '');
    $purpose = sanitize($input['purpose'] ?? '');
    $pass_type = in_array($input['pass_type'] ?? '', ['one_time', 'recurring', 'delivery']) ? $input['pass_type'] : 'one_time';
    $vehicle = sanitize($input['vehicle_details'] ?? '');
    $entry_time = $input['entry_time'] ?? '';
    $exit_time = $input['exit_time'] ?? '';

    if (empty($visitor_name) || empty($contact) || empty($purpose)) {
        api_response(false, null, 'Visitor name, contact, and purpose are required fields.', 400);
    }

    // Check blacklist
    $bl = $conn->prepare("SELECT id FROM blacklist WHERE phone = ? LIMIT 1");
    $bl->bind_param("s", $contact);
    $bl->execute();
    if ($bl->get_result()->num_rows > 0) {
        $bl->close();
        api_response(false, null, 'Security Block: This phone number is on the estate watchlist.', 403);
    }
    $bl->close();

    // Auto calculate delivery and recurring dates
    if ($pass_type === 'delivery') {
        $entry_time = !empty($entry_time) ? $entry_time : date('Y-m-d H:i:s');
        $exit_time = date('Y-m-d H:i:s', strtotime($entry_time . ' +2 hours'));
    } elseif ($pass_type === 'recurring') {
        $entry_time = date('Y-m-d H:i:s');
        $exit_time = '2099-12-31 23:59:59';
    } else {
        if (empty($entry_time) || empty($exit_time)) {
            api_response(false, null, 'Entry and exit times are required for one-time passes.', 400);
        }
    }

    $token = bin2hex(random_bytes(8));
    $user_id = $current_user['id'];

    $insert = $conn->prepare("INSERT INTO gate_passes (user_id, visitor_name, contact, purpose, pass_type, vehicle_details, entry_time, exit_time, token, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $insert->bind_param("issssssss", $user_id, $visitor_name, $contact, $purpose, $pass_type, $vehicle, $entry_time, $exit_time, $token);

    if ($insert->execute()) {
        $pass_id = $insert->insert_id;
        $insert->close();

        api_response(true, [
            'id'           => $pass_id,
            'token'        => $token,
            'visitor_name' => $visitor_name,
            'pass_type'    => $pass_type,
            'entry_time'   => $entry_time,
            'exit_time'    => $exit_time
        ], 'Gate pass created successfully.', 201);
    } else {
        $error = $insert->error;
        $insert->close();
        api_response(false, null, 'Database error: ' . $error, 500);
    }
}

// DELETE: Cancel pass
if ($method === 'DELETE') {
    $input = get_api_input();
    $pass_id = intval($input['id'] ?? ($_GET['id'] ?? 0));
    $user_id = $current_user['id'];

    if ($pass_id <= 0) {
        api_response(false, null, 'Invalid pass ID.', 400);
    }

    $stmt = $conn->prepare("UPDATE gate_passes SET status='revoked' WHERE id = ? AND user_id = ? AND (status = 'pending' OR (status = 'active' AND pass_type = 'recurring'))");
    $stmt->bind_param("ii", $pass_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        api_response(true, null, 'Gate pass cancelled successfully.');
    } else {
        api_response(false, null, 'Pass could not be cancelled. Only pending or active recurring passes can be cancelled.', 400);
    }
}

api_response(false, null, 'Method not allowed.', 405);
