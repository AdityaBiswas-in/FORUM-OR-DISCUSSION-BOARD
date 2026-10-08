<?php
// api/create_post.php - Handle new thread creation with image uploads
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $topic_id = (int)($_POST['topic_id'] ?? 1);
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        header("Location: ../index.php?error=empty_fields");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, topic_id, title, content, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $topic_id, $title, $content]);
        $post_id = $pdo->lastInsertId();

        // Handle uploaded images
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

        header("Location: ../thread.php?id=" . $post_id . "&msg=created");
        exit;
    } catch (PDOException $e) {
        die("Error creating post: " . $e->getMessage());
    }
} else {
    header("Location: ../index.php");
    exit;
}
