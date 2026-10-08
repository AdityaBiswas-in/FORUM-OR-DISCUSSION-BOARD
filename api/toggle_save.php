<?php
// api/toggle_save.php - Toggle bookmarking / saving a thread
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Please log in to save']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$post_id = (int)($_POST['post_id'] ?? 0);

if (!$post_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?");
$stmt->execute([$user_id, $post_id]);
$existing = $stmt->fetch();

if ($existing) {
    $del = $pdo->prepare("DELETE FROM saved_posts WHERE id = ?");
    $del->execute([$existing['id']]);
    $saved = false;
} else {
    $ins = $pdo->prepare("INSERT INTO saved_posts (user_id, post_id, created_at) VALUES (?, ?, NOW())");
    $ins->execute([$user_id, $post_id]);
    $saved = true;
}

echo json_encode(['success' => true, 'saved' => $saved]);
