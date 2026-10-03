<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';

$sentTo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        // Only a hash of the token is stored, so a database leak can't be used to reset passwords
        $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = NOW() + INTERVAL '30 minutes' WHERE id = ?")
            ->execute([hash('sha256', $token), $user['id']]);
        $link = base_url() . '/reset-password.php?token=' . $token;
        send_email($email, 'Reset your PawHome password',
            '<p>Hi ' . e($user['full_name']) . ',</p>'
            . '<p>Click the link below to choose a new password. It expires in 30 minutes.</p>'
            . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
            . '<p>If you didn\'t ask for this, you can ignore this email.</p>');
    }
    // Same message whether or not the account exists, so nobody can test which emails are registered
    $sentTo = $email;
}

auth_top('Reset password');
?>
            <h2>Reset your password</h2>
            <p class="subtitle">Enter your staff email and we'll send you a link to choose a new password.</p>

            <?php if ($sentTo !== null): ?>
                <div class="alert alert-success" role="status"><?= icon('mail') ?><span>If <strong><?= e($sentTo) ?></strong> belongs to a PawHome account, a reset link is on its way. It expires in 30 minutes.</span></div>
            <?php endif; ?>

            <form id="forgotForm" method="post" action="forgot-password.php" data-server>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-icon"><?= icon('mail') ?><input class="form-control" type="email" id="email" name="email" placeholder="name@pawhome.org" autocomplete="username" required autofocus></div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Send reset link</button>
            </form>

            <a href="login.php" class="back-link"><?= icon('arrow-left', 16) ?>Back to sign in</a>
<?php auth_bottom(); ?>
