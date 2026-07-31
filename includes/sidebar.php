<?php
/**
 * sms/includes/sidebar.php
 * 
 * Shared Navigation Sidebar for Clayon SMS SaaS Platform.
 * Detects active page automatically; displays the logged-in user's name.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$sessionName = $_SESSION['CLAYON_CLIENT_NAME'] ?? 'User';
$sessionInitial = strtoupper(substr($sessionName, 0, 1));
?>
<aside class="sidebar" id="sidebar">
    <div class="logo">CLAYON SMS</div>

    <!-- User Identity Strip -->
    <div style="
        margin: 0.75rem 0.5rem 1.5rem;
        padding: 0.9rem 1rem;
        background: rgba(99, 102, 241, 0.08);
        border: 1px solid rgba(99, 102, 241, 0.15);
        border-radius: 14px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    ">
        <div style="
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
        "><?php echo htmlspecialchars($sessionInitial); ?></div>
        <div style="overflow: hidden;">
            <div style="font-weight: 600; font-size: 0.9rem; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?php echo htmlspecialchars($sessionName); ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Active Account</div>
        </div>
    </div>

    <nav class="nav-links">
        <a href="index.php" class="nav-item <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="send.php" class="nav-item <?php echo $currentPage === 'send.php' ? 'active' : ''; ?>">
            <i class="fas fa-paper-plane"></i>
            <span>Send SMS</span>
        </a>
        <a href="buy.php" class="nav-item <?php echo $currentPage === 'buy.php' ? 'active' : ''; ?>">
            <i class="fas fa-wallet"></i>
            <span>Buy Units</span>
        </a>
        <a href="reports.php" class="nav-item <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
        </a>

        <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 0.75rem 0;">

        <a href="developer.php" class="nav-item <?php echo $currentPage === 'developer.php' ? 'active' : ''; ?>">
            <i class="fas fa-code"></i>
            <span>Developer Hub</span>
        </a>
        <a href="profile.php" class="nav-item <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-cog"></i>
            <span>Profile Settings</span>
        </a>

        <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 0.75rem 0;">

        <a href="logout.php" class="nav-item" style="color: #f87171;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#f87171'">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>
