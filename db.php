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

// Helper: Format message and post text with safe rich text (Bold, Italic, Strikethrough, Headings, Bullet Lists, Links, Code)
if (!function_exists('render_rich_formatted_text')) {
    function render_rich_formatted_text(?string $str = '', bool $isAnnouncement = false): string {
        if ($str === null || $str === '') return '';

        // Step 1: Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $str);

        // Step 2: Normalize inline headings and bullet points if squashed without newlines
        $text = preg_replace('/(?<!\n)\s+(#{1,3})\s+/', "\n\n$1 ", $text);
        $text = preg_replace('/(?<!\n)\s+([•\-\*])\s+(\*\*|__)/u', "\n$1 $2", $text);

        // Step 3: Protect inline code blocks e.g. `code`
        $codeBlocks = [];
        $text = preg_replace_callback('/`([^`]+)`/', function($m) use (&$codeBlocks) {
            $idx = count($codeBlocks);
            $escaped = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $codeBlocks[$idx] = '<code style="background: rgba(255, 255, 255, 0.08); padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9em; color: #38bdf8;">' . $escaped . '</code>';
            return "%%CODEBLOCK_{$idx}%%";
        }, $text);

        // If text already has HTML block tags (like <p>, <ul>, <h3> from rich editor), sanitize them
        $hasHtmlTags = preg_match('/<(?:p|ul|ol|li|h[1-6]|div|blockquote|table|b|strong|i|em|u|s|del)[^>]*>/i', $text);
        if ($hasHtmlTags) {
            $allowedTags = '<b><strong><i><em><s><del><strike><u><h3><h4><h5><p><br><div><span><blockquote><ul><ol><li><a><code><pre>';
            $text = strip_tags($text, $allowedTags);
            $text = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $text);
            $text = preg_replace('/javascript:/i', '', $text);
        } else {
            // Safe encode raw HTML entities
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        // Step 4: Headings: ### Heading or ## Heading
        if ($isAnnouncement) {
            $text = preg_replace('/^(?:<p>)?\s*#{1,3}\s+(.+?)(?:<\/p>)?$/m', '<h3 class="announcement-section-heading" style="font-size: 16.5px; font-weight: 700; color: #ffffff; margin: 20px 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.08); display: block;">$1</h3>', $text);
        } else {
            $text = preg_replace('/^(?:<p>)?\s*#{1,3}\s+(.+?)(?:<\/p>)?$/m', '<strong class="post-heading" style="font-size: 1.15em; font-weight: 700; display: inline-block; margin: 8px 0 4px 0; color: var(--text-main);">$1</strong>', $text);
        }

        // Step 5: Parse bullet and numbered lists BEFORE bold/italic
        $lines = explode("\n", $text);
        $inUl = false;
        $inOl = false;
        $processed = [];

        $ulStyle = $isAnnouncement ? 
            'margin: 10px 0 14px 22px; padding-left: 4px; line-height: 1.75; color: #e2e8f0; list-style-type: disc;' :
            'padding-left: 22px; margin: 6px 0 10px 0; list-style-type: disc; color: var(--text-main);';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Check bullet line: starts with * or - or •
            if (preg_match('/^(?:<p>)?\s*[•\-\*]\s+(.+?)(?:<\/p>)?$/u', $trimmed, $m)) {
                if ($inOl) { $processed[] = '</ol>'; $inOl = false; }
                if (!$inUl) {
                    $processed[] = '<ul class="announcement-bullet-list" style="' . $ulStyle . '">';
                    $inUl = true;
                }
                $processed[] = '<li style="margin-bottom: 6px;">' . $m[1] . '</li>';
                continue;
            }

            // Check numbered list line: starts with 1. 2. etc
            if (preg_match('/^(?:<p>)?\s*(\d+)\.\s+(.+?)(?:<\/p>)?$/u', $trimmed, $m)) {
                if ($inUl) { $processed[] = '</ul>'; $inUl = false; }
                if (!$inOl) {
                    $processed[] = '<ol style="padding-left: 24px; margin: 6px 0 10px 0; color: var(--text-main);">';
                    $inOl = true;
                }
                $processed[] = '<li style="margin-bottom: 6px;">' . $m[2] . '</li>';
                continue;
            }

            // Not a list item
            if ($inUl) { $processed[] = '</ul>'; $inUl = false; }
            if ($inOl) { $processed[] = '</ol>'; $inOl = false; }
            $processed[] = $line;
        }
        if ($inUl) { $processed[] = '</ul>'; }
        if ($inOl) { $processed[] = '</ol>'; }

        $html = implode("\n", $processed);

        // Step 6: Bold: **text** or __text__
        $boldColor = $isAnnouncement ? '#f1f5f9' : 'var(--text-main)';
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong style="font-weight: 700; color: ' . $boldColor . ';">$1</strong>', $html);
        $html = preg_replace('/__(.+?)__/s', '<strong style="font-weight: 700; color: ' . $boldColor . ';">$1</strong>', $html);

        // Step 7: Italic: *text* or _text_ (excluding inside words or URLs)
        $html = preg_replace('/(?<![\*\w])\*(?!\*)(.+?)(?<![\*\w])\*(?![\*\w])/s', '<em style="font-style: italic;">$1</em>', $html);
        $html = preg_replace('/(?<![_\w])_(?!_)(.+?)(?<![_\w])_(?![_\w])/s', '<em style="font-style: italic;">$1</em>', $html);

        // Step 8: Strikethrough: ~~text~~
        $html = preg_replace('/~~(.+?)~~/s', '<del style="text-decoration: line-through;">$1</del>', $html);

        // Step 9: Markdown link: [text](url)
        $linkColor = $isAnnouncement ? '#38bdf8' : 'var(--primary-blue)';
        $html = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer" style="color: ' . $linkColor . '; text-decoration: underline;">$1</a>', $html);

        // Auto-link bare URLs (if not already inside an <a> tag)
        $html = preg_replace('/(?<!href=")(https?:\/\/[^\s<]+)/', '<a href="$1" target="_blank" rel="noopener noreferrer" style="color: ' . $linkColor . '; text-decoration: underline;">$1</a>', $html);

        // Step 10: Restore inline code blocks
        foreach ($codeBlocks as $idx => $codeHtml) {
            $html = str_replace("%%CODEBLOCK_{$idx}%%", $codeHtml, $html);
        }

        // Step 11: Add line breaks for paragraphs if not already rich HTML
        if (!$hasHtmlTags) {
            $html = nl2br($html);
            $html = preg_replace('/(<(?:ul|ol)[^>]*>)\s*<br\s*\/?>/i', '$1', $html);
            $html = preg_replace('/<br\s*\/?>\s*(<\/?(?:ul|ol|li)[^>]*>)/i', '$1', $html);
            $html = preg_replace('/(<\/(?:li|ul|ol)>)\s*<br\s*\/?>/i', '$1', $html);
            $html = preg_replace('/(<\/h[1-6]>)\s*<br\s*\/?>/i', '$1', $html);
        }

        return $html;
    }
}

// Helper: Format post and reply text with safe rich text (Bold, Italic, Strikethrough, Headings) & Markdown fallback
if (!function_exists('format_post_content')) {
    function format_post_content(?string $str = ''): string {
        return render_rich_formatted_text($str, false);
    }
}

// Helper: Format official group announcements into structured classic forum layouts (Headings, Bullet points, Links)
if (!function_exists('format_announcement_content')) {
    function format_announcement_content(?string $str = ''): string {
        return render_rich_formatted_text($str, true);
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

// Helper: Check if a user has a pending join request in a group
if (!function_exists('has_group_request')) {
    function has_group_request(int $userId, int $groupId, mysqli $conn): bool {
        if ($userId <= 0 || $groupId <= 0) return false;
        $stmt = $conn->execute_query("SELECT 1 FROM group_requests WHERE user_id = ? AND group_id = ? LIMIT 1", [$userId, $groupId]);
        return (bool)$stmt->fetch_row();
    }
}

// Helper: Render consistent status badge for groups (Public, Private, Restricted)
if (!function_exists('render_group_status_badge')) {
    function render_group_status_badge(string $status, string $extraStyle = ''): string {
        $status = ($status === 'active') ? 'public' : $status;
        $styleAttr = $extraStyle !== '' ? " style=\"$extraStyle\"" : '';
        if ($status === 'private') {
            return "<span class=\"group-status-badge status-private\"$styleAttr title=\"Private: Admin approval required to join\"><svg width=\"11\" height=\"11\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.4\" style=\"display: inline-block; vertical-align: -1px; margin-right: 3px;\"><rect x=\"3\" y=\"11\" width=\"18\" height=\"11\" rx=\"2\" ry=\"2\"></rect><path d=\"M7 11V7a5 5 0 0 1 10 0v4\"></path></svg>Private</span>";
        } elseif ($status === 'restricted') {
            return "<span class=\"group-status-badge status-restricted\"$styleAttr title=\"Restricted: Discussions are paused\"><svg width=\"11\" height=\"11\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.4\" style=\"display: inline-block; vertical-align: -1px; margin-right: 3px;\"><rect x=\"3\" y=\"11\" width=\"18\" height=\"11\" rx=\"2\" ry=\"2\"></rect><path d=\"M7 11V7a5 5 0 0 1 10 0v4\"></path></svg>Restricted</span>";
        } else {
            return "<span class=\"group-status-badge status-active\"$styleAttr title=\"Public: Open to everyone\"><svg width=\"11\" height=\"11\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.4\" style=\"display: inline-block; vertical-align: -1px; margin-right: 3px;\"><circle cx=\"12\" cy=\"12\" r=\"10\"></circle><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"></line><path d=\"M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z\"></path></svg>Public</span>";
        }
    }
}

// Ensure group_notices table exists for admin important announcements
$conn->query("
    CREATE TABLE IF NOT EXISTS `group_notices` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `group_id` INT NOT NULL,
      `user_id` INT NOT NULL,
      `headline` VARCHAR(255) NOT NULL,
      `message` LONGTEXT NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_group_notices_group` (`group_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

