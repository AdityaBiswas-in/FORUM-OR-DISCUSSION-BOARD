<?php
require_once __DIR__ . '/db.php';

// Determine active login portal
$requestedRole = strtolower(trim($_GET['role'] ?? $_POST['login_role'] ?? 'student'));
if (!in_array($requestedRole, ['student', 'faculty', 'admin'])) {
    $requestedRole = 'student';
}

if (is_logged_in()) {
    if (is_admin()) {
        header("Location: index.php?view=admin");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = '';
$registeredType = $_GET['registered'] ?? '';
$registeredId   = trim($_GET['id'] ?? '');

$success = '';
if ($registeredType === '1') {
    $success = ucfirst($requestedRole) . ' account created successfully! Please sign in below.';
} elseif (isset($_GET['logged_out'])) {
    $success = 'You have signed out.';
}

$login_input = trim($_GET['email'] ?? '') ?: $registeredId ?: trim($_GET['username'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $login_role = strtolower(trim($_POST['login_role'] ?? 'student'));
    if (!in_array($login_role, ['student', 'faculty', 'admin'])) {
        $login_role = 'student';
    }

    if ($action === 'bypass_login') {
        $input = trim($_POST['bypass_email'] ?? '');

        if ($login_role === 'student') {
            $account = $conn->execute_query("SELECT * FROM students WHERE email = ? LIMIT 1", [$input])->fetch_assoc();
            if ($account) {
                $u = $conn->execute_query("SELECT id FROM users WHERE uid = ? OR email = ? LIMIT 1", [$account['id'], $account['email']])->fetch_assoc();
                $userId = $u ? (int)$u['id'] : 0;
                if (!$userId) {
                    $conn->execute_query("INSERT INTO users (uid, username, name, email, role, account_type, avatar, created_at) VALUES (?, ?, ?, ?, 'student', 'student', ?, NOW())", [$account['id'], $account['name'], $account['name'], $account['email'], $account['avatar']]);
                    $userId = (int)$conn->insert_id;
                }
                $_SESSION['user_id'] = $userId;
                $_SESSION['account_id'] = $account['id'];
                $_SESSION['account_type'] = 'student';
                $_SESSION['user_role'] = 'student';
                $_SESSION['user_name'] = $account['name'];
                header("Location: index.php");
                exit;
            } else {
                // Check if account belongs to another portal
                $inOther = $conn->execute_query("SELECT 1 FROM faculty WHERE email = ? LIMIT 1", [$input])->fetch_row()
                        || $conn->execute_query("SELECT 1 FROM admins WHERE email = ? LIMIT 1", [$input])->fetch_row();
                $error = $inOther 
                    ? "Invalid email or account type. This account is not authorized to access this portal." 
                    : "No student account found with that email address.";
            }
        } elseif ($login_role === 'faculty') {
            $account = $conn->execute_query("SELECT * FROM faculty WHERE id = ? OR email = ? OR name = ? LIMIT 1", [$input, $input, $input])->fetch_assoc();
            if ($account) {
                $u = $conn->execute_query("SELECT id FROM users WHERE uid = ? OR email = ? LIMIT 1", [$account['id'], $account['email']])->fetch_assoc();
                $userId = $u ? (int)$u['id'] : 0;
                if (!$userId) {
                    $conn->execute_query("INSERT INTO users (uid, username, name, email, role, account_type, avatar, created_at) VALUES (?, ?, ?, ?, 'faculty', 'faculty', ?, NOW())", [$account['id'], $account['name'], $account['name'], $account['email'], $account['avatar']]);
                    $userId = (int)$conn->insert_id;
                }
                $_SESSION['user_id'] = $userId;
                $_SESSION['account_id'] = $account['id'];
                $_SESSION['account_type'] = 'faculty';
                $_SESSION['user_role'] = 'faculty';
                $_SESSION['user_name'] = $account['name'];
                header("Location: index.php");
                exit;
            } else {
                $inOther = $conn->execute_query("SELECT 1 FROM students WHERE id = ? OR email = ? OR name = ? LIMIT 1", [$input, $input, $input])->fetch_row()
                        || $conn->execute_query("SELECT 1 FROM admins WHERE id = ? OR email = ? OR name = ? LIMIT 1", [$input, $input, $input])->fetch_row();
                $error = $inOther 
                    ? "Invalid ID or account type. This account is not authorized to access this portal." 
                    : "No faculty account found with the provided identifier.";
            }
        } elseif ($login_role === 'admin') {
            $account = $conn->execute_query("SELECT * FROM admins WHERE email = ? LIMIT 1", [$input])->fetch_assoc();
            if ($account) {
                $u = $conn->execute_query("SELECT id FROM users WHERE uid = ? OR email = ? LIMIT 1", [$account['id'], $account['email']])->fetch_assoc();
                $userId = $u ? (int)$u['id'] : 0;
                if (!$userId) {
                    $conn->execute_query("INSERT INTO users (uid, username, name, email, role, account_type, avatar, created_at) VALUES (?, ?, ?, ?, 'admin', 'admin', ?, NOW())", [$account['id'], $account['name'], $account['name'], $account['email'], $account['avatar']]);
                    $userId = (int)$conn->insert_id;
                }
                $_SESSION['user_id'] = $userId;
                $_SESSION['account_id'] = $account['id'];
                $_SESSION['account_type'] = 'admin';
                $_SESSION['user_role'] = 'admin';
                $_SESSION['user_name'] = $account['name'];
                header("Location: index.php?view=admin");
                exit;
            } else {
                $inOther = $conn->execute_query("SELECT 1 FROM students WHERE email = ? LIMIT 1", [$input])->fetch_row()
                        || $conn->execute_query("SELECT 1 FROM faculty WHERE email = ? LIMIT 1", [$input])->fetch_row();
                $error = $inOther 
                    ? "Invalid email or account type. This account is not authorized to access this portal." 
                    : "No administrator account found with that email address.";
            }
        }
    } else {
        // Standard Login
        $login_input = trim($_POST['login_input'] ?? '');
        $password    = $_POST['password'] ?? '';

        if ($login_input === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } elseif ($login_role === 'student') {
            // Authenticate against students table using registered email address and password
            $account = $conn->execute_query("SELECT * FROM students WHERE email = ? LIMIT 1", [$login_input])->fetch_assoc();

            if ($account) {
                if (password_verify($password, $account['password']) || $account['password'] === $password) {
                    if ($account['password'] === $password) {
                        $conn->execute_query("UPDATE students SET password = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $account['id']]);
                    }

                    $u = $conn->execute_query("SELECT id FROM users WHERE uid = ? OR email = ? LIMIT 1", [$account['id'], $account['email']])->fetch_assoc();
                    $userId = $u ? (int)$u['id'] : 0;
                    if (!$userId) {
                        $conn->execute_query("INSERT INTO users (uid, username, name, email, role, account_type, avatar, created_at) VALUES (?, ?, ?, ?, 'student', 'student', ?, NOW())", [$account['id'], $account['name'], $account['name'], $account['email'], $account['avatar']]);
                        $userId = (int)$conn->insert_id;
                    }

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['account_id'] = $account['id'];
                    $_SESSION['account_type'] = 'student';
                    $_SESSION['user_role'] = 'student';
                    $_SESSION['user_name'] = $account['name'];

                    header("Location: index.php");
                    exit;
                } else {
                    $error = 'Invalid password. Please check your credentials.';
                }
            } else {
                // Strict Login Restriction Check
                $inFaculty = $conn->execute_query("SELECT 1 FROM faculty WHERE email = ? LIMIT 1", [$login_input])->fetch_row();
                $inAdmin   = $conn->execute_query("SELECT 1 FROM admins WHERE email = ? LIMIT 1", [$login_input])->fetch_row();

                if ($inFaculty || $inAdmin) {
                    $error = 'Invalid email or account type. This account is not authorized to access this portal.';
                } else {
                    $error = 'No student account found with that email address.';
                }
            }
        } elseif ($login_role === 'faculty') {
            // Authenticate against faculty table (supports 10-digit ID, Email, or Full Name)
            $account = $conn->execute_query("SELECT * FROM faculty WHERE id = ? OR email = ? OR name = ? LIMIT 1", [$login_input, $login_input, $login_input])->fetch_assoc();

            if ($account) {
                if (password_verify($password, $account['password'])) {
                    $u = $conn->execute_query("SELECT id FROM users WHERE uid = ? OR email = ? LIMIT 1", [$account['id'], $account['email']])->fetch_assoc();
                    $userId = $u ? (int)$u['id'] : 0;
                    if (!$userId) {
                        $conn->execute_query("INSERT INTO users (uid, username, name, email, role, account_type, avatar, created_at) VALUES (?, ?, ?, ?, 'faculty', 'faculty', ?, NOW())", [$account['id'], $account['name'], $account['name'], $account['email'], $account['avatar']]);
                        $userId = (int)$conn->insert_id;
                    }

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['account_id'] = $account['id'];
                    $_SESSION['account_type'] = 'faculty';
                    $_SESSION['user_role'] = 'faculty';
                    $_SESSION['user_name'] = $account['name'];

                    header("Location: index.php");
                    exit;
                } else {
                    $error = 'Invalid password. Please check your credentials.';
                }
            } else {
                // Strict Login Restriction Check
                $inStudent = $conn->execute_query("SELECT 1 FROM students WHERE id = ? OR email = ? OR name = ? LIMIT 1", [$login_input, $login_input, $login_input])->fetch_row();
                $inAdmin   = $conn->execute_query("SELECT 1 FROM admins WHERE id = ? OR email = ? OR name = ? LIMIT 1", [$login_input, $login_input, $login_input])->fetch_row();

                if ($inStudent || $inAdmin) {
                    $error = 'Invalid ID or account type. This account is not authorized to access this portal.';
                } else {
                    $error = 'No faculty account found with the provided identifier.';
                }
            }
        } elseif ($login_role === 'admin') {
            // Authenticate against admins table using registered email address and password
            $account = $conn->execute_query("SELECT * FROM admins WHERE email = ? LIMIT 1", [$login_input])->fetch_assoc();

            if ($account) {
                if (password_verify($password, $account['password'])) {
                    $u = $conn->execute_query("SELECT id FROM users WHERE uid = ? OR email = ? LIMIT 1", [$account['id'], $account['email']])->fetch_assoc();
                    $userId = $u ? (int)$u['id'] : 0;
                    if (!$userId) {
                        $conn->execute_query("INSERT INTO users (uid, username, name, email, role, account_type, avatar, created_at) VALUES (?, ?, ?, ?, 'admin', 'admin', ?, NOW())", [$account['id'], $account['name'], $account['name'], $account['email'], $account['avatar']]);
                        $userId = (int)$conn->insert_id;
                    }

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['account_id'] = $account['id'];
                    $_SESSION['account_type'] = 'admin';
                    $_SESSION['user_role'] = 'admin';
                    $_SESSION['user_name'] = $account['name'];

                    header("Location: index.php?view=admin");
                    exit;
                } else {
                    $error = 'Invalid password. Please check your credentials.';
                }
            } else {
                // Strict Login Restriction Check
                $inStudent = $conn->execute_query("SELECT 1 FROM students WHERE email = ? LIMIT 1", [$login_input])->fetch_row();
                $inFaculty = $conn->execute_query("SELECT 1 FROM faculty WHERE email = ? LIMIT 1", [$login_input])->fetch_row();

                if ($inStudent || $inFaculty) {
                    $error = 'Invalid email or account type. This account is not authorized to access this portal.';
                } else {
                    $error = 'No administrator account found with that email address.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= ucfirst($requestedRole) ?> Login | Quaddit</title>
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
      
      <!-- Login Switcher (Faculty option hidden from public UI) -->
      <nav class="auth-role-tabs" aria-label="Account Type Selection">
        <a href="login.php?role=student" class="auth-role-tab role-tab-student <?= $requestedRole === 'student' ? 'active' : '' ?>">
          <span class="role-dot" aria-hidden="true"></span>
          <span>Student</span>
        </a>
        <?php /* Faculty login option hidden from public UI while preserving internal faculty system
        <a href="login.php?role=faculty" class="auth-role-tab role-tab-faculty <?= $requestedRole === 'faculty' ? 'active' : '' ?>">
          <span class="role-dot" aria-hidden="true"></span>
          <span>Faculty</span>
        </a>
        */ ?>
        <a href="login.php?role=admin" class="auth-role-tab role-tab-admin <?= $requestedRole === 'admin' ? 'active' : '' ?>">
          <span class="role-dot" aria-hidden="true"></span>
          <span>Admin</span>
        </a>
      </nav>

      <!-- Card Title & Description -->
      <div class="auth-card-header">
        <?php if ($requestedRole === 'admin'): ?>
          <h2 class="auth-card-title">Admin Sign In</h2>
          <p class="auth-card-subtitle">Sign in with your registered administrator email address and password.</p>
        <?php elseif ($requestedRole === 'faculty'): ?>
          <h2 class="auth-card-title">Faculty Sign In</h2>
          <p class="auth-card-subtitle">Sign in with your Full Name, Email, or 10-digit Faculty ID.</p>
        <?php else: ?>
          <h2 class="auth-card-title">Student Sign In</h2>
          <p class="auth-card-subtitle">Sign in with your registered email address and password.</p>
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

      <?php if (!empty($success)): ?>
        <div class="auth-alert auth-alert-success" role="status">
          <svg class="auth-alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          <span><?= e($success) ?></span>
        </div>
      <?php endif; ?>

      <form action="login.php?role=<?= e($requestedRole) ?>" method="POST" class="auth-form" id="login-form" autocomplete="off">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="login_role" value="<?= e($requestedRole) ?>">

        <!-- Identifier input -->
        <div class="form-group">
          <label for="login-input" class="form-label">
            <?= $requestedRole === 'faculty' ? 'Faculty ID, Email, or Full Name' : 'Email Address' ?>
            <span class="required" aria-hidden="true">*</span>
          </label>
          <input type="<?= $requestedRole === 'faculty' ? 'text' : 'email' ?>" name="login_input" id="login-input" class="form-control" placeholder="<?= $requestedRole === 'admin' ? 'e.g. admin@college.edu' : ($requestedRole === 'faculty' ? 'e.g. 2000000001' : 'e.g. student@college.edu') ?>" value="<?= !empty($login_input) ? e($login_input) : '' ?>" autocomplete="<?= $requestedRole === 'faculty' ? 'username' : 'email' ?>" required>
        </div>

        <!-- Password input -->
        <div class="form-group">
          <label for="login-password" class="form-label">Password <span class="required" aria-hidden="true">*</span></label>
          <div class="input-password-wrap">
            <input type="password" name="password" id="login-password" class="form-control" placeholder="Enter your password" autocomplete="current-password" required>
            <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('login-password', this)" aria-label="Toggle password visibility" title="Show password">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>

        <!-- Options row (Remember me & Instant Sign In) -->
        <div class="auth-options-row">
          <label class="remember-label" for="remember-me">
            <input type="checkbox" name="remember" id="remember-me" class="remember-checkbox">
            <span>Remember me</span>
          </label>
          <a href="#instant" class="forgot-link" onclick="handleForgotPassword(event); return false;">Instant Sign In</a>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn-auth-submit <?= $requestedRole === 'admin' ? 'btn-auth-admin' : ($requestedRole === 'faculty' ? 'btn-auth-faculty' : '') ?>" id="btn-login-submit">
          <?= $requestedRole === 'admin' ? 'Sign In as Administrator' : ($requestedRole === 'faculty' ? 'Sign In as Faculty' : 'Sign In as Student') ?>
        </button>

        <!-- Footer Link -->
        <p class="auth-footer-prompt">
          Don't have an account? 
          <a href="register.php?role=<?= e($requestedRole) ?>">
            Register as <?= ucfirst($requestedRole) ?>
          </a>
        </p>
      </form>
    </div>
  </div>

  <!-- Instant Sign In Modal (Prototype) -->
  <div class="auth-modal-overlay" id="reset-modal" onclick="closeResetModalOnBackdrop(event)">
    <div class="auth-modal-card">
      <div class="auth-modal-header">
        <h3 class="auth-modal-title">Instant <?= ucfirst($requestedRole) ?> Sign In</h3>
        <button type="button" class="btn-close-modal" onclick="closeResetModal()" aria-label="Close dialog">&times;</button>
      </div>
      <p class="auth-modal-desc">
        Enter your registered email address to sign in directly without a password.
      </p>

      <form action="login.php?role=<?= e($requestedRole) ?>" method="POST" class="auth-form" id="bypass-login-form">
        <input type="hidden" name="action" value="bypass_login">
        <input type="hidden" name="login_role" value="<?= e($requestedRole) ?>">

        <div class="form-group">
          <label for="bypass-input-field" class="form-label">Email Address <span class="required" aria-hidden="true">*</span></label>
          <input type="email" name="bypass_email" id="bypass-input-field" class="form-control" placeholder="e.g. you@college.edu" required autofocus>
        </div>

        <button type="submit" class="btn-auth-submit <?= $requestedRole === 'admin' ? 'btn-auth-admin' : ($requestedRole === 'faculty' ? 'btn-auth-faculty' : '') ?>" id="btn-bypass-submit">
          Sign In Directly
        </button>
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

    function handleForgotPassword(e) {
      if (e) e.preventDefault();
      const modal = document.getElementById('reset-modal');
      if (modal) modal.classList.add('active');
    }

    function closeResetModal() {
      const modal = document.getElementById('reset-modal');
      if (modal) modal.classList.remove('active');
    }

    function closeResetModalOnBackdrop(e) {
      if (e.target.id === 'reset-modal') {
        closeResetModal();
      }
    }

    document.getElementById('login-form').addEventListener('submit', function() {
      const btn = document.getElementById('btn-login-submit');
      if (btn) {
        btn.textContent = 'Verifying credentials...';
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
