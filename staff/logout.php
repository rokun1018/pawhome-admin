<?php
require __DIR__ . '/staff-common.php';
if (current_user()) log_activity('Signed out', 'Staff panel');
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/']);
redirect('login.php?logged_out=1');
