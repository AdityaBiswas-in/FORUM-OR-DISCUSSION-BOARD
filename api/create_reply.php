<?php
// api/create_reply.php - Create reply or nested reply to reply (with optional image)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_SESSION['user_id'];
    $post_id = (int)($_POST['post_id'] ?? 0);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $content = trim($_POST['content'] ?? '');

    if (empty($content) && empty($_FILES['reply_image']['name'])) {
        header("Location: ../thread.php?id={$post_id}&err=empty");
        exit;
    }

    $image_url = null;

    // Handle optional reply image attachment (Like Twitter/X)
    if (isset($_FILES['reply_image']) && $_FILES['reply_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $orig_name = basename($_FILES['reply_image']['name']);
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed)) {
            $new_name = 'reply_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['reply_image']['tmp_name'], $upload_dir . $new_name)) {
                $image_url = 'assets/uploads/' . $new_name;
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO replies (post_id, user_id, parent_id, content, image_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$post_id, $user_id, $parent_id, $content, $image_url]);
    $reply_id = $pdo->lastInsertId();

    header("Location: ../thread.php?id={$post_id}#reply-{$reply_id}");
    exit;
} else {
    header("Location: ../index.php");
    exit;
}
