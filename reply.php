<?php
// reply.php - Redirect to thread reply section

require_once __DIR__ . '/db.php';

// Get post ID from URL parameters
$id = 0;

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
} elseif (isset($_GET['post'])) {
    $id = (int)$_GET['post'];
} elseif (isset($_GET['post_id'])) {
    $id = (int)$_GET['post_id'];
}

// If valid ID exists, redirect to that post
if ($id > 0) {
    header("Location: index.php?post=" . $id . "#post-" . $id);
    exit;
}

header("Location: index.php");
exit;
