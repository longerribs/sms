<?php
/**
 * clayon/setup/get-admin-key.php
 * 
 * Initial setup script to generate an Admin API key.
 * Security: This file should be deleted or protected after use.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../src/Auth.php';

$db = getClayonDb();
$auth = new Auth($db);

echo "<h2>Clayon Admin Setup</h2>";

try {
    // 1. Check if an admin client exists
    $stmt = $db->prepare("SELECT id FROM clients WHERE email = 'admin@clayon.com'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        // Create default admin client
        $stmt = $db->prepare("INSERT INTO clients (name, email, phone, status) VALUES ('System Admin', 'admin@clayon.com', '0700000000', 'active')");
        $stmt->execute();
        $adminId = $db->lastInsertId();
        
        // Initialize wallet
        $db->prepare("INSERT INTO wallet_accounts (client_id, balance_units) VALUES (?, 1000.00)")->execute([$adminId]);
        
        echo "<p style='color: green;'>Admin client created successfully.</p>";
    } else {
        $adminId = $admin['id'];
        echo "<p>Admin client already exists.</p>";
    }

    // 2. Generate a new key for the admin
    $newKey = $auth->generateKey($adminId);

    echo "<div style='background: #f4f4f4; padding: 20px; border: 1px solid #ccc; font-family: monospace;'>";
    echo "<strong>YOUR ADMIN API KEY:</strong><br><br>";
    echo "<span style='font-size: 1.2em; color: #d63384;'>" . htmlspecialchars($newKey) . "</span><br><br>";
    echo "<small>Copy this key now. It will not be shown again in plain text.</small>";
    echo "</div>";

    echo "<p><strong>Usage Example:</strong></p>";
    echo "<pre>Authorization: Bearer " . $newKey . "</pre>";
    echo "<p><a href='../login.php'>Go to Login</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error during setup: " . $e->getMessage() . "</p>";
}

echo "<hr><p style='color: #666;'>Security Note: Please delete the <code>clayon/setup/</code> folder after completing your setup.</p>";
