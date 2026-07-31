<?php
/**
 * clayon/api/v1/stk_push.php
 * 
 * Initiates M-Pesa STK Push for wallet top-up.
 * Updated to return checkout_id for frontend polling.
 */

session_start();
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../env_loader.php';

header("Content-Type: application/json");

if (!isset($_SESSION['CLAYON_CLIENT_ID'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
$amount = (float)($input['amount'] ?? 0);
$phone = $input['phone'] ?? '';

// Sanitize phone (ensure 254 format)
$phone = preg_replace('/[^0-9]/', '', $phone);
if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
    $phone = '254' . substr($phone, 1);
}

if ($amount < 1 || !$phone) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid amount or phone number']);
    exit;
}

// 1. Get Access Token
$consumerKey = clayon_env('MPESA_CONSUMER_KEY');
$consumerSecret = clayon_env('MPESA_CONSUMER_SECRET');
$authUrl = clayon_env('MPESA_AUTH_URL');

$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$ch = curl_init($authUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$authData = json_decode($response, true);

if (!isset($authData['access_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to get M-Pesa access token']);
    exit;
}

$accessToken = $authData['access_token'];

// 2. Initiate STK Push
$shortCode = clayon_env('MPESA_SHORTCODE');
$tillNumber = clayon_env('MPESA_TILL_NUMBER');
$passKey = clayon_env('MPESA_PASSKEY');
$stkUrl = clayon_env('MPESA_STK_PUSH_URL');
$callbackUrl = clayon_env('APP_URL') . '/callback.php';

$timestamp = date('YmdHis');
$password = base64_encode($shortCode . $passKey . $timestamp);

$payload = [
    'BusinessShortCode' => $shortCode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerBuyGoodsOnline',
    'Amount' => round($amount),
    'PartyA' => $phone,
    'PartyB' => $tillNumber,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callbackUrl,
    'AccountReference' => 'CLAYON_SMS',
    'TransactionDesc' => 'Wallet Topup for Client #' . $clientId
];

$ch = curl_init($stkUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$stkResponse = curl_exec($ch);
$stkData = json_decode($stkResponse, true);

if (isset($stkData['ResponseCode']) && $stkData['ResponseCode'] == '0') {
    // Save pending transaction
    $stmt = $db->prepare("INSERT INTO mpesa_transactions (client_id, checkout_request_id, merchant_request_id, phone, amount, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([
        $clientId,
        $stkData['CheckoutRequestID'],
        $stkData['MerchantRequestID'],
        $phone,
        $amount
    ]);

    echo json_encode([
        'status' => 'success', 
        'message' => 'STK Push initiated',
        'checkout_id' => $stkData['CheckoutRequestID']
    ]);
} else {
    error_log("M-Pesa STK Error: " . json_encode($stkData));
    
    echo json_encode([
        'status' => 'error', 
        'message' => $stkData['errorMessage'] ?? 'STK Push failed. Please check your phone number.',
        'details' => $stkData
    ]);
}
