<?php
/**
 * clayon/api/history.php
 * 
 * Get SMS history endpoint
 * GET /clayon/api/history?limit=50&offset=0
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    // Verify API key
    $client = Auth::verifyApiKey();
    $clientId = $client['id'];

    // Get parameters
    $limit = min(intval($_GET['limit'] ?? 50), 500); // Max 500
    $offset = intval($_GET['offset'] ?? 0);

    // Get SMS history
    $smsService = new SMSService();
    $history = $smsService->getHistory($clientId, $limit, $offset);

    // Format response
    $records = array_map(function($record) {
        return [
            'id' => intval($record['id']),
            'reference' => $record['request_reference'],
            'recipient' => $record['recipient'],
            'message' => $record['message'],
            'sender_id' => $record['sender_id'],
            'segments' => intval($record['estimated_segments']),
            'estimated_cost' => floatval($record['estimated_cost']),
            'final_cost' => floatval($record['final_cost'] ?: $record['estimated_cost']),
            'status' => $record['status'],
            'created_at' => $record['created_at']
        ];
    }, $history);

    Response::success([
        'limit' => $limit,
        'offset' => $offset,
        'total_in_response' => count($records),
        'records' => $records
    ], 'SMS history retrieved');

} catch (Exception $e) {
    error_log("History endpoint error: " . $e->getMessage());
    Response::serverError('Failed to retrieve history');
}
