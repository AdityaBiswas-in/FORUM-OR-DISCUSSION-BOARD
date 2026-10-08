<?php
// api/delete_reply.php - Delete own reply
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit;
}

$reply_id = (int)($_GET['id'] ?? $_POST['reply_id'] ?? 0);
$current_user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT user_id, post_id, image_url FROM replies WHERE id = ?");
$stmt->execute([$reply_id]);
$reply = $stmt->fetch();

if ($reply && (int)$reply['user_id'] === $current_user_id) {
    if (!empty($reply['image_url']) && strpos($reply['image_url'], 'assets/uploads/') !== false) {
        $path = __DIR__ . '/../' . $reply['image_url'];
        if (file_exists($path)) {
            @unlink($path);
        }
    }
    $del = $pdo->prepare("DELETE FROM replies WHERE id = ?");
    $del->execute([$reply_id]);

    header("Location: ../thread.php?id=" . (int)$reply['post_id']);
    exit;
}

header("Location: ../index.php");
exit;
