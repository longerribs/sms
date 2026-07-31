<?php
require_once 'c:/x/htdocs/mlm/clayon/db.php';
$db = getClayonDb();
echo "--- delivery_reports ---\n";
$stmt = $db->query('SELECT * FROM delivery_reports');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- sms_requests (latest) ---\n";
$stmt = $db->query('SELECT id, provider_message_id, delivery_status, status FROM sms_requests ORDER BY id DESC LIMIT 1');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
