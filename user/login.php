<?php
// Adopter log in
require __DIR__ . '/user-common.php';
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
if (current_adopter()) redirect($next);

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $stmt = $pdo->prepare('SELECT id, password, status FROM adopters WHERE email = ?');
    $stmt->execute([$email]);
    $a = $stmt->fetch();
    if ($a && password_verify($_POST['password'] ?? '', $a['password'])) {
        if ($a['status'] !== 'active') {
            $error = 'This account has been switched off. Please contact the shelter.';
        } else {
            adopter_sign_in((int)$a['id'], !empty($_POST['remember']));
            redirect($next);
        }
    } else {
        $error = 'Invalid email or password.';
    }
}
user_head('Login', ['login.css']);
?>
<body>
    <div class="login-page">
        <!-- Left Section - Form -->
        <div class="login-left">
            <div class="login-container">
                <div class="login-header">
                    <a class="logo" href="index.php" style="text-decoration:none">
                        <span class="logo-icon">🐾</span>
                        <span class="logo-text">PawHome</span>
                    </a>
                    <p class="tagline">Rescue & Adoption</p>
                </div>

                <div class="welcome-section">
                    <h1>Welcome Back</h1>
                    <p>Sign in to manage adoptions and browse available pets.</p>
                </div>

                <?= user_flash_html() ?>

                <form class="login-form" id="loginForm" method="post" action="login.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input type="email" id="loginEmail" name="email" placeholder="your.email@example.com" autocomplete="username" required value="<?= e($email) ?>">
                    </div>

                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input type="password" id="loginPassword" name="password" placeholder="••••••••" autocomplete="current-password" required>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" value="1">
                            Remember me
                        </label>
                        <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                    </div>

                    <?php if ($error): ?><div id="loginError" class="error-message" role="alert"><?= e($error) ?></div><?php endif; ?>

                    <button type="submit" class="btn-login">Log In</button>
                </form>

                <div class="signup-link">
                    Don't have an account? <a href="signup.php<?= $next !== 'index.php' ? '?next=' . urlencode($next) : '' ?>">Sign up</a>
                </div>

                <footer class="login-footer">
                    <p>Copyright © <?= date('Y') ?> <?= e(settings()['org_name']) ?>. All rights reserved.</p>
                </footer>
            </div>
        </div>

        <!-- Right Section - Image -->
        <div class="login-right">
            <div class="login-image">
                <div class="image-overlay">
                    <h2>Bringing hearts together, one paw at a time.</h2>
                    <p>Join thousands of happy pet parents who found their perfect companion through PawHome.</p>
                    <button class="explore-btn" type="button" onclick="goToPage('index.php')">Explore as Guest</button>
                </div>
            </div>
        </div>
    </div>
    <script src="js/navigation.js"></script>
</body>
</html>
