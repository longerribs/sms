<?php
require_once 'c:/x/htdocs/mlm/clayon/db.php';
$db = getClayonDb();
$stmt = $db->query('SELECT provider_response_payload FROM sms_attempts WHERE http_code BETWEEN 200 AND 299 ORDER BY id DESC LIMIT 5');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($results as $row) {
    echo $row['provider_response_payload'] . "\n---\n";
}
