<?php
/**
 * clayon/api/sender-ids.php
 * 
 * Manage sender IDs
 * GET /clayon/api/sender-ids - list
 * POST /clayon/api/sender-ids - create request
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    // Verify API key
    $client = Auth::verifyApiKey();
    $clientId = $client['id'];

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // List approved sender IDs
        $senderIdService = new SenderIdService();
        $senderIds = $senderIdService->getApprovedSenderIds($clientId);

        $data = array_map(function($s) {
            return [
                'sender_id' => $s['sender_id'],
                'status' => $s['status'],
                'approval_status' => $s['approval_status']
            ];
        }, $senderIds);

        Response::success([
            'sender_ids' => $data,
            'count' => count($data)
        ], 'Sender IDs retrieved');

    } elseif ($method === 'POST') {
        // Request new sender ID
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['sender_id'])) {
            Response::validation(['sender_id' => 'Sender ID is required']);
        }

        if (!Validator::isValidSenderId($data['sender_id'])) {
            Response::validation(['sender_id' => 'Invalid sender ID format (2-20 alphanumeric characters)']);
        }

        $senderIdService = new SenderIdService();
        $result = $senderIdService->requestSenderId($clientId, $data['sender_id']);

        if (isset($result['error'])) {
            Response::error($result['error'], 400);
        }

        Response::success([
            'message' => 'Sender ID request submitted',
            'sender_id' => $data['sender_id'],
            'status' => 'pending'
        ], 'Request submitted for approval', 201);

    } else {
        http_response_code(405);
        Response::error('Method not allowed');
    }

} catch (Exception $e) {
    error_log("Sender ID endpoint error: " . $e->getMessage());
    Response::serverError('Failed to process request');
}
