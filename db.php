<?php
// db.php - Database connection & session setup

// Ensure PHP and MySQL share the same local timezone (+05:30 / Asia/Kolkata)
date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect to Localhost Database (XAMPP)
$conn = new mysqli('127.0.0.1', 'root', '', 'Quaddit');
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+05:30'");

// Helper: Escape HTML output to prevent XSS
if (!function_exists('e')) {
    function e(?string $str = ''): string {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Helper: Check if user is logged in
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        return !empty($_SESSION['user_id']);
    }
}

// Helper: Get current user ID
if (!function_exists('current_user_id')) {
    function current_user_id(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

// Helper: Get current account 10-digit ID
if (!function_exists('current_account_id')) {
    function current_account_id(): string {
        return (string)($_SESSION['account_id'] ?? '');
    }
}

// Helper: Get current account type ('student', 'faculty', 'admin')
if (!function_exists('current_account_type')) {
    function current_account_type(): string {
        return (string)($_SESSION['account_type'] ?? 'student');
    }
}

// Helper: Check if currently authenticated user has admin role
if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return is_logged_in() && (
            ($_SESSION['account_type'] ?? '') === 'admin' ||
            ($_SESSION['user_role'] ?? '') === 'admin'
        );
    }
}

// Helper: Check if user is faculty
if (!function_exists('is_faculty')) {
    function is_faculty(): bool {
        return is_logged_in() && (
            ($_SESSION['account_type'] ?? '') === 'faculty' ||
            ($_SESSION['user_role'] ?? '') === 'faculty'
        );
    }
}

// Helper: Check if user is a student
if (!function_exists('is_student')) {
    function is_student(): bool {
        return is_logged_in() && !is_admin() && !is_faculty();
    }
}

// Helper: Check if a table exists in the current database
if (!function_exists('table_exists')) {
    function table_exists(string $table, mysqli $conn): bool {
        static $cache = [];
        if (!isset($cache[$table])) {
            $escaped = $conn->real_escape_string($table);
            $cache[$table] = (bool)$conn->query("SHOW TABLES LIKE '$escaped'")->fetch_row();
        }
        return $cache[$table];
    }
}

// Helper: Check if 10-digit ID exists in any of the account tables
if (!function_exists('check_id_exists')) {
    function check_id_exists(string $id, mysqli $conn): ?string {
        if ($conn->execute_query("SELECT 1 FROM students WHERE id = ? LIMIT 1", [$id])->fetch_row()) return 'student';
        if (table_exists('faculty', $conn) && $conn->execute_query("SELECT 1 FROM faculty WHERE id = ? LIMIT 1", [$id])->fetch_row()) return 'faculty';
        if ($conn->execute_query("SELECT 1 FROM admins WHERE id = ? LIMIT 1", [$id])->fetch_row()) return 'admin';
        return null;
    }
}

// Helper: Check if email exists in any of the account tables
if (!function_exists('check_email_exists')) {
    function check_email_exists(string $email, mysqli $conn): ?string {
        if ($conn->execute_query("SELECT 1 FROM students WHERE email = ? LIMIT 1", [$email])->fetch_row()) return 'student';
        if (table_exists('faculty', $conn) && $conn->execute_query("SELECT 1 FROM faculty WHERE email = ? LIMIT 1", [$email])->fetch_row()) return 'faculty';
        if ($conn->execute_query("SELECT 1 FROM admins WHERE email = ? LIMIT 1", [$email])->fetch_row()) return 'admin';
        return null;
    }
}

// Helper: Check if a user is a member of a group
if (!function_exists('is_group_member')) {
    function is_group_member(int $userId, int $groupId, mysqli $conn): bool {
        if ($userId <= 0 || $groupId <= 0) return false;
        $stmt = $conn->execute_query("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ? LIMIT 1", [$userId, $groupId]);
        return (bool)$stmt->fetch_row();
    }
}
