<?php
/**
 * clayon/callback/dlr.php
 * 
 * Delivery Receipt (DLR) Callback handler for TalkSasa.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../src/Logger.php';

date_default_timezone_set('Africa/Nairobi');

$db = getClayonDb();

// Tell TalkSasa we are responding in JSON
header("Content-Type: application/json");

// 1. Capture payload (JSON, POST form-data, or GET params)
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!$payload) {
    if (!empty($_POST)) {
        $payload = $_POST;
        $raw = json_encode($_POST);
    } elseif (!empty($_GET)) {
        $payload = $_GET;
        $raw = json_encode($_GET);
    }
}

if (!$payload) {
    Logger::error("DLR Callback: Invalid/Empty payload received. Raw: " . $raw);
    http_response_code(400);
    exit;
}

Logger::info("DLR Callback Received: " . $raw);

// 2. Identify the message ID
// TalkSasa uses queue_uid or message_id
$providerMsgId = $payload['queue_uid'] ?? ($payload['message_id'] ?? ($payload['id'] ?? null));

if (!$providerMsgId) {
    Logger::error("DLR Callback: No provider message ID found in payload.");
    http_response_code(422);
    exit;
}

// 3. Extract status
$status = $payload['status'] ?? 'unknown';
$deliveredAt = null;
$failedAt = null;
$reason = $payload['message'] ?? ($payload['error'] ?? null);

// Normalize status
$normalizedStatus = strtolower(trim($status));
if (in_array($normalizedStatus, ['delivered', 'delivrd', 'success', 'true', '1', 'sent'])) {
    $normalizedStatus = 'delivered';
    $deliveredAt = date('Y-m-d H:i:s');
} elseif (in_array($normalizedStatus, ['failed', 'undelivered', 'undeliv', 'rejected', 'false', '0'])) {
    $normalizedStatus = 'failed';
    $failedAt = date('Y-m-d H:i:s');
}

try {
    $db->beginTransaction();

    // Find the request
    $stmt = $db->prepare("SELECT id, status FROM sms_requests WHERE provider_message_id = ?");
    $stmt->execute([$providerMsgId]);
    $request = $stmt->fetch();

    if ($request) {
        $smsRequestId = $request['id'];

        // Update sms_requests
        $stmt = $db->prepare("
            UPDATE sms_requests 
            SET delivery_status = ?, 
                delivered_at = ?, 
                status = CASE WHEN ? = 'delivered' THEN 'completed' ELSE status END
            WHERE id = ?
        ");
        $stmt->execute([$normalizedStatus, $deliveredAt ?: $failedAt, $normalizedStatus, $smsRequestId]);

        // Insert into delivery_reports
        $stmt = $db->prepare("
            INSERT INTO delivery_reports (sms_request_id, provider_message_id, status, delivered_at, failed_at, failure_reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$smsRequestId, $providerMsgId, $normalizedStatus, $deliveredAt, $failedAt, $reason]);
        
        Logger::info("DLR Processed: ReqID #$smsRequestId | Status: $normalizedStatus | ProviderID: $providerMsgId");
    } else {
        Logger::warning("DLR Callback: Provider ID $providerMsgId not found in database.");
    }

    $db->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    Logger::error("DLR Callback Error: " . $e->getMessage());
    http_response_code(500);
}
