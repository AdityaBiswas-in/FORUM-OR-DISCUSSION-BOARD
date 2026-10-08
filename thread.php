<?php
// thread.php - Single Discussion Thread & Nested Replies
$post_id = (int)($_GET['id'] ?? 0);

if (!$post_id) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';

// Increment view count
$pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?")->execute([$post_id]);

// Fetch post with author and topic details
$stmt = $pdo->prepare("
    SELECT p.*, u.name as author_name, u.username as author_username, u.avatar as author_avatar,
           t.name as topic_name, t.slug as topic_slug
    FROM posts p
    JOIN users u ON p.user_id = u.id
    JOIN topics t ON p.topic_id = t.id
    WHERE p.id = ?
");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    echo "<div style='padding: 60px; text-align: center;'><h2>Discussion not found</h2><a href='index.php'>Return to discussions</a></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $post['title'];
$postImages = get_post_images($post['id']);
$imgCount = count($postImages);
$isAuthor = ($currentUser && (int)$post['user_id'] === (int)$currentUser['id']);
$isLiked = $currentUser ? user_has_liked($currentUser['id'], $post['id']) : false;
$isSaved = $currentUser ? user_has_saved($currentUser['id'], $post['id']) : false;
$likeCount = get_like_count($post['id']);
$replyCount = get_reply_count($post['id']);

// Get hierarchical replies tree
$repliesTree = get_post_replies_tree($post['id']);

// Recursive function to render nested replies
function renderReplyNode($reply, $currentUser, $postId, $depth = 0) {
    $isReplyAuthor = ($currentUser && (int)$reply['user_id'] === (int)$currentUser['id']);
    ?>
    <div class="reply-node" id="reply-<?= (int)$reply['id'] ?>">
      <div class="reply-card">
        <div class="reply-header">
          <div class="reply-author">
            <img src="<?= e($reply['avatar']) ?>" alt="<?= e($reply['name']) ?>" class="reply-avatar">
            <span class="reply-author-name"><?= e($reply['name']) ?></span>
            <span class="reply-timestamp">· <?= time_ago($reply['created_at']) ?></span>
            <?php if (!empty($reply['updated_at'])): ?>
              <span style="font-size: 11px; color: var(--text-dim);">(edited)</span>
            <?php endif; ?>
          </div>

          <!-- Actions for reply author (Edit, Delete) -->
          <div style="display: flex; align-items: center; gap: 8px;">
            <?php if ($isReplyAuthor): ?>
              <button type="button" class="btn-edit-reply" onclick="toggleEditReplyForm(<?= (int)$reply['id'] ?>)">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Edit
              </button>
              <a href="api/delete_reply.php?id=<?= (int)$reply['id'] ?>" class="btn-delete-reply" onclick="return confirm('Delete this reply?')">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Delete
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Normal Reply Text View -->
        <div class="reply-body-text" id="reply-text-<?= (int)$reply['id'] ?>">
          <?= nl2br(e($reply['content'])) ?>
        </div>

        <!-- Inline Edit Form (Hidden until Edit is clicked) -->
        <?php if ($isReplyAuthor): ?>
          <form action="api/edit_reply.php" method="POST" id="reply-edit-form-<?= (int)$reply['id'] ?>" style="display: none; margin-bottom: 10px;">
            <input type="hidden" name="reply_id" value="<?= (int)$reply['id'] ?>">
            <textarea name="content" class="reply-textarea" style="min-height: 70px; margin-bottom: 8px;" required><?= e($reply['content']) ?></textarea>
            <div style="display: flex; gap: 8px;">
              <button type="submit" class="btn-post-reply" style="padding: 6px 14px; font-size: 13px;">Save</button>
              <button type="button" class="btn-modal-cancel" style="padding: 6px 14px; font-size: 13px;" onclick="toggleEditReplyForm(<?= (int)$reply['id'] ?>)">Cancel</button>
            </div>
          </form>
        <?php endif; ?>

        <!-- Reply Image Attachment if present -->
        <?php if (!empty($reply['image_url'])): ?>
          <div class="reply-image-attachment" onclick="openLightbox('<?= e($reply['image_url']) ?>')">
            <img src="<?= e($reply['image_url']) ?>" alt="Reply photo">
          </div>
        <?php endif; ?>

        <!-- Reply Actions (Reply to this reply button) -->
        <div class="reply-actions-bar">
          <button type="button" class="btn-nested-reply" onclick="toggleNestedComposer(<?= (int)$reply['id'] ?>)">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 10h10a5 5 0 0 1 5 5v5"></path>
              <path d="m7 6-4 4 4 4"></path>
            </svg>
            Reply
          </button>
        </div>

        <!-- Nested Composer for replying to this specific comment -->
        <div class="inline-nested-composer" id="nested-composer-<?= (int)$reply['id'] ?>">
          <form action="api/create_reply.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="post_id" value="<?= (int)$postId ?>">
            <input type="hidden" name="parent_id" value="<?= (int)$reply['id'] ?>">
            <textarea name="content" class="reply-textarea" style="min-height: 65px; margin-bottom: 8px;" placeholder="Replying to @<?= e($reply['username']) ?>..." required></textarea>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <label class="btn-attach-image" style="cursor: pointer;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                  <circle cx="8.5" cy="8.5" r="1.5"></circle>
                  <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
                Image
                <input type="file" name="reply_image" accept="image/*" style="display: none;">
              </label>
              <div style="display: flex; gap: 8px;">
                <button type="button" class="btn-modal-cancel" style="padding: 6px 14px; font-size: 13px;" onclick="toggleNestedComposer(<?= (int)$reply['id'] ?>)">Cancel</button>
                <button type="submit" class="btn-post-reply" style="padding: 6px 16px; font-size: 13px;">Reply</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Nested Sub-Replies Tree (reply Ons reply) -->
      <?php if (!empty($reply['children'])): ?>
        <div class="reply-children-container">
          <?php foreach ($reply['children'] as $childReply): ?>
            <?php renderReplyNode($childReply, $currentUser, $postId, $depth + 1); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}
?>

<!-- LEFT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

<!-- MAIN FEED COLUMN -->
<main class="main-feed-column">
  <!-- Back Button Header -->
  <div class="thread-header-bar">
    <a href="index.php" class="btn-back-feed">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      Back to discussions
    </a>
  </div>

  <!-- THE ORIGINAL POST -->
  <article class="thread-original-post" data-post-id="<?= (int)$post['id'] ?>">
    <div class="post-header">
      <div class="post-author-meta">
        <img src="<?= e($post['author_avatar']) ?>" alt="<?= e($post['author_name']) ?>" class="author-avatar">
        <div class="author-details">
          <span class="author-name"><?= e($post['author_name']) ?></span>
          <svg class="verified-badge" viewBox="0 0 24 24" width="18" height="18" fill="#1d9bf0">
            <path d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.79-4-4-4-.495 0-.965.084-1.4.238C14.55 2.475 13.18 1.6 11.6 1.6c-1.58 0-2.95.875-3.6 2.148-.435-.154-.905-.238-1.4-.238-2.21 0-4 1.79-4 4 0 .495.084.965.238 1.4C1.575 10.45.7 11.82.7 13.4c0 1.58.875 2.95 2.148 3.6-.154.435-.238.905-.238 1.4 0 2.21 1.79 4 4 4 .495 0 .965-.084 1.4-.238.65 1.273 2.02 2.148 3.6 2.148 1.58 0 2.95-.875 3.6-2.148.435.154.905.238 1.4.238 2.21 0 4-1.79 4-4 0-.495-.084-.965-.238-1.4 1.273-.65 2.148-2.02 2.148-3.6zm-12.87 4.16l-3.29-3.29 1.41-1.41 1.88 1.88 5.18-5.18 1.41 1.41-6.59 6.59z"/>
          </svg>
          <span class="user-handle" style="font-size: 14px;">@<?= e($post['author_username']) ?></span>
          <span class="post-dot">·</span>
          <span class="post-timestamp"><?= time_ago($post['created_at']) ?></span>
        </div>
      </div>

      <div class="post-header-right">
        <span class="post-topic-badge"><?= e($post['topic_name']) ?></span>
        
        <!-- 3-Dots Action Menu -->
        <div class="post-actions-menu">
          <button type="button" class="btn-three-dots" title="More options" onclick="togglePostMenu(event, 'thread-post-menu')">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
              <path d="M3 12c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm9 2c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm7 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
            </svg>
          </button>
          <div class="three-dots-dropdown" id="thread-post-menu">
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
    <h1 class="post-title" style="font-size: 22px; margin-bottom: 12px;"><?= e($post['title']) ?></h1>

    <!-- Post Body Text -->
    <p class="post-body-text" style="font-size: 15px;"><?= nl2br(e($post['content'])) ?></p>

    <!-- Post Images Gallery -->
    <?php if ($imgCount > 0): ?>
      <div class="post-media-grid grid-<?= min($imgCount, 4) ?>" style="margin: 20px 0;">
        <?php foreach ($postImages as $img): ?>
          <div class="post-media-item" onclick="openLightbox('<?= e($img) ?>')">
            <img src="<?= e($img) ?>" alt="Thread media" loading="lazy">
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Interactive Actions Bar -->
    <div class="post-footer" style="border-top: 1px solid #2f3336; border-bottom: 1px solid #2f3336; padding: 12px 4px; margin-bottom: 20px;">
      <!-- Reply count -->
      <div class="post-action-btn action-reply" title="Replies">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <span><?= $replyCount ?> <?= $replyCount === 1 ? 'reply' : 'replies' ?></span>
      </div>

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
  </article>

  <!-- LEAVE A REPLY COMPOSER BOX -->
  <div class="reply-composer-card">
    <div class="reply-composer-header">
      <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="reply-avatar">
      <span style="font-size: 14px; font-weight: 700; color: #fff;">Replying as <?= e($currentUser['name']) ?></span>
    </div>

    <form action="api/create_reply.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
      <textarea name="content" class="reply-textarea" placeholder="Post your reply or answer..." required></textarea>

      <!-- Reply image preview strip -->
      <div class="image-preview-strip" id="reply-image-preview" style="display: none; margin-bottom: 12px;"></div>

      <div class="reply-composer-actions">
        <label class="btn-attach-image" style="cursor: pointer;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <circle cx="8.5" cy="8.5" r="1.5"></circle>
            <polyline points="21 15 16 10 5 21"></polyline>
          </svg>
          <span>Attach Photo</span>
          <input type="file" name="reply_image" accept="image/*" style="display: none;" onchange="previewReplyImage(this)">
        </label>

        <button type="submit" class="btn-post-reply">Post Reply</button>
      </div>
    </form>
  </div>

  <!-- DISCUSSION REPLIES TREE -->
  <section class="replies-section">
    <h3 class="replies-section-title">
      Replies (<?= count($repliesTree) ?> threads, <?= $replyCount ?> total)
    </h3>

    <?php if (!empty($repliesTree)): ?>
      <div class="replies-tree-list">
        <?php foreach ($repliesTree as $reply): ?>
          <?php renderReplyNode($reply, $currentUser, $post['id']); ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 40px 20px; background: var(--bg-card); border-radius: 14px; border: 1px solid var(--border-color); color: var(--text-muted); font-size: 14px;">
        No replies yet. Be the first to share your thoughts!
      </div>
    <?php endif; ?>
  </section>
</main>

<!-- RIGHT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>

<!-- FOOTER -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
