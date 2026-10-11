<?php
// db.example.php - Template for database connection & session setup
// Rename or copy this file to 'db.php' and insert your database credentials.

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Auto-detect environment: Localhost (XAMPP) vs Live Hosting
$is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
    || (isset($_SERVER['HTTP_HOST']) && str_starts_with($_SERVER['HTTP_HOST'], 'localhost:'))
    || (php_sapi_name() === 'cli');

if ($is_local) {
    // Localhost (XAMPP)
    $db_host = '127.0.0.1';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'Quaddit';
    $db_port = 3306;
} else {
    // Live Hosting / Production Settings
    $db_host = 'YOUR_MYSQL_HOSTNAME';
    $db_user = 'YOUR_MYSQL_USERNAME';
    $db_pass = 'YOUR_MYSQL_PASSWORD';
    $db_name = 'YOUR_MYSQL_DATABASE_NAME';
    $db_port = 3306;
}

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};port={$db_port};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// Global sanitization helper
if (!function_exists('e')) {
    /**
     * Escape HTML special characters for safe rendering.
     *
     * @param string|null $str
     * @return string
     */
    function e(?string $str): string {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Global authentication helpers
if (!function_exists('is_logged_in')) {
    /**
     * Check if the current session represents a valid logged in user.
     *
     * @return bool
     */
    function is_logged_in(): bool {
        global $pdo;
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            if ($stmt->fetch()) {
                return true;
            }
            // User was deleted/recreated, clean stale session
            unset($_SESSION['user_id']);
            return false;
        }
        return false;
    }
}
