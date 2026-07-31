<?php
require __DIR__ . '/../db.php';
$db = getClayonDb();
$stmt = $db->prepare("SELECT id, client_id, request_reference, recipient, sender_id, status, provider_message_id, created_at FROM sms_requests ORDER BY id DESC LIMIT 5");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
