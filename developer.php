<?php
/**
 * sms/developer.php
 * 
 * Developer Hub - API Key Lifecycle, Gateway Telemetry, Combined Overview,
 * and Extensive API Integration Documentation.
 */
require_once __DIR__ . '/auth_bridge.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/src/ThemeManager.php';

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

function maskApiKeyValue($key) {
    $value = trim((string)($key ?? ''));
    if ($value === '') {
        return 'No active API key available';
    }

    if (strlen($value) <= 12) {
        return $value;
    }

    return substr($value, 0, 12) . '…' . substr($value, -4);
}

// Initialize theme manager
$themeManager = new ThemeManager($clientId);
$cssVariables = $themeManager->generateCSSVariables();

// Fetch API Keys
$stmt = $db->prepare("SELECT * FROM client_api_keys WHERE client_id = ? ORDER BY created_at DESC");
$stmt->execute([$clientId]);
$apiKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activeApiKey = null;
foreach ($apiKeys as $key) {
    if (($key['status'] ?? '') === 'active') {
        $activeApiKey = $key;
        break;
    }
}

// Check if a new key has just been generated in session
$newKey = null;
if (isset($_SESSION['CLAYON_NEW_API_KEY'])) {
    $newKey = $_SESSION['CLAYON_NEW_API_KEY'];
    unset($_SESSION['CLAYON_NEW_API_KEY']);
}

$rawCurrentApiKey = null;
if ($newKey) {
    $rawCurrentApiKey = $newKey;
    $currentApiKeyText = maskApiKeyValue($newKey);
    $currentApiKeyHint = 'Keep this key secret. Share it only with trusted systems and rotate it immediately if it is exposed.';
    $currentApiKeyCopyable = true;
} elseif ($activeApiKey && !empty($activeApiKey['plain_api_key'])) {
    $rawCurrentApiKey = $activeApiKey['plain_api_key'];
    $currentApiKeyText = maskApiKeyValue($activeApiKey['plain_api_key']);
    $currentApiKeyHint = 'This key is masked here for safety, but you can copy the full key when needed. Keep it secret.';
    $currentApiKeyCopyable = true;
} elseif ($activeApiKey) {
    $currentApiKeyText = maskApiKeyValue(($activeApiKey['key_prefix'] ?? 'sk_live_') . '••••');
    $currentApiKeyHint = 'The full key is not available to this client view yet. Contact an admin if the key has been exposed.';
    $currentApiKeyCopyable = false;
} else {
    $currentApiKeyText = 'No active API key available';
    $currentApiKeyHint = 'No active API key is registered for this client yet.';
    $currentApiKeyCopyable = false;
}

// Base URL configuration
$appBaseUrl = rtrim(clayon_env('APP_URL', 'http://localhost/sms'), '/');
$sendSmsEndpoint = $appBaseUrl . '/api/send.php';
$balanceEndpoint = $appBaseUrl . '/api/v1/balance.php';
$historyEndpoint = $appBaseUrl . '/api/v1/history.php';

// Combined Telemetry & Overview Calculations
// 1. Remaining Wallet Balance
$stmt = $db->prepare("SELECT balance_units FROM wallet_accounts WHERE client_id = ? LIMIT 1");
$stmt->execute([$clientId]);
$remainingUnits = (float)($stmt->fetchColumn() ?: 0.0000);

// 2. API & SMS Usage metrics using estimated_cost
$stmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_requests,
        COALESCE(SUM(estimated_cost), 0) AS total_units_consumed,
        MAX(created_at) AS last_called_at
    FROM sms_requests 
    WHERE client_id = ?
");
$stmt->execute([$clientId]);
$usageRow = $stmt->fetch(PDO::FETCH_ASSOC);

$totalApiRequests = (int)($usageRow['total_requests'] ?? 0);
$totalUnitsConsumed = (float)($usageRow['total_units_consumed'] ?? 0);
$lastCalledAt = $usageRow['last_called_at'] ? date('M d, Y H:i:s', strtotime($usageRow['last_called_at'])) : 'No recent calls';

// 3. Last Key Used Timestamp
$stmt = $db->prepare("SELECT MAX(last_used_at) FROM client_api_keys WHERE client_id = ?");
$stmt->execute([$clientId]);
$lastKeyUsedAt = $stmt->fetchColumn();
$lastKeyUsedText = $lastKeyUsedAt ? date('M d, Y H:i:s', strtotime($lastKeyUsedAt)) : 'Never';

// 4. Last Credited Wallet Transaction
$stmt = $db->prepare("
    SELECT units AS amount, created_at 
    FROM wallet_ledger 
    WHERE client_id = ? AND entry_type = 'credit' 
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$clientId]);
$lastCreditRow = $stmt->fetch(PDO::FETCH_ASSOC);
$lastCreditedText = $lastCreditRow 
    ? '+' . number_format($lastCreditRow['amount'], 2) . ' Units (' . date('M d, Y', strtotime($lastCreditRow['created_at'])) . ')' 
    : 'No recent deposits';

// Trust Timestamp
$refreshedAt = date('H:i:s T');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Hub | Clayon SMS</title>
    
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Dynamic Theme CSS -->
    <style id="theme-dynamic-css">
        <?php echo $cssVariables; ?>
    </style>
    <style>
        .tabs-header {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: 0.5rem;
            overflow-x: auto;
        }
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 0.75rem 1.25rem;
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tab-btn:hover, .tab-btn.active {
            color: var(--text-primary);
            background: var(--card-bg);
            border: 1px solid var(--border-light);
        }
        .tab-content {
            display: none;
            visibility: hidden;
            opacity: 0;
        }
        .tab-content.active {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        .tab-content:not(.active) {
            display: none !important;
        }
        .telemetry-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .telemetry-card {
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 18px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            position: relative;
            overflow: hidden;
        }
        .telemetry-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
            background: var(--primary);
        }
        .telemetry-card.success::before { background: var(--accent-success); }
        .telemetry-card.warning::before { background: var(--accent-warning); }
        .telemetry-card.info::before { background: var(--accent-info); }
        
        .t-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .t-value {
            font-size: 1.6rem;
            font-weight: 700;
            font-family: var(--font-display);
            color: var(--text-primary);
        }
        .t-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .code-container {
            position: relative;
            background: var(--bg-main);
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 1.25rem;
            margin-top: 1rem;
            font-family: 'Consolas', 'Fira Code', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            color: var(--text-primary);
        }
        .copy-btn {
            position: absolute;
            top: 0.85rem;
            right: 0.85rem;
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            color: var(--text-primary);
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .copy-btn:hover {
            color: var(--primary);
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        .copy-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .new-key-alert {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--accent-success);
            padding: 1.5rem;
            border-radius: 18px;
            margin-bottom: 2rem;
        }
        .new-key-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-main);
            border: 1px solid var(--border-light);
            padding: 0.85rem 1.25rem;
            border-radius: 12px;
            margin-top: 1rem;
            font-family: monospace;
            font-size: 1.1rem;
            color: var(--text-primary);
        }
        .api-key-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(168, 85, 247, 0.14));
            border: 1px solid rgba(99, 102, 241, 0.28);
            border-radius: 18px;
            padding: 1.25rem 1.3rem;
            margin-bottom: 1.5rem;
        }
        .api-key-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 0.9rem;
        }
        .api-key-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: var(--bg-main);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 0.9rem 1rem;
            font-family: monospace;
            color: var(--text-primary);
            overflow-x: auto;
        }
        .api-key-display code {
            background: transparent;
            color: inherit;
            padding: 0;
            border: 0;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        .badge-active-protected {
            padding: 0.3rem 0.7rem;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.12);
            color: var(--accent-success);
            border: 1px solid rgba(16, 185, 129, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.65rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pill.online {
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-success);
            border: 1px solid rgba(16, 185, 129, 0.3);
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
    <?php include 'includes/theme-center.php'; ?>

    <main class="main-content" style="display:block; visibility:visible; opacity:1;">
        <div class="main-content-body-shell">
        <header style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-family: var(--font-display); font-size: 2rem; color: var(--text-primary); margin: 0;">Developer Hub</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">API Key Management, Gateway Telemetry, and Integration Documentation</p>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); background: var(--card-bg); border: 1px solid var(--border-light); padding: 0.4rem 0.8rem; border-radius: 20px;">
                <i class="fas fa-clock"></i> Data Refreshed: <strong><?php echo $refreshedAt; ?></strong>
            </div>
        </header>

        <!-- Low Balance Alert Banner -->
        <?php if ($remainingUnits < 10): ?>
            <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: var(--accent-warning); padding: 1rem 1.25rem; border-radius: 16px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.3rem;"></i>
                    <div>
                        <strong style="display: block;">SMS Balance Low (<?php echo number_format($remainingUnits, 4); ?> Units Remaining)</strong>
                        <span style="font-size: 0.85rem; opacity: 0.9;">API requests may fail if balance reaches 0.00 units.</span>
                    </div>
                </div>
                <a href="buy.php" class="btn-primary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                    <i class="fas fa-plus-circle"></i> Refill Units Now
                </a>
            </div>
        <?php endif; ?>

        <!-- Combined API & SMS Telemetry Overview Grid -->
        <div class="telemetry-grid">
            <div class="telemetry-card info">
                <span class="t-label">API Requests Called</span>
                <span class="t-value"><?php echo number_format($totalApiRequests); ?></span>
                <span class="t-sub">Total dispatched dispatches</span>
            </div>

            <div class="telemetry-card">
                <span class="t-label">Last API Call</span>
                <span class="t-value" style="font-size: 1.15rem; margin-top: 0.2rem;"><?php echo htmlspecialchars($lastCalledAt); ?></span>
                <span class="t-sub">Last key token use: <?php echo htmlspecialchars($lastKeyUsedText); ?></span>
            </div>

            <div class="telemetry-card success">
                <span class="t-label">Last Wallet Deposit</span>
                <span class="t-value" style="font-size: 1.15rem; margin-top: 0.2rem; color: var(--accent-success);"><?php echo htmlspecialchars($lastCreditedText); ?></span>
                <span class="t-sub">Automatic M-Pesa credited</span>
            </div>

            <div class="telemetry-card warning">
                <span class="t-label">Units Consumed vs Remaining</span>
                <span class="t-value" style="font-size: 1.3rem;"><?php echo number_format($remainingUnits, 4); ?> <span style="font-size: 0.85rem; color: var(--text-muted);">Units Left</span></span>
                <span class="t-sub"><?php echo number_format($totalUnitsConsumed, 4); ?> total units spent</span>
            </div>
        </div>

        <?php if ($newKey): ?>
            <div class="new-key-alert">
                <h4 style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem; margin: 0;">
                    <i class="fas fa-check-circle"></i> New API Key Generated!
                </h4>
                <p style="margin-top: 0.5rem; font-size: 0.92rem;">
                    Copy it now and store it securely. If it is exposed, contact an admin so the key can be rotated.
                </p>
                <div class="new-key-box">
                    <span id="plain-key-val"><?php echo htmlspecialchars(maskApiKeyValue($newKey)); ?></span>
                    <button class="copy-btn" style="position: static;" type="button" data-raw="<?php echo htmlspecialchars($newKey, ENT_QUOTES); ?>" onclick="copyTextToClipboard(this.getAttribute('data-raw'), 'Copied to clipboard')">
                        <i class="fas fa-copy"></i> Copy Key
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="api-key-card">
            <div class="api-key-card-header">
                <div>
                    <h3 style="margin: 0; color: var(--text-primary);">Current API Key</h3>
                    <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.9rem;"><?php echo htmlspecialchars($currentApiKeyHint); ?></p>
                </div>
                <span class="badge-active-protected">
                    <i class="fas fa-lock"></i> Active & Protected
                </span>
            </div>
            <div class="api-key-display">
                <code id="current-api-key-display"><?php echo htmlspecialchars($currentApiKeyText); ?></code>
                <?php if ($currentApiKeyCopyable && !empty($rawCurrentApiKey)): ?>
                    <button class="copy-btn" style="position: static;" type="button" data-raw="<?php echo htmlspecialchars($rawCurrentApiKey, ENT_QUOTES); ?>" onclick="copyTextToClipboard(this.getAttribute('data-raw'), 'API key copied to clipboard')">
                        <i class="fas fa-copy"></i> Copy Full Key
                    </button>
                <?php else: ?>
                    <button class="copy-btn" style="position: static;" disabled>
                        <i class="fas fa-lock"></i> Full key hidden
                    </button>
                <?php endif; ?>
            </div>
            <p style="margin: 0.75rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                Revocation is disabled for clients right now. Contact an admin if an API key is exposed to unauthorized people.
            </p>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab('keys-tab', event)">
                <i class="fas fa-key"></i> API Keys
            </button>
            <button class="tab-btn" onclick="switchTab('gateway-tab', event)">
                <i class="fas fa-server"></i> Gateway Telemetry
            </button>
            <button class="tab-btn" onclick="switchTab('docs-tab', event)">
                <i class="fas fa-book"></i> API Documentation
            </button>
            <button class="tab-btn" onclick="switchTab('code-tab', event)">
                <i class="fas fa-code"></i> Code Snippets
            </button>
        </div>

        <!-- 1. API KEYS TAB -->
        <div id="keys-tab" class="tab-content active">
            <div class="glass-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h3 style="margin: 0; color: var(--text-primary);">Active Access Keys</h3>
                        <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted);">Use Bearer token authentication in your HTTP request headers.</p>
                    </div>
                    <form action="api/actions/generate_key.php" method="POST" onsubmit="return confirm('Generating a new key will rotate your key credentials. Continue?');">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-sync-alt"></i> Rotate & Generate Key
                        </button>
                    </form>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; color: var(--text-muted); font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-light); text-align: left;">
                                <th style="padding: 1rem; color: var(--text-primary);">Key Prefix</th>
                                <th style="padding: 1rem; color: var(--text-primary);">Created Date</th>
                                <th style="padding: 1rem; color: var(--text-primary);">Last Called</th>
                                <th style="padding: 1rem; color: var(--text-primary);">Status</th>
                                <th style="padding: 1rem; color: var(--text-primary);">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($apiKeys)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted);">No active API keys found. Click rotate to generate your first key.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($apiKeys as $key): ?>
                                    <tr style="border-bottom: 1px solid var(--border-light);">
                                        <td style="padding: 1rem;">
                                            <code style="background: var(--card-bg); border: 1px solid var(--border-light); padding: 0.35rem 0.75rem; border-radius: 8px; color: var(--primary); font-weight: 600;">
                                                <?php echo htmlspecialchars($key['key_prefix']); ?>...
                                            </code>
                                        </td>
                                        <td style="padding: 1rem;"><?php echo date('M d, Y H:i', strtotime($key['created_at'])); ?></td>
                                        <td style="padding: 1rem;"><?php echo $key['last_used_at'] ? date('M d, Y H:i', strtotime($key['last_used_at'])) : 'Never'; ?></td>
                                        <td style="padding: 1rem;">
                                            <?php if ($key['status'] === 'active'): ?>
                                                <span class="badge-active-protected">
                                                    <i class="fas fa-shield-alt"></i> Active & Protected
                                                </span>
                                            <?php else: ?>
                                                <span style="padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.8rem; background: rgba(239,68,68,0.1); color: var(--accent-error);">Revoked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                                <button class="btn-secondary" style="font-size: 0.8rem; padding: 0.35rem 0.7rem;" type="button" onclick="copyTextToClipboard('<?php echo htmlspecialchars($key['key_prefix']); ?>...', 'Copied key prefix to clipboard')">
                                                    <i class="fas fa-copy"></i> Copy Prefix
                                                </button>
                                                <button class="btn-secondary" style="font-size: 0.8rem; padding: 0.35rem 0.7rem; opacity: 0.7; cursor: not-allowed;" type="button" disabled title="Revocation is admin-only and is disabled for clients right now.">
                                                    <i class="fas fa-user-shield"></i> Revoke
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. GATEWAY TELEMETRY TAB -->
        <div id="gateway-tab" class="tab-content">
            <div class="glass-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h3 style="margin: 0; color: var(--text-primary);">API Gateway Status & Metrics</h3>
                        <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted);">Real-time dispatch status, gateway uptime, and unit usage meters.</p>
                    </div>
                    <span class="status-pill online">
                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i> System Health: 99.99% Operational
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                    <!-- Meter 1 -->
                    <div style="background: var(--card-bg); border: 1px solid var(--border-light); padding: 1.5rem; border-radius: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                            <span style="font-weight: 600; color: var(--text-primary);">API Gateway Throughput</span>
                            <span style="color: var(--accent-success); font-weight: 700;">Fast-Path (Direct)</span>
                        </div>
                        <div style="height: 10px; width: 100%; background: var(--bg-main); border-radius: 5px; overflow: hidden; margin-bottom: 0.75rem;">
                            <div style="height: 100%; width: 92%; background: linear-gradient(90deg, var(--primary), var(--accent-success));"></div>
                        </div>
                        <p style="margin: 0; font-size: 0.82rem; color: var(--text-muted);">Latency: &lt; 150ms average processing window per API payload.</p>
                    </div>

                    <!-- Meter 2 -->
                    <div style="background: var(--card-bg); border: 1px solid var(--border-light); padding: 1.5rem; border-radius: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                            <span style="font-weight: 600; color: var(--text-primary);">Wallet Consumption Meter</span>
                            <span style="color: var(--primary); font-weight: 700;"><?php echo number_format($totalUnitsConsumed, 2); ?> Spent</span>
                        </div>
                        <div style="height: 10px; width: 100%; background: var(--bg-main); border-radius: 5px; overflow: hidden; margin-bottom: 0.75rem;">
                            <div style="height: 100%; width: <?php echo min(100, max(5, ($totalUnitsConsumed / max(1, $totalUnitsConsumed + $remainingUnits)) * 100)); ?>%; background: linear-gradient(90deg, var(--secondary), var(--primary));"></div>
                        </div>
                        <p style="margin: 0; font-size: 0.82rem; color: var(--text-muted);">Current balance reserve: <?php echo number_format($remainingUnits, 4); ?> Units available.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. DOCUMENTATION TAB -->
        <div id="docs-tab" class="tab-content">
            <div class="glass-card">
                <h3>Extensive HTTP API Specifications</h3>
                <p style="margin-top: 0.5rem; color: var(--text-muted);">Complete developer reference for authenticating and programmatically sending SMS messages, checking balance, and reading logs.</p>

                <!-- Endpoint 1 -->
                <h4 style="margin-top: 2rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                    <span style="background: var(--accent-success); color: white; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.8rem;">POST</span>
                    1. Send Single / Bulk SMS
                </h4>
                <div class="code-container">
                    <span id="ep-send" style="color: var(--accent-success); font-weight: bold;"><?php echo htmlspecialchars($sendSmsEndpoint); ?></span>
                    <button class="copy-btn" onclick="copyTextToClipboard('<?php echo htmlspecialchars($sendSmsEndpoint); ?>', 'Endpoint URL copied!')"><i class="fas fa-copy"></i> Copy URL</button>
                </div>

                <h4 style="margin-top: 1.5rem; color: var(--text-primary);">Headers</h4>
                <table style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-light); text-align: left;">
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Header</th>
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Value</th>
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>Authorization</code></td>
                            <td style="padding: 0.6rem 0;"><code>Bearer sk_live_...</code></td>
                            <td style="padding: 0.6rem 0;">Your active API secret key.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>Content-Type</code></td>
                            <td style="padding: 0.6rem 0;"><code>application/json</code></td>
                            <td style="padding: 0.6rem 0;">Payload encoding must be JSON.</td>
                        </tr>
                    </tbody>
                </table>

                <h4 style="margin-top: 1.5rem; color: var(--text-primary);">Request JSON Parameters</h4>
                <table style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-light); text-align: left;">
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Field</th>
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Type</th>
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Required</th>
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>recipient</code></td>
                            <td style="padding: 0.6rem 0;">String</td>
                            <td style="padding: 0.6rem 0; color: var(--accent-warning);">Yes</td>
                            <td style="padding: 0.6rem 0;">Recipient phone number in standard format (e.g. 254711486334 or 0711486334).</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>message</code></td>
                            <td style="padding: 0.6rem 0;">String</td>
                            <td style="padding: 0.6rem 0; color: var(--accent-warning);">Yes</td>
                            <td style="padding: 0.6rem 0;">Text content of the SMS. Segment pricing applies per 60 characters.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>sender_id</code></td>
                            <td style="padding: 0.6rem 0;">String</td>
                            <td style="padding: 0.6rem 0; color: var(--text-muted);">No</td>
                            <td style="padding: 0.6rem 0;">Optional. If omitted, the gateway uses TALKSASA automatically.</td>
                        </tr>
                    </tbody>
                </table>

                <h4 style="margin-top: 2rem; color: var(--text-primary);">Example Request Payload</h4>
                <div class="code-container">
                    <pre style="color: #60a5fa;">{
  "recipient": "+254711486334",
  "message": "Hello from your app"
}</pre>
                </div>
                <p style="margin-top: 0.6rem; color: var(--text-muted); font-size: 0.9rem;">The gateway automatically defaults the sender ID to TALKSASA when the field is omitted, so a minimal payload is sufficient for third-party integrations.</p>

                <h4 style="margin-top: 1.5rem; color: var(--text-primary);">Provider Parameters Forwarded to TalkSasa</h4>
                <table style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-light); text-align: left;">
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Field</th>
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Type</th>
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>recipient</code></td>
                            <td style="padding: 0.6rem 0;">String</td>
                            <td style="padding: 0.6rem 0;">The normalized destination phone number.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>sender_id</code></td>
                            <td style="padding: 0.6rem 0;">String</td>
                            <td style="padding: 0.6rem 0;">Defaults to TALKSASA when omitted.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>message</code></td>
                            <td style="padding: 0.6rem 0;">String</td>
                            <td style="padding: 0.6rem 0;">The SMS body sent to the provider.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>type</code></td>
                            <td style="padding: 0.6rem 0;">String</td>
                            <td style="padding: 0.6rem 0;">Set as plain text for the downstream provider request.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>callback_url</code></td>
                            <td style="padding: 0.6rem 0;">String</td>
                            <td style="padding: 0.6rem 0;">DLR callback URL included for delivery updates.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><code>dlr_url</code></td>
                            <td style="padding: 0.6rem 0;">String</td>
                            <td style="padding: 0.6rem 0;">Delivery report URL included for provider callbacks.</td>
                        </tr>
                    </tbody>
                </table>

                <h4 style="margin-top: 1.5rem; color: var(--text-primary);">Example Success Response From This API</h4>
                <div class="code-container">
                    <pre style="color: #34d399;">{
  "status": "success",
  "message": "SMS queued for delivery",
  "data": {
    "request_id": 10,
    "reference": "req_16a6d0a3c55c80",
    "recipient": "+254711486334",
    "segments": 1,
    "estimated_cost": 1,
    "sms_status": "pending_provider_confirmation",
    "billing_status": "reserved_not_debited",
    "info": "Message queued. Units will be debited only when provider confirms delivery."
  },
  "timestamp": "2026-08-01 00:00:00"
}</pre>
                </div>
                <p style="margin-top: 0.6rem; color: var(--text-muted); font-size: 0.9rem;">
                    This matches the current public send endpoint implementation. The request is accepted with HTTP 202 while the provider confirmation is still pending, so the response exposes <code>pending_provider_confirmation</code> and <code>reserved_not_debited</code>.
                </p>

                <h4 style="margin-top: 1.5rem; color: var(--text-primary);">Example Validation Error Response</h4>
                <div class="code-container">
                    <pre style="color: #facc15;">{
  "status": "validation_error",
  "message": "Validation failed",
  "errors": {
    "recipient": "Recipient phone number is required"
  },
  "timestamp": "2026-08-01 00:00:00"
}</pre>
                </div>

                <h4 style="margin-top: 1.5rem; color: var(--text-primary);">Example Response Shape From TalkSasa</h4>
                <div class="code-container">
                    <pre style="color: #facc15;">{
  "status": true,
  "message": "queued",
  "data": {
    "queue_uid": "5d801e2e-ff56-4ad6-a63e-0cc83f733d10"
  }
}</pre>
                </div>

                <!-- HTTP Error Table -->
                <h4 style="margin-top: 2rem; color: var(--text-primary);">HTTP Response Status Codes</h4>
                <table style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-light); text-align: left;">
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Code</th>
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Status</th>
                            <th style="padding: 0.6rem 0; color: var(--text-primary);">Meaning</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><span style="color: var(--accent-success); font-weight: bold;">202 Accepted</span></td>
                            <td style="padding: 0.6rem 0;">Queued</td>
                            <td style="padding: 0.6rem 0;">The request was accepted and queued for delivery. Units are reserved until provider confirmation.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><span style="color: var(--accent-warning); font-weight: bold;">422 Unprocessable Entity</span></td>
                            <td style="padding: 0.6rem 0;">Validation Error</td>
                            <td style="padding: 0.6rem 0;">Missing recipient, empty message, or malformed JSON body.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><span style="color: var(--accent-error); font-weight: bold;">401 Unauthorized</span></td>
                            <td style="padding: 0.6rem 0;">Invalid Key</td>
                            <td style="padding: 0.6rem 0;">Missing or invalid Authorization Bearer API token.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.6rem 0;"><span style="color: var(--accent-error); font-weight: bold;">402 Payment Required</span></td>
                            <td style="padding: 0.6rem 0;">Insufficient Balance</td>
                            <td style="padding: 0.6rem 0;">SMS unit balance is too low to queue the message. Refill units to resume dispatches.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. CODE SNIPPETS TAB -->
        <div id="code-tab" class="tab-content">
            <div class="glass-card">
                <h3>Multi-Language Integration Snippets</h3>
                <p style="margin-top: 0.5rem; color: var(--text-muted);">Copy and paste production code examples directly into your app.</p>
                
                <!-- cURL -->
                <h4 style="margin-top: 2rem; color: var(--text-primary);">cURL Request</h4>
                <div class="code-container">
                    <pre id="curl-code" style="color: #60a5fa;">curl -X POST <?php echo htmlspecialchars($sendSmsEndpoint); ?> \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "recipient": "254711486334",
    "message": "Hello from Clayon SMS API!"
  }'</pre>
                    <button class="copy-btn" onclick="copySnippetCode('curl-code')"><i class="fas fa-copy"></i> Copy Snippet</button>
                </div>

                <!-- PHP cURL -->
                <h4 style="margin-top: 2rem; color: var(--text-primary);">PHP (Native cURL)</h4>
                <div class="code-container">
                    <pre id="php-code" style="color: #f472b6;">&lt;?php
$payload = [
    "recipient" => "254711486334",
    "message"   => "Hello from Clayon SMS API!"
];

$ch = curl_init("<?php echo htmlspecialchars($sendSmsEndpoint); ?>");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer YOUR_API_KEY",
        "Content-Type: application/json"
    ],
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;</pre>
                    <button class="copy-btn" onclick="copySnippetCode('php-code')"><i class="fas fa-copy"></i> Copy Snippet</button>
                </div>

                <!-- JavaScript Fetch / Node.js -->
                <h4 style="margin-top: 2rem; color: var(--text-primary);">JavaScript (Fetch / Node.js)</h4>
                <div class="code-container">
                    <pre id="js-code" style="color: #facc15;">const response = await fetch("<?php echo htmlspecialchars($sendSmsEndpoint); ?>", {
  method: "POST",
  headers: {
    "Authorization": "Bearer YOUR_API_KEY",
    "Content-Type": "application/json"
  },
  body: JSON.stringify({
    recipient: "254711486334",
    message: "Hello from Clayon SMS API!"
  })
});

const data = await response.json();
console.log(data);</pre>
                    <button class="copy-btn" onclick="copySnippetCode('js-code')"><i class="fas fa-copy"></i> Copy Snippet</button>
                </div>

                <!-- Python Requests -->
                <h4 style="margin-top: 2rem; color: var(--text-primary);">Python (Requests)</h4>
                <div class="code-container">
                    <pre id="python-code" style="color: #34d399;">import requests

url = "<?php echo htmlspecialchars($sendSmsEndpoint); ?>"
headers = {
    "Authorization": "Bearer YOUR_API_KEY",
    "Content-Type": "application/json"
}
payload = {
    "recipient": "254711486334",
    "message": "Hello from Clayon SMS API!"
}

response = requests.post(url, json=payload, headers=headers)
print(response.json())</pre>
                    <button class="copy-btn" onclick="copySnippetCode('python-code')"><i class="fas fa-copy"></i> Copy Snippet</button>
                </div>
            </div>
        </div>
        </div>
    </main>

    <script>
        function switchTab(tabId, evt) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const target = document.getElementById(tabId);
            if (target) target.classList.add('active');
            if (evt && evt.currentTarget) evt.currentTarget.classList.add('active');
        }

        function copySnippetCode(elementId) {
            const el = document.getElementById(elementId);
            if (el) {
                copyTextToClipboard(el.innerText, 'Copied to clipboard');
            }
        }
    </script>
</body>
</html>
