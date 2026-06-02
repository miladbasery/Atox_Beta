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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require 'db.php';

$project_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$uid = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'user';
$is_admin = ($user_role === 'admin' || (isset($_SESSION['username']) && $_SESSION['username'] === 'milad'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('خطای اعتبارسنجی درخواست.');
    }

    if (isset($_POST['local_action'])) {
        $action = $_POST['local_action'];
        
        if ($action === 'add_project_member') {
            $username = trim($_POST['username'] ?? '');
            if ($username) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $target_user_id = $stmt->fetchColumn();
                
                if ($target_user_id) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM project_members WHERE project_id = ? AND user_id = ?");
                    $check->execute([$project_id, $target_user_id]);
                    if ($check->fetchColumn() == 0) {
                        $pdo->prepare("INSERT INTO project_members (project_id, user_id) VALUES (?, ?)")->execute([$project_id, $target_user_id]);
                    }
                }
            }
            header("Location: project.php?id=" . $project_id);
            exit;
        }
        
        if ($action === 'remove_project_member') {
            $target_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if ($target_user_id) {
                $pdo->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ?")->execute([$project_id, $target_user_id]);
            }
            header("Location: project.php?id=" . $project_id);
            exit;
        }
    }
}

$stmt = $pdo->prepare("SELECT p.*, k.name as kanoon_name FROM projects p JOIN kanoons k ON p.kanoon_id = k.id WHERE p.id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) die('پروژه یافت نشد.');

$img_stmt = $pdo->prepare("SELECT id, image_path FROM project_images WHERE project_id = ? ORDER BY id ASC");
$img_stmt->execute([$project_id]);
$project_images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

$members_stmt = $pdo->prepare("
    SELECT u.id, u.name, u.username, u.avatar, u.level, u.is_verified 
    FROM project_members pm 
    JOIN users u ON pm.user_id = u.id 
    WHERE pm.project_id = ?
");
$members_stmt->execute([$project_id]);
$team = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

function pNum($str) { return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str); }

function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 39) / 100)) + ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * ((int)($days / 12053));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    $jy += (int)(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return array($jy, $jm, $jd);
}

function toJalaliDateTime($date) {
    if(empty($date)) return '';
    $ts = strtotime($date);
    $gy = (int)date('Y', $ts);
    $gm = (int)date('m', $ts);
    $gd = (int)date('d', $ts);
    
    list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);
    $months = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    $time = date('H:i', $ts);
    
    return pNum($jd) . ' ' . $months[$jm] . ' ' . pNum($jy) . ' / ' . pNum($time);
}

function getImg($path, $name, $is_blue = false) {
    if (!empty($path)) {
        if (strpos($path, 'http') === 0) return htmlspecialchars($path);
        if (file_exists($path) && !is_dir($path)) return htmlspecialchars($path);
        if (file_exists('uploads/' . $path) && !is_dir('uploads/' . $path)) return 'uploads/' . htmlspecialchars($path);
    }
    $bg = $is_blue ? '0D8cd7&color=fff' : 'random';
    return "https://ui-avatars.com/api/?name=".urlencode($name)."&background=".$bg;
}

function formatText($text) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.-]*(\?\S+)?)?)?)@i', '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-link">$1</a>', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<b>$1</b>', $text);
    $text = preg_replace('/\*([^\*]+)\*/s', '<i>$1</i>', $text);
    $text = preg_replace('/__(.*?)__/s', '<i>$1</i>', $text);
    $text = preg_replace('/_([^_]+)_/s', '<i>$1</i>', $text);
    $text = str_replace(["\r\n", "\r", "\n"], "\n", $text);
    return nl2br($text);
}
$ic_back = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"/></svg>';
$ic_share = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M12 2.59l5.7 5.7-1.41 1.42L13 6.41V16h-2V6.41l-3.3 3.3-1.41-1.42L12 2.59zM21 15l-.02 3.51c0 1.38-1.12 2.49-2.5 2.49H5.5C4.11 21 3 19.88 3 18.5V15h2v3.5c0 .28.22.5.5.5h12.98c.28 0 .5-.22.5-.5L19 15h2z"/></svg>';
$ic_edit = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M19.4 7.34L16.66 4.6c-.39-.39-1.02-.39-1.41 0L3 16.84V19.6c0 .55.45 1 1 1h2.76l12.24-12.24c.39-.39.39-1.02 0-1.42zM5 18.6V17.2l9.83-9.83 1.41 1.41L6.41 18.6H5z"/></svg>';
$ic_delete = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M16 9v10H8V9h8m-1.5-6h-5l-1 1H5v2h14V4h-3.5l-1-1zM18 7H6v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7z"/></svg>';
$ic_plus = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M11 11V4h2v7h7v2h-7v7h-2v-7H4v-2h7z"/></svg>';
$ic_github = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.462-1.11-1.462-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.379.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.161 22 16.416 22 12c0-5.523-4.477-10-10-10z"/></svg>';
$ic_link = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M11.96 14.945c-.067 0-.136-.01-.203-.027-1.13-.318-2.097-.986-2.795-1.932-.832-1.125-1.176-2.508-.968-3.893s.942-2.605 2.068-3.438l3.53-2.608c2.322-1.716 5.61-1.224 7.33 1.1.83 1.127 1.175 2.51.967 3.895s-.943 2.605-2.07 3.439l-1.48 1.094c-.333.246-.804.175-1.05-.158-.246-.334-.176-.804.158-1.05l1.48-1.095c.803-.592 1.327-1.463 1.476-2.45.148-.988-.098-1.975-.69-2.778-1.225-1.656-3.572-2.01-5.23-.784l-3.53 2.608c-.802.593-1.326 1.464-1.475 2.45-.15.99.097 1.975.69 2.778.498.675 1.187 1.15 1.992 1.377.4.114.633.528.52.928-.092.33-.394.547-.722.547z"/><path d="M7.27 22.054c-1.61 0-3.197-.735-4.225-2.125-.832-1.127-1.176-2.51-.968-3.894s.943-2.605 2.07-3.438l1.478-1.094c.334-.245.805-.175 1.05.158s.177.804-.157 1.05l-1.48 1.095c-.803.593-1.326 1.464-1.475 2.45-.148.99.097 1.975.69 2.778 1.225 1.657 3.57 2.01 5.23.785l3.528-2.608c1.658-1.225 2.01-3.57.785-5.23-.498-.674-1.187-1.15-1.992-1.376-.4-.113-.633-.527-.52-.927.112-.4.528-.63.926-.522 1.13.318 2.096.986 2.794 1.932 1.717 2.324 1.224 5.612-1.1 7.33l-3.53 2.608c-.933.693-2.023 1.026-3.105 1.026z"/></svg>';
$ic_verified = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" style="display:inline-block;vertical-align:-3px;"><path d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#1d9bf0"></path></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?=htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8')?> - پروژه</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { 
    --x-blue: #1d9bf0; --x-black: #0f1419; --x-gray: #536471; --x-border: #eff3f4; 
    --x-bg: #fff; --x-bg-trans: rgba(255,255,255,0.85); --x-hover: rgba(15,20,25,0.05); 
    --x-hover-b: rgba(29,155,240,0.1); --x-modal: rgba(0,0,0,0.4); 
    --glass: rgba(255,255,255,0.7); --glass-border: rgba(255,255,255,0.3);
}
.dark { 
    --x-black: #e7e9ea; --x-gray: #71767b; --x-border: #2f3336; 
    --x-bg: #000; --x-bg-trans: rgba(0,0,0,0.8); --x-hover: rgba(255,255,255,0.05); 
    --x-modal: rgba(255,255,255,0.1);
    --glass: rgba(22, 24, 28, 0.7); --glass-border: rgba(255,255,255,0.05);
}

*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,sans-serif;}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent; overflow-x:hidden;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}

.app { display:flex; justify-content:center; min-height:100vh; }
.main { width:100%; max-width:650px; position:relative; padding-bottom:80px; }

.hdr { position:sticky; top:0; background:var(--x-bg-trans); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); z-index:10; border-bottom:1px solid var(--x-border); padding:12px 16px; display:flex; align-items:center; gap:15px; }
.vt-back { width:36px; height:36px; border-radius:50%; display:flex; justify-content:center; align-items:center; transition:0.2s; cursor:pointer;}
.vt-back:hover { background:var(--x-hover); }
.hdr-title { font-size:18px; font-weight:800; flex:1; }

.info-box { margin:16px; background:var(--glass); backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); border:1px solid var(--glass-border); border-radius:20px; padding:20px; box-shadow:0 4px 24px rgba(0,0,0,0.04); }
.p-kanoon { font-size:13px; color:var(--x-gray); margin-bottom:8px; display:inline-block; background:var(--x-hover); padding:4px 10px; border-radius:99px; font-weight:bold;}
.p-title { font-size:24px; font-weight:900; line-height:1.3; margin-bottom:10px; }
.p-date { font-size:13px; color:var(--x-gray); margin-bottom:16px; display:flex; align-items:center; gap:5px; }
.p-desc { font-size:15px; line-height:1.8; color:var(--x-black); word-break:break-word; }
.text-link { color:var(--x-blue); text-decoration:none; word-break:break-all; }
.text-link:hover { text-decoration:underline; }

.carousel-wrapper { margin: 16px; position: relative; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid var(--glass-border); user-select: none; touch-action: pan-y; }
.carousel-track { display: flex; transition: transform 0.4s ease-in-out; cursor: grab; }
.carousel-track:active { cursor: grabbing; }
.carousel-slide { min-width: 100%; position: relative; }
.carousel-slide img { width: 100%; aspect-ratio: 16/10; object-fit: cover; display: block; pointer-events: auto; cursor:zoom-in; }
.carousel-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: #fff; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 5; cursor: pointer; border: none; font-size: 18px; transition: 0.2s; }
.carousel-nav:hover { background: rgba(0,0,0,0.8); }
.carousel-nav.prev { right: 10px; } 
.carousel-nav.next { left: 10px; }
.carousel-dots { position: absolute; bottom: 15px; left: 0; right: 0; display: flex; justify-content: center; gap: 6px; z-index: 5; }
.carousel-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.4); cursor: pointer; transition: 0.2s; }
.carousel-dot.active { background: #fff; width: 12px; }

.btn-delete-img { position: absolute; top: 10px; left: 10px; background: rgba(249, 24, 128, 0.9); color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; opacity: 0; transform: scale(0.8); transition: 0.2s; z-index: 10; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
.carousel-slide:hover .btn-delete-img { opacity: 1; transform: scale(1); }
.btn-delete-img:hover { background: #f91880; transform: scale(1.1) !important; }

.links-row { display:flex; gap:10px; margin-top:20px; flex-wrap:wrap;}
.btn-link { flex:1; min-width:140px; display:flex; justify-content:center; align-items:center; gap:8px; padding:12px; border-radius:14px; font-weight:bold; font-size:14px; transition:0.2s; border:1px solid var(--x-border); background:var(--x-bg); }
.btn-link:hover { background:var(--x-hover); }
.btn-link.main-link { background:var(--x-blue); color:#fff; border:none; }
.btn-link.main-link:hover { background:#1a8cd8; }

.admin-toolbar { display:flex; gap:10px; margin:0 16px 16px; padding:12px; background:var(--x-hover-b); border:1px dashed var(--x-blue); border-radius:16px; align-items:center; flex-wrap:wrap; }
.btn-admin { padding:8px 14px; border-radius:99px; font-size:13px; font-weight:bold; display:inline-flex; align-items:center; gap:5px; background:var(--x-bg); color:var(--x-black); border:1px solid var(--x-border); transition:0.2s; }
.btn-admin:hover { background:var(--x-hover); }
.btn-admin.danger { color:#f91880; border-color:rgba(249,24,128,0.3); }
.btn-admin.danger:hover { background:rgba(249,24,128,0.1); }
.btn-admin.primary { background:var(--x-black); color:var(--x-bg); border:none; }

.section-title { font-size:18px; font-weight:900; margin:10px 16px 15px; }
.members-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px; padding:0 16px; }
.mem-card { display:flex; flex-direction:column; align-items:center; text-align:center; padding:16px 10px; background:var(--glass); border:1px solid var(--x-border); border-radius:20px; transition:0.2s; text-decoration:none; color:inherit; position:relative; overflow:hidden;}
.mem-card:hover { background:var(--x-hover); transform:translateY(-2px); }
.mem-avatar { width:64px; height:64px; border-radius:50%; object-fit:cover; margin-bottom:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
.mem-name-row { display:flex; justify-content:center; align-items:center; gap:5px; width:100%; margin-bottom:4px; flex-wrap:wrap;}
.mem-name { font-size:14px; font-weight:bold; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:flex; align-items:center; gap:3px;}
.mem-badge { font-size:11px; background:var(--x-hover-b); color:var(--x-blue); padding:2px 6px; border-radius:6px; font-weight:bold; flex-shrink:0;}
.mem-user { font-size:12px; color:var(--x-gray); width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; direction:ltr;}
.btn-remove-mem { position:absolute; top:8px; left:8px; width:24px; height:24px; border-radius:50%; background:var(--x-bg); border:1px solid var(--x-border); color:#f91880; display:flex; align-items:center; justify-content:center; z-index:2; opacity:0.8;}
.btn-remove-mem:hover { opacity:1; background:rgba(249,24,128,0.1); }

.mod{display:none;position:fixed;inset:0;background:var(--x-modal);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px);}
.m-c{position:relative;background:var(--x-bg);border-radius:24px;width:90%;max-width:400px;padding:24px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:p .3s cubic-bezier(0.175, 0.885, 0.32, 1.275); max-height: 90vh; overflow-y: auto;}
.m-hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--x-border);padding-bottom:15px;margin-bottom:15px;}
.input-ui{width:100%;padding:12px;border:1px solid var(--x-border);border-radius:12px;font-size:15px;margin-bottom:12px;background:var(--x-bg);color:var(--x-black);outline:none;box-sizing:border-box;}
.input-ui:focus{border-color:var(--x-blue);}
.btn-submit{background:var(--x-black);color:var(--x-bg);border:none;padding:12px;border-radius:99px;font-weight:bold;font-size:15px;cursor:pointer;width:100%;transition:0.2s;}
.btn-submit:hover{opacity:0.8;}
.btn-submit.danger{background:#f91880; color:#fff;}
@keyframes p{0%{transform:translateY(30px) scale(0.9);opacity:0}100%{transform:translateY(0) scale(1);opacity:1}}

.search-res { position:absolute; width:100%; background:var(--x-bg); border:1px solid var(--x-border); border-radius:12px; max-height:200px; overflow-y:auto; z-index:10; display:none; box-shadow:0 5px 15px rgba(0,0,0,0.1); margin-top:-8px; margin-bottom:12px; }
.s-item { padding:10px; border-bottom:1px solid var(--x-border); cursor:pointer; display:flex; align-items:center; gap:10px; transition:0.2s;}
.s-item:hover { background:var(--x-hover); }
.s-item img { width:36px; height:36px; border-radius:50%; object-fit:cover; }

.lb-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:9999; justify-content:center; align-items:center; animation:fadeIn 0.2s; }
.lb-overlay img { max-width:90%; max-height:90vh; border-radius:12px; object-fit:contain; box-shadow:0 0 30px rgba(0,0,0,0.5); }
.lb-close { position:absolute; top:20px; right:20px; color:#fff; font-size:40px; cursor:pointer; width:50px; height:50px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.1); border-radius:50%; transition:0.2s; }
.lb-close:hover { background:rgba(255,255,255,0.2); }
@keyframes fadeIn { from{opacity:0;} to{opacity:1;} }

@media(min-width:650px) { 
    .main { border-left:1px solid var(--x-border); border-right:1px solid var(--x-border); } 
    .carousel-slide img { max-height: 400px; object-fit: cover; }
}
</style>
</head>
<body>

<div class="app">
    <main class="main">
        <?php include 'header.php'; ?>
        
        <div class="hdr">
            <div class="vt-back" onclick="window.location.href='university.php?id=<?=htmlspecialchars($project['kanoon_id'], ENT_QUOTES, 'UTF-8')?>&tab=projects'"><?=$ic_back?></div>
            <div class="hdr-title">جزئیات پروژه</div>
            <div class="vt-back" onclick="shareProject()" title="اشتراک‌گذاری"><?=$ic_share?></div>
        </div>

        <?php if(!empty($project_images)): ?>
        <div class="carousel-wrapper" id="carouselWrapper">
            <div class="carousel-track" id="carouselTrack">
                <?php foreach($project_images as $index => $img): 
                    $img_url = getImg($img['image_path'], $project['name'], $index === 0);
                ?>
                <div class="carousel-slide">
                    <img src="<?=$img_url?>" alt="Project Image" onclick="openLightbox(this.src)">
                    <?php if($is_admin): ?>
                        <form action="actions.php" method="POST" onsubmit="return confirm('آیا از حذف این عکس مطمئن هستید؟ این کار غیرقابل بازگشت است.');" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;">
                            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
                            <input type="hidden" name="action" value="delete_project_image">
                            <input type="hidden" name="image_id" value="<?=$img['id']?>">
                            <input type="hidden" name="project_id" value="<?=$project_id?>">
                            <button type="submit" class="btn-delete-img" title="حذف این عکس" style="pointer-events:auto;">✕</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if(count($project_images) > 1): ?>
                <button class="carousel-nav prev" onclick="moveSlide(-1)">❮</button>
                <button class="carousel-nav next" onclick="moveSlide(1)">❯</button>
                <div class="carousel-dots" id="carouselDots">
                    <?php foreach($project_images as $index => $img): ?>
                        <div class="carousel-dot <?=$index === 0 ? 'active' : ''?>" onclick="goToSlide(<?=$index?>)"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <div class="p-kanoon"><?=htmlspecialchars($project['kanoon_name'], ENT_QUOTES, 'UTF-8')?></div>
            <h1 class="p-title"><?=htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8')?></h1>
            <div class="p-date">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M19 4h-1V3c0-.55-.45-1-1-1s-1 .45-1 1v1H8V3c0-.55-.45-1-1-1s-1 .45-1 1v1H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>
                ثبت شده در: <?=toJalaliDateTime($project['created_at'])?>
            </div>
            
            <div class="p-desc"><?=formatText($project['description'])?></div>

            <?php if($project['project_link'] || $project['github_link']): ?>
            <div class="links-row">
                <?php if($project['project_link']): ?>
                    <a href="<?=htmlspecialchars($project['project_link'], ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener noreferrer" class="btn-link main-link">
                        <?=$ic_link?> مشاهده پروژه
                    </a>
                <?php endif; ?>
                
                <?php if($project['github_link']): ?>
                    <a href="<?=htmlspecialchars($project['github_link'], ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener noreferrer" class="btn-link">
                        <?=$ic_github?> سورس در گیت‌هاب
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if($is_admin): ?>
        <div class="admin-toolbar">
            <span style="font-size:12px; font-weight:bold; color:var(--x-blue); width:100%; margin-bottom:4px;">ابزارهای مدیر کانون</span>
            <button class="btn-admin primary" onclick="oM('addMemberModal')"><?=$ic_plus?> عضو جدید</button>
            <button class="btn-admin" onclick="oM('editProjectModal')"><?=$ic_edit?> ویرایش</button>
            <button class="btn-admin danger" onclick="oM('delProjectModal')"><?=$ic_delete?> حذف پروژه</button>
        </div>
        <?php endif; ?>

        <div class="section-title">تیم پروژه</div>
        <?php if(empty($team)): ?>
            <div style="text-align:center; padding:30px; color:var(--x-gray); font-size:14px;">هنوز عضوی برای این پروژه ثبت نشده است.</div>
        <?php else: ?>
            <div class="members-grid">
                <?php foreach($team as $m): 
                    $m_avatar = getImg($m['avatar'], $m['name']);
                ?>
                    <div style="position:relative;">
                        <?php if($is_admin): ?>
                            <form action="" method="POST" onsubmit="return confirm('عضو از پروژه حذف شود؟');">
                                <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
                                <input type="hidden" name="local_action" value="remove_project_member">
                                <input type="hidden" name="user_id" value="<?=$m['id']?>">
                                <button type="submit" class="btn-remove-mem" title="حذف از پروژه">✕</button>
                            </form>
                        <?php endif; ?>
                        
                        <a href="profile.php?id=<?=htmlspecialchars($m['id'], ENT_QUOTES, 'UTF-8')?>" class="mem-card">
                            <img src="<?=$m_avatar?>" class="mem-avatar">
                            <div class="mem-name-row">
                                <span class="mem-name">
                                    <?=htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8')?>
                                    <?php if($m['is_verified']) echo $ic_verified; ?>
                                </span>
                                <span class="mem-badge">سطح <?=pNum($m['level'])?></span>
                            </div>
                            <div class="mem-user">@<?=htmlspecialchars($m['username'], ENT_QUOTES, 'UTF-8')?></div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if($is_admin): ?>
            
            <div id="editProjectModal" class="mod">
                <div class="m-c">
                    <div class="m-hdr"><h2>ویرایش اطلاعات پروژه</h2><button onclick="tgM('editProjectModal')">✕</button></div>
                    <form action="actions.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
                        <input type="hidden" name="action" value="edit_project">
                        <input type="hidden" name="id" value="<?=$project_id?>">
                        <input type="hidden" name="kanoon_id" value="<?=htmlspecialchars($project['kanoon_id'], ENT_QUOTES, 'UTF-8')?>">
                        
                        <input type="text" name="name" class="input-ui" value="<?=htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8')?>" placeholder="نام پروژه" required>
                        <textarea name="description" class="input-ui" rows="4" placeholder="توضیحات (پشتیبانی از **بولد** و _ایتالیک_ و لینک)"><?=htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8')?></textarea>
                        <input type="url" name="project_link" class="input-ui" value="<?=htmlspecialchars($project['project_link'], ENT_QUOTES, 'UTF-8')?>" placeholder="لینک پروژه" dir="ltr" style="text-align:left;">
                        <input type="url" name="github_link" class="input-ui" value="<?=htmlspecialchars($project['github_link'], ENT_QUOTES, 'UTF-8')?>" placeholder="لینک گیت‌هاب" dir="ltr" style="text-align:left;">
                        
                        <label style="font-size:13px;color:var(--x-gray);display:block;margin-bottom:5px;">افزودن عکس‌های جدید به گالری (چندتایی مجاز است):</label>
                        <input type="file" name="images[]" class="input-ui" accept="image/*" multiple>
                        <button type="submit" class="btn-submit">ذخیره تغییرات</button>
                    </form>
                </div>
            </div>

            <div id="delProjectModal" class="mod">
                <div class="m-c" style="text-align:center;">
                    <h2 style="margin-bottom:10px; color:#f91880;">حذف پروژه</h2>
                    <p style="color:var(--x-gray); font-size:14px; margin-bottom:24px;">آیا مطمئن هستید؟ با این کار پروژه و لیست اعضای آن حذف می‌شود و غیرقابل بازگشت است.</p>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
                        <input type="hidden" name="action" value="delete_project">
                        <input type="hidden" name="id" value="<?=$project_id?>">
                        <input type="hidden" name="kanoon_id" value="<?=htmlspecialchars($project['kanoon_id'], ENT_QUOTES, 'UTF-8')?>">
                        <div style="display:flex; gap:10px;">
                            <button type="button" class="btn-submit" style="background:var(--x-border);color:var(--x-black);" onclick="tgM('delProjectModal')">لغو</button>
                            <button type="submit" class="btn-submit danger">حذف شود</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="addMemberModal" class="mod">
                <div class="m-c" style="overflow:visible;">
                    <div class="m-hdr"><h2>افزودن عضو به پروژه</h2><button onclick="tgM('addMemberModal')">✕</button></div>
                    <form action="" method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
                        <input type="hidden" name="local_action" value="add_project_member">
                        <div style="position:relative;">
                            <input type="text" id="userSearch" name="username" class="input-ui" placeholder="جستجوی آیدی یا نام کاربر..." required onkeyup="searchUsers(this.value)">
                            <div id="searchBox" class="search-res"></div>
                        </div>
                        <button type="submit" class="btn-submit">افزودن به تیم</button>
                    </form>
                </div>
            </div>

            <?php 
            $all_users = $pdo->query("SELECT username, name, avatar, level, is_verified FROM users ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <script>
            const users = <?=json_encode($all_users)?>;
            const svgVerify = '<?=$ic_verified?>';
            
            function searchUsers(val) {
                const box = document.getElementById('searchBox');
                if(!val){ box.style.display='none'; return; }
                val = val.toLowerCase();
                
                let res = users.filter(u => u.username.toLowerCase().includes(val) || u.name.toLowerCase().includes(val)).slice(0, 5);
                
                if(res.length > 0) {
                    box.innerHTML = res.map(u => {
                        let img = u.avatar ? (u.avatar.startsWith('http') ? u.avatar : 'uploads/'+u.avatar) : `https://ui-avatars.com/api/?name=${u.name}&background=random`;
                        let vBadge = u.is_verified == 1 ? svgVerify : '';
                        return `
                        <div class="s-item" onclick="selectUser('${u.username}')">
                            <img src="${img}">
                            <div style="flex:1;">
                                <div style="font-size:14px; font-weight:bold;">${u.name} ${vBadge}</div>
                                <div style="font-size:12px; color:var(--x-gray); display:flex; justify-content:space-between;">
                                    <span>@${u.username}</span>
                                    <span style="color:var(--x-blue); font-weight:bold;">Lvl ${u.level || 0}</span>
                                </div>
                            </div>
                        </div>`;
                    }).join('');
                    box.style.display = 'block';
                } else {
                    box.innerHTML = '<div style="padding:15px; color:gray; font-size:13px; text-align:center;">کاربری یافت نشد.</div>';
                    box.style.display = 'block';
                }
            }
            
            function selectUser(username) {
                document.getElementById('userSearch').value = username;
                document.getElementById('searchBox').style.display = 'none';
            }
            </script>
        <?php endif; ?>

        <div id="lightboxModal" class="lb-overlay" onclick="closeLightbox()">
            <div class="lb-close">✕</div>
            <img id="lightboxImg" src="" onclick="event.stopPropagation()">
        </div>
        
        <script>
        const tgM = i => {
            document.getElementById(i).style.display = 'none';
            if(document.getElementById('searchBox')) document.getElementById('searchBox').style.display = 'none';
            if(document.getElementById('userSearch')) document.getElementById('userSearch').value = '';
        };
        const oM = i => document.getElementById(i).style.display = 'flex';
        
        window.onclick = e => {
            if(e.target.classList.contains('mod')) tgM(e.target.id);
            if(document.getElementById('userSearch') && e.target.id !== 'userSearch') {
                if(document.getElementById('searchBox')) document.getElementById('searchBox').style.display = 'none';
            }
        };

        let isSwiping = false;
        function openLightbox(src) {
            if(isSwiping) return;
            document.getElementById('lightboxImg').src = src;
            document.getElementById('lightboxModal').style.display = 'flex';
        }
        function closeLightbox() {
            document.getElementById('lightboxModal').style.display = 'none';
            document.getElementById('lightboxImg').src = '';
        }

        function shareProject() {
            const title = '<?=addslashes(htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'))?>';
            const text = 'مشاهده پروژه ' + title + ' در پلتفرم ما!';
            const url = window.location.href;

            if (navigator.share) {
                navigator.share({ title: title, text: text, url: url }).catch((error) => console.log('خطا', error));
            } else {
                navigator.clipboard.writeText(url).then(() => alert('لینک پروژه در حافظه کپی شد!')).catch((error) => console.log('خطا', error));
            }
        }

        <?php if(count($project_images) > 1): ?>
        const track = document.getElementById('carouselTrack');
        const dots = document.querySelectorAll('.carousel-dot');
        const totalSlides = <?=count($project_images)?>;
        let currentSlide = 0;
        let slideInterval;
        let isDragging = false;
        let startPos = 0;

        function updateCarousel() {
            track.style.transform = `translateX(${currentSlide * 100}%)`;
            dots.forEach((dot, idx) => {
                dot.classList.toggle('active', idx === currentSlide);
            });
        }

        function moveSlide(dir) {
            currentSlide += dir;
            if(currentSlide < 0) currentSlide = totalSlides - 1;
            if(currentSlide >= totalSlides) currentSlide = 0;
            updateCarousel();
        }

        function goToSlide(idx) {
            currentSlide = idx;
            updateCarousel();
        }

        function startAutoPlay() {
            slideInterval = setInterval(() => moveSlide(-1), 3000); 
        }

        function stopAutoPlay() {
            clearInterval(slideInterval);
        }

        startAutoPlay();

        const wrapper = document.getElementById('carouselWrapper');
        
        wrapper.addEventListener('mouseenter', stopAutoPlay);
        wrapper.addEventListener('mouseleave', startAutoPlay);
        
        wrapper.addEventListener('touchstart', (e) => {
            stopAutoPlay();
            isDragging = true;
            isSwiping = false;
            startPos = e.touches[0].clientX;
        }, {passive: true});

        wrapper.addEventListener('touchmove', () => { isSwiping = true; }, {passive: true});

        wrapper.addEventListener('touchend', (e) => {
            if(!isDragging) return;
            isDragging = false;
            startAutoPlay();
            const endPos = e.changedTouches[0].clientX;
            const diff = startPos - endPos;
            if (diff > 50) moveSlide(1); 
            else if (diff < -50) moveSlide(-1);
            setTimeout(() => { isSwiping = false; }, 50);
        });

        wrapper.addEventListener('mousedown', (e) => {
            stopAutoPlay();
            isDragging = true;
            isSwiping = false;
            startPos = e.clientX;
        });

        wrapper.addEventListener('mousemove', () => {
            if(isDragging) isSwiping = true;
        });

        wrapper.addEventListener('mouseup', (e) => {
            if(!isDragging) return;
            isDragging = false;
            startAutoPlay();
            const endPos = e.clientX;
            const diff = startPos - endPos;
            if (diff > 50) moveSlide(1); 
            else if (diff < -50) moveSlide(-1);
            setTimeout(() => { isSwiping = false; }, 50);
        });

        wrapper.addEventListener('mouseleave', () => {
            isDragging = false;
            isSwiping = false;
        });

        <?php endif; ?>
        </script>

    </main>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
<?php ob_end_flush(); ?>
