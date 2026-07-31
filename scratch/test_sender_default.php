<?php
require __DIR__ . '/../config/Config.php';
require __DIR__ . '/../config/Validator.php';
$data = ['recipient' => '+254711486334', 'message' => 'Hello from test'];
$valid = Validator::validateSmsRequest($data);
echo 'valid=' . ($valid ? 'true' : 'false') . PHP_EOL;
echo 'sender_id=' . ($data['sender_id'] ?? 'null') . PHP_EOL;
