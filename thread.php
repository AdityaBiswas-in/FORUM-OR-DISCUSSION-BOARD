<?php

require_once __DIR__ . '/db.php';

$id = 0;

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
} elseif (isset($_GET['post'])) {
    $id = (int)$_GET['post'];
}

if ($id > 0) {
    header("Location: index.php?post=" . $id . "#post-" . $id);
    exit;
}


header("Location: index.php");
exit;
