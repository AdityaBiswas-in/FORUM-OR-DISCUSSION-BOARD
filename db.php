<?php
// db.php - Central Database connection & session setup
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

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'campus_forum';

try {
    $pdo = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Check if database exists, create from schema if not
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$db_name}'");
    if (!$stmt->fetch()) {
        $sql_file = __DIR__ . '/database.sql';
        if (file_exists($sql_file)) {
            $pdo->exec(file_get_contents($sql_file));
        }
    }

    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// Global sanitization helper
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Global authentication helpers
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('set_remember_login')) {
    function set_remember_login($user_id) {
        setcookie('campus_remember', (string)$user_id, time() + (86400 * 30), '/');
    }
}
