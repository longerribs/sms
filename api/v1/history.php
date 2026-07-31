<?php
/**
 * clayon/api/v1/history.php
 * 
 * API endpoint to fetch SMS send history for a client.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../src/Auth.php';

header("Content-Type: application/json");

// 1. Authenticate
$plainKey = Auth::getBearerToken();
$db = getClayonDb();
$auth = new Auth($db);
$clientId = $auth->validate($plainKey);

if (!$clientId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 2. Fetch History
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$stmt = $db->prepare("
    SELECT id, request_reference, recipient, sender_id, estimated_segments, 
           COALESCE(NULLIF(delivery_status, 'pending'), status) AS status, 
           delivery_status, created_at 
    FROM sms_requests 
    WHERE client_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$clientId, $limit, $offset]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => $history,
    'pagination' => [
        'limit' => $limit,
        'offset' => $offset,
        'count' => count($history)
    ]
]);
