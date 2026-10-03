<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()');
$stmt->execute([hash('sha256', $token)]);
$userId = $token ? $stmt->fetchColumn() : false;

$error = '';
if ($userId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $new = $_POST['new_password'] ?? '';
    if (strlen($new) < 8) {
        $error = 'Use at least 8 characters.';
    } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
        $error = 'The two passwords do not match.';
    } else {
        $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        $pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$userId]); // sign out everywhere
        redirect('login.php?reset=1');
    }
}

auth_top('Choose a new password');
?>
            <h2>Choose a new password</h2>
            <?php if (!$userId): ?>
                <div class="alert alert-error" role="alert"><?= icon('x') ?><span>This reset link has expired or was already used. Ask for a new one.</span></div>
                <a href="forgot-password.php" class="btn btn-primary btn-lg btn-block">Send a new link</a>
            <?php else: ?>
                <p class="subtitle">You'll use it the next time you sign in.</p>
                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert"><?= icon('x') ?><span><?= e($error) ?></span></div>
                <?php endif; ?>
                <form method="post" action="reset-password.php" data-server>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="form-group">
                        <label for="new_password">New password</label>
                        <div class="input-icon"><?= icon('lock') ?><input class="form-control" type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required autofocus></div>
                        <span class="form-hint">At least 8 characters.</span>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm new password</label>
                        <div class="input-icon"><?= icon('lock') ?><input class="form-control" type="password" id="confirm_password" name="confirm_password" data-match="new_password" autocomplete="new-password" required></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Save new password</button>
                </form>
            <?php endif; ?>
            <a href="login.php" class="back-link"><?= icon('arrow-left', 16) ?>Back to sign in</a>
<?php auth_bottom(); ?>
