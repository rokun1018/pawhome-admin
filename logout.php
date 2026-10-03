<?php
require __DIR__ . '/includes/config.php';

if (current_user()) log_activity('Signed out');
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/']);
redirect('login.php');
