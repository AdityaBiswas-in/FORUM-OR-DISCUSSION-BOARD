<?php
// saved.php - Bookmarked & Saved Discussions
$pageTitle = 'Saved Discussions';
require_once __DIR__ . '/includes/header.php';

$current_user_id = $currentUser['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT p.*, u.name as author_name, u.username as author_username, u.avatar as author_avatar,
           t.name as topic_name, t.slug as topic_slug,
           (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count
    FROM saved_posts sp
    JOIN posts p ON sp.post_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN topics t ON p.topic_id = t.id
    WHERE sp.user_id = ?
    ORDER BY sp.created_at DESC
");
$stmt->execute([$current_user_id]);
$savedPosts = $stmt->fetchAll();
?>

<!-- LEFT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

<!-- MAIN FEED COLUMN -->
<main class="main-feed-column">
  <div style="margin-bottom: 24px;">
    <h1 style="font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Saved Discussions</h1>
    <p style="color: var(--text-muted); font-size: 14px;">Threads and questions you have bookmarked for later.</p>
  </div>

  <div class="posts-feed">
    <?php if (!empty($savedPosts)): ?>
      <?php foreach ($savedPosts as $post): 
        $postImages = get_post_images($post['id']);
        $imgCount = count($postImages);
        $likeCount = get_like_count($post['id']);
      ?>
        <article class="post-card" onclick="goToThread(event, 'thread.php?id=<?= (int)$post['id'] ?>')">
          <div class="post-header">
            <div class="post-author-meta">
              <img src="<?= e($post['author_avatar']) ?>" alt="<?= e($post['author_name']) ?>" class="author-avatar">
              <div class="author-details">
                <span class="author-name"><?= e($post['author_name']) ?></span>
                <span class="post-timestamp">· <?= time_ago($post['created_at']) ?></span>
              </div>
            </div>
            <span class="post-topic-badge"><?= e($post['topic_name']) ?></span>
          </div>

          <h2 class="post-title"><?= e($post['title']) ?></h2>
          <p class="post-body-text"><?= nl2br(e($post['content'])) ?></p>

          <?php if ($imgCount > 0): ?>
            <div class="post-media-grid grid-<?= min($imgCount, 4) ?>" onclick="event.stopPropagation()">
              <?php foreach ($postImages as $img): ?>
                <div class="post-media-item" onclick="openLightbox('<?= e($img) ?>')">
                  <img src="<?= e($img) ?>" alt="Post attachment">
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="post-footer" onclick="event.stopPropagation()">
            <span class="post-action-btn">
              💬 <?= (int)$post['reply_count'] ?> replies
            </span>
            <button type="button" class="post-action-btn active-saved" onclick="toggleSavePost(event, <?= (int)$post['id'] ?>)">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" width="18" height="18">
                <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"></path>
              </svg>
              <span>Saved</span>
            </button>
          </div>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align: center; padding: 60px 20px; background: var(--bg-card); border-radius: 18px; border: 1px solid var(--border-color);">
        <p style="color: var(--text-muted); font-size: 15px; margin-bottom: 14px;">You haven't saved any discussions yet.</p>
        <a href="index.php" class="btn-create-thread-welcome" style="display: inline-flex; width: auto;">Explore Discussions</a>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- RIGHT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>

<!-- FOOTER -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
