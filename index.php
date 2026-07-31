<?php
/**
 * clayon/index.php
 * 
 * Dashboard for Clayon SMS Reseller Platform
 */

require_once __DIR__ . '/auth_bridge.php';

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/src/ThemeManager.php';

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

// Initialize theme manager
$themeManager = new ThemeManager($clientId);
$cssVariables = $themeManager->generateCSSVariables();

// Fetch balance
$stmt = $db->prepare("SELECT balance_units FROM wallet_accounts WHERE client_id = ?");
$stmt->execute([$clientId]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $wallet ? (float) $wallet['balance_units'] : 0;

// Fetch total sent
$stmt = $db->prepare("SELECT COUNT(*) FROM sms_requests WHERE client_id = ? AND status IN ('accepted', 'completed')");
$stmt->execute([$clientId]);
$totalSent = $stmt->fetchColumn();

// Fetch today's sent
$stmt = $db->prepare("SELECT COUNT(*) FROM sms_requests WHERE client_id = ? AND status IN ('accepted', 'completed') AND DATE(created_at) = CURDATE()");
$stmt->execute([$clientId]);
$todaySent = $stmt->fetchColumn();

// Fetch recent activity
$stmt = $db->prepare("SELECT * FROM wallet_ledger WHERE client_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$clientId]);
$recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch last 7 days SMS volume for chart
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM sms_requests 
    WHERE client_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
");
$stmt->execute([$clientId]);
$chartRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = [];
$dateMap = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($date));
    $dateMap[$date] = 0;
}

foreach ($chartRows as $row) {
    if (isset($dateMap[$row['date']])) {
        $dateMap[$row['date']] = (int) $row['count'];
    }
}
$chartData = array_values($dateMap);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Clayon</title>

    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Dynamic Theme CSS -->
    <style id="theme-dynamic-css">
        <?php echo $cssVariables; ?>
    </style>
</head>

<body>
    <div class="aurora">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <?php include 'includes/header.php'; ?>

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if (isset($_GET['welcome'])): ?>
            <div class="glass-card" style="margin-bottom: 2rem; border-color: var(--accent-success); background: rgba(16, 185, 129, 0.05);">
                <h3 style="color: var(--accent-success); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-magic"></i> Welcome to Clayon SMS SaaS!
                </h3>
                <p style="margin-top: 0.5rem; color: var(--text-muted);">
                    Your account has been set up successfully. We have auto-generated your first API key.
                </p>
                <div style="margin-top: 1rem; display: flex; gap: 1rem; align-items: center;">
                    <a href="developer.php" class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                        <i class="fas fa-code"></i> View API Key & Documentation
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <header style="margin-bottom: 2rem;">
            <?php
            $hour = date('H');
            if ($hour < 12) {
                $greeting = "Good morning";
            } elseif ($hour < 18) {
                $greeting = "Good afternoon";
            } else {
                $greeting = "Good evening";
            }
            ?>
            <h1><?php echo $greeting; ?>,
                <?php echo htmlspecialchars($_SESSION['CLAYON_CLIENT_NAME'] ?? 'Valued Client'); ?></h1>
            <p style="color: var(--text-muted);">Manage Your SMS,Buy,Send to your Teams</p>
        </header>

        <div class="dashboard-grid">
            <div class="glass-card stat-card">
                <span class="stat-label">Available Balance</span>
                <span class="stat-value" style="color: var(--primary);"><?php echo number_format($balance, 2); ?> <span
                        style="font-size: 1rem; opacity: 0.6;">Units</span></span>
            </div>

            <div class="glass-card stat-card">
                <span class="stat-label">Messages Sent (Total)</span>
                <span class="stat-value"><?php echo number_format($totalSent); ?></span>
            </div>

            <div class="glass-card stat-card">
                <span class="stat-label">Sent Today</span>
                <span class="stat-value"
                    style="color: var(--accent-success);"><?php echo number_format($todaySent); ?></span>
            </div>
        </div>

        <!-- Dashboard Widgets -->
        <div class="dashboard-grid" style="margin-top: 0rem;">
            <div class="glass-card">
                <h3>Recent Activity</h3>
                <div style="margin-top: 1.5rem;">
                    <?php if (empty($recentActivities)): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 2rem;">No recent
                            activity found.</p>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach ($recentActivities as $activity): ?>
                                <?php
                                $isTopup = $activity['entry_type'] === 'credit';
                                $icon = $isTopup ? 'fa-arrow-down' : 'fa-paper-plane';
                                $color = $isTopup ? 'var(--accent-success)' : 'var(--primary)';
                                $title = $isTopup ? 'Account Top-up' : 'SMS Sent';
                                $units = $isTopup ? '+' . number_format($activity['units'], 2) : '-' . number_format($activity['units'], 2);
                                $date = date('M d, g:i A', strtotime($activity['created_at']));
                                ?>
                                <li
                                    style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div
                                            style="width: 40px; height: 40px; border-radius: 8px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: <?php echo $color; ?>;">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 500; font-size: 0.95rem;"><?php echo $title; ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo $date; ?></div>
                                        </div>
                                    </div>
                                    <div style="font-weight: 600; color: <?php echo $color; ?>;">
                                        <?php echo $units; ?> Units
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="glass-card">
                <h3>Quick Actions</h3>
                <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                    <a href="send.php" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Send SMS
                    </a>
                    <a href="buy.php" class="btn-primary"
                        style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border);">
                        <i class="fas fa-wallet"></i> Top up Wallet
                    </a>

                    <div style="margin-top: 1rem; height: 120px;">
                        <canvas id="volumeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Theme Center -->
    <?php include 'includes/theme-center.php'; ?>

    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('volumeChart').getContext('2d');

            // Get CSS variable color
            const getVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'SMS Volume',
                        data: <?php echo json_encode($chartData); ?>,
                        borderColor: getVar('--primary'),
                        backgroundColor: getVar('--primary-glow'),
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { display: false },
                        y: {
                            display: false,
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>