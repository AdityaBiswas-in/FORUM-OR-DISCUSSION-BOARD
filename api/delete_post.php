<?php
// api/delete_post.php - Delete thread (Author only)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit;
}

$post_id = (int)($_GET['id'] ?? $_POST['post_id'] ?? 0);
$current_user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if ($post && (int)$post['user_id'] === $current_user_id) {
    // Delete post images files from disk if in uploads
    $imgStmt = $pdo->prepare("SELECT image_url FROM post_images WHERE post_id = ?");
    $imgStmt->execute([$post_id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($images as $img) {
        if (strpos($img, 'assets/uploads/') !== false) {
            $path = __DIR__ . '/../' . $img;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    $delStmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $delStmt->execute([$post_id]);
}

header("Location: ../index.php");
exit;
