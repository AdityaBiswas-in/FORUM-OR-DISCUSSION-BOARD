<?php
// api/edit_post.php - Edit an existing thread (Owner only via 3-dots menu)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_user_id = (int)$_SESSION['user_id'];
    $post_id = (int)($_POST['post_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $topic_id = (int)($_POST['topic_id'] ?? 1);
    $content = trim($_POST['content'] ?? '');

    // Check ownership
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        die("Post not found");
    }

    if ((int)$post['user_id'] !== $current_user_id) {
        die("Permission denied: You can only edit your own posts.");
    }

    // Update post
    $updateStmt = $pdo->prepare("UPDATE posts SET title = ?, topic_id = ?, content = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$title, $topic_id, $content, $post_id]);

    // Handle extra uploaded images if any
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $upload_dir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $total_files = count($_FILES['images']['name']);

        for ($i = 0; $i < min($total_files, 4); $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['images']['tmp_name'][$i];
                $orig_name = basename($_FILES['images']['name'][$i]);
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed_exts)) {
                    $new_name = 'post_' . $post_id . '_' . time() . '_' . $i . '.' . $ext;
                    $target_path = $upload_dir . $new_name;

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $image_url = 'assets/uploads/' . $new_name;
                        $img_stmt = $pdo->prepare("INSERT INTO post_images (post_id, image_url) VALUES (?, ?)");
                        $img_stmt->execute([$post_id, $image_url]);
                    }
                }
            }
        }
    }

    // Return or redirect
    $redirect_url = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "../thread.php?id=" . $post_id;
    header("Location: " . $redirect_url);
    exit;
}
