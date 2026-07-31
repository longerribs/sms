<?php
require_once __DIR__ . '/../db.php';
$db = getClayonDb();

echo "--- Clients ---\n";
$stmt = $db->query("SELECT id, name, email FROM clients LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n--- Wallet Accounts ---\n";
$stmt = $db->query("SELECT * FROM wallet_accounts LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
