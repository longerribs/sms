<?php
/**
 * clayon/setup/verify-setup.php
 * 
 * Verify Clayon installation and configuration
 */

header('Content-Type: text/html; charset=utf-8');

$checks = [];
$all_passed = true;

// Helper function
function check($name, $condition, $details = '') {
    global $checks, $all_passed;
    $status = $condition ? '✅' : '❌';
    if (!$condition) $all_passed = false;
    $checks[] = [
        'name' => $name,
        'status' => $status,
        'passed' => $condition,
        'details' => $details
    ];
}

// File checks
check('clayon/.env2 exists', file_exists(__DIR__ . '/../.env2'));
check('bootstrap.php exists', file_exists(__DIR__ . '/../bootstrap.php'));
check('Database.php exists', file_exists(__DIR__ . '/../config/Database.php'));
check('schema.sql exists', file_exists(__DIR__ . '/../sql/schema.sql'));

// Directory checks
check('pages/ directory exists', is_dir(__DIR__ . '/../pages'));
check('api/ directory exists', is_dir(__DIR__ . '/../api'));
check('config/ directory exists', is_dir(__DIR__ . '/../config'));
check('src/ directory exists', is_dir(__DIR__ . '/../src'));
check('sql/ directory exists', is_dir(__DIR__ . '/../sql'));

// Environment file content checks
if (file_exists(__DIR__ . '/../.env2')) {
    $env_content = file_get_contents(__DIR__ . '/../.env2');
    check('DB_DATABASE=clayon_sms in .env2', strpos($env_content, 'DB_DATABASE=clayon_sms') !== false);
    check('DB_HOST configured', preg_match('/DB_HOST=/', $env_content));
    check('DB_USERNAME configured', preg_match('/DB_USERNAME=/', $env_content));
    check('DB_PASSWORD configured', preg_match('/DB_PASSWORD=/', $env_content));
}

// PHP extensions
check('PDO extension enabled', extension_loaded('pdo'));
check('PDO MySQL driver', extension_loaded('pdo_mysql'));
check('cURL extension', extension_loaded('curl'));

// Database connectivity
try {
    // Load .env2
    $envFile = __DIR__ . '/../.env2';
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
    
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: 3306;
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    // Try to connect to MySQL (without selecting database first)
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    check('Database server accessible', true, "$host:$port");
    
    // Check if clayon_sms database exists
    $result = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'clayon_sms'");
    $db_exists = $result->rowCount() > 0;
    check('clayon_sms database exists', $db_exists);
    
    if ($db_exists) {
        // Check tables
        $result = $pdo->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'clayon_sms'");
        $count = $result->fetch()['count'];
        check('Database tables created', $count > 0, "$count tables found");
        
        // Check admin user
        $pdo->exec("USE clayon_sms");
        $result = $pdo->query("SELECT id FROM clients WHERE email = 'simonjogu001@gmail.com' LIMIT 1");
        $admin_exists = $result->rowCount() > 0;
        check('Admin user exists', $admin_exists, 'simonjogu001@gmail.com');
        
        if ($admin_exists) {
            $admin = $result->fetch();
            $result = $pdo->query("SELECT balance FROM wallet_accounts WHERE client_id = " . $admin['id']);
            if ($result->rowCount() > 0) {
                $wallet = $result->fetch();
                check('Admin wallet initialized', true, $wallet['balance'] . ' units');
            }
        }
    }
    
} catch (Exception $e) {
    check('Database server accessible', false, $e->getMessage());
}

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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .status-badge {
            text-align: center;
            font-size: 24px;
            padding: 10px;
            margin-bottom: 30px;
            border-radius: 6px;
        }
        .status-badge.passed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-badge.failed {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .checks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        .check {
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #ccc;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .check.passed {
            background: #f0f9ff;
            border-left-color: #28a745;
        }
        .check.failed {
            background: #fff5f5;
            border-left-color: #dc3545;
        }
        .check-status {
            font-size: 20px;
            flex-shrink: 0;
        }
        .check-content {
            flex: 1;
            min-width: 0;
        }
        .check-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        .check-details {
            font-size: 12px;
            color: #666;
            word-break: break-word;
        }
        .next-steps {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 6px;
            margin-top: 30px;
            border-left: 4px solid #667eea;
        }
        .next-steps h2 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
        }
        .next-steps ol {
            margin-left: 20px;
            color: #555;
            line-height: 1.8;
        }
        .next-steps li {
            margin-bottom: 10px;
        }
        .code {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .footer {
            text-align: center;
            color: #999;
            margin-top: 40px;
            font-size: 12px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Clayon Setup Verification</h1>
        
        <div class="status-badge <?php echo $all_passed ? 'passed' : 'failed'; ?>">
            <?php echo $all_passed ? '✅ All Checks Passed!' : '❌ Some Checks Failed'; ?>
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

        <div class="next-steps">
            <h2><?php echo $all_passed ? '✅ Ready to Go!' : '⚠️ Next Steps'; ?></h2>
            <?php if ($all_passed): ?>
                <ol>
                    <li>Access the dashboard: <a href="/clayon/pages/login.html">Login Page</a></li>
                    <li>Use the API key displayed during setup to login</li>
                    <li>Configure TalkSasa API key in <code>clayon/.env2</code></li>
                    <li>Set up cron worker for background SMS processing</li>
                </ol>
            <?php else: ?>
                <ol>
                    <li>Run database setup: <div class="code">php clayon/setup/run-all-setup.php</div></li>
                    <li>Fix any configuration issues in <code>clayon/.env2</code></li>
                    <li>Ensure MySQL server is running and accessible</li>
                    <li>Verify file permissions on setup directory</li>
                    <li>After fixes, refresh this page to verify again</li>
                </ol>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Clayon SMS Reseller Platform • Setup Verification Tool</p>
            <p>Last checked: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
