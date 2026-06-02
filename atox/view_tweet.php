<?php
ob_start();
header("X-XSS-Protection: 1; mode=block");
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

require 'db.php';
require_once 'tweet_box.php'; 

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$tid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tid <= 0) {
    header("Location: index.php");
    exit;
}

if (isset($_POST['ajax_like']) && $uid > 0) {
    $t_id = (int)$_POST['tweet_id'];
    if ($t_id <= 0) exit;
    
    $chk = $pdo->prepare("SELECT id FROM likes WHERE tweet_id=? AND user_id=?");
    $chk->execute([$t_id, $uid]);
    $is_liked = false;
    
    if ($chk->rowCount() > 0) {
        $pdo->prepare("DELETE FROM likes WHERE tweet_id=? AND user_id=?")->execute([$t_id, $uid]);
    } else {
        $pdo->prepare("INSERT INTO likes (tweet_id, user_id) VALUES (?, ?)")->execute([$t_id, $uid]);
        $is_liked = true;
    }
    $c = $pdo->prepare("SELECT COUNT(id) FROM likes WHERE tweet_id=?");
    $c->execute([$t_id]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['liked' => $is_liked, 'count' => (int)$c->fetchColumn()]);
    exit;
}

$view_cookie = "atx_v_" . $tid;
$has_viewed = false;
$time_now = time();
$three_hours = 10800; 

if (isset($_SESSION['viewed_tweets_time'][$tid])) {
    if ($time_now - (int)$_SESSION['viewed_tweets_time'][$tid] < $three_hours) {
        $has_viewed = true; 
    }
}
if (isset($_COOKIE[$view_cookie])) {
    $has_viewed = true; 
}

if (!$has_viewed) {
    $pdo->prepare("UPDATE tweets SET views = IFNULL(views, 0) + 1 WHERE id = ?")->execute([$tid]);
    
    if (!isset($_SESSION['viewed_tweets_time']) || !is_array($_SESSION['viewed_tweets_time'])) {
        $_SESSION['viewed_tweets_time'] = [];
    }
    $_SESSION['viewed_tweets_time'][$tid] = $time_now;
    
    setcookie($view_cookie, "1", $time_now + $three_hours, "/", "", true, true);
}

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
    return pNum(htmlspecialchars("$jd {$months[$jm - 1]} $jy / " . date('H:i', $timestamp), ENT_QUOTES, 'UTF-8'));
}

function pNum($str) { 
    return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str); 
}

function getLvlStyle($lvl) {
    $l = (int)$lvl;
    if($l <= 5) return 'lvl-w'; if($l <= 10) return 'lvl-y'; if($l <= 25) return 'lvl-p'; if($l <= 49) return 'lvl-r'; return 'lvl-g';
}

$user_role = 'user';
$is_logged = false;
$user_data = [];
$current_user_name = '';
$user_level = 0; 

if ($uid > 0) {
    $stmt = $pdo->prepare("SELECT role, username, name, avatar, level FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data) {
        $user_role = ($user_data['username'] === 'milad') ? 'admin' : ($user_data['role'] ?? 'user');
        $current_user_name = htmlspecialchars($user_data['name'], ENT_QUOTES, 'UTF-8');
        $user_level = (int)$user_data['level'];
        $is_logged = true;
    }
}

$t_stmt = $pdo->prepare("SELECT t.*, u.name, u.username, u.avatar, u.is_verified, u.level, r.job,
       (SELECT COUNT(id) FROM likes WHERE tweet_id = t.id) as lc,
       " . ($is_logged ? "(SELECT 1 FROM likes WHERE tweet_id = t.id AND user_id = ? LIMIT 1)" : "0") . " as is_liked,
       (SELECT COUNT(id) FROM tweets WHERE parent_id = t.id AND is_comment = 1) as cc
FROM tweets t JOIN users u ON t.user_id = u.id LEFT JOIN resumes r ON u.id = r.user_id 
WHERE t.id = ?");
if($is_logged) $t_stmt->execute([$uid, $tid]); else $t_stmt->execute([$tid]);
$tweet = $t_stmt->fetch(PDO::FETCH_ASSOC);

if (!$tweet) {
    die("<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>خطا</title><style>body{background:#000;color:#fff;text-align:center;padding:50px;font-family:sans-serif;}a{color:#1d9bf0;}</style></head><body>توییت یافت نشد یا حذف شده است. <br><br><a href='index.php'>بازگشت به خانه</a></body></html>");
}

$valid_sorts = ['newest', 'oldest', 'liked', 'viewed'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $valid_sorts) ? $_GET['sort'] : 'newest';

$order_by = "c.id DESC"; 
if ($sort === 'oldest') $order_by = "c.id ASC";
elseif ($sort === 'liked') $order_by = "lc DESC, c.id DESC";
elseif ($sort === 'viewed') $order_by = "c.views DESC, c.id DESC"; 

$limit = 10;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

$c_count = $pdo->prepare("SELECT COUNT(id) FROM tweets WHERE parent_id = ? AND is_comment = 1");
$c_count->execute([$tid]);
$total_pages = ceil((int)$c_count->fetchColumn() / $limit);

$c_stmt = $pdo->prepare("SELECT c.*, u.name, u.username, u.avatar, u.is_verified, u.level, r.job,
       (SELECT COUNT(id) FROM likes WHERE tweet_id = c.id) as lc,
       " . ($is_logged ? "(SELECT 1 FROM likes WHERE tweet_id = c.id AND user_id = ? LIMIT 1)" : "0") . " as is_liked,
       (SELECT COUNT(id) FROM tweets WHERE parent_id = c.id AND is_comment = 1) as cc
FROM tweets c JOIN users u ON c.user_id = u.id LEFT JOIN resumes r ON u.id = r.user_id 
WHERE c.parent_id = ? AND c.is_comment = 1 ORDER BY $order_by LIMIT $limit OFFSET $offset");
if($is_logged) $c_stmt->execute([$uid, $tid]); else $c_stmt->execute([$tid]);
$comments_list = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

global $ic_dots, $ic_del, $ic_send, $ic_edit, $ic_reply, $ic_liked, $ic_like, $blue_tick, $ic_back, $ic_reply_mod, $ic_info;
$ic_dots = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M3 12c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm9 2c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm7 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/></svg>';
$ic_del = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M15 3H9v2H4v2h16V5h-5V3zM6 9v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V9H6zm6 10H8v-8h4v8zm4 0h-2v-8h2v8z"/></svg>';
$ic_edit = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
$ic_reply = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M1.751 10c0-4.42 3.584-8 8.005-8h4.366c4.49 0 8.129 3.64 8.129 8.13 0 2.96-1.607 5.68-4.196 7.11l-8.054 4.46v-3.69h-.067c-4.49.1-8.183-3.51-8.183-8.01zm8.005-6c-3.317 0-6.005 2.69-6.005 6 0 3.37 2.77 6.08 6.138 6.01l.351-.01h1.761v2.3l5.087-2.81c1.951-1.08 3.163-3.13 3.163-5.36 0-3.39-2.744-6.13-6.129-6.13H9.756z"/></svg>';
$ic_send = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';
$ic_like = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M16.697 5.5c-1.222-.06-2.679.51-3.89 2.16l-.805 1.09-.806-1.09C9.984 6.01 8.526 5.44 7.304 5.5c-1.243.07-2.349.78-2.91 1.91-.552 1.12-.633 2.78.479 4.82 1.074 1.97 3.257 4.27 7.129 6.61 3.87-2.34 6.052-4.64 7.126-6.61 1.111-2.04 1.03-3.7.477-4.82-.561-1.13-1.666-1.84-2.908-1.91zm4.187 7.69c-1.351 2.48-4.001 5.12-8.379 7.67l-.503.3-.504-.3c-4.379-2.55-7.029-5.19-8.382-7.67-1.36-2.5-1.41-4.86-.514-6.67.887-1.79 2.647-2.91 4.601-3.01 1.651-.09 3.368.56 4.798 2.01 1.429-1.45 3.146-2.1 4.796-2.01 1.954.1 3.714 1.22 4.601 3.01.896 1.81.846 4.17-.514 6.67z"/></svg>';
$ic_liked = '<svg viewBox="0 0 24 24" class="ic-a" style="fill:#f91880"><path d="M12 21.638h-.014C9.403 21.59 1.95 14.856 1.95 8.478c0-3.064 2.525-5.754 5.403-5.754 2.29 0 3.83 1.58 4.646 2.73.814-1.148 2.354-2.73 4.645-2.73 2.88 0 5.404 2.69 5.404 5.755 0 6.376-7.454 13.11-10.037 13.157H12z"/></svg>';
$ic_info = '<svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:currentColor;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
$ic_back = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"></path></svg>';
$ic_reply_mod = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:var(--x-blue);margin-left:8px;"><path d="M1.751 10c0-4.42 3.584-8 8.005-8h4.366c4.49 0 8.129 3.64 8.129 8.13 0 2.96-1.607 5.68-4.196 7.11l-8.054 4.46v-3.69h-.067c-4.49.1-8.183-3.51-8.183-8.01zm8.005-6c-3.317 0-6.005 2.69-6.005 6 0 3.37 2.77 6.08 6.138 6.01l.351-.01h1.761v2.3l5.087-2.81c1.951-1.08 3.163-3.13 3.163-5.36 0-3.39-2.744-6.13-6.129-6.13H9.756z"/></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>آتوکس - مشاهده پست</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.85); --x-hover:rgba(15,20,25,0.05); --x-hover-r:rgba(249,24,128,0.1); --x-hover-b:rgba(29,155,240,0.1); --x-modal:rgba(0,0,0,0.5); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.85); --x-hover:rgba(255,255,255,0.05); --x-hover-r:rgba(249,24,128,0.15); --x-hover-b:rgba(29,155,240,0.15); --x-modal:rgba(255,255,255,0.1); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;overflow-y:scroll; overflow-x:hidden;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}

.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:120px; min-height:100vh; position: relative;}
.hdr{position:sticky;top:0;background:var(--x-bg-trans);backdrop-filter:blur(12px);z-index:10;border-bottom:1px solid var(--x-border)}

.vt-top-bar { display: flex; align-items: center; justify-content: space-between; padding: 0 16px; height: 53px; }
.vt-top-left { display: flex; align-items: center; gap: 20px; cursor: pointer; }
.vt-back { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; color: var(--x-black); transition: 0.2s; margin-right: -8px; }
.vt-back:hover { background: var(--x-hover); }
.vt-title { font-size: 20px; font-weight: 1000; color: var(--x-black); }
.vt-info-btn { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; color: var(--x-black); transition: 0.2s; cursor: pointer; }
.vt-info-btn:hover { background: var(--x-hover); color: var(--x-blue); }

.menu-wrap { position: relative; display: inline-block; }
.menu-btn { padding: 6px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--x-gray); cursor: pointer; transition: 0.2s; }
.menu-btn:hover { background: var(--x-hover-b); color: var(--x-blue); }
.menu-content { display: none; position: absolute; left: 0; top: 100%; background: var(--x-bg); min-width: 140px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); border-radius: 12px; border: 1px solid var(--x-border); z-index: 100; overflow: hidden; }
.dark .menu-content { box-shadow: 0 4px 15px rgba(255,255,255,0.05); }
.menu-wrap.active .menu-content { display: block; animation: slideDown 0.2s ease; }
@keyframes slideDown { from {opacity:0; transform:translateY(-5px);} to {opacity:1; transform:translateY(0);} }
.menu-item { display: flex; align-items: center; gap: 8px; padding: 12px 16px; font-size: 14px; font-weight: bold; color: var(--x-black); transition: 0.2s; cursor: pointer; width: 100%; text-align: right; border: none; background: transparent;}
.menu-item:hover { background: var(--x-hover); }
.menu-item.danger { color: #f91880; }
.menu-item.danger:hover { background: var(--x-hover-r); color: #f91880; }

.comments-header-box { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-top: 1px solid var(--x-border); border-bottom: 1px solid var(--x-border); background: var(--x-bg); margin-top: 0; }
.comments-title { font-size: 18px; font-weight: bold; color: var(--x-black); }
.comments-filter select { background: var(--x-hover); border: 1px solid transparent; color: var(--x-black); padding: 8px 36px 8px 16px; border-radius: 99px; font-size: 13px; font-weight: bold; cursor: pointer; outline: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill="%231d9bf0" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>'); background-repeat: no-repeat; background-position: right 12px center; transition: 0.2s; }
.comments-filter select:hover, .comments-filter select:focus { border-color: var(--x-blue); background: var(--x-bg); }

.comments-container { padding: 0 12px; padding-top: 15px; }

.glass-card { content-visibility: auto; contain-intrinsic-size: 200px; contain: content; will-change: transform; }

.reply-fab { display:flex; position:fixed; bottom:50px; left:30px; width:64px; height:64px; background: rgba(29, 155, 240, 0.6); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); border: 1px solid rgba(255,255,255,0.3); border-radius: 30%; color: #fff;  z-index:99; align-items:center; justify-content:center; transition: all 0.3s ease; cursor:pointer;}
.reply-fab:hover { transform:scale(1.08) translateY(-4px); background: rgba(29, 155, 240, 0.85); box-shadow: 0 14px 40px rgba(29, 155, 240, 0.5); }
.pagination { display: none; }
.reply-fab svg { width: 22px; height: 22px; fill: currentColor; }

@media(max-width:600px){ 
	.side{display:none;} .nav-m{display:flex;} .main{border:none; padding-bottom:90px;}
    .reply-fab { bottom: 95px; left: 20px; width:56px; height:56px; } 
}

.liked svg.ic-a { fill: #f91880 !important; color: #f91880 !important; }
.liked .like-icon { color: #f91880 !important; }
.liked .like-count { color: #f91880 !important; }

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

.info-modal-content { font-size: 15px; line-height: 1.7; color: var(--x-black); text-align: justify; }
.info-modal-content b { color: var(--x-blue); }
</style>
</head>
<body>
<div class="app">
    <main class="main">
	    <?php include 'header.php'; ?>

        <div class="hdr" style="padding:0; display:flex; flex-direction:column;">
            <div class="vt-top-bar">
                <div class="vt-top-left" onclick="window.location.href='index.php'">
                    <a href="index.php" class="vt-back" onclick="event.stopPropagation()"><?=$ic_back?></a>
                    <h2 class="vt-title">پست</h2>
                </div>
                <div class="vt-info-btn" onclick="openTwxInfoModal(event)">
                    <?=$ic_info?>
                </div>
            </div>
        </div>

        <div style="padding: 12px; position:relative;">
            <?php render_tweet_box($tweet, 'home', $is_logged, $uid, $user_role, []); ?>
        </div>

        <div class="comments-header-box">
            <div class="comments-title">کامنت‌ها</div>
            <div class="comments-filter">
                <select onchange="window.location.href='?id=<?=$tid?>&sort='+this.value">
                    <option value="newest" <?=$sort=='newest'?'selected':''?>>جدیدترین</option>
                    <option value="oldest" <?=$sort=='oldest'?'selected':''?>>قدیمی‌ترین</option>
                    <option value="liked" <?=$sort=='liked'?'selected':''?>>بیشترین لایک</option>
                    <option value="viewed" <?=$sort=='viewed'?'selected':''?>>بیشترین ویو</option>
                </select>
            </div>
        </div>

        <div class="comments-container">
            <?php if(empty($comments_list)): ?>
                <div style="text-align:center; padding:50px 20px; color:var(--x-gray); font-size:15px;">اولین نفری باشید که نظر می‌دهد.</div>
            <?php else: ?>
                <?php foreach($comments_list as $c): ?>
                    <?php render_tweet_box($c, 'home', $is_logged, $uid, $user_role, []); ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if($total_pages > 1): ?>
            <div class="pagination" style="display:flex; justify-content:center; gap:8px; margin:30px 0; direction:ltr;">
                <?php 
                $start_p = max(1, $page - 2);
                $end_p = min($total_pages, $page + 2);
                if($page > 1): ?>
                    <a href="?id=<?=$tid?>&sort=<?=htmlspecialchars($sort, ENT_QUOTES, 'UTF-8')?>&p=<?=$page-1?>" style="padding:8px 16px; border-radius:14px; background:var(--x-hover); color:var(--x-black); font-weight:bold;">قبلی</a>
                <?php endif; ?>
                
                <?php for($i = $start_p; $i <= $end_p; $i++): ?>
                    <a href="?id=<?=$tid?>&sort=<?=htmlspecialchars($sort, ENT_QUOTES, 'UTF-8')?>&p=<?=$i?>" style="padding:8px 16px; border-radius:14px; background:<?=$page==$i?'var(--x-blue)':'var(--x-hover)'?>; color:<?=$page==$i?'#fff':'var(--x-black)'?>; font-weight:bold;"><?= pNum($i) ?></a>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                    <a href="?id=<?=$tid?>&sort=<?=htmlspecialchars($sort, ENT_QUOTES, 'UTF-8')?>&p=<?=$page+1?>" style="padding:8px 16px; border-radius:14px; background:var(--x-hover); color:var(--x-black); font-weight:bold;">بعدی</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="reply-fab" onclick="twxOpenReplyModal(<?=$tid?>, event)" title="ثبت کامنت">
            <svg viewBox="0 0 24 24"><path d="M1.751 10c0-4.42 3.584-8 8.005-8h4.366c4.49 0 8.129 3.64 8.129 8.13 0 2.96-1.607 5.68-4.196 7.11l-8.054 4.46v-3.69h-.067c-4.49.1-8.183-3.51-8.183-8.01zm8.005-6c-3.317 0-6.005 2.69-6.005 6 0 3.37 2.77 6.08 6.138 6.01l.351-.01h1.761v2.3l5.087-2.81c1.951-1.08 3.163-3.13 3.163-5.36 0-3.39-2.744-6.13-6.129-6.13H9.756z"/></svg>
        </div>
    </main>
</div>

<div id="twx-info-modal" class="twx-modal-overlay" onclick="closeTwxInfoModal()">
    <div class="twx-modal-box" onclick="event.stopPropagation()">
        <div class="twx-header">
            <button type="button" class="twx-close" onclick="closeTwxInfoModal()">×</button>
            <div class="twx-title">راهنمای صفحه پست</div>
        </div>
        <div class="twx-body info-modal-content">
            <?php $greeting_name = $is_logged ? $current_user_name : 'کاربر'; ?>
            <b><?=$greeting_name?> عزیز،</b><br>
            در این بخش شما در حال مشاهده صفحه اختصاصی یک پست هستید. در اینجا می‌توانید:<br><br>
            - با استفاده از دکمه لایک، علاقه خود را به پست نشان دهید.<br>
            - نظرات سایر کاربران را بر اساس فیلترهای دلخواه مرتب و مشاهده کنید.<br>
            - با لمس دکمه شناور <b>"یه کامنت بزار"</b> در پایین صفحه، نظر خود را برای این پست ثبت کنید.<br>
            - با کلیک روی سه نقطه در کنار پست‌های خودتان، به منوی ویرایش و حذف دسترسی پیدا کنید.
        </div>
        <div class="twx-footer" style="justify-content: center;">
            <button type="button" class="twx-btn-save" style="width: 100%;" onclick="closeTwxInfoModal()">متوجه شدم</button>
        </div>
    </div>
</div>

<script>
function tglMenu(id, e) {
    if(e) e.stopPropagation();
    document.querySelectorAll('.menu-wrap').forEach(m => { if(m.id !== id) m.classList.remove('active'); });
    const menu = document.getElementById(id);
    if(menu) menu.classList.toggle('active');
}

window.addEventListener('click', () => { document.querySelectorAll('.menu-wrap').forEach(m => m.classList.remove('active')); });

function openTwxInfoModal(event) {
    if(event) event.stopPropagation();
    const modal = document.getElementById('twx-info-modal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}
function closeTwxInfoModal() {
    const modal = document.getElementById('twx-info-modal');
    modal.classList.remove('active');
    setTimeout(() => modal.style.display = 'none', 200);
}

async function ajaxLike(e, form) {
    e.preventDefault();
    <?php if(!$is_logged): ?> document.getElementById('lM').style.display='flex'; return false; <?php endif; ?>
    const btn = form.querySelector('.like');
    const icon = form.querySelector('.like-icon');
    const span = form.querySelector('.like-count');
    const wasLiked = btn.classList.contains('liked');
    
    btn.classList.toggle('liked');
    const svgLike = `<svg viewBox="0 0 24 24" class="ic-a"><path d="M16.697 5.5c-1.222-.06-2.679.51-3.89 2.16l-.805 1.09-.806-1.09C9.984 6.01 8.526 5.44 7.304 5.5c-1.243.07-2.349.78-2.91 1.91-.552 1.12-.633 2.78.479 4.82 1.074 1.97 3.257 4.27 7.129 6.61 3.87-2.34 6.052-4.64 7.126-6.61 1.111-2.04 1.03-3.7.477-4.82-.561-1.13-1.666-1.84-2.908-1.91zm4.187 7.69c-1.351 2.48-4.001 5.12-8.379 7.67l-.503.3-.504-.3c-4.379-2.55-7.029-5.19-8.382-7.67-1.36-2.5-1.41-4.86-.514-6.67.887-1.79 2.647-2.91 4.601-3.01 1.651-.09 3.368.56 4.798 2.01 1.429-1.45 3.146-2.1 4.796-2.01 1.954.1 3.714 1.22 4.601 3.01.896 1.81.846 4.17-.514 6.67z"/></svg>`;
    const svgLiked = `<svg viewBox="0 0 24 24" class="ic-a" style="fill:#f91880"><path d="M12 21.638h-.014C9.403 21.59 1.95 14.856 1.95 8.478c0-3.064 2.525-5.754 5.403-5.754 2.29 0 3.83 1.58 4.646 2.73.814-1.148 2.354-2.73 4.645-2.73 2.88 0 5.404 2.69 5.404 5.755 0 6.376-7.454 13.11-10.037 13.157H12z"/></svg>`;
    
    icon.innerHTML = !wasLiked ? svgLiked : svgLike;
    let currentCount = parseInt(span.innerText.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))) || 0;
    const pNum = (num) => num.toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
    span.innerText = !wasLiked ? pNum(currentCount+1) : (currentCount > 1 ? pNum(currentCount-1) : '');
    
    const fd = new FormData(form);
    fd.append('ajax_like', '1');
    try {
        const res = await fetch(location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.liked) { btn.classList.add('liked'); icon.innerHTML = svgLiked; } 
        else { btn.classList.remove('liked'); icon.innerHTML = svgLike; }
        span.innerText = data.count > 0 ? pNum(data.count) : '';
    } catch (err) { console.error(err); }
}
</script>
<?php include_once 'twx_modals.php'; ?>
<?php if(file_exists('footer.php')) include 'footer.php'; ?>
</body>
</html>
<?php ob_end_flush(); ?>
