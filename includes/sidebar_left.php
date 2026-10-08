<?php
// includes/sidebar_left.php - Left Navigation Sidebar
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($currentUser)) {
    $currentUser = current_user();
}
?>
<aside class="sidebar-left">
  <div>
    <!-- Brand Logo matching CampusForum in Image 1 -->
    <a href="index.php" class="brand-header">
      <div class="brand-logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
      </div>
      <div class="brand-text">Campus<span>Forum</span></div>
    </a>

    <!-- Nav Menu items matching X / Twitter screenshot -->
    <ul class="nav-menu">
      <li class="nav-item <?= ($current_page == 'index.php' || $current_page == 'thread.php') ? 'active' : '' ?>">
        <a href="index.php" id="nav-home">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
          </svg>
          <span>Home</span>
        </a>
      </li>
      <li class="nav-item <?= ($current_page == 'topics.php') ? 'active' : '' ?>">
        <a href="topics.php" id="nav-explore">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <span>Explore</span>
        </a>
      </li>

      <li class="nav-item <?= ($current_page == 'saved.php') ? 'active' : '' ?>">
        <a href="saved.php" id="nav-saved">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
            <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"></path>
          </svg>
          <span>Bookmarks</span>
        </a>

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
          <span id="theme-label-text">Theme</span>
        </a>
      </li>
    </ul>

    <!-- X style prominent pill Post button -->
    <button class="btn-create-thread-sidebar" id="btn-open-create-modal" onclick="openCreateThreadModal()">
      <span>Post</span>
    </button>
  </div>

  <!-- Bottom User Card matching screenshot -->
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

    <!-- Dropdown Menu -->
    <div class="profile-dropdown-menu" id="profile-dropdown-menu">
      <div style="padding: 8px 12px; font-size: 13px; font-weight: 700; color: var(--text-main);">
        <?= e($currentUser['name']) ?>
        <span style="font-size: 12px; font-weight: 500; color: var(--text-dim); display: block;">@<?= e($currentUser['username']) ?></span>
      </div>
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
