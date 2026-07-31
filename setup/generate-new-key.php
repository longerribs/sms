<?php
/**
 * Generate a new API key for admin if the old one was lost
 */

// Load dependencies
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Config.php';
require_once __DIR__ . '/../config/Auth.php';
require_once __DIR__ . '/../config/Response.php';

// Load .env2
$envFile = __DIR__ . '/../.env2';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || !strpos($line, '=')) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get admin user
    $stmt = $db->query("SELECT id, email, phone FROM clients WHERE email = 'simonjogu001@gmail.com' LIMIT 1");
    if ($stmt->rowCount() === 0) {
        throw new Exception("Admin user not found");
    }
    
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Revoke old keys
    $db->prepare("UPDATE client_api_keys SET status = 'revoked' WHERE client_id = ?")->execute([$admin['id']]);
    
    // Generate new key
    $newKey = Auth::generateApiKey($admin['id']);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>New Admin API Key</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
                margin: 0;
            }
            .container { 
                max-width: 700px;
                margin: 0 auto;
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            h1 { color: #333; margin: 0 0 10px 0; }
            .success-badge { 
                background: #d4edda;
                color: #155724;
                padding: 10px 20px;
                border-radius: 6px;
                border: 1px solid #c3e6cb;
                margin-bottom: 30px;
            }
            .info-box {
                background: #f0f9ff;
                padding: 15px;
                border-left: 4px solid #0066cc;
                margin: 20px 0;
                border-radius: 6px;
            }
            .key-display {
                background: #1e1e1e;
                color: #00ff00;
                padding: 20px;
                border-radius: 6px;
                margin: 20px 0;
                font-family: 'Courier New', monospace;
                word-break: break-all;
                font-size: 14px;
                border: 2px solid #00ff00;
            }
            .copy-btn {
                background: #667eea;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                margin-top: 10px;
            }
            .copy-btn:hover { background: #764ba2; }
            .next-steps {
                background: #f5f5f5;
                padding: 20px;
                border-radius: 6px;
                margin-top: 30px;
            }
            .next-steps ol {
                margin: 10px 0;
                padding-left: 20px;
            }
            .next-steps li {
                margin: 8px 0;
                color: #333;
            }
            a {
                color: #667eea;
                text-decoration: none;
            }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>✅ New Admin API Key Generated</h1>
            <div class="success-badge">Previous keys have been revoked. Here's your new API key:</div>

            <div class="info-box">
                <strong>Admin Account:</strong><br>
                Email: <code><?php echo htmlspecialchars($admin['email']); ?></code><br>
                Phone: <code><?php echo htmlspecialchars($admin['phone']); ?></code>
            </div>

            <p><strong>Your New API Key:</strong></p>
            <div class="key-display" id="apiKey"><?php echo htmlspecialchars($newKey['key']); ?></div>
            <button class="copy-btn" onclick="copyKey()">📋 Copy to Clipboard</button>

            <div class="next-steps">
                <h3 style="margin-top: 0;">Next Steps:</h3>
                <ol>
                    <li>Copy the API key above (use the copy button)</li>
                    <li>Go to <a href="/mlm/clayon/pages/login.html" target="_blank">Login Page</a></li>
                    <li>Paste the API key in the password field</li>
                    <li>Click Login</li>
                </ol>
                <p style="margin-bottom: 0;">
                    <strong>🔒 Important:</strong> Keep this API key secure. Don't share it with anyone.
                </p>
            </div>

            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center;">
                <p style="margin: 0; color: #666;">
                    <a href="/mlm/clayon/QUICK_START.php" target="_blank">📖 Setup Guide</a> | 
                    <a href="/mlm/clayon/pages/login.html" target="_blank">🔐 Login</a> | 
                    <a href="/mlm/clayon/pages/dashboard.html" target="_blank">📊 Dashboard</a>
                </p>
            </div>
        </div>

        <script>
            function copyKey() {
                const keyElement = document.getElementById('apiKey');
                const text = keyElement.textContent;
                navigator.clipboard.writeText(text).then(() => {
                    alert('API Key copied to clipboard!');
                    window.location.href = '/mlm/clayon/pages/login.html';
                });
            }
        </script>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    die();
}
?>
