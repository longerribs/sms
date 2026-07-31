<?php
/**
 * clayon/api/payment/initiate.php
 * 
 * Initiate M-Pesa STK Push payment
 * POST /clayon/api/payment/initiate
 */

require_once __DIR__ . '/../../bootstrap.php';

try {
    // Verify API key
    $client = Auth::verifyApiKey();
    $clientId = $client['id'];

    // Parse request
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate
    if (!Validator::validateTopupRequest($data)) {
        Response::validation(Validator::getErrors());
    }

    $amount = floatval($data['amount']);
    $phone = $data['phone'];

    // Normalize phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '+254' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) !== '+25') {
        $phone = '+254' . $phone;
    }

    $db = getDb();

    try {
        $db->beginTransaction();

        // Create transaction record
        $checkoutRequestId = 'CLAY_' . bin2hex(random_bytes(16)) . '_' . time();
        
        $stmt = $db->prepare("
            INSERT INTO mpesa_transactions (client_id, checkout_request_id, phone, amount, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$clientId, $checkoutRequestId, $phone, $amount]);
        $transactionId = $db->lastInsertId();

        $db->commit();

        // Prepare STK Push request
        $mpesaConfig = Config::getMpesaConfig();
        $timestamp = date('YmdHis');
        $businessShortCode = $mpesaConfig['shortcode'];
        $passkey = $mpesaConfig['passkey'];
        $password = base64_encode($businessShortCode . $passkey . $timestamp);

        $stkPushPayload = [
            'BusinessShortCode' => $businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => intval($amount),
            'PartyA' => $phone,
            'PartyB' => $businessShortCode,
            'PhoneNumber' => $phone,
            'CallBackURL' => getenv('APP_URL') . '/clayon/callback/payment',
            'AccountReference' => 'CLAYON-' . $clientId,
            'TransactionDesc' => 'SMS Units TopUp'
        ];

        // Get M-Pesa token
        $mpesaAuth = getMpesaToken($mpesaConfig);
        if (!$mpesaAuth) {
            throw new Exception('Failed to authenticate with M-Pesa');
        }

        // Send STK Push
        $ch = curl_init($mpesaConfig['stk_push_url']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $mpesaAuth
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkPushPayload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }

        $responseData = json_decode($response, true);

        // Save response to transaction
        $stmt = $db->prepare("
            UPDATE mpesa_transactions 
            SET callback_payload = ? 
            WHERE id = ?
        ");
        $stmt->execute([$response, $transactionId]);

        if ($httpCode === 200 && isset($responseData['CheckoutRequestID'])) {
            Response::success([
                'transaction_id' => $transactionId,
                'checkout_request_id' => $responseData['CheckoutRequestID'],
                'merchant_request_id' => $responseData['MerchantRequestID'] ?? null,
                'phone' => $phone,
                'amount' => $amount,
                'status' => 'pending',
                'message' => 'Check your phone for the M-Pesa prompt'
            ], 'Payment initiated', 201);
        } else {
            throw new Exception('M-Pesa API error: ' . ($responseData['errorMessage'] ?? 'Unknown error'));
        }

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Payment initiate error: " . $e->getMessage());
        Response::serverError('Failed to initiate payment: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Payment endpoint error: " . $e->getMessage());
    Response::serverError('Failed to process payment request');
}

/**
 * Get M-Pesa authentication token
 */
function getMpesaToken($config) {
    try {
        $ch = curl_init($config['auth_url']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $config['consumer_key'] . ':' . $config['consumer_secret']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    } catch (Exception $e) {
        error_log("M-Pesa auth error: " . $e->getMessage());
        return null;
    }
}
