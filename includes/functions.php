<?php
// includes/functions.php - Helper functions for CampusForum

require_once __DIR__ . '/db.php';

function check_remember_login() {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    if (empty($_COOKIE['campus_remember'])) {
        return false;
    }

    $parts = explode(':', $_COOKIE['campus_remember'], 2);
    if (count($parts) !== 2) {
        setcookie('campus_remember', '', time() - 3600, '/');
        return false;
    }

    $user_id = (int)$parts[0];
    $raw_token = $parts[1];

    if ($user_id <= 0 || empty($raw_token)) {
        setcookie('campus_remember', '', time() - 3600, '/');
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, remember_token FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && !empty($user['remember_token'])) {
            $hashed_incoming = hash('sha256', $raw_token);
            if (hash_equals($user['remember_token'], $hashed_incoming)) {
                $_SESSION['user_id'] = (int)$user['id'];
                return true;
            }
        }
    } catch (Exception $e) {
        // Fallback gracefully on query error
    }

    // Invalid cookie / token, delete cookie
    setcookie('campus_remember', '', time() - 3600, '/');
    return false;
}

function set_remember_login($user_id) {
    global $pdo;
    $raw_token = bin2hex(random_bytes(32));
    $hashed_token = hash('sha256', $raw_token);

    try {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->execute([$hashed_token, $user_id]);

        // Cookie lasts 30 days
        setcookie('campus_remember', $user_id . ':' . $raw_token, time() + (86400 * 30), '/', '', false, true);
    } catch (Exception $e) {
        // Ignore error
    }
}

function clear_login() {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (Exception $e) {}
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    setcookie('campus_remember', '', time() - 3600, '/');
}

function is_logged_in() {
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    return check_remember_login();
}

function current_user() {
    global $pdo;
    if (!is_logged_in()) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, name, avatar, role, bio FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            clear_login();
            return null;
        }
        return $user;
    } catch (Exception $e) {
        return null;
    }
}

function get_user_by_id($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, email, name, avatar, role, bio FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function time_ago($datetime) {
    if (empty($datetime)) return 'just now';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

function get_post_images($post_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT image_url FROM post_images WHERE post_id = ? ORDER BY id ASC");
    $stmt->execute([$post_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_reply_count($post_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM replies WHERE post_id = ?");
    $stmt->execute([$post_id]);
    return (int)$stmt->fetchColumn();
}

function get_like_count($post_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ? AND reply_id IS NULL");
    $stmt->execute([$post_id]);
    return (int)$stmt->fetchColumn();
}

function user_has_liked($user_id, $post_id) {
    if (!$user_id) return false;
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ? AND reply_id IS NULL");
    $stmt->execute([$user_id, $post_id]);
    return (bool)$stmt->fetch();
}

function user_has_saved($user_id, $post_id) {
    if (!$user_id) return false;
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    return (bool)$stmt->fetch();
}

function get_recent_discussions($limit = 5) {
    global $pdo;
    $sql = "SELECT p.id, p.title, p.created_at, u.name, u.avatar,
            (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count
            FROM posts p
            JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
            LIMIT " . (int)$limit;
    return $pdo->query($sql)->fetchAll();
}

function get_all_topics() {
    global $pdo;
    return $pdo->query("SELECT * FROM topics ORDER BY id ASC")->fetchAll();
}

// Hierarchical replies fetching
function get_post_replies_tree($post_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*, u.name, u.username, u.avatar 
        FROM replies r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.post_id = ? 
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$post_id]);
    $all_replies = $stmt->fetchAll();

    // Group by parent_id
    $tree = [];
    $lookup = [];

    foreach ($all_replies as $reply) {
        $reply['children'] = [];
        $lookup[$reply['id']] = $reply;
    }

    foreach ($lookup as $id => &$reply) {
        if (!empty($reply['parent_id']) && isset($lookup[$reply['parent_id']])) {
            $lookup[$reply['parent_id']]['children'][] = &$reply;
        } else {
            $tree[] = &$reply;
        }
    }
    unset($reply);

    return $tree;
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
