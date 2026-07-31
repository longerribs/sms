<?php
/**
 * sms/api/actions/change_password.php
 * 
 * Securely changes the client account password.
 */
require_once __DIR__ . '/../../auth_bridge.php';
require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../profile.php');
    exit;
}

$currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    header('Location: ../../profile.php?status=error&msg=All password fields are required.');
    exit;
}

if (strlen($newPassword) < 8) {
    header('Location: ../../profile.php?status=error&msg=New password must be at least 8 characters long.');
    exit;
}

if ($newPassword !== $confirmPassword) {
    header('Location: ../../profile.php?status=error&msg=New passwords do not match.');
    exit;
}

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

try {
    // 1. Fetch current hash
    $stmt = $db->prepare("SELECT password_hash FROM clients WHERE id = ? LIMIT 1");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client || !password_verify($currentPassword, $client['password_hash'])) {
        header('Location: ../../profile.php?status=error&msg=Incorrect current password.');
        exit;
    }

    $db->beginTransaction();

    // 2. Hash and update
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE clients SET password_hash = ? WHERE id = ?");
    $stmt->execute([$newHash, $clientId]);

    // 3. Insert audit log
    $stmt = $db->prepare("INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, metadata) VALUES ('client', ?, 'password_change', 'client', ?, ?)");
    $stmt->execute([$clientId, $clientId, json_encode(['ip' => $_SERVER['REMOTE_ADDR']])]);

    $db->commit();

    header('Location: ../../profile.php?status=success&msg=Password updated successfully.');
    exit;
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Change password error: " . $e->getMessage());
    header('Location: ../../profile.php?status=error&msg=Failed to update password.');
    exit;
}
