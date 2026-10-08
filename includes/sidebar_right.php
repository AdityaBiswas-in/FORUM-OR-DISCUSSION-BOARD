<?php
// includes/sidebar_right.php - Right Sidebar with Recent Discussions
$recentDiscussions = get_recent_discussions(5);
?>
<aside class="sidebar-right">
  <div class="recent-discussions-card">
    <h3 class="recent-card-header">Recent Discussions</h3>
    <div class="recent-discussions-list">
      <?php if (!empty($recentDiscussions)): ?>
        <?php foreach ($recentDiscussions as $item): ?>
          <a href="thread.php?id=<?= (int)$item['id'] ?>" class="recent-discussion-item">
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
        <p style="font-size: 13px; color: var(--text-dim);">No discussions yet.</p>
      <?php endif; ?>
    </div>
  </div>
</aside>

