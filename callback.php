<?php
/**
 * sms/callback.php
 * 
 * Public entry point for M-Pesa callbacks from Safaricom.
 * Must be accessible without session authentication.
 */

// Disable output buffering to ensure clean JSON response
if (ob_get_level()) {
    ob_end_clean();
}

// Handle GET health check (for testing reachability)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'message' => 'M-Pesa callback endpoint is reachable.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

require_once __DIR__ . '/src/PaymentCallback.php';
