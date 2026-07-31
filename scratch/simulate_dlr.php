<?php
/**
 * Manual DLR Simulation
 * Proves if dlr.php works.
 */

$url = "http://localhost/mlm/clayon/callback/dlr.php";
$payload = json_encode([
    "queue_uid" => "fd4576a4-404a-4c81-befc-debbc2afd801",
    "status" => "Delivered",
    "message" => "Test Handset Confirmation"
]);

echo "Simulating DLR for ID: fd4576a4-404a-4c81-befc-debbc2afd801\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $result\n";
