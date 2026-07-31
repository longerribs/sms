<?php
/**
 * clayon/scratch/recover_key.php
 * 
 * emergency key generation for lost keys.
 * REVOKES ALL PREVIOUS KEYS for the client.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../src/Auth.php';

$db = getClayonDb();
$auth = new Auth($db);

$targetClientId = 2; // User requested client_id 2

try {
    // Check if client exists
    $stmt = $db->prepare("SELECT name FROM clients WHERE id = ?");
    $stmt->execute([$targetClientId]);
    $clientName = $stmt->fetchColumn();

    if (!$clientName) {
        die("Error: Client ID #$targetClientId not found in database.\n");
    }

    // 1. Revoke all existing keys for this client first
    $stmt = $db->prepare("UPDATE client_api_keys SET status = 'revoked' WHERE client_id = ? AND status = 'active'");
    $stmt->execute([$targetClientId]);
    $revokedCount = $stmt->rowCount();

    echo "Revoked $revokedCount existing keys for: $clientName (ID #$targetClientId)...\n";
    echo "Generating your single replacement API Key...\n";
    
    // 2. Generate new key
    $newKey = $auth->generateKey($targetClientId);

    echo "\n------------------------------------------------\n";
    echo "NEW ACTIVE API KEY: $newKey\n";
    echo "------------------------------------------------\n";
    echo "IMPORTANT: All your previous keys have been invalidated. Only this key will work now.\n";
    echo "Store this key safely! It is hashed in the database and cannot be shown again.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
