<?php
// api/toggle_like.php - Toggle like on thread
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Please log in to like']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$post_id = (int)($_POST['post_id'] ?? 0);

if (!$post_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ? AND reply_id IS NULL");
$stmt->execute([$user_id, $post_id]);
$existing = $stmt->fetch();

if ($existing) {
    $del = $pdo->prepare("DELETE FROM likes WHERE id = ?");
    $del->execute([$existing['id']]);
    $liked = false;
} else {
    $ins = $pdo->prepare("INSERT INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())");
    $ins->execute([$user_id, $post_id]);
    $liked = true;
}

$count = get_like_count($post_id);

echo json_encode(['success' => true, 'liked' => $liked, 'count' => $count]);
