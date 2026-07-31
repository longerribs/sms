<?php
/**
 * clayon/api/ledger.php
 * 
 * Get wallet ledger/transaction history
 * GET /clayon/api/ledger?limit=50&offset=0
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    // Verify API key
    $client = Auth::verifyApiKey();
    $clientId = $client['id'];

    // Get parameters
    $limit = min(intval($_GET['limit'] ?? 50), 500); // Max 500
    $offset = intval($_GET['offset'] ?? 0);

    // Get ledger entries
    $walletService = new WalletService();
    $entries = $walletService->getLedger($clientId, $limit, $offset);

    // Format response
    $records = array_map(function($entry) {
        return [
            'id' => intval($entry['id']),
            'type' => $entry['entry_type'],
            'units' => floatval($entry['units']),
            'reference' => $entry['reference'],
            'note' => $entry['note'],
            'created_at' => $entry['created_at']
        ];
    }, $entries);

    Response::success([
        'limit' => $limit,
        'offset' => $offset,
        'total_in_response' => count($records),
        'records' => $records
    ], 'Ledger retrieved');

} catch (Exception $e) {
    error_log("Ledger endpoint error: " . $e->getMessage());
    Response::serverError('Failed to retrieve ledger');
}
