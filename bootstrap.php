<?php
/**
 * sms/bootstrap.php
 * 
 * Bootstrap file for SMS module.
 * Initializes all dependencies and configuration.
 */

// Error handling
error_reporting(E_ALL);
ini_set('log_errors', 1);
date_default_timezone_set('Africa/Nairobi');

// Load Clayon-specific environment from .env2
$envFile = __DIR__ . '/.env2';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || !strpos($line, '=')) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
}

// Set display_errors after loading Config constants
ini_set('display_errors', 0);

// Load configuration classes
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Response.php';
require_once __DIR__ . '/config/Auth.php';
require_once __DIR__ . '/config/Validator.php';

// Load services
require_once __DIR__ . '/src/WalletService.php';
require_once __DIR__ . '/src/PricingService.php';
require_once __DIR__ . '/src/SenderIdService.php';
require_once __DIR__ . '/src/SMSService.php';
require_once __DIR__ . '/src/QueueService.php';

// Initialize database and create schema if needed
try {
    $db = Database::getInstance()->getConnection();
    
    // You can initialize schema here if first time setup
    // Database::getInstance()->initializeSchema();
    
} catch (Exception $e) {
    error_log("Bootstrap error: " . $e->getMessage());
    Response::serverError("Database initialization failed");
}

// Set CORS headers for API
header('Access-Control-Allow-Origin: ' . (getenv('APP_URL') ?: '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set default response type
header('Content-Type: application/json');
