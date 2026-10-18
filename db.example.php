<?php

// 1. Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database credentials (Localhost vs Live Hosting)
$is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

if ($is_local) {
    // Localhost (XAMPP)
    $host = '127.0.0.1';
    $dbname = 'Quaddit';
    $user = 'root';
    $pass = '';
} else {
    // Live Hosting / Production Settings
    $host = 'YOUR_MYSQL_HOST';
    $dbname = 'YOUR_MYSQL_DATABASE';
    $user = 'YOUR_MYSQL_USER';
    $pass = 'YOUR_MYSQL_PASSWORD';
}

// 3. Connect to MySQL using MySQLi
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set character set to utf8mb4
$conn->set_charset("utf8mb4");

// 4. Helper: Escape output to prevent XSS
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// 5. Helper: Check if user is logged in
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return !empty($_SESSION['user_id']);
    }
}
