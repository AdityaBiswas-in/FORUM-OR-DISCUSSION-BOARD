<?php
// reply.php - Forwarder to thread/discussion page
require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? $_GET['post'] ?? $_GET['post_id'] ?? 0);
if ($id > 0) {
    header("Location: index.php?post={$id}#post-{$id}");
    exit;
}
header("Location: index.php");
exit;
