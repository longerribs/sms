<?php
/**
 * sms/profile.php
 * 
 * Account profile configuration, account statistics, and personal details editing.
 */
require_once __DIR__ . '/auth_bridge.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/src/ThemeManager.php';

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

// Initialize theme manager
$themeManager = new ThemeManager($clientId);
$cssVariables = $themeManager->generateCSSVariables();

// Fetch Client & Wallet Details
$stmt = $db->prepare("
    SELECT c.*, p.plan_name, w.balance_units 
    FROM clients c
    LEFT JOIN pricing_plans p ON c.plan_id = p.id
    LEFT JOIN wallet_accounts w ON c.id = w.client_id
    WHERE c.id = ? LIMIT 1
");
$stmt->execute([$clientId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    die("User session invalid.");
}

// Calculate Account Age
$createdAt = strtotime($client['created_at']);
$ageDays = floor((time() - $createdAt) / 86400);
$ageText = ($ageDays < 1) ? "Joined today" : $ageDays . " days old";

// Fetch Last Profile Update from audit_logs
$stmt = $db->prepare("
    SELECT created_at FROM audit_logs 
    WHERE actor_type = 'client' AND actor_id = ? AND action = 'profile_update' 
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$clientId]);
$lastUpdateRow = $stmt->fetch();
$lastUpdateText = $lastUpdateRow ? date('M d, Y H:i', strtotime($lastUpdateRow['created_at'])) : 'Never updated';

// Calculate Performance Delivery %
$stmt = $db->prepare("SELECT COUNT(*) FROM sms_requests WHERE client_id = ?");
$stmt->execute([$clientId]);
$totalSms = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM sms_requests WHERE client_id = ? AND delivery_status = 'delivered'");
$stmt->execute([$clientId]);
$deliveredSms = (int)$stmt->fetchColumn();

$deliveryRate = $totalSms > 0 ? round(($deliveredSms / $totalSms) * 100, 1) : 100.0;

// Handle Success/Error status queries
$statusMsg = '';
$statusType = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $statusMsg = isset($_GET['msg']) ? $_GET['msg'] : 'Profile updated successfully.';
        $statusType = 'success';
    } elseif ($_GET['status'] === 'error') {
        $statusMsg = isset($_GET['msg']) ? $_GET['msg'] : 'Failed to update details.';
        $statusType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings | Clayon</title>
    
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
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: 0.5rem;
        }
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 0.75rem 1.25rem;
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
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
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .info-card {
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            padding: 1.25rem;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .info-card-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-card-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .status-alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .status-alert.success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--accent-success);
        }
        .status-alert.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--accent-error);
        }
        .performance-section {
            display: flex;
            align-items: center;
            gap: 2rem;
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            padding: 2rem;
            border-radius: 20px;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        .performance-percentage {
            font-size: 3rem;
            font-weight: 700;
            font-family: var(--font-display);
            color: var(--accent-success);
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
        <header style="margin-bottom: 2rem;">
            <h1 style="font-family: var(--font-display); color: var(--text-primary);">Profile Settings</h1>
            <p style="color: var(--text-muted);">Manage your personal account profile and view usage performance</p>
        </header>

        <?php if (!empty($statusMsg)): ?>
            <div class="status-alert <?php echo $statusType; ?>">
                <i class="fas <?php echo $statusType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($statusMsg); ?></span>
            </div>
        <?php endif; ?>

        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab('info-tab', event)">Account Info</button>
            <button class="tab-btn" onclick="switchTab('profile-tab', event)">Edit Profile</button>
            <button class="tab-btn" onclick="switchTab('security-tab', event)">Security</button>
        </div>

        <!-- ACCOUNT INFO TAB -->
        <div id="info-tab" class="tab-content active">
            <div class="glass-card">
                <h3 style="color: var(--text-primary);">Account Summary</h3>
                <div class="info-grid">
                    <div class="info-card">
                        <span class="info-card-label">SMS Wallet Balance</span>
                        <span class="info-card-value" style="color: var(--primary);"><?php echo number_format($client['balance_units'], 4); ?> Units</span>
                    </div>
                    <div class="info-card">
                        <span class="info-card-label">Date Joined</span>
                        <span class="info-card-value"><?php echo date('M d, Y', $createdAt); ?></span>
                    </div>
                    <div class="info-card">
                        <span class="info-card-label">Account Age</span>
                        <span class="info-card-value"><?php echo $ageText; ?></span>
                    </div>
                    <div class="info-card">
                        <span class="info-card-label">Last Profile Update</span>
                        <span class="info-card-value" style="font-size: 1.05rem;"><?php echo $lastUpdateText; ?></span>
                    </div>
                </div>

                <div class="performance-section">
                    <div>
                        <div class="performance-percentage"><?php echo $deliveryRate; ?>%</div>
                        <div style="font-weight: 600; color: var(--text-primary); margin-top: 0.25rem;">DLR Success Rate</div>
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                        <div style="height: 10px; width: 100%; background: var(--bg-main); border-radius: 5px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $deliveryRate; ?>%; background: linear-gradient(90deg, var(--primary), var(--accent-success)); border-radius: 5px;"></div>
                        </div>
                        <p style="margin-top: 0.75rem; font-size: 0.9rem; color: var(--text-muted);">
                            Computed from <?php echo number_format($deliveredSms); ?> successfully delivered messages out of <?php echo number_format($totalSms); ?> total dispatches.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- EDIT PROFILE TAB -->
        <div id="profile-tab" class="tab-content">
            <div class="glass-card">
                <h3 style="color: var(--text-primary);">Personal Information</h3>
                <form action="api/actions/update_profile.php" method="POST" style="margin-top: 1.5rem; max-width: 600px;">
                    <div class="form-group">
                        <label class="form-label" for="name">Full Name</label>
                        <input class="form-control" type="text" id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input class="form-control" type="email" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input class="form-control" type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" required>
                    </div>

                    <button type="submit" class="btn-primary" style="padding: 0.75rem 2rem;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- SECURITY TAB -->
        <div id="security-tab" class="tab-content">
            <div class="glass-card">
                <h3 style="color: var(--text-primary);">Change Password</h3>
                <form action="api/actions/change_password.php" method="POST" style="margin-top: 1.5rem; max-width: 600px;">
                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <input class="form-control" type="password" id="new_password" name="new_password" required autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn-primary" style="padding: 0.75rem 2rem;">
                        <i class="fas fa-key"></i> Update Password
                    </button>
                </form>
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
            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add('active');
            }
        }
    </script>
</body>
</html>




