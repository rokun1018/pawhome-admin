<?php
// Adopter: ask for a password reset link by email
require __DIR__ . '/user-common.php';
$sent = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $stmt = $pdo->prepare("SELECT id, full_name FROM adopters WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    if ($a = $stmt->fetch()) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE adopters SET reset_token = ?, reset_expires = NOW() + INTERVAL '30 minutes' WHERE id = ?")
            ->execute([hash('sha256', $token), $a['id']]);
        $link = base_url() . '/user/reset-password.php?token=' . $token;
        send_email($email, 'Reset your PawHome password',
            '<p>Hi ' . e($a['full_name']) . ',</p><p>Click the link below to choose a new password. It expires in 30 minutes.</p>'
            . '<p><a href="' . e($link) . '">' . e($link) . '</a></p><p>If you didn\'t ask for this, you can ignore this email.</p>');
    }
    $sent = $email; // same message either way, so nobody can test which emails have accounts
}
user_head('Forgot Password', ['login.css']);
?>
<body>
    <div class="login-page">
        <div class="login-left">
            <div class="login-container">
                <div class="login-header">
                    <a class="logo" href="index.php" style="text-decoration:none"><span class="logo-icon">🐾</span><span class="logo-text">PawHome</span></a>
                    <p class="tagline">Rescue & Adoption</p>
                </div>
                <div class="welcome-section">
                    <h1>Forgot Password?</h1>
                    <p>Enter your email and we'll send you a link to choose a new password.</p>
                </div>
                <?php if ($sent !== null): ?>
                    <div class="flash-success" role="status">If <strong><?= e($sent) ?></strong> has a PawHome account, a reset link is on its way. It expires in 30 minutes. Check your spam folder too.</div>
                <?php endif; ?>
                <form class="login-form" method="post" action="forgot-password.php">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="your.email@example.com" required autofocus>
                    </div>
                    <button type="submit" class="btn-login">Send Reset Link</button>
                </form>
                <div class="signup-link">Remembered it? <a href="login.php">Back to login</a></div>
            </div>
        </div>
        <div class="login-right"><div class="login-image"><div class="image-overlay">
            <h2>Bringing hearts together, one paw at a time.</h2>
            <p>If the email doesn't arrive, call the shelter at <?= e(settings()['contact_phone'] ?: settings()['contact_email']) ?>.</p>
        </div></div></div>
    </div>
</body>
</html>
