<?php
/**
 * clayon/src/PaymentCallback.php
 * 
 * Handles M-Pesa STK Push callbacks for buying SMS units.
 * Now supports dynamic reseller pricing based on client plans.
 */

require_once __DIR__ . '/../db.php';

header("Content-Type: application/json");

$rawPayload = file_get_contents('php://input');
error_log("M-Pesa Callback Received: " . $rawPayload);
$callback = json_decode($rawPayload, true);

if (!$callback || !isset($callback['Body']['stkCallback'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$stkCallback = $callback['Body']['stkCallback'];
$resultCode = $stkCallback['ResultCode'];
$checkoutRequestID = $stkCallback['CheckoutRequestID'];

$db = getClayonDb();

// 1. Fetch transaction and client plan details
$stmt = $db->prepare("
    SELECT t.*, c.plan_id, p.provider_markup_type, p.markup_value 
    FROM mpesa_transactions t
    JOIN clients c ON t.client_id = c.id
    LEFT JOIN pricing_plans p ON c.plan_id = p.id
    WHERE t.checkout_request_id = ?
");
$stmt->execute([$checkoutRequestID]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction || $transaction['status'] !== 'pending') {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Already processed or not found']);
    exit;
}

if ($resultCode === 0) {
    // Success
    $metadata = [];
    if (isset($stkCallback['CallbackMetadata']['Item'])) {
        foreach ($stkCallback['CallbackMetadata']['Item'] as $item) {
            $metadata[$item['Name']] = $item['Value'] ?? null;
        }
    }

    $mpesaReceipt = $metadata['MpesaReceiptNumber'] ?? '';
    
    // Fetch base provider rate from settings
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'PROVIDER_UNITS_PER_KES'");
    $stmt->execute();
    $providerRate = (float)($stmt->fetchColumn() ?: 2.0); // Default 2.0 units per KES

    $amount = (float)$transaction['amount'];
    $markupValue = (float)($transaction['markup_value'] ?: 0);
    $markupType = $transaction['provider_markup_type'] ?: 'percentage';

    // Calculate Reseller Rate (Profit logic)
    // If markup is 25%, we give 25% fewer units than the provider rate
    // Client Rate = Provider Rate / (1 + Markup)
    if ($markupType === 'percentage') {
        $clientRate = $providerRate / (1 + ($markupValue / 100));
    } else {
        // Fixed markup subtracts from the units per KES
        $clientRate = $providerRate - $markupValue;
    }

    // Ensure rate doesn't go below 0
    $clientRate = max(0.1, $clientRate);
    $unitsToCredit = $amount * $clientRate;

    try {
        $db->beginTransaction();

        // Update transaction status
        $stmt = $db->prepare("UPDATE mpesa_transactions SET status = 'completed', callback_payload = ?, units_credited = ? WHERE id = ?");
        $stmt->execute([$rawPayload, $unitsToCredit, $transaction['id']]);

        // Credit wallet
        $stmt = $db->prepare("INSERT INTO wallet_accounts (client_id, balance_units) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance_units = balance_units + ?");
        $stmt->execute([$transaction['client_id'], $unitsToCredit, $unitsToCredit]);

        // Log to ledger
        $stmt = $db->prepare("INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note) VALUES (?, 'credit', ?, ?, ?)");
        $stmt->execute([
            $transaction['client_id'], 
            $unitsToCredit, 
            $mpesaReceipt, 
            "M-Pesa Topup - $checkoutRequestID (Plan: " . ($transaction['plan_id'] ?: 'None') . ", Rate: " . round($clientRate, 3) . " Units/KES)"
        ]);

        $db->commit();
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Payment Callback Error: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    // Failed
    $stmt = $db->prepare("UPDATE mpesa_transactions SET status = 'failed', callback_payload = ? WHERE id = ?");
    $stmt->execute([$rawPayload, $transaction['id']]);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Failure recorded']);
}
