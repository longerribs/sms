<?php
/**
 * clayon/api/balance.php
 * 
 * Get wallet balance endpoint
 * GET /clayon/api/balance
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    // Verify API key
    $client = Auth::verifyApiKey();
    $clientId = $client['id'];

    // Get wallet balance
    $walletService = new WalletService();
    $wallet = $walletService->getBalance($clientId);

    if (!$wallet) {
        Response::serverError('Failed to retrieve wallet');
    }

    $available = $wallet['balance_units'] - $wallet['reserved_units'];

    Response::success([
        'client_id' => $clientId,
        'client_name' => $client['name'],
        'balance_units' => floatval($wallet['balance_units']),
        'reserved_units' => floatval($wallet['reserved_units']),
        'available_units' => floatval($available),
        'currency' => 'KES',
        'updated_at' => date('Y-m-d H:i:s')
    ], 'Wallet balance retrieved');

} catch (Exception $e) {
    error_log("Balance endpoint error: " . $e->getMessage());
    Response::serverError('Failed to retrieve balance');
}
