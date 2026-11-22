<?php
/* ==========================================================================
   QUADDIT PLATFORM - THREAD SHORTCUT REDIRECTOR
   File: thread.php
   Purpose: Resolves direct discussion thread links and routes to index.php.
   ========================================================================== */
require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? $_GET['post'] ?? 0);
if ($id > 0) {
    header("Location: index.php?post=$id#post-$id");
    exit;
}

header("Location: index.php");
exit;
