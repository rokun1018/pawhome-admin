<?php
// Staff sign up. New accounts are "pending" until a Super Admin approves them
// in the admin panel (User Management), so nobody gets in on their own.
require __DIR__ . '/../staff-common.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../login.php#signup');
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) redirect('../login.php?error=expired#signup');

// Spam trap filled in = a bot. Pretend it worked.
if (trim($_POST['website'] ?? '') !== '') redirect('../login.php?registered=1');

$name     = trim($_POST['full_name'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$role     = $_POST['role'] ?? '';
$password = $_POST['password'] ?? '';

// The browser already checks these; the server checks again
if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !in_array($role, SIGNUP_ROLES, true) || strlen($password) < 8 || $password !== ($_POST['password_confirm'] ?? '')) {
    redirect('../login.php?error=invalid_signup#signup');
}

$exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
$exists->execute([$email]);
if ($exists->fetch()) redirect('../login.php?error=exists#signup');

$pdo->prepare("INSERT INTO users (full_name, email, role, status, password) VALUES (?, ?, ?, 'pending', ?)")
    ->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT)]);
log_activity('Staff sign-up', "$name, " . ROLES[$role] . ' (waiting for approval)');
redirect('../login.php?registered=1');
