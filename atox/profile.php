<?php
ob_start();
session_start();

if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

require 'db.php';
require_once 'gamification.php';


$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($current_user_id === 0) {
    header('Location: index.php');
    exit;
}

if (isset($_POST['ajax_like']) && $current_user_id) {
    $tid = (int)$_POST['tweet_id'];
    $chk = $pdo->prepare("SELECT id FROM likes WHERE tweet_id=? AND user_id=?");
    $chk->execute([$tid, $current_user_id]);
    $is_liked = false;
    
    if ($chk->rowCount() > 0) {
        $pdo->prepare("DELETE FROM likes WHERE tweet_id=? AND user_id=?")->execute([$tid, $current_user_id]);
        $is_unlike = true;
    } else {
        $pdo->prepare("INSERT INTO likes (tweet_id, user_id) VALUES (?, ?)")->execute([$tid, $current_user_id]);
        $is_liked = true;
        $is_unlike = false;
    }
    
    $author_stmt = $pdo->prepare("SELECT user_id FROM tweets WHERE id = ?");
    $author_stmt->execute([$tid]);
    $author_id = $author_stmt->fetchColumn();
    if ($author_id && $author_id != $current_user_id) {
        apply_tweet_like_gamification($pdo, (int)$author_id, $is_unlike);
    }
    
    $c = $pdo->prepare("SELECT COUNT(id) FROM likes WHERE tweet_id=?");
    $c->execute([$tid]);
    
    header('Content-Type: application/json');
    echo json_encode(['liked' => $is_liked, 'count' => (int)$c->fetchColumn()]);
    exit;
}

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : $current_user_id;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$profile_user = $stmt->fetch();
if (!$profile_user) { die("کاربر یافت نشد!"); }

$user_role = $_SESSION['role'] ?? 'user';

$stmt_followers = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$stmt_followers->execute([$profile_id]);
$followers = $stmt_followers->fetchColumn();

$stmt_following = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$stmt_following->execute([$profile_id]);
$following = $stmt_following->fetchColumn();

$stmt_posts = $pdo->prepare("SELECT COUNT(*) FROM tweets WHERE user_id = ?");
$stmt_posts->execute([$profile_id]);
$post_count = $stmt_posts->fetchColumn();

$stmt_likes = $pdo->prepare("SELECT COUNT(l.id) FROM likes l JOIN tweets t ON l.tweet_id = t.id WHERE t.user_id = ?");
$stmt_likes->execute([$profile_id]);
$total_likes_received = $stmt_likes->fetchColumn();

$user_points = (int)($profile_user['points'] ?? 0);
$user_level = (int)($profile_user['level'] ?? 1);
$lvlData = getLvlData($user_level);
$lvl_color = htmlspecialchars($lvlData['c'], ENT_QUOTES, 'UTF-8');
$lvl_name = htmlspecialchars($lvlData['n'], ENT_QUOTES, 'UTF-8');
$lvl_icon = $lvlData['i'];

$rank_stmt = $pdo->prepare("SELECT COUNT(id) + 1 FROM users WHERE points > ?");
$rank_stmt->execute([$user_points]);
$user_rank = $rank_stmt->fetchColumn();

$points_for_current_level = ($user_level - 1) * 5;
$points_for_next_level = $user_level * 5;
$points_in_level = $user_points - $points_for_current_level;
$level_progress_percent = ($points_in_level / 5) * 100;
if ($level_progress_percent > 100) $level_progress_percent = 100;
if ($level_progress_percent < 0) $level_progress_percent = 0;

$is_following = false;
if ($profile_id != $current_user_id) {
    $f_stmt = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1");
    $f_stmt->execute([$current_user_id, $profile_id]);
    $is_following = $f_stmt->rowCount() > 0;
}

$r_stmt = $pdo->prepare("SELECT * FROM resumes WHERE user_id = ?");
$r_stmt->execute([$profile_id]);
$resume = $r_stmt->fetch(PDO::FETCH_ASSOC);

if ($resume && !empty($resume['linkedin'])) {
    $resume['linkedin'] = str_replace(['atoxcomputer.ir/', 'https://atoxcomputer.ir/', 'http://atoxcomputer.ir/'], '', $resume['linkedin']);
}

function pNum($str) {
    return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str);
}

function getJalaliDateShort($date) {
    if (!$date) return 'نامشخص';
    $timestamp = strtotime($date);
    $gy = date('Y', $timestamp); $gm = date('n', $timestamp); $gd = date('j', $timestamp);
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $jy = ($gy<=1600)?0:979; $gy-=($gy<=1600)?621:1600;
    $gy2 = ($gm>2)?($gy+1):$gy;
    $days = (365*$gy) + ((int)(($gy2+3)/4)) - ((int)(($gy2+99)/100)) + ((int)(($gy2+399)/400)) - 80 + $gd + $g_d_m[$gm-1];
    $jy += 33*((int)($days/12053)); $days %= 12053;
    $jy += 4*((int)($days/1461)); $days %= 1461;
    $jy += (int)(($days-1)/365);
    if($days > 365)$days=($days-1)%365;
    $jm = ($days < 186)?1+(int)($days/31):7+(int)(($days-186)/30);
    $jd = ($days < 186)?1+($days%31):1+(($days-186)%30);
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    return pNum($jd) . ' ' . $months[$jm-1] . ' ' . pNum($jy);
}

function getJalaliMonthYear($date) {
    if (!$date) return 'نامشخص';
    $timestamp = strtotime($date);
    $gy = date('Y', $timestamp); $gm = date('n', $timestamp); $gd = date('j', $timestamp);
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $jy = ($gy<=1600)?0:979; $gy-=($gy<=1600)?621:1600;
    $gy2 = ($gm>2)?($gy+1):$gy;
    $days = (365*$gy) + ((int)(($gy2+3)/4)) - ((int)(($gy2+99)/100)) + ((int)(($gy2+399)/400)) - 80 + $gd + $g_d_m[$gm-1];
    $jy += 33*((int)($days/12053)); $days %= 12053;
    $jy += 4*((int)($days/1461)); $days %= 1461;
    $jy += (int)(($days-1)/365);
    if($days > 365)$days=($days-1)%365;
    $jm = ($days < 186)?1+(int)($days/31):7+(int)(($days-186)/30);
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    return $months[$jm-1] . ' ' . pNum($jy);
}

function pTime($date) { return getJalaliDateShort($date) . ' · ' . pNum(date('H:i', strtotime($date))); }

function formatBio($text) {
    if (empty($text)) return '';
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = nl2br($text);
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.*?)__/s', '<em>$1</em>', $text);
    $text = preg_replace('/(?<!\S)((https?:\/\/|www\.)[^\s<]+)/i', '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:var(--x-blue);text-decoration:none;">$1</a>', $text);
    return $text;
}

global $ic_dots, $ic_del, $ic_send, $ic_edit, $ic_reply, $ic_liked, $ic_like, $blue_tick, $ic_msg, $ic_info;

$blue_tick = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg>';
$ic_arrow = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"></path></svg>';
$ic_cal = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;vertical-align:-3px;margin-left:4px"><path d="M7 4V3h2v1h6V3h2v1h1.5C19.89 4 21 5.12 21 6.5v12c0 1.38-1.11 2.5-2.5 2.5h-13C4.12 21 3 19.88 3 18.5v-12C3 5.12 4.12 4 5.5 4H7zm0 2H5.5c-.27 0-.5.22-.5.5v12c0 .28.23.5.5.5h13c.28 0 .5-.22.5-.5v-12c0-.28-.22-.5-.5-.5H17v1h-2V6H9v1H7V6zm0 6h2v-2H7v2zm0 4h2v-2H7v2zm4-4h2v-2h-2v2zm0 4h2v-2h-2v2zm4-4h2v-2h-2v2zm0 4h2v-2h-2v2z"/></svg>';
$ic_link = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;vertical-align:-3px;margin-left:4px"><path d="M18.36 5.64c-1.95-1.96-5.11-1.96-7.07 0L9.88 7.05 8.46 5.64l1.42-1.41c2.73-2.73 7.16-2.73 9.9 0 2.73 2.74 2.73 7.17 0 9.9l-1.41 1.42-1.41-1.42 1.4-1.41c1.17-1.17 1.17-3.08 0-4.25zM5.64 18.36c1.95 1.96 5.11 1.96 7.07 0l1.41-1.41 1.42 1.41-1.42 1.41c-2.73 2.73-7.16 2.73-9.9 0-2.73-2.74-2.73-7.17 0-9.9l1.41-1.42 1.41 1.42-1.4-1.41c-1.17 1.17-1.17 3.08 0 4.25zM9.17 14.83l5.66-5.66 1.41 1.41-5.66 5.66-1.41-1.41z"/></svg>';
$ic_settings = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor;"><path d="M12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5zm0-5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm10.59-1.89l-2.07-.6c-.18-.55-.42-1.07-.7-1.56l1.1-1.89c.14-.24.08-.55-.13-.74l-2.1-2.1c-.19-.19-.51-.25-.75-.12l-1.88 1.11c-.49-.29-1.02-.53-1.57-.7l-.6-2.08c-.06-.28-.31-.48-.6-.48h-2.98c-.29 0-.54.2-.6.48l-.6 2.08c-.55.18-1.08.41-1.57.7L5.34 2.1c-.24-.13-.56-.07-.75.12l-2.1 2.1c-.21.19-.27.5-.13.74l1.1 1.89c-.28.49-.52 1.01-.7 1.56l-2.07.6c-.28.08-.49.33-.49.62v2.97c0 .29.21.54.49.62l2.07.6c.18.55.42 1.07.7 1.56l-1.1 1.89c-.14.24-.08.55.13.74l2.1 2.1c.19.19.51.25.75.12l1.88-1.11c.49.29 1.02.53 1.57.7l.6 2.08c.06.28.31.48.6.48h2.98c.29 0 .54-.2.6-.48l.6-2.08c.55-.18 1.08-.41 1.57-.7l1.88 1.11c.24.13.56.07.75-.12l2.1-2.1c.21-.19.27-.5.13-.74l-1.1-1.89c.28-.49.52-1.01.7-1.56l2.07-.6c.28-.08.49-.33.49-.62V9.23c0-.29-.21-.54-.49-.62z"></path></svg>';


$ic_dots = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>';
$ic_reply = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M1.751 10c0-4.42 3.584-8 8.005-8h4.366c4.49 0 8.129 3.64 8.129 8.13 0 2.96-1.607 5.68-4.196 7.11l-8.054 4.46v-3.69h-.067c-4.49.1-8.183-3.51-8.183-8.01zm8.005-6c-3.317 0-6.005 2.69-6.005 6 0 3.37 2.77 6.08 6.138 6.01l.351-.01h1.761v2.3l5.087-2.81c1.951-1.08 3.163-3.13 3.163-5.36 0-3.39-2.744-6.13-6.129-6.13H9.756z"/></svg>';
$ic_like = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M16.697 5.5c-1.222-.06-2.679.51-3.89 2.16l-.805 1.09-.806-1.09C9.984 6.01 8.526 5.44 7.304 5.5c-1.243.07-2.349.78-2.91 1.91-.552 1.12-.633 2.78.479 4.82 1.074 1.97 3.257 4.27 7.129 6.61 3.87-2.34 6.052-4.64 7.126-6.61 1.111-2.04 1.03-3.7.477-4.82-.561-1.13-1.666-1.84-2.908-1.91zm4.187 7.69c-1.351 2.48-4.001 5.12-8.379 7.67l-.503.3-.504-.3c-4.379-2.55-7.029-5.19-8.382-7.67-1.36-2.5-1.41-4.86-.514-6.67.887-1.79 2.647-2.91 4.601-3.01 1.651-.09 3.368.56 4.798 2.01 1.429-1.45 3.146-2.1 4.796-2.01 1.954.1 3.714 1.22 4.601 3.01.896 1.81.846 4.17-.514 6.67z"/></svg>';
$ic_liked = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#f91880"><path d="M20.884 13.19c-1.351 2.48-4.001 5.12-8.379 7.67l-.503.3-.504-.3c-4.379-2.55-7.029-5.19-8.382-7.67-1.36-2.5-1.41-4.86-.514-6.67.887-1.79 2.647-2.91 4.601-3.01 1.651-.09 3.368.56 4.798 2.01 1.429-1.45 3.146-2.1 4.796-2.01 1.954.1 3.714 1.22 4.601 3.01.896 1.81.846 4.17-.514 6.67z"/></svg>';
$ic_del = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M15 3h6v2h-2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5H3V3h6V2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1zM7 5v14h10V5H7zm2 2h2v10H9V7zm4 0h2v10h-2V7z"/></svg>';
$ic_edit = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
$ic_send = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';
$ic_msg = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M1.998 5.5c0-1.381 1.119-2.5 2.5-2.5h15c1.381 0 2.5 1.119 2.5 2.5v13c0 1.381-1.119 2.5-2.5 2.5h-15c-1.381 0-2.5-1.119-2.5-2.5v-13zm2.5-.5c-.276 0-.5.224-.5.5v2.764l8 3.638 8-3.636V5.5c0-.276-.224-.5-.5-.5h-15zm15.5 5.463l-8 3.636-8-3.638V18.5c0 .276.224.5.5.5h15c.276 0 .5-.224.5-.5v-8.037z"></path></svg>';
$ic_info = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>';

require_once 'tweet_box.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?= htmlspecialchars($profile_user['name'], ENT_QUOTES, 'UTF-8') ?> - آتوکس</title>
<script>
    if(localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }
</script>
<style>
@font-face { font-family: 'MyCustomFont'; src: url('fonts/font.ttf') format('truetype'); font-weight: normal; font-style: normal; font-display: swap; }
@font-face { font-family: 'MyCustomFont'; src: url('fonts/font-bold.ttf') format('truetype'); font-weight: bold; font-style: normal; font-display: swap; }
* { margin:0; padding:0; box-sizing:border-box; font-family: 'MyCustomFont', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; }

:root { 
    --x-blue: #1d9bf0; --x-black: #0f1419; --x-gray: #536471; --x-border: #eff3f4; --x-bg: #ffffff; 
    --x-bg-trans: rgba(255,255,255,0.85); --x-hover: rgba(15,20,25,0.05); 
    --x-hover-b: rgba(29,155,240,0.1); --x-hover-r: rgba(249,24,128,0.1);
    --glass-bg: rgba(255,255,255,0.7); --glass-border: rgba(0,0,0,0.05);
    --shadow-soft: 0 4px 15px rgba(0,0,0,0.04);
}
.dark { 
    --x-black: #e7e9ea; --x-gray: #71767b; --x-border: #2f3336; --x-bg: #000000; 
    --x-bg-trans: rgba(0,0,0,0.85); --x-hover: rgba(255,255,255,0.08);
    --glass-bg: rgba(21,24,28,0.7); --glass-border: rgba(255,255,255,0.1);
    --shadow-soft: 0 4px 15px rgba(255,255,255,0.02);
}
body { background: var(--x-bg); color: var(--x-black); -webkit-tap-highlight-color: transparent; overflow-x: hidden; transition: background 0.3s, color 0.3s; }
a, button { text-decoration: none; color: inherit; background: none; border: none; cursor: pointer; outline: none; }
.no-select { user-select: none; -webkit-user-select: none; }

.app { display: flex; justify-content: center; min-height: 100vh; max-width: 1250px; margin: 0 auto; }
.main { width: 100%; max-width: 600px; border-left: 1px solid var(--x-border); border-right: 1px solid var(--x-border); padding-bottom: 80px; position: relative; }

.p-acts { display: flex; gap: 8px; align-items: center; }
.btn-follow { background: var(--x-black); color: var(--x-bg); padding: 0 20px; font-weight: bold; min-height: 36px; border-radius: 999px; transition: 0.2s; font-size: 15px;}
.btn-unfollow { position: relative; background: transparent; color: var(--x-black); border: 1px solid var(--x-border); padding: 0 20px; font-weight: bold; min-height: 36px; border-radius: 999px; transition: 0.2s; font-size: 15px; overflow: hidden;}
.btn-unfollow span { transition: opacity 0.2s; }
.btn-unfollow:hover { border-color: #f91880; color: #f91880; background: var(--x-hover-r); }
.btn-unfollow:hover span { opacity: 0; }
.btn-unfollow::after { content: 'لغو دنبال کردن'; position: absolute; left: 0; top: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; }
.btn-unfollow:hover::after { opacity: 1; }
.btn-msg { width: 36px; height: 36px; border-radius: 50%; border: 1px solid var(--x-border); display: flex; align-items: center; justify-content: center; color: var(--x-black); transition: 0.2s; }
.btn-msg:hover { background: var(--x-hover); border-color: var(--x-gray); }
.settings-btn-mod { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--x-border); padding: 6px 16px; border-radius: 99px; font-size: 14px; font-weight: bold; color: var(--x-black); transition: 0.2s; }
.settings-btn-mod:hover { background: var(--x-hover); }

.hdr { position: sticky; top: 0; background: var(--x-bg-trans); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); z-index: 10; padding: 10px 15px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--x-border); }
.hdr-left { display: flex; align-items: center; gap: 15px; }

.cover { height: 200px; background: linear-gradient(135deg, #1d9bf0, #8A2BE2); width: 100%; object-fit: cover; display: block; }
.p-info { padding: 12px 16px; position: relative; }
.avt-wrap { display: flex; justify-content: space-between; align-items: flex-end; margin-top: -80px; position: relative; z-index: 2; margin-bottom: 15px;}
.avt-l { width: 135px; height: 135px; border-radius: 50%; border: 4px solid var(--x-bg); background: var(--x-border); object-fit: cover; transition: transform 0.3s, box-shadow 0.3s; cursor: pointer; }

.name-line { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
.u-name-l { font-size: 22px; font-weight: 900; display: flex; align-items: center; gap: 4px;}
.u-handle-l { font-size: 15px; color: var(--x-gray); display: flex; align-items: center; gap: 12px; }
.p-stats-inline { display: flex; gap: 12px; font-size: 14px; color: var(--x-gray); align-items: center; }
.p-stats-inline a { color: var(--x-gray); display: flex; gap: 4px; }
.p-stats-inline a:hover { text-decoration: underline; }
.p-stats-inline b { color: var(--x-black); }

.meta-badges { display: flex; align-items: center; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
.lvl-badge-mod { background: var(--x-hover); border: 1px solid var(--x-border); padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; color: var(--x-gray); }
.job-badge-mod { background: rgba(29, 155, 240, 0.08); border: 1px solid rgba(29, 155, 240, 0.2); color: var(--x-blue); padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 4px;}

.p-bio { margin-top: 14px; font-size: 15px; line-height: 1.5; word-wrap: break-word; }
.p-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; color: var(--x-gray); font-size: 14px; align-items: center;}

.gami-card {
    background: var(--glass-bg); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--glass-border); border-radius: 16px; padding: 16px; margin-top: 16px;
    box-shadow: var(--shadow-soft); display: flex; flex-direction: column; gap: 14px;
    position: relative; border-right: 4px solid var(--gami-color, var(--x-blue));
}
.gami-header { display: flex; justify-content: space-between; align-items: center; }
.gami-level-sec { display: flex; align-items: center; gap: 10px; }
.gami-icon-wrap { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.gami-level-title { font-size: 18px; font-weight: 900; }
.gami-rank-badge { background: var(--x-hover); color: var(--x-black); font-size: 14px; font-weight: 900; padding: 6px 14px; border-radius: 99px; display: flex; align-items: center; gap: 4px;}

.gami-progress-wrapper { display: flex; flex-direction: column; gap: 6px; }
.gami-progress-info { display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; color: var(--x-gray); }
.gami-progress-bar { width: 100%; height: 8px; background: var(--x-border); border-radius: 8px; overflow: hidden; }
.gami-progress-fill { height: 100%; border-radius: 8px; transition: width 0.5s ease-in-out; }

.gami-stats-grid {
    display: flex;
    flex-wrap: nowrap;
    width: 100%;
    gap: 5px;
}

.gami-stat-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 0;
}

.gami-stat-val {
    font-size: 14px;
    font-weight: bold;
    white-space: nowrap;
}

.gami-stat-lbl {
    font-size: 11px;
    color: var(--x-gray, #536471);
    margin-top: 2px;
}
.img-viewer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); z-index: 9999; display: flex; justify-content: center; align-items: center; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
.img-viewer-overlay.active { opacity: 1; pointer-events: auto; }
.img-viewer-content { max-width: 90%; max-height: 85vh; border-radius: 20px; object-fit: contain; box-shadow: 0 10px 40px rgba(0,0,0,0.5); transform: scale(0.9); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.img-viewer-overlay.active .img-viewer-content { transform: scale(1); }
.img-viewer-close { position: absolute; top: 20px; right: 20px; color: #fff; background: rgba(255,255,255,0.15); width: 44px; height: 44px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 28px; cursor: pointer; transition: 0.2s; backdrop-filter: blur(5px); }
.img-viewer-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }

.tw-wrapper { background: var(--glass-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid var(--glass-border); border-radius: 16px; margin: 12px 15px; box-shadow: var(--shadow-soft); overflow: hidden; }
.glass-card { margin: 0 !important; border: none !important; box-shadow: none !important; border-radius: 0 !important; background: transparent !important; backdrop-filter: none !important;}

.menu-wrap { position: relative; margin-right: auto; }
.menu-btn { color: var(--x-gray); width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.2s; cursor: pointer; }
.menu-btn:hover { background: var(--x-hover-b); color: var(--x-blue); }
.menu-content { display: none; position: absolute; left: 0; top: 100%; background: var(--x-bg); border: 1px solid var(--x-border); border-radius: 12px; box-shadow: var(--shadow-soft); z-index: 10; min-width: 140px; overflow: hidden; }
.menu-content.show { display: block; animation: fadeIn 0.1s; }
.menu-item { padding: 12px 15px; display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--x-black); cursor: pointer; width: 100%; text-align: right; background: none; border: none; font-family: inherit;}
.menu-item:hover { background: var(--x-hover); }
.menu-item.danger { color: #f91880; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1000; display: none; justify-content: center; align-items: center; }
.modal-overlay.active { display: flex; animation: fadeIn 0.2s; }
.edit-modal { background: var(--x-bg); width: 90%; max-width: 450px; border-radius: 20px; padding: 20px; transform: scale(0.95); transition: 0.2s; box-shadow: 0 10px 30px rgba(0,0,0,0.2); border: 1px solid var(--x-border); }
.modal-overlay.active .edit-modal { transform: scale(1); }
.edit-textarea { width: 100%; background: var(--x-hover); border: 1px solid var(--x-border); border-radius: 12px; padding: 15px; color: var(--x-black); font-size: 16px; min-height: 120px; resize: none; margin-bottom: 15px; outline: none; transition: border-color 0.2s; }
.edit-textarea:focus { border-color: var(--x-blue); }
.modal-btn { display: block; width: 100%; padding: 12px; margin-bottom: 8px; border-radius: 99px; font-size: 15px; font-weight: bold; cursor: pointer; text-align: center; }
.modal-btn.primary { background: var(--x-black); color: var(--x-bg); border: none; }
.modal-btn.cancel { background: transparent; color: var(--x-black); border: 1px solid var(--x-border); }

@media(max-width: 600px) {
    .main { border: none; }
    .cover { height: 140px; }
    .avt-l { width: 100px; height: 100px; }
    .avt-wrap { margin-top: -55px; }
    .tw-wrapper { margin: 10px; border-radius: 14px;}
    .gami-stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px;}
}
</style>
</head>
<body>
<?php if(file_exists('header.php')) include 'header.php'; ?>

<div class="app">
    <main class="main">
        <div class="hdr">
            <div class="hdr-left">
                <button onclick="history.back()" style="padding:8px; border-radius:50%; margin-right:4px; transition:0.2s;" onmouseover="this.style.background='var(--x-hover)'" onmouseout="this.style.background='transparent'">
                    <?=$ic_arrow?>
                </button>
                <div style="font-size:20px; font-weight:900; display:flex; align-items:center;">
                    <?=htmlspecialchars($profile_user['name'], ENT_QUOTES, 'UTF-8')?> <?=!empty($profile_user['is_verified']) ? $blue_tick : ''?>
                </div>
            </div>
        </div>

        <?php if(!empty($profile_user['cover'])): ?>
            <img src="<?=htmlspecialchars($profile_user['cover'], ENT_QUOTES, 'UTF-8')?>" class="cover">
        <?php else: ?>
            <img src="uploads/cover.png" class="cover">
        <?php endif; ?>

        <div class="p-info">
            <div class="avt-wrap">
                <?php if(!empty($profile_user['avatar'])): ?>
                    <img src="<?=htmlspecialchars($profile_user['avatar'], ENT_QUOTES, 'UTF-8')?>" class="avt-l" style="box-shadow: 0 0 0 3px <?=$lvl_color?>, 0 0 15px <?=$lvl_color?>50;" onclick="openImageViewer(this.src)" title="نمایش تصویر">
                <?php else: ?>
                    <img src="uploads/avatar1.png" class="avt-l" style="box-shadow: 0 0 0 3px <?=$lvl_color?>, 0 0 15px <?=$lvl_color?>50;" onclick="openImageViewer(this.src)" title="نمایش تصویر">
                <?php endif; ?>

                <div class="p-acts">
                    <?php if ($profile_id == $current_user_id): ?>
                        <a href="settings.php" class="settings-btn-mod"><?=$ic_settings?> تنظیمات</a>
                    <?php else: ?>
                        <a href="chat.php?user_id=<?=$profile_id?>" class="btn-msg" title="ارسال مستقیم پیام"><?=$ic_msg?></a>
                        
                        <form onsubmit="ajaxFollow(event, this)" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8')?>">
                            <input type="hidden" name="following_id" value="<?=$profile_id?>">
                            <button type="submit" class="<?= $is_following ? 'btn-unfollow' : 'btn-follow' ?>">
                                <span><?= $is_following ? 'دنبال شده' : 'دنبال کردن' ?></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="name-line" style="justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <div>
                    <div class="u-name-l">
                        <?=htmlspecialchars($profile_user['name'], ENT_QUOTES, 'UTF-8')?> 
                        <?=!empty($profile_user['is_verified']) ? $blue_tick : ''?>
                    </div>
                    
                    <div class="meta-badges" style="margin-top: 6px; margin-bottom: 0;">
                        <?php if(!empty($resume['job'])): ?>
                            <span class="job-badge-mod"><?=htmlspecialchars($resume['job'], ENT_QUOTES, 'UTF-8')?></span>
                        <?php endif; ?>
                        <span dir="ltr" style="font-size: 15px; color: var(--x-gray); margin-right: 6px; font-weight: 500;">@<?=htmlspecialchars($profile_user['username'], ENT_QUOTES, 'UTF-8')?></span>
                    </div>
                </div>
                
                <div class="p-stats-inline" style="margin-top: 6px; gap: 15px;">
                    <a href="following.php?id=<?=$profile_id?>"><b><?=pNum($following)?></b> دنبال‌شونده</a>
                    <a href="followers.php?id=<?=$profile_id?>"><b><?=pNum($followers)?></b> دنبال‌کننده</a>
                </div>
            </div>

            <?php if(!empty($profile_user['bio'])): ?>
                <div class="p-bio"><?=formatBio($profile_user['bio'])?></div>
            <?php endif; ?>

            <div class="p-meta">
                <div style="display:flex; align-items:center;"><?=$ic_cal?> پیوستن در <?=getJalaliMonthYear($profile_user['created_at'])?></div>
                <?php if(!empty($resume['location'])): ?>
                    <div style="display:flex; align-items:center;"><svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;vertical-align:-3px;margin-left:4px"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg><?=htmlspecialchars($resume['location'], ENT_QUOTES, 'UTF-8')?></div>
                <?php endif; ?>
            </div>

            <div class="gami-card" style="--gami-color: <?=$lvl_color?>;">
                <div class="gami-header">
                    <div class="gami-level-sec">
                        <div class="gami-icon-wrap" style="background: <?=$lvl_color?>15; color: <?=$lvl_color?>; box-shadow: 0 0 10px <?=$lvl_color?>30;">
                            <?=$lvl_icon?>
                        </div>
                        <div class="gami-level-title" style="color: <?=$lvl_color?>;"><?=$lvl_name?></div>
                    </div>
                    <div class="gami-rank-badge" style="color: <?=$lvl_color?>;">#<?=pNum($user_rank)?></div>
                </div>
                
                <div class="gami-progress-wrapper">
                    <div class="gami-progress-info">
                        <span>سطح <?=pNum($user_level)?></span>
                        <span><?=pNum($user_points)?> / <?=pNum($points_for_next_level)?></span>
                        <span>سطح <?=pNum($user_level + 1)?></span>
                    </div>
                    <div class="gami-progress-bar">
                        <div class="gami-progress-fill" style="width: <?=$level_progress_percent?>%; background: <?=$lvl_color?>;"></div>
                    </div>
                </div>

                <div class="gami-stats-grid">
                    <div class="gami-stat-item">
                        <span class="gami-stat-val"><?=pNum($user_level)?></span>
                        <span class="gami-stat-lbl">سطح</span>
                    </div>
                    <div class="gami-stat-item">
                        <span class="gami-stat-val"><?=pNum($user_points)?></span>
                        <span class="gami-stat-lbl">امتیاز کل</span>
                    </div>
                    <div class="gami-stat-item">
                        <span class="gami-stat-val"><?=pNum($post_count)?></span>
                        <span class="gami-stat-lbl">توییت‌ها</span>
                    </div>
                    <div class="gami-stat-item">
                        <span class="gami-stat-val"><?=pNum($total_likes_received)?></span>
                        <span class="gami-stat-lbl">لایک‌ها</span>
                    </div>
                </div>
            </div>
            
        </div>

        <?php include 'profile_feed.php'; ?>

    </main>
</div>

<div class="img-viewer-overlay" id="imgViewerModal" onclick="closeImageViewer()">
    <div class="img-viewer-close" onclick="closeImageViewer()">×</div>
    <img src="" id="imgViewerContent" class="img-viewer-content" onclick="event.stopPropagation()">
</div>


<script>
function openImageViewer(src) {
    let viewer = document.getElementById('imgViewerModal');
    let img = document.getElementById('imgViewerContent');
    img.src = src;
    viewer.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeImageViewer() {
    let viewer = document.getElementById('imgViewerModal');
    viewer.classList.remove('active');
    document.body.style.overflow = 'auto';
}

function ajaxFollow(e, form) {
    e.preventDefault();
    let btn = form.querySelector('button');
    let formData = new FormData(form);
    
    let isUnfollowing = btn.classList.contains('btn-unfollow');
    if (isUnfollowing) {
        btn.classList.remove('btn-unfollow');
        btn.classList.add('btn-follow');
        btn.innerHTML = '<span>دنبال کردن</span>';
    } else {
        btn.classList.remove('btn-follow');
        btn.classList.add('btn-unfollow');
        btn.innerHTML = '<span>دنبال شده</span>';
    }

    fetch('actions.php?action=follow', { method: 'POST', body: formData }).catch(err => console.log('Error:', err));
}

function tg(id) {
    let el = document.getElementById(id);
    if(el) el.style.display = (el.style.display === 'block') ? 'none' : 'block';
}
function tglMenu(id, e) {
    e.stopPropagation();
    let m = document.querySelector('#' + id + ' .menu-content');
    if(!m) return;
    let isShowing = m.classList.contains('show');
    document.querySelectorAll('.menu-content').forEach(c => c.classList.remove('show'));
    if(!isShowing) m.classList.add('show');
}
document.addEventListener('click', () => {
    document.querySelectorAll('.menu-content').forEach(c => c.classList.remove('show'));
});

function oEd(actionStr, idName, idVal, txtId, e) {
    if(e) e.stopPropagation();
    document.getElementById('editActionName').name = 'action';
    document.getElementById('editActionName').value = actionStr;
    document.getElementById('editItemId').name = idName;
    document.getElementById('editItemId').value = idVal;
    
    let txtEl = document.getElementById(txtId);
    let val = txtEl ? (txtEl.value || txtEl.innerText || txtEl.textContent) : '';
    document.getElementById('editTextarea').value = val.trim();
    
    document.getElementById('editModal').classList.add('active');
    document.querySelectorAll('.menu-content').forEach(c => c.classList.remove('show'));
}
</script>
<?php if(file_exists('footer.php')) include 'footer.php'; ?>

</body>
</html>
<?php ob_end_flush(); ?>
