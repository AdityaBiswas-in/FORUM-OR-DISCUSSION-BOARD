<?php
/* ==========================================================================
   QUADDIT PLATFORM - ADMIN REGISTRATION SHORTCUT
   File: admin_register.php
   Purpose: Direct entry point to register an administrator account.
   ========================================================================== */
$_GET['role'] = 'admin';
require_once __DIR__ . '/register.php';
