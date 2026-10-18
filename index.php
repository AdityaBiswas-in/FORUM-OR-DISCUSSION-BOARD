<?php

require_once __DIR__ . '/db.php';

// ========================================================
// 1. SESSION & CURRENT USER SETUP
// ========================================================
$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $result = $conn->execute_query("SELECT id, username, email, name, avatar, role, bio FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $currentUser = $result->fetch_assoc();
}

if (!$currentUser) {
    unset($_SESSION['user_id']);
    header("Location: login.php");
    exit;
}

// ========================================================
// 2. HELPER FUNCTIONS
// ========================================================
if (!function_exists('e')) {
    /**
     * Escape HTML special characters for safe rendering.
     *
     * @param string|null $str
     * @return string
     */
    function e(?string $str): string {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Calculate relative time elapsed from a datetime string.
 *
 * @param string|null $datetime
 * @return string
 */
function time_ago(?string $datetime): string {
    if (empty($datetime)) return 'just now';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

/**
 * Upload multiple images for a forum post.
 *
 * @param array|null $files
 * @param int $post_id
 * @param mysqli $conn
 * @return void
 */
function upload_post_images(?array $files, int $post_id, mysqli $conn): void {
    if (empty($files) || empty($files['name'][0])) return;
    $dir = __DIR__ . '/assets/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    for ($i = 0; $i < min(count($files['name']), 4); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $name = 'post_' . $post_id . '_' . time() . '_' . $i . '.' . $ext;
                if (move_uploaded_file($files['tmp_name'][$i], $dir . $name)) {
                    $conn->execute_query("INSERT INTO post_images (post_id, image_url) VALUES (?, ?)", [$post_id, 'assets/uploads/' . $name]);
                }
            }
        }
    }
}

/**
 * Upload a single image file for a reply.
 *
 * @param array|null $file
 * @return string|null
 */
function upload_single_image(?array $file): ?string {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $dir = __DIR__ . '/assets/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $name = 'reply_' . time() . '_' . rand(100, 999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
            return 'assets/uploads/' . $name;
        }
    }
    return null;
}

/**
 * Render a nested reply tree node recursively.
 *
 * @param array $rep
 * @param array $childrenByParent
 * @param array $repliesById
 * @param array $currentUser
 * @param int $postId
 * @param int $depth
 * @return void
 */
function renderReplyNode(array $rep, array $childrenByParent, array $repliesById, array $currentUser, int $postId, int $depth = 0): void {
    $isRepAuthor = ((int)$rep['user_id'] === (int)$currentUser['id']);
    $repId = (int)$rep['id'];
    $parentId = !empty($rep['parent_id']) ? (int)$rep['parent_id'] : 0;
    $parentAuthor = ($parentId > 0 && isset($repliesById[$parentId])) ? $repliesById[$parentId]['author_username'] : null;
    $children = $childrenByParent[$repId] ?? [];
    ?>
    <div class="reply-thread-item" id="reply-<?= $repId ?>" style="position: relative; margin-bottom: 12px;">
      <div class="reply-card">
        <div class="reply-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
          <div class="reply-author" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <a href="profile.php?id=<?= (int)$rep['user_id'] ?>" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: inherit;">
              <img src="<?= e($rep['author_avatar']) ?>" alt="<?= e($rep['author_name']) ?>" class="reply-avatar" style="width: 24px; height: 24px; border-radius: 50%;">
              <span class="reply-author-name" style="font-size: 13px; font-weight: 700; color: var(--text-main);"><?= e($rep['author_name']) ?></span>
            </a>
            <?php if (!empty($parentAuthor)): ?>
              <span class="reply-to-tag" style="font-size: 11.5px; color: var(--text-dim); display: inline-flex; align-items: center; gap: 3px;">
                <span>replied to</span>
                <a href="profile.php?u=<?= urlencode($parentAuthor) ?>" style="color: var(--primary-blue); font-weight: 600; text-decoration: none;">@<?= e($parentAuthor) ?></a>
              </span>
            <?php endif; ?>
            <span class="reply-timestamp" style="font-size: 12px; color: var(--text-dim);">· <?= time_ago($rep['created_at']) ?></span>
            <?php if (!empty($rep['updated_at'])): ?>
              <span style="font-size: 11px; color: var(--text-dim);">(edited)</span>
            <?php endif; ?>
          </div>

          <!-- Actions for reply author (Edit, Delete) -->
          <div style="display: flex; align-items: center; gap: 8px;">
            <?php if ($isRepAuthor): ?>
              <button type="button" class="btn-edit-reply" onclick="toggleEditReplyForm(<?= $repId ?>)" style="background: none; border: none; color: var(--primary-blue); font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Edit
              </button>
              <a href="index.php?action=delete_reply&id=<?= $repId ?>" class="btn-delete-reply" onclick="return confirm('Delete this reply?')" style="background: none; border: none; color: var(--danger-red); font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Delete
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Reply Body Text -->
        <div class="reply-body-text" id="reply-text-<?= $repId ?>" style="font-size: 13.5px; color: var(--text-main); line-height: 1.5; margin-bottom: 6px;">
          <?= nl2br(e($rep['content'])) ?>
        </div>

        <!-- Inline Reply Edit Form -->
        <?php if ($isRepAuthor): ?>
          <form action="index.php" method="POST" id="reply-edit-form-<?= $repId ?>" style="display: none; margin-bottom: 10px;">
            <input type="hidden" name="action" value="edit_reply">
            <input type="hidden" name="reply_id" value="<?= $repId ?>">
            <textarea name="content" class="reply-textarea" style="width: 100%; min-height: 60px; margin-bottom: 8px;" required><?= e($rep['content']) ?></textarea>
            <div style="display: flex; gap: 8px;">
              <button type="submit" class="btn-post-reply" style="padding: 5px 14px; font-size: 12px;">Save</button>
              <button type="button" class="btn-modal-cancel" style="padding: 5px 14px; font-size: 12px;" onclick="toggleEditReplyForm(<?= $repId ?>)">Cancel</button>
            </div>
          </form>
        <?php endif; ?>

        <!-- Reply Photo Attachment -->
        <?php if (!empty($rep['image_url'])): ?>
          <div class="reply-image-attachment" onclick="openLightbox('<?= e($rep['image_url']) ?>')" style="margin: 6px 0; max-width: 240px; border-radius: 8px; overflow: hidden; cursor: pointer;">
            <img src="<?= e($rep['image_url']) ?>" alt="Reply photo" style="width: 100%; height: auto; display: block;">
          </div>
        <?php endif; ?>

        <!-- Nested Reply Action Trigger -->
        <div class="reply-actions-bar" style="margin-top: 6px;">
          <button type="button" class="btn-nested-reply" onclick="toggleNestedComposer(<?= $repId ?>)" style="background: none; border: none; color: var(--text-muted); font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 10h10a5 5 0 0 1 5 5v5"></path>
              <path d="m7 6-4 4 4 4"></path>
            </svg>
            Reply
          </button>
        </div>

        <!-- Nested Inline Composer Box -->
        <div class="inline-nested-composer" id="nested-composer-<?= $repId ?>" style="display: none; margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border-color);">
          <form action="index.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_reply">
            <input type="hidden" name="post_id" value="<?= $postId ?>">
            <input type="hidden" name="parent_id" value="<?= $repId ?>">
            <textarea name="content" class="reply-textarea" style="width: 100%; min-height: 55px; margin-bottom: 8px;" placeholder="Replying to @<?= e($rep['author_username']) ?>..." required></textarea>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <label class="btn-attach-image" style="cursor: pointer; font-size: 12px; color: var(--primary-blue);">
                <span>Photo</span>
                <input type="file" name="reply_image" accept="image/*" style="display: none;">
              </label>
              <div style="display: flex; gap: 8px;">
                <button type="button" class="btn-modal-cancel" style="padding: 5px 12px; font-size: 12px;" onclick="toggleNestedComposer(<?= $repId ?>)">Cancel</button>
                <button type="submit" class="btn-post-reply" style="padding: 5px 14px; font-size: 12px;">Reply</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Threaded Sub-Replies (Directly Below Parent Reply) -->
      <?php if (!empty($children)): ?>
        <div class="reply-children-list">
          <?php foreach ($children as $child): ?>
            <?php renderReplyNode($child, $childrenByParent, $repliesById, $currentUser, $postId, $depth + 1); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

// ========================================================
// 3. ACTION HANDLERS (POST, EDIT, DELETE, REPLY, SAVE)
// ========================================================
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 3.1 CREATE POST
if ($action === 'create_post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if (!empty($title) && !empty($content)) {
        $conn->execute_query("INSERT INTO posts (user_id, title, content, created_at) VALUES (?, ?, ?, NOW())", [$currentUser['id'], $title, $content]);
        $post_id = (int)$conn->insert_id;
        upload_post_images($_FILES['images'] ?? null, $post_id, $conn);
        header("Location: index.php?msg=created#post-" . $post_id);
        exit;
    }
    header("Location: index.php?error=empty_fields");
    exit;
}

// 3.2 EDIT POST
if ($action === 'edit_post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $delete_image_ids = $_POST['delete_image_ids'] ?? [];

    $result = $conn->execute_query("SELECT user_id FROM posts WHERE id = ?", [$post_id]);
    $p = $result->fetch_assoc();

    if ($p && (int)$p['user_id'] === (int)$currentUser['id'] && !empty($title) && !empty($content)) {
        $conn->execute_query("UPDATE posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?", [$title, $content, $post_id]);

        // Delete any existing images requested for removal
        if (!empty($delete_image_ids) && is_array($delete_image_ids)) {
            foreach ($delete_image_ids as $del_id) {
                $del_id = (int)$del_id;
                if ($del_id > 0) {
                    $imgResult = $conn->execute_query("SELECT image_url FROM post_images WHERE id = ? AND post_id = ?", [$del_id, $post_id]);
                    $imgRow = $imgResult->fetch_assoc();
                    if ($imgRow && !empty($imgRow['image_url'])) {
                        $f = __DIR__ . '/' . $imgRow['image_url'];
                        if (file_exists($f)) @unlink($f);
                        $conn->execute_query("DELETE FROM post_images WHERE id = ? AND post_id = ?", [$del_id, $post_id]);
                    }
                }
            }
        }

        // Upload any newly selected images
        upload_post_images($_FILES['images'] ?? null, $post_id, $conn);
    }
    header("Location: index.php?post=" . $post_id . "#post-" . $post_id);
    exit;
}

// 3.3 DELETE POST
if ($action === 'delete_post') {
    $post_id = (int)($_REQUEST['id'] ?? $_POST['post_id'] ?? 0);
    $result = $conn->execute_query("SELECT user_id FROM posts WHERE id = ?", [$post_id]);
    $p = $result->fetch_assoc();

    if ($p && (int)$p['user_id'] === (int)$currentUser['id']) {
        $imgResult = $conn->execute_query("SELECT image_url FROM post_images WHERE post_id = ?", [$post_id]);
        while ($imgRow = $imgResult->fetch_assoc()) {
            $f = __DIR__ . '/' . $imgRow['image_url'];
            if (file_exists($f)) @unlink($f);
        }
        $conn->execute_query("DELETE FROM posts WHERE id = ?", [$post_id]);
    }
    header("Location: index.php?msg=deleted");
    exit;
}

// 3.4 CREATE REPLY ON POST
if ($action === 'create_reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $content = trim($_POST['content'] ?? '');
    $image_url = upload_single_image($_FILES['reply_image'] ?? null);

    if (!empty($content) || !empty($image_url)) {
        $conn->execute_query(
            "INSERT INTO replies (post_id, user_id, parent_id, content, image_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$post_id, $currentUser['id'], $parent_id, $content, $image_url]
        );
        $reply_id = (int)$conn->insert_id;
        header("Location: index.php?post=" . $post_id . "#reply-" . $reply_id);
        exit;
    }
    header("Location: index.php?post=" . $post_id . "#post-" . $post_id);
    exit;
}

// 3.5 EDIT REPLY
if ($action === 'edit_reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply_id = (int)($_POST['reply_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    $result = $conn->execute_query("SELECT user_id, post_id FROM replies WHERE id = ?", [$reply_id]);
    $r = $result->fetch_assoc();

    if ($r && (int)$r['user_id'] === (int)$currentUser['id'] && !empty($content)) {
        $conn->execute_query("UPDATE replies SET content = ?, updated_at = NOW() WHERE id = ?", [$content, $reply_id]);
        header("Location: index.php?post=" . (int)$r['post_id'] . "#reply-" . $reply_id);
        exit;
    }
    header("Location: index.php");
    exit;
}

// 3.6 DELETE REPLY
if ($action === 'delete_reply') {
    $reply_id = (int)($_REQUEST['id'] ?? $_POST['reply_id'] ?? 0);
    $result = $conn->execute_query("SELECT user_id, post_id, image_url FROM replies WHERE id = ?", [$reply_id]);
    $r = $result->fetch_assoc();

    if ($r && (int)$r['user_id'] === (int)$currentUser['id']) {
        if (!empty($r['image_url']) && file_exists(__DIR__ . '/' . $r['image_url'])) {
            @unlink(__DIR__ . '/' . $r['image_url']);
        }
        $conn->execute_query("DELETE FROM replies WHERE id = ?", [$reply_id]);
        header("Location: index.php?post=" . (int)$r['post_id']);
        exit;
    }
    header("Location: index.php");
    exit;
}

// 3.7 TOGGLE LIKE
if ($action === 'toggle_like') {
    $post_id = (int)($_REQUEST['post_id'] ?? 0);
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    $result = $conn->execute_query("SELECT id FROM likes WHERE user_id = ? AND post_id = ? AND reply_id IS NULL", [$currentUser['id'], $post_id]);
    $existing = $result->fetch_assoc();

    if ($existing) {
        $conn->execute_query("DELETE FROM likes WHERE id = ?", [$existing['id']]);
        $liked = false;
    } else {
        $conn->execute_query("INSERT INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())", [$currentUser['id'], $post_id]);
        $liked = true;
    }

    $count = (int)($conn->query("SELECT COUNT(*) FROM likes WHERE post_id = {$post_id} AND reply_id IS NULL")->fetch_row()[0] ?? 0);

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'liked' => $liked, 'count' => $count]);
        exit;
    }
    header("Location: index.php#post-" . $post_id);
    exit;
}

// ========================================================
// 4. FETCH DATA FOR VIEW (POSTS, REPLIES)
// ========================================================
$search_query       = trim($_GET['q'] ?? '');
$single_post_id     = (int)($_GET['post'] ?? $_GET['id'] ?? 0);

$where  = [];
$params = [$currentUser['id']];

if ($single_post_id > 0) {
    $where[]  = "p.id = ?";
    $params[] = $single_post_id;
} else {
    if (!empty($search_query)) {
        $where[]  = "(p.title LIKE ? OR p.content LIKE ?)";
        $params[] = "%{$search_query}%";
        $params[] = "%{$search_query}%";
    }
}

$sql = "
    SELECT p.*, u.name as author_name, u.username as author_username, u.avatar as author_avatar,
           (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count,
           (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.reply_id IS NULL) as like_count,
           (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) as is_liked
    FROM posts p
    JOIN users u ON p.user_id = u.id
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY p.created_at DESC";

$postsResult = $conn->execute_query($sql, $params);
$posts = $postsResult->fetch_all(MYSQLI_ASSOC);

// Fetch all images for these posts
$postImages = [];
$postImagesData = [];
$post_ids = array_column($posts, 'id');
if (!empty($post_ids)) {
    $in = implode(',', array_fill(0, count($post_ids), '?'));
    $imgResult = $conn->execute_query("SELECT id, post_id, image_url FROM post_images WHERE post_id IN ($in) ORDER BY id ASC", $post_ids);
    while ($row = $imgResult->fetch_assoc()) {
        $postImages[$row['post_id']][] = $row['image_url'];
        $postImagesData[$row['post_id']][] = [
            'id' => (int)$row['id'],
            'url' => $row['image_url']
        ];
    }
}

// Fetch all replies for these posts
$repliesByPost = [];
if (!empty($post_ids)) {
    $in = implode(',', array_fill(0, count($post_ids), '?'));
    $repResult = $conn->execute_query("
        SELECT r.*, u.name as author_name, u.username as author_username, u.avatar as author_avatar
        FROM replies r
        JOIN users u ON r.user_id = u.id
        WHERE r.post_id IN ($in)
        ORDER BY r.created_at ASC
    ", $post_ids);
    while ($r = $repResult->fetch_assoc()) {
        $repliesByPost[$r['post_id']][] = $r;
    }
}

// Recent Discussions for right sidebar
$recentDiscussions = $conn->query("
    SELECT p.id, p.title, p.created_at, u.name, u.avatar,
           (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>





<!-- ========================================
HTML PART
======================================== -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quaddit - College Discussion Board</title>
  <meta name="description" content="Quaddit is the modern discussion board for college students to share questions, images, setups, and campus moments.">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time() ?>">
  <link rel="icon" type="image/jpeg" href="assets/images/logo.jpg">
  <script>
    (function() {
      const savedTheme = localStorage.getItem('campus_theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();
  </script>
</head>
<body>
<div class="app-container">

  <!-- ======================================================== -->
  <!-- LEFT SIDEBAR                                             -->
  <!-- ======================================================== -->
  <aside class="sidebar-left">
    <div>
      <!-- Brand Logo -->
      <a href="index.php" class="brand-header">
        <div class="brand-logo-icon">
          <img src="assets/images/logo.jpg" alt="Quaddit Logo" class="brand-logo-img">
        </div>
        <div class="brand-text">Quad<span>dit</span></div>
      </a>

      <!-- Navigation Menu -->
      <ul class="nav-menu">
        <li class="nav-item active">
          <a href="index.php" id="nav-home">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
            </svg>
            <span>Home</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="profile.php">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
            <span>Profile</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="javascript:void(0)" id="theme-toggle-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
            <span id="theme-icon-container">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="theme-icon-sun" style="display:none;">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
              </svg>
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="theme-icon-moon">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
              </svg>
            </span>
            <span id="theme-label-text">Theme: Dark</span>
          </a>
        </li>
      </ul>

      <!-- Primary Post Button -->
      <button class="btn-create-thread-sidebar" id="btn-open-create-modal" onclick="openCreateThreadModal()">
        <span>Post</span>
      </button>
    </div>

    <!-- User Profile Bar -->
    <div class="user-profile-bar" id="user-profile-bar" onclick="toggleProfileDropdown(event)">
      <div class="user-profile-info">
        <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="user-avatar-img">
        <div class="user-names">
          <span class="user-display-name"><?= e($currentUser['name']) ?></span>
          <span class="user-handle">@<?= e($currentUser['username']) ?></span>
        </div>
      </div>
      <div class="user-dropdown-arrow">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </div>

      <div class="profile-dropdown-menu" id="profile-dropdown-menu">
        <div style="padding: 8px 12px; font-size: 13px; font-weight: 700; color: var(--text-main);">
          <?= e($currentUser['name']) ?>
          <span style="font-size: 12px; font-weight: 500; color: var(--text-dim); display: block;">@<?= e($currentUser['username']) ?></span>
        </div>
        <a href="profile.php" style="color: var(--text-main); display: flex; align-items: center; gap: 8px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          View Profile
        </a>
        <div class="divider"></div>
        <a href="logout.php" style="color: var(--danger-red);">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          Sign Out
        </a>
      </div>
    </div>
  </aside>

  <!-- ======================================================== -->
  <!-- MAIN FEED COLUMN                                         -->
  <!-- ======================================================== -->
  <main class="main-feed-column">
    <!-- Search Bar -->
    <div class="search-container">
      <form action="index.php" method="GET" class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" name="q" placeholder="Search discussions..." value="<?= e($search_query) ?>" autocomplete="off">
      </form>
    </div>

    <?php if ($single_post_id > 0): ?>
      <!-- Single Discussion View Top Bar -->
      <div class="single-discussion-header" style="margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
        <a href="index.php" style="display: inline-flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: var(--primary-blue); text-decoration: none; padding: 6px 14px; border-radius: 9999px; background: rgba(37, 99, 235, 0.1);">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          <span>Back to All Discussions</span>
        </a>
        <span style="font-size: 13px; font-weight: 600; color: var(--text-dim);">Discussion View</span>
      </div>
    <?php else: ?>
      <!-- Quick Composer Trigger (Opens compose modal or writes inline) -->
      <div class="x-quick-composer" onclick="openCreateThreadModal()">
        <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="composer-avatar">
        <div class="composer-content-box">
          <div class="composer-placeholder">What's happening?</div>
          <div class="composer-bottom-bar">
            <div class="composer-tools">
              <button type="button" class="composer-tool-btn" title="Add Photos">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M3 5.5C3 4.119 4.119 3 5.5 3h13C19.881 3 21 4.119 21 5.5v13c0 1.381-1.119 2.5-2.5 2.5h-13C4.119 21 3 19.881 3 18.5v-13zM5.5 5c-.276 0-.5.224-.5.5v9.086l3.293-3.293a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414l-1.293 1.293H18.5c.276 0 .5-.224.5-.5V5.5c0-.276-.224-.5-.5-.5h-13zM15 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
                </svg>
              </button>
            </div>
            <button type="button" class="composer-post-btn">Post</button>
          </div>
        </div>
      </div>

      <!-- Show New Posts Indicator -->
      <div class="show-new-posts-banner" onclick="window.location.href='index.php'">
        Show new posts
      </div>
    <?php endif; ?>

    <!-- Discussion Feed -->
    <div class="posts-feed" id="posts-feed-container">
      <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post): ?>
          <?php
            $postId    = (int)$post['id'];
            $isAuthor  = ((int)$post['user_id'] === (int)$currentUser['id']);
            $images    = $postImages[$postId] ?? [];
            $imgCount  = count($images);
            $replies   = $repliesByPost[$postId] ?? [];
            $hasReplied = count($replies) > 0;
          ?>
          <article class="post-card" id="post-<?= $postId ?>" data-post-id="<?= $postId ?>" onclick="handlePostCardClick(event, <?= $postId ?>)" style="cursor: pointer;">
            <div class="post-avatar-col">
              <a href="profile.php?id=<?= (int)$post['user_id'] ?>" onclick="event.stopPropagation()">
                <img src="<?= e($post['author_avatar']) ?>" alt="<?= e($post['author_name']) ?>" class="author-avatar">
              </a>
            </div>
            
            <div class="post-main-col">
              <!-- Post Header: Author, Badge, @username, Timestamp, 3-dots -->
              <div class="post-header">
                <div class="post-author-meta">
                  <a href="profile.php?id=<?= (int)$post['user_id'] ?>" onclick="event.stopPropagation()" style="color: inherit; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                    <span class="author-name"><?= e($post['author_name']) ?></span>
                    <svg class="verified-badge" viewBox="0 0 24 24" width="18" height="18" fill="#1d9bf0" style="display: inline-block; vertical-align: middle;">
                      <path d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.79-4-4-4-.495 0-.965.084-1.4.238C14.55 2.475 13.18 1.6 11.6 1.6c-1.58 0-2.95.875-3.6 2.148-.435-.154-.905-.238-1.4-.238-2.21 0-4 1.79-4 4 0 .495.084.965.238 1.4C1.575 10.45.7 11.82.7 13.4c0 1.58.875 2.95 2.148 3.6-.154.435-.238.905-.238 1.4 0 2.21 1.79 4 4 4 .495 0 .965-.084 1.4-.238.65 1.273 2.02 2.148 3.6 2.148 1.58 0 2.95-.875 3.6-2.148.435.154.905.238 1.4.238 2.21 0 4-1.79 4-4 0-.495-.084-.965-.238-1.4 1.273-.65 2.148-2.02 2.148-3.6zm-12.87 4.16l-3.29-3.29 1.41-1.41 1.88 1.88 5.18-5.18 1.41 1.41-6.59 6.59z"/>
                    </svg>
                    <span class="user-handle">@<?= e($post['author_username']) ?></span>
                  </a>
                  <span class="post-dot">·</span>
                  <span class="post-timestamp"><?= time_ago($post['created_at']) ?></span>
                  <?php if (!empty($post['updated_at'])): ?>
                    <span style="font-size: 11.5px; color: var(--text-dim); margin-left: 4px;">(edited)</span>
                  <?php endif; ?>
                </div>

                <div class="post-header-right">
                  <!-- 3-Dots Action Menu (Author only) -->
                  <?php if ($isAuthor): ?>
                  <div class="post-actions-menu" onclick="event.stopPropagation()">
                    <button type="button" class="btn-three-dots" title="More" onclick="togglePostMenu(event, 'post-menu-<?= $postId ?>')">
                      <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                        <path d="M3 12c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm9 2c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm7 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                      </svg>
                    </button>
                    <div class="three-dots-dropdown" id="post-menu-<?= $postId ?>">
                      <button type="button" 
                              data-id="<?= $postId ?>" 
                              data-title="<?= e($post['title']) ?>" 
                              data-content="<?= e($post['content']) ?>" 
                              data-images='<?= htmlspecialchars(json_encode($postImagesData[$postId] ?? []), ENT_QUOTES, 'UTF-8') ?>'
                              onclick="openEditPostModal(this)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit Discussion
                      </button>
                      <button type="button" class="btn-delete" onclick="confirmDeletePost(<?= $postId ?>)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Delete Discussion
                      </button>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Post Title -->
              <h2 class="post-title" style="margin: 4px 0 8px 0;">
                <a href="index.php?post=<?= $postId ?>" class="post-title-link" style="color: inherit; text-decoration: none;">
                  <?= e($post['title']) ?>
                </a>
              </h2>

              <!-- Post Content Text -->
              <p class="post-body-text"><?= nl2br(e($post['content'])) ?></p>

              <!-- Image Gallery -->
              <?php if ($imgCount > 0): ?>
                <div class="post-media-grid grid-<?= min($imgCount, 4) ?>" onclick="event.stopPropagation()">
                  <?php foreach ($images as $img): ?>
                    <div class="post-media-item" onclick="openLightbox('<?= e($img) ?>')">
                      <img src="<?= e($img) ?>" alt="Post photo" loading="lazy">
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Post Action Bar (Reply & Like) -->
              <div class="post-footer" onclick="event.stopPropagation()">
                <!-- Reply Button (Toggles replies and quick response composer) -->
                <button type="button" class="post-action-btn action-reply" onclick="event.stopPropagation(); toggleRepliesSection(<?= $postId ?>)" title="Replies">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                  </svg>
                  <span id="reply-count-badge-<?= $postId ?>"><?= count($replies) > 0 ? count($replies) . (count($replies) === 1 ? ' reply' : ' replies') : 'Reply' ?></span>
                </button>

                <!-- Like Button -->
                <button type="button" class="post-action-btn action-like <?= $post['is_liked'] ? 'active-liked' : '' ?>" id="like-btn-<?= $postId ?>" onclick="toggleLikePost(event, <?= $postId ?>)" title="Like">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="<?= $post['is_liked'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.8">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path>
                  </svg>
                  <span id="like-count-<?= $postId ?>"><?= (int)$post['like_count'] > 0 ? (int)$post['like_count'] : '' ?></span>
                </button>
              </div>

              <!-- ==================================================== -->
              <!-- INLINE REPLIES & REPLY COMPOSER (All in index.php)   -->
              <!-- ==================================================== -->
              <?php $isExpanded = ($single_post_id === $postId); ?>
              <div class="post-replies-collapse" id="replies-section-<?= $postId ?>" style="display: <?= $isExpanded ? 'block' : 'none' ?>; margin-top: 14px; border-top: 1px solid var(--border-color); padding-top: 14px;" onclick="event.stopPropagation()">
                
                <!-- Discussion Info Header inside replies section -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                  <span style="font-size: 13px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Replies (<?= count($replies) ?>)</span>
                  <?php if ($single_post_id !== $postId): ?>
                    <a href="index.php?post=<?= $postId ?>" style="font-size: 12.5px; color: var(--primary-blue); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 600;">
                      <span>Discussion Page</span>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                  <?php endif; ?>
                </div>
                
                <!-- Quick Inline Reply Composer Form -->
                <div class="reply-composer-card" style="margin-bottom: 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 14px; padding: 12px 14px;">
                  <div class="reply-composer-header" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="reply-avatar" style="width: 26px; height: 26px; border-radius: 50%;">
                    <span style="font-size: 13px; font-weight: 700; color: var(--text-main);">Reply as <?= e($currentUser['name']) ?></span>
                  </div>
                  <form action="index.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_reply">
                    <input type="hidden" name="post_id" value="<?= $postId ?>">
                    <textarea name="content" class="reply-textarea" placeholder="Post your reply or answer..." required style="width: 100%; min-height: 58px; resize: vertical; border-radius: 8px; padding: 8px 12px; font-size: 13.5px;"></textarea>
                    
                    <div class="reply-composer-actions" style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px;">
                      <label class="btn-attach-image" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--primary-blue);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                          <circle cx="8.5" cy="8.5" r="1.5"></circle>
                          <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        <span>Attach Photo</span>
                        <input type="file" name="reply_image" accept="image/*" style="display: none;" onchange="previewReplyImage(this, <?= $postId ?>)">
                      </label>
                      <button type="submit" class="btn-post-reply" style="padding: 6px 18px; font-size: 13px; border-radius: 9999px; background: var(--primary-blue); color: #fff; font-weight: 700; border: none; cursor: pointer;">Reply</button>
                    </div>
                    <div class="image-preview-strip" id="reply-preview-<?= $postId ?>" style="display: none; margin-top: 8px;"></div>
                  </form>
                </div>

                <!-- Existing Replies List (Shown on Discussion Page) -->
                <?php if ($hasReplied && $single_post_id === $postId): ?>
                  <?php
                    // Index all replies by ID and group child replies by parent_id
                    $repliesById = [];
                    $childrenByParent = [];
                    foreach ($replies as $r) {
                        $repliesById[(int)$r['id']] = $r;
                    }
                    $topLevelReplies = [];
                    foreach ($replies as $r) {
                        $pId = !empty($r['parent_id']) ? (int)$r['parent_id'] : 0;
                        if ($pId > 0 && isset($repliesById[$pId])) {
                            $childrenByParent[$pId][] = $r;
                        } else {
                            $topLevelReplies[] = $r;
                        }
                    }
                  ?>
                  <div class="replies-tree-list">
                    <?php foreach ($topLevelReplies as $rep): ?>
                      <?php renderReplyNode($rep, $childrenByParent, $repliesById, $currentUser, $postId, 0); ?>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <!-- Empty State Card -->
        <div class="empty-state-card" style="text-align: center; padding: 56px 28px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; margin-top: 10px; max-width: 600px; margin-left: auto; margin-right: auto;">
          <div class="empty-state-icon-wrap" style="width: 64px; height: 64px; border-radius: 50%; background: rgba(29, 155, 240, 0.12); border: 1px solid rgba(29, 155, 240, 0.28); display: flex; align-items: center; justify-content: center; color: var(--primary-blue);">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
          </div>
          <h2 class="empty-state-title" style="font-size: 20px; font-weight: 800; color: var(--text-main); margin: 0;">
            <?= !empty($search_query) ? 'No matching discussions' : 'No discussions yet' ?>
          </h2>
          <p class="empty-state-subtitle" style="font-size: 14px; color: var(--text-muted); max-width: 400px; line-height: 1.5; margin: 0;">
            <?= !empty($search_query) ? 'No discussions matched your query. Try different words or start a fresh thread.' : 'Be the first to share an idea, ask a campus question, or showcase your project.' ?>
          </p>
          <button type="button" class="btn-empty-state-cta" onclick="openCreateThreadModal()" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px; padding: 12px 26px; border-radius: 9999px; font-size: 14px; font-weight: 700; background: linear-gradient(135deg, #1d9bf0 0%, #0284c7 100%); color: #ffffff; border: none; cursor: pointer; text-decoration: none; box-shadow: 0 4px 16px rgba(29, 155, 240, 0.35);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/>
            </svg>
            <span>Start a New Discussion</span>
          </button>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- ======================================================== -->
  <!-- RIGHT SIDEBAR (Recent Discussions)                       -->
  <!-- ======================================================== -->
  <aside class="sidebar-right">
    <div class="recent-discussions-card">
      <h3 class="recent-card-header">Recent Discussions</h3>
      <div class="recent-discussions-list">
        <?php if (!empty($recentDiscussions)): ?>
          <?php foreach ($recentDiscussions as $item): ?>
            <a href="index.php?post=<?= (int)$item['id'] ?>" class="recent-discussion-item">
              <div class="recent-discussion-left">
                <img src="<?= e($item['avatar']) ?>" alt="<?= e($item['name']) ?>" class="recent-avatar">
                <div class="recent-info">
                  <div class="recent-title"><?= e($item['title']) ?></div>
                  <div class="recent-meta"><?= (int)$item['reply_count'] ?> replies · <?= time_ago($item['created_at']) ?></div>
                </div>
              </div>
              <div class="recent-chevron">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="font-size: 13px; color: var(--text-dim); padding: 8px;">No discussions yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </aside>

</div>

<!-- ======================================================== -->
<!-- MODALS (COMPOSE & EDIT)                                  -->
<!-- ======================================================== -->
<!-- CREATE THREAD MODAL -->
<div class="modal-overlay" id="create-thread-modal">
  <div class="modal-dialog x-compose-dialog">
    <div class="x-modal-top-bar">
      <button type="button" class="x-compose-close-btn" onclick="closeCreateThreadModal()" title="Close">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
          <path d="M10.59 12L4.54 5.96l1.42-1.42L12 10.59l6.04-6.05 1.42 1.42L13.41 12l6.05 6.04-1.42 1.42L12 13.41l-6.04 6.05-1.42-1.42L10.59 12z"/>
        </svg>
      </button>
      <button type="button" class="x-compose-drafts-btn" onclick="alert('No saved drafts.')">Drafts</button>
    </div>

    <form action="index.php" method="POST" enctype="multipart/form-data" id="create-post-form">
      <input type="hidden" name="action" value="create_post">
      <div class="x-compose-body">
        <div class="x-compose-left">
          <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="x-compose-avatar">
        </div>
        <div class="x-compose-right">
          <!-- Title Input -->
          <input type="text" name="title" id="post-title" class="x-compose-title-input" placeholder="Title of your discussion..." required>

          <!-- Main Textarea -->
          <textarea name="content" id="post-content" class="x-compose-textarea" placeholder="What’s happening?" required></textarea>

          <!-- Previews for selected photos -->
          <div class="image-preview-strip" id="create-image-previews"></div>
        </div>
      </div>

      <div class="x-compose-bottom-toolbar">
        <div class="x-toolbar-icons">
          <button type="button" class="x-tool-btn" onclick="document.getElementById('post-images-input').click()" title="Add photos">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
              <path d="M3 5.5C3 4.119 4.119 3 5.5 3h13C19.881 3 21 4.119 21 5.5v13c0 1.381-1.119 2.5-2.5 2.5h-13C4.119 21 3 19.881 3 18.5v-13zM5.5 5c-.276 0-.5.224-.5.5v9.086l3.293-3.293a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414l-1.293 1.293H18.5c.276 0 .5-.224.5-.5V5.5c0-.276-.224-.5-.5-.5h-13zM15 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
            </svg>
          </button>
          <input type="file" name="images[]" id="post-images-input" accept="image/*" multiple style="display: none;" onchange="handleImageSelection(this)">
        </div>

        <button type="submit" class="x-compose-submit-btn" id="btn-submit-post">Post</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT POST MODAL -->
<div class="modal-overlay" id="edit-post-modal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">Edit Discussion Thread</h3>
      <button class="btn-close-modal" onclick="closeEditPostModal()">✕</button>
    </div>
    <form action="index.php" method="POST" enctype="multipart/form-data" id="edit-post-form">
      <input type="hidden" name="action" value="edit_post">
      <input type="hidden" name="post_id" id="edit-post-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="edit-post-title">Thread Title</label>
          <input type="text" name="title" id="edit-post-title" class="form-input" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="edit-post-content">Discussion Content</label>
          <textarea name="content" id="edit-post-content" class="form-textarea" required></textarea>
        </div>

        <!-- Existing Photos (With Delete / Remove Option) -->
        <div class="form-group" id="edit-existing-images-group" style="display: none;">
          <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Current Photos</span>
            <span style="font-size: 11.5px; color: var(--text-muted); font-weight: normal;">Click ✕ on photo to delete</span>
          </label>
          <div class="existing-images-grid" id="edit-existing-images-container" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px;"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Add Photos (Upload up to 4 photos)</label>
          <div class="image-upload-zone" onclick="document.getElementById('edit-images-input').click()">
            <div class="upload-text">Click to add photos</div>
            <input type="file" name="images[]" id="edit-images-input" accept="image/*" multiple style="display: none;" onchange="handleEditImageSelection(this)">
          </div>
          <div class="image-preview-strip" id="edit-image-previews" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal-cancel" onclick="closeEditPostModal()">Cancel</button>
        <button type="submit" class="btn-modal-submit" id="btn-save-edit-post">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- FULL IMAGE LIGHTBOX PREVIEW -->
<div class="lightbox-overlay" id="lightbox-overlay" onclick="closeLightbox(event)">
  <button class="lightbox-close" onclick="closeLightbox(event)">✕</button>
  <img src="" alt="Enlarged view" id="lightbox-img" class="lightbox-img" onclick="event.stopPropagation()">
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toast-container"></div>

<script>
// Inline Fallback for Immediate Reply & Accordion Interactivity
function toggleRepliesSection(postId) {
  const section = document.getElementById('replies-section-' + postId);
  if (!section) return;
  const currentDisplay = window.getComputedStyle(section).display;
  const isHidden = (currentDisplay === 'none');
  section.style.display = isHidden ? 'block' : 'none';
  if (isHidden) {
    const textarea = section.querySelector('textarea');
    if (textarea) {
      setTimeout(function() {
        textarea.focus();
        textarea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 50);
    }
  }
}
function toggleEditReplyForm(replyId) {
  const form = document.getElementById('reply-edit-form-' + replyId);
  const text = document.getElementById('reply-text-' + replyId);
  if (!form) return;
  const isHidden = window.getComputedStyle(form).display === 'none';
  form.style.display = isHidden ? 'block' : 'none';
  if (text) text.style.display = isHidden ? 'none' : 'block';
  if (isHidden) {
    const textarea = form.querySelector('textarea');
    if (textarea) textarea.focus();
  }
}
function toggleNestedComposer(replyId) {
  const composer = document.getElementById('nested-composer-' + replyId);
  if (!composer) return;
  const isHidden = window.getComputedStyle(composer).display === 'none';
  composer.style.display = isHidden ? 'block' : 'none';
  if (isHidden) {
    const textarea = composer.querySelector('textarea');
    if (textarea) textarea.focus();
  }
}
function handlePostCardClick(event, postId) {
  const target = event.target;
  if (target.closest('button, a, input, textarea, label, .post-actions-menu, .post-replies-collapse, .post-media-grid, .post-action-btn, .reply-card')) {
    return;
  }
  window.location.href = 'index.php?post=' + postId;
}

function togglePostMenu(e, menuId) {
  e.stopPropagation();
  const currentMenu = document.getElementById(menuId);
  const isOpen = currentMenu && currentMenu.classList.contains('show');
  document.querySelectorAll('.three-dots-dropdown.show').forEach(el => el.classList.remove('show'));
  if (currentMenu && !isOpen) {
    currentMenu.classList.add('show');
  }
}

function openEditPostModal(idOrBtn, title, content) {
  document.querySelectorAll('.three-dots-dropdown.show').forEach(el => el.classList.remove('show'));
  
  const modal = document.getElementById('edit-post-modal');
  if (!modal) return;

  let id = idOrBtn;
  let rawImages = [];
  if (typeof idOrBtn === 'object' && idOrBtn !== null) {
    const btn = idOrBtn;
    id = btn.getAttribute('data-id');
    title = btn.getAttribute('data-title') || '';
    content = btn.getAttribute('data-content') || '';
    const imgData = btn.getAttribute('data-images');
    if (imgData) {
      try {
        rawImages = JSON.parse(imgData);
      } catch (e) {
        rawImages = [];
      }
    }
  }

  const idInput = document.getElementById('edit-post-id');
  const titleInput = document.getElementById('edit-post-title');
  const contentInput = document.getElementById('edit-post-content');

  if (idInput) idInput.value = id || '';
  if (titleInput) titleInput.value = title || '';
  if (contentInput) contentInput.value = content || '';

  // Remove any previously appended delete_image_ids hidden inputs
  const form = document.getElementById('edit-post-form');
  if (form) {
    form.querySelectorAll('input[name="delete_image_ids[]"]').forEach(el => el.remove());
  }

  // Render current existing images
  const existingGroup = document.getElementById('edit-existing-images-group');
  const existingContainer = document.getElementById('edit-existing-images-container');
  if (existingGroup && existingContainer) {
    existingContainer.innerHTML = '';
    if (Array.isArray(rawImages) && rawImages.length > 0) {
      existingGroup.style.display = 'block';
      rawImages.forEach(img => {
        const item = document.createElement('div');
        item.className = 'existing-img-card';
        item.id = `existing-img-${img.id}`;
        item.innerHTML = `
          <img src="${img.url}" alt="Post image">
          <button type="button" class="btn-remove-existing-img" onclick="removeExistingImage(${img.id}, this)" title="Remove image">✕</button>
        `;
        existingContainer.appendChild(item);
      });
    } else {
      existingGroup.style.display = 'none';
    }
  }

  // Reset file input and preview strip for new uploads
  const editPreviewStrip = document.getElementById('edit-image-previews');
  if (editPreviewStrip) editPreviewStrip.innerHTML = '';
  const fileInput = document.getElementById('edit-images-input');
  if (fileInput) fileInput.value = '';

  modal.classList.add('active');
}

function removeExistingImage(imageId, btn) {
  const card = document.getElementById(`existing-img-${imageId}`);
  if (card) {
    card.classList.add('marked-for-delete');
    btn.outerHTML = `
      <button type="button" class="btn-undo-existing-img" onclick="undoRemoveExistingImage(${imageId}, this)" title="Undo remove">↩</button>
      <div class="delete-badge">Delete</div>
    `;

    const form = document.getElementById('edit-post-form');
    if (form && !document.getElementById(`input-del-img-${imageId}`)) {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'delete_image_ids[]';
      hidden.id = `input-del-img-${imageId}`;
      hidden.value = imageId;
      form.appendChild(hidden);
    }
  }
}

function undoRemoveExistingImage(imageId, btn) {
  const card = document.getElementById(`existing-img-${imageId}`);
  if (card) {
    card.classList.remove('marked-for-delete');
    const badge = card.querySelector('.delete-badge');
    if (badge) badge.remove();

    btn.outerHTML = `
      <button type="button" class="btn-remove-existing-img" onclick="removeExistingImage(${imageId}, this)" title="Remove image">✕</button>
    `;

    const hidden = document.getElementById(`input-del-img-${imageId}`);
    if (hidden) hidden.remove();
  }
}

function closeEditPostModal() {
  const modal = document.getElementById('edit-post-modal');
  if (modal) modal.classList.remove('active');
}

function handleEditImageSelection(input) {
  const previewContainer = document.getElementById('edit-image-previews');
  if (!previewContainer) return;
  previewContainer.innerHTML = '';

  const files = Array.from(input.files);
  files.slice(0, 4).forEach((file) => {
    if (!file.type.startsWith('image/')) return;
    const reader = new window.FileReader();
    reader.onload = (e) => {
      const thumb = document.createElement('div');
      thumb.className = 'preview-thumb-container';
      thumb.style.cssText = 'position: relative; width: 70px; height: 70px; border-radius: 8px; overflow: hidden; display: inline-block; margin-right: 8px; margin-top: 8px; border: 1px solid var(--border-color);';
      thumb.innerHTML = `
        <img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
        <button type="button" class="btn-remove-thumb" onclick="removeSelectedEditFile(this)" style="position: absolute; top: 3px; right: 3px; width: 18px; height: 18px; border-radius: 50%; background: rgba(0,0,0,0.75); color: #fff; border: none; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center;">✕</button>
      `;
      previewContainer.appendChild(thumb);
    };
    reader.readAsDataURL(file);
  });
}

function removeSelectedEditFile(btn) {
  btn.closest('.preview-thumb-container').remove();
  const previewContainer = document.getElementById('edit-image-previews');
  if (previewContainer && previewContainer.children.length === 0) {
    const fileInput = document.getElementById('edit-images-input');
    if (fileInput) fileInput.value = '';
  }
}

window.handlePostCardClick = handlePostCardClick;
window.toggleRepliesSection = toggleRepliesSection;
window.toggleEditReplyForm = toggleEditReplyForm;
window.toggleNestedComposer = toggleNestedComposer;
window.togglePostMenu = togglePostMenu;
window.openEditPostModal = openEditPostModal;
window.closeEditPostModal = closeEditPostModal;
window.removeExistingImage = removeExistingImage;
window.undoRemoveExistingImage = undoRemoveExistingImage;
window.handleEditImageSelection = handleEditImageSelection;
window.removeSelectedEditFile = removeSelectedEditFile;
</script>
<script src="assets/js/main.js?v=<?= file_exists(__DIR__ . '/assets/js/main.js') ? filemtime(__DIR__ . '/assets/js/main.js') : time() ?>"></script>
</body>
</html>
