<?php
/**
 * clayon/api/v1/balance.php
 * 
 * API endpoint to check client wallet balance.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../src/Auth.php';

header("Content-Type: application/json");

// 1. Authenticate via session bridge
session_start();
if (empty($_SESSION['CLAYON_CLIENT_ID'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

// 2. Fetch Balance
$stmt = $db->prepare("SELECT balance_units, updated_at FROM wallet_accounts WHERE client_id = ?");
$stmt->execute([$clientId]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet) {
    echo json_encode([
        'status' => 'success',
        'balance' => 0,
        'currency' => 'units'
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'balance' => (float)$wallet['balance_units'],
        'updated_at' => $wallet['updated_at'],
        'currency' => 'units'
    ]);
}
