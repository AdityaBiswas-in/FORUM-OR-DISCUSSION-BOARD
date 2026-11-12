<?php
$currentUser = $currentUser ?? null;
$currentFile = basename($_SERVER['PHP_SELF']);
$isHomePage = ($currentFile === 'index.php');
$isProfilePage = ($currentFile === 'profile.php');
$mobileSearchVal = trim($_GET['q'] ?? '');
?>

<!-- ======================================================== -->
<!-- X-STYLE MOBILE TOP BAR (Sticky)                          -->
<!-- ======================================================== -->
<?php if (!$isProfilePage): ?>
<header class="mobile-top-bar" id="mobile-top-bar">
  <div class="mobile-top-left">
    <button type="button" class="mobile-avatar-trigger" onclick="openMobileDrawer()" aria-label="Open navigation menu">
      <img src="<?= e($currentUser['avatar'] ?? 'assets/images/avatar_default.svg') ?>" alt="<?= e($currentUser['name'] ?? 'Guest') ?>" class="mobile-avatar-head">
    </button>
  </div>
  
  <a href="index.php" class="mobile-top-center-brand" aria-label="Quaddit Home">
    <div class="mobile-logo-wrap">
      <img src="assets/images/logo-dark-theme.png?v=9" alt="Quaddit Logo" class="mobile-logo-img logo-for-dark">
      <img src="assets/images/logo-light-theme.png?v=9" alt="Quaddit Logo" class="mobile-logo-img logo-for-light">
    </div>
    <span class="mobile-logo-text">Quad<span>dit</span></span>
  </a>

  <div class="mobile-top-right">
    <button type="button" class="mobile-top-theme-btn" onclick="toggleTheme()" aria-label="Toggle Theme" title="Toggle theme">
      <svg class="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
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
      <svg class="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
      </svg>
    </button>
  </div>
</header>
<?php endif; ?>

<!-- ======================================================== -->
<!-- X-STYLE MOBILE FLOATING ACTION BUTTON (POST FAB)         -->
<!-- ======================================================== -->
<button type="button" class="mobile-post-fab" onclick="<?= $currentUser ? 'handleMobilePostClick()' : "window.location.href='register.php'" ?>" aria-label="Create Post" title="Create Discussion">
  <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
    <path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/>
  </svg>
</button>

<!-- ======================================================== -->
<!-- MOBILE BOTTOM NAVIGATION ZONE (Search Drawer + Bottom Bar)-->
<!-- ======================================================== -->
<div class="mobile-bottom-nav-zone" id="mobile-bottom-nav-zone">
  
  <!-- Search Drawer in Bottom Navigation Zone -->
  <div class="mobile-bottom-search-drawer" id="mobile-bottom-search-drawer">
    <div class="mobile-search-drawer-inner">
      <div class="mobile-search-drawer-header">
        <span class="mobile-search-drawer-title">Search Discussions</span>
        <button type="button" class="btn-close-mobile-search" onclick="closeMobileSearch()" aria-label="Close search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      <form action="index.php" method="GET" class="mobile-search-form" onsubmit="return submitMobileSearch(event)">
        <div class="mobile-search-input-box">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" name="q" id="mobile-search-input" placeholder="Search discussions, topics, keywords..." value="<?= e($mobileSearchVal) ?>" autocomplete="off">
          <?php if (!empty($mobileSearchVal)): ?>
            <a href="index.php" class="mobile-search-clear-btn" title="Clear search">✕</a>
          <?php endif; ?>
        </div>
        <button type="submit" class="mobile-search-action-btn">Search</button>
      </form>
    </div>
  </div>

  <!-- Bottom Navigation Bar (Home, Groups, Search, Profile) -->
  <nav class="mobile-bottom-bar" aria-label="Mobile Navigation">
    
    <!-- Home Tab -->
    <a href="index.php" class="mobile-nav-btn <?= $isHomePage && empty($mobileSearchVal) && empty($_GET['view']) ? 'active' : '' ?>" aria-label="Home">
      <div class="mobile-nav-icon-wrap">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
        </svg>
      </div>
      <span class="mobile-nav-label">Home</span>
    </a>

    <!-- Groups Tab -->
    <a href="index.php?view=groups" class="mobile-nav-btn <?= (isset($_GET['view']) && $_GET['view'] === 'groups') ? 'active' : '' ?>" aria-label="Groups">
      <div class="mobile-nav-icon-wrap">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      </div>
      <span class="mobile-nav-label">Groups</span>
    </a>

    <!-- Search Tab in Bottom Zone -->
    <button type="button" class="mobile-nav-btn <?= !empty($mobileSearchVal) ? 'active' : '' ?>" id="mobile-bottom-search-tab" onclick="toggleMobileSearch()" aria-label="Search">
      <div class="mobile-nav-icon-wrap">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </div>
      <span class="mobile-nav-label">Search</span>
    </button>

    <!-- Profile Tab (Opens Profile Drawer Menu) -->
    <button type="button" class="mobile-nav-btn <?= $isProfilePage ? 'active' : '' ?>" onclick="openMobileDrawer()" aria-label="Profile Menu">
      <div class="mobile-nav-icon-wrap">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
      </div>
      <span class="mobile-nav-label">Profile</span>
    </button>

  </nav>
</div>

<!-- ======================================================== -->
<!-- X-STYLE MOBILE PROFILE DRAWER / MENU                     -->
<!-- ======================================================== -->
<div class="mobile-drawer-backdrop" id="mobile-drawer-backdrop" onclick="closeMobileDrawer()"></div>

<aside class="mobile-drawer" id="mobile-drawer" aria-label="User Drawer Menu">
  
  <div class="mobile-drawer-top">
    <!-- User Info Header -->
    <div class="mobile-drawer-user-header">
      <a href="<?= $currentUser ? 'profile.php?id=' . (int)$currentUser['id'] : 'register.php' ?>" class="mobile-drawer-avatar-link" onclick="closeMobileDrawer()">
        <img src="<?= e($currentUser['avatar'] ?? 'assets/images/avatar_default.svg') ?>" alt="<?= e($currentUser['name'] ?? 'Guest') ?>" class="mobile-drawer-avatar">
      </a>
      <button type="button" class="mobile-drawer-close-btn" onclick="closeMobileDrawer()" aria-label="Close menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>

    <div class="mobile-drawer-user-meta">
      <div class="mobile-drawer-name-row" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
        <span class="mobile-drawer-name"><?= e($currentUser['name'] ?? 'Guest Visitor') ?></span>
        <?php if (function_exists('is_admin') && is_admin()): ?>
          <span class="user-role-badge badge-admin" style="font-size: 10px; padding: 2px 7px;">Admin</span>
        <?php elseif (function_exists('is_faculty') && is_faculty()): ?>
          <span class="user-role-badge badge-faculty" style="font-size: 10px; padding: 2px 7px;">Faculty</span>
        <?php elseif ($currentUser): ?>
          <span class="user-role-badge badge-student" style="font-size: 10px; padding: 2px 7px;">Student</span>
        <?php endif; ?>
      </div>
      <span class="mobile-drawer-handle"><?= $currentUser ? (!empty($currentUser['uid']) ? 'ID: ' . e($currentUser['uid']) : '@' . e($currentUser['username'])) : 'Public Browsing Mode' ?></span>
    </div>

    <?php if (!empty($currentUser['bio'])): ?>
      <p class="mobile-drawer-bio"><?= e($currentUser['bio']) ?></p>
    <?php endif; ?>

    <div class="mobile-drawer-divider"></div>

    <!-- Navigation Options in Drawer -->
    <nav class="mobile-drawer-nav">
      
      <?php if (function_exists('is_admin') && is_admin()): ?>
      <!-- Admin Console -->
      <a href="index.php?view=admin" class="mobile-drawer-item <?= (isset($_GET['view']) && $_GET['view'] === 'admin') ? 'active' : '' ?>" onclick="closeMobileDrawer()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #38bdf8;">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        <span style="font-weight: 700; color: #38bdf8;">Admin Console</span>
        <span class="mobile-drawer-pill-badge" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">Admin</span>
      </a>
      <?php endif; ?>

      <!-- Option 1: Profile -->
      <a href="<?= $currentUser ? 'profile.php?id=' . (int)$currentUser['id'] : 'register.php' ?>" class="mobile-drawer-item <?= $isProfilePage ? 'active' : '' ?>" onclick="closeMobileDrawer()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
        <span>Profile</span>
      </a>

      <!-- Academic Groups -->
      <a href="index.php?view=groups" class="mobile-drawer-item <?= (isset($_GET['view']) && $_GET['view'] === 'groups') ? 'active' : '' ?>" onclick="closeMobileDrawer()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        <span>Groups</span>
      </a>

      <!-- Option 2: Post Option -->
      <button type="button" class="mobile-drawer-item mobile-drawer-post-btn" onclick="closeMobileDrawer(); handleMobilePostClick();">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
        </svg>
        <span>Post</span>
        <span class="mobile-drawer-pill-badge">New</span>
      </button>

 
      <!-- Option 4: Light/Dark Mode Option -->
      <button type="button" class="mobile-drawer-item mobile-theme-drawer-row" onclick="toggleTheme()" aria-label="Toggle dark/light theme">
        <div class="mobile-theme-drawer-label">
          <svg class="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
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
          <svg class="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <span class="theme-label-text">Theme: Dark</span>
        </div>
        <div class="mobile-theme-switch-pill" id="mobile-theme-switch-pill">
          <div class="mobile-theme-switch-thumb"></div>
        </div>
      </button>

    </nav>
  </div>

  <!-- Drawer Footer (Sign Out) -->
  <div class="mobile-drawer-footer">
    <div class="mobile-drawer-divider"></div>
    <?php if ($currentUser): ?>
    <a href="logout.php" class="mobile-drawer-item logout-link" style="color: var(--danger-red);">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
      <span>Sign Out</span>
    </a>
    <?php else: ?>
    <a href="register.php" class="mobile-drawer-item" style="color: var(--primary-blue); font-weight: 700;"><span>Create Account</span></a>
    <a href="login.php" class="mobile-drawer-item" style="color: var(--text-main);"><span>Sign In</span></a>
    <?php endif; ?>
  </div>
</aside>
