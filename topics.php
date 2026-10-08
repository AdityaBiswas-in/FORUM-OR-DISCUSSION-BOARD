<?php
// topics.php - Topics Directory for CampusForum
$pageTitle = 'Explore Topics';
require_once __DIR__ . '/includes/header.php';

$sql = "
    SELECT t.*, COUNT(p.id) as thread_count
    FROM topics t
    LEFT JOIN posts p ON t.id = p.topic_id
    GROUP BY t.id
    ORDER BY thread_count DESC
";
$topics = $pdo->query($sql)->fetchAll();
?>

<!-- LEFT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

<!-- MAIN FEED COLUMN -->
<main class="main-feed-column">
  <div style="margin-bottom: 24px;">
    <h1 style="font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Campus Topics</h1>
    <p style="color: var(--text-muted); font-size: 14px;">Browse and discover discussions across college categories.</p>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
    <?php foreach ($topics as $t): ?>
      <a href="index.php?topic=<?= e($t['slug']) ?>" class="post-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 20px;">
        <div>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
            <span class="post-topic-badge" style="font-size: 13px;"># <?= e($t['name']) ?></span>
            <span style="font-size: 12px; color: var(--text-dim);"><?= (int)$t['thread_count'] ?> threads</span>
          </div>
          <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px;">
            <?= e($t['description']) ?>
          </p>
        </div>
        <div style="font-size: 13px; font-weight: 700; color: var(--accent-cyan); display: flex; align-items: center; gap: 6px;">
          View Discussions
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</main>

<!-- RIGHT SIDEBAR -->
<?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>

<!-- FOOTER -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
