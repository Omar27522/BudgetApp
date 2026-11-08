<?php
// login.php - Secure 2-Step Honeypot Authentication with Progressive Rate Limiting
require_once __DIR__ . '/src/Auth/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$hasUsers = Auth::hasUsers();
$error = '';
$success = '';
$remainingLockout = Auth::getRemainingLockout($ip);

// Auto-redirect if already authenticated
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = 'You have been securely signed out.';
}

// 1. Initial First-Time Account Setup (if no users in database)
if (!$hasUsers && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setup_user') {
    $newUsername = trim($_POST['username'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');

    if ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $reg = Auth::registerUser($newUsername, $newPassword, $displayName);
        if ($reg['success']) {
            // Automatically log in
            Auth::verifyLogin($newUsername, $newPassword, $ip);
            header("Location: index.php?msg=" . urlencode("Master account created successfully!"));
            exit;
        } else {
            $error = $reg['error'] ?? 'Could not create account.';
        }
    }
}

// 2. Login POST Handler (Step 2 Submit)
if ($hasUsers && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please provide both username and password.';
    } else {
        $result = Auth::verifyLogin($username, $password, $ip);
        if ($result['success']) {
            $returnUrl = $_POST['return_url'] ?? ($_GET['return_url'] ?? 'index.php');
            if (empty($returnUrl) || str_starts_with($returnUrl, '//') || !preg_match('#^(/[a-zA-Z0-9_\-\./\?=&%#]+|\w+\.php(\?[a-zA-Z0-9_\-\./\?=&%#]+)?)$#', $returnUrl)) {
                $returnUrl = 'index.php';
            }
            header("Location: " . $returnUrl);
            exit;
        } else {
            $error = $result['error'] ?? 'Invalid credentials.';
            $remainingLockout = $result['remaining'] ?? 5;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !$hasUsers ? 'First-Time Setup' : 'Sign In' ?> &bull; Personal Finance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f1f5f9;
            padding: 16px;
        }
        .auth-wrapper {
            width: 100%;
            max-width: 420px;
        }
        .auth-card {
            background: #ffffff;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 32px 28px;
            border: 1px solid var(--border-color);
        }
        .auth-brand {
            text-align: center;
            margin-bottom: 24px;
        }
        .auth-brand h1 {
            font-size: 1.4rem;
            color: var(--primary);
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .auth-brand p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .step-pill {
            display: inline-block;
            background: #e0e7ff;
            color: var(--primary);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            margin-bottom: 12px;
        }
        .user-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            font-size: 0.88rem;
        }
        .user-preview .change-link {
            font-size: 0.78rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        .user-preview .change-link:hover {
            text-decoration: underline;
        }
        .lockout-banner {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #b45309;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 16px;
            text-align: center;
        }
        .btn-full {
            width: 100%;
            padding: 10px 14px;
            font-size: 0.95rem;
            font-weight: 700;
        }
        .pw-wrapper {
            position: relative;
        }
        .pw-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 0.9rem;
            padding: 4px;
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand">
            <h1>🔒 Personal Finance</h1>
            <p><?= !$hasUsers ? 'First-Time Setup: Set Master Credentials' : 'Secure Private Portal' ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:10px 12px; border-radius:6px; font-size:0.85rem; margin-bottom:16px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="padding:10px 12px; font-size:0.85rem; margin-bottom:16px;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($remainingLockout > 0): ?>
            <div class="lockout-banner" id="lockout-box">
                ⏳ Rate limit active: Please wait <span id="countdown"><?= $remainingLockout ?></span>s before next attempt.
            </div>
        <?php endif; ?>

        <?php if (!$hasUsers): ?>
            <!-- -------------------------------------------------------- -->
            <!-- FIRST-TIME SETUP: CREATE ADMIN CREDENTIALS              -->
            <!-- -------------------------------------------------------- -->
            <form method="POST" action="login.php">
                <input type="hidden" name="action" value="setup_user">

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" for="setup_username">Choose Secret Username</label>
                    <input type="text" id="setup_username" name="username" class="form-control" placeholder="e.g. mysecretname" required autofocus>
                    <span style="font-size:0.75rem; color:var(--text-muted); display:block; margin-top:3px;">
                        Keep it private. The login honeypot will hide this username from brute-force bots.
                    </span>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" for="setup_display">Display Name (Optional)</label>
                    <input type="text" id="setup_display" name="display_name" class="form-control" placeholder="e.g. John">
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" for="setup_password">Strong Password (min 8 chars)</label>
                    <div class="pw-wrapper">
                        <input type="password" id="setup_password" name="password" class="form-control" required minlength="8" placeholder="••••••••••••">
                        <button type="button" class="pw-toggle" onclick="togglePasswordVisibility('setup_password')">👁️</button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label class="form-label" for="setup_confirm">Confirm Password</label>
                    <input type="password" id="setup_confirm" name="confirm_password" class="form-control" required minlength="8" placeholder="••••••••••••">
                </div>

                <button type="submit" class="btn btn-primary btn-full">Create Account & Enter App ➔</button>
            </form>

        <?php else: ?>
            <!-- -------------------------------------------------------- -->
            <!-- 2-STEP HONEYPOT LOGIN: USERNAME FIRST, THEN PASSWORD     -->
            <!-- -------------------------------------------------------- -->
            <form method="POST" action="login.php" id="login-form">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($_GET['return_url'] ?? '') ?>">

                <!-- STEP 1: USERNAME (ALWAYS ACCEPTED AS HONEYPOT) -->
                <div id="step-username">
                    <span class="step-pill">Step 1 of 2</span>
                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="form-label" for="login_username">Username</label>
                        <input type="text" id="login_username" name="username" class="form-control" placeholder="Enter your username" required autofocus autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <button type="button" class="btn btn-primary btn-full" id="btn-next" onclick="proceedToPassword()">
                        Continue ➔
                    </button>
                </div>

                <!-- STEP 2: PASSWORD -->
                <div id="step-password" style="display:none;">
                    <span class="step-pill">Step 2 of 2</span>
                    
                    <div class="user-preview">
                        <span>Signing in as: <strong id="preview-user">user</strong></span>
                        <span class="change-link" onclick="goBackToUsername()">Change</span>
                    </div>

                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="form-label" for="login_password">Password</label>
                        <div class="pw-wrapper">
                            <input type="password" id="login_password" name="password" class="form-control" placeholder="••••••••••••" autocomplete="current-password">
                            <button type="button" class="pw-toggle" onclick="togglePasswordVisibility('login_password')">👁️</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full" id="btn-submit">
                        Sign In ➔
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // 2-Step Honeypot Logic
    function proceedToPassword() {
        const usernameInput = document.getElementById('login_username');
        const usernameVal = usernameInput.value.trim();
        if (!usernameVal) {
            usernameInput.focus();
            return;
        }

        // Always accept username without querying the server (prevents username enumeration/probing)
        document.getElementById('preview-user').textContent = usernameVal;
        document.getElementById('step-username').style.display = 'none';
        document.getElementById('step-password').style.display = 'block';

        const pwInput = document.getElementById('login_password');
        pwInput.focus();
    }

    function goBackToUsername() {
        document.getElementById('step-password').style.display = 'none';
        document.getElementById('step-username').style.display = 'block';
        document.getElementById('login_username').focus();
    }

    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.type = field.type === 'password' ? 'text' : 'password';
        }
    }

    // Enter key support in Step 1
    const usernameField = document.getElementById('login_username');
    if (usernameField) {
        usernameField.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                proceedToPassword();
            }
        });
    }

    // If an error occurred and username was already filled, advance directly to Step 2
    <?php if ($hasUsers && !empty($_POST['username'])): ?>
        proceedToPassword();
    <?php endif; ?>

    // Rate-limit countdown timer
    let remaining = <?= (int)$remainingLockout ?>;
    if (remaining > 0) {
        const btnSubmit = document.getElementById('btn-submit');
        if (btnSubmit) btnSubmit.disabled = true;

        const countdownSpan = document.getElementById('countdown');
        const lockoutBox = document.getElementById('lockout-box');

        const interval = setInterval(() => {
            remaining--;
            if (countdownSpan) countdownSpan.textContent = remaining;
            if (remaining <= 0) {
                clearInterval(interval);
                if (btnSubmit) btnSubmit.disabled = false;
                if (lockoutBox) lockoutBox.style.display = 'none';
            }
        }, 1000);
    }
</script>

</body>
</html>
