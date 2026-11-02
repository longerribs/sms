<?php
/**
 * clayon/api/v1/stk_push.php
 *
 * Initiates M-Pesa STK Push for wallet top-up.
 * Features automatic fallback to PHP stream contexts when curl_init is unavailable.
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

$input = json_decode(file_get_contents('php://input'), true);
$amount = (float)($input['amount'] ?? 0);
$phone = $input['phone'] ?? '';

$phone = preg_replace('/[^0-9]/', '', (string) $phone);
if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
    $phone = '254' . substr($phone, 1);
}

if ($amount < 1 || !$phone) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid amount or phone number']);
    exit;
}

$missingConfig = [];
foreach (['MPESA_CONSUMER_KEY', 'MPESA_CONSUMER_SECRET', 'MPESA_AUTH_URL', 'MPESA_SHORTCODE', 'MPESA_TILL_NUMBER', 'MPESA_PASSKEY', 'MPESA_STK_PUSH_URL', 'APP_URL'] as $key) {
    if (trim((string) clayon_env($key, '')) === '') {
        $missingConfig[] = $key;
    }
}

if (!empty($missingConfig)) {
    error_log('M-Pesa config missing: ' . implode(', ', $missingConfig));
    echo json_encode([
        'status' => 'error',
        'message' => 'M-Pesa configuration is incomplete. Missing: ' . implode(', ', $missingConfig)
    ]);
    exit;
}

/**
 * Universal HTTP request helper with curl & stream context fallback
 */
function clayon_http_request(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = $body;
            }
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'body' => $response,
            'code' => (int) $code,
            'error' => (string) $error
        ];
    }

    // Native stream context fallback when curl extension is disabled or missing
    $formattedHeaders = implode("\r\n", $headers);
    $httpOptions = [
        'method' => strtoupper($method),
        'header' => $formattedHeaders,
        'timeout' => 30,
        'ignore_errors' => true,
    ];
    if ($body !== null) {
        $httpOptions['content'] = $body;
    }

    $context = stream_context_create([
        'http' => $httpOptions,
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $hdr) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $hdr, $m)) {
                $code = (int) $m[1];
            }
        }
    }

    $error = ($response === false) ? 'Failed to connect to M-Pesa gateway' : '';
    return [
        'body' => $response,
        'code' => $code,
        'error' => $error
    ];
}

$consumerKey = trim((string) clayon_env('MPESA_CONSUMER_KEY'));
$consumerSecret = trim((string) clayon_env('MPESA_CONSUMER_SECRET'));
$authUrl = trim((string) clayon_env('MPESA_AUTH_URL'));
$shortCode = trim((string) clayon_env('MPESA_SHORTCODE'));
$tillNumber = trim((string) clayon_env('MPESA_TILL_NUMBER'));
$passKey = trim((string) clayon_env('MPESA_PASSKEY'));
$stkUrl = trim((string) clayon_env('MPESA_STK_PUSH_URL'));
$appUrl = trim((string) clayon_env('APP_URL', ''));

// Dynamic ngrok / tunnel auto-detection:
// If request arrives via a tunnel (e.g. ngrok), use the active incoming tunnel host
// so callbacks never break if ngrok is restarted with a new URL.
$forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
if (!empty($forwardedHost) && (strpos($forwardedHost, 'ngrok') !== false || strpos($forwardedHost, 'loca.lt') !== false)) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/sms/api/v1/stk_push.php');
    $baseDir = rtrim(dirname(dirname($scriptDir)), '/\\');
    $baseDir = str_replace('\\', '/', $baseDir);
    if ($baseDir === '.' || $baseDir === '/') {
        $baseDir = '';
    }
    $callbackUrl = $forwardedProto . '://' . $forwardedHost . $baseDir . '/callback.php';
} else {
    $callbackUrl = rtrim($appUrl, '/') . '/callback.php';
}


// 1. Get Access Token
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$authResult = clayon_http_request(
    $authUrl,
    'GET',
    [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/json'
    ]
);

$response = $authResult['body'];
$authError = $authResult['error'];
$authHttpCode = $authResult['code'];

if ($response === false || $authError !== '') {
    error_log('M-Pesa OAuth error: ' . $authError);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to authenticate with M-Pesa: ' . $authError
    ]);
    exit;
}

$authData = json_decode($response, true);
if ($authHttpCode >= 400 || !isset($authData['access_token'])) {
    error_log('M-Pesa OAuth failed: HTTP ' . $authHttpCode . ' Response: ' . $response);
    echo json_encode([
        'status' => 'error',
        'message' => 'M-Pesa authentication failed. Please verify consumer key and secret.',
        'details' => ['http_code' => $authHttpCode, 'response' => $response]
    ]);
    exit;
}

$accessToken = $authData['access_token'];

// 2. Initiate STK Push
$timestamp = date('YmdHis');
$password = base64_encode($shortCode . $passKey . $timestamp);
$merchantId = !empty($tillNumber) ? $tillNumber : $shortCode;

$payload = [
    'BusinessShortCode' => $shortCode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerBuyGoodsOnline',
    'Amount' => round($amount),
    'PartyA' => $phone,
    'PartyB' => $merchantId,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callbackUrl,
    'AccountReference' => 'CLAYON_SMS',
    'TransactionDesc' => 'Wallet Topup for Client #' . $clientId
];

$stkResult = clayon_http_request(
    $stkUrl,
    'POST',
    [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ],
    json_encode($payload)
);

$stkResponse = $stkResult['body'];
$stkError = $stkResult['error'];
$stkHttpCode = $stkResult['code'];

if ($stkResponse === false || $stkError !== '') {
    error_log('M-Pesa STK request failed: ' . $stkError);
    echo json_encode([
        'status' => 'error',
        'message' => 'STK push request failed: ' . $stkError
    ]);
    exit;
}

$stkData = json_decode($stkResponse, true);
if ($stkHttpCode >= 400 || !is_array($stkData)) {
    error_log('M-Pesa STK invalid response: HTTP ' . $stkHttpCode . ' Body: ' . $stkResponse);
    echo json_encode([
        'status' => 'error',
        'message' => 'M-Pesa returned an invalid STK response.',
        'details' => ['http_code' => $stkHttpCode, 'response' => $stkResponse]
    ]);
    exit;
}

if (isset($stkData['ResponseCode']) && $stkData['ResponseCode'] == '0') {
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
    error_log('M-Pesa STK Error: ' . json_encode($stkData));

    echo json_encode([
        'status' => 'error',
        'message' => $stkData['errorMessage'] ?? $stkData['ResponseDescription'] ?? 'STK Push failed. Please check your phone number.',
        'details' => $stkData
    ]);
}
