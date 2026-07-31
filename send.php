<?php
/**
 * clayon/send.php
 * 
 * SMS Composition Page for Clayon Reseller Platform.
 * Locked to TALKSASA sender ID.
 */
require_once __DIR__ . '/auth_bridge.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/src/ThemeManager.php';

$db = getClayonDb();
$clientId = $_SESSION['CLAYON_CLIENT_ID'];

$themeManager = new ThemeManager($clientId);
$cssVariables = $themeManager->generateCSSVariables();

// Fetch balance
$stmt = $db->prepare("SELECT balance_units FROM wallet_accounts WHERE client_id = ?");
$stmt->execute([$clientId]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $wallet ? (float)$wallet['balance_units'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send SMS | Clayon</title>
    
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Dynamic Theme CSS -->
    <style id="theme-dynamic-css">
        <?php echo $cssVariables; ?>
    </style>
    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(12px);
            z-index: 1150;
            display: none;
            justify-content: center;
            align-items: center;
            pointer-events: auto;
        }

        .modal-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            padding: 3.5rem;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.7);
        }

        .spinner-ring {
            width: 70px;
            height: 70px;
            border: 4px solid rgba(99, 102, 241, 0.1);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            margin: 0 auto 2rem;
            animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }

        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .status-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 2rem;
            animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .icon-success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .icon-queued { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .icon-error { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        @keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        
        .progress-bar-container {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            margin-top: 2rem;
            overflow: hidden;
        }
        
        .progress-bar-fill {
            height: 100%;
            width: 0%;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .input-error {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: none;
        }

        .btn-primary:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            filter: grayscale(1);
        }
    </style>
</head>
<body>
    <div class="aurora">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <!-- Monitoring Modal -->
    <div id="monitoring-modal" class="modal-overlay">
        <div class="modal-card">
            <div id="modal-status-container">
                <div class="spinner-ring"></div>
            </div>
            <h2 id="modal-title" style="font-family: 'Clash Display'; margin-bottom: 1rem;">Sending Message</h2>
            <p id="modal-text" style="color: var(--text-muted); line-height: 1.5;">Initiating connection to SMS gateway...</p>
            
            <div class="progress-bar-container">
                <div id="progress-bar" class="progress-bar-fill"></div>
            </div>

            <div id="modal-footer" style="margin-top: 2rem; display: none;">
                <button onclick="closeMonitoringModal()" class="btn-primary" style="padding: 0.75rem 1.5rem; font-size: 0.9rem;">Back to Composer</button>
            </div>
        </div>
    </div>

    <?php include 'includes/header.php'; ?>

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header style="margin-bottom: 2rem;">
            <h1>Compose Message</h1>
            <p style="color: var(--text-muted);">Send SMS instantly to your contacts</p>
        </header>

        <div class="dashboard-grid">
            <div class="glass-card">
                <form id="send-sms-form" style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label class="stat-label">Sender ID</label>
                        <input type="text" value="TALKSASA" disabled style="padding: 0.75rem 1rem; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: var(--text-muted); cursor: not-allowed;">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Default verified sender ID.</small>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label class="stat-label">Recipient Phone</label>
                        <input type="text" id="recipient-input" name="recipient" placeholder="e.g. 07XXXXXXXX or 01XXXXXXXX" required style="padding: 1rem; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; font-family: var(--font-body);">
                        <div id="phone-error" class="input-error">Invalid phone format. (07/01... requires 10 digits, 254... requires 12 digits)</div>
                        <small id="phone-hint" style="color: var(--text-muted); font-size: 0.75rem;">Use 07... / 01... or 254... format.</small>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label class="stat-label">Message Content</label>
                        <textarea name="message" id="message-body" rows="5" placeholder="Type your message here..." required style="padding: 1rem; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; font-family: var(--font-body); resize: none;"></textarea>
                        <div id="message-error" class="input-error">Please enter a message body.</div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted);">
                            <span id="char-count">0 characters</span>
                            <span id="segment-count">0 segments (0 units)</span>
                        </div>
                    </div>

                    <div id="status-msg" style="padding: 1rem; border-radius: 12px; display: none;"></div>

                    <button type="submit" id="submit-btn" class="btn-primary" disabled style="justify-content: center; font-size: 1.1rem; padding: 1rem;">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>

            <div class="glass-card">
                <h3>Balance Info</h3>
                <div style="margin-top: 1.5rem;">
                    <div class="stat-card">
                        <span class="stat-label">Units Available</span>
                        <span id="display-balance" class="stat-value" style="color: var(--primary);"><?php echo number_format($balance, 2); ?></span>
                    </div>
                    <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 1.5rem 0;">
                    <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                        Each message segment (60 characters) consumes 1 unit. Multi-segment messages follow reseller pricing.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/main.js"></script>
    <script>
        const messageInput = document.getElementById('message-body');
        const charCount = document.getElementById('char-count');
        const segmentCount = document.getElementById('segment-count');
        const monitorModal = document.getElementById('monitoring-modal');
        const progressBar = document.getElementById('progress-bar');
        const modalTitle = document.getElementById('modal-title');
        const modalText = document.getElementById('modal-text');
        const modalStatusContainer = document.getElementById('modal-status-container');
        const modalFooter = document.getElementById('modal-footer');
        const recipientInput = document.getElementById('recipient-input');
        const phoneError = document.getElementById('phone-error');
        const messageError = document.getElementById('message-error');
        const submitBtn = document.getElementById('submit-btn');

        function closeMonitoringModal() {
            monitorModal.style.display = 'none';
        }

        function validateForm() {
            const recipient = recipientInput.value.replace(/\D/g, '');
            const message = messageInput.value.trim();
            let phoneValid = false;

            if (recipient.startsWith('0')) {
                if (recipient.length === 10) phoneValid = true;
            } else if (recipient.startsWith('254')) {
                if (recipient.length === 12) phoneValid = true;
            }

            const messageValid = message.length > 0;
            
            submitBtn.disabled = !(phoneValid && messageValid);
        }

        recipientInput.addEventListener('input', () => {
            const val = recipientInput.value.replace(/\D/g, '');
            let isInvalid = false;

            if (val.startsWith('0')) {
                if (val.length !== 10) isInvalid = true;
                const normalized = '254' + val.substring(1);
                document.getElementById('phone-hint').innerText = `Normalization: Will be sent as ${normalized}`;
                document.getElementById('phone-hint').style.color = 'var(--primary)';
            } else if (val.startsWith('254')) {
                if (val.length !== 12) isInvalid = true;
                document.getElementById('phone-hint').innerText = `International format detected.`;
                document.getElementById('phone-hint').style.color = '#10b981';
            } else {
                isInvalid = true;
                document.getElementById('phone-hint').innerText = `Use 07... / 01... or 254... format.`;
                document.getElementById('phone-hint').style.color = '#f59e0b';
            }

            if (isInvalid && val.length > 0) {
                phoneError.style.display = 'block';
                recipientInput.style.borderColor = '#ef4444';
            } else {
                phoneError.style.display = 'none';
                recipientInput.style.borderColor = 'var(--glass-border)';
            }
            validateForm();
        });

        messageInput.addEventListener('input', () => {
            const len = messageInput.value.length;
            const segments = Math.ceil(len / 60);
            let units = segments;
            if (segments > 2) {
                units = 2 + ((segments - 2) * 2);
            }
            
            charCount.innerText = `${len} characters`;
            segmentCount.innerText = `${segments} segments (${units} units)`;

            if (len > 0) {
                messageError.style.display = 'none';
                messageInput.style.borderColor = 'var(--glass-border)';
            }
            validateForm();
        });

        document.getElementById('send-sms-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const recipient = recipientInput.value.replace(/\D/g, '');
            const message = messageInput.value.trim();

            const payload = {
                recipient: recipient,
                message: message,
                sender_id: 'TALKSASA'
            };

            monitorModal.style.display = 'flex';
            modalStatusContainer.innerHTML = '<div class="spinner-ring"></div>';
            modalTitle.innerText = "Sending Message";
            modalText.innerText = "Connecting to SMS gateway...";
            progressBar.style.width = '10%';
            modalFooter.style.display = 'none';

            try {
                setTimeout(() => { progressBar.style.width = '40%'; modalText.innerText = "Authenticating and checking balance..."; }, 400);
                
                const res = await fetch('api/v1/send_sms.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const data = await res.json();
                progressBar.style.width = '100%';

                if (data.status === 'success' || data.success === true) {
                    if (data.sms_status === 'pending') {
                        modalStatusContainer.innerHTML = '<div class="status-icon icon-queued"><i class="fas fa-clock"></i></div>';
                        modalTitle.innerText = "Message Queued";
                        modalText.innerText = `Successfully normalized to ${data.recipient}. Your message will be dispatched shortly by our background worker.`;
                    } else {
                        modalStatusContainer.innerHTML = '<div class="status-icon icon-success"><i class="fas fa-check"></i></div>';
                        modalTitle.innerText = "Accepted by Provider!";
                        modalText.innerText = `Message sent and accepted by provider for ${data.recipient}.`;
                    }
                    e.target.reset();
                    fetchBalance();
                    charCount.innerText = "0 characters";
                    segmentCount.innerText = "0 segments (0 units)";
                    validateForm(); // Reset button to disabled
                } else {
                    modalStatusContainer.innerHTML = '<div class="status-icon icon-error"><i class="fas fa-times"></i></div>';
                    modalTitle.innerText = "Sending Failed";
                    modalText.innerText = data.message || "An unexpected error occurred.";
                }
                modalFooter.style.display = 'block';

            } catch (err) {
                modalStatusContainer.innerHTML = '<div class="status-icon icon-error"><i class="fas fa-wifi"></i></div>';
                modalTitle.innerText = "Network Error";
                modalText.innerText = "Could not connect to the API. Please check your internet connection.";
                modalFooter.style.display = 'block';
            }
        });

        async function fetchBalance() {
            try {
                const res = await fetch('api/v1/balance.php');
                const data = await res.json();
                if (data.status === 'success') {
                    const balEl = document.getElementById('display-balance');
                    if (balEl) balEl.innerText = parseFloat(data.balance).toLocaleString(undefined, {minimumFractionDigits: 2});
                }
            } catch (e) {}
        }
    </script>

    <?php include 'includes/theme-center.php'; ?>
</body>
</html>


