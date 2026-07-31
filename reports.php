<?php
/**
 * clayon/reports.php
 * 
 * Reports Tab - SMS Delivery & Wallet Transactions
 */

require_once __DIR__ . '/auth_bridge.php';

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/src/ThemeManager.php';

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

// Initialize theme manager
$themeManager = new ThemeManager($clientId);
$cssVariables = $themeManager->generateCSSVariables();

$tab = $_GET['tab'] ?? 'sms'; // 'sms' or 'wallet'
$period = $_GET['period'] ?? '7'; // 1, 7, 30 days

// Date filter
$dateFrom = date('Y-m-d', strtotime("-$period days"));
$dateTo = date('Y-m-d');

// ════ SMS REPORT ════
$smsStats = [];
$stmt = $db->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM sms_requests 
    WHERE client_id = ? AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute([$clientId, $dateFrom, $dateTo]);
$smsStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// SMS breakdown
$pending = $smsStats['pending'] ?? 0;
$accepted = $smsStats['accepted'] ?? 0;
$completed = $smsStats['completed'] ?? 0;
$failed = $smsStats['failed'] ?? 0;
$totalSms = array_sum($smsStats);

// Recent SMS list
$stmt = $db->prepare("
    SELECT id, request_reference, recipient, estimated_segments, 
           COALESCE(NULLIF(delivery_status, 'pending'), status) AS status, 
           delivery_status, provider_message_id, created_at
    FROM sms_requests 
    WHERE client_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$clientId, $dateFrom, $dateTo]);
$smsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ════ WALLET REPORT ════
$walletStats = [];
$stmt = $db->prepare("
    SELECT 
        entry_type,
        COUNT(*) as count,
        SUM(units) as total
    FROM wallet_ledger 
    WHERE client_id = ? AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY entry_type
");
$stmt->execute([$clientId, $dateFrom, $dateTo]);
$walletStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Wallet breakdown
$walletBreakdown = [];
$totalUnits = 0;
foreach ($walletStats as $row) {
    $walletBreakdown[$row['entry_type']] = [
        'count' => $row['count'],
        'total' => (float)$row['total']
    ];
    if ($row['entry_type'] !== 'reserved') {
        $totalUnits += ($row['entry_type'] === 'credit' ? 1 : -1) * (float)$row['total'];
    }
}

// Recent transactions
$stmt = $db->prepare("
    SELECT id, entry_type, units, reference, note, created_at
    FROM wallet_ledger 
    WHERE client_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$clientId, $dateFrom, $dateTo]);
$walletList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Current balance
$stmt = $db->prepare("SELECT balance_units FROM wallet_accounts WHERE client_id = ?");
$stmt->execute([$clientId]);
$balance = (float)($stmt->fetchColumn() ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Clayon</title>
    
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Dynamic Theme CSS -->
    <style id="theme-dynamic-css">
        <?php echo $cssVariables; ?>
    </style>
    <style>
        .tab-nav {
            display: flex;
            gap: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 2rem;
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .tab-btn:hover {
            color: var(--text-primary);
        }
        
        .period-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        .period-btn {
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-primary);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .period-btn.active {
            background: var(--primary);
            border-color: var(--primary);
        }
        .period-btn:hover {
            border-color: var(--primary);
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        .stat-box-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        .stat-box-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-box-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .report-table th {
            background: rgba(255,255,255,0.05);
            padding: 1rem;
            text-align: left;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .report-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .report-table tr:hover {
            background: rgba(255,255,255,0.02);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: rgba(245,158,11,0.15); color: var(--accent-warning); }
        .status-accepted { background: rgba(59,130,246,0.15); color: var(--chart-3); }
        .status-completed { background: rgba(16,185,129,0.15); color: var(--accent-success); }
        .status-failed { background: rgba(239,68,68,0.15); color: var(--accent-error); }
        .status-credit { background: rgba(16,185,129,0.15); color: var(--accent-success); }
        .status-debit { background: rgba(239,68,68,0.15); color: var(--accent-error); }
        .status-reserved { background: rgba(156,163,175,0.15); color: var(--text-muted); }
        .status-refund { background: rgba(168,85,247,0.15); color: var(--chart-2); }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
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
        <header style="margin-bottom: 2rem;">
            <h1>Reports & Analytics</h1>
            <p style="color: var(--text-muted);">Track your SMS delivery and wallet activity</p>
        </header>

        <!-- Period Selector -->
        <div class="period-selector">
            <a href="?tab=<?php echo $tab; ?>&period=1" class="period-btn <?php echo $period === '1' ? 'active' : ''; ?>">Last 24h</a>
            <a href="?tab=<?php echo $tab; ?>&period=7" class="period-btn <?php echo $period === '7' ? 'active' : ''; ?>">Last 7d</a>
            <a href="?tab=<?php echo $tab; ?>&period=30" class="period-btn <?php echo $period === '30' ? 'active' : ''; ?>">Last 30d</a>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-btn <?php echo $tab === 'sms' ? 'active' : ''; ?>" onclick="switchTab('sms')">
                <i class="fas fa-paper-plane"></i> SMS Delivery
            </button>
            <button class="tab-btn <?php echo $tab === 'wallet' ? 'active' : ''; ?>" onclick="switchTab('wallet')">
                <i class="fas fa-wallet"></i> Wallet Transactions
            </button>
        </div>

        <!-- ════ SMS REPORT ════ -->
        <?php if ($tab === 'sms'): ?>
        <div class="glass-card">
            <h2 style="margin-bottom: 1.5rem;">SMS Delivery Report</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">
                Period: <?php echo date('M d, Y', strtotime($dateFrom)); ?> — <?php echo date('M d, Y', strtotime($dateTo)); ?>
            </p>

            <!-- Stats Grid -->
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-box-label">Total Sent</div>
                    <div class="stat-box-value"><?php echo number_format($totalSms); ?></div>
                    <div class="stat-box-sub">messages</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: var(--accent-success);">Completed</div>
                    <div class="stat-box-value" style="color: var(--accent-success);"><?php echo number_format($completed); ?></div>
                    <div class="stat-box-sub"><?php echo $totalSms > 0 ? round(($completed/$totalSms)*100, 1) : 0; ?>%</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: var(--chart-3);">Accepted</div>
                    <div class="stat-box-value" style="color: var(--chart-3);"><?php echo number_format($accepted); ?></div>
                    <div class="stat-box-sub"><?php echo $totalSms > 0 ? round(($accepted/$totalSms)*100, 1) : 0; ?>%</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: var(--accent-error);">Failed</div>
                    <div class="stat-box-value" style="color: var(--accent-error);"><?php echo number_format($failed); ?></div>
                    <div class="stat-box-sub"><?php echo $totalSms > 0 ? round(($failed/$totalSms)*100, 1) : 0; ?>%</div>
                </div>
            </div>

            <!-- SMS Table -->
            <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Recent Messages</h3>
            <?php if (empty($smsList)): ?>
                <div class="empty-state">
                    <i class="fas fa-envelope-circle-check"></i>
                    <p>No SMS found for this period.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Recipient</th>
                                <th>Segments</th>
                                <th>Status</th>
                                <th>Provider ID</th>
                                <th>Sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($smsList as $sms): ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 0.85rem;">
                                    <?php echo substr($sms['request_reference'], 0, 12); ?>...
                                </td>
                                <td><?php echo htmlspecialchars($sms['recipient']); ?></td>
                                <td><?php echo $sms['estimated_segments']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $sms['status']; ?>">
                                        <?php echo $sms['status']; ?>
                                    </span>
                                </td>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    <?php echo $sms['provider_message_id'] ? substr($sms['provider_message_id'], 0, 10) . '...' : '—'; ?>
                                </td>
                                <td><?php echo date('M d, g:i A', strtotime($sms['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ════ WALLET REPORT ════ -->
        <?php elseif ($tab === 'wallet'): ?>
        <div class="glass-card">
            <h2 style="margin-bottom: 1.5rem;">Wallet Activity Report</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">
                Period: <?php echo date('M d, Y', strtotime($dateFrom)); ?> — <?php echo date('M d, Y', strtotime($dateTo)); ?>
            </p>

            <!-- Stats Grid -->
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-box-label">Current Balance</div>
                    <div class="stat-box-value" style="color: var(--primary);"><?php echo number_format($balance, 2); ?></div>
                    <div class="stat-box-sub">units</div>
                </div>
                <?php if (isset($walletBreakdown['credit'])): ?>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: #10b981;">Total Credits</div>
                    <div class="stat-box-value" style="color: #10b981;">
                        +<?php echo number_format($walletBreakdown['credit']['total'], 2); ?>
                    </div>
                    <div class="stat-box-sub"><?php echo $walletBreakdown['credit']['count']; ?> transactions</div>
                </div>
                <?php endif; ?>
                <?php if (isset($walletBreakdown['debit'])): ?>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: #f87171;">Total Debits</div>
                    <div class="stat-box-value" style="color: #f87171;">
                        -<?php echo number_format($walletBreakdown['debit']['total'], 2); ?>
                    </div>
                    <div class="stat-box-sub"><?php echo $walletBreakdown['debit']['count']; ?> transactions</div>
                </div>
                <?php endif; ?>
                <?php if (isset($walletBreakdown['reserved'])): ?>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: #d1d5db;">Reserved</div>
                    <div class="stat-box-value" style="color: #d1d5db;">
                        <?php echo number_format($walletBreakdown['reserved']['total'], 2); ?>
                    </div>
                    <div class="stat-box-sub">pending messages</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Wallet Table -->
            <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Transaction History</h3>
            <?php if (empty($walletList)): ?>
                <div class="empty-state">
                    <i class="fas fa-credit-card"></i>
                    <p>No transactions found for this period.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Units</th>
                                <th>Reference</th>
                                <th>Note</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($walletList as $tx): ?>
                            <tr>
                                <td>
                                    <span class="status-badge status-<?php echo $tx['entry_type']; ?>">
                                        <?php echo $tx['entry_type']; ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600; color: <?php echo in_array($tx['entry_type'], ['credit', 'refund']) ? '#10b981' : '#f87171'; ?>;">
                                    <?php echo in_array($tx['entry_type'], ['credit', 'refund']) ? '+' : '−'; ?>
                                    <?php echo number_format($tx['units'], 2); ?>
                                </td>
                                <td style="font-family: monospace; font-size: 0.85rem;">
                                    <?php echo htmlspecialchars(substr($tx['reference'] ?? 'N/A', 0, 15)); ?>
                                </td>
                                <td style="font-size: 0.85rem; max-width: 200px;">
                                    <?php echo htmlspecialchars(substr($tx['note'] ?? '', 0, 40)); ?>...
                                </td>
                                <td><?php echo date('M d, g:i A', strtotime($tx['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>

    <!-- Theme Center -->
    <?php include 'includes/theme-center.php'; ?>

    <script>
        function switchTab(tabName) {
            const period = new URLSearchParams(window.location.search).get('period') || '7';
            window.location.href = `?tab=${tabName}&period=${period}`;
        }
    </script>
</body>
</html>
