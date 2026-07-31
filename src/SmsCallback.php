<?php
/**
 * clayon/src/SmsCallback.php
 * 
 * Webhook handler for TalkSasa delivery reports.
 */

require_once __DIR__ . '/../db.php';

header("Content-Type: application/json");

$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

if (!$data) {
    http_response_code(400);
    exit;
}

$providerMsgId = $data['message_id'] ?? ($data['uid'] ?? null);
$status = $data['status'] ?? 'unknown';

if (!$providerMsgId) {
    exit;
}

$db = getClayonDb();

try {
    // 1. Find the request associated with this message ID
    $stmt = $db->prepare("SELECT sms_request_id FROM provider_sms_logs WHERE provider_message_id = ?");
    $stmt->execute([$providerMsgId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($log) {
        $requestId = $log['sms_request_id'];

        // 2. Insert into delivery_reports
        $stmt = $db->prepare("INSERT INTO delivery_reports (sms_request_id, provider_message_id, status, delivered_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$requestId, $providerMsgId, $status]);

        // 3. Update request status if it's a final state
        if (in_array(strtolower($status), ['delivered', 'failed', 'rejected'])) {
            $stmt = $db->prepare("UPDATE sms_requests SET status = 'completed' WHERE id = ?");
            $stmt->execute([$requestId]);
        }
    }

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log("SMS Callback Error: " . $e->getMessage());
    http_response_code(500);
}
