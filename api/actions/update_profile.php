<?php
/**
 * sms/api/actions/update_profile.php
 * 
 * Updates user profile details (name, email, phone).
 */
require_once __DIR__ . '/../../auth_bridge.php';
require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../profile.php');
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

if (empty($name) || empty($email) || empty($phone)) {
    header('Location: ../../profile.php?status=error&msg=All fields are required.');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../../profile.php?status=error&msg=Invalid email format.');
    exit;
}

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

try {
    // Check if email is in use by another user
    $stmt = $db->prepare("SELECT id FROM clients WHERE email = ? AND id != ? LIMIT 1");
    $stmt->execute([$email, $clientId]);
    if ($stmt->fetch()) {
        header('Location: ../../profile.php?status=error&msg=Email is already in use by another user.');
        exit;
    }

    // Normalize phone format
    $phoneClean = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phoneClean, 0, 1) === '0') {
        $phoneClean = '254' . substr($phoneClean, 1);
    }

    $db->beginTransaction();

    // Update clients table
    $stmt = $db->prepare("UPDATE clients SET name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phoneClean, $clientId]);

    // Insert log to audit_logs
    $stmt = $db->prepare("INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, metadata) VALUES ('client', ?, 'profile_update', 'client', ?, ?)");
    $stmt->execute([$clientId, $clientId, json_encode(['name' => $name, 'email' => $email, 'phone' => $phoneClean])]);

    $db->commit();

    // Update session variables
    $_SESSION['CLAYON_CLIENT_NAME'] = $name;
    $_SESSION['CLAYON_CLIENT_EMAIL'] = $email;

    header('Location: ../../profile.php?status=success&msg=Profile details updated successfully.');
    exit;
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Update profile error: " . $e->getMessage());
    header('Location: ../../profile.php?status=error&msg=Failed to update profile details.');
    exit;
}
