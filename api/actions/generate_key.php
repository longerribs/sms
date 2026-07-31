<?php
/**
 * sms/api/actions/generate_key.php
 * 
 * Generates a new API Key for the authenticated client.
 * Revokes any previous active keys to maintain rotation security.
 */
require_once __DIR__ . '/../../auth_bridge.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../src/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../developer.php');
    exit;
}

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

try {
    $db->beginTransaction();

    // 1. Revoke existing active keys
    $stmt = $db->prepare("UPDATE client_api_keys SET status = 'revoked' WHERE client_id = ? AND status = 'active'");
    $stmt->execute([$clientId]);

    // 2. Generate new API key
    $auth = new Auth($db);
    $plainKey = $auth->generateKey($clientId);

    // 3. Log audit event
    $stmt = $db->prepare("INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, metadata) VALUES ('client', ?, 'api_key_rotated', 'client', ?, ?)");
    $stmt->execute([$clientId, $clientId, json_encode(['ip' => $_SERVER['REMOTE_ADDR']])]);

    $db->commit();

    // 4. Save plaintext key in session to show once
    $_SESSION['CLAYON_NEW_API_KEY'] = $plainKey;

    header('Location: ../../developer.php');
    exit;
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Generate Key action error: " . $e->getMessage());
    header('Location: ../../developer.php?status=error&msg=Failed to generate API Key');
    exit;
}
