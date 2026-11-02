<?php
/**
 * clayon/setup/init-db.php
 * 
 * Initialize and verify database schema for Clayon SMS Platform.
 * Run once: php setup/init-db.php
 */

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config/Database.php';

echo "=====================================================\n";
echo "🔄 Clayon SMS: Initializing Database Schema\n";
echo "=====================================================\n\n";

try {
    $dbName = clayon_env('DB_DATABASE', 'sms_clayon_sms');
    echo "Target Database: $dbName\n\n";

    echo "Executing schema import...\n";
    Database::getInstance()->initializeSchema();
    echo "✅ Database schema loaded successfully!\n\n";
    
    $db = getClayonDb();
    
    // Ensure default pricing plan exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM pricing_plans WHERE plan_name = 'Default'");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();
    
    if ($count === 0) {
        $stmt = $db->prepare("
            INSERT INTO pricing_plans (plan_name, provider_markup_type, markup_value, min_topup, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute(['Default', 'percentage', 25.0, 100.0]);
        echo "✅ Default pricing plan created (25% markup)\n";
    } else {
        echo "ℹ️ Default pricing plan already present\n";
    }
    
    echo "\n📊 Table Summary in '$dbName':\n";
    $tables = [
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
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM `$table`");
            $stmt->execute();
            $count = (int)$stmt->fetchColumn();
            printf("  [✔] %-20s : %d records\n", $table, $count);
        } catch (Exception $te) {
            printf("  [✘] %-20s : missing or inaccessible\n", $table);
        }
    }
    
    echo "\n=====================================================\n";
    echo "✅ Database initialization completed successfully!\n";
    echo "=====================================================\n\n";
    echo "📖 Next Steps:\n";
    echo "  1. Verify setup health:  php setup/verify-setup.php\n";
    echo "  2. Access Web Dashboard: " . rtrim((string)clayon_env('APP_URL', 'http://localhost/sms'), '/') . "/send.php\n";
    echo "  3. Developer Docs:       " . rtrim((string)clayon_env('APP_URL', 'http://localhost/sms'), '/') . "/developer.php\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Initialization Error: " . $e->getMessage() . "\n";
    exit(1);
}
