<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

if (bdst_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {
        $hash = bdst_admin_hash();
        if ($hash !== '' && hash_equals(bdst_admin_username(), $username) && password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
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
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-body">
    <aside class="login-side">
        <div class="login-side-inner">
            <span class="login-eyebrow">BDS Tanzania</span>
            <h1>Manage your updates from one clean place.</h1>
            <p>Publish, edit and control the news, announcements and updates shown on the BDS Tanzania website — securely and without fuss.</p>
        </div>
    </aside>

    <main class="login-main">
        <div class="login-card-head">
            <div class="login-brand-img">
                <img src="../assets/images/logos/logo.webp" alt="BDS Tanzania Logo" width="150" height="56">
            </div>
            <h2>Welcome back</h2>
            <p>Sign in to the admin console to continue.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= bdst_e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="index.php" autocomplete="off">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <p class="login-footer">
            <a href="../index.html">&larr; Back to website</a>
        </p>
    </main>
</body>
</html>
