<?php
// logout.php - Sign out current user
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

clear_login();
header("Location: login.php?logged_out=1");
exit;
