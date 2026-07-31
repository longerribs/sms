<?php
/**
 * clayon/setup/init-db.php
 * 
 * Initialize database schema
 * Run once: php clayon/setup/init-db.php
 */

require_once __DIR__ . '/../config/Database.php';

echo "🔄 Initializing Clayon database schema...\n\n";

try {
    Database::getInstance()->initializeSchema();
    echo "✅ Database schema initialized successfully!\n\n";
    
    // Create default pricing plan
    $db = getDb();
    
    // Check if default plan exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM pricing_plans WHERE plan_name = 'Default'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        $stmt = $db->prepare("
            INSERT INTO pricing_plans (plan_name, provider_markup_type, markup_value, min_topup, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute(['Default', 'percentage', 25.0, 100.0]);
        echo "✅ Default pricing plan created\n";
    }
    
    echo "\n📊 Database Status:\n";
    $tables = ['clients', 'client_api_keys', 'sms_requests', 'sms_queue', 'wallet_accounts', 'mpesa_transactions'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM $table");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "  - $table: $count records\n";
    }
    
    echo "\n✅ Initialization complete!\n";
    echo "\n📖 Next steps:\n";
    echo "  1. Set up your TalkSasa API key in .env\n";
    echo "  2. Create your first client and API key (see SETUP_GUIDE.md)\n";
    echo "  3. Set up the cron worker for SMS queue processing\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
