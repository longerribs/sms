<?php
/**
 * sms/register.php
 * 
 * Standalone Client Registration Page
 */
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['CLAYON_CLIENT_ID'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/src/Auth.php';
$db = getClayonDb();

$error = '';
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email address already exists.';
            } else {
                // Normalize phone to International format (e.g. 254...)
                $phoneClean = preg_replace('/[^0-9]/', '', $phone);
                if (substr($phoneClean, 0, 1) === '0') {
                    $phoneClean = '254' . substr($phoneClean, 1);
                }
                
                if (strlen($phoneClean) < 10 || strlen($phoneClean) > 15) {
                    $error = 'Invalid phone number. Use standard formats (e.g. 0712345678 or 254712345678).';
                } else {
                    $db->beginTransaction();

                    // 1. Insert user client record (plan_id = 1 = Default)
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO clients (name, email, phone, password_hash, plan_id, status) VALUES (?, ?, ?, ?, 1, 'active')");
                    $stmt->execute([$name, $email, $phoneClean, $passwordHash]);
                    $clientId = $db->lastInsertId();

                    // 2. Initialize wallet account
                    $stmt = $db->prepare("INSERT INTO wallet_accounts (client_id, balance_units, reserved_units) VALUES (?, 0.0000, 0.0000)");
                    $stmt->execute([$clientId]);

                    // 3. Generate initial API key
                    $auth = new Auth($db);
                    $plainKey = $auth->generateKey($clientId);

                    // 4. Log audit log
                    $stmt = $db->prepare("INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, metadata) VALUES ('client', ?, 'account_created', 'client', ?, ?)");
                    $stmt->execute([$clientId, $clientId, json_encode(['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR']])]);

                    $db->commit();

                    // Auto login
                    $_SESSION['CLAYON_CLIENT_ID'] = $clientId;
                    $_SESSION['CLAYON_CLIENT_NAME'] = $name;
                    $_SESSION['CLAYON_CLIENT_EMAIL'] = $email;
                    
                    // Hold the plain API key in session to display once on index.php / developer.php
                    $_SESSION['CLAYON_NEW_API_KEY'] = $plainKey;

                    header('Location: index.php?welcome=1');
                    exit;
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Registration error: " . $e->getMessage());
            $error = 'An error occurred while creating your account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Clayon SMS</title>
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .auth-card {
            width: 100%;
            max-width: 500px;
            padding: 3rem 2.5rem;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .auth-header h2 {
            font-family: var(--font-display);
            font-size: 2.2rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            padding: 0.75rem 1rem;
            border-radius: 12px;
            color: white;
            font-family: var(--font-body);
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 10px var(--primary-glow);
            background: rgba(255, 255, 255, 0.08);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .auth-footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        .auth-footer a:hover {
            color: var(--secondary);
        }
    </style>
</head>
<body>
    <div class="aurora">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <div class="auth-container">
        <div class="glass-card auth-card">
            <div class="auth-header">
                <h2>Create Account</h2>
                <p style="color: var(--text-muted);">Get started with Clayon SMS Platform</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="name">Full Name</label>
                    <input class="form-control" type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="John Doe" required autocomplete="name">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="name@domain.com" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input class="form-control" type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="0712345678" required autocomplete="tel">
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 1rem; padding: 0.85rem;">
                    <i class="fas fa-user-plus"></i> Sign Up & Start
                </button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>
