<?php
/**
 * clayon/api/v1/send_sms.php
 * 
 * Main API endpoint for clients to send SMS.
 * Includes phone normalization and basic security sanitization.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/QueueService.php';
require_once __DIR__ . '/../../src/SMSService.php';

header("Content-Type: application/json");

// 1. Authenticate via session bridge
session_start();
if (empty($_SESSION['CLAYON_CLIENT_ID'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: No active session']);
    exit;
}
$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

// 2. Parse & Sanitize Input
$rawBody = trim((string) file_get_contents('php://input'));
$input = [];

if ($rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Request body must be valid JSON. Example: {"recipient":"+254711486334","message":"Hello"}']);
        exit;
    }
    $input = $decoded;
} elseif (!empty($_POST)) {
    $input = $_POST;
}

$smsService = new SMSService($db);

// Normalize Recipient
$rawRecipient = $input['recipient'] ?? '';
$recipient = $smsService->formatPhone($rawRecipient);

// Basic Sanitization for Security
$message = trim($input['message'] ?? '');
$message = strip_tags($message); // Prevent basic HTML injection in logs/display

$senderId = trim((string)($input['sender_id'] ?? ''));
if ($senderId === '') {
    $senderId = 'TALKSASA';
}
$senderId = preg_replace('/[^A-Za-z0-9_-]/', '', $senderId); // Sanitize sender_id format
if ($senderId === '') {
    $senderId = 'TALKSASA';
}
$senderId = strtoupper($senderId);

$requestRef = $input['reference'] ?? uniqid('req_', true);
$requestRef = preg_replace('/[^A-Za-z0-9_-]/', '', $requestRef);

if (!$recipient || !$message) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid fields: recipient, message']);
    exit;
}

// Validate recipient length (International: 12 digits)
if (strlen($recipient) < 10 || strlen($recipient) > 15) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format. Use international format (e.g. 254712345678)']);
    exit;
}

// 3. Validation & Billing
$segments = $smsService->calculateSegments($message);

// Keep the public send flow on TALKSASA for a smoother third-party experience.

// Check Balance
$stmt = $db->prepare("SELECT balance_units FROM wallet_accounts WHERE client_id = ?");
$stmt->execute([$clientId]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet || $wallet['balance_units'] < $segments) {
    http_response_code(402);
    echo json_encode(['status' => 'error', 'message' => 'Insufficient units. Required: ' . $segments]);
    exit;
}

try {
    $db->beginTransaction();

    // Create request record (status: pending, NO DEBIT YET)
    $stmt = $db->prepare("INSERT INTO sms_requests (client_id, request_reference, recipient, message, sender_id, estimated_segments, estimated_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$clientId, $requestRef, $recipient, $message, $senderId, $segments, $segments]);
    $requestId = $db->lastInsertId();

    // DEBIT DEFERRED: Balance check only, debit happens after provider confirms in Worker
    // Log reserved units to ledger for transparency (optional)
    $stmt = $db->prepare("INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note) VALUES (?, 'reserved', ?, ?, ?)");
    $stmt->execute([$clientId, $segments, $requestRef, "SMS Send Request Reserved - $recipient (pending provider confirmation)"]);

    $db->commit();

    // 4. Send Strategy (Fast Path vs Queue)
    $queueService = new QueueService($db);
    $result = $queueService->sendCriticalSMS($requestId, $clientId);
    $isSentSync = isset($result['queued']) && $result['queued'] === false;

    echo json_encode([
        'status' => 'success',
        'message' => $isSentSync ? 'Message sent successfully.' : 'Message queued for delivery.',
        'request_id' => $requestId,
        'reference' => $requestRef,
        'recipient' => $recipient,
        'segments' => $segments,
        'estimated_cost' => $segments,
        'sms_status' => $isSentSync ? 'accepted' : 'pending',
        'billing_status' => $isSentSync ? 'debited' : 'reserved_not_debited',
        'info' => $isSentSync ? 'Units debited after provider confirmation.' : 'Units held in reserve. Debit happens when provider confirms delivery.'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error']);
}
