<?php
// login.php - CampusForum Login Screen
require_once __DIR__ . '/db.php';

// If already logged in, redirect directly to index.php
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if (isset($_GET['registered'])) {
    $success = 'Account created successfully! Please log in to continue.';
} elseif (isset($_GET['logged_out'])) {
    $success = 'You have signed out successfully.';
} elseif (isset($_GET['reset'])) {
    $success = 'Password has been reset successfully! Please log in with your new password.';
}

$login_input = trim($_GET['username'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'reset_password') {
        $reset_user = trim($_POST['reset_user'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_new_password'] ?? '';

        if (empty($reset_user) || empty($new_pass) || empty($confirm_pass)) {
            $error = 'Please fill in all fields to reset your password.';
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_pass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(username)) = LOWER(?) OR LOWER(TRIM(email)) = LOWER(?) OR LOWER(TRIM(name)) = LOWER(?) LIMIT 1");
            $stmt->execute([$reset_user, $reset_user, $reset_user]);
            $targetUser = $stmt->fetch();

            if ($targetUser) {
                $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->execute([$new_pass, (int)$targetUser['id']]);
                header("Location: login.php?reset=1");
                exit;
            } else {
                $error = 'No user account found matching that email or username.';
            }
        }
    } else {
        $login_input = trim($_POST['login_input'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login_input) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            // Support login by username, email, or full name
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(TRIM(username)) = LOWER(?) OR LOWER(TRIM(email)) = LOWER(?) OR LOWER(TRIM(name)) = LOWER(?) LIMIT 1");
            $stmt->execute([$login_input, $login_input, $login_input]);
            $user = $stmt->fetch();

            $passwordMatches = false;
            if ($user) {
                if ($user['password'] === $password) {
                    $passwordMatches = true;
                } elseif (password_verify($password, $user['password'])) {
                    $passwordMatches = true;
                }
            }

            if ($user && $passwordMatches) {
                $_SESSION['user_id'] = (int)$user['id'];
                header("Location: index.php");
                exit;
            } else {
                $error = 'Invalid email/username or password.';
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
  <title>Login | CampusForum</title>
  <link rel="stylesheet" href="assets/css/auth.css?v=<?= time() ?>">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2338bdf8'><path d='M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z'/></svg>">
  <style>
    /* Direct inline autofill reset to override browser defaults immediately */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active {
      -webkit-box-shadow: 0 0 0 1000px #091424 inset !important;
      -webkit-text-fill-color: #f8fafc !important;
      caret-color: #f8fafc !important;
      border-radius: 12px !important;
      border: 1px solid #1e2e47 !important;
      transition: background-color 500000s ease-in-out 0s, color 500000s ease-in-out 0s !important;
    }
    [data-theme="light"] input:-webkit-autofill,
    [data-theme="light"] input:-webkit-autofill:hover, 
    [data-theme="light"] input:-webkit-autofill:focus, 
    [data-theme="light"] input:-webkit-autofill:active {
      -webkit-box-shadow: 0 0 0 1000px #f8fafc inset !important;
      -webkit-text-fill-color: #0f172a !important;
      caret-color: #0f172a !important;
      border-radius: 12px !important;
      border: 1px solid #cbd5e1 !important;
      transition: background-color 500000s ease-in-out 0s, color 500000s ease-in-out 0s !important;
    }
  </style>
  <script>
    (function() {
      const savedTheme = localStorage.getItem('campus_theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();
  </script>
</head>
<body class="auth-page">
  <!-- Theme Toggle Floating Pill -->
  <button type="button" class="auth-theme-toggle" onclick="toggleAuthTheme()" id="auth-theme-btn" title="Toggle Light/Dark Theme">
    <span id="auth-theme-icon">🌙</span>
    <span id="auth-theme-text">Dark</span>
  </button>

  <!-- Campus Night Background matching Design -->
  <div class="auth-bg-layer"></div>
  <div class="auth-overlay-gradient"></div>

  <div class="auth-wrapper" style="margin: auto;">
    <!-- Brand Header -->
    <div class="auth-header-branding">
      <div class="auth-logo-bubbles">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
      </div>
      <h1 class="auth-brand-title">Campus<span>Forum</span></h1>
      <p class="auth-tagline">Share ideas. Ask questions. Grow together.</p>
    </div>

    <!-- Login Frosted Card -->
    <div class="auth-card">
      <h2 class="auth-card-title">Welcome Back</h2>
      <p class="auth-card-subtitle">Login to continue to CampusForum</p>

      <?php if (!empty($error)): ?>
        <div class="auth-alert auth-alert-error">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="auth-alert auth-alert-success">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          <span><?= e($success) ?></span>
        </div>
      <?php endif; ?>

      <form action="login.php" method="POST" class="auth-form" id="login-form">
        <input type="hidden" name="action" value="login">

        <!-- Email or Username field -->
        <div class="auth-field-box">
          <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
          </svg>
          <input type="text" name="login_input" class="auth-input" placeholder="Enter your email or username" value="<?= isset($_POST['login_input']) ? e($_POST['login_input']) : e($_GET['username'] ?? '') ?>" autocomplete="username" required>
        </div>

        <!-- Password field with eye toggle -->
        <div class="auth-field-box">
          <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <input type="password" name="password" id="login-password" class="auth-input has-eye" placeholder="Enter your password" autocomplete="current-password" required>
          <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('login-password', this)" aria-label="Toggle password visibility" title="Show password">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>

        <!-- Options row (Forgot pass) -->
        <div class="auth-options-row" style="justify-content: flex-end;">
          <a href="#reset" class="forgot-link" onclick="openResetModal(); return false;">Forgot password?</a>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn-auth-submit" id="btn-login-submit">Login</button>

        <!-- Divider -->
        <div class="auth-divider">
          <span>or continue with</span>
        </div>

        <!-- Social Icons matching Image 2 -->
        <div class="auth-social-row">
          <!-- Google -->
          <button type="button" class="btn-social" title="Continue with Google" onclick="alert('Please log in using your registered username or email.');">
            <svg width="20" height="20" viewBox="0 0 24 24">
              <path fill="#EA4335" d="M12 5c1.56 0 2.98.54 4.09 1.58l3.07-3.07C17.3 1.77 14.84 1 12 1 7.42 1 3.53 3.61 1.71 7.39l3.66 2.84C6.25 7.37 8.88 5 12 5z"/>
              <path fill="#4285F4" d="M23.49 12.28c0-.79-.07-1.54-.19-2.28H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58l3.68 2.86c2.14-1.98 3.74-4.89 3.74-8.67z"/>
              <path fill="#FBBC05" d="M5.37 14.77c-.24-.72-.37-1.49-.37-2.77s.13-1.55.37-2.27L1.71 7.39C.62 9.56 0 11.97 0 14.5s.62 4.94 1.71 7.11l3.66-2.84z"/>
              <path fill="#34A853" d="M12 23.5c3.24 0 5.96-1.08 7.95-2.92l-3.68-2.86c-1.08.72-2.46 1.15-4.27 1.15-3.12 0-5.75-2.37-6.63-5.23L1.71 16.48C3.53 20.26 7.42 22.87 12 23.5z"/>
            </svg>
          </button>
          <!-- GitHub -->
          <button type="button" class="btn-social" title="Continue with GitHub" onclick="alert('Please log in using your registered username or email.');">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#ffffff">
              <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/>
            </svg>
          </button>
          <!-- Microsoft -->
          <button type="button" class="btn-social" title="Continue with Microsoft" onclick="alert('Please log in using your registered username or email.');">
            <svg width="18" height="18" viewBox="0 0 24 24">
              <path fill="#F25022" d="M1 1h10v10H1z"/>
              <path fill="#7FBA00" d="M13 1h10v10H13z"/>
              <path fill="#00A4EF" d="M1 13h10v10H1z"/>
              <path fill="#FFB900" d="M13 13h10v10H13z"/>
            </svg>
          </button>
        </div>

        <!-- Footer Switch -->
        <p class="auth-footer-prompt">
          Don't have an account? <a href="register.php">Register</a>
        </p>
      </form>
    </div>
  </div>

  <!-- Reset Password Modal -->
  <div class="auth-modal-overlay" id="reset-modal" onclick="closeResetModalOnBackdrop(event)">
    <div class="auth-modal-card">
      <div class="auth-modal-header">
        <h3 class="auth-modal-title">Reset Password</h3>
        <button type="button" class="btn-close-modal" onclick="closeResetModal()">&times;</button>
      </div>
      <p class="auth-modal-desc">Enter your username or registered email and choose a new password.</p>

      <form action="login.php" method="POST" class="auth-form">
        <input type="hidden" name="action" value="reset_password">

        <div class="auth-field-box">
          <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
          </svg>
          <input type="text" name="reset_user" class="auth-input" placeholder="Your username or email" required>
        </div>

        <div class="auth-field-box">
          <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <input type="password" name="new_password" id="reset-pass" class="auth-input has-eye" placeholder="New password (min 6 chars)" required>
          <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('reset-pass', this)" aria-label="Toggle password visibility" title="Show password">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>

        <div class="auth-field-box">
          <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <input type="password" name="confirm_new_password" id="reset-confirm-pass" class="auth-input has-eye" placeholder="Confirm new password" required>
          <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('reset-confirm-pass', this)" aria-label="Toggle password visibility" title="Show password">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>

        <button type="submit" class="btn-auth-submit" style="margin-top: 8px;">Update Password</button>
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
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
        btn.setAttribute('aria-label', 'Hide password');
        btn.setAttribute('title', 'Hide password');
      } else {
        input.type = 'password';
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('title', 'Show password');
      }
    }

    function openResetModal() {
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

    // Submit animation feedback
    document.getElementById('login-form').addEventListener('submit', function() {
      const btn = document.getElementById('btn-login-submit');
      if (btn) {
        btn.textContent = 'Logging in...';
        btn.style.opacity = '0.85';
      }
    });
  </script>
</body>
</html>
