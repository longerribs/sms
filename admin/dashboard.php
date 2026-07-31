<?php
/**
 * clayon/admin/dashboard.php
 * 
 * Admin dashboard for Clayon
 */

require_once __DIR__ . '/../bootstrap.php';

// For now, just a simple HTML page
// In production, you'd implement authentication for admins
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clayon - Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; }
        .card { background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin: 10px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Clayon Admin Dashboard</h1>
        
        <div class="card">
            <h3>Queue Status</h3>
            <p>Processing SMS queue. Check logs for details.</p>
        </div>
        
        <div class="card">
            <h3>System Configuration</h3>
            <ul>
                <li>Check .env for API keys and settings</li>
                <li>Run schema initialization: <code>php /path/to/clayon/bootstrap.php</code></li>
                <li>Set up cron worker: <code>* * * * * php /path/to/clayon/src/Worker.php</code></li>
            </ul>
        </div>
    </div>
</body>
</html>
