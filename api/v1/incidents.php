<?php
// api/v1/incidents.php - Incident reporting & management API
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/functions.php';

$current_user = authenticate_api_user($conn, ['security', 'admin']);
$method = $_SERVER['REQUEST_METHOD'];

// GET: List incidents
if ($method === 'GET') {
    $status = trim($_GET['status'] ?? '');
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));

    if (!empty($status)) {
        $stmt = $conn->prepare("SELECT ir.*, u.name as guard_name, u.phone as guard_phone 
            FROM incident_reports ir 
            JOIN users u ON ir.security_id = u.id 
            WHERE ir.status = ? 
            ORDER BY ir.created_at DESC LIMIT ?");
        $stmt->bind_param("si", $status, $limit);
    } else {
        $stmt = $conn->prepare("SELECT ir.*, u.name as guard_name, u.phone as guard_phone 
            FROM incident_reports ir 
            JOIN users u ON ir.security_id = u.id 
            ORDER BY ir.created_at DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
    }
    $stmt->execute();
    $incidents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    api_response(true, ['incidents' => $incidents, 'count' => count($incidents)], 'Incident reports retrieved.');
}

// POST: File an incident report
if ($method === 'POST' && ($_GET['action'] ?? '') !== 'resolve') {
    $input = get_api_input();
    $title = sanitize($input['title'] ?? '');
    $category = in_array($input['category'] ?? '', ['trespassing', 'gate_damage', 'suspicious_activity', 'argument', 'other']) ? $input['category'] : 'other';
    $severity = in_array($input['severity'] ?? '', ['low', 'medium', 'high', 'critical']) ? $input['severity'] : 'medium';
    $description = sanitize($input['description'] ?? '');
    $sec_id = $current_user['id'];

    if (empty($title) || empty($description)) {
        api_response(false, null, 'Incident title and description are required.', 400);
    }

    $stmt = $conn->prepare("INSERT INTO incident_reports (security_id, title, category, description, severity, status) VALUES (?, ?, ?, ?, ?, 'reported')");
    $stmt->bind_param("issss", $sec_id, $title, $category, $description, $severity);

    if ($stmt->execute()) {
        $incident_id = $stmt->insert_id;
        $stmt->close();

        // Notify admins if high or critical
        if ($severity === 'high' || $severity === 'critical') {
            $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'");
            while ($adm = $admins->fetch_assoc()) {
                create_notification($conn, (int)$adm['id'], "Urgent Incident at Gate: {$title}", "Severity: " . strtoupper($severity) . " reported by {$current_user['name']}.");
            }
        }

        api_response(true, [
            'id'       => $incident_id,
            'title'    => $title,
            'category' => $category,
            'severity' => $severity,
            'status'   => 'reported'
        ], 'Incident report filed successfully.', 201);
    } else {
        $error = $stmt->error;
        $stmt->close();
        api_response(false, null, 'Failed to file incident report: ' . $error, 500);
    }
}

// PUT / POST update: Resolve incident
if ($method === 'PUT' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'resolve')) {
    if ($current_user['role'] !== 'admin') {
        api_response(false, null, 'Only administrators can resolve incident reports.', 403);
    }

    $input = get_api_input();
    $incident_id = intval($input['id'] ?? ($_GET['id'] ?? 0));
    $status = in_array($input['status'] ?? '', ['reported', 'investigating', 'resolved']) ? $input['status'] : 'resolved';
    $now = date('Y-m-d H:i:s');
    $adm_id = $current_user['id'];

    if ($incident_id <= 0) {
        api_response(false, null, 'Valid incident ID is required.', 400);
    }

    $up = $conn->prepare("UPDATE incident_reports SET status = ?, resolved_by = ?, resolved_at = ? WHERE id = ?");
    $up->bind_param("sisi", $status, $adm_id, $now, $incident_id);
    $up->execute();
    $up->close();

    api_response(true, ['id' => $incident_id, 'status' => $status], 'Incident report updated.');
}

api_response(false, null, 'Method not allowed.', 405);
