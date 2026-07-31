<?php
/**
 * QUICK_START.php
 *
 * Browser-friendly setup guide for the SMS reseller platform.
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clayon Quick Start</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111827; color: #f9fafb; padding: 24px; }
        .container { max-width: 900px; margin: 0 auto; background: #1f2937; padding: 24px; border-radius: 10px; }
        h1, h2 { color: #60a5fa; }
        .card { background: #111827; padding: 16px; border-left: 4px solid #34d399; margin: 16px 0; border-radius: 6px; }
        code, pre { background: #0f172a; padding: 8px 10px; border-radius: 4px; display: block; overflow-x: auto; }
        a { color: #93c5fd; }
        .warning { color: #fbbf24; }
    </style>
</head>
<body>
<div class="container">
    <h1>Clayon Quick Start</h1>

    <div class="card">
        <p>This project runs from the current workspace root and uses a separate <strong>.env2</strong> file for environment configuration.</p>
        <ul>
            <li>Database: <strong>clayon_sms</strong></li>
            <li>Admin email: <strong>simonjogu001@gmail.com</strong></li>
            <li>Initial balance: <strong>10,000 SMS units</strong></li>
            <li>Billing model: units are reserved first and debited only after provider confirmation</li>
        </ul>
    </div>

    <h2>Setup steps</h2>
    <div class="card">
        <p>1. Run the full setup script:</p>
        <pre>php setup/run-all-setup.php</pre>
        <p>This creates the database, schema, admin user, pricing plans, and API key.</p>
    </div>

    <div class="card">
        <p>2. Configure <strong>.env2</strong>:</p>
        <pre>DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=clayon_sms
DB_USERNAME=root
DB_PASSWORD=
TALKSASA_API_KEY=your_api_key_here
APP_URL=http://localhost/sms</pre>
    </div>

    <div class="card">
        <p>3. Run the worker:</p>
        <pre>* * * * * php /full/path/to/sms/src/Worker.php >> /path/to/logs/worker.log 2>&1</pre>
        <p class="warning">Replace the path with the real location of this project.</p>
    </div>

    <h2>Open the app</h2>
    <div class="card">
        <ul>
            <li><a href="/sms/pages/login.html">Login page</a></li>
            <li><a href="/sms/pages/dashboard.html">Dashboard</a></li>
            <li><a href="/sms/admin/dashboard.php">Admin dashboard</a></li>
        </ul>
    </div>

    <h2>API example</h2>
    <div class="card">
        <pre>curl -X POST http://localhost/sms/api/send \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"recipient":"+254712345678","message":"Hello"}'</pre>
        <p>Note: the current gateway defaults the sender ID to <strong>TALKSASA</strong> when omitted.</p>
    </div>
</div>
</body>
</html>
