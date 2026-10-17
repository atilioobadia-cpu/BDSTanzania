<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

if (bdst_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$flash = bdst_flash_consume();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {
        $hash = bdst_admin_hash();
        if ($hash !== '' && hash_equals(bdst_admin_username(), $username) && password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_last_activity'] = time();
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid username or password.';
    } catch (Throwable $e) {
        error_log('Admin login error: ' . $e->getMessage());
        $error = 'Login is unavailable. Please check the server configuration.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bdst_admin_title('Login') ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/fav.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= bdst_e(bdst_admin_css()) ?>">
</head>
<body class="login-body">
    <main class="login-card">
        <div class="login-card-head">
            <div class="login-brand-img">
                <img src="../assets/images/logos/logo.webp" alt="BDS Tanzania" width="136" height="64" decoding="async">
            </div>
            <h1>Welcome back</h1>
            <p>Sign in to the admin console to continue.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= bdst_e($error) ?></span>
            </div>
        <?php elseif ($flash !== null): ?>
            <?php
            // The only flash that can reach this page is the idle sign-out set by
            // bdst_require_login(). That is a calm heads-up rather than a failure,
            // so anything that is not an explicit success is shown in amber.
            $flashClass = $flash['type'] === 'success' ? 'alert-success' : 'alert-notice';
            ?>
            <div class="alert <?= $flashClass ?>" role="status">
                <?php if ($flashClass === 'alert-success'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <?php endif; ?>
                <span><?= bdst_e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php" autocomplete="off" id="loginForm">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="field">
                <label for="passwordMask">Password</label>
                <div class="password-wrap">
                    <input type="text" id="passwordMask" class="password-mask" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" autocomplete="off" aria-label="Password">
                    <input type="password" id="password" name="password" class="sr-only" autocomplete="off" tabindex="-1" aria-hidden="true">
                    <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password"></button>
                </div>
            </div>
            <button type="submit" id="loginBtn" class="btn btn-primary btn-block">
                <span class="spinner"></span>
                <span class="btn-label">Sign In</span>
            </button>
        </form>

        <p class="login-footer">
            <a href="../index.html">&larr; Back to website</a>
        </p>
    </main>

    <script>
    (function () {
        var iconEye = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        var iconEyeOff = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

        var mask = document.getElementById('passwordMask');
        var real = document.getElementById('password');
        var toggle = document.getElementById('togglePassword');
        var form = document.getElementById('loginForm');
        var btn = document.getElementById('loginBtn');
        var revealed = false;

        if (!mask || !real) { return; }

        function reMask() {
            var caret = mask.selectionStart || mask.value.length;
            var len = mask.value.length;
            mask.value = new Array(len + 1).join('*');
            var pos = Math.min(caret, mask.value.length);
            mask.setSelectionRange(pos, pos);
        }

        mask.addEventListener('input', function () {
            real.value = mask.value;
            if (!revealed) { reMask(); }
        });

        if (toggle) {
            toggle.innerHTML = iconEye;
            toggle.addEventListener('click', function () {
                revealed = !revealed;
                if (revealed) {
                    var caret = mask.selectionStart || mask.value.length;
                    mask.value = real.value;
                    var pos = Math.min(caret, mask.value.length);
                    mask.setSelectionRange(pos, pos);
                    toggle.innerHTML = iconEyeOff;
                    toggle.setAttribute('aria-label', 'Hide password');
                } else {
                    reMask();
                    toggle.innerHTML = iconEye;
                    toggle.setAttribute('aria-label', 'Show password');
                }
                mask.focus();
            });
        }

        if (form && btn) {
            form.addEventListener('submit', function (e) {
                if (btn.disabled) { e.preventDefault(); return; }
                if (real.value === '') {
                    e.preventDefault();
                    mask.focus();
                    return;
                }
                btn.classList.add('loading');
                btn.disabled = true;
                var label = btn.querySelector('.btn-label');
                if (label && label.textContent === 'Sign In') {
                    label.textContent = 'Signing in...';
                }
            });
        }
    })();
    </script>
</body>
</html>
