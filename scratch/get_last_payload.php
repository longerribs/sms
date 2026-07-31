<?php
require_once 'c:/x/htdocs/mlm/clayon/db.php';
$db = getClayonDb();
$stmt = $db->query('SELECT provider_response_payload FROM sms_attempts WHERE provider_response_payload LIKE "%message_id%" ORDER BY id DESC LIMIT 1');
$payload = $stmt->fetchColumn();
echo $payload;
