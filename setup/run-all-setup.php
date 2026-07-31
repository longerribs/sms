<?php
/**
 * clayon/setup/run-all-setup.php
 * 
 * Run all setup steps in sequence
 * Usage: php clayon/setup/run-all-setup.php
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║            CLAYON COMPLETE SETUP WIZARD                               ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Create Database
echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "STEP 1: Create Database (clayon_sms)\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: 3306;
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    echo "🔗 Connecting to MySQL...\n";
    $pdo = new PDO(
        "mysql:host=$host;port=$port",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "   ✅ Connected to MySQL\n";
    
    $databaseName = 'clayon_sms';
    echo "📦 Creating database: $databaseName\n";
    
    try {
        $pdo->exec("DROP DATABASE IF EXISTS `$databaseName`");
        echo "   ✅ Cleaned existing database (if any)\n";
    } catch (Exception $e) {
        // Ignore if database doesn't exist
    }
    
    $pdo->exec("CREATE DATABASE `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   ✅ Database created successfully\n";
    
} catch (PDOException $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Initialize Schema and Seed Data
echo "\n═══════════════════════════════════════════════════════════════════════\n";
echo "STEP 2: Initialize Schema and Seed Data\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

try {
    require_once __DIR__ . '/../config/Database.php';
    require_once __DIR__ . '/../config/Auth.php';
    require_once __DIR__ . '/../config/Config.php';
    
    $db = getDb();
    
    // Initialize schema
    echo "📊 Initializing database schema...\n";
    Database::getInstance()->initializeSchema();
    echo "   ✅ Schema created\n\n";
    
    // Create pricing plan
    echo "💰 Creating pricing plans...\n";
    $stmt = $db->prepare("
        INSERT INTO pricing_plans (plan_name, provider_markup_type, markup_value, min_topup, status)
        VALUES (?, ?, ?, ?, 'active')
    ");
    $stmt->execute(['Default', 'percentage', 25.0, 100.0]);
    echo "   ✅ Default plan (25% markup)\n";
    
    $stmt->execute(['Premium', 'percentage', 30.0, 500.0]);
    echo "   ✅ Premium plan (30% markup)\n";
    
    $stmt->execute(['Starter', 'fixed', 0.25, 50.0]);
    echo "   ✅ Starter plan (fixed KES 0.25 per segment)\n\n";
    
    // Create admin user
    echo "👤 Creating admin user...\n";
    $adminName = 'Simon Jogu';
    $adminEmail = 'simonjogu001@gmail.com';
    $adminPhone = '0711486334';
    $adminPassword = password_hash('admin@123', PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("
        INSERT INTO clients (name, email, phone, password_hash, plan_id, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$adminName, $adminEmail, $adminPhone, $adminPassword, 1]);
    $adminClientId = $db->lastInsertId();
    echo "   ✅ Admin user created: $adminEmail\n";
    
    // Initialize wallet
    echo "💳 Setting up admin wallet...\n";
    $stmt = $db->prepare("
        INSERT INTO wallet_accounts (client_id, balance_units, reserved_units)
        VALUES (?, 10000, 0)
    ");
    $stmt->execute([$adminClientId]);
    echo "   ✅ Initial balance: 10,000 SMS units\n";
    
    // Ledger entry
    $stmt = $db->prepare("
        INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note)
        VALUES (?, 'credit', ?, ?, ?)
    ");
    $stmt->execute([$adminClientId, 10000, 'INIT_ADMIN', 'Admin account initialization']);
    
    // Create API key
    echo "🔑 Generating API key...\n";
    $apiKey = Auth::generateApiKey($adminClientId);
    echo "   ✅ API key generated\n\n";
    
    // Create sender IDs
    echo "📤 Creating approved sender IDs...\n";
    $senderIds = ['CLAYON', 'ADMIN', 'TEST'];
    foreach ($senderIds as $senderId) {
        $stmt = $db->prepare("
            INSERT INTO sender_ids (client_id, sender_id, approval_status, status)
            VALUES (?, ?, 'approved', 'active')
        ");
        $stmt->execute([$adminClientId, $senderId]);
        echo "   ✅ $senderId (approved)\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3: Display Results
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "STEP 3: Setup Results\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

try {
    $db = getDb();
    
    // Display admin details
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║              ADMIN ACCOUNT CREDENTIALS                     ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📋 ACCOUNT DETAILS:\n";
    echo "   Name: $adminName\n";
    echo "   Email: $adminEmail\n";
    echo "   Phone: $adminPhone\n";
    echo "   Balance: 10,000 SMS units\n";
    echo "   Status: Active\n\n";
    
    echo "🔑 API KEY (Save securely!):\n";
    echo "   " . $apiKey['key'] . "\n\n";
    
    echo "📤 APPROVED SENDER IDS:\n";
    foreach ($senderIds as $id) {
        echo "   - $id\n";
    }
    
    echo "\n";
    
    // Database stats
    echo "📊 DATABASE STATISTICS:\n";
    $tables = [
        'clients', 'client_api_keys', 'wallet_accounts', 'wallet_ledger',
        'sms_requests', 'sms_queue', 'sender_ids', 'pricing_plans',
        'sms_attempts', 'provider_sms_logs'
    ];
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "   - $table: $count records\n";
        } catch (Exception $e) {
            echo "   - $table: N/A\n";
        }
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "   ⚠️  Could not retrieve stats: " . $e->getMessage() . "\n";
}

// Step 4: Next Steps
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "NEXT STEPS\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

echo "1️⃣  UPDATE .env2 with TalkSasa API key:\n";
echo "   TALKSASA_API_KEY=your_api_key_here\n\n";

echo "2️⃣  SET UP CRON WORKER (add to crontab):\n";
echo "   * * * * * php " . __DIR__ . "/../src/Worker.php >> /path/to/logs/worker.log 2>&1\n\n";

echo "3️⃣  ACCESS DASHBOARD:\n";
echo "   URL: http://localhost/clayon/pages/login.html\n";
echo "   Use the API key above to login\n\n";

echo "4️⃣  OPTIONAL - TEST API:\n";
echo "   php " . __DIR__ . "/test-api.php \"" . $apiKey['key'] . "\"\n\n";

echo "✅ SETUP COMPLETE!\n\n";
