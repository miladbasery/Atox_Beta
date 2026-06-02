<?php
ob_start();
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

require 'db.php';

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$user_role = 'user';
$is_logged = false;
$current_user_name = 'کاربر';

if ($uid > 0) {
    $stmt = $pdo->prepare("SELECT role, username, name FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) { 
        session_destroy(); 
        header("Location: index.php"); 
        exit; 
    }
    
    $user_role = $user_data['role'] ?? 'user';
    $current_user_name = $user_data['name'] ?? $user_data['username'];
    
    if ($user_data['username'] === 'milad') {
        $user_role = 'admin';
        $_SESSION['role'] = 'admin';
    }
    $is_logged = true;
} else {
    session_destroy();
    header("Location: index.php");
    exit;
}

$top_points = $pdo->query("SELECT u.id, u.name, u.username, u.avatar, u.points, u.level, u.is_verified, r.job FROM users u LEFT JOIN resumes r ON u.id = r.user_id ORDER BY u.points DESC, u.id ASC LIMIT 250")->fetchAll(PDO::FETCH_ASSOC);
$top_tweets = $pdo->query("SELECT u.id, u.name, u.username, u.avatar, u.level, u.points, u.is_verified, r.job, COUNT(t.id) as item_count FROM users u LEFT JOIN tweets t ON u.id = t.user_id AND t.is_comment = 0 AND t.is_retweet = 0 LEFT JOIN resumes r ON u.id = r.user_id GROUP BY u.id ORDER BY item_count DESC, u.id ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$top_likes = $pdo->query("SELECT u.id, u.name, u.username, u.avatar, u.level, u.points, u.is_verified, r.job, COUNT(l.id) as item_count FROM users u JOIN tweets t ON u.id = t.user_id JOIN likes l ON t.id = l.tweet_id LEFT JOIN resumes r ON u.id = r.user_id GROUP BY u.id ORDER BY item_count DESC, u.id ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

function fa_num($number) {
    $en = array("0","1","2","3","4","5","6","7","8","9");
    $fa = array("۰","۱","۲","۳","۴","۵","۶","۷","۸","۹");
    return str_replace($en, $fa, (string)$number);
}

function getLvlColor($lvl) {
    $lvl = (int)$lvl;
    if ($lvl < 5) return '#aab8c2';
    if ($lvl < 10) return '#1d9bf0';
    if ($lvl < 20) return '#00ba7c';
    if ($lvl < 30) return '#f91880';
    if ($lvl < 40) return '#ffad1f';
    return '#794bc4'; 
}

function renderVerifiedBadge() {
    return '<svg viewBox="0 0 24 24" class="verified-icon" style="width:16px;height:16px;fill:var(--x-blue);flex-shrink:0;"><g><path d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.71-3.998-3.918-3.998-.47 0-.92.084-1.336.25C14.818 2.415 13.51 1.5 12 1.5s-2.816.917-3.337 2.25c-.416-.165-.866-.25-1.336-.25-2.21 0-3.918 1.79-3.918 4 0 .495.084.965.238 1.4-1.273.65-2.148 2.02-2.148 3.6 0 1.46.756 2.72 1.86 3.4-.105.34-.16.71-.16 1.09 0 2.21 1.71 4 3.918 4 .51 0 1.002-.107 1.453-.298C9.282 22.185 10.57 23 12 23s2.718-.815 3.237-2.148c.45.19.943.298 1.453.298 2.21 0 3.918-1.79 3.918-4 0-.38-.055-.75-.16-1.09 1.104-.68 1.86-1.94 1.86-3.4zM10.232 16.48l-3.32-3.32 1.414-1.413 1.906 1.905 5.438-5.438 1.414 1.414-6.852 6.852z"></path></g></svg>';
}

function renderPodium($list, $type_label) {
    if (empty($list)) return;
    
    $podium_data = [
        2 => isset($list[1]) ? $list[1] : null,
        1 => isset($list[0]) ? $list[0] : null,
        3 => isset($list[2]) ? $list[2] : null,
    ];
    
    echo '<div class="podium-container">';
    foreach ([2, 1, 3] as $rank) {
        $u = $podium_data[$rank];
        if (!$u) continue;
        
        $u_avatar = !empty($u['avatar']) ? htmlspecialchars($u['avatar'], ENT_QUOTES, 'UTF-8') : 'default-avatar.png';
        $score = isset($u['item_count']) ? $u['item_count'] : $u['points'];
        $name = htmlspecialchars($u['name'] ?: $u['username'], ENT_QUOTES, 'UTF-8');
        $is_ver = !empty($u['is_verified']) ? renderVerifiedBadge() : '';
        
        echo '<a href="profile.php?id='.htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8').'" class="podium-item" style="order:'.$rank.'; text-decoration:none;">';
        
        echo '<div class="podium-badge-icon badge-rank-'.$rank.'">';
        if ($rank === 1) {
            echo '<svg viewBox="0 0 24 24"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg>';
        } elseif ($rank === 2) {
            echo '<svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
        } else {
            echo '<svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
        }
        echo '</div>';
        
        echo '<img src="'.$u_avatar.'" class="podium-avatar p-avatar-'.$rank.'">';
        echo '<div class="podium-bar p-bar-'.$rank.'">';
        echo '<div class="podium-name-wrap"><span class="podium-name">'.$name.'</span>'.$is_ver.'</div>';
        echo '<span class="podium-score">'.fa_num(number_format($score)).' '.$type_label.'</span>';
        echo '</div>';
        echo '</a>';
    }
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>آتوکس - رده‌بندی</title>
<link rel="manifest" href="/manifest.json?v=3">
<meta name="theme-color" content="#1DA1F2">
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.85); --x-hover:rgba(15,20,25,0.05); --x-modal:rgba(0,0,0,0.4); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.85); --x-hover:rgba(255,255,255,0.05); --x-modal:rgba(255,255,255,0.1); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;overflow-y:scroll; overflow-x:hidden;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}
.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:100px; min-height:100vh;}
.hdr { position: sticky; top: 0; background: var(--x-bg-trans); backdrop-filter: blur(12px); z-index: 10; border-bottom: 1px solid var(--x-border); display: flex; flex-direction: column; }
.hdr-top { padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; }
.hdr-title { font-size: 20px; font-weight: 900; }
.header-info-btn { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.header-info-btn:hover { background: var(--x-hover); }
.header-info-btn svg { width: 22px; height: 22px; fill: var(--x-black); }
.main-tabs { display: flex; width: 100%; }
.m-tab-btn { flex: 1; display: flex; justify-content: center; align-items: center; padding: 16px 0; font-size: 15px; font-weight: bold; color: var(--x-gray); cursor: pointer; transition: 0.2s; position: relative; }
.m-tab-btn:hover { background: var(--x-hover); }
.m-tab-btn.active { color: var(--x-black); }
.m-tab-indicator { position: absolute; bottom: 0; height: 4px; border-radius: 99px; background: var(--x-blue); width: 60px; display: none; }
.m-tab-btn.active .m-tab-indicator { display: block; }
.feed-content { display: block; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.rank-tabs { display: flex; gap: 10px; padding: 16px; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
.rank-tabs::-webkit-scrollbar { display: none; }
.r-tab-btn { flex-shrink: 0; padding: 12px 20px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.3s; background: rgba(15, 20, 25, 0.03); border: 1px solid var(--x-border); color: var(--x-gray); backdrop-filter: blur(12px); display: flex; align-items: center; gap: 8px; }
.dark .r-tab-btn { background: rgba(255,255,255,0.03); }
.r-tab-btn:hover { background: var(--x-hover); transform: translateY(-2px); }
.r-tab-btn.active { background: var(--x-black); color: var(--x-bg); border-color: var(--x-black); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.dark .r-tab-btn.active { background: var(--x-bg); color: var(--x-black); }

.rank-list-view { display: none; animation: fadeIn 0.3s; }
.rank-list-view.active { display: block; }


.r-card { background: transparent; border-radius: 20px; margin: 0 12px 24px; overflow: hidden; border: 1px solid var(--x-border); }
.r-itm { display: flex; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--x-border); text-decoration: none; transition: background 0.2s ease; gap: 14px; background: var(--x-bg); }
.r-itm:last-child { border-bottom: none; }
.r-itm:hover { background: var(--x-hover); }

.r-n { width: 32px; font-size: 15px; font-weight: 900; color: var(--x-gray); text-align: center; opacity: 0.7; flex-shrink: 0; }
.r-img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 1px solid var(--x-border); padding: 2px; flex-shrink: 0; }


.r-det { flex: 1; display: flex; align-items: center; justify-content: space-between; min-width: 0; gap: 12px; }


.r-info { display: flex; flex-direction: column; justify-content: center; min-width: 0; gap: 6px; flex: 1; }

.r-row-1, .r-row-2 { display: flex; align-items: center; gap: 8px; width: 100%; overflow: hidden; }


.r-nm { font-size: 15px; font-weight: 800; color: var(--x-black); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%; }
.r-lv { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 6px; border: 1px solid currentColor; letter-spacing: 0.5px; white-space: nowrap; flex-shrink: 0; }

.r-id { font-size: 13px; color: var(--x-gray); font-family: monospace; direction: ltr; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 45%; text-align: left; }
.r-jb { font-size: 12px; color: var(--x-gray); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 4px; }
.r-jb::before { content: ''; width: 4px; height: 4px; border-radius: 50%; background: var(--x-gray); display: block; opacity: 0.5; flex-shrink: 0; }


.r-sc-box { display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; min-width: 65px; background: var(--x-hover); padding: 8px 12px; border-radius: 12px; }
.r-sc { font-size: 14px; font-weight: 900; color: var(--x-black); font-family: monospace; }
.r-sc-lbl { font-size: 10px; color: var(--x-gray); font-weight: 700; margin-top: 2px;}


.podium-container { display:flex; justify-content:center; align-items:flex-end; gap:8px; margin:20px 16px 40px; padding-top:40px;}
.podium-item { display:flex; flex-direction:column; align-items:center; position:relative; width:31%; transition:transform 0.2s;}
.podium-item:hover { transform:translateY(-5px); }


.podium-badge-icon { position: absolute; top: -45px; z-index: 3; display: flex; align-items: center; justify-content: center; border-radius: 50%; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15)); }
.badge-rank-1 { top: -55px; width: 44px; height: 44px; background: #d4af37; border: 3px solid #fff; }
.badge-rank-1 svg { fill: #fff; width: 24px; height: 24px; }
.badge-rank-2 { width: 36px; height: 36px; background: #a9a9a9; border: 2px solid #fff; }
.badge-rank-2 svg { fill: #fff; width: 18px; height: 18px; }
.badge-rank-3 { width: 36px; height: 36px; background: #cd7f32; border: 2px solid #fff; }
.badge-rank-3 svg { fill: #fff; width: 18px; height: 18px; }
.dark .badge-rank-1, .dark .badge-rank-2, .dark .badge-rank-3 { border-color: var(--x-bg); }

.podium-avatar { border-radius:50%; object-fit:cover; background:var(--x-bg); z-index:1; position:relative; border: 4px solid var(--x-bg); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.p-avatar-1 { width:75px; height:75px; border-color:#d4af37; z-index: 2; }
.p-avatar-2 { width:60px; height:60px; border-color:#a9a9a9; }
.p-avatar-3 { width:60px; height:60px; border-color:#cd7f32; }

.podium-bar { width:100%; border-radius:16px 16px 0 0; display:flex; flex-direction:column; align-items:center; justify-content: flex-end; padding:10px 4px 16px; margin-top:-35px; padding-top:45px; box-shadow: inset 0 2px 10px rgba(255,255,255,0.2); }
.p-bar-1 { height:150px; background:linear-gradient(to top, rgba(212,175,55,0.15), rgba(212,175,55,0.4)); border:1px solid rgba(212,175,55,0.5); border-bottom:none;}
.p-bar-2 { height:120px; background:linear-gradient(to top, rgba(169,169,169,0.15), rgba(169,169,169,0.3)); border:1px solid rgba(169,169,169,0.5); border-bottom:none;}
.p-bar-3 { height:100px; background:linear-gradient(to top, rgba(205,127,50,0.15), rgba(205,127,50,0.3)); border:1px solid rgba(205,127,50,0.5); border-bottom:none;}

.podium-name-wrap { display: flex; align-items: center; justify-content: center; gap: 4px; width: 100%; padding: 0 6px; margin-bottom: 4px; }
.podium-name { font-size:13px; font-weight:800; color:var(--x-black); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:center; }
.podium-score { font-size:12px; font-weight:900; color:var(--x-gray); font-family:monospace; background: rgba(255,255,255,0.5); padding: 2px 8px; border-radius: 8px; }
.dark .podium-score { background: rgba(0,0,0,0.3); }


.twx-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 16px; opacity: 0; transition: opacity 0.2s; }
.twx-modal-overlay.active { display: flex; opacity: 1; }
.twx-modal-box { background: var(--x-bg); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); transform: scale(0.95); transition: transform 0.2s; display: flex; flex-direction: column; width: 100%; max-width: 500px; }
.twx-modal-overlay.active .twx-modal-box { transform: scale(1); }
.twx-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--x-border); }
.twx-title { font-size: 17px; font-weight: 800; flex: 1; text-align: center; margin-right: -36px; }
.twx-close { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; cursor: pointer; border:none; background:transparent;}
.twx-body { padding: 16px; line-height: 1.6; }
.twx-footer { padding: 0 16px 16px; display:flex; justify-content:center;}
.twx-btn-save { background: var(--x-black); color: var(--x-bg); border: none; padding: 8px 24px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; width:100%;}

@media(max-width:600px){ .main{border:none; padding-bottom:90px;} }
</style>
</head>
<body>
<div class="app">
    <main class="main">
        <?php include 'header.php'; ?>
        <div class="hdr">
            <div class="hdr-top">
                <div class="hdr-title">خانه</div>
                <button class="header-info-btn" onclick="openTwxInfoModal(event)" title="راهنمای صفحه">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                </button>
            </div>
            <div class="main-tabs">
                <a href="home.php" class="m-tab-btn">
                    داشبورد و اعلانات
                    <div class="m-tab-indicator"></div>
                </a>
                <a href="home_rate.php" class="m-tab-btn active">
                    رده‌بندی
                    <div class="m-tab-indicator"></div>
                </a>
            </div>
        </div>

        <div id="feed-rank" class="feed-content">
            <div class="rank-tabs">
                <button class="r-tab-btn active" data-target="points" onclick="showRankList('points', this)">
                    <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    برترین‌ها (امتیاز)
                </button>
                <button class="r-tab-btn" data-target="tweets" onclick="showRankList('tweets', this)">
                    <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor;"><path d="M22 12c0 5.52-4.48 10-10 10S2 17.52 2 12 6.48 2 12 2s10 4.48 10 10zM11 7v5.59l3.88 3.88 1.41-1.41L13 11.83V7h-2z"/></svg>
                    بیشترین فعالیت
                </button>
                <button class="r-tab-btn" data-target="likes" onclick="showRankList('likes', this)">
                    <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                    محبوب‌ترین‌ها
                </button>
            </div>

            <div id="list-points" class="rank-list-view active">
                <?php renderPodium($top_points, 'امتیاز'); ?>
                <div class="r-card">
                    <?php $rank = 4; for($i = 3; $i < count($top_points); $i++): $u = $top_points[$i];
                        $u_avatar = !empty($u['avatar']) ? htmlspecialchars($u['avatar'], ENT_QUOTES, 'UTF-8') : 'default-avatar.png';
                        $u_lvl = $u['level'] ?? 1;
                        $is_ver = !empty($u['is_verified']) ? renderVerifiedBadge() : '';
                    ?>
                    <a href="profile.php?id=<?=htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8')?>" class="r-itm">
                        <div class="r-n"><?=fa_num(str_pad($rank, 2, '0', STR_PAD_LEFT))?></div>
                        <img src="<?=$u_avatar?>" class="r-img">
                        <div class="r-det">
                            <div class="r-info">
                                <div class="r-row-1">
                                    <span class="r-nm"><?=htmlspecialchars($u['name'] ?: $u['username'], ENT_QUOTES, 'UTF-8')?></span>
                                    <?=$is_ver?>
                                    <span class="r-lv" style="color:<?=getLvlColor($u_lvl)?>">سطح <?=fa_num($u_lvl)?></span>
                                </div>
                                <div class="r-row-2">
                                    <span class="r-id">@<?=htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8')?></span>
                                    <?php if(!empty($u['job'])): ?><div class="r-jb"><?=htmlspecialchars($u['job'], ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="r-sc-box">
                                <span class="r-sc"><?=fa_num(number_format($u['points']))?></span>
                                <span class="r-sc-lbl">امتیاز</span>
                            </div>
                        </div>
                    </a>
                    <?php $rank++; endfor; ?>
                </div>
            </div>
            
            <div id="list-tweets" class="rank-list-view">
                <?php renderPodium($top_tweets, 'فعالیت'); ?>
                <div class="r-card">
                    <?php $rank = 4; for($i = 3; $i < count($top_tweets); $i++): $u = $top_tweets[$i];
                        $u_avatar = !empty($u['avatar']) ? htmlspecialchars($u['avatar'], ENT_QUOTES, 'UTF-8') : 'default-avatar.png';
                        $u_lvl = $u['level'] ?? 1;
                        $is_ver = !empty($u['is_verified']) ? renderVerifiedBadge() : '';
                    ?>
                    <a href="profile.php?id=<?=htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8')?>" class="r-itm">
                        <div class="r-n"><?=fa_num(str_pad($rank, 2, '0', STR_PAD_LEFT))?></div>
                        <img src="<?=$u_avatar?>" class="r-img">
                        <div class="r-det">
                            <div class="r-info">
                                <div class="r-row-1">
                                    <span class="r-nm"><?=htmlspecialchars($u['name'] ?: $u['username'], ENT_QUOTES, 'UTF-8')?></span>
                                    <?=$is_ver?>
                                    <span class="r-lv" style="color:<?=getLvlColor($u_lvl)?>">سطح <?=fa_num($u_lvl)?></span>
                                </div>
                                <div class="r-row-2">
                                    <span class="r-id">@<?=htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8')?></span>
                                    <?php if(!empty($u['job'])): ?><div class="r-jb"><?=htmlspecialchars($u['job'], ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="r-sc-box">
                                <span class="r-sc"><?=fa_num(number_format($u['item_count']))?></span>
                                <span class="r-sc-lbl">فعالیت</span>
                            </div>
                        </div>
                    </a>
                    <?php $rank++; endfor; ?>
                </div>
            </div>

            <div id="list-likes" class="rank-list-view">
                <?php renderPodium($top_likes, 'محبوبیت'); ?>
                <div class="r-card">
                    <?php $rank = 4; for($i = 3; $i < count($top_likes); $i++): $u = $top_likes[$i];
                        $u_avatar = !empty($u['avatar']) ? htmlspecialchars($u['avatar'], ENT_QUOTES, 'UTF-8') : 'default-avatar.png';
                        $u_lvl = $u['level'] ?? 1;
                        $is_ver = !empty($u['is_verified']) ? renderVerifiedBadge() : '';
                    ?>
                    <a href="profile.php?id=<?=htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8')?>" class="r-itm">
                        <div class="r-n"><?=fa_num(str_pad($rank, 2, '0', STR_PAD_LEFT))?></div>
                        <img src="<?=$u_avatar?>" class="r-img">
                        <div class="r-det">
                            <div class="r-info">
                                <div class="r-row-1">
                                    <span class="r-nm"><?=htmlspecialchars($u['name'] ?: $u['username'], ENT_QUOTES, 'UTF-8')?></span>
                                    <?=$is_ver?>
                                    <span class="r-lv" style="color:<?=getLvlColor($u_lvl)?>">سطح <?=fa_num($u_lvl)?></span>
                                </div>
                                <div class="r-row-2">
                                    <span class="r-id">@<?=htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8')?></span>
                                    <?php if(!empty($u['job'])): ?><div class="r-jb"><?=htmlspecialchars($u['job'], ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="r-sc-box">
                                <span class="r-sc"><?=fa_num(number_format($u['item_count']))?></span>
                                <span class="r-sc-lbl">محبوبیت</span>
                            </div>
                        </div>
                    </a>
                    <?php $rank++; endfor; ?>
                </div>
            </div>
        </div>
    </main>
</div>


<div id="twx-info-modal" class="twx-modal-overlay" onclick="closeTwxInfoModal()"><div class="twx-modal-box" onclick="event.stopPropagation()"><div class="twx-header"><button type="button" class="twx-close" onclick="closeTwxInfoModal()">×</button><div class="twx-title">راهنمای صفحه</div></div><div class="twx-body"><b><?=htmlspecialchars($current_user_name, ENT_QUOTES, 'UTF-8')?> عزیز،</b><br>به خانه آتوکس خوش آمدید. از طریق تب‌های بالا می‌توانید بین "داشبورد" و "رده‌بندی" جابجا شوید.<br><br>- در فید رده‌بندی، با کلیک روی تب‌های شیشه‌ای می‌توانید کاربران برتر را بر اساس امتیاز، میزان فعالیت (توییت) و محبوبیت (لایک) مشاهده کنید.<br>- با کلیک روی هر کاربر، به پروفایل اختصاصی او منتقل می‌شوید.</div><div class="twx-footer"><button type="button" class="twx-btn-save" onclick="closeTwxInfoModal()">متوجه شدم</button></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash;
    if(hash.startsWith('#rank-')) {
        const listId = hash.hash.replace('#rank-', '');
        const btn = document.querySelector(`.r-tab-btn[data-target="${listId}"]`);
        if(btn) {
            showRankList(listId, btn);
        }
    }
});

function showRankList(listId, el) {
    document.querySelectorAll('.r-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.rank-list-view').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('list-' + listId).classList.add('active');
    window.history.replaceState(null, null, '#rank-' + listId);
}

function openTwxInfoModal(e) { if(e) e.stopPropagation(); const m = document.getElementById('twx-info-modal'); m.style.display='flex'; setTimeout(()=>m.classList.add('active'), 10); }
function closeTwxInfoModal() { const m = document.getElementById('twx-info-modal'); m.classList.remove('active'); setTimeout(()=>m.style.display='none', 200); }
</script>
<?php include 'footer.php'; ?>

</body>
</html>
<?php ob_end_flush(); ?>
