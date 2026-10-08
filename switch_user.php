<?php
// switch_user.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$user_id = (int)($_GET['user_id'] ?? 0);
$user = get_user_by_id($user_id);

if ($user) {
    $_SESSION['user_id'] = $user['id'];
    set_remember_login($user['id']);
}

header("Location: index.php");
exit;
