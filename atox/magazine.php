<?php
ob_start();
session_start();
require 'db.php';
require_once 'gamification.php';


$j_id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$uid = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'user';
$is_admin = ($user_role === 'admin' || (isset($_SESSION['username']) && $_SESSION['username'] === 'milad'));

if (!$j_id) die('شناسه نامعتبر.');

$check_owner = $pdo->prepare("SELECT user_id FROM jozves WHERE id = ?");
$check_owner->execute([$j_id]);
$owner_id = (int)$check_owner->fetchColumn();

$can_edit = ($is_admin || ($uid > 0 && $uid === $owner_id));


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit && isset($_POST['action'])) {
    $action_type = $_POST['action'];
    
    if ($action_type === 'edit_jozve') {
        $desc = trim($_POST['description'] ?? '');
        $link = trim($_POST['file_link'] ?? '');
        
        $language = trim($_POST['language'] ?? '');
        $university_name = trim($_POST['university_name'] ?? '');
        $is_handwritten = isset($_POST['is_handwritten']) ? 1 : 0;
        $author_name = trim($_POST['author_name'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE jozves SET description = ?, file_link = ?, language = ?, university_name = ?, is_handwritten = ?, author_name = ? WHERE id = ?");
        $stmt->execute([$desc, $link, $language, $university_name, $is_handwritten, $author_name, $j_id]);
        
        header("Location: magazine.php?id=" . $j_id);
        exit;
    }
    
    if ($action_type === 'delete_jozve') {
        $group_id = (int)$_POST['group_id'];
        
        $pdo->prepare("DELETE FROM jozve_likes WHERE jozve_id = ?")->execute([$j_id]);
        
        $stmt = $pdo->prepare("DELETE FROM jozves WHERE id = ?");
        $stmt->execute([$j_id]);
        
        header("Location: jozveGroup.php?id=" . $group_id);
        exit;
    }
}


if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    if (!$uid) exit(json_encode(['status' => 'redirect', 'url' => 'auth.php']));

    $action = $_POST['ajax_action'];

    if ($action === 'like' || $action === 'dislike') {
        $stmt = $pdo->prepare("SELECT type FROM jozve_likes WHERE jozve_id = ? AND user_id = ?");
        $stmt->execute([$j_id, $uid]);
        $existing = $stmt->fetchColumn();

        $u_points_diff = 0;
        $new_status = '';
        $current = '';

        if ($existing === $action) {
            $pdo->prepare("DELETE FROM jozve_likes WHERE jozve_id = ? AND user_id = ?")->execute([$j_id, $uid]);
            if ($action === 'like') $u_points_diff = -1; 
            else $u_points_diff = 1; 
            $new_status = 'removed';
        } elseif ($existing) {
            $pdo->prepare("UPDATE jozve_likes SET type = ? WHERE jozve_id = ? AND user_id = ?")->execute([$action, $j_id, $uid]);
            if ($action === 'like') $u_points_diff = 3; 
            else $u_points_diff = -2; 
            $new_status = 'added';
            $current = $action;
        } else {
            $pdo->prepare("INSERT INTO jozve_likes (jozve_id, user_id, type) VALUES (?, ?, ?)")->execute([$j_id, $uid, $action]);
            if ($action === 'like') $u_points_diff = 2;
            else $u_points_diff = -1;
            $new_status = 'added';
            $current = $action;
        }

        if (isset($owner_id) && $owner_id > 0 && $owner_id !== $uid && $u_points_diff != 0) {
            apply_jozve_like_gamification($pdo, $owner_id, $u_points_diff);
        }

        $likes_count = $pdo->query("SELECT COUNT(*) FROM jozve_likes WHERE jozve_id = $j_id AND type = 'like'")->fetchColumn();
        $dislikes_count = $pdo->query("SELECT COUNT(*) FROM jozve_likes WHERE jozve_id = $j_id AND type = 'dislike'")->fetchColumn();

        exit(json_encode([
            'status' => $new_status, 
            'current' => $current, 
            'likes' => $likes_count, 
            'dislikes' => $dislikes_count
        ]));
    }

    if ($action === 'download') {
        $dl_key = "dl_j_{$j_id}_u_{$uid}";
        if (!isset($_SESSION[$dl_key])) {
            $_SESSION[$dl_key] = true;
        }
        exit(json_encode(['status' => 'ok']));
    }
}

$view_cookie_name = "view_j_{$j_id}_u_" . ($uid ?: 'guest');
if (!isset($_COOKIE[$view_cookie_name])) {
    $pdo->prepare("UPDATE jozves SET views = views + 1 WHERE id = ?")->execute([$j_id]);
    setcookie($view_cookie_name, "1", time() + (3 * 3600), "/"); 
    
    $views = $pdo->query("SELECT views FROM jozves WHERE id = $j_id")->fetchColumn();
    
    if (isset($owner_id) && $owner_id > 0 && $owner_id !== $uid) {
        apply_jozve_view_gamification($pdo, $owner_id, $views);
    }
}

$stmt = $pdo->prepare("
    SELECT j.*, 
           g.name as course_name, g.term as course_term, 
           k.name as kanoon_name,
           u.name as u_name, u.username as u_username, u.avatar as u_avatar, u.is_verified, u.level, u.points,
           (SELECT COUNT(*) FROM jozve_likes jl WHERE jl.jozve_id = j.id AND jl.type = 'like') as likes_count,
           (SELECT COUNT(*) FROM jozve_likes jl WHERE jl.jozve_id = j.id AND jl.type = 'dislike') as dislikes_count,
           (SELECT type FROM jozve_likes jl WHERE jl.jozve_id = j.id AND jl.user_id = ?) as user_reaction
    FROM jozves j 
    JOIN jozve_groups g ON j.group_id = g.id 
    JOIN kanoons k ON g.kanoon_id = k.id 
    LEFT JOIN users u ON j.user_id = u.id
    WHERE j.id = ?
");
$stmt->execute([$uid, $j_id]);
$jozve = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$jozve) die('جزوه یافت نشد.');

$my_react = $jozve['user_reaction'] ?? '';

function pNum($str) { return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str); }
function formatK($num) { return $num >= 1000 ? pNum(round($num/1000,1)).'K' : pNum($num); }

function toJalali($date_string) {
    if(empty($date_string)) return '';
    $timestamp = strtotime($date_string);
    $diff = time() - $timestamp;
    if($diff < 60) return 'همین الان';
    if($diff < 3600) return pNum(floor($diff / 60)) . ' دقیقه پیش';
    if($diff < 86400) return pNum(floor($diff / 3600)) . ' ساعت پیش';
    if($diff < 604800) return pNum(floor($diff / 86400)) . ' روز پیش';
    return pNum(date('Y/m/d', $timestamp));
}


$ic_back = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"></path></svg>';
$ic_like = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';
$ic_dislike = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/></svg>';
$ic_dl = '<svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>';
$ic_verify = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg>';
$ic_edit = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
$ic_trash = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
$ic_author = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
$ic_uni = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99v-2.08l-5 2.73-5-2.73v2.08l5 2.73 5-2.73z"/></svg>';
$ic_lang = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm6.93 6h-2.95c-.32-1.25-.78-2.45-1.38-3.56 1.84.63 3.37 1.91 4.33 3.56zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14C4.09 13.36 4 12.69 4 12s.09-1.36.26-2h3.38c-.08.66-.14 1.32-.14 2s.06 1.34.14 2H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56-1.84-.63-3.37-1.91-4.33-3.56zm2.95-8H5.08c1.96-1.66 3.49-2.93 5.33-3.56C9.81 5.55 9.35 6.75 9.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66c-.09-.66-.16-1.32-.16-2s.07-1.34.16-2h4.68c.09.66.16 1.32.16 2s-.07 1.34-.16 2zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95c-.96 1.65-2.49 2.93-4.33 3.56zM16.36 14c.08-.66.14-1.32.14-2s-.06-1.34-.14-2h3.38c.17.64.26 1.31.26 2s-.09 1.36-.26 2h-3.38z"/></svg>';
$ic_pen = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
$ic_eye = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
$ic_layer = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?=htmlspecialchars($jozve['title'] ?? 'جزوه '.$jozve['course_name'])?> - آتوکس</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { 
    --x-blue: #1d9bf0; 
    --x-black: #0f1419; 
    --x-gray: #536471; 
    --x-border: #eff3f4; 
    --x-bg: #ffffff; 
    --x-body: #f7f9f9; 
    --x-hover: rgba(15,20,25,0.08); 
    --x-modal: rgba(0,0,0,0.4); 
    --glass-bg: rgba(255, 255, 255, 0.85);
}
.dark { 
    --x-black: #e7e9ea; 
    --x-gray: #71767b; 
    --x-border: #2f3336; 
    --x-bg: #000000; 
    --x-body: #000000; 
    --x-hover: rgba(255,255,255,0.08); 
    --x-modal: rgba(255,255,255,0.1); 
    --glass-bg: rgba(22, 24, 28, 0.8);
    --glass-bg2: #000000 ;
}

*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,sans-serif;}
body{background:var(--x-body);color:var(--x-black);overflow-y:scroll;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}

.app{display:flex;justify-content:center;min-height:100vh;}
.main{width:100%;max-width:600px;position:relative;z-index:1;padding-bottom:120px;}

.hdr { position:sticky; top:0; z-index:10; background:var(--glass-bg2); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border-bottom:1px solid var(--x-border); display:flex; align-items:center; padding:12px 16px; gap:15px; }
.vt-back { width:36px; height:36px; border-radius:50%; display:flex; justify-content:center; align-items:center; transition:0.2s; cursor:pointer;}
.vt-back:hover { background:var(--x-hover); }
.hdr-title { font-size:18px; font-weight:800; display:flex; flex-direction:column; }
.hdr-sub { font-size:13px; color:var(--x-gray); font-weight:normal; }

.glass-box {
    background: var(--glass-bg);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--x-border);
    border-radius: 16px;
    margin: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    overflow: hidden;
}
.dark .glass-box { box-shadow: 0 4px 20px rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); }


.box-header { padding: 16px; display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px dashed var(--x-border); background: var(--x-body);}
.stats-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.badge-ui { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: bold; background: var(--x-bg); padding: 6px 12px; border-radius: 99px; border: 1px solid var(--x-border); color: var(--x-gray); }
.badge-ui.term { color: var(--x-blue); background: rgba(29,155,240,0.1); border: none;}

.admin-actions { display: flex; gap: 6px; }
.btn-ico { width: 32px; height: 32px; display: flex; justify-content: center; align-items: center; border-radius: 50%; background: var(--x-bg); border: 1px solid var(--x-border); transition: 0.2s; color: var(--x-gray); }
.btn-ico:hover { background: var(--x-hover); }
.btn-ico.del:hover { color: #f91880; border-color: rgba(249,24,128,0.5); }

.c-box { padding: 20px 16px; }
.c-title { font-size: 20px; font-weight: 800; margin-bottom: 8px; line-height: 1.4; color: var(--x-black); }
.date-ui { font-size: 13px; color: var(--x-gray); margin-bottom: 16px; display: block; font-weight: bold;}
.c-desc { font-size: 15px; line-height: 1.6; white-space: pre-wrap; margin-bottom: 16px; color: var(--x-black); }

.props-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 16px; background: var(--x-hover); padding: 14px; border-radius: 12px; border: 1px solid var(--x-border); }
.prop-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--x-gray); }
.prop-item svg { fill: var(--x-blue); flex-shrink: 0; }
.prop-item b { color: var(--x-black); font-weight: bold; }

.pub-card { display:flex; align-items:center; gap:12px; padding:16px; border-top:1px solid var(--x-border); transition:0.2s; background: var(--x-body);}
.pub-card:hover { background:var(--x-hover); cursor:pointer;}
.pub-avatar { width:48px; height:48px; border-radius:50%; object-fit:cover; background:var(--x-border); }
.pub-info { flex:1; }
.pub-name { font-weight:bold; font-size:15px; display:flex; align-items:center; gap:4px; }
.pub-user { color:var(--x-gray); font-size:14px; margin-top:2px; }
.pub-badge { font-size:11px; background:rgba(29,155,240,0.1); color:var(--x-blue); padding:2px 8px; border-radius:12px; font-weight:bold; }
.pub-role { font-size:12px; color:var(--x-gray); background:var(--x-bg); padding:4px 10px; border-radius:12px; font-weight:bold; border:1px solid var(--x-border);}

.act-row { display:flex; justify-content:space-between; align-items:center; padding:16px; border-top:1px solid var(--x-border); gap: 12px;}
.btn-dl { 
    flex:1; display:flex; justify-content:center; align-items:center; gap:8px; 
    background:var(--x-blue); color:#fff; padding:12px; border-radius:99px; 
    font-weight:bold; font-size:15px; transition:0.2s; 
}
.btn-dl:hover { background: #1a8cd8; }

.react-group { display:flex; align-items:center; gap:8px; }
.btn-react { display:flex; align-items:center; gap:6px; font-size:14px; font-weight:bold; color:var(--x-gray); padding:8px 14px; border-radius:99px; transition:0.2s; border:1px solid var(--x-border); background: transparent; }
.btn-react:hover { background:var(--x-hover); }
.btn-react.liked { color:#f91880; border-color:rgba(249,24,128,0.5); background:rgba(249,24,128,0.1); }
.btn-react.disliked { color:var(--x-black); border-color:var(--x-gray); background:var(--x-hover); }


.mod{display:none;position:fixed;inset:0;background:var(--x-modal);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px);}
.m-c{position:relative;background:var(--x-bg); border-radius:16px;width:90%;max-width:400px;padding:24px;box-shadow:0 15px 50px rgba(0,0,0,.2);animation:p .3s cubic-bezier(0.175, 0.885, 0.32, 1.275); max-height:90vh; overflow-y:auto;}
.dark .m-c { border:1px solid var(--x-border); }
.input-ui{width:100%;padding:14px;border:1px solid var(--x-border);border-radius:12px;font-size:15px;margin-bottom:14px;background:var(--x-body);color:var(--x-black);outline:none;box-sizing:border-box;}
.input-ui:focus{border-color:var(--x-blue); background:var(--x-bg);}
.check-ui { display: flex; align-items: center; gap: 10px; font-size: 14px; margin-bottom: 14px; cursor: pointer; color: var(--x-black); user-select: none; padding: 12px; border: 1px solid var(--x-border); border-radius: 12px; background: var(--x-hover); }
.check-ui input { width: 18px; height: 18px; accent-color: var(--x-blue); cursor: pointer; }

.btn-submit{background:var(--x-black);color:var(--x-bg);border:none;padding:12px;border-radius:99px;font-weight:bold;font-size:15px;cursor:pointer;width:100%; transition:0.2s;}
.btn-submit:hover{opacity:0.8;}
.btn-submit.danger{background:#f91880; color:#fff;}
@keyframes p{0%{transform:translateY(30px) scale(0.95);opacity:0}100%{transform:translateY(0) scale(1);opacity:1}}
</style>
</head>
<body>

<div class="app">
    <main class="main">
        <?php include 'header.php'; ?>
        
        <!-- Header -->
        <div class="hdr">
            <div class="vt-back" onclick="window.history.back()"><?=$ic_back?></div>
            <div class="hdr-title">
                جزوه <?=htmlspecialchars($jozve['course_name'])?>
                <span class="hdr-sub"><?=htmlspecialchars($jozve['kanoon_name'])?></span>
            </div>
        </div>

        <div class="glass-box">
            
            <!-- هدر باکس: ترم، بازدید، دکمه های مدیریت -->
            <div class="box-header">
                <div class="stats-badges">
                    <span class="badge-ui term"><?=$ic_layer?> ترم <?=pNum($jozve['course_term'])?></span>
                    <span class="badge-ui" id="v-count"><?=$ic_eye?> <?=formatK($jozve['views'])?> بازدید</span>
                </div>
                
                <?php if($can_edit): ?>
                <div class="admin-actions">
                    <button class="btn-ico" onclick="oM('editModal')" title="ویرایش"><?=$ic_edit?></button>
                    <button class="btn-ico del" onclick="oM('delModal')" title="حذف"><?=$ic_trash?></button>
                </div>
                <?php endif; ?>
            </div>

            <!-- 1. Content Area -->
            <div class="c-box">
                <h1 class="c-title"><?=htmlspecialchars($jozve['title'] ?: 'جزوه درس '.$jozve['course_name'])?></h1>
                <span class="date-ui"><?=toJalali($jozve['created_at'])?></span>

                <!-- مشخصات اضافه شده (زبان، نویسنده، دانشگاه، دست‌نویس) -->
                <?php if(!empty($jozve['author_name']) || !empty($jozve['university_name']) || !empty($jozve['language']) || $jozve['is_handwritten']): ?>
                <div class="props-grid">
                    <?php if(!empty($jozve['author_name'])): ?>
                    <div class="prop-item"><?=$ic_author?> <span>نویسنده: <b><?=htmlspecialchars($jozve['author_name'])?></b></span></div>
                    <?php endif; ?>
                    <?php if(!empty($jozve['university_name'])): ?>
                    <div class="prop-item"><?=$ic_uni?> <span>دانشگاه: <b><?=htmlspecialchars($jozve['university_name'])?></b></span></div>
                    <?php endif; ?>
                    <?php if(!empty($jozve['language'])): ?>
                    <div class="prop-item"><?=$ic_lang?> <span>زبان: <b><?=htmlspecialchars($jozve['language'])?></b></span></div>
                    <?php endif; ?>
                    <div class="prop-item"><?=$ic_pen?> <span>فرمت: <b><?=$jozve['is_handwritten'] ? 'دست‌نویس' : 'تایپ شده'?></b></span></div>
                </div>
                <?php endif; ?>

                <p class="c-desc"><?=htmlspecialchars($jozve['description'] ?: '')?></p>
            </div>

            <!-- 2. Publisher Profile Card -->
            <?php if($jozve['u_name']): ?>
            <div class="pub-card" onclick="location.href='profile.php?username=<?=$jozve['u_username']?>'">
                <img src="<?=htmlspecialchars($jozve['u_avatar'] ?: 'default_avatar.jpg')?>" class="pub-avatar">
                <div class="pub-info">
                    <div class="pub-name">
                        <?=htmlspecialchars($jozve['u_name'])?> 
                        <?php if($jozve['is_verified']) echo $ic_verify; ?>
                        <span class="pub-badge">سطح <?=pNum($jozve['level'])?></span>
                    </div>
                    <div class="pub-user">@<?=htmlspecialchars($jozve['u_username'])?></div>
                </div>
                <div class="pub-role">منتشر کننده</div>
            </div>
            <?php else: ?>
            <div class="pub-card">
                <div class="pub-avatar" style="display:flex;align-items:center;justify-content:center;background:var(--x-black);color:var(--x-bg);font-weight:bold;">M</div>
                <div class="pub-info">
                    <div class="pub-name">مدیریت سایت <?=$ic_verify?></div>
                    <div class="pub-user">@admin</div>
                </div>
                <div class="pub-role">مدیر کل</div>
            </div>
            <?php endif; ?>

            <!-- 3. Action Bar -->
            <div class="act-row">
                <a href="<?=htmlspecialchars($jozve['file_link'])?>" target="_blank" download class="btn-dl" onclick="return dlJozve(event)">
                    <?=$ic_dl?> دریافت فایل
                </a>
                
                <div class="react-group">
                    <button class="btn-react <?=($my_react=='like')?'liked':''?>" id="b-like" onclick="doReact('like')">
                        <?=$ic_like?> <span id="c-like"><?=formatK($jozve['likes_count'] ?? 0)?></span>
                    </button>
                    <button class="btn-react <?=($my_react=='dislike')?'disliked':''?>" id="b-dislike" onclick="doReact('dislike')">
                        <?=$ic_dislike?> <span id="c-dislike"><?=formatK($jozve['dislikes_count'] ?? 0)?></span>
                    </button>
                </div>
            </div>

        </div> 

        <?php if($can_edit): ?>
        <!-- Edit Modal -->
        <div id="editModal" class="mod">
            <div class="m-c">
                <h2 style="margin-bottom:20px; font-size:18px;">ویرایش جزوه</h2>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit_jozve">
                    <input type="hidden" name="id" value="<?=$j_id?>">
                    <input type="hidden" name="group_id" value="<?=$jozve['group_id']?>">
                    
                    <input type="text" name="author_name" class="input-ui" placeholder="نویسنده جزوه..." value="<?=htmlspecialchars($jozve['author_name'] ?? '')?>">
                    <input type="text" name="university_name" class="input-ui" placeholder="مربوط به کدام دانشگاه؟" value="<?=htmlspecialchars($jozve['university_name'] ?? '')?>">
                    <input type="text" name="language" class="input-ui" placeholder="زبان (مثلا: فارسی، انگلیسی)..." value="<?=htmlspecialchars($jozve['language'] ?? '')?>">
                    
                    <label class="check-ui">
                        <input type="checkbox" name="is_handwritten" value="1" <?=!empty($jozve['is_handwritten']) ? 'checked' : ''?>>
                        این جزوه کاملاً دست‌نویس است
                    </label>

                    <textarea name="description" class="input-ui" placeholder="توضیحات کلی جزوه..." style="min-height:100px;"><?=htmlspecialchars($jozve['description'] ?? '')?></textarea>
                    <input type="text" name="file_link" class="input-ui" placeholder="لینک مستقیم فایل" value="<?=htmlspecialchars($jozve['file_link'] ?? '')?>" dir="ltr" style="text-align:left;">
                    
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <button type="button" class="btn-submit" style="background:var(--x-border);color:var(--x-black);" onclick="tgM('editModal')">لغو</button>
                        <button type="submit" class="btn-submit">ذخیره</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div id="delModal" class="mod">
            <div class="m-c" style="text-align:center;">
                <h2 style="margin-bottom:10px; color:#f91880; font-size:18px;">حذف دائمی</h2>
                <p style="color:var(--x-gray); font-size:14px; margin-bottom:24px;">آیا مطمئن هستید؟ این عمل غیرقابل بازگشت است.</p>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="delete_jozve">
                    <input type="hidden" name="id" value="<?=$j_id?>">
                    <input type="hidden" name="group_id" value="<?=$jozve['group_id']?>">
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="btn-submit" style="background:var(--x-border);color:var(--x-black);" onclick="tgM('delModal')">لغو</button>
                        <button type="submit" class="btn-submit danger">حذف شود</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>


<script>
const tgM = i => document.getElementById(i).style.display = 'none';
const oM = i => document.getElementById(i).style.display = 'flex';

function checkAuth() {
    let loggedIn = <?=$uid > 0 ? 'true' : 'false'?>;
    if (!loggedIn) {
        window.location.href = 'auth.php';
        return false;
    }
    return true;
}

function req(action, callback) {
    if (!checkAuth()) return;
    let fd = new FormData();
    fd.append('ajax_action', action);
    fd.append('id', <?=$j_id?>);
    fetch('magazine.php', { method: 'POST', body: fd }).then(r => r.json()).then(callback);
}

function dlJozve(e) {
    if (!checkAuth()) {
        e.preventDefault();
        return false;
    }
    req('download', d => {
        if(d.status === 'redirect') window.location.href = d.url;
    });
    return true; 
}

function doReact(type) {
    if (!checkAuth()) return;
    
    req(type, data => {
        if(data.status === 'redirect') { window.location.href = data.url; return; }
        if(data.status === 'error') { alert(data.msg); return; }
        
        let lBtn = document.getElementById('b-like'), dBtn = document.getElementById('b-dislike');
        let lCnt = document.getElementById('c-like'), dCnt = document.getElementById('c-dislike');

        lCnt.innerText = data.likes.toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        dCnt.innerText = data.dislikes.toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);

        lBtn.classList.remove('liked'); 
        dBtn.classList.remove('disliked');
        
        if(data.current === 'like') {
            lBtn.classList.add('liked'); 
        } else if(data.current === 'dislike') {
            dBtn.classList.add('disliked');
        }
    });
}
</script>
<?php include 'footer.php'; ?>

</body>
</html>
<?php ob_end_flush(); ?>
