<?php
// api/edit_reply.php - Edit own reply
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_user_id = (int)$_SESSION['user_id'];
    $reply_id = (int)($_POST['reply_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    $stmt = $pdo->prepare("SELECT user_id, post_id FROM replies WHERE id = ?");
    $stmt->execute([$reply_id]);
    $reply = $stmt->fetch();

    if ($reply && (int)$reply['user_id'] === $current_user_id && !empty($content)) {
        $updateStmt = $pdo->prepare("UPDATE replies SET content = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$content, $reply_id]);
        
        // If JSON requested via fetch
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true, 'content' => $content]);
            exit;
        }

        header("Location: ../thread.php?id=" . (int)$reply['post_id'] . "#reply-" . $reply_id);
        exit;
    }
}

header("Location: ../index.php");
exit;
