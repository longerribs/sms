<?php
/**
 * sms/api/actions/revoke_key.php
 * 
 * Revokes a specific API key belonging to the authenticated client.
 */
require_once __DIR__ . '/../../auth_bridge.php';
require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../developer.php');
    exit;
}

$keyId = isset($_POST['key_id']) ? (int)$_POST['key_id'] : 0;

if ($keyId <= 0) {
    header('Location: ../../developer.php?status=error&msg=Invalid API Key ID');
    exit;
}

// Revocation is intentionally disabled on the client side for now.
// Admin-only revocation will be implemented later.
// The original implementation was:
// $db = getClayonDb();
// $clientId = $_SESSION['CLAYON_CLIENT_ID'];
// $stmt = $db->prepare("UPDATE client_api_keys SET status = 'revoked' WHERE id = ? AND client_id = ?");
// $stmt->execute([$keyId, $clientId]);

header('Location: ../../developer.php?status=error&msg=Revocation is temporarily disabled for clients. Please contact an admin if a key has been exposed.');
exit;
