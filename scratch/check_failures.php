<?php
/**
 * clayon/scratch/check_failures.php
 */

require_once __DIR__ . '/../db.php';
$db = getClayonDb();

$ids = [5, 6, 8];
echo "Checking SMS attempts for Request IDs: " . implode(', ', $ids) . "\n\n";

foreach ($ids as $id) {
    $stmt = $db->prepare("SELECT * FROM sms_attempts WHERE sms_request_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$id]);
    $attempt = $stmt->fetch();
    
    if ($attempt) {
        echo "--- Request ID #$id ---\n";
        echo "HTTP Code: {$attempt['http_code']}\n";
        echo "Error Msg: {$attempt['error_message']}\n";
        echo "Response:  {$attempt['provider_response_payload']}\n\n";
    } else {
        echo "--- Request ID #$id: No attempts found ---\n\n";
    }
}
