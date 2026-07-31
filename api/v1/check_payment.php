<?php
/**
 * clayon/api/v1/check_payment.php
 * 
 * Polls the database to check if a specific M-Pesa transaction has been completed.
 */

session_start();
require_once __DIR__ . '/../../db.php';

header("Content-Type: application/json");

if (!isset($_SESSION['CLAYON_CLIENT_ID'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$checkoutRequestId = $_GET['checkout_id'] ?? '';

if (!$checkoutRequestId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Checkout ID']);
    exit;
}

$db = getClayonDb();

$stmt = $db->prepare("SELECT status FROM mpesa_transactions WHERE checkout_request_id = ? AND client_id = ?");
$stmt->execute([$checkoutRequestId, $_SESSION['CLAYON_CLIENT_ID']]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'payment_status' => $transaction['status'] // 'pending', 'completed', 'failed', 'cancelled'
]);
