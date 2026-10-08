<?php
// index.php - Main Discussion Feed for CampusForum
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';

// Topic filter
$current_topic_slug = $_GET['topic'] ?? '';
$search_query = trim($_GET['q'] ?? '');

$sql = "
    SELECT p.*, u.name as author_name, u.username as author_username, u.avatar as author_avatar,
           t.name as topic_name, t.slug as topic_slug,
           (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    JOIN topics t ON p.topic_id = t.id
";

$params = [];
$where = [];

if (!empty($current_topic_slug)) {
    $where[] = "t.slug = ?";
    $params[] = $current_topic_slug;
}

if (!empty($search_query)) {
    $where[] = "(p.title LIKE ? OR p.content LIKE ?)";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$topics = get_all_topics();
?>

<!-- LEFT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

<!-- MAIN FEED COLUMN -->
<main class="main-feed-column">
  <!-- Search Bar matching Image 1 -->
  <div class="search-container">
    <form action="index.php" method="GET" class="search-box">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <input type="text" name="q" placeholder="Search discussions..." value="<?= e($search_query) ?>" autocomplete="off">
      <?php if (!empty($current_topic_slug)): ?>
        <input type="hidden" name="topic" value="<?= e($current_topic_slug) ?>">
      <?php endif; ?>
    </form>
  </div>

  <!-- Quick Topic Pills Filter -->
  <div class="topic-pills-bar">
    <a href="index.php" class="topic-pill <?= empty($current_topic_slug) ? 'active' : '' ?>">All Topics</a>
    <?php foreach ($topics as $top): ?>
      <a href="index.php?topic=<?= e($top['slug']) ?>" class="topic-pill <?= ($current_topic_slug === $top['slug']) ? 'active' : '' ?>">
        # <?= e($top['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- X-style "What's happening?" Quick Composer -->
  <div class="x-quick-composer" onclick="openCreateThreadModal()">
    <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="composer-avatar">
    <div class="composer-content-box">
      <div class="composer-placeholder">What's happening?</div>
      <div class="composer-bottom-bar">
        <div class="composer-tools">
          <!-- Only Image upload option as requested -->
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

  <!-- Show New Posts Indicator matching screenshot -->
  <div class="show-new-posts-banner" onclick="window.location.reload()">
    Show new posts
  </div>

  <!-- Discussion Posts Feed -->
  <div class="posts-feed" id="posts-feed-container">
    <?php if (!empty($posts)): ?>
      <?php foreach ($posts as $post): 
        $postImages = get_post_images($post['id']);
        $imgCount = count($postImages);
        $isAuthor = ($currentUser && (int)$post['user_id'] === (int)$currentUser['id']);
        $isLiked = $currentUser ? user_has_liked($currentUser['id'], $post['id']) : false;
        $isSaved = $currentUser ? user_has_saved($currentUser['id'], $post['id']) : false;
        $likeCount = get_like_count($post['id']);
      ?>
        <article class="post-card" onclick="goToThread(event, 'thread.php?id=<?= (int)$post['id'] ?>')" data-post-id="<?= (int)$post['id'] ?>">
          <div class="post-avatar-col">
            <img src="<?= e($post['author_avatar']) ?>" alt="<?= e($post['author_name']) ?>" class="author-avatar">
          </div>
          
          <div class="post-main-col">
            <!-- Header: Name, Verified Badge, @handle, ·, timestamp, topic, 3-dots -->
            <div class="post-header">
              <div class="post-author-meta">
                <span class="author-name"><?= e($post['author_name']) ?></span>
                <!-- X Verified Blue Badge -->
                <svg class="verified-badge" viewBox="0 0 24 24" width="18" height="18" fill="#1d9bf0">
                  <path d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.79-4-4-4-.495 0-.965.084-1.4.238C14.55 2.475 13.18 1.6 11.6 1.6c-1.58 0-2.95.875-3.6 2.148-.435-.154-.905-.238-1.4-.238-2.21 0-4 1.79-4 4 0 .495.084.965.238 1.4C1.575 10.45.7 11.82.7 13.4c0 1.58.875 2.95 2.148 3.6-.154.435-.238.905-.238 1.4 0 2.21 1.79 4 4 4 .495 0 .965-.084 1.4-.238.65 1.273 2.02 2.148 3.6 2.148 1.58 0 2.95-.875 3.6-2.148.435.154.905.238 1.4.238 2.21 0 4-1.79 4-4 0-.495-.084-.965-.238-1.4 1.273-.65 2.148-2.02 2.148-3.6zm-12.87 4.16l-3.29-3.29 1.41-1.41 1.88 1.88 5.18-5.18 1.41 1.41-6.59 6.59z"/>
                </svg>
                <span class="user-handle">@<?= e($post['author_username']) ?></span>
                <span class="post-dot">·</span>
                <span class="post-timestamp"><?= time_ago($post['created_at']) ?></span>
              </div>

              <div class="post-header-right">
                <span class="post-topic-badge"><?= e($post['topic_name']) ?></span>
                
                <!-- 3-Dots Action Menu -->
                <div class="post-actions-menu" onclick="event.stopPropagation()">
                  <button type="button" class="btn-three-dots" title="More" onclick="togglePostMenu(event, 'post-menu-<?= (int)$post['id'] ?>')">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                      <path d="M3 12c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm9 2c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm7 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                    </svg>
                  </button>
                  <div class="three-dots-dropdown" id="post-menu-<?= (int)$post['id'] ?>">
                    <?php if ($isAuthor): ?>
                      <button type="button" onclick="openEditPostModal(<?= (int)$post['id'] ?>, <?= htmlspecialchars(json_encode($post['title']), ENT_QUOTES) ?>, <?= (int)$post['topic_id'] ?>, <?= htmlspecialchars(json_encode($post['content']), ENT_QUOTES) ?>)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit Thread
                      </button>
                      <button type="button" class="btn-delete" onclick="confirmDeletePost(<?= (int)$post['id'] ?>)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Delete Thread
                      </button>
                    <?php else: ?>
                      <button type="button" onclick="copyThreadLink(<?= (int)$post['id'] ?>)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                          <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                        Copy Link
                      </button>
                      <button type="button" onclick="toggleSavePost(event, <?= (int)$post['id'] ?>)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"></path>
                        </svg>
                        Save Discussion
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Post Title -->
            <h2 class="post-title"><?= e($post['title']) ?></h2>

            <!-- Post Content Text -->
            <p class="post-body-text"><?= nl2br(e($post['content'])) ?></p>

            <!-- Adaptive Image Grid (Twitter / X style) -->
            <?php if ($imgCount > 0): ?>
              <div class="post-media-grid grid-<?= min($imgCount, 4) ?>" onclick="event.stopPropagation()">
                <?php foreach ($postImages as $img): ?>
                  <div class="post-media-item" onclick="openLightbox('<?= e($img) ?>')">
                    <img src="<?= e($img) ?>" alt="Post attachment" loading="lazy">
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <!-- Card Footer: replies count and bookmark -->
            <div class="post-footer" onclick="event.stopPropagation()">
              <!-- Reply button -->
              <a href="thread.php?id=<?= (int)$post['id'] ?>" class="post-action-btn action-reply" title="Reply">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span><?= (int)$post['reply_count'] > 0 ? (int)$post['reply_count'] . ' replies' : 'Reply' ?></span>
              </a>

              <!-- Right icons: Bookmark & Share -->
              <div class="post-footer-right-icons">
                <button type="button" class="post-action-btn action-bookmark <?= $isSaved ? 'active-saved' : '' ?>" id="save-btn-<?= (int)$post['id'] ?>" onclick="toggleSavePost(event, <?= (int)$post['id'] ?>)" title="Bookmark">
                  <svg viewBox="0 0 24 24" fill="<?= $isSaved ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.8">
                    <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"></path>
                  </svg>
                </button>
                <button type="button" class="post-action-btn action-share" onclick="copyThreadLink(<?= (int)$post['id'] ?>)" title="Share">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                    <polyline points="16 6 12 2 8 6"></polyline>
                    <line x1="12" y1="2" x2="12" y2="15"></line>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align: center; padding: 60px 20px; background: var(--bg-card); border-radius: 18px; border: 1px solid var(--border-color);">
        <p style="color: var(--text-muted); font-size: 16px; margin-bottom: 14px;">
          <?= !empty($search_query) ? 'No discussions found matching your query.' : 'No discussions yet. Be the first to start a conversation!' ?>
        </p>
        <button class="btn-create-thread-welcome" style="display: inline-flex; width: auto;" onclick="openCreateThreadModal()">Start a New Discussion</button>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- RIGHT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>

<!-- FOOTER -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
