<?php
/**
 * sms/buy.php
 * 
 * Top-up wallet units via M-Pesa STK Push.
 * Supports dynamic reseller pricing and real-time payment polling.
 */
require_once __DIR__ . '/auth_bridge.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/src/ThemeManager.php';

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

// Initialize theme manager
$themeManager = new ThemeManager($clientId);
$cssVariables = $themeManager->generateCSSVariables();

// Fetch client and plan details
$stmt = $db->prepare("
    SELECT c.*, p.plan_name, p.provider_markup_type, p.markup_value, w.balance_units
    FROM clients c
    LEFT JOIN pricing_plans p ON c.plan_id = p.id
    LEFT JOIN wallet_accounts w ON c.id = w.client_id
    WHERE c.id = ?
");
$stmt->execute([$clientId]);
$clientData = $stmt->fetch(PDO::FETCH_ASSOC);

$currentBalance = $clientData ? (float)$clientData['balance_units'] : 0;
$markupValue    = (float)($clientData['markup_value'] ?? 0);
$markupType     = $clientData['provider_markup_type'] ?? 'percentage';

// Fetch base provider rate from settings
$stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'PROVIDER_UNITS_PER_KES'");
$stmt->execute();
$providerRate = (float)($stmt->fetchColumn() ?: 2.0);

// Calculate client-specific unit rate after markup
if ($markupType === 'percentage') {
    $clientRate = $providerRate / (1 + ($markupValue / 100));
} else {
    $clientRate = $providerRate - $markupValue;
}
$clientRate = max(0.1, $clientRate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Units | Clayon SMS</title>

    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Dynamic Theme CSS -->
    <style id="theme-dynamic-css">
        <?php echo $cssVariables; ?>
    </style>
    <style>
        #units-preview {
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-block;
            transform-origin: left center;
        }

        /* Payment Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 1150;
            display: none;
            justify-content: center;
            align-items: center;
            pointer-events: auto;
        }

        .modal-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .spinner-ring {
            width: 80px;
            height: 80px;
            border: 4px solid rgba(99, 102, 241, 0.15);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            margin: 0 auto 2rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .result-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 2rem;
            animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .result-icon.success { background: rgba(16, 185, 129, 0.2); color: var(--accent-success); }
        .result-icon.error   { background: rgba(239, 68, 68, 0.2);  color: var(--accent-error); }

        @keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        .package-presets {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .package-btn {
            padding: 0.65rem 0.5rem;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }
        .package-btn:hover, .package-btn.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.12);
            color: var(--primary);
        }
        .buy-input {
            width: 100%;
            padding: 1rem;
            border-radius: 12px;
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            color: var(--text-primary);
            font-family: var(--font-body);
            font-size: 1.2rem;
            transition: border-color 0.25s;
        }
        .buy-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 10px var(--primary-glow);
        }
    </style>
</head>
<body>
    <div class="aurora">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <!-- Payment Waiting Modal -->
    <div id="payment-modal" class="modal-overlay">
        <div class="modal-card">
            <div id="modal-status-icon">
                <div class="spinner-ring"></div>
            </div>
            <h2 id="modal-title" style="font-family: var(--font-display); margin-bottom: 1rem; color: var(--text-primary);">Waiting for Payment</h2>
            <p id="modal-text" style="color: var(--text-muted); line-height: 1.5;">
                Please check your phone for the M-Pesa STK prompt and enter your PIN.
            </p>
            <div id="modal-timer" style="margin-top: 2rem; font-size: 0.8rem; color: var(--text-muted);">
                Auto-checking: <span id="timer-val">60</span>s remaining
            </div>
        </div>
    </div>

    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header style="margin-bottom: 2rem;">
            <h1 style="font-family: var(--font-display); color: var(--text-primary);">Refill Wallet</h1>
            <p style="color: var(--text-muted);">
                Current Balance: <strong style="color: var(--primary);"><?php echo number_format($currentBalance, 4); ?> Units</strong>
            </p>
        </header>

        <?php if ($currentBalance < 10): ?>
            <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: var(--accent-warning); padding: 1rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-exclamation-triangle"></i>
                <span><strong>Low balance!</strong> Refill now to keep sending SMS messages uninterrupted.</span>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- TOP-UP FORM -->
            <div class="glass-card">
                <h3 style="color: var(--text-primary);">Top-up via M-Pesa</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">
                    Plan: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($clientData['plan_name'] ?: 'Standard'); ?></strong> &mdash;
                    Rate: <strong style="color: var(--primary);">1 KES = <?php echo round($clientRate, 3); ?> Units</strong>
                </p>

                <!-- Quick Package Presets -->
                <div style="margin-top: 1.25rem;">
                    <div style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.5rem;">Quick Amounts</div>
                    <div class="package-presets">
                        <button class="package-btn" onclick="setAmount(100)">KES 100</button>
                        <button class="package-btn" onclick="setAmount(250)">KES 250</button>
                        <button class="package-btn" onclick="setAmount(500)">KES 500</button>
                        <button class="package-btn" onclick="setAmount(1000)">KES 1,000</button>
                        <button class="package-btn" onclick="setAmount(2500)">KES 2,500</button>
                        <button class="package-btn" onclick="setAmount(5000)">KES 5,000</button>
                    </div>
                </div>

                <form id="topup-form" style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label class="stat-label" for="amount">Amount (KES)</label>
                        <input type="number" id="amount" name="amount" class="buy-input" min="1" placeholder="e.g. 500" required>
                        <div id="units-preview" style="color: var(--primary); margin-top: 0.5rem; font-size: 1rem;">You will receive: <strong>0</strong> Units</div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label class="stat-label" for="phone">M-Pesa Phone Number</label>
                        <input type="text" id="phone" name="phone" class="buy-input" placeholder="2547XXXXXXXX" required>
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Format: 2547... or 2541... (12 digits)</small>
                    </div>

                    <div id="status-msg" style="padding: 1rem; border-radius: 12px; display: none;"></div>

                    <button type="submit" class="btn-primary" style="justify-content: center; font-size: 1.05rem; padding: 0.9rem;">
                        <i class="fas fa-mobile-alt"></i> Pay with M-Pesa
                    </button>
                </form>
            </div>

            <!-- INFO PANEL -->
            <div class="glass-card">
                <h3 style="color: var(--text-primary);">How it Works</h3>
                <ol style="color: var(--text-muted); line-height: 1.9; font-size: 0.9rem; padding-left: 1.25rem; margin-top: 1rem;">
                    <li>Enter the amount in KES and your M-Pesa number.</li>
                    <li>Click <strong style="color: var(--text-primary);">Pay with M-Pesa</strong> to initiate an STK push.</li>
                    <li>A prompt will appear on your phone — enter your PIN.</li>
                    <li>Units are credited to your account <strong style="color: var(--text-primary);">instantly</strong> upon confirmation.</li>
                </ol>

                <div style="margin-top: 2rem; display: flex; flex-direction: column; gap: 1rem;">
                    <div style="padding: 1rem; border-radius: 12px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.25);">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
                            <i class="fas fa-shield-alt" style="color: var(--primary);"></i>
                            <strong style="color: var(--text-primary);">Secure Payments</strong>
                        </div>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">Encrypted STK push directly to your phone. No card details stored.</span>
                    </div>
                    <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25);">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
                            <i class="fas fa-bolt" style="color: var(--accent-success);"></i>
                            <strong style="color: var(--text-primary);">Instant Crediting</strong>
                        </div>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">Units are added to your balance in real-time after payment confirmation.</span>
                    </div>
                </div>

                <!-- Pricing summary -->
                <div style="margin-top: 2rem; padding: 1.25rem; background: var(--card-bg); border: 1px solid var(--border-light); border-radius: 14px;">
                    <h4 style="margin: 0 0 1rem; font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Your Pricing</h4>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.4rem;">
                        <span style="color: var(--text-muted);">Units per KES 1</span>
                        <strong style="color: var(--primary);"><?php echo round($clientRate, 4); ?> Units</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.4rem;">
                        <span style="color: var(--text-muted);">KES 100 buys</span>
                        <strong style="color: var(--text-primary);"><?php echo number_format($clientRate * 100, 2); ?> Units</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                        <span style="color: var(--text-muted);">KES 1,000 buys</span>
                        <strong style="color: var(--text-primary);"><?php echo number_format($clientRate * 1000, 2); ?> Units</strong>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/main.js"></script>
    <script>
        const CLIENT_RATE = <?php echo $clientRate; ?>;
        const amountInput = document.getElementById('amount');
        const unitsPreview = document.getElementById('units-preview');
        const paymentModal = document.getElementById('payment-modal');
        let currentDisplayedUnits = 0;
        let animationFrame;
        let pollTimer;
        let countdownTimer;

        function setAmount(amount) {
            amountInput.value = amount;
            document.querySelectorAll('.package-btn').forEach(btn => btn.classList.remove('selected'));
            event.target.classList.add('selected');
            animateUnits(Math.floor(amount * CLIENT_RATE));
        }

        function animateUnits(target) {
            if (animationFrame) cancelAnimationFrame(animationFrame);
            const start = currentDisplayedUnits;
            const duration = 600;
            const startTime = performance.now();
            unitsPreview.style.transform = 'scale(1.05)';
            setTimeout(() => { unitsPreview.style.transform = 'scale(1)'; }, 120);

            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const ease = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                currentDisplayedUnits = start + (target - start) * ease;
                const display = Math.floor(currentDisplayedUnits).toLocaleString();
                unitsPreview.innerHTML = `You will receive: <span style="font-weight: 800; font-size: 1.4rem; font-family: var(--font-display); color: var(--text-primary);">${display}</span> Units`;
                if (progress < 1) {
                    animationFrame = requestAnimationFrame(update);
                } else {
                    currentDisplayedUnits = target;
                    unitsPreview.innerHTML = `You will receive: <span style="font-weight: 800; font-size: 1.4rem; font-family: var(--font-display); color: var(--text-number);">${target.toLocaleString()}</span> Units`;
                }
            }
            animationFrame = requestAnimationFrame(update);
        }

        amountInput.addEventListener('input', () => {
            const amount = parseFloat(amountInput.value) || 0;
            animateUnits(Math.floor(amount * CLIENT_RATE));
            document.querySelectorAll('.package-btn').forEach(btn => btn.classList.remove('selected'));
        });

        async function checkPaymentStatus(checkoutId) {
            try {
                const res = await fetch(`api/v1/check_payment.php?checkout_id=${checkoutId}`);
                const data = await res.json();
                if (data.status === 'success') {
                    if (data.payment_status === 'completed') {
                        clearInterval(pollTimer);
                        clearInterval(countdownTimer);
                        showPaymentSuccess();
                    } else if (data.payment_status === 'failed' || data.payment_status === 'cancelled') {
                        clearInterval(pollTimer);
                        clearInterval(countdownTimer);
                        showPaymentError("Payment failed or was cancelled.");
                    }
                }
            } catch (err) {
                console.error("Polling error:", err);
            }
        }

        function showPaymentSuccess() {
            document.getElementById('modal-status-icon').innerHTML = '<div class="result-icon success"><i class="fas fa-check"></i></div>';
            document.getElementById('modal-title').innerText = "Payment Successful!";
            document.getElementById('modal-text').innerText = "Your units have been credited instantly. Redirecting...";
            document.getElementById('modal-timer').style.display = 'none';
            if (typeof showToast === 'function') showToast('Units credited to your account!', 'success');
            setTimeout(() => { window.location.href = 'index.php'; }, 3000);
        }

        function showPaymentError(msg) {
            document.getElementById('modal-status-icon').innerHTML = '<div class="result-icon error"><i class="fas fa-times"></i></div>';
            document.getElementById('modal-title').innerText = "Payment Failed";
            document.getElementById('modal-text').innerText = msg;
            document.getElementById('modal-timer').innerHTML = '<button onclick="location.reload()" class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; margin-top: 1rem;">Try Again</button>';
        }

        document.getElementById('topup-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const statusMsg = document.getElementById('status-msg');

            statusMsg.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing...';

            const payload = {
                amount: amountInput.value,
                phone: document.getElementById('phone').value
            };

            try {
                const res = await fetch('api/v1/stk_push.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (data.status === 'success') {
                    paymentModal.style.display = 'flex';

                    let secondsLeft = 60;
                    countdownTimer = setInterval(() => {
                        secondsLeft--;
                        const timerEl = document.getElementById('timer-val');
                        if (timerEl) timerEl.innerText = secondsLeft;
                        if (secondsLeft <= 0) {
                            clearInterval(pollTimer);
                            clearInterval(countdownTimer);
                            showPaymentError("Payment timed out. If you paid, please wait a moment and refresh.");
                        }
                    }, 1000);

                    pollTimer = setInterval(() => {
                        checkPaymentStatus(data.checkout_id);
                    }, 2500);

                } else {
                    statusMsg.style.display = 'block';
                    statusMsg.style.background = 'rgba(239, 68, 68, 0.1)';
                    statusMsg.style.border = '1px solid rgba(239,68,68,0.3)';
                    statusMsg.style.color = 'var(--accent-error)';
                    statusMsg.style.padding = '0.75rem 1rem';
                    statusMsg.style.borderRadius = '10px';
                    statusMsg.innerText = `Error: ${data.message || 'Unknown error occurred.'}`;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-mobile-alt"></i> Pay with M-Pesa';
                }
            } catch (err) {
                statusMsg.style.display = 'block';
                statusMsg.style.background = 'rgba(239, 68, 68, 0.1)';
                statusMsg.style.border = '1px solid rgba(239,68,68,0.3)';
                statusMsg.style.color = 'var(--accent-error)';
                statusMsg.style.padding = '0.75rem 1rem';
                statusMsg.style.borderRadius = '10px';
                statusMsg.innerText = 'Network error. Please check your connection and try again.';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-mobile-alt"></i> Pay with M-Pesa';
            }
        });
    </script>

    <?php include 'includes/theme-center.php'; ?>
</body>
</html>
