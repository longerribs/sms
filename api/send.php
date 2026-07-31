<?php
/**
 * clayon/api/send.php
 * 
 * Send SMS endpoint
 * POST /clayon/api/send
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    // Verify API key
    $client = Auth::verifyApiKey();
    $clientId = $client['id'];

    // Parse request body
    $rawBody = trim((string) file_get_contents('php://input'));
    $data = [];

    if ($rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            Response::validation(['body' => 'Request body must be valid JSON. Example: {"recipient":"+254711486334","message":"Hello"}']);
        }
        $data = $decoded;
    } elseif (!empty($_POST)) {
        $data = $_POST;
    }

    // Validate SMS request
    if (!Validator::validateSmsRequest($data)) {
        Response::validation(Validator::getErrors());
    }

    // Extract fields
    $recipient = trim((string)($data['recipient'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));
    $senderId = trim((string)($data['sender_id'] ?? ''));
    if ($senderId === '') {
        $senderId = 'TALKSASA';
    }
    $senderId = preg_replace('/[^A-Za-z0-9_-]/', '', $senderId);
    if ($senderId === '') {
        $senderId = 'TALKSASA';
    }
    $senderId = strtoupper($senderId);
    $idempotencyKey = $data['idempotency_key'] ?? null;

    // Use TALKSASA as the default sender for all public sends.

    // Create SMS request
    $smsService = new SMSService();
    $requestData = $smsService->createRequest($clientId, $recipient, $message, $senderId);

    if (!$requestData) {
        Response::serverError('Failed to create SMS request');
    }

    // Try to send immediately with 5-second timeout
    $queueService = new QueueService();
    $result = $queueService->sendCriticalSMS($requestData['id'], $clientId);

    if ($result['success']) {
        $statusCode = 202; // Always 202 (Accepted) - units not debited yet
        Response::success([
            'request_id' => $requestData['id'],
            'reference' => $requestData['reference'],
            'recipient' => $recipient,
            'segments' => $requestData['segments'],
            'estimated_cost' => $requestData['estimated_cost'],
            'sms_status' => 'pending_provider_confirmation',
            'billing_status' => 'reserved_not_debited',
            'info' => 'Message queued. Units will be debited only when provider confirms delivery.'
        ], 'SMS queued for delivery', $statusCode);
    } else {
        Response::serverError($result['error']);
    }

} catch (Exception $e) {
    error_log("Send SMS error: " . $e->getMessage());
    Response::serverError('Failed to send SMS: ' . $e->getMessage());
}
