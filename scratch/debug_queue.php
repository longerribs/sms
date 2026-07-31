<?php
/**
 * clayon/scratch/debug_queue.php
 */

require_once __DIR__ . '/../db.php';
$db = getClayonDb();

echo "Checking sms_queue content...\n";
$stmt = $db->query("SELECT * FROM sms_queue");
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo "Queue is empty.\n";
} else {
    echo "ID | RequestID | Status | NextAttempt | LockedBy\n";
    foreach ($rows as $row) {
        echo "{$row['id']} | {$row['sms_request_id']} | {$row['status']} | {$row['next_attempt_at']} | {$row['locked_by']}\n";
    }
}

echo "\nChecking CURRENT_TIMESTAMP vs PHP date()...\n";
$dbTime = $db->query("SELECT CURRENT_TIMESTAMP")->fetchColumn();
$phpTime = date('Y-m-d H:i:s');

echo "DB Time:  $dbTime\n";
echo "PHP Time: $phpTime\n";
