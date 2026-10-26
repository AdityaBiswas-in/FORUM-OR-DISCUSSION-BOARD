<?php
// logout.php - Sign out current user
require_once __DIR__ . '/db.php';

// Clear and destroy session
session_unset();
session_destroy();

// Delete remember-me cookie if set
setcookie('campus_remember', '', time() - 3600, '/');

header("Location: login.php?logged_out=1");
exit;
