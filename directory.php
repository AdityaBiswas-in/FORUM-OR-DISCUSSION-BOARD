<?php
// directory.php - Campus Members & Students Directory
$pageTitle = 'Campus Directory';
require_once __DIR__ . '/includes/header.php';

$sql = "
    SELECT u.*,
           (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) as threads_count,
           (SELECT COUNT(*) FROM replies r WHERE r.user_id = u.id) as replies_count
    FROM users u
    ORDER BY threads_count DESC, replies_count DESC
";
$members = $pdo->query($sql)->fetchAll();
?>

<!-- LEFT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

<!-- MAIN FEED COLUMN -->
<main class="main-feed-column">
  <div style="margin-bottom: 24px;">
    <h1 style="font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Campus Directory</h1>
    <p style="color: var(--text-muted); font-size: 14px;">Connect with fellow students, engineers, and creators on CampusForum.</p>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
    <?php if (empty($members)): ?>
      <div style="grid-column: 1 / -1; text-align: center; padding: 48px 20px; background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); color: var(--text-muted);">
        No campus members registered yet.
      </div>
    <?php else: ?>
      <?php foreach ($members as $m): ?>
        <div class="post-card" style="padding: 20px;">
          <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 12px;">
            <img src="<?= e($m['avatar']) ?>" alt="<?= e($m['name']) ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
            <div>
              <div style="font-size: 16px; font-weight: 800; color: var(--text-main);"><?= e($m['name']) ?></div>
              <div style="font-size: 13px; color: var(--text-dim);">@<?= e($m['username']) ?></div>
            </div>
          </div>
          <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px; min-height: 38px;">
            <?= e($m['bio'] ?: 'Campus student active in community discussions.') ?>
          </p>
          <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--border-color); font-size: 12.5px; color: var(--text-dim);">
            <span>📝 <?= (int)$m['threads_count'] ?> threads</span>
            <span>💬 <?= (int)$m['replies_count'] ?> replies</span>
            <span style="color: var(--accent-cyan); font-weight: 700; font-size: 11.5px; text-transform: uppercase;">Member</span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<!-- RIGHT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>

<!-- FOOTER -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
