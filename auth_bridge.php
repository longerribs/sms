<?php
/**
 * sms/auth_bridge.php
 *
 * Standalone session authorization check.
 * Redirects unauthorized users to the login page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for active standalone SMS platform session
if (empty($_SESSION['CLAYON_CLIENT_ID'])) {
    header('Location: login.php');
    exit;
}

// Keep connection handy
require_once __DIR__ . '/db.php';
$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];
?>
