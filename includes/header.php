<?php
/**
 * sms/includes/header.php
 * 
 * Unified SaaS Top Navigation Bar & Profile Modal.
 * Included at top of index.php, send.php, buy.php, reports.php, developer.php, profile.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$headerClientId = $_SESSION['CLAYON_CLIENT_ID'] ?? null;
$headerClientName = $_SESSION['CLAYON_CLIENT_NAME'] ?? 'Guest';
$headerClientEmail = $_SESSION['CLAYON_CLIENT_EMAIL'] ?? '';
$headerInitial = strtoupper(substr($headerClientName, 0, 1));
$headerUnits = 0.0000;
$headerPlanName = 'Default Plan';
$headerPhone = '';
$headerCreatedAt = null;
$headerAgeText = '';
$headerDlrRate = 100.0;

if ($headerClientId) {
    try {
        $headerDb = getClayonDb();
        
        // Query client profile & wallet
        $hStmt = $headerDb->prepare("
            SELECT c.*, p.plan_name, w.balance_units 
            FROM clients c
            LEFT JOIN pricing_plans p ON c.plan_id = p.id
            LEFT JOIN wallet_accounts w ON c.id = w.client_id
            WHERE c.id = ? LIMIT 1
        ");
        $hStmt->execute([$headerClientId]);
        $hClient = $hStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($hClient) {
            $headerUnits = (float)($hClient['balance_units'] ?? 0);
            $headerPlanName = $hClient['plan_name'] ?? 'Default Plan';
            $headerPhone = $hClient['phone'] ?? '';
            $headerCreatedAt = strtotime($hClient['created_at']);
            
            $days = floor((time() - $headerCreatedAt) / 86400);
            $headerAgeText = ($days < 1) ? "Joined today" : $days . " days old";
        }
        
        // Calculate DLR %
        $hStmtSms = $headerDb->prepare("SELECT COUNT(*), SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) FROM sms_requests WHERE client_id = ?");
        $hStmtSms->execute([$headerClientId]);
        $smsRow = $hStmtSms->fetch(PDO::FETCH_NUM);
        $totalS = (int)($smsRow[0] ?? 0);
        $delivS = (int)($smsRow[1] ?? 0);
        $headerDlrRate = $totalS > 0 ? round(($delivS / $totalS) * 100, 1) : 100.0;
        
    } catch (Exception $e) {
        error_log("Header DB Error: " . $e->getMessage());
    }
}
?>
<!-- SPA Top Loader -->
<div id="clayon-loader"><div class="loader-bar"></div></div>

<!-- Global Toast Container -->
<div id="toast-container" class="toast-container"></div>

<!-- Top SaaS Header Bar -->
<header class="saas-top-header">
    <div class="header-left">
        <div class="mobile-logo-wrap">
            <span class="logo">CLAYON</span>
            <button class="hamburger" id="hamburger-toggle" title="Toggle Navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    <div class="header-right">
        <!-- Units Pill Badge -->
        <a href="buy.php" class="header-units-badge <?php echo $headerUnits < 10 ? 'low-balance' : ''; ?>" title="Click to Refill Units">
            <i class="fas fa-coins"></i>
            <span><?php echo number_format($headerUnits, 4); ?> Units</span>
            <?php if ($headerUnits < 10): ?>
                <span class="low-tag">Low</span>
            <?php endif; ?>
        </a>

        <!-- Profile Quick Access Tab -->
        <button class="header-profile-btn" onclick="openProfileModal()" title="View Account Profile">
            <div class="avatar-circle"><?php echo htmlspecialchars($headerInitial); ?></div>
            <div class="profile-info-text">
                <span class="profile-name"><?php echo htmlspecialchars($headerClientName); ?></span>
                <span class="profile-plan"><?php echo htmlspecialchars($headerPlanName); ?></span>
            </div>
            <i class="fas fa-chevron-down" style="font-size: 0.75rem; opacity: 0.7;"></i>
        </button>
    </div>
</header>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Full Profile Overview Modal -->
<div id="profileOverviewModal" class="profile-modal-wrap" style="display: none;">
    <div class="profile-modal-backdrop" onclick="closeProfileModal()"></div>
    <div class="profile-modal-card">
        <div class="profile-modal-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="avatar-circle large"><?php echo htmlspecialchars($headerInitial); ?></div>
                <div>
                    <h3 style="margin: 0; font-family: var(--font-display); color: var(--text-primary);"><?php echo htmlspecialchars($headerClientName); ?></h3>
                    <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($headerClientEmail); ?></p>
                </div>
            </div>
            <button class="modal-close-btn" onclick="closeProfileModal()">&times;</button>
        </div>

        <div class="profile-modal-body">
            <!-- Account Summary Stats -->
            <div class="modal-stats-grid">
                <div class="m-stat-card">
                    <span class="m-stat-label">Units Remaining</span>
                    <span class="m-stat-val" style="color: var(--primary);"><?php echo number_format($headerUnits, 4); ?></span>
                </div>
                <div class="m-stat-card">
                    <span class="m-stat-label">Plan Tier</span>
                    <span class="m-stat-val"><?php echo htmlspecialchars($headerPlanName); ?></span>
                </div>
                <div class="m-stat-card">
                    <span class="m-stat-label">DLR Success</span>
                    <span class="m-stat-val" style="color: var(--accent-success);"><?php echo $headerDlrRate; ?>%</span>
                </div>
                <div class="m-stat-card">
                    <span class="m-stat-label">Account Age</span>
                    <span class="m-stat-val"><?php echo $headerAgeText; ?></span>
                </div>
            </div>

            <!-- Profile Overview Details Table -->
            <div class="modal-info-block">
                <h4 style="margin: 0 0 0.75rem; font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Account Metadata</h4>
                <div class="modal-info-row">
                    <span>Phone Number:</span>
                    <strong><?php echo htmlspecialchars($headerPhone ?: 'Not set'); ?></strong>
                </div>
                <div class="modal-info-row">
                    <span>Member Since:</span>
                    <strong><?php echo $headerCreatedAt ? date('M d, Y', $headerCreatedAt) : 'N/A'; ?></strong>
                </div>
                <div class="modal-info-row">
                    <span>Account Status:</span>
                    <strong style="color: var(--accent-success);"><i class="fas fa-check-circle"></i> Active & Verified</strong>
                </div>
            </div>

            <!-- Quick Action Row -->
            <div class="modal-quick-actions">
                <button onclick="copyCurrentClientApiKey()" class="m-action-btn">
                    <i class="fas fa-key"></i>
                    <span>Copy API Key</span>
                </button>
                <a href="developer.php" class="m-action-btn">
                    <i class="fas fa-code"></i>
                    <span>API & Usage</span>
                </a>
                <a href="buy.php" class="m-action-btn highlight">
                    <i class="fas fa-plus-circle"></i>
                    <span>Refill Units</span>
                </a>
                <a href="mailto:support@cashwrite.co.ke" class="m-action-btn">
                    <i class="fas fa-headset"></i>
                    <span>Support</span>
                </a>
            </div>
        </div>

        <div class="profile-modal-footer">
            <a href="profile.php" class="btn-secondary" style="font-size: 0.85rem;"><i class="fas fa-user-cog"></i> Edit Details</a>
            <button class="btn-primary" onclick="closeProfileModal()">Done</button>
        </div>
    </div>
</div>

<style>
    /* SaaS Top Header CSS */
    .saas-top-header {
        position: fixed;
        top: 0;
        right: 0;
        left: 280px;
        height: 68px;
        background: var(--bg-main);
        border-bottom: 1px solid var(--glass-border);
        padding: 0 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 90;
        transition: left 0.3s ease;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .header-units-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 0.9rem;
        background: rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(99, 102, 241, 0.25);
        border-radius: 20px;
        color: var(--primary);
        font-weight: 600;
        font-size: 0.88rem;
        text-decoration: none;
        transition: all 0.25s ease;
    }

    .header-units-badge:hover {
        background: rgba(99, 102, 241, 0.2);
        transform: translateY(-1px);
    }

    .header-units-badge.low-balance {
        background: rgba(239, 68, 68, 0.12);
        border-color: rgba(239, 68, 68, 0.3);
        color: var(--accent-error);
    }

    .low-tag {
        background: var(--accent-error);
        color: white;
        font-size: 0.65rem;
        padding: 0.1rem 0.4rem;
        border-radius: 10px;
        text-transform: uppercase;
        font-weight: 700;
    }

    .header-profile-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--card-bg);
        border: 1px solid var(--border-light);
        padding: 0.35rem 0.85rem 0.35rem 0.4rem;
        border-radius: 30px;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.25s ease;
    }

    .header-profile-btn:hover {
        background: var(--card-hover);
        border-color: var(--primary);
    }

    .avatar-circle {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        font-weight: 700;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-circle.large {
        width: 48px;
        height: 48px;
        font-size: 1.3rem;
    }

    .profile-info-text {
        display: flex;
        flex-direction: column;
        text-align: left;
    }

    .mobile-logo-wrap {
        display: none;
    }

    .profile-name {
        font-size: 0.85rem;
        font-weight: 600;
        line-height: 1.2;
    }

    .profile-plan {
        font-size: 0.7rem;
        color: var(--text-muted);
    }

    /* Profile Modal CSS */
    .profile-modal-wrap {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        z-index: 10005;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .profile-modal-backdrop {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(6px);
        z-index: -1;
    }

    .profile-modal-card {
        background: var(--bg-main);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        width: 100%;
        max-width: 540px;
        padding: 1.75rem;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .profile-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--divider);
    }

    .modal-close-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 1.6rem;
        cursor: pointer;
        transition: color 0.2s;
    }

    .modal-close-btn:hover {
        color: var(--accent-error);
    }

    .modal-stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .m-stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border-light);
        padding: 0.85rem 1rem;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .m-stat-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .m-stat-val {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text-primary);
        font-family: var(--font-display);
    }

    .modal-info-block {
        background: var(--card-bg);
        border: 1px solid var(--border-light);
        padding: 1rem;
        border-radius: 14px;
    }

    .modal-info-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.88rem;
        padding: 0.35rem 0;
        color: var(--text-muted);
    }

    .modal-info-row strong {
        color: var(--text-primary);
    }

    .modal-quick-actions {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.5rem;
    }

    .m-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.75rem 0.5rem;
        background: var(--card-bg);
        border: 1px solid var(--border-light);
        border-radius: 12px;
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .m-action-btn:hover {
        background: var(--card-hover);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }

    .m-action-btn.highlight {
        background: rgba(16, 185, 129, 0.1);
        border-color: rgba(16, 185, 129, 0.3);
        color: var(--accent-success);
    }

    .profile-modal-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid var(--divider);
    }

    /* Toast Notification System */
    .toast-container {
        position: fixed;
        top: 1.5rem;
        right: 1.5rem;
        z-index: 100000;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        pointer-events: none;
    }

    .toast-item {
        pointer-events: auto;
        background: var(--bg-main);
        color: var(--text-primary);
        border: 1px solid var(--primary);
        box-shadow: 0 10px 30px rgba(0,0,0,0.4), 0 0 15px var(--primary-glow);
        padding: 0.85rem 1.25rem;
        border-radius: 14px;
        font-size: 0.9rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: toastIn 0.35s cubic-bezier(0.1, 0.7, 0.1, 1) forwards;
    }

    .toast-item.success { border-color: var(--accent-success); }
    .toast-item.info { border-color: var(--primary); }
    .toast-item.warning { border-color: var(--accent-warning); }
    .toast-item.error { border-color: var(--accent-error); }

    @keyframes toastIn {
        from { opacity: 0; transform: translateY(-15px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    @keyframes toastOut {
        from { opacity: 1; transform: translateY(0) scale(1); }
        to { opacity: 0; transform: translateY(-15px) scale(0.95); }
    }

    @media (max-width: 1024px) {
        .saas-top-header {
            left: 0;
            padding: 0 1rem;
            height: 64px;
            align-items: center;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .header-right {
            gap: 0.75rem;
        }
        .profile-info-text {
            display: none;
        }
        .mobile-logo-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .saas-top-header .logo {
            font-size: 1.25rem;
        }
    }
</style>

<script>
    // Global Toast Function
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast-item ${type}`;
        
        const icons = {
            'success': 'fa-check-circle',
            'info': 'fa-info-circle',
            'warning': 'fa-exclamation-triangle',
            'error': 'fa-times-circle'
        };
        const colors = {
            'success': 'var(--accent-success)',
            'info': 'var(--primary)',
            'warning': 'var(--accent-warning)',
            'error': 'var(--accent-error)'
        };
        
        toast.innerHTML = `
            <i class="fas ${icons[type] || 'fa-bell'}" style="color: ${colors[type] || 'var(--primary)'}; font-size: 1.1rem;"></i>
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'toastOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3200);
    }

    // Copy to Clipboard Utility with Top-Right Toast
    function copyTextToClipboard(text, customLabel = 'Copied to clipboard') {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            showToast(customLabel, 'success');
        }).catch(err => {
            console.error('Clipboard copy error:', err);
            showToast('Failed to copy', 'error');
        });
    }

    async function copyCurrentClientApiKey() {
        try {
            const res = await fetch('api/theme-api.php?action=current'); // Simple ping check
            const codeEl = document.getElementById('plain-key-val');
            if (codeEl && codeEl.innerText) {
                copyTextToClipboard(codeEl.innerText, 'Copied to clipboard');
            } else {
                copyTextToClipboard('<?php echo htmlspecialchars($_SESSION['CLAYON_NEW_API_KEY'] ?? "sk_live_..."); ?>', 'Copied to clipboard');
            }
        } catch(e) {
            copyTextToClipboard('API Key', 'Copied to clipboard');
        }
    }

    function openProfileModal() {
        const modal = document.getElementById('profileOverviewModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeProfileModal() {
        const modal = document.getElementById('profileOverviewModal');
        if (modal) modal.style.display = 'none';
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!sidebar || !overlay) return;
        const open = sidebar.classList.toggle('active');
        document.body.classList.toggle('sidebar-open', open);
        overlay.classList.toggle('active', open);
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!sidebar || !overlay) return;
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const toggleButton = document.getElementById('hamburger-toggle');
        const overlay = document.getElementById('sidebar-overlay');
        const mainJsHandledSidebar = window.__sidebarToggleHandledByMainJS === true;

        if (!mainJsHandledSidebar && toggleButton) {
            toggleButton.addEventListener('click', function () {
                toggleSidebar();
            });
        }
        if (!mainJsHandledSidebar && overlay) {
            overlay.addEventListener('click', function () {
                closeSidebar();
            });
        }

        document.querySelectorAll('.nav-item').forEach(function (navItem) {
            navItem.addEventListener('click', function () {
                if (window.innerWidth <= 1024) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        });
    });
</script>
<?php include __DIR__ . '/theme-center-script.php'; ?>
