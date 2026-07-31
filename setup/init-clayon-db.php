<?php
/**
 * clayon/setup/init-clayon-db.php
 * 
 * Initialize Clayon database with separate schema and dummy data
 * Run once: php clayon/setup/init-clayon-db.php
 */

// Load environment from .env2 instead of .env
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Auth.php';
require_once __DIR__ . '/../config/Config.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     CLAYON DATABASE INITIALIZATION                         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = getDb();
    
    // 1. Initialize schema
    echo "📊 Initializing database schema...\n";
    Database::getInstance()->initializeSchema();
    echo "   ✅ Schema created\n\n";
    
    // 2. Create default pricing plan
    echo "💰 Creating pricing plans...\n";
    $stmt = $db->prepare("
        INSERT INTO pricing_plans (plan_name, provider_markup_type, markup_value, min_topup, status)
        VALUES (?, ?, ?, ?, 'active')
    ");
    $stmt->execute(['Default', 'percentage', 25.0, 100.0]);
    echo "   ✅ Default plan created (25% markup)\n\n";
    
    // 3. Create admin user
    echo "👤 Creating admin user...\n";
    $adminName = 'Simon Jogu';
    $adminEmail = 'simonjogu001@gmail.com';
    $adminPhone = '0711486334';
    $adminPassword = password_hash('admin@123', PASSWORD_BCRYPT); // temp password
    
    $stmt = $db->prepare("
        INSERT INTO clients (name, email, phone, password_hash, plan_id, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$adminName, $adminEmail, $adminPhone, $adminPassword, 1]);
    $adminClientId = $db->lastInsertId();
    echo "   ✅ Admin user created: $adminEmail\n";
    
    // 4. Initialize wallet for admin
    echo "💳 Setting up admin wallet...\n";
    $stmt = $db->prepare("
        INSERT INTO wallet_accounts (client_id, balance_units, reserved_units)
        VALUES (?, 10000, 0)
    ");
    $stmt->execute([$adminClientId]);
    echo "   ✅ Initial balance: 10,000 units\n";
    
    // 5. Create initial ledger entry
    $stmt = $db->prepare("
        INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note)
        VALUES (?, 'credit', ?, ?, ?)
    ");
    $stmt->execute([$adminClientId, 10000, 'INIT_ADMIN', 'Admin account initialization']);
    
    // 6. Create admin API key
    echo "🔑 Generating API key...\n";
    $apiKey = Auth::generateApiKey($adminClientId);
    echo "   ✅ API key created\n";
    echo "   📋 Key prefix: " . $apiKey['key_prefix'] . "\n\n";
    
    // 7. Create approved sender IDs
    echo "📤 Creating approved sender IDs...\n";
    $senderIds = ['CLAYON', 'ADMIN', 'TEST'];
    foreach ($senderIds as $senderId) {
        $stmt = $db->prepare("
            INSERT INTO sender_ids (client_id, sender_id, approval_status, status)
            VALUES (?, ?, 'approved', 'active')
        ");
        $stmt->execute([$adminClientId, $senderId]);
        echo "   ✅ Sender ID approved: $senderId\n";
    }
    
    echo "\n";
    
    // 8. Display summary
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║                 SETUP COMPLETE ✅                          ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📋 ADMIN ACCOUNT DETAILS:\n";
    echo "   Name: $adminName\n";
    echo "   Email: $adminEmail\n";
    echo "   Phone: $adminPhone\n";
    echo "   Balance: 10,000 SMS units\n";
    echo "   Status: Active\n\n";
    
    echo "🔑 API KEY (Save this securely!):\n";
    echo "   " . $apiKey['key'] . "\n\n";
    
    echo "📤 APPROVED SENDER IDS:\n";
    foreach ($senderIds as $id) {
        echo "   - $id\n";
    }
    
    echo "\n";
    
    // 9. Display database statistics
    echo "📊 DATABASE STATISTICS:\n";
    $tables = [
        'clients', 'client_api_keys', 'wallet_accounts', 'wallet_ledger',
        'sms_requests', 'sms_queue', 'sender_ids', 'pricing_plans'
    ];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "   - $table: $count records\n";
    }
    
    echo "\n";
    
    // 10. Next steps
    echo "📖 NEXT STEPS:\n";
    echo "   1. Set up cron worker:\n";
    echo "      * * * * * php " . __DIR__ . "/../src/Worker.php >> /path/to/logs/worker.log 2>&1\n\n";
    echo "   2. Update .env2 with your TalkSasa API key\n";
    echo "   3. Access dashboard: http://localhost/clayon/pages/login.html\n";
    echo "   4. Use the API key above to login\n\n";
    
    echo "✅ Initialization complete!\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    error_log("Init error: " . $e->getMessage());
    exit(1);
}
