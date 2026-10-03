<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';

if (current_user()) redirect('index.php');

$error = false;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $stmt = $pdo->prepare('SELECT id, full_name, role, status, password FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active' && password_verify($_POST['password'] ?? '', $user['password'])) {
        session_regenerate_id(true);
        unset($user['password']);
        $_SESSION['user'] = $user;
        $_SESSION['last_seen'] = time();
        $_SESSION['remember'] = !empty($_POST['remember']);
        if ($_SESSION['remember']) {
            // Keep the sign-in cookie for 30 days instead of until the browser closes
            setcookie(session_name(), session_id(), [
                'expires' => time() + REMEMBER_SECONDS, 'path' => '/',
                'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
        $pdo->prepare('UPDATE users SET last_active = NOW() WHERE id = ?')->execute([$user['id']]);
        log_activity('Signed in');
        redirect('index.php');
    }
    $error = true;
}

auth_top('Sign in');
?>
            <h2>Sign in</h2>
            <p class="subtitle">Use your PawHome staff email and password.</p>

            <?php if (isset($_GET['reset'])): ?>
                <div class="alert alert-success" role="status"><?= icon('check') ?><span>Your password has been changed. Sign in with the new one.</span></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error" role="alert"><?= icon('x') ?><span>That email and password don't match. Check both and try again.</span></div>
            <?php endif; ?>

            <form id="loginForm" method="post" action="login.php" data-server>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-icon"><?= icon('mail') ?><input class="form-control" type="email" id="email" name="email" placeholder="name@pawhome.org" autocomplete="username" required autofocus value="<?= e($email) ?>"></div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon has-toggle"><?= icon('lock') ?><input class="form-control" type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
                        <button type="button" class="toggle-password" data-password-toggle aria-label="Show password"><?= icon('eye') ?></button></div>
                </div>
                <div class="auth-options">
                    <label class="checkbox"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                    <a href="forgot-password.php">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Sign in</button>
            </form>

            <p class="auth-footer">Staff access only. Trouble signing in? Ask your shelter's Super Admin.</p>
<?php auth_bottom(); ?>
