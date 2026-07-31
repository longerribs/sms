<?php
/**
 * clayon/setup/verify-installation.php
 * 
 * Verify Clayon installation is complete and working
 * Run: php clayon/setup/verify-installation.php
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          CLAYON INSTALLATION VERIFICATION                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$checks = [];
$passed = 0;
$failed = 0;

// Check 1: Files exist
echo "📂 Checking file structure...\n";

$requiredFiles = [
    'config/Database.php',
    'config/Config.php',
    'config/Response.php',
    'config/Auth.php',
    'config/Validator.php',
    'src/SMSService.php',
    'src/QueueService.php',
    'src/WalletService.php',
    'src/PricingService.php',
    'src/SenderIdService.php',
    'src/Worker.php',
    'src/PaymentCallback.php',
    'api/send.php',
    'api/balance.php',
    'api/history.php',
    'api/sender-ids.php',
    'api/ledger.php',
    'api/payment/initiate.php',
    'callback/payment.php',
    'pages/login.html',
    'pages/dashboard.html',
    'sql/schema.sql',
    'bootstrap.php',
    'index.php'
];

$basePath = __DIR__ . '/..';

foreach ($requiredFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        echo "  ✅ $file\n";
        $passed++;
    } else {
        echo "  ❌ $file (MISSING)\n";
        $failed++;
    }
}

echo "\n";

// Check 2: Environment
echo "⚙️  Checking environment...\n";

$env_file = __DIR__ . '/../../.env';
if (file_exists($env_file)) {
    echo "  ✅ .env file exists\n";
    $passed++;
    
    $env_content = file_get_contents($env_file);
    if (strpos($env_content, 'TALKSASA_API_KEY') !== false) {
        echo "  ✅ TALKSASA_API_KEY configured\n";
        $passed++;
    } else {
        echo "  ⚠️  TALKSASA_API_KEY not set (configure in .env)\n";
    }
    
    if (strpos($env_content, 'MPESA_CONSUMER_KEY') !== false) {
        echo "  ✅ M-Pesa credentials configured\n";
        $passed++;
    } else {
        echo "  ⚠️  M-Pesa credentials not set (optional)\n";
    }
} else {
    echo "  ❌ .env file not found\n";
    $failed++;
}

echo "\n";

// Check 3: Database
echo "🗄️  Checking database...\n";

try {
    require_once $basePath . '/config/Database.php';
    $db = getDb();
    echo "  ✅ Database connection successful\n";
    $passed++;
    
    // Check tables
    $tableNames = [
        'clients', 'client_api_keys', 'sender_ids', 'pricing_plans',
        'wallet_accounts', 'wallet_ledger', 'sms_requests', 'sms_queue',
        'sms_attempts', 'provider_sms_logs', 'mpesa_transactions',
        'delivery_reports', 'audit_logs', 'system_settings'
    ];
    
    $allTablesExist = true;
    foreach ($tableNames as $table) {
        try {
            $stmt = $db->query("SELECT 1 FROM $table LIMIT 1");
            echo "  ✅ Table: $table\n";
            $passed++;
        } catch (Exception $e) {
            echo "  ❌ Table: $table (MISSING)\n";
            $failed++;
            $allTablesExist = false;
        }
    }
    
    if (!$allTablesExist) {
        echo "\n  💡 To initialize database schema:\n";
        echo "     php clayon/setup/init-db.php\n";
    }
    
} catch (Exception $e) {
    echo "  ❌ Database connection failed: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n";

// Check 4: Permissions
echo "🔐 Checking permissions...\n";

$pathsToCheck = [
    'config',
    'src',
    'api',
    'callback',
    'pages',
    'sql',
    'setup',
    'admin'
];

foreach ($pathsToCheck as $path) {
    $fullPath = $basePath . '/' . $path;
    if (is_writable(dirname($fullPath))) {
        echo "  ✅ $path directory writable\n";
        $passed++;
    } else {
        echo "  ⚠️  $path directory may have permission issues\n";
    }
}

echo "\n";

// Summary
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                     VERIFICATION SUMMARY                   ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$total = $passed + $failed;
$percentage = ($total > 0) ? round(($passed / $total) * 100) : 0;

echo "  Passed: $passed/$total ($percentage%)\n";
echo "  Failed: $failed/$total\n\n";

if ($failed === 0) {
    echo "✅ Installation verification passed!\n\n";
    echo "📖 Next Steps:\n";
    echo "  1. Initialize database (if not done):\n";
    echo "     php clayon/setup/init-db.php\n\n";
    echo "  2. Create your first client:\n";
    echo "     php clayon/setup/create-client.php \"Client Name\" \"email@example.com\"\n\n";
    echo "  3. Set up cron worker (add to crontab):\n";
    echo "     * * * * * php /full/path/to/clayon/src/Worker.php\n\n";
    echo "  4. Access the dashboard:\n";
    echo "     http://localhost/clayon/pages/dashboard.html\n\n";
    echo "📚 Documentation: clayon/README.md\n";
    echo "🆘 Setup Guide: clayon/SETUP_GUIDE.md\n";
} else {
    echo "⚠️  Installation verification failed!\n";
    echo "❌ Please fix the issues above and try again.\n";
    exit(1);
}

echo "\n";
