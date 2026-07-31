<?php
/**
 * clayon/setup/create-client.php
 * 
 * Create a new client and API key
 * Usage: php clayon/setup/create-client.php "Client Name" "email@example.com"
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Auth.php';
require_once __DIR__ . '/../config/Config.php';

if ($argc < 3) {
    echo "Usage: php create-client.php \"Client Name\" \"email@example.com\"\n";
    exit(1);
}

$name = $argv[1];
$email = $argv[2];

echo "🔄 Creating new client...\n";

try {
    $db = getDb();
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo "❌ Error: Email already exists\n";
        exit(1);
    }
    
    $db->beginTransaction();
    
    // Create client
    $stmt = $db->prepare("
        INSERT INTO clients (name, email, phone, password_hash, status)
        VALUES (?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$name, $email, '', hash('sha256', 'temp')]);
    $clientId = $db->lastInsertId();
    
    // Initialize wallet
    $stmt = $db->prepare("INSERT INTO wallet_accounts (client_id, balance_units) VALUES (?, 0)");
    $stmt->execute([$clientId]);
    
    // Create API key
    $apiKey = Auth::generateApiKey($clientId);
    
    $db->commit();
    
    echo "✅ Client created successfully!\n\n";
    echo "📋 Details:\n";
    echo "  Client ID: $clientId\n";
    echo "  Name: $name\n";
    echo "  Email: $email\n";
    echo "  Status: active\n\n";
    echo "🔑 API Key:\n";
    echo "  " . $apiKey['key'] . "\n\n";
    echo "⚠️  Store this API key securely - it won't be shown again!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
