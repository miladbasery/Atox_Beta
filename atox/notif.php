<?php
ob_start();
session_start();
require 'db.php';

$uid = $_SESSION['user_id'] ?? 0;
if (!$uid) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role, username, name FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user_data) { session_destroy(); header("Location: index.php"); exit; }

$current_user_name = !empty($user_data['name']) ? $user_data['name'] : $user_data['username'];
$is_logged = true;

$filter = $_GET['f'] ?? 'all';
if (!in_array($filter, ['all', 'like', 'comment', 'follow'])) { $filter = 'all'; }

$limit = 20;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

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
    return pNum("$jd {$months[$jm - 1]} / " . date('H:i', $timestamp));
}

function pNum($str) { return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str); }

$queries = [];
$params = [];

if (in_array($filter, ['all', 'follow'])) {
    $queries[] = "SELECT 'follow' as type, f.created_at, u.id as u_id, u.name, u.username, u.avatar, u.is_verified, 0 as item_id, '' as item_desc
                  FROM follows f JOIN users u ON f.follower_id = u.id 
                  WHERE f.following_id = ? AND f.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)";
    $params[] = $uid;
}
if (in_array($filter, ['all', 'like'])) {
    $queries[] = "SELECT 'like' as type, l.created_at, u.id as u_id, u.name, u.username, u.avatar, u.is_verified, t.id as item_id, t.description as item_desc
                  FROM likes l JOIN users u ON l.user_id = u.id JOIN tweets t ON l.tweet_id = t.id 
                  WHERE t.user_id = ? AND l.user_id != ? AND l.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)";
    $params[] = $uid; $params[] = $uid;
}
if (in_array($filter, ['all', 'comment'])) {
    $queries[] = "SELECT 'comment' as type, c.created_at, u.id as u_id, u.name, u.username, u.avatar, u.is_verified, t.id as item_id, c.description as item_desc
                  FROM tweets c JOIN users u ON c.user_id = u.id JOIN tweets t ON c.parent_id = t.id 
                  WHERE t.user_id = ? AND c.user_id != ? AND c.is_comment = 1 AND c.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)";
    $params[] = $uid; $params[] = $uid;
}

$full_query = implode(" UNION ALL ", $queries);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ($full_query) AS total");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$final_query = $full_query . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($final_query);
$stmt->execute($params);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$blue_tick = '<svg viewBox="0 0 24 24" aria-label="Verified account" style="width:16px;height:16px;vertical-align:-3px;margin-right:2px;display:inline-block;"><defs><linearGradient id="premiumBlue" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#33a8ff" /><stop offset="100%" stop-color="#0d85d8" /></linearGradient></defs><path fill="url(#premiumBlue)" d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.71-3.998-3.918-3.998-.47 0-.92.084-1.336.25C14.818 2.415 13.51 1.5 12 1.5s-2.816.917-3.337 2.25c-.416-.165-.866-.25-1.336-.25-2.21 0-3.918 1.79-3.918 4 0 .495.084.965.238 1.4-1.273.65-2.148 2.02-2.148 3.6 0 1.46.826 2.75 2.043 3.45-.05.223-.076.458-.076.7 0 2.21 1.71 3.998 3.918 3.998.337 0 .662-.046.978-.13.545 1.152 1.713 1.93 3.098 1.93 1.386 0 2.554-.778 3.098-1.93.316.084.64.13.978.13 2.208 0 3.918-1.788 3.918-3.998 0-.242-.026-.477-.076-.7 1.217-.7 2.043-1.99 2.043-3.45z"></path><path fill="#ffffff" d="M10.23 17.5l-4.7-4.7 1.41-1.42 3.29 3.3 7.8-7.8 1.41 1.42-9.21 9.2z"></path></svg>';
$ic_like = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="20" height="20"><defs></defs><g transform="translate(256, 256) rotate(0) scale(1, 1) scale(1) translate(-256, -256)" > <g xmlns="http://www.w3.org/2000/svg" id="heart" fill="#ff0000"> <path d="M362.656,21.336c-41.781,0-79.562,17.171-106.656,44.835c-27.109-27.664-64.883-44.835-106.672-44.835 C66.852,21.336,0,88.195,0,170.671c0,170.665,256,319.993,256,319.993s256-149.328,256-319.993 C512,88.195,445.141,21.336,362.656,21.336z M370.141,378.429c-46.469,42.688-93.547,74.157-114.141,87.204 c-20.593-13.047-67.68-44.517-114.147-87.204c-35.626-32.733-63.891-65.515-84.023-97.482 c-24.22-38.439-36.501-75.541-36.501-110.275c0-34.188,13.312-66.335,37.492-90.507c24.18-24.18,56.321-37.493,90.508-37.493 c17.516,0,34.484,3.469,50.422,10.313c15.414,6.617,29.203,16.078,41,28.116L256,96.655l15.234-15.555 c11.797-12.039,25.594-21.5,40.999-28.116c15.953-6.844,32.907-10.313,50.423-10.313c34.203,0,66.344,13.313,90.517,37.493 c24.17,24.172,37.484,56.32,37.484,90.507c0,34.734-12.282,71.836-36.501,110.275C434.032,312.914,405.766,345.695,370.141,378.429 z" fill="#ff0000"> <path d="M149.328,63.999L149.328,63.999c-58.906,0-106.664,47.758-106.664,106.672l0,0c0,2.727,1.039,5.461,3.125,7.54 c4.164,4.163,10.914,4.163,15.086,0c2.078-2.079,3.125-4.813,3.125-7.54l0,0c0-22.797,8.875-44.227,24.992-60.344 s37.547-24.992,60.336-24.992l0,0c2.734,0,5.461-1.04,7.547-3.125c4.164-4.164,4.164-10.914,0-15.086 C154.789,65.046,152.062,63.999,149.328,63.999z" fill="#ff0000"> </path></path></g> </g></svg>';
$ic_comment = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <g xmlns="http://www.w3.org/2000/svg" fill="#006eff"> <path fill="none" stroke="#006eff" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" d="M6.6335,17.5146 c-2.94-0.6889-5.926-4.1063-5.926-8.491c0-4.5789,3.5251-8.5255,7.629-8.5255h7.5208c4.1039,0,7.431,3.9212,7.431,8.5001 c0,4.5313-3.3271,8.4999-7.431,8.4999h-2.369l-6.8548,5.78V17.5146z"> <line fill="none" stroke="#006eff" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" x1="7.4677" y1="6.4981" x2="16.6062" y2="6.4981"> <line fill="none" stroke="#006eff" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" x1="7.4677" y1="9.498" x2="16.6062" y2="9.498"> <line fill="none" stroke="#006eff" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" x1="7.4677" y1="12.498" x2="12.4523" y2="12.498"> </line></line></line></path></g> <rect xmlns="http://www.w3.org/2000/svg" x="0" y="-0.002" fill="none"> </rect></g></svg>';
$ic_follow = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" d="M13.8244 7.35397C13.8244 5.19398 12.0737 3.43699 9.92144 3.43699C7.7692 3.43699 6.01851 5.19398 6.01851 7.35397C6.01851 9.51396 7.7692 11.271 9.92144 11.271C12.0737 11.271 13.8244 9.51396 13.8244 7.35397ZM15.2552 7.35397C15.2552 10.306 12.8628 12.7079 9.92144 12.7079C6.98005 12.7079 4.58767 10.306 4.58767 7.35397C4.58767 4.40199 6.98005 2 9.92144 2C12.8628 2 15.2552 4.40199 15.2552 7.35397ZM21.2527 10.0385H19.9613V8.78447C19.9613 8.37047 19.6266 8.03447 19.214 8.03447C18.8015 8.03447 18.4667 8.37047 18.4667 8.78447V10.0385H17.1774C16.7649 10.0385 16.4301 10.3745 16.4301 10.7885C16.4301 11.2025 16.7649 11.5385 17.1774 11.5385H18.4667V12.7934C18.4667 13.2074 18.8015 13.5434 19.214 13.5434C19.6266 13.5434 19.9613 13.2074 19.9613 12.7934V11.5385H21.2527C21.6652 11.5385 22 11.2025 22 10.7885C22 10.3745 21.6652 10.0385 21.2527 10.0385ZM9.92144 14.64C6.5207 14.64 2 15.02 2 18.31C2 19.295 2.45635 20.625 4.63052 21.364C5.00616 21.494 5.4107 21.288 5.53824 20.914C5.66479 20.539 5.46451 20.131 5.08986 20.004C3.60222 19.498 3.43084 18.795 3.43084 18.31C3.43084 16.828 5.61497 16.076 9.92144 16.076C14.2279 16.076 16.412 16.834 16.412 18.33C16.412 19.813 14.2279 20.564 9.92144 20.564C9.59263 20.564 9.2688 20.56 8.95094 20.55C8.56832 20.553 8.22655 20.851 8.21559 21.247C8.20364 21.644 8.51452 21.975 8.90909 21.987C9.2409 21.995 9.57868 22 9.92144 22C13.3222 22 17.8429 21.619 17.8429 18.33C17.8429 14.64 11.8804 14.64 9.92144 14.64Z" fill="#1bd081"> </path></g></svg>';
$ic_info = '<svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:currentColor;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>آتوکس - اعلانات</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.92); --x-hover:rgba(15,20,25,0.05); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.92); --x-hover:rgba(255,255,255,0.08); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}

html { scroll-behavior: smooth; }
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;overflow-y:auto; -webkit-overflow-scrolling: touch;}
a{text-decoration:none;color:inherit;}
button{cursor:pointer; background:none; border:none; color:inherit; font-family:inherit;}

.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:100px; min-height:100vh; display:flex; flex-direction:column;}

.hdr{position:sticky;top:0;background:var(--x-bg-trans);backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:10;border-bottom:1px solid var(--x-border); padding:12px 16px;}
.hdr-top { display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px; }
.hdr-title{font-size:19px; font-weight:900;}


.info-btn { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.15s; color: var(--x-black); }
.info-btn:hover { background: var(--x-hover); }


.filter-tabs { display:flex; gap:6px; overflow-x:auto; padding-bottom:2px; scrollbar-width: none; }
.filter-tabs::-webkit-scrollbar { display: none; }
.filter-tab { padding: 6px 14px; border-radius: 99px; font-size: 13px; font-weight: bold; white-space: nowrap; transition: 0.15s; border: 1px solid var(--x-border); color: var(--x-gray); }
.filter-tab:hover { background: var(--x-hover); }
.filter-tab.active { background: var(--x-black); color: var(--x-bg); border-color: var(--x-black); }
.dark .filter-tab.active { background: #fff; color: #000; border-color: #fff; }


.notif-card { display:flex; align-items:center; padding:10px 14px; border-radius: 14px; transition: background-color 0.15s ease, transform 0.1s ease; margin: 4px 10px; cursor:pointer; }
.notif-card:hover { background: var(--x-hover); }
.notif-card:active { transform: scale(0.98); }


.n-av-link { display:inline-flex; flex-shrink:0; margin-left:12px; }
.n-av { width:42px; height:42px; border-radius:50%; object-fit:cover; border: 1px solid var(--x-border); transition: 0.2s; }
.n-av-link:hover .n-av { filter: brightness(0.85); transform:scale(1.05); }


.n-body{flex:1; display:flex; flex-direction:column; justify-content:center; margin-left:8px;}
.n-text{font-size:14px; color:var(--x-black); line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;}
.n-text span.action-text { color: var(--x-gray); margin-right: 2px; }
.n-time{font-size:12px; color:var(--x-gray); margin-top:3px;}


.n-action { display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:50%; background:var(--x-hover); flex-shrink:0; }


.pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin: 20px 16px 40px; flex-wrap: wrap; direction: ltr; }
.page-link { padding: 6px 14px; border-radius: 12px; background: var(--x-hover); color: var(--x-black); font-weight: bold; font-size: 14px; transition: 0.15s; }
.page-link:hover { opacity:0.8; }
.page-link.active { background: var(--x-blue); color: #fff; }

@media(max-width:600px){ .main{border:none;} .notif-card{border-radius:0; margin: 2px 0;} }


.twx-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 16px; opacity: 0; transition: opacity 0.2s ease; will-change: opacity; }
.twx-modal-overlay.active { display: flex; opacity: 1; }
.twx-modal-box { background: var(--x-bg); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); transform: scale(0.95); transition: transform 0.2s ease; display: flex; flex-direction: column; overflow: hidden; position: relative; width: 100%; max-width: 500px; will-change: transform; }
.dark .twx-modal-box { box-shadow: 0 8px 32px rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
.twx-modal-overlay.active .twx-modal-box { transform: scale(1); }
@media (max-width: 480px) {
    .twx-modal-box { position: absolute; bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0; transform: translateY(100%); scale: 1; }
    .twx-modal-overlay.active .twx-modal-box { transform: translateY(0); scale: 1; }
}
.twx-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--x-border); }
.twx-title { font-size: 16px; font-weight: bold; color: var(--x-black); flex: 1; text-align: center; margin-right: -36px; }
.twx-close { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--x-black); transition: 0.2s;}
.twx-close:hover { background: var(--x-hover); }
.twx-body { padding: 16px; line-height:1.7; font-size:14px;}
.twx-footer { display: flex; justify-content: center; padding: 0 16px 16px; }
.twx-btn-save { background: var(--x-black); color: var(--x-bg); padding: 10px 24px; border-radius: 99px; font-weight: bold; font-size: 14px; transition: 0.2s; width:100%; }
.dark .twx-btn-save { background: #fff; color: #000; }
.twx-btn-save:active { transform: scale(0.97); }
</style>
</head>
<body>
<div class="app">
    <main class="main">
        <?php include 'header.php'; ?>
        
        <div class="hdr">
            <div class="hdr-top">
                <div class="hdr-title">اعلانات شما</div>
                <button type="button" class="info-btn" onclick="openTwxInfoModal(event)" title="راهنما"><?=$ic_info?></button>
            </div>
            
            <div class="filter-tabs">
                <a href="?f=all" class="filter-tab <?=$filter==='all'?'active':''?>">همه</a>
                <a href="?f=follow" class="filter-tab <?=$filter==='follow'?'active':''?>">دنبال‌کنندگان</a>
                <a href="?f=like" class="filter-tab <?=$filter==='like'?'active':''?>">لایک‌ها</a>
                <a href="?f=comment" class="filter-tab <?=$filter==='comment'?'active':''?>">کامنت‌ها</a>
            </div>
        </div>

        <div style="flex:1;">
            <?php if(empty($notifs)): ?>
                <div style="text-align:center; padding:80px 20px; color:var(--x-gray); font-size:15px; font-weight:bold;">هیچ اعلانی در این بخش وجود ندارد.</div>
            <?php else: ?>
                <?php foreach($notifs as $n): 
                    $avatar = !empty($n['avatar']) ? htmlspecialchars($n['avatar']) : "https://ui-avatars.com/api/?name=".urlencode($n['name'])."&background=random&color=fff";
                    $card_link = ($n['type'] === 'follow') ? "profile.php?id=" . $n['u_id'] : "view_tweet.php?id=" . $n['item_id'];
                    $profile_link = "profile.php?id=" . $n['u_id'];
                ?>
                    <div class="notif-card" onclick="location.href='<?=$card_link?>'">
                        <!-- عکس پروفایل در راست -->
                        <a href="<?=$profile_link?>" class="n-av-link" onclick="event.stopPropagation();">
                            <img src="<?=$avatar?>" class="n-av" alt="avatar">
                        </a>
                        
                        <!-- متن خلاصه در وسط -->
                        <div class="n-body">
                            <div class="n-text">
                                <b><?=htmlspecialchars($n['name'])?></b><?=$n['is_verified']?$blue_tick:''?>
                                <span class="action-text">
                                <?php 
                                    if($n['type'] === 'like') echo "پست شما را لایک کرد.";
                                    elseif($n['type'] === 'comment') echo "برای شما کامنت گذاشت.";
                                    elseif($n['type'] === 'follow') echo "شما را دنبال کرد.";
                                ?>
                                </span>
                            </div>
                            <span class="n-time"><?=pTime($n['created_at'])?></span>
                        </div>

                        <!-- آیکون ارجاع در چپ -->
                        <div class="n-action">
                            <?php 
                                if($n['type'] === 'like') echo $ic_like;
                                elseif($n['type'] === 'comment') echo $ic_comment;
                                else echo $ic_follow;
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- صفحه‌بندی -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php 
                    $start_p = max(1, $page - 2);
                    $end_p = min($total_pages, $page + 2);
                    if($page > 1): ?>
                        <a href="?f=<?=$filter?>&p=<?=$page-1?>" class="page-link">قبلی</a>
                    <?php endif; ?>
                    
                    <?php for($i = $start_p; $i <= $end_p; $i++): ?>
                        <a href="?f=<?=$filter?>&p=<?=$i?>" class="page-link <?= $page == $i ? 'active' : '' ?>"><?= pNum($i) ?></a>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?f=<?=$filter?>&p=<?=$page+1?>" class="page-link">بعدی</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </main>
</div>

<div id="twx-info-modal" class="twx-modal-overlay" onclick="closeTwxInfoModal()">
    <div class="twx-modal-box" onclick="event.stopPropagation()">
        <div class="twx-header">
            <button type="button" class="twx-close" onclick="closeTwxInfoModal()">×</button>
            <div class="twx-title">راهنمای صفحه</div>
            <div style="width:5px;"></div>
        </div>
        <div class="twx-body">
            <?php $greeting_name = $is_logged ? $current_user_name : 'کاربر'; ?>
            <b><?=$greeting_name?> عزیز،</b><br>
            در این بخش اعلانات سریع و لحظه‌ای شما نمایش داده می‌شود:<br><br>
            - با کلیک روی هر کادر، مستقیماً به پست لایک/کامنت شده یا پروفایل شخص فالوکننده هدایت می‌شوید.<br>
            - برای دسترسی سریع‌تر به پروفایل کاربران، مستقیماً روی <b>عکس پروفایل</b> آن‌ها لمس کنید.
        </div>
        <div class="twx-footer">
            <button type="button" class="twx-btn-save" onclick="closeTwxInfoModal()">متوجه شدم</button>
        </div>
    </div>
</div>

<script>
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
</script>

<?php if(file_exists('footer.php')) include 'footer.php'; ?>
</body>
</html>
<?php ob_end_flush(); ?>
