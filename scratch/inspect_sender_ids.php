<?php
require __DIR__ . '/../db.php';
$db = getClayonDb();
$stmt = $db->prepare("SELECT id, client_id, sender_id, approval_status, status FROM sender_ids WHERE sender_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute(['TALKSASA']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
