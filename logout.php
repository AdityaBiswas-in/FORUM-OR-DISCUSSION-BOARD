<?php
/* ==========================================================================
   QUADDIT PLATFORM - USER SIGNOUT HANDLER
   File: logout.php
   Purpose: Terminates active user session, destroys session storage, expires
            remember-me cookies, and safely redirects to the login screen.
   ========================================================================== */
require_once __DIR__ . '/db.php';

// Clear session variables and destroy session
session_unset();
session_destroy();

// Invalidate remember-me cookie
setcookie('campus_remember', '', time() - 3600, '/');

// Redirect back to login screen with confirmation message
header("Location: login.php?logged_out=1");
exit;
