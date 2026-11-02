<?php
/**
 * clayon/setup/verify-setup.php
 * 
 * Verify Clayon SMS installation, configuration, and database integrity.
 * Supports both CLI execution and browser rendering.
 * 
 * CLI: php setup/verify-setup.php
 * Web: http://localhost/sms/setup/verify-setup.php
 */

require_once __DIR__ . '/../env_loader.php';

$isCli = (php_sapi_name() === 'cli');
$checks = [];
$all_passed = true;

function check(string $name, bool $condition, string $details = '') {
    global $checks, $all_passed;
    if (!$condition) {
        $all_passed = false;
    }
    $checks[] = [
        'name' => $name,
        'status' => $condition ? '✅' : '❌',
        'passed' => $condition,
        'details' => $details
    ];
}

// 1. Files & Directories
check('.env2 configuration file exists', file_exists(__DIR__ . '/../.env2'));
check('bootstrap.php exists', file_exists(__DIR__ . '/../bootstrap.php'));
check('config/Database.php exists', file_exists(__DIR__ . '/../config/Database.php'));

$sqlSchemaExists = file_exists(__DIR__ . '/../sql/sms_clayon_sms.sql') || file_exists(__DIR__ . '/../sql/schema.sql');
check('Database schema SQL exists (sql/sms_clayon_sms.sql)', $sqlSchemaExists);

check('pages/ directory exists', is_dir(__DIR__ . '/../pages'));
check('api/ directory exists', is_dir(__DIR__ . '/../api'));
check('config/ directory exists', is_dir(__DIR__ . '/../config'));
check('src/ directory exists', is_dir(__DIR__ . '/../src'));
check('sql/ directory exists', is_dir(__DIR__ . '/../sql'));

// 2. Environment Variables
$dbName = (string)clayon_env('DB_DATABASE', '');
check('DB_DATABASE configured in .env2', !empty($dbName), $dbName ?: 'Missing');
check('DB_HOST configured', !empty(clayon_env('DB_HOST')), (string)clayon_env('DB_HOST', 'localhost'));
check('DB_USERNAME configured', !empty(clayon_env('DB_USERNAME')), (string)clayon_env('DB_USERNAME', 'root'));
check('APP_URL configured', !empty(clayon_env('APP_URL')), (string)clayon_env('APP_URL', ''));

$talksasaKey = (string)clayon_env('TALKSASA_API_KEY', '');
check('TalkSasa API Key configured', !empty($talksasaKey), $talksasaKey ? substr($talksasaKey, 0, 10) . '...' : 'Missing');

$mpesaConfigured = !empty(clayon_env('MPESA_CONSUMER_KEY')) && !empty(clayon_env('MPESA_CONSUMER_SECRET')) && !empty(clayon_env('MPESA_PASSKEY'));
check('M-Pesa Daraja STK Push credentials configured', $mpesaConfigured);

// 3. PHP Extensions & Transport
check('PDO extension enabled', extension_loaded('pdo'));
check('PDO MySQL driver enabled', extension_loaded('pdo_mysql'));
check('OpenSSL extension enabled', extension_loaded('openssl'));

$caBundleFound = file_exists('C:/xammp/apache/bin/curl-ca-bundle.crt') || file_exists('C:/xammp/perl/vendor/lib/Mozilla/CA/cacert.pem') || !empty(ini_get('openssl.cafile'));
$hasHttpTransport = extension_loaded('curl') || $caBundleFound;
check('HTTP Gateway Transport (curl or stream context + CA bundle)', $hasHttpTransport, extension_loaded('curl') ? 'cURL loaded' : 'Stream fallback with SSL CA bundle active');

// 4. Database Connectivity & Schema
$dbConnected = false;
$pdo = null;

try {
    $host = (string)clayon_env('DB_HOST', 'localhost');
    $port = (int)clayon_env('DB_PORT', 3306);
    $user = (string)clayon_env('DB_USERNAME', 'root');
    $pass = (string)clayon_env('DB_PASSWORD', '');
    $database = $dbName ?: 'sms_clayon_sms';

    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $dbConnected = true;
    check('Database server accessible', true, "$host:$port ($database)");

    // Check core tables
    $coreTables = [
        'clients', 
        'client_api_keys', 
        'wallet_accounts', 
        'wallet_ledger', 
        'sms_requests', 
        'sms_queue', 
        'sms_attempts',
        'provider_sms_logs',
        'mpesa_transactions',
        'pricing_plans',
        'system_settings',
        'sender_ids',
        'delivery_reports'
    ];

    $existingTables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }

    $missingTables = array_diff($coreTables, $existingTables);
    check('Core database tables created', empty($missingTables), empty($missingTables) ? count($existingTables) . ' tables verified' : 'Missing: ' . implode(', ', $missingTables));

    // Check client records
    $clientCount = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    check('Client profile accounts present', $clientCount > 0, "$clientCount clients found");

    // Check wallet accounts
    if (in_array('wallet_accounts', $existingTables)) {
        $walletCount = (int)$pdo->query("SELECT COUNT(*) FROM wallet_accounts")->fetchColumn();
        check('Wallet accounts initialized', $walletCount > 0, "$walletCount wallets active");
    }

    // Check pricing plans
    if (in_array('pricing_plans', $existingTables)) {
        $planCount = (int)$pdo->query("SELECT COUNT(*) FROM pricing_plans WHERE status = 'active'")->fetchColumn();
        check('Active pricing plans configured', $planCount > 0, "$planCount active plans");
    }

} catch (Exception $e) {
    check('Database server accessible', false, $e->getMessage());
}

// 5. Output Results

if ($isCli) {
    echo "=====================================================\n";
    echo "🔍 Clayon SMS: Setup Verification\n";
    echo "=====================================================\n\n";

    foreach ($checks as $item) {
        $mark = $item['passed'] ? "\033[32m[✔]\033[0m" : "\033[31m[✘]\033[0m";
        printf("  %s %-45s", $mark, $item['name']);
        if ($item['details']) {
            echo " \033[90m(" . $item['details'] . ")\033[0m";
        }
        echo "\n";
    }

    echo "\n=====================================================\n";
    if ($all_passed) {
        echo "Result: \033[32m✅ All Checks Passed! System is healthy.\033[0m\n";
        echo "=====================================================\n\n";
        echo "🌐 Available Endpoints:\n";
        echo "  - Composer:   " . rtrim((string)clayon_env('APP_URL', 'http://localhost/sms'), '/') . "/send.php\n";
        echo "  - Top-up:     " . rtrim((string)clayon_env('APP_URL', 'http://localhost/sms'), '/') . "/buy.php\n";
        echo "  - Dev Docs:   " . rtrim((string)clayon_env('APP_URL', 'http://localhost/sms'), '/') . "/developer.php\n";
        echo "  - Reports:    " . rtrim((string)clayon_env('APP_URL', 'http://localhost/sms'), '/') . "/reports.php\n\n";
        exit(0);
    } else {
        echo "Result: \033[31m❌ Some Checks Failed. Please review warnings above.\033[0m\n";
        echo "=====================================================\n\n";
        exit(1);
    }
}

// Web Output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clayon Setup Verification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
            padding: 30px 20px;
            color: #e2e8f0;
        }
        .container {
            max-width: 850px;
            margin: 0 auto;
            background: rgba(30, 41, 59, 0.85);
            padding: 40px;
            border-radius: 16px;
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        h1 {
            color: #f8fafc;
            margin-bottom: 20px;
            text-align: center;
            font-size: 26px;
        }
        .status-badge {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            padding: 14px;
            margin-bottom: 30px;
            border-radius: 10px;
        }
        .status-badge.passed {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-badge.failed {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .checks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 12px;
            margin: 25px 0;
        }
        .check {
            padding: 14px 16px;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.6);
            border-left: 4px solid #64748b;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .check.passed {
            border-left: 4px solid #10b981;
        }
        .check.failed {
            border-left: 4px solid #ef4444;
        }
        .check-status { font-size: 18px; }
        .check-content { flex: 1; }
        .check-name { font-weight: 600; font-size: 14px; color: #f1f5f9; }
        .check-details { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .footer {
            text-align: center;
            color: #64748b;
            margin-top: 30px;
            font-size: 12px;
            border-top: 1px solid rgba(255,255,255,0.06);
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Clayon SMS Setup Verification</h1>
        
        <div class="status-badge <?php echo $all_passed ? 'passed' : 'failed'; ?>">
            <?php echo $all_passed ? '✅ All Checks Passed! System is Ready.' : '❌ Some Checks Failed'; ?>
        </div>

        <div class="checks-grid">
            <?php foreach ($checks as $check): ?>
            <div class="check <?php echo $check['passed'] ? 'passed' : 'failed'; ?>">
                <div class="check-status"><?php echo $check['status']; ?></div>
                <div class="check-content">
                    <div class="check-name"><?php echo htmlspecialchars($check['name']); ?></div>
                    <?php if ($check['details']): ?>
                    <div class="check-details"><?php echo htmlspecialchars($check['details']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <p>Clayon SMS Reseller Platform • Setup Verification Tool</p>
            <p>Checked: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
