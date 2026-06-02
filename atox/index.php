<?php
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: https: http:;");

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
        'domain' => "",
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die("CSRF token validation failed.");
    }
}

ob_start();
require 'db.php';
require_once 'tweet_box.php'; 

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_user'])) {
    try {
        $check_col = $pdo->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
        if($check_col->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) NULL");
        }
        $stmt = $pdo->prepare("SELECT id FROM users WHERE remember_token = ?");
        $stmt->execute([$_COOKIE['remember_user']]);
        $u = $stmt->fetch();
        if ($u) $_SESSION['user_id'] = $u['id'];
    } catch (Exception $e) {}
}

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($uid && !isset($_COOKIE['remember_user'])) {
    try {
        $token = bin2hex(random_bytes(32));
        setcookie('remember_user', $token, [
            'expires' => time() + 604800,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        $check_col = $pdo->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
        if($check_col->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) NULL");
        }
        $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $uid]);
    } catch (Exception $e) {}
}

$show_privacy_modal = false;
if ($uid) {
    try {
        $check_col = $pdo->query("SHOW COLUMNS FROM users LIKE 'privacy_accepted'");
        if($check_col->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN privacy_accepted TINYINT(1) DEFAULT 0");
        }
        $stmt = $pdo->prepare("SELECT privacy_accepted FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        if ((int)$stmt->fetchColumn() === 0) {
            $show_privacy_modal = true;
        }
    } catch (Exception $e) {}
}

if (isset($_POST['accept_privacy']) && $uid) {
    $pdo->prepare("UPDATE users SET privacy_accepted = 1 WHERE id = ?")->execute([$uid]);
    echo "OK"; exit;
}

if (isset($_GET['mark_notifs_read']) && $uid) {
    try {
        $pdo->prepare("UPDATE users SET last_notif_time = NOW() WHERE id = ?")->execute([$uid]);
    } catch (Exception $e) {}
    exit;
}

if (isset($_GET['check_live_notifs']) && $uid) {
    try {
        $u_stmt = $pdo->prepare("SELECT last_notif_time FROM users WHERE id = ?");
        $u_stmt->execute([$uid]);
        $last_time = $u_stmt->fetchColumn();
        $time_cond = $last_time ? "AND created_at > '$last_time'" : "AND created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)";

        $cf = $pdo->prepare("SELECT COUNT(id) FROM follows WHERE following_id = ? $time_cond");
        $cf->execute([$uid]);
        $follows_c = (int)$cf->fetchColumn();

        $cl_q = str_replace("created_at", "l.created_at", $time_cond);
        $cl = $pdo->prepare("SELECT COUNT(l.id) FROM likes l JOIN tweets t ON l.tweet_id = t.id WHERE t.user_id = ? AND l.user_id != ? $cl_q");
        $cl->execute([$uid, $uid]);
        $likes_c = (int)$cl->fetchColumn();

        $cc_q = str_replace("created_at", "c.created_at", $time_cond);
        $cc = $pdo->prepare("SELECT COUNT(c.id) FROM tweets c JOIN tweets t ON c.parent_id = t.id WHERE t.user_id = ? AND c.user_id != ? AND c.is_comment = 1 $cc_q");
        $cc->execute([$uid, $uid]);
        $comments_c = (int)$cc->fetchColumn();

        $count = $follows_c + $likes_c + $comments_c;
        
        $latest = null;
        if ($count > 0) {
            $q = "
                (SELECT 'follow' as type, f.created_at, u.name, u.avatar FROM follows f JOIN users u ON f.follower_id = u.id WHERE f.following_id = $uid $time_cond ORDER BY f.created_at DESC LIMIT 1)
                UNION ALL
                (SELECT 'like' as type, l.created_at, u.name, u.avatar FROM likes l JOIN tweets t ON l.tweet_id = t.id JOIN users u ON l.user_id = u.id WHERE t.user_id = $uid AND l.user_id != $uid $cl_q ORDER BY l.created_at DESC LIMIT 1)
                UNION ALL
                (SELECT 'comment' as type, c.created_at, u.name, u.avatar FROM tweets c JOIN tweets t ON c.parent_id = t.id JOIN users u ON c.user_id = u.id WHERE t.user_id = $uid AND c.user_id != $uid AND c.is_comment = 1 $cc_q ORDER BY c.created_at DESC LIMIT 1)
                ORDER BY created_at DESC LIMIT 1
            ";
            $l_stmt = $pdo->query($q);
            $latest = $l_stmt->fetch(PDO::FETCH_ASSOC);
            if ($latest) {
                $latest['name'] = htmlspecialchars($latest['name'], ENT_QUOTES, 'UTF-8');
                if ($latest['avatar']) {
                    $latest['avatar'] = htmlspecialchars($latest['avatar'], ENT_QUOTES, 'UTF-8');
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['count' => $count, 'follows' => $follows_c, 'likes' => $likes_c, 'comments' => $comments_c, 'latest' => $latest]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['count' => 0]);
        exit;
    }
}

$has_new_notif = false;
if ($uid) {
    try {
        $u_stmt = $pdo->prepare("SELECT last_notif_time FROM users WHERE id = ?");
        $u_stmt->execute([$uid]);
        $last_time = $u_stmt->fetchColumn();
        $time_cond = $last_time ? "AND created_at > '$last_time'" : "AND created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)";

        if ($pdo->query("SELECT 1 FROM follows WHERE following_id = $uid $time_cond LIMIT 1")->fetch()) {
            $has_new_notif = true;
        } elseif ($pdo->query("SELECT 1 FROM likes l JOIN tweets t ON l.tweet_id = t.id WHERE t.user_id = $uid AND l.user_id != $uid " . str_replace("created_at", "l.created_at", $time_cond) . " LIMIT 1")->fetch()) {
            $has_new_notif = true;
        } elseif ($pdo->query("SELECT 1 FROM tweets c JOIN tweets t ON c.parent_id = t.id WHERE t.user_id = $uid AND c.user_id != $uid AND c.is_comment = 1 " . str_replace("created_at", "c.created_at", $time_cond) . " LIMIT 1")->fetch()) {
            $has_new_notif = true;
        }
    } catch(PDOException $e) {}
}

if (isset($_POST['ajax_like']) && $uid) {
    $tid = (int)$_POST['tweet_id'];
    $chk = $pdo->prepare("SELECT id FROM likes WHERE tweet_id=? AND user_id=?");
    $chk->execute([$tid, $uid]);
    $is_liked = false;
    
    if ($chk->rowCount() > 0) {
        $pdo->prepare("DELETE FROM likes WHERE tweet_id=? AND user_id=?")->execute([$tid, $uid]);
    } else {
        $pdo->prepare("INSERT INTO likes (tweet_id, user_id) VALUES (?, ?)")->execute([$tid, $uid]);
        $is_liked = true;
    }
    
    $c = $pdo->prepare("SELECT COUNT(id) FROM likes WHERE tweet_id=?");
    $c->execute([$tid]);
    
    header('Content-Type: application/json');
    echo json_encode(['liked' => $is_liked, 'count' => (int)$c->fetchColumn()]);
    exit;
}

try {
    $check_lvl = $pdo->query("SHOW COLUMNS FROM users LIKE 'level'");
    if($check_lvl->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN level INT DEFAULT 1, ADD COLUMN points INT DEFAULT 0");
    }
    $pdo->exec("UPDATE users SET level = FLOOR(points / 5) + 1 WHERE points >= 0");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT, receiver_id INT, message TEXT, rating TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch(PDOException $e) {}

$user_role = 'user';
$is_logged = false;
$current_user_name = 'کاربر';

function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $jy = ($gy <= 1600) ? 0 : 979; $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * ((int)($days / 12053)); $days %= 12053; $jy += 4 * ((int)($days / 1461)); $days %= 1461; $jy += (int)(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    if ($days < 186) { $jm = 1 + (int)($days / 31); $jd = 1 + ($days % 31); } 
    else { $jm = 7 + (int)(($days - 186) / 30); $jd = 1 + (($days - 186) % 30); }
    return array($jy, $jm, $jd);
}

function pTime($time) {
    if (empty($time)) return '';
    $timestamp = strtotime($time);
    list($jy, $jm, $jd) = gregorian_to_jalali((int)date('Y', $timestamp), (int)date('m', $timestamp), (int)date('d', $timestamp));
    $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    return pNum("$jd {$months[$jm - 1]} $jy / " . date('H:i', $timestamp));
}

function pNum($str) { return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str); }

if ($uid) {
    $stmt = $pdo->prepare("SELECT role, username, name, level, avatar FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) { session_destroy(); setcookie('remember_user', '', time()-3600, '/'); header("Location: index.php"); exit; }
    $user_role = ($user_data['username'] === 'milad') ? 'admin' : ($user_data['role'] ?? 'user');
    $current_user_name = htmlspecialchars($user_data['name'] ?? $user_data['username'], ENT_QUOTES, 'UTF-8');
    if ($user_role === 'admin') $_SESSION['role'] = 'admin';
    $is_logged = true;
}

$is_ajax_scroll = isset($_GET['ajax_scroll']) && $_GET['ajax_scroll'] === '1';

$tab = isset($_GET['tab']) ? htmlspecialchars($_GET['tab'], ENT_QUOTES, 'UTF-8') : 'global';
$search_query = isset($_GET['q']) ? trim(htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8')) : '';
$search_results = []; $tweets = []; 
$total_pages = 1; $limit = 10;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

if (!empty($search_query) && $is_logged) {
    $stmt = $pdo->prepare("SELECT u.id, u.name, u.username, u.avatar, u.is_verified, u.bio, u.level, r.job FROM users u LEFT JOIN resumes r ON u.id = r.user_id WHERE u.username LIKE ? OR u.id = ? LIMIT 50");
    $stmt->execute(["%$search_query%", is_numeric($search_query) ? (int)$search_query : 0]);
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    if ($tab === 'explore') {
        $limit = 20; 
        $offset = ($page - 1) * $limit;
        $total_pages = ceil($pdo->query("SELECT COUNT(id) FROM tweets WHERE image IS NOT NULL AND image != ''")->fetchColumn() / $limit);
        $t_stmt = $pdo->prepare("SELECT id, image FROM tweets WHERE image IS NOT NULL AND image != '' ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $t_stmt->execute();
        $tweets = $t_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $offset = ($page - 1) * $limit;
        $total_pages = ceil($pdo->query("SELECT COUNT(id) FROM tweets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR) AND (is_comment = 0 OR is_comment IS NULL)")->fetchColumn() / $limit);
        
        $t_stmt = $pdo->prepare("SELECT t.*, u.name, u.username, u.avatar, u.is_verified, u.level, r.job,
               (SELECT COUNT(id) FROM likes WHERE tweet_id = t.id) as lc,
               " . ($is_logged ? "(SELECT 1 FROM likes WHERE tweet_id = t.id AND user_id = ? LIMIT 1)" : "0") . " as is_liked,
               (SELECT COUNT(id) FROM tweets WHERE parent_id = t.id AND is_comment = 1) as cc
        FROM tweets t JOIN users u ON t.user_id = u.id LEFT JOIN resumes r ON u.id = r.user_id 
        WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR) AND (t.is_comment = 0 OR t.is_comment IS NULL)
        ORDER BY t.id DESC LIMIT $limit OFFSET $offset");
        if($is_logged) $t_stmt->execute([$uid]); else $t_stmt->execute();
        $tweets = $t_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


$blue_tick = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg>';
$ic_logo = '<svg viewBox="0 0 24 24" style="width:30px;height:30px;fill:var(--x-blue)"><path d="M12 1L14.5 8.5L22 11L14.5 13.5L12 21L9.5 13.5L2 11L9.5 8.5L12 1Z"></path></svg>';
$ic_like = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M16.697 5.5c-1.222-.06-2.679.51-3.89 2.16l-.805 1.09-.806-1.09C9.984 6.01 8.526 5.44 7.304 5.5c-1.243.07-2.349.78-2.91 1.91-.552 1.12-.633 2.78.479 4.82 1.074 1.97 3.257 4.27 7.129 6.61 3.87-2.34 6.052-4.64 7.126-6.61 1.111-2.04 1.03-3.7.477-4.82-.561-1.13-1.666-1.84-2.908-1.91zm4.187 7.69c-1.351 2.48-4.001 5.12-8.379 7.67l-.503.3-.504-.3c-4.379-2.55-7.029-5.19-8.382-7.67-1.36-2.5-1.41-4.86-.514-6.67.887-1.79 2.647-2.91 4.601-3.01 1.651-.09 3.368.56 4.798 2.01 1.429-1.45 3.146-2.1 4.796-2.01 1.954.1 3.714 1.22 4.601 3.01.896 1.81.846 4.17-.514 6.67z"/></svg>';
$ic_liked = '<svg viewBox="0 0 24 24" class="ic-a" style="fill:#f91880"><path d="M12 21.638h-.014C9.403 21.59 1.95 14.856 1.95 8.478c0-3.064 2.525-5.754 5.403-5.754 2.29 0 3.83 1.58 4.646 2.73.814-1.148 2.354-2.73 4.645-2.73 2.88 0 5.404 2.69 5.404 5.755 0 6.376-7.454 13.11-10.037 13.157H12z"/></svg>';
$ic_new_msg = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M19 10.5V4h-2v6.5l-2.5-1.5L12 10.5V4h-2v6.5l-2.5-1.5L5 10.5V4H3v16h18V4h-2v6.5z" opacity="0"/><path d="M11 11V4h2v7h7v2h-7v7h-2v-7H4v-2h7z"/></svg>';
$ic_dots = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>';
$ic_del = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
$ic_edit = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
$ic_reply = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M1.751 10c0-4.42 3.584-8 8.005-8h4.366c4.49 0 8.129 3.64 8.129 8.13 0 2.96-1.607 5.68-4.196 7.11l-8.054 4.46v-3.69h-.067c-4.49.1-8.183-3.51-8.183-8.01zm8.005-6c-3.317 0-6.005 2.69-6.005 6 0 3.37 2.77 6.08 6.138 6.01l.351-.01h1.761v2.3l5.087-2.81c1.951-1.08 3.163-3.13 3.163-5.36 0-3.39-2.744-6.13-6.129-6.13H9.756z"/></svg>';
$ic_send = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';

if ($is_ajax_scroll) {
    if(empty($tweets)) { echo "EOF"; exit; }
    if ($tab === 'explore') {
        foreach($tweets as $t) {
            echo '<a href="view_tweet.php?id='.$t['id'].'" class="exp-grid-item"><img src="'.htmlspecialchars($t['image'], ENT_QUOTES, 'UTF-8').'" loading="lazy" alt="explore"></a>';
        }
    } else {
        foreach($tweets as $t) { render_tweet_box($t, $tab, $is_logged, $uid, $user_role, []); }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>آتوکس - دیوار اعلانات</title>
<link rel="manifest" href="/manifest.json?v=3">
<meta name="theme-color" content="#1DA1F2">
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.85); --x-hover:rgba(15,20,25,0.05); --x-hover-b:rgba(29,155,240,0.1); --x-hover-r:rgba(249,24,128,0.1); --x-modal:rgba(0,0,0,0.4); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.85); --x-hover:rgba(255,255,255,0.05); --x-modal:rgba(255,255,255,0.1); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
html { scroll-behavior: smooth; }
body { background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;overflow-y:overlay; overflow-x:hidden; -webkit-overflow-scrolling: touch; overscroll-behavior-y: contain; }
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}
.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.side{width:275px;padding:0 12px;position:sticky;top:0;height:100vh;display:flex;flex-direction:column;align-items:flex-start}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:100px; min-height:100vh;}
.left-side{width:350px;padding:12px 24px;position:sticky;top:0;height:100vh;display:block;}
.btn{background:var(--x-blue);color:#fff;padding:0 32px;border-radius:9999px;font-weight:700;font-size:17px;min-height:52px;width:90%;transition:.2s;margin-top:15px;display:flex;align-items:center;justify-content:center}
.btn:hover{background:#1a8cd8}
.hdr{position:sticky;top:0;background:var(--x-bg-trans);backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); z-index:10;border-bottom:1px solid var(--x-border)}
.tw-wrapper { padding: 8px 12px; }
.glass-card { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 20px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03); transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; padding: 16px; display: flex; margin-bottom: 12px; content-visibility: auto; contain-intrinsic-size: 200px; contain: content; will-change: transform; transform: translateZ(0); backface-visibility: hidden; }
.dark .glass-card { background: rgba(30, 30, 30, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); }
.glass-card:hover { transform: translateY(-2px) translateZ(0); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
.dark .glass-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
.hdr-top-row { padding: 12px 16px 8px; display: flex; justify-content: space-between; align-items: center; }
.hdr-title { font-size: 19px; font-weight: 900; letter-spacing: -0.5px; }
.hdr-actions { display: flex; gap: 6px; position: relative; }
.hdr-btn { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; cursor: pointer; color: var(--x-black); position: relative; }
.hdr-btn:hover { background: var(--x-hover); transform: scale(1.05); }
.hdr-btn:active { transform: scale(0.92); }
.notif-badge { position: absolute; top: 10px; right: 12px; width: 10px; height: 10px; background: #f91880; border-radius: 50%; border: 2px solid var(--x-bg); pointer-events: none; display: none; }
.notif-badge.active { display: block; box-shadow: 0 0 8px rgba(249, 24, 128, 0.6); }
.dark .notif-badge { border-color: #000; }
.live-notif-popup { display: none; align-items: center; gap: 10px; position: absolute; top: 55px; left: 0; right: auto; background: rgba(29, 155, 240, 0.95); backdrop-filter: blur(10px); color: #fff; padding: 12px 20px; border-radius: 16px; font-size: 14px; font-weight: bold; box-shadow: 0 8px 24px rgba(29,155,240,0.4); white-space: nowrap; z-index: 100; cursor: pointer; opacity: 0; transform: translateY(15px) scale(0.9); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); border: 1px solid rgba(255,255,255,0.2); }
.live-notif-popup.show { display: flex; opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
.dark .live-notif-popup { background: rgba(30, 30, 30, 0.95); border-color: rgba(255,255,255,0.1); box-shadow: 0 8px 24px rgba(0,0,0,0.5); }
.live-notif-popup img { width:32px; height:32px; border-radius:50%; object-fit:cover; border:2px solid #fff; }
.av-col{margin-left:12px;flex-shrink:0}
.av{width:48px;height:48px;border-radius:50%;object-fit:cover;background:var(--x-border);display:flex;align-items:center;justify-content:center;font-weight:bold;color:var(--x-gray)}
.tw-c{flex:1;min-width:0;} 
.u-n{display:flex;flex-direction:column;gap:3px;font-size:15px;line-height:1.3; word-break:break-word;}
.u-n-line1{display:flex;align-items:center;flex-wrap:wrap;gap:2px;}
.u-n-line2{display:flex;align-items:center;flex-wrap:wrap;gap:6px;}
.u-n b{color:var(--x-black);white-space:nowrap;} 
.u-n span{color:var(--x-gray);font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;}
.job-badge { font-size: 11px; padding: 2px 8px; border-radius: 6px; color: var(--x-gray); background: var(--x-hover); border: 1px solid var(--x-border); font-weight: normal; }
.nav-m{display:none;position:fixed;bottom:0;width:100%;background:var(--x-bg-trans);backdrop-filter:blur(12px);border-top:1px solid var(--x-border);justify-content:space-between;padding:0;z-index:100}
.nav-m a{flex:1;display:flex;justify-content:center;padding:14px 0;transition:0.2s; color:var(--x-black);}
.fab{ display:flex; position:fixed; bottom:50px; left:30px; width:64px; height:64px; background: rgba(29, 155, 240, 0.6); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); border: 1px solid rgba(255,255,255,0.3); border-radius: 30%; color: #fff;  z-index:99; align-items:center; justify-content:center; transition: all 0.3s ease; cursor:pointer;}
.fab:hover { transform:scale(1.08) translateY(-4px); background: rgba(29, 155, 240, 0.85); box-shadow: 0 14px 40px rgba(29, 155, 240, 0.5); }
.pagination { display: none; }

@keyframes p{0%{transform:translateY(30px) scale(0.9);opacity:0}100%{transform:translateY(0) scale(1);opacity:1}}
@media(max-width:1050px){ .left-side{display:none;} }
@media(max-width:600px){
    .side{display:none;} .nav-m{display:flex;} .main{border:none; padding-bottom:90px;}
    .fab { bottom: 95px; left: 20px; width:56px; height:56px; } 
}
.twx-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 16px; opacity: 0; transition: opacity 0.2s ease; will-change: opacity; }
.twx-modal-overlay.active { display: flex; opacity: 1; }
.twx-modal-box { background: var(--x-bg); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); transform: scale(0.95); transition: transform 0.2s ease; display: flex; flex-direction: column; overflow: hidden; position: relative; width: 100%; max-width: 500px; will-change: transform; }
.dark .twx-modal-box { box-shadow: 0 8px 32px rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
.twx-modal-overlay.active .twx-modal-box { transform: scale(1); }
.twx-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--x-border); }
.twx-title { font-size: 17px; font-weight: 800; color: var(--x-black); flex: 1; text-align: center; margin-right: -36px; }
.twx-close { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--x-black); cursor: pointer; transition: 0.2s; user-select: none; z-index: 2; border:none; background:transparent;}
.twx-close:hover { background: var(--x-hover); }
.twx-body { padding: 16px; }
.twx-textarea { width: 100%; min-height: 140px; border: none; background: transparent; color: var(--x-black); font-size: 16px; resize: none; outline: none; font-family: inherit; line-height: 1.6; }
.twx-textarea::placeholder { color: var(--x-gray); }
.twx-footer { display: flex; justify-content: space-between; align-items: center; padding: 0 16px 16px; }
.twx-counter { font-size: 14px; color: var(--x-gray); font-family: Consolas, monospace; font-weight: 500; transition: color 0.2s; }
.twx-counter.limit { color: #f91880; font-weight: bold; }
.twx-btn-save { background: var(--x-black); color: var(--x-bg); border: none; padding: 8px 24px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; }
.dark .twx-btn-save { background: #fff; color: #000; }
.twx-btn-save:hover { opacity: 0.8; }
.twx-btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
@media (max-width: 480px) {
    .twx-modal-box { position: absolute; bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0; transform: translateY(100%); scale: 1; }
    .twx-modal-overlay.active .twx-modal-box { transform: translateY(0); scale: 1; }
}
.scroll-loader { text-align: center; padding: 20px; color: var(--x-gray); display: none; }


.explore-grid-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; padding: 2px; }
.exp-grid-item { position: relative; aspect-ratio: 1/1; background: var(--x-hover); display: block; overflow: hidden; cursor: pointer; }
.exp-grid-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; display: block; }
.exp-grid-item:hover img { transform: scale(1.05); }
.exp-grid-item::after { content: ''; position: absolute; inset: 0; background: rgba(0,0,0,0.1); opacity: 0; transition: opacity 0.2s; }
.exp-grid-item:hover::after { opacity: 1; }
</style>
</head>
<body>

<div class="app">
    <main class="main">
	    <?php include 'header.php'; ?>

        <div class="hdr" style="padding:0; display:flex; flex-direction:column; align-items:stretch;">
		
            <?php if (empty($search_query)): ?>
                <div class="hdr-top-row">
                    <div class="hdr-title">دیوار</div>
                    <div class="hdr-actions">
                        <a href="home_rate.php" class="hdr-btn" title="رده‌بندی">
                            <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: currentColor;">
                                <path d="M16 11V3H8v6H2v12h20V11h-6zM10 5h4v14h-4V5zM4 11h4v8H4v-8zm16 8h-4v-6h4v6z"/>
                            </svg>
                        </a>

                        <a href="notif.php" class="hdr-btn" title="اعلانات" id="hdrNotifBtn">
                            <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: currentColor;">
                                <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6.605-6.515L17.28 14H6.72l-1.325 1.485c-.43.48-.68 1.105-.68 1.765V18h14.56v-.75c0-.66-.25-1.285-.68-1.765zM12 2C8.14 2 5 5.14 5 9v3.75l-1.68 1.895A3.5 3.5 0 0 0 2.5 17.25V18a2 2 0 0 0 2 2h15a2 2 0 0 0 2-2v-.75a3.5 3.5 0 0 0-.82-2.605L19 12.75V9c0-3.86-3.14-7-7-7z"/>
                            </svg>
                            <span class="notif-badge <?= $has_new_notif ? 'active' : '' ?>" id="liveNotifBadge"></span>
                        </a>
                        <div id="liveNotifPopup" class="live-notif-popup">
                            <img src="" id="notifPopImg" style="display:none;">
                            <span id="notifPopText">اعلانات جدید دارید!</span>
                        </div>
                    </div>
                </div>

                <div style="display:flex; width:100%;">
                    <div style="flex:1; display:flex; justify-content:center; align-items:center; gap:6px; position:relative; cursor:pointer;" onclick="location.href='?tab=global'">
                        <span style="padding:12px 0 16px; font-size:14px; font-weight:bold; transition:0.2s; color:<?=$tab==='global'?'var(--x-black)':'var(--x-gray)'?>;">دیوار جهانی</span>
                        <button type="button" class="hdr-btn" style="width:28px; height:28px; margin-top:-4px;" onclick="openTwxInfoModal(event)" title="راهنمای صفحه">
                            <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; fill: currentColor;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                        </button>
                        <?php if($tab === 'global'): ?><div style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:40px; height:4px; background:var(--x-blue); border-radius:4px;"></div><?php endif; ?>
                    </div>

                    <a href="?tab=explore" style="flex:1; text-align:center; padding:12px 0 16px; font-size:14px; font-weight:bold; position:relative; transition:0.2s; color:<?=$tab==='explore'?'var(--x-black)':'var(--x-gray)'?>;">اکسپلور<?php if($tab === 'explore'): ?><div style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:56px; height:4px; background:var(--x-blue); border-radius:4px;"></div><?php endif; ?></a>
                </div>
            <?php else: ?>
                <div style="padding:16px 20px; font-size:20px; font-weight:900;">نتایج جستجو</div>
            <?php endif; ?>
        </div>

        <div id="tweets-container">
            <?php if ($tab === 'explore' && empty($search_query)): ?>
                <div class="explore-grid-container" id="tw-wrapper-content">
                    <?php if(empty($tweets)): ?>
                        <div style="text-align:center; padding:80px 20px; color:var(--x-gray); font-size:16px; font-weight:bold; grid-column: 1 / -1;">عکسی برای نمایش وجود ندارد.</div>
                    <?php else: ?>
                        <?php foreach($tweets as $t): ?>
                            <a href="view_tweet.php?id=<?=$t['id']?>" class="exp-grid-item"><img src="<?=htmlspecialchars($t['image'], ENT_QUOTES, 'UTF-8')?>" loading="lazy" alt="explore"></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="tw-wrapper" id="tw-wrapper-content">
                <?php if (!empty($search_query)): ?>
                    <?php if (empty($search_results)): ?>
                        <div style="text-align:center; padding:60px 20px; color:var(--x-gray); font-size:15px;">هیچ کاربری با این نام یافت نشد.</div>
                    <?php else: foreach ($search_results as $u): ?>
                        <div class="glass-card" onclick="location.href='profile.php?id=<?=(int)$u['id']?>'" style="align-items:center;">
                            <div class="av-col" style="margin-right:0;">
                                <?php if(!empty($u['avatar'])): ?><img src="<?=htmlspecialchars($u['avatar'], ENT_QUOTES, 'UTF-8')?>" class="av" loading="lazy">
                                <?php else: ?><img src="https://ui-avatars.com/api/?name=<?=urlencode($u['name'])?>&background=random&color=fff&bold=true" class="av" loading="lazy"><?php endif; ?>
                            </div>
                            <div class="tw-c" style="flex:1;">
                                <div class="u-n">
                                    <div class="u-n-line1">
                                        <b><?=htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8')?></b><?=$u['is_verified']?$blue_tick:''?>
                                        <span class="lvl-badge <?=getLvlStyle($u['level'] ?? 1)?>"><?=(int)($u['level'] ?? 1)?></span>
                                    </div>
                                    <div class="u-n-line2">
                                        <?php if(!empty($u['job'])): ?><span class="job-badge"><?=htmlspecialchars($u['job'], ENT_QUOTES, 'UTF-8')?></span><?php endif; ?>
                                        <span>@<?=htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8')?></span>
                                    </div>
                                </div>
                                <?php if(!empty($u['bio'])): ?><div style="font-size:15px; color:var(--x-black); margin-top:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?=htmlspecialchars($u['bio'], ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                <?php else: ?>
                    <?php if(empty($tweets)): ?>
                        <div style="text-align:center; padding:80px 20px; color:var(--x-gray); font-size:16px; font-weight:bold;">هیچ پستی برای نمایش وجود ندارد.</div>
                    <?php endif; ?>
                    
                    <?php 
                    foreach($tweets as $t) {
                        render_tweet_box($t, $tab, $is_logged, $uid, $user_role, []);
                    }
                    ?>
                <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if(empty($search_query) && $total_pages > 1): ?>
                <div class="scroll-loader" id="scrollLoader">در حال بارگذاری...</div>
            <?php endif; ?>
        </div>
    </main>
</div>

<button class="fab" id="fabNewMsg" onclick="twxOpenAddModal(event)">
    <svg viewBox="0 0 24 24" style="width: 28px; height: 28px; fill: currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
</button>


<div id="twx-info-modal" class="twx-modal-overlay" onclick="closeTwxInfoModal()">
    <div class="twx-modal-box" onclick="event.stopPropagation()">
        <div class="twx-header">
            <button type="button" class="twx-close" onclick="closeTwxInfoModal()">×</button>
            <div class="twx-title">چطور امتیاز بگیریم؟</div>
            <div style="width:36px;"></div>
        </div>
        <div class="twx-body info-modal-content" style="line-height: 1.8; font-size:14px; max-height: 60vh; overflow-y: auto;">
            <?php 
            $greeting_name = $is_logged ? htmlspecialchars($current_user_name, ENT_QUOTES, 'UTF-8') : 'کاربر'; 
            require_once 'gamification.php';
            ?>
            <b style="font-size: 16px;"><?=$greeting_name?> جان، خوش آمدی</b>
            سیستم امتیازگیری آتوکس برای پاداش به فعالیت‌های مفید طراحی شده است .<br>

            <div style="background:var(--x-hover); padding:10px; border-radius:12px; margin-bottom:15px;">
                <b>نحوه کسب امتیاز:</b><br>
                • ارسال پست: <b style="color:#00ba7c">+۱ امتیاز</b><br>
                • حذف پست: <b style="color:#f91880">-۱ امتیاز</b><br>
                • دریافت هر ۱۰ لایک در مجموع پست ها: <b style="color:#00ba7c">+۱ امتیاز</b><br>
                • لایک گرفتن روی جزوه ارسالی: <b style="color:#00ba7c">+۱ امتیاز</b><br>
                • دیسلایک گرفتن روی جزوه: <b style="color:#f91880">-۱ امتیاز</b><br>
                • هر ۱۰ بازدید: <b style="color:#00ba7c">+۱ امتیاز</b><br>
                <i>(هر ۵ امتیاز = ۱ سطح بالاتر)</i>
            </div>

			<b>قابلیت‌های بازشونده :</b>
            • سطح ۱۰: امکان ثبت پروژه و ساخت رزومه<br>
            • سطح ۱۵: ارسال پست همراه با عکس<br>
            • سطح ۲۰: انتشار مقاله بلاگ<br>
            • سطح ۳۰: دریافت تیک آبی <?=$blue_tick?>
            <span style="font-size:12px; color:var(--x-gray);">* شرط تیک آبی: حداقل سطح ۳۰ + داشتن ۳ پروژه + ۱۰ مقاله ثبت شده.</span>
            <b>معرفی نشان‌ها بر اساس سطح (تغییر هر ۵ سطح):</b>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px;">
                <?php for($i=0; $i<=9; $i++): $d=getLvlData($i*5); ?>
                <div style="display:flex;align-items:center;gap:8px; background:var(--x-hover); padding:6px 10px; border-radius:8px;">
                    <span style="font-size:12px; width:45px; opacity:.7; font-family:monospace;">سطح <?=pNum($i*5)?></span>
                    <span><?=$d['i']?></span>
                    <span style="font-size:13px; font-weight:bold; color:<?=$d['c']?>"><?=$d['n']?></span>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="twx-footer" style="justify-content: center; padding-top:15px;">
            <button type="button" class="twx-btn-save" style="width: 100%;" onclick="closeTwxInfoModal()">متوجه شدم</button>
        </div>
    </div>
</div>
<?php include_once 'twx_modals.php'; ?>

<script>
const tgM=i=>{let e=document.getElementById(i);e.style.display='none'};
const oM=i=>document.getElementById(i).style.display='flex';
const tglMenu=(id, ev)=>{ ev.stopPropagation(); document.querySelectorAll('.menu-wrap').forEach(el => { if(el.id !== id) el.classList.remove('active'); }); document.getElementById(id).classList.toggle('active'); };

window.onclick=e=>{ if(e.target.classList.contains('mod')) e.target.style.display='none'; if(!e.target.closest('.menu-wrap')) document.querySelectorAll('.menu-wrap').forEach(el => el.classList.remove('active')); };

async function ajaxLike(e, form) {
    e.preventDefault();
    <?php if(!$is_logged): ?> oM('lM'); return false; <?php endif; ?>
    
    const btn = form.querySelector('.like');
    const icon = form.querySelector('.like-icon');
    const span = form.querySelector('.like-count');
    
    const wasLiked = btn.classList.contains('liked');
    btn.classList.toggle('liked');
    icon.innerHTML = !wasLiked ? '<?=$ic_liked?>' : '<?=$ic_like?>';
    let currentCount = parseInt(span.innerText.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))) || 0;
    span.innerText = !wasLiked ? (currentCount+1).toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) : (currentCount > 1 ? (currentCount-1).toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) : '');

    const fd = new FormData(form);
    fd.append('ajax_like', '1');
    fd.append('csrf_token', '<?=$_SESSION['csrf_token'] ?? ''?>');
    try {
        const res = await fetch(location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.liked) { btn.classList.add('liked'); icon.innerHTML = '<?=$ic_liked?>'; } 
        else { btn.classList.remove('liked'); icon.innerHTML = '<?=$ic_like?>'; }
        span.innerText = data.count > 0 ? data.count.toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) : '';
    } catch (err) { console.error(err); }
}

function openTwxInfoModal(event) {
    if(event) { event.preventDefault(); event.stopPropagation(); }
    const modal = document.getElementById('twx-info-modal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}
function closeTwxInfoModal() {
    const modal = document.getElementById('twx-info-modal');
    modal.classList.remove('active');
    setTimeout(() => modal.style.display = 'none', 200);
}

document.addEventListener('change', function(e) {
    if (e.target && e.target.type === 'file' && e.target.closest('.twx-modal-box')) {
        const file = e.target.files[0];
        const modal = e.target.closest('.twx-modal-box');
        let previewImg = modal.querySelector('img.img-preview, img[id*="Preview"]');
        let previewContainer = modal.querySelector('.preview-container, div[id*="preview"]');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                if (previewImg) {
                    previewImg.src = event.target.result;
                    if(previewContainer) previewContainer.style.display = 'block';
                    previewImg.style.display = 'block';
                }
            }
            reader.readAsDataURL(file);
        }
    }
});

let currentPage = <?=(int)$page?>;
let totalPages = <?=(int)$total_pages?>;
let isLoadingMore = false;
let currentTab = '<?=$tab?>';

window.addEventListener('scroll', () => {
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
        loadMoreTweets();
    }
});

async function loadMoreTweets() {
    if (isLoadingMore || currentPage >= totalPages) return;
    isLoadingMore = true;
    currentPage++;
    
    const loader = document.getElementById('scrollLoader');
    if(loader) loader.style.display = 'block';

    try {
        const res = await fetch(`?tab=${encodeURIComponent(currentTab)}&p=${currentPage}&ajax_scroll=1`);
        const html = await res.text();
        
        if (html.trim() !== '' && html.trim() !== 'EOF') {
            document.getElementById('tw-wrapper-content').insertAdjacentHTML('beforeend', html);
        }
    } catch (e) {
        currentPage--;
    }
    
    if(loader) loader.style.display = 'none';
    isLoadingMore = false;
}

<?php if($is_logged): ?>
document.getElementById('hdrNotifBtn').addEventListener('click', () => { fetch('?mark_notifs_read=1'); });

document.getElementById('liveNotifPopup').onclick = function() {
    fetch('?mark_notifs_read=1').then(() => {
        location.href = 'notif.php';
    });
};

setInterval(async () => {
    try {
        const res = await fetch('?check_live_notifs=1');
        const data = await res.json();
        
        if (data.count > 0) {
            document.getElementById('liveNotifBadge').classList.add('active');
            const popup = document.getElementById('liveNotifPopup');
            const popText = document.getElementById('notifPopText');
            const popImg = document.getElementById('notifPopImg');
            
            let summary = [];
            if(data.follows > 0) summary.push(data.follows + ' فالوور جدید');
            if(data.likes > 0) summary.push(data.likes + ' لایک');
            if(data.comments > 0) summary.push(data.comments + ' کامنت');
            let msg = summary.join('، ');

            if(data.latest) {
                let actDesc = 'پست شما را لایک کرد';
                if(data.latest.type === 'follow') actDesc = 'شما را دنبال کرد';
                if(data.latest.type === 'comment') actDesc = 'برای شما کامنت گذاشت';

                popText.innerHTML = `<div style="display:flex;flex-direction:column;gap:4px;">
                    <span style="font-size:12px;opacity:0.9;">${msg}</span>
                    <span><b>${data.latest.name}</b> ${actDesc}</span>
                </div>`;

                if(data.latest.avatar) {
                    popImg.src = data.latest.avatar;
                    popImg.style.display = 'block';
                } else {
                    popImg.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(data.latest.name)}&background=random&color=fff`;
                    popImg.style.display = 'block';
                }
            } else {
                popText.textContent = msg;
                popImg.style.display = 'none';
            }
            
            popup.classList.add('show');
            setTimeout(() => { popup.classList.remove('show'); }, 6000);
        } else {
            document.getElementById('liveNotifBadge').classList.remove('active');
        }
    } catch (e) {}
}, 10000); 
<?php endif; ?>
</script>

<?php if(file_exists('footer.php')) include 'footer.php'; ?>

</body>
</html>
<?php ob_end_flush(); ?>
