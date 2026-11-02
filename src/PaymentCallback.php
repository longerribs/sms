<?php
/**
 * clayon/src/PaymentCallback.php
 * 
 * Handles M-Pesa STK Push callbacks for buying SMS units.
 * Supports dynamic reseller pricing based on client plans.
 */

require_once __DIR__ . '/../db.php';

// ── Helpers ────────────────────────────────────────────────────────────────────

function callback_log(string $message): void {
    $logDir  = __DIR__ . '/../logs';
    $logFile = $logDir . '/mpesa_callback.log';
    $line    = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;

    // Also push to PHP error_log for Apache log visibility
    error_log('M-Pesa Callback: ' . $message);

    if (is_writable($logDir)) {
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

// ── Read raw POST body ─────────────────────────────────────────────────────────

header('Content-Type: application/json');

// Read body — try multiple methods to handle chunked transfer or buffering
$rawPayload = file_get_contents('php://input');

// Fallback: read via stream with explicit length
if (empty($rawPayload)) {
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0) {
        $handle = fopen('php://input', 'r');
        $rawPayload = stream_get_contents($handle, $contentLength);
        fclose($handle);
    }
}

callback_log('Received [' . $_SERVER['REQUEST_METHOD'] . '] body(' . strlen((string)$rawPayload) . ' bytes): ' . substr((string)$rawPayload, 0, 500));

// ── Validate ───────────────────────────────────────────────────────────────────

if (empty($rawPayload)) {
    callback_log('ERROR: Empty body received from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Empty payload received']);
    exit;
}

$callback = json_decode($rawPayload, true);

if (!$callback || !isset($callback['Body']['stkCallback'])) {
    callback_log('ERROR: Invalid JSON or missing stkCallback key. JSON error: ' . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload structure']);
    exit;
}

$stkCallback      = $callback['Body']['stkCallback'];
$resultCode       = (int)$stkCallback['ResultCode'];
$checkoutRequestID = $stkCallback['CheckoutRequestID'];

callback_log("Processing CheckoutRequestID=$checkoutRequestID ResultCode=$resultCode");

// ── Database ───────────────────────────────────────────────────────────────────

$db = getClayonDb();

$stmt = $db->prepare("
    SELECT t.*, c.plan_id, p.provider_markup_type, p.markup_value 
    FROM mpesa_transactions t
    JOIN clients c ON t.client_id = c.id
    LEFT JOIN pricing_plans p ON c.plan_id = p.id
    WHERE t.checkout_request_id = ?
");
$stmt->execute([$checkoutRequestID]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    callback_log("WARNING: Transaction not found for CheckoutRequestID=$checkoutRequestID");
    // Return 0 so Safaricom does not retry forever
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Transaction not found']);
    exit;
}

if ($transaction['status'] !== 'pending') {
    callback_log("INFO: Transaction already in status={$transaction['status']} for CheckoutRequestID=$checkoutRequestID");
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Already processed']);
    exit;
}

// ── Process Result ─────────────────────────────────────────────────────────────

if ($resultCode === 0) {
    // ── Payment Successful ──────────────────────────────────────────────────

    $metadata = [];
    if (isset($stkCallback['CallbackMetadata']['Item'])) {
        foreach ($stkCallback['CallbackMetadata']['Item'] as $item) {
            $metadata[$item['Name']] = $item['Value'] ?? null;
        }
    }

    $mpesaReceipt = $metadata['MpesaReceiptNumber'] ?? '';
    callback_log("Payment SUCCESS Receipt=$mpesaReceipt for client_id={$transaction['client_id']}");

    // Fetch base provider rate from settings
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'PROVIDER_UNITS_PER_KES'");
    $stmt->execute();
    $providerRate = (float)($stmt->fetchColumn() ?: 2.0);

    $amount      = (float)$transaction['amount'];
    $markupValue = (float)($transaction['markup_value'] ?: 0);
    $markupType  = $transaction['provider_markup_type'] ?: 'percentage';

    // Calculate client-specific unit rate after markup
    if ($markupType === 'percentage') {
        $clientRate = $providerRate / (1 + ($markupValue / 100));
    } else {
        $clientRate = $providerRate - $markupValue;
    }
    $clientRate     = max(0.1, $clientRate);
    $unitsToCredit  = $amount * $clientRate;

    callback_log("Crediting {$unitsToCredit} units to client_id={$transaction['client_id']} (amount={$amount} KES, rate={$clientRate})");

    try {
        $db->beginTransaction();

        // Update transaction to completed
        $stmt = $db->prepare("UPDATE mpesa_transactions SET status = 'completed', callback_payload = ?, units_credited = ? WHERE id = ?");
        $stmt->execute([$rawPayload, $unitsToCredit, $transaction['id']]);

        // Credit wallet
        $stmt = $db->prepare("INSERT INTO wallet_accounts (client_id, balance_units) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance_units = balance_units + ?");
        $stmt->execute([$transaction['client_id'], $unitsToCredit, $unitsToCredit]);

        // Ledger entry
        $stmt = $db->prepare("INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note) VALUES (?, 'credit', ?, ?, ?)");
        $stmt->execute([
            $transaction['client_id'],
            $unitsToCredit,
            $mpesaReceipt,
            "M-Pesa Topup - $checkoutRequestID (Plan: " . ($transaction['plan_id'] ?: 'None') . ", Rate: " . round($clientRate, 3) . " Units/KES)"
        ]);

        $db->commit();
        callback_log("SUCCESS: Wallet credited {$unitsToCredit} units for client_id={$transaction['client_id']}");
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);

    } catch (Exception $e) {
        $db->rollBack();
        callback_log('DB ERROR: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Internal server error']);
    }

} else {
    // ── Payment Failed/Cancelled ────────────────────────────────────────────

    $resultDesc = $stkCallback['ResultDesc'] ?? 'Unknown';
    callback_log("Payment FAILED ResultCode=$resultCode Desc=$resultDesc for CheckoutRequestID=$checkoutRequestID");

    $stmt = $db->prepare("UPDATE mpesa_transactions SET status = 'failed', callback_payload = ? WHERE id = ?");
    $stmt->execute([$rawPayload, $transaction['id']]);

    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Failure recorded']);
}
