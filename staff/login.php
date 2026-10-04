<?php
// Staff panel login + sign up. Same accounts as the admin panel.
require __DIR__ . '/staff-common.php';
if (current_user() && (current_user()['status'] ?? '') === 'active') redirect('index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Login — PawHome</title>
  <link rel="stylesheet" href="style.css">
</head>

<body class="auth-body">

<main class="auth">

  <section class="auth-aside">
    <div class="brand light">
      <span class="brand-mark" aria-hidden="true">🐾</span>
      PawHome
    </div>

    <div class="auth-aside-copy">
      <h2>Staff Panel</h2>
      <p>Review adoption applications, confirm boarding stays, and keep every pet's daily care log up to date.</p>
    </div>

    <p class="auth-aside-foot">For PawHome shelter staff only.</p>
  </section>

  <section class="auth-main">
    <div class="auth-card">

      <div class="auth-tabs" role="tablist" aria-label="Account">
        <button type="button" role="tab" id="loginTab" aria-controls="loginForm" aria-selected="true">Log in</button>
        <button type="button" role="tab" id="signupTab" aria-controls="signupForm" aria-selected="false" tabindex="-1">Sign up</button>
      </div>

      <div class="notice" id="authNotice" role="status" hidden></div>

      <!-- LOGIN -->
      <form id="loginForm" role="tabpanel" aria-labelledby="loginTab"
            action="auth/login.php" method="post" novalidate>
        <?= csrf_field() ?>

        <div class="auth-head">
          <h1>Welcome back</h1>
          <p>Log in with your staff email address.</p>
        </div>

        <div class="field">
          <label for="loginEmail">Email</label>
          <input type="email" id="loginEmail" name="email" autocomplete="username" placeholder="name@pawhome.org">
          <small class="error"></small>
        </div>

        <div class="field">
          <label for="loginPassword">Password</label>
          <input type="password" id="loginPassword" name="password" autocomplete="current-password">
          <small class="error"></small>
        </div>

        <button class="btn primary block" type="submit">Log in</button>

        <p class="auth-switch">
          New to the team?
          <button type="button" class="link-btn" data-switch="signup">Create an account</button>
        </p>
      </form>

      <!-- SIGN UP -->
      <form id="signupForm" role="tabpanel" aria-labelledby="signupTab"
            action="auth/register.php" method="post" novalidate hidden>
        <?= csrf_field() ?>
        <!-- Spam trap: people never see or fill this box -->
        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

        <div class="auth-head">
          <h1>Create a staff account</h1>
          <p>Use the email address the shelter gave you. A Super Admin approves new accounts before you can log in.</p>
        </div>

        <div class="field">
          <label for="signupName">Full name</label>
          <input type="text" id="signupName" name="full_name" autocomplete="name">
          <small class="error"></small>
        </div>

        <div class="field">
          <label for="signupEmail">Email</label>
          <input type="email" id="signupEmail" name="email" autocomplete="email" placeholder="name@pawhome.org">
          <small class="error"></small>
        </div>

        <div class="field">
          <label for="signupRole">Role</label>
          <select id="signupRole" name="role">
            <option value="">Select your role</option>
            <?php foreach (SIGNUP_ROLES as $r): ?><option value="<?= $r ?>"><?= e(ROLES[$r]) ?></option><?php endforeach; ?>
          </select>
          <small class="error"></small>
        </div>

        <div class="field-row">
          <div class="field">
            <label for="signupPassword">Password</label>
            <input type="password" id="signupPassword" name="password" autocomplete="new-password" aria-describedby="passwordHint">
            <small class="hint" id="passwordHint">At least 8 characters.</small>
            <small class="error"></small>
          </div>

          <div class="field">
            <label for="signupConfirm">Confirm password</label>
            <input type="password" id="signupConfirm" name="password_confirm" autocomplete="new-password">
            <small class="error"></small>
          </div>
        </div>

        <button class="btn primary block" type="submit">Create account</button>

        <p class="auth-switch">
          Already have an account?
          <button type="button" class="link-btn" data-switch="login">Log in</button>
        </p>
      </form>

    </div>
  </section>

</main>

<script src="auth.js"></script>
</body>
</html>
