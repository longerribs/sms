<?php
require __DIR__ . '/../db.php';
$db = getClayonDb();
$stmt = $db->prepare("SELECT id, client_id, key_prefix, status, plain_api_key, key_hash FROM client_api_keys ORDER BY created_at DESC LIMIT 20");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
