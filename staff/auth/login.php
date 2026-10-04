<?php
// Staff log in: checks the account, then opens the staff panel.
require __DIR__ . '/../staff-common.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../login.php');
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) redirect('../login.php?error=expired');

$email = strtolower(trim($_POST['email'] ?? ''));
$stmt = $pdo->prepare('SELECT id, full_name, role, status, password FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($_POST['password'] ?? '', $user['password'])) {
    redirect('../login.php?error=invalid');
}
if ($user['status'] !== 'active') {
    redirect('../login.php?error=' . ($user['status'] === 'pending' ? 'pending' : 'inactive'));
}
sign_in($user, false, 'Staff panel');
redirect('../index.php');
