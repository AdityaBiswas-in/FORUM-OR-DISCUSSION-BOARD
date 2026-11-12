<?php
require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    if (is_admin()) {
        header("Location: index.php?view=admin");
    } else {
        header("Location: index.php");
    }
    exit;
}

// Determine active registration role
$requestedRole = strtolower(trim($_GET['role'] ?? $_POST['reg_role'] ?? 'student'));
if (!in_array($requestedRole, ['student', 'faculty', 'admin'])) {
    $requestedRole = 'student';
}

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $role       = strtolower(trim($_POST['reg_role'] ?? 'student'));
    if (!in_array($role, ['student', 'faculty', 'admin'])) {
        $role = 'student';
    }

    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $account_id = trim($_POST['account_id'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $auth_key   = trim($_POST['auth_key'] ?? '');

    // 1. Mandatory Field Validation
    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all required fields.';
    } elseif ($role === 'faculty' && $account_id === '') {
        $error = 'Please enter your 10-digit Faculty ID.';
    } elseif ($role === 'faculty' && !preg_match('/^[0-9]{10}$/', $account_id)) {
        // 2. Strict 10-Digit Numeric ID Validation (Faculty)
        $error = 'Unique ID must contain exactly 10 numeric digits (digits only, no letters or special characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // 3. Email Validation
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm) {
        // 4. Password Confirmation
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($role === 'faculty' && check_id_exists($account_id, $conn)) {
        // 5. Cross-Table Unique ID Check (reject duplicates across students, faculty, and admins)
        $error = 'This 10-digit ID is already registered in the system. Duplicate IDs are not permitted.';
    } elseif (check_email_exists($email, $conn)) {
        // 6. Cross-Table Unique Email Check
        $error = 'This email address is already registered in the system. Please use a different email.';
    } else {
        // Auto-generate a unique internal 10-digit record ID for student and admin accounts
        if ($role === 'student' || $role === 'admin') {
            $minId = ($role === 'admin') ? 9000000000 : 1000000000;
            $maxId = ($role === 'admin') ? 9999999999 : 1999999999;
            do {
                $account_id = (string)random_int($minId, $maxId);
            } while (
                check_id_exists($account_id, $conn) ||
                $conn->execute_query("SELECT 1 FROM users WHERE uid = ? LIMIT 1", [$account_id])->fetch_row()
            );
        }
        // 7. Admin Security Authorization Check
        if ($role === 'admin') {
            $isAuthorizedAdmin = false;

            // Check Master Institutional Authorization Key
            if ($auth_key === 'QUAD-ADMIN-2026') {
                $isAuthorizedAdmin = true;
            } else {
                // Check authorized_admins roster table
                $authCheck = $conn->execute_query(
                    "SELECT admin_id, auth_key, is_registered FROM authorized_admins WHERE admin_id = ? LIMIT 1",
                    [$account_id]
                )->fetch_assoc();

                if ($authCheck && ($authCheck['auth_key'] === $auth_key || empty($auth_key))) {
                    $isAuthorizedAdmin = true;
                }
            }

            if (!$isAuthorizedAdmin) {
                $error = 'Authorization Failed: Invalid Admin Authorization Key.';
            }
        }

        if (empty($error)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $defaultAvatar = 'assets/images/avatar_default.svg';

            // Insert into the designated table
            if ($role === 'student') {
                $stmt = $conn->prepare("INSERT INTO students (id, name, email, password, avatar, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssss", $account_id, $name, $email, $hashedPassword, $defaultAvatar);
                $stmt->execute();
            } elseif ($role === 'faculty' && table_exists('faculty', $conn)) {
                $stmt = $conn->prepare("INSERT INTO faculty (id, name, email, password, avatar, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssss", $account_id, $name, $email, $hashedPassword, $defaultAvatar);
                $stmt->execute();
            } elseif ($role === 'admin') {
                $stmt = $conn->prepare("INSERT INTO admins (id, name, email, password, avatar, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssss", $account_id, $name, $email, $hashedPassword, $defaultAvatar);
                $stmt->execute();

                // Mark as registered in authorized_admins if present
                $conn->execute_query("UPDATE authorized_admins SET is_registered = 1 WHERE admin_id = ?", [$account_id]);
            }

            // Sync to unified users table so discussions, replies, likes, groups reference works seamlessly
            $existingUser = $conn->execute_query("SELECT id FROM users WHERE uid = ? OR email = ? LIMIT 1", [$account_id, $email])->fetch_assoc();
            if ($existingUser) {
                $conn->execute_query(
                    "UPDATE users SET uid = ?, name = ?, username = ?, email = ?, role = ?, account_type = ? WHERE id = ?",
                    [$account_id, $name, $name, $email, $role, $role, $existingUser['id']]
                );
            } else {
                $conn->execute_query(
                    "INSERT INTO users (uid, username, name, email, role, account_type, avatar, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$account_id, $name, $name, $email, $role, $role, $defaultAvatar]
                );
            }

            // Redirect to Login page with account-type pre-selected
            header("Location: login.php?role=" . urlencode($role) . "&registered=1&email=" . urlencode($email));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create <?= ucfirst($requestedRole) ?> Account | Quaddit</title>
  <link rel="icon" type="image/png" sizes="32x32" href="assets/images/logo-transparent.png?v=7">
  <link rel="shortcut icon" href="assets/images/favicon.ico?v=7">
  <link rel="stylesheet" href="assets/css/auth.css?v=<?= time() ?>">
  <script>
    (function() {
      const savedTheme = localStorage.getItem('campus_theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();
  </script>
</head>
<body class="auth-page">
  <!-- Theme Toggle -->
  <button type="button" class="auth-theme-toggle" onclick="toggleAuthTheme()" id="auth-theme-btn" title="Toggle Light/Dark Theme">
    <span id="auth-theme-icon">🌙</span>
    <span id="auth-theme-text">Dark</span>
  </button>

  <div class="auth-wrapper">
    <!-- Brand Header -->
    <div class="auth-header-branding">
      <div class="auth-logo-wrap">
        <img src="assets/images/logo-dark-theme.png?v=9" alt="Quaddit Logo" class="logo-for-dark">
        <img src="assets/images/logo-light-theme.png?v=9" alt="Quaddit Logo" class="logo-for-light">
      </div>
      <h1 class="auth-brand-title">Quad<span>dit</span></h1>
      <p class="auth-tagline">Share ideas. Ask questions. Grow together.</p>
    </div>

    <!-- Clean Auth Card -->
    <div class="auth-card">
      
      <!-- Registration Switcher (Faculty option hidden from public UI) -->
      <nav class="auth-role-tabs" aria-label="Account Type Selection">
        <a href="register.php?role=student" class="auth-role-tab role-tab-student <?= $requestedRole === 'student' ? 'active' : '' ?>">
          <span class="role-dot" aria-hidden="true"></span>
          <span>Student</span>
        </a>
        <?php /* Faculty registration option hidden from public UI while preserving internal faculty system
        <a href="register.php?role=faculty" class="auth-role-tab role-tab-faculty <?= $requestedRole === 'faculty' ? 'active' : '' ?>">
          <span class="role-dot" aria-hidden="true"></span>
          <span>Faculty</span>
        </a>
        */ ?>
        <a href="register.php?role=admin" class="auth-role-tab role-tab-admin <?= $requestedRole === 'admin' ? 'active' : '' ?>">
          <span class="role-dot" aria-hidden="true"></span>
          <span>Admin</span>
        </a>
      </nav>

      <!-- Card Title & Description -->
      <div class="auth-card-header">
        <?php if ($requestedRole === 'admin'): ?>
          <h2 class="auth-card-title">Admin Registration</h2>
          <p class="auth-card-subtitle">Register using your name, email address, password, and Admin Authorization Key.</p>
        <?php elseif ($requestedRole === 'faculty'): ?>
          <h2 class="auth-card-title">Faculty Registration</h2>
          <p class="auth-card-subtitle">Create a faculty account using your official 10-digit Faculty ID.</p>
        <?php else: ?>
          <h2 class="auth-card-title">Student Registration</h2>
          <p class="auth-card-subtitle">Create your account using your full name, email address, and password.</p>
        <?php endif; ?>
      </div>

      <?php if (!empty($error)): ?>
        <div class="auth-alert auth-alert-error" role="alert">
          <svg class="auth-alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form action="register.php?role=<?= e($requestedRole) ?>" method="POST" class="auth-form" id="register-form" autocomplete="off">
        <input type="hidden" name="reg_role" value="<?= e($requestedRole) ?>">

        <!-- 1. Full Name -->
        <div class="form-group">
          <label for="reg-name" class="form-label">Full Name <span class="required" aria-hidden="true">*</span></label>
          <input type="text" name="name" id="reg-name" class="form-control" placeholder="<?= $requestedRole === 'admin' ? 'e.g. Admin Officer' : ($requestedRole === 'faculty' ? 'e.g. Dr. Sarah Connor' : 'e.g. Aditya Biswas') ?>" value="<?= isset($_POST['name']) ? e($_POST['name']) : '' ?>" autocomplete="name" required>
        </div>

        <!-- 2. Email Address -->
        <div class="form-group">
          <label for="reg-email" class="form-label">Email Address <span class="required" aria-hidden="true">*</span></label>
          <input type="email" name="email" id="reg-email" class="form-control" placeholder="<?= $requestedRole === 'student' ? 'e.g. student@college.edu' : 'e.g. faculty@college.edu' ?>" value="<?= isset($_POST['email']) ? e($_POST['email']) : '' ?>" autocomplete="email" required>
        </div>

        <?php if ($requestedRole === 'faculty'): ?>
        <!-- 3. Unique 10-Digit ID (Internal Faculty only) -->
        <div class="form-group">
          <div class="form-label-row">
            <label for="account_id_input" class="form-label">10-Digit Faculty ID <span class="required" aria-hidden="true">*</span></label>
            <span class="form-label-hint">Digits only</span>
          </div>
          <input type="text" name="account_id" id="account_id_input" class="form-control" placeholder="e.g. 2000000001" value="<?= isset($_POST['account_id']) ? e($_POST['account_id']) : '' ?>" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" required oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
          <span class="form-help-text">Enter your official 10-digit institutional ID number.</span>
        </div>
        <?php endif; ?>

        <!-- 4. Password -->
        <div class="form-group">
          <label for="reg-password" class="form-label">Password <span class="required" aria-hidden="true">*</span></label>
          <div class="input-password-wrap">
            <input type="password" name="password" id="reg-password" class="form-control" placeholder="Minimum 6 characters" autocomplete="new-password" required>
            <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('reg-password', this)" aria-label="Toggle password visibility" title="Show password">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>

        <!-- 5. Confirm Password -->
        <div class="form-group">
          <label for="reg-confirm-password" class="form-label">Confirm Password <span class="required" aria-hidden="true">*</span></label>
          <div class="input-password-wrap">
            <input type="password" name="confirm_password" id="reg-confirm-password" class="form-control" placeholder="Re-enter your password" autocomplete="new-password" required>
            <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('reg-confirm-password', this)" aria-label="Toggle password visibility" title="Show password">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>

        <?php if ($requestedRole === 'admin'): ?>
          <!-- Admin Security Authorization Key -->
          <div class="form-group">
            <label for="reg-auth-key" class="form-label">Admin Authorization Key <span class="required" aria-hidden="true">*</span></label>
            <input type="text" name="auth_key" id="reg-auth-key" class="form-control" placeholder="Enter authorization key" value="<?= isset($_POST['auth_key']) ? e($_POST['auth_key']) : '' ?>" required>
            <span class="form-help-text">Authorized key: <code>QUAD-ADMIN-2026</code> (or verified roster ID).</span>
          </div>
        <?php endif; ?>

        <!-- Submit Button -->
        <button type="submit" class="btn-auth-submit <?= $requestedRole === 'admin' ? 'btn-auth-admin' : ($requestedRole === 'faculty' ? 'btn-auth-faculty' : '') ?>" id="btn-reg-submit">
          <?= $requestedRole === 'admin' ? 'Create Admin Account' : ($requestedRole === 'faculty' ? 'Create Faculty Account' : 'Create Student Account') ?>
        </button>

        <!-- Footer Link -->
        <p class="auth-footer-prompt">
          Already have an account? <a href="login.php?role=<?= e($requestedRole) ?>">Sign in</a>
        </p>
      </form>
    </div>
  </div>

  <script>
    function updateAuthThemeUI(theme) {
      document.documentElement.setAttribute('data-theme', theme);
      const icon = document.getElementById('auth-theme-icon');
      const text = document.getElementById('auth-theme-text');
      if (icon && text) {
        icon.textContent = theme === 'light' ? '☀️' : '🌙';
        text.textContent = theme === 'light' ? 'Light' : 'Dark';
      }
    }

    function toggleAuthTheme() {
      const current = document.documentElement.getAttribute('data-theme') || 'dark';
      const next = current === 'dark' ? 'light' : 'dark';
      localStorage.setItem('campus_theme', next);
      updateAuthThemeUI(next);
    }

    document.addEventListener('DOMContentLoaded', () => {
      const savedTheme = localStorage.getItem('campus_theme') || 'dark';
      updateAuthThemeUI(savedTheme);
    });

    function togglePasswordVisibility(inputId, btn) {
      const input = document.getElementById(inputId);
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
        btn.setAttribute('aria-label', 'Hide password');
        btn.setAttribute('title', 'Hide password');
      } else {
        input.type = 'password';
        btn.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('title', 'Show password');
      }
    }

    document.getElementById('register-form').addEventListener('submit', function() {
      const btn = document.getElementById('btn-reg-submit');
      if (btn) {
        btn.textContent = 'Creating account...';
        btn.style.opacity = '0.85';
      }
    });

    document.querySelectorAll('.auth-role-tab, .auth-footer-prompt a').forEach(function(link) {
      link.addEventListener('click', function(e) {
        const href = link.getAttribute('href');
        if (!href || e.metaKey || e.ctrlKey || e.shiftKey) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        e.preventDefault();
        const card = document.querySelector('.auth-card');
        if (card) card.classList.add('is-transitioning');
        setTimeout(function() { window.location.href = href; }, 140);
      });
    });
  </script>
</body>
</html>
