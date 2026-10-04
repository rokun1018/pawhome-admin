<?php
// Adopter: choose a new password from the emailed link
require __DIR__ . '/user-common.php';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$stmt = $pdo->prepare('SELECT id FROM adopters WHERE reset_token = ? AND reset_expires > NOW()');
$stmt->execute([hash('sha256', (string)$token)]);
$id = $token ? $stmt->fetchColumn() : false;

$error = '';
if ($id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $new = $_POST['password'] ?? '';
    if (strlen($new) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($new !== ($_POST['confirm_password'] ?? '')) $error = 'Passwords do not match.';
    else {
        $pdo->prepare('UPDATE adopters SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $id]);
        user_flash('Your password has been changed. Log in with the new one.');
        redirect('login.php');
    }
}
user_head('Choose a New Password', ['login.css']);
?>
<body>
    <div class="login-page">
        <div class="login-left">
            <div class="login-container">
                <div class="login-header">
                    <a class="logo" href="index.php" style="text-decoration:none"><span class="logo-icon">🐾</span><span class="logo-text">PawHome</span></a>
                    <p class="tagline">Rescue & Adoption</p>
                </div>
                <div class="welcome-section"><h1>Choose a New Password</h1></div>
                <?php if (!$id): ?>
                    <div class="error-message" role="alert">This link has expired or was already used. Please ask for a new one.</div>
                    <a href="forgot-password.php" class="btn-login" style="display:block;text-align:center;text-decoration:none">Send a New Link</a>
                <?php else: ?>
                    <form class="login-form" method="post" action="reset-password.php">
                        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
                        <div class="form-group"><label for="password">New Password</label><input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required autofocus></div>
                        <div class="form-group"><label for="confirm">Confirm New Password</label><input type="password" id="confirm" name="confirm_password" autocomplete="new-password" required></div>
                        <?php if ($error): ?><div class="error-message" role="alert"><?= e($error) ?></div><?php endif; ?>
                        <button type="submit" class="btn-login">Save New Password</button>
                    </form>
                <?php endif; ?>
                <div class="signup-link"><a href="login.php">Back to login</a></div>
            </div>
        </div>
        <div class="login-right"><div class="login-image"><div class="image-overlay">
            <h2>Bringing hearts together, one paw at a time.</h2>
        </div></div></div>
    </div>
</body>
</html>
