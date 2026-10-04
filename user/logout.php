<?php
require __DIR__ . '/user-common.php';
unset($_SESSION['adopter_id'], $_SESSION['adopter_seen'], $_SESSION['adopter_remember']);
session_regenerate_id(true);
user_flash('You have been logged out.');
redirect('login.php');
