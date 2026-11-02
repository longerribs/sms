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

// ════ CLIENT PRICING & BOOKKEEPING RATE ════
$stmt = $db->prepare("
    SELECT c.plan_id, p.plan_name, p.provider_markup_type, p.markup_value 
    FROM clients c
    LEFT JOIN pricing_plans p ON c.plan_id = p.id
    WHERE c.id = ?
");
$stmt->execute([$clientId]);
$clientPlan = $stmt->fetch(PDO::FETCH_ASSOC);

$markupValue = (float)($clientPlan['markup_value'] ?? 0);
$markupType  = $clientPlan['provider_markup_type'] ?? 'percentage';

$stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'PROVIDER_UNITS_PER_KES'");
$stmt->execute();
$providerRate = (float)($stmt->fetchColumn() ?: 2.0);

// Client rate: Units received per 1 KES
if ($markupType === 'percentage') {
    $clientRate = $providerRate / (1 + ($markupValue / 100));
} else {
    $clientRate = $providerRate - $markupValue;
}
$clientRate = max(0.1, $clientRate);
$costPerUnitKes = 1 / $clientRate; // Cost in KES per 1 Unit

// ════ SMS REPORT & DELIVERY AGGREGATION ════
$stmt = $db->prepare("
    SELECT 
        status,
        delivery_status,
        COUNT(*) as count,
        COALESCE(SUM(COALESCE(NULLIF(final_cost, 0), estimated_cost)), 0) as units_used,
        COALESCE(SUM(estimated_segments), 0) as segments_count
    FROM sms_requests 
    WHERE client_id = ? AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY status, delivery_status
");
$stmt->execute([$clientId, $dateFrom, $dateTo]);
$smsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalSms = 0;
$deliveredCount = 0;
$failedCount = 0;
$acceptedCount = 0;
$pendingCount = 0;
$totalUnitsUsed = 0.0;
$totalSegments = 0;

foreach ($smsRows as $row) {
    $c = (int)$row['count'];
    $totalSms += $c;
    $totalUnitsUsed += (float)$row['units_used'];
    $totalSegments += (int)$row['segments_count'];
    
    $deliv = strtolower(trim((string)$row['delivery_status']));
    $st = strtolower(trim((string)$row['status']));
    
    if ($deliv === 'delivered' || $st === 'completed') {
        $deliveredCount += $c;
    } elseif ($deliv === 'failed' || $deliv === 'undelivered' || $st === 'failed') {
        $failedCount += $c;
    } elseif ($st === 'accepted' || $deliv === 'accepted') {
        $acceptedCount += $c;
    } else {
        $pendingCount += $c;
    }
}

// Delivery Rate %
$deliveryRate = $totalSms > 0 ? round(($deliveredCount / $totalSms) * 100, 1) : 0.0;

// Amount spent on SMS dispatches in KES
$amountSpentSms = $totalUnitsUsed * $costPerUnitKes;

// Recent SMS list with units and cost
$stmt = $db->prepare("
    SELECT id, request_reference, recipient, estimated_segments, 
           estimated_cost, final_cost,
           COALESCE(NULLIF(delivery_status, 'pending'), status) AS status, 
           delivery_status, provider_message_id, created_at
    FROM sms_requests 
    WHERE client_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 30
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
        'count' => (int)$row['count'],
        'total' => (float)$row['total']
    ];
    if ($row['entry_type'] !== 'reserved') {
        $totalUnits += ($row['entry_type'] === 'credit' ? 1 : -1) * (float)$row['total'];
    }
}

$debitUnits = (float)($walletBreakdown['debit']['total'] ?? 0);
$creditUnits = (float)($walletBreakdown['credit']['total'] ?? 0);
$reservedUnits = (float)($walletBreakdown['reserved']['total'] ?? 0);
$amountSpentWallet = $debitUnits * $costPerUnitKes;
$amountDepositedWallet = $creditUnits * $costPerUnitKes;

// Actual cash bought from transactions table (M-Pesa payments)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM mpesa_transactions
    WHERE client_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$clientId, $dateFrom, $dateTo]);
$actualCashBought = (float)$stmt->fetchColumn();
$displayAmountBought = $actualCashBought > 0 ? $actualCashBought : $amountDepositedWallet;

// Recent transactions
$stmt = $db->prepare("
    SELECT id, entry_type, units, reference, note, created_at
    FROM wallet_ledger 
    WHERE client_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 30
");
$stmt->execute([$clientId, $dateFrom, $dateTo]);
$walletList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Current balance
$stmt = $db->prepare("SELECT balance_units FROM wallet_accounts WHERE client_id = ?");
$stmt->execute([$clientId]);
$balance = (float)($stmt->fetchColumn() ?? 0);
$balanceKes = $balance * $costPerUnitKes;
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
            <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div class="stat-box">
                    <div class="stat-box-label">Total Sent</div>
                    <div class="stat-box-value"><?php echo number_format($totalSms); ?></div>
                    <div class="stat-box-sub"><?php echo number_format($totalSegments); ?> total segments</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: var(--primary);">Delivery Rate</div>
                    <div class="stat-box-value" style="color: <?php echo $deliveryRate >= 80 ? 'var(--accent-success)' : ($deliveryRate >= 50 ? 'var(--accent-warning)' : 'var(--accent-error)'); ?>;">
                        <?php echo $deliveryRate; ?>%
                    </div>
                    <div class="stat-box-sub"><?php echo number_format($deliveredCount); ?> of <?php echo number_format($totalSms); ?> delivered</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: #60a5fa;">Units Used</div>
                    <div class="stat-box-value" style="color: #60a5fa;">
                        <?php echo number_format($totalUnitsUsed, 2); ?>
                    </div>
                    <div class="stat-box-sub">SMS credits consumed</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: var(--accent-success);">Delivered</div>
                    <div class="stat-box-value" style="color: var(--accent-success);"><?php echo number_format($deliveredCount); ?></div>
                    <div class="stat-box-sub"><?php echo $totalSms > 0 ? round(($deliveredCount/$totalSms)*100, 1) : 0; ?>% success</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: var(--accent-error);">Failed</div>
                    <div class="stat-box-value" style="color: var(--accent-error);"><?php echo number_format($failedCount); ?></div>
                    <div class="stat-box-sub"><?php echo $totalSms > 0 ? round(($failedCount/$totalSms)*100, 1) : 0; ?>% failed</div>
                </div>
            </div>

            <!-- Delivery Performance & Bookkeeping Summary Card -->
            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <span style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;">Delivery Reliability & Consumption Progress</span>
                    </div>
                    <span style="font-weight: 700; color: <?php echo $deliveryRate >= 80 ? 'var(--accent-success)' : ($deliveryRate >= 50 ? 'var(--accent-warning)' : 'var(--accent-error)'); ?>; font-size: 1.05rem;">
                        <?php echo $deliveryRate; ?>% Delivery Success
                    </span>
                </div>
                <div style="height: 10px; width: 100%; background: rgba(255,255,255,0.06); border-radius: 5px; overflow: hidden; display: flex;">
                    <div style="height: 100%; width: <?php echo $deliveryRate; ?>%; background: linear-gradient(90deg, var(--primary), var(--accent-success));" title="Delivered: <?php echo $deliveryRate; ?>%"></div>
                    <?php if ($totalSms > 0 && $acceptedCount > 0): ?>
                    <div style="height: 100%; width: <?php echo round(($acceptedCount/$totalSms)*100, 1); ?>%; background: var(--chart-3);" title="Accepted / In Flight"></div>
                    <?php endif; ?>
                    <?php if ($totalSms > 0 && $failedCount > 0): ?>
                    <div style="height: 100%; width: <?php echo round(($failedCount/$totalSms)*100, 1); ?>%; background: var(--accent-error);" title="Failed"></div>
                    <?php endif; ?>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-muted); flex-wrap: wrap; gap: 0.75rem;">
                    <span><i class="fas fa-circle" style="color: var(--accent-success); font-size: 0.55rem; margin-right: 0.25rem;"></i> Confirmed Delivered: <?php echo number_format($deliveredCount); ?></span>
                    <span><i class="fas fa-circle" style="color: var(--chart-3); font-size: 0.55rem; margin-right: 0.25rem;"></i> Accepted / In-Flight: <?php echo number_format($acceptedCount); ?></span>
                    <span><i class="fas fa-circle" style="color: var(--accent-error); font-size: 0.55rem; margin-right: 0.25rem;"></i> Failed / Undelivered: <?php echo number_format($failedCount); ?></span>
                    <span><i class="fas fa-bolt" style="color: #60a5fa; margin-right: 0.25rem;"></i> Total Consumed: <strong><?php echo number_format($totalUnitsUsed, 2); ?> Units</strong></span>
                </div>
            </div>

            <!-- SMS Table -->
            <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Recent Messages & Delivery Status</h3>
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
                                <th>Units Used</th>
                                <th>Status</th>
                                <th>Provider ID</th>
                                <th>Sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($smsList as $sms): 
                                $costUnits = (float)($sms['final_cost'] > 0 ? $sms['final_cost'] : $sms['estimated_cost']);
                            ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 0.85rem;">
                                    <?php echo substr($sms['request_reference'], 0, 12); ?>...
                                </td>
                                <td><?php echo htmlspecialchars($sms['recipient']); ?></td>
                                <td><?php echo $sms['estimated_segments']; ?> seg</td>
                                <td style="font-weight: 600; color: #60a5fa;">
                                    <?php echo number_format($costUnits, 2); ?> u
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($sms['status']); ?>">
                                        <?php echo htmlspecialchars($sms['status']); ?>
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
            <h2 style="margin-bottom: 1.5rem;">Wallet Activity & Ledger Report</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">
                Period: <?php echo date('M d, Y', strtotime($dateFrom)); ?> — <?php echo date('M d, Y', strtotime($dateTo)); ?>
            </p>

            <!-- Stats Grid -->
            <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div class="stat-box">
                    <div class="stat-box-label">Current Balance</div>
                    <div class="stat-box-value" style="color: var(--primary);"><?php echo number_format($balance, 2); ?></div>
                    <div class="stat-box-sub">units available</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: #f87171;">Units Used (Debits)</div>
                    <div class="stat-box-value" style="color: #f87171;">
                        -<?php echo number_format($debitUnits, 2); ?>
                    </div>
                    <div class="stat-box-sub"><?php echo (int)($walletBreakdown['debit']['count'] ?? 0); ?> dispatches</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: #f59e0b;">Amount Spent</div>
                    <div class="stat-box-value" style="color: #f59e0b;">
                        KES <?php echo number_format($amountSpentWallet, 2); ?>
                    </div>
                    <div class="stat-box-sub">total debited value</div>
                </div>
                <?php if (isset($walletBreakdown['credit']) || $displayAmountBought > 0): ?>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: #10b981;">Amount Bought</div>
                    <div class="stat-box-value" style="color: #10b981;">
                        KES <?php echo number_format($displayAmountBought, 2); ?>
                    </div>
                    <div class="stat-box-sub">+<?php echo number_format($creditUnits, 2); ?> units credited</div>
                </div>
                <?php endif; ?>
                <?php if ($reservedUnits > 0): ?>
                <div class="stat-box">
                    <div class="stat-box-label" style="color: #d1d5db;">Reserved Units</div>
                    <div class="stat-box-value" style="color: #d1d5db;">
                        <?php echo number_format($reservedUnits, 2); ?>
                    </div>
                    <div class="stat-box-sub">pending delivery</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Wallet Table -->
            <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Transaction History & Value</h3>
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
                                <th>Value (KES)</th>
                                <th>Reference</th>
                                <th>Note</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($walletList as $tx): 
                                $txUnits = (float)$tx['units'];
                                $txKes = $txUnits * $costPerUnitKes;
                            ?>
                            <tr>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($tx['entry_type']); ?>">
                                        <?php echo htmlspecialchars($tx['entry_type']); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600; color: <?php echo in_array($tx['entry_type'], ['credit', 'refund']) ? '#10b981' : '#f87171'; ?>;">
                                    <?php echo in_array($tx['entry_type'], ['credit', 'refund']) ? '+' : '−'; ?>
                                    <?php echo number_format($txUnits, 2); ?>
                                </td>
                                <td style="color: #f59e0b; font-weight: 500;">
                                    KES <?php echo number_format($txKes, 2); ?>
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
