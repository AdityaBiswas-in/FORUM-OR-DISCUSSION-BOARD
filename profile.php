<?php
// profile.php - User Profile Page with Posts, Replies, and Discussion Comments Activity
require_once __DIR__ . '/db.php';

// ========================================================
// 1. SESSION & CURRENT USER SETUP
// ========================================================
$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $result = $conn->execute_query("SELECT id, username, email, name, avatar, role, bio, banner_url, created_at FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $currentUser = $result->fetch_assoc();
}

if (!$currentUser) {
    unset($_SESSION['user_id']);
    header("Location: login.php");
    exit;
}

// Global time_ago helper if not already defined
if (!function_exists('time_ago')) {
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
}

// ========================================================
// 2. PROFILE TARGET USER RESOLUTION
// ========================================================
$profileUser = null;
$targetId = (int)($_GET['id'] ?? 0);
$targetUsername = trim($_GET['u'] ?? '');

if ($targetId > 0) {
    $result = $conn->execute_query("SELECT * FROM users WHERE id = ?", [$targetId]);
    $profileUser = $result->fetch_assoc();
} elseif (!empty($targetUsername)) {
    $result = $conn->execute_query("SELECT * FROM users WHERE username = ?", [$targetUsername]);
    $profileUser = $result->fetch_assoc();
} else {
    $profileUser = $currentUser;
}

if (!$profileUser) {
    $profileUser = $currentUser;
}

$isOwnProfile = ($currentUser && (int)$currentUser['id'] === (int)$profileUser['id']);
$profileId = (int)$profileUser['id'];

// ========================================================
// 3. EDIT PROFILE ACTION HANDLER
// ========================================================
$errorMsg = '';
$successMsg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!$isOwnProfile) {
        header("Location: profile.php?id=" . $profileId);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $bio  = trim($_POST['bio'] ?? '');

    if (empty($name)) {
        $errorMsg = "Display Name cannot be empty.";
    } else {
        $uploadDir = __DIR__ . '/assets/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $newAvatar = null;
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts)) {
                $avatarFileName = 'avatar_' . $profileId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $avatarFileName)) {
                    $newAvatar = 'assets/uploads/' . $avatarFileName;
                }
            }
        }

        $newBanner = null;
        if (!empty($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts)) {
                $bannerFileName = 'banner_' . $profileId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $uploadDir . $bannerFileName)) {
                    $newBanner = 'assets/uploads/' . $bannerFileName;
                }
            }
        }

        $updateSql = "UPDATE users SET name = ?, bio = ?";
        $params = [$name, $bio];

        if ($newAvatar !== null) {
            $updateSql .= ", avatar = ?";
            $params[] = $newAvatar;
        }
        if ($newBanner !== null) {
            $updateSql .= ", banner_url = ?";
            $params[] = $newBanner;
        }

        $updateSql .= " WHERE id = ?";
        $params[] = $profileId;

        $conn->execute_query($updateSql, $params);

        header("Location: profile.php?id=" . $profileId . "&updated=1");
        exit;
    }
}

if (!empty($_GET['updated'])) {
    $successMsg = "Profile updated successfully!";
}

// Refresh profile user data
$result = $conn->execute_query("SELECT * FROM users WHERE id = ?", [$profileId]);
$profileUser = $result->fetch_assoc() ?: $profileUser;
if ($isOwnProfile) {
    $currentUser = $profileUser;
}

// ========================================================
// 4. COUNTS & TAB DATA FETCHING
// ========================================================
$postCount = (int)($conn->query("SELECT COUNT(*) FROM posts WHERE user_id = {$profileId}")->fetch_row()[0] ?? 0);
$replyCount = (int)($conn->query("SELECT COUNT(*) FROM replies WHERE user_id = {$profileId}")->fetch_row()[0] ?? 0);

$tab = $_GET['tab'] ?? 'posts';
if (!in_array($tab, ['posts', 'replies'])) {
    $tab = 'posts';
}

$userPosts = [];
$postImages = [];
$userReplies = [];
$commentsOnPosts = [];

if ($tab === 'posts') {
    $result = $conn->execute_query("
        SELECT p.*, u.name as author_name, u.username as author_username, u.avatar as author_avatar,
               (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count,
               (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.reply_id IS NULL) as like_count
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ", [$profileId]);
    $userPosts = $result->fetch_all(MYSQLI_ASSOC);

    $p_ids = array_column($userPosts, 'id');
    if (!empty($p_ids)) {
        $in = implode(',', array_fill(0, count($p_ids), '?'));
        $imgResult = $conn->execute_query("SELECT post_id, image_url FROM post_images WHERE post_id IN ($in) ORDER BY id ASC", $p_ids);
        while ($row = $imgResult->fetch_assoc()) {
            $postImages[$row['post_id']][] = $row['image_url'];
        }
    }
} elseif ($tab === 'replies') {
    $result = $conn->execute_query("
        SELECT r.*, 
               p.title as post_title, p.id as post_id, p.content as post_content, p.created_at as post_created_at,
               pu.id as post_author_id, pu.name as post_author_name, pu.username as post_author_username, pu.avatar as post_author_avatar,
               u.name as author_name, u.username as author_username, u.avatar as author_avatar
        FROM replies r
        JOIN posts p ON r.post_id = p.id
        JOIN users pu ON p.user_id = pu.id
        JOIN users u ON r.user_id = u.id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ", [$profileId]);
    $userReplies = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch Recent Discussions for right sidebar
$recentDiscussions = $conn->query("
    SELECT p.id, p.title, p.created_at, u.name, u.avatar,
           (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
















<!-- ========================================
HTML PART
======================================== -->


<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($profileUser['name']) ?> (@<?= e($profileUser['username']) ?>) - Quaddit</title>
  <link rel="icon" type="image/jpeg" href="assets/images/logo.jpg">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
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
        <li class="nav-item">
          <a href="index.php" id="nav-home">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
            </svg>
            <span>Home</span>
          </a>
        </li>
        <li class="nav-item <?= $isOwnProfile ? 'active' : '' ?>">
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
      <a href="index.php" class="btn-create-thread-sidebar" style="text-decoration: none; text-align: center; display: block;">
        <span>Post</span>
      </a>
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
  <!-- MAIN PROFILE COLUMN                                      -->
  <!-- ======================================================== -->
  <main class="main-feed-column">
    
    <!-- Top Header Bar with Back Button -->
    <div class="profile-top-header">
      <a href="index.php" class="btn-profile-back" title="Back to discussions">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
      </a>
      <div class="profile-header-titles">
        <h2><?= e($profileUser['name']) ?></h2>
        <span><?= $postCount ?> Discussions</span>
      </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($successMsg)): ?>
      <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #10b981; padding: 10px 16px; margin: 12px 16px 0 16px; border-radius: 10px; font-size: 13.5px; font-weight: 600;">
        <?= e($successMsg) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($errorMsg)): ?>
      <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 10px 16px; margin: 12px 16px 0 16px; border-radius: 10px; font-size: 13.5px; font-weight: 600;">
        <?= e($errorMsg) ?>
      </div>
    <?php endif; ?>

    <!-- HERO BANNER -->
    <div class="profile-banner-hero">
      <?php if (!empty($profileUser['banner_url'])): ?>
        <img src="<?= e($profileUser['banner_url']) ?>" alt="Profile Banner" id="profile-banner-display">
      <?php else: ?>
        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #09131e 0%, #152238 45%, #0284c7 100%);"></div>
      <?php endif; ?>
    </div>

    <!-- PROFILE INFO SECTION -->
    <div class="profile-info-section">
      <div class="profile-avatar-action-row">
        <img src="<?= e($profileUser['avatar']) ?>" alt="<?= e($profileUser['name']) ?>" class="profile-avatar-lg" id="profile-avatar-display">
        
        <?php if ($isOwnProfile): ?>
          <button type="button" class="btn-edit-profile-pill" onclick="openEditProfileModal()">Edit profile</button>
        <?php endif; ?>
      </div>

      <!-- User Names & Badges -->
      <div class="profile-user-names">
        <h1 class="profile-display-name">
          <span><?= e($profileUser['name']) ?></span>
          <svg class="verified-badge" viewBox="0 0 24 24" width="18" height="18" fill="#1d9bf0" title="Verified Member">
            <path d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.79-4-4-4-.495 0-.965.084-1.4.238C14.55 2.475 13.18 1.6 11.6 1.6c-1.58 0-2.95.875-3.6 2.148-.435-.154-.905-.238-1.4-.238-2.21 0-4 1.79-4 4 0 .495.084.965.238 1.4C1.575 10.45.7 11.82.7 13.4c0 1.58.875 2.95 2.148 3.6-.154.435-.238.905-.238 1.4 0 2.21 1.79 4 4 4 .495 0 .965-.084 1.4-.238.65 1.273 2.02 2.148 3.6 2.148 1.58 0 2.95-.875 3.6-2.148.435.154.905.238 1.4.238 2.21 0 4-1.79 4-4 0-.495-.084-.965-.238-1.4 1.273-.65 2.148-2.02 2.148-3.6zm-12.87 4.16l-3.29-3.29 1.41-1.41 1.88 1.88 5.18-5.18 1.41 1.41-6.59 6.59z"/>
          </svg>
        </h1>
        <div class="profile-user-handle">@<?= e($profileUser['username']) ?></div>
        
        <?php if (!empty($profileUser['role']) && $profileUser['role'] === 'admin'): ?>
          <span class="profile-role-badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">Administrator</span>
        <?php else: ?>
          <span class="profile-role-badge">Campus Member</span>
        <?php endif; ?>
      </div>

      <!-- Bio -->
      <?php if (!empty($profileUser['bio'])): ?>
        <div class="profile-bio-text"><?= nl2br(e($profileUser['bio'])) ?></div>
      <?php endif; ?>

      <!-- Meta Information (Join Date) -->
      <div class="profile-meta-info">
        <span class="profile-meta-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          <span>Joined <?= date('F Y', strtotime($profileUser['created_at'] ?? '2026-01-01')) ?></span>
        </span>
      </div>
    </div>

    <!-- TABS BAR (Posts, Replies, Comments on Posts) -->
    <div class="profile-tabs-bar">
      <a href="profile.php?id=<?= $profileId ?>&tab=posts" class="profile-tab-item <?= $tab === 'posts' ? 'active' : '' ?>">
        <span>Posts</span>
        <span class="profile-tab-count"><?= $postCount ?></span>
      </a>
      <a href="profile.php?id=<?= $profileId ?>&tab=replies" class="profile-tab-item <?= $tab === 'replies' ? 'active' : '' ?>">
        <span>Replies</span>
        <span class="profile-tab-count"><?= $replyCount ?></span>
      </a>
    </div>

    <!-- TAB CONTENT: POSTS -->
    <?php if ($tab === 'posts'): ?>
      <div class="profile-feed-list">
        <?php if (!empty($userPosts)): ?>
          <?php foreach ($userPosts as $post): ?>
            <?php 
              $pId = (int)$post['id'];
              $images = $postImages[$pId] ?? [];
              $imgCount = count($images);
            ?>
            <article class="post-card" onclick="window.location.href='index.php?post=<?= $pId ?>'" style="cursor: pointer;">
              <div class="post-avatar-col">
                <img src="<?= e($post['author_avatar']) ?>" alt="<?= e($post['author_name']) ?>" class="author-avatar">
              </div>
              <div class="post-main-col">
                <div class="post-header">
                  <div class="post-author-meta">
                    <span class="author-name"><?= e($post['author_name']) ?></span>
                    <span class="user-handle">@<?= e($post['author_username']) ?></span>
                    <span class="post-timestamp">· <?= time_ago($post['created_at']) ?></span>
                  </div>
                </div>
                
                <h3 class="post-title" style="margin: 4px 0 8px 0; font-size: 16px;">
                  <a href="index.php?post=<?= $pId ?>" style="color: inherit; text-decoration: none;">
                    <?= e($post['title']) ?>
                  </a>
                </h3>

                <p class="post-body-text"><?= nl2br(e($post['content'])) ?></p>

                <?php if ($imgCount > 0): ?>
                  <div class="post-media-grid grid-<?= min($imgCount, 4) ?>" onclick="event.stopPropagation()">
                    <?php foreach ($images as $img): ?>
                      <div class="post-media-item" onclick="openLightbox('<?= e($img) ?>')">
                        <img src="<?= e($img) ?>" alt="Post photo" loading="lazy">
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="post-footer-actions" style="margin-top: 10px; display: flex; gap: 24px; color: var(--text-dim); font-size: 13px;">
                  <span style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    <?= (int)$post['reply_count'] ?> replies
                  </span>
                  <span style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                    <?= (int)$post['like_count'] ?> likes
                  </span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="padding: 48px 20px; text-align: center; color: var(--text-muted);">
            <div style="font-size: 17px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">No discussions yet</div>
            <p style="font-size: 13.5px;">This user hasn't posted any discussion threads.</p>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- TAB CONTENT: REPLIES -->
    <?php if ($tab === 'replies'): ?>
      <div class="profile-feed-list">
        <?php if (!empty($userReplies)): ?>
          <?php foreach ($userReplies as $rep): ?>
            <?php 
              $pId = (int)$rep['post_id'];
              $repId = (int)$rep['id'];
            ?>
            <div class="profile-reply-thread-group" onclick="window.location.href='index.php?post=<?= $pId ?>#reply-<?= $repId ?>'" style="cursor: pointer; border-bottom: 1px solid var(--border-color); padding: 14px 16px; transition: background-color 0.15s ease;">
              
              <!-- 1. The Original Post Parent -->
              <div class="profile-parent-post-row" style="display: flex; gap: 12px; position: relative;">
                <div class="profile-thread-avatar-col" style="display: flex; flex-direction: column; align-items: center;">
                  <a href="profile.php?id=<?= (int)$rep['post_author_id'] ?>" onclick="event.stopPropagation()">
                    <img src="<?= e($rep['post_author_avatar']) ?>" alt="<?= e($rep['post_author_name']) ?>" class="author-avatar" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover;">
                  </a>
                  <!-- Vertical connecting thread line -->
                  <div class="profile-thread-line" style="width: 2px; flex: 1; min-height: 24px; background-color: var(--border-color); margin: 6px 0;"></div>
                </div>

                <div class="profile-parent-content-col" style="flex: 1; min-width: 0; padding-bottom: 10px;">
                  <div class="post-header" style="margin-bottom: 2px;">
                    <div class="post-author-meta" style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                      <a href="profile.php?id=<?= (int)$rep['post_author_id'] ?>" onclick="event.stopPropagation()" style="font-weight: 700; color: var(--text-main); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                        <span><?= e($rep['post_author_name']) ?></span>
                        <svg class="verified-badge" viewBox="0 0 24 24" width="16" height="16" fill="#1d9bf0">
                          <path d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.79-4-4-4-.495 0-.965.084-1.4.238C14.55 2.475 13.18 1.6 11.6 1.6c-1.58 0-2.95.875-3.6 2.148-.435-.154-.905-.238-1.4-.238-2.21 0-4 1.79-4 4 0 .495.084.965.238 1.4C1.575 10.45.7 11.82.7 13.4c0 1.58.875 2.95 2.148 3.6-.154.435-.238.905-.238 1.4 0 2.21 1.79 4 4 4 .495 0 .965-.084 1.4-.238.65 1.273 2.02 2.148 3.6 2.148 1.58 0 2.95-.875 3.6-2.148.435.154.905.238 1.4.238 2.21 0 4-1.79 4-4 0-.495-.084-.965-.238-1.4 1.273-.65 2.148-2.02 2.148-3.6zm-12.87 4.16l-3.29-3.29 1.41-1.41 1.88 1.88 5.18-5.18 1.41 1.41-6.59 6.59z"/>
                        </svg>
                        <span class="user-handle" style="color: var(--text-muted); font-size: 14px;">@<?= e($rep['post_author_username']) ?></span>
                      </a>
                      <span class="post-dot" style="color: var(--text-muted);">·</span>
                      <span class="post-timestamp" style="color: var(--text-muted); font-size: 14px;"><?= time_ago($rep['post_created_at']) ?></span>
                    </div>
                  </div>

                  <!-- Post Title -->
                  <div style="font-weight: 700; font-size: 14.5px; color: var(--text-main); margin: 2px 0 4px 0;">
                    <?= e($rep['post_title']) ?>
                  </div>

                  <!-- Post snippet -->
                  <div style="font-size: 13.5px; color: var(--text-muted); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                    <?= e($rep['post_content']) ?>
                  </div>
                </div>
              </div>

              <!-- 2. The User's Reply directly below -->
              <div class="profile-reply-row" style="display: flex; gap: 12px; margin-top: 2px;">
                <div class="profile-reply-avatar-col" style="flex-shrink: 0;">
                  <a href="profile.php?id=<?= $profileId ?>" onclick="event.stopPropagation()">
                    <img src="<?= e($profileUser['avatar']) ?>" alt="<?= e($profileUser['name']) ?>" class="author-avatar" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover;">
                  </a>
                </div>

                <div class="profile-reply-content-col" style="flex: 1; min-width: 0;">
                  <div class="post-author-meta" style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-bottom: 2px;">
                    <a href="profile.php?id=<?= $profileId ?>" onclick="event.stopPropagation()" style="font-weight: 700; color: var(--text-main); text-decoration: none;">
                      <?= e($profileUser['name']) ?>
                    </a>
                    <span class="user-handle" style="color: var(--text-muted); font-size: 14px;">@<?= e($profileUser['username']) ?></span>
                    <span class="post-dot" style="color: var(--text-muted);">·</span>
                    <span class="post-timestamp" style="color: var(--text-muted); font-size: 14px;"><?= time_ago($rep['created_at']) ?></span>
                    <?php if (!empty($rep['updated_at'])): ?>
                      <span style="font-size: 11.5px; color: var(--text-dim);">(edited)</span>
                    <?php endif; ?>
                  </div>

                  <!-- Subtitle tag -->
                  <div style="font-size: 12px; color: var(--text-dim); margin-bottom: 4px;">
                    Replying to <span style="color: var(--primary-blue); font-weight: 600;">@<?= e($rep['post_author_username']) ?></span>
                  </div>

                  <!-- Reply text content -->
                  <div class="reply-body-text" style="font-size: 14.5px; color: var(--text-main); line-height: 1.45; word-break: break-word;">
                    <?= nl2br(e($rep['content'])) ?>
                  </div>

                  <!-- Reply Image Attachment if present -->
                  <?php if (!empty($rep['image_url'])): ?>
                    <div style="max-width: 320px; border-radius: 12px; overflow: hidden; margin-top: 8px; border: 1px solid var(--border-color);" onclick="event.stopPropagation(); openLightbox('<?= e($rep['image_url']) ?>')">
                      <img src="<?= e($rep['image_url']) ?>" alt="Reply photo" style="width: 100%; height: auto; display: block;">
                    </div>
                  <?php endif; ?>
                </div>
              </div>

            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="padding: 48px 20px; text-align: center; color: var(--text-muted);">
            <div style="font-size: 17px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">No replies yet</div>
            <p style="font-size: 13.5px;">This user hasn't replied to any discussions yet.</p>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </main>

  <!-- ======================================================== -->
  <!-- RIGHT SIDEBAR                                            -->
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
                  <div class="recent-meta">
                    <?= (int)$item['reply_count'] ?> replies · <?= time_ago($item['created_at']) ?>
                  </div>
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
<!-- EDIT PROFILE MODAL                                       -->
<!-- ======================================================== -->
<?php if ($isOwnProfile): ?>
<div class="modal-overlay" id="edit-profile-modal">
  <div class="modal-dialog" style="max-width: 560px;">
    <div class="modal-header">
      <h3 class="modal-title">Edit profile</h3>
      <button type="button" class="btn-close-modal" onclick="closeEditProfileModal()">✕</button>
    </div>
    
    <form action="profile.php?id=<?= $profileId ?>" method="POST" enctype="multipart/form-data" id="edit-profile-form">
      <input type="hidden" name="action" value="update_profile">
      
      <div class="modal-body" style="padding: 16px 20px;">
        
        <!-- Banner Upload Field & Preview -->
        <div class="form-group">
          <label class="form-label" style="display: flex; justify-content: space-between;">
            <span>Header Banner</span>
            <span style="font-size: 11.5px; color: var(--text-muted); font-weight: normal;">Recommended 3:1 aspect ratio</span>
          </label>
          <div style="position: relative; width: 100%; height: 130px; border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); background: #111;">
            <img src="<?= !empty($profileUser['banner_url']) ? e($profileUser['banner_url']) : '' ?>" alt="Banner Preview" id="preview-modal-banner" style="width: 100%; height: 100%; object-fit: cover; display: <?= !empty($profileUser['banner_url']) ? 'block' : 'none' ?>;">
            <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center;">
              <button type="button" onclick="document.getElementById('input-banner-file').click()" style="padding: 8px 16px; border-radius: 9999px; background: rgba(0,0,0,0.7); color: #fff; border: 1px solid rgba(255,255,255,0.4); font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                Change Banner
              </button>
            </div>
            <input type="file" name="banner" id="input-banner-file" accept="image/*" style="display: none;" onchange="handleModalBannerPreview(this)">
          </div>
        </div>

        <!-- Avatar Upload Field & Preview -->
        <div class="form-group" style="margin-top: 14px;">
          <label class="form-label">Profile Picture</label>
          <div style="display: flex; align-items: center; gap: 16px;">
            <div style="position: relative; width: 72px; height: 72px; border-radius: 50%; overflow: hidden; border: 2px solid var(--border-color); background: #111; flex-shrink: 0;">
              <img src="<?= e($profileUser['avatar']) ?>" alt="Avatar Preview" id="preview-modal-avatar" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div>
              <button type="button" onclick="document.getElementById('input-avatar-file').click()" style="padding: 7px 16px; border-radius: 9999px; background: rgba(255,255,255,0.08); color: var(--text-main); border: 1px solid var(--border-color); font-size: 13px; font-weight: 700; cursor: pointer;">
                Upload Photo
              </button>
              <input type="file" name="avatar" id="input-avatar-file" accept="image/*" style="display: none;" onchange="handleModalAvatarPreview(this)">
              <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 4px;">JPG, PNG, WEBP up to 5MB</div>
            </div>
          </div>
        </div>

        <!-- Display Name -->
        <div class="form-group" style="margin-top: 14px;">
          <label class="form-label" for="edit-profile-name">Display Name</label>
          <input type="text" name="name" id="edit-profile-name" class="form-input" value="<?= e($profileUser['name']) ?>" required maxlength="60">
        </div>

        <!-- Bio -->
        <div class="form-group" style="margin-top: 14px;">
          <label class="form-label" for="edit-profile-bio">Bio</label>
          <textarea name="bio" id="edit-profile-bio" class="form-textarea" style="min-height: 80px;" maxlength="255" placeholder="Tell the campus about yourself..."><?= e($profileUser['bio']) ?></textarea>
          <div style="font-size: 11.5px; color: var(--text-dim); text-align: right; margin-top: 4px;" id="bio-char-count">255 chars remaining</div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn-modal-cancel" onclick="closeEditProfileModal()">Cancel</button>
        <button type="submit" class="btn-modal-submit">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- FULL IMAGE LIGHTBOX PREVIEW -->
<div class="lightbox-overlay" id="lightbox-overlay" onclick="closeLightbox(event)">
  <button class="lightbox-close" onclick="closeLightbox(event)">✕</button>
  <img src="" alt="Enlarged view" id="lightbox-img" class="lightbox-img" onclick="event.stopPropagation()">
</div>

<script>
// Theme Management
function initTheme() {
  const saved = localStorage.getItem('campus_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
  updateThemeIcon(saved);
}
function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'dark';
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('campus_theme', next);
  updateThemeIcon(next);
}
function updateThemeIcon(theme) {
  const sun = document.querySelector('.theme-icon-sun');
  const moon = document.querySelector('.theme-icon-moon');
  const label = document.getElementById('theme-label-text');
  if (theme === 'light') {
    if (sun) sun.style.display = 'block';
    if (moon) moon.style.display = 'none';
    if (label) label.textContent = 'Theme: Light';
  } else {
    if (sun) sun.style.display = 'none';
    if (moon) moon.style.display = 'block';
    if (label) label.textContent = 'Theme: Dark';
  }
}
initTheme();

// Profile Dropdown
function toggleProfileDropdown(e) {
  e.stopPropagation();
  const menu = document.getElementById('profile-dropdown-menu');
  if (menu) menu.classList.toggle('active');
}
document.addEventListener('click', () => {
  const menu = document.getElementById('profile-dropdown-menu');
  if (menu) menu.classList.remove('active');
});

// Edit Profile Modal
function openEditProfileModal() {
  const modal = document.getElementById('edit-profile-modal');
  if (modal) modal.classList.add('active');
}
function closeEditProfileModal() {
  const modal = document.getElementById('edit-profile-modal');
  if (modal) modal.classList.remove('active');
}

// Live Image Previews in Edit Modal
function handleModalBannerPreview(input) {
  if (input.files && input.files[0]) {
    const reader = new window.FileReader();
    reader.onload = function(e) {
      const img = document.getElementById('preview-modal-banner');
      if (img) {
        img.src = e.target.result;
        img.style.display = 'block';
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function handleModalAvatarPreview(input) {
  if (input.files && input.files[0]) {
    const reader = new window.FileReader();
    reader.onload = function(e) {
      const img = document.getElementById('preview-modal-avatar');
      if (img) img.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// Bio Character Counter
const bioTextarea = document.getElementById('edit-profile-bio');
const bioCharCount = document.getElementById('bio-char-count');
if (bioTextarea && bioCharCount) {
  bioTextarea.addEventListener('input', function() {
    const remaining = 255 - this.value.length;
    bioCharCount.textContent = remaining + ' chars remaining';
  });
}

// Lightbox
function openLightbox(url) {
  const overlay = document.getElementById('lightbox-overlay');
  const img = document.getElementById('lightbox-img');
  if (overlay && img) {
    img.src = url;
    overlay.classList.add('active');
  }
}
function closeLightbox(e) {
  if (e) e.stopPropagation();
  const overlay = document.getElementById('lightbox-overlay');
  if (overlay) overlay.classList.remove('active');
}
</script>
</body>
</html>
