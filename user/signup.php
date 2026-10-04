<?php
// Adopter sign up. The account works straight away (unlike staff accounts).
require __DIR__ . '/user-common.php';
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '', '');
if (current_adopter()) redirect($next ?: 'index.php');

$error = '';
$v = ['full_name' => '', 'phone' => '', 'email' => '', 'address' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    $v['email'] = strtolower($v['email']);
    $password = $_POST['password'] ?? '';

    if (mb_strlen($v['full_name']) < 2 || mb_strlen($v['full_name']) > 120) $error = 'Please enter your full name.';
    elseif (!preg_match('/^[\d\s\-\+\(\)]{6,40}$/', $v['phone'])) $error = 'Please enter a valid phone number.';
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $error = 'Invalid email format.';
    elseif (mb_strlen($v['address']) < 5) $error = 'Please enter your home address.';
    elseif (strlen($password) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($password !== ($_POST['confirm_password'] ?? '')) $error = 'Passwords do not match.';
    elseif (empty($_POST['terms'])) $error = 'Please agree to the Terms of Service and Privacy Policy.';
    else {
        $exists = $pdo->prepare('SELECT 1 FROM adopters WHERE email = ?');
        $exists->execute([$v['email']]);
        if ($exists->fetch()) {
            $error = 'Email already registered. Try logging in instead.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO adopters (full_name, phone, email, address, password) VALUES (?, ?, ?, ?, ?) RETURNING id');
            $stmt->execute([$v['full_name'], $v['phone'], $v['email'], mb_substr($v['address'], 0, 255), password_hash($password, PASSWORD_DEFAULT)]);
            adopter_sign_in((int)$stmt->fetchColumn(), false);
            redirect('account-created.php' . ($next ? '?next=' . urlencode($next) : ''));
        }
    }
}
user_head('Sign Up', ['signup.css']);
?>
<body>
    <div class="signup-page">
        <!-- Left Section - Image -->
        <div class="signup-left">
            <div class="signup-image">
                <div class="image-overlay">
                    <h2>Empower your pet adoption journey.</h2>
                    <p>Create an account to access exclusive features, track applications, and find your perfect companion.</p>
                    <button class="explore-btn" type="button" onclick="goToPage('index.php')">Explore as Guest</button>
                </div>
            </div>
        </div>

        <!-- Right Section - Form -->
        <div class="signup-right">
            <div class="signup-container">
                <div class="signup-header">
                    <a class="logo" href="index.php" style="text-decoration:none">
                        <span class="logo-icon">🐾</span>
                        <span class="logo-text">PawHome</span>
                    </a>
                    <p class="tagline">Rescue & Adoption</p>
                </div>

                <div class="create-section">
                    <h1>Create an Account</h1>
                    <p>Join PawHome and start your adoption journey today.</p>
                </div>

                <form class="signup-form" id="signupForm" method="post" action="signup.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <input type="text" id="fullName" name="full_name" placeholder="John Doe" autocomplete="name" required maxlength="120" value="<?= e($v['full_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="+880 1XXX-XXXXXX" autocomplete="tel" required maxlength="40" value="<?= e($v['phone']) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="your.email@example.com" autocomplete="email" required value="<?= e($v['email']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="address">Home Address</label>
                        <input type="text" id="address" name="address" placeholder="House, Road, Area, City" autocomplete="street-address" required maxlength="255" value="<?= e($v['address']) ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" id="confirmPassword" name="confirm_password" placeholder="••••••••" autocomplete="new-password" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="terms-checkbox">
                            <input type="checkbox" name="terms" value="1" required>
                            I agree to the Terms of Service and Privacy Policy
                        </label>
                    </div>

                    <?php if ($error): ?><div id="signupError" class="error-message" role="alert"><?= e($error) ?></div><?php endif; ?>

                    <button type="submit" class="btn-signup">Create Account</button>

                    <div class="login-link">
                        Already have an account? <a href="login.php<?= $next ? '?next=' . urlencode($next) : '' ?>">Log In</a>
                    </div>
                </form>

                <footer class="signup-footer">
                    <p>Copyright © <?= date('Y') ?> <?= e(settings()['org_name']) ?>. All rights reserved.</p>
                </footer>
            </div>
        </div>
    </div>
    <script src="js/navigation.js"></script>
</body>
</html>
