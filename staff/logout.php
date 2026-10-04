<?php
require __DIR__ . '/staff-common.php';
if (current_user()) log_activity('Signed out', 'Staff panel');
// Sign out the staff account only (an adopter signed in on the public site stays signed in)
unset($_SESSION['user'], $_SESSION['last_seen'], $_SESSION['remember'], $_SESSION['csrf']);
session_regenerate_id(true);
redirect('login.php?logged_out=1');
