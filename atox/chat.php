<?php
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (function_exists('isLoggedIn') && !isLoggedIn()) { header("Location: index.php"); exit; }

$uid = $_SESSION['user_id'];
$user_role = 'user';
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user_data = $stmt->fetch();

if ($user_data['role'] === 'admin' || $user_data['username'] === 'milad') {
    $user_role = 'admin';
}

function pTime($time) {
    if (empty($time)) return '';
    $diff = time() - strtotime($time);
    if ($diff < 60) return 'الان';
    if ($diff < 3600) return round($diff / 60) . ' دقیقه پیش';
    if ($diff < 86400) return round($diff / 3600) . ' ساعت پیش';
    return date('Y/m/d', strtotime($time));
}
function pNum($str) { return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str); }

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

$invite_group = null;
$is_already_member = false;
if (!empty($_GET['invite'])) {
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE is_group = 1 AND invite_link = ?");
    $stmt->execute([$_GET['invite']]);
    $invite_group = $stmt->fetch();
    
    if ($invite_group) {
        $stmt2 = $pdo->prepare("SELECT 1 FROM participants WHERE conversation_id = ? AND user_id = ?");
        $stmt2->execute([$invite_group['id'], $uid]);
        if ($stmt2->fetchColumn()) {
            $is_already_member = true;
        }
    }
}

$stmt = $pdo->prepare("
    SELECT c.id, c.updated_at,
           (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_msg,
           (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_msg_time,
           u.id as other_id, u.name as other_name, u.username as other_username, u.avatar as other_avatar, u.is_verified, u.last_seen,
           (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND user_id != ? AND is_read = 0) as unread_count,
           (SELECT COUNT(*) FROM blocks WHERE blocker_id = ? AND blocked_id = u.id) as is_blocked
    FROM conversations c
    JOIN participants p ON c.id = p.conversation_id
    JOIN participants p2 ON c.id = p2.conversation_id AND p2.user_id != ?
    JOIN users u ON p2.user_id = u.id
    WHERE p.user_id = ? AND c.is_group = 0
    AND EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id)
    ORDER BY COALESCE(last_msg_time, c.updated_at) DESC
");
$stmt->execute([$uid, $uid, $uid, $uid]);
$personal_chats = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT c.*,
           (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_msg,
           (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_msg_time,
           (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND user_id != ? AND is_read = 0) as unread_count
    FROM conversations c
    JOIN participants p ON c.id = p.conversation_id
    WHERE p.user_id = ? AND c.is_group = 1
    ORDER BY COALESCE(last_msg_time, c.updated_at) DESC
");
$stmt->execute([$uid, $uid]);
$group_chats = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT c.* 
    FROM conversations c
    WHERE c.is_group = 1 AND c.id NOT IN (SELECT conversation_id FROM participants WHERE user_id = ?)
");
$stmt->execute([$uid]);
$available_groups = $stmt->fetchAll();

$blue_tick = '<span class="verified-badge" title="تایید شده" style="display:inline-flex; align-items:center; margin-right:4px; vertical-align:-3px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg></span>';


$ic_logo = '<svg viewBox="0 0 24 24" style="width:30px;height:30px;fill:var(--x-blue)"><path d="M12 1L14.5 8.5L22 11L14.5 13.5L12 21L9.5 13.5L2 11L9.5 8.5L12 1Z"></path></svg>';
$ic_home = '<svg viewBox="0 0 24 24" class="ic"><path d="M12 1.696L.622 8.807l1.06 1.696L3 9.679V19.5C3 20.881 4.119 22 5.5 22h13c1.381 0 2.5-1.119 2.5-2.5V9.679l1.318.824 1.06-1.696L12 1.696zM12 16.5c-1.933 0-3.5-1.567-3.5-3.5s1.567-3.5 3.5-3.5 3.5 1.567 3.5 3.5-1.567 3.5-3.5 3.5z"/></svg>';
$ic_prof = '<svg viewBox="0 0 24 24" class="ic"><path d="M12 11.816c1.355 0 2.872-.15 3.84-1.256.814-.93 1.078-2.368.806-4.392-.38-2.825-2.117-4.512-4.646-4.512S7.734 3.343 7.354 6.168c-.272 2.024-.008 3.46.806 4.392.968 1.106 2.485 1.256 3.84 1.256zM8.84 6.368c.162-1.2.787-3.212 3.16-3.212s2.998 2.013 3.16 3.212c.207 1.55.057 2.627-.45 3.205-.455.52-1.266.686-2.71.686s-2.255-.166-2.71-.686c-.507-.578-.657-1.656-.45-3.205zm11.44 12.868c-.877-3.526-4.282-5.99-8.28-5.99s-7.403 2.464-8.28 5.99c-.172.692-.028 1.4.395 1.94.408.52 1.04.82 1.733.82h12.304c.693 0 1.325-.3 1.733-.82.424-.54.567-1.247.395-1.94zm-1.676 1.32c-.113.14-.28.22-.452.22H5.848c-.172 0-.34-.08-.452-.22-.128-.16-.182-.35-.124-.53.714-2.87 3.477-4.876 6.728-4.876s6.014 2.006 6.728 4.876c.058.18.004.37-.124.53z"/></svg>';
$ic_msg = '<svg viewBox="0 0 24 24" class="ic"><path d="M1.998 5.5c0-1.381 1.119-2.5 2.5-2.5h15c1.381 0 2.5 1.119 2.5 2.5v13c0 1.381-1.119 2.5-2.5 2.5h-15c-1.381 0-2.5-1.119-2.5-2.5v-13zm2.5-.5c-.276 0-.5.224-.5.5v2.764l8 3.638 8-3.636V5.5c0-.276-.224-.5-.5-.5h-15zm15.5 5.463l-8 3.636-8-3.638V18.5c0 .276.224.5.5.5h15c.276 0 .5-.224.5-.5v-8.037z"/></svg>';
$ic_new_msg = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M19 10.5V4h-2v6.5l-2.5-1.5L12 10.5V4h-2v6.5l-2.5-1.5L5 10.5V4H3v16h18V4h-2v6.5z" opacity="0"/><path d="M11 11V4h2v7h7v2h-7v7h-2v-7H4v-2h7z"/></svg>';
$ic_dots_modern = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>';
$ic_trash_sm = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M15 3H9v2H4v2h16V5h-5V3zM6 9v10c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V9H6z"></path></svg>';
$ic_share = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor;vertical-align:-4px;"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92c0-1.61-1.31-2.92-2.92-2.92z"/></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>آتوکس - پیام‌ها</title>
<link rel="manifest" href="/manifest.json?v=3">
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.7); --x-hover:rgba(15,20,25,0.05); --x-modal:rgba(0,0,0,0.5); --glass-bg: rgba(230, 230, 230, 0.4); --glass-border: rgba(0,0,0,0.05); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.7); --x-hover:rgba(255,255,255,0.05); --x-modal:rgba(255,255,255,0.2); --glass-bg: rgba(50, 50, 50, 0.4); --glass-border: rgba(255,255,255,0.05); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;-webkit-touch-callout:none;overflow-y:scroll;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}
a, button, .chat-row, .tab-btn, .menu-item, .glass-btn, .confirm-btn {touch-action: manipulation; user-select: none; -webkit-user-select: none; -webkit-tap-highlight-color: transparent;}

.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.side{width:275px;padding:0 12px;position:sticky;top:0;height:100vh;display:flex;flex-direction:column;align-items:flex-start}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:120px} 

.hdr{position:sticky;top:0;background:var(--x-bg-trans);backdrop-filter:blur(15px);-webkit-backdrop-filter:blur(15px);z-index:90; padding:15px 16px 0; display:flex; flex-direction:column; gap:10px;}
.hdr-top {display:flex; justify-content:space-between; align-items:center; width:100%;}
.hdr h2 {font-size:20px; font-weight:900;}
.icon-btn {width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:0.2s;}
.icon-btn:hover {background:var(--x-hover);}

.tab-container { display:flex; gap:5px; border-bottom:1px solid var(--glass-border); }
.tab-btn { flex:1; padding:12px 0; background:none; border:none; font-size:15px; font-weight:bold; color:var(--x-gray); cursor:pointer; transition:0.2s; border-bottom:3px solid transparent; text-align:center;}
.tab-btn:hover { background:var(--x-hover); }
.tab-btn.active { color:var(--x-black); border-bottom:3px solid var(--x-blue); }
.chat-list-wrapper { padding: 10px; display: none; flex-direction: column; }
.chat-list-wrapper.active { display: flex; animation: fadeIn 0.3s ease;}
.empty-state { text-align:center; padding:50px 20px; color:var(--x-gray); font-size:16px; font-weight:bold; }

.chat-row {display:flex; padding:12px; transition:0.3s; cursor:pointer; align-items:center; margin-bottom:8px; border-radius:16px; background:var(--glass-bg); border:1px solid var(--glass-border); position:relative; z-index:1;} 
.chat-row:hover {transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05);}

.c-av-wrap {position:relative; margin-left:12px; flex-shrink:0;}
.c-av {width:54px; height:54px; border-radius:50%; object-fit:cover; background:var(--x-border);}
.online-dot {position:absolute; bottom:2px; right:2px; width:14px; height:14px; background:#00ba7c; border:2.5px solid var(--x-bg); border-radius:50%;}

.c-body {flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center;}
.c-top {display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;}
.c-title {font-size:16px; font-weight:bold; color:var(--x-black); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:flex; align-items:center;}
.blocked-badge { background:#f4212e; color:#fff; font-size:10px; padding:2px 6px; border-radius:4px; margin-right:6px; font-weight:bold; }
.c-time {font-size:12px; color:var(--x-gray); flex-shrink:0;}

.c-bot {display:flex; justify-content:space-between; align-items:center;}
.c-preview {font-size:14px; color:var(--x-gray); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;}
.c-preview.unread {font-weight:bold; color:var(--x-black);}
.unread-badge {background:var(--x-blue); color:#fff; font-size:12px; font-weight:bold; height:22px; min-width:22px; padding:0 7px; display:flex; align-items:center; justify-content:center; border-radius:11px; margin-right:8px;}

.menu-wrap {position:relative; margin-right:5px; flex-shrink:0; cursor:pointer;}
.menu-btn {width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--x-gray); transition:0.2s; cursor:pointer;}
.menu-btn:hover {background:var(--x-hover); color:var(--x-black);}
.menu-dropdown {position:absolute; top:40px; left:0; background:var(--x-bg); border:1px solid var(--x-border); border-radius:16px; box-shadow:0 10px 35px rgba(0,0,0,0.2); width:190px; display:none; flex-direction:column; z-index:9999; overflow:hidden;}
.menu-dropdown.show {display:flex; animation: fadeIn 0.2s ease;}
.menu-item {padding:14px 15px; font-size:14px; font-weight:bold; color:var(--x-black); text-align:right; transition:0.2s; cursor:pointer;}
.menu-item:hover {background:var(--x-hover);}
.menu-item.danger {color:#f4212e;}
.menu-item.success {color:#00ba7c;}

.glass-btn {background:var(--glass-bg); border:1px solid var(--glass-border); color:var(--x-black); border-radius:99px; font-weight:bold; cursor:pointer; transition:0.3s;}
.glass-btn:hover {background:var(--x-hover);}
.btn-ui {padding:0 32px; font-size:17px; min-height:52px; width:90%; margin-top:15px; display:flex; align-items:center; justify-content:center;}

.toast-container {position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; display:flex; flex-direction:column; gap:10px;}
.toast {background:var(--glass-bg); backdrop-filter:blur(20px); border:1px solid var(--glass-border); padding:12px 20px; border-radius:99px; color:var(--x-black); font-weight:bold; font-size:14px; display:flex; align-items:center; gap:10px; box-shadow:0 10px 30px rgba(0,0,0,0.1); animation:slideDown 0.4s;}

.mod{display:none;position:fixed;inset:0;background:var(--x-modal);z-index:1000;align-items:center;justify-content:center; padding:15px; backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px);}
.m-c{background:var(--x-bg);border-radius:24px;width:100%;max-width:400px;padding:24px;box-shadow:0 15px 40px rgba(0,0,0,.2);animation:slideUp .3s; position:relative;}
.m-hdr{display:flex; align-items:center; margin-bottom:20px; gap:10px;}
.m-hdr h2{font-size:22px; font-weight:900; flex:1;}
.inp-wrap {background:var(--x-hover); border-radius:16px; margin-bottom:15px; display:flex; align-items:center; padding:0 15px; border:1px solid transparent; transition:0.2s;}
.inp-wrap:focus-within { border-color:var(--x-blue); background:var(--x-bg); }
.inp-ui {width:100%; border:none; background:transparent; padding:14px 10px; outline:none; font-size:15px; color:var(--x-black); font-family:inherit;}

.logo-box{padding:12px;border-radius:99px;display:inline-flex;align-items:center;gap:12px;transition:.2s;margin:5px 0}
.logo-box h1 {font-size:24px; font-weight:900;}
.n-i{display:flex;align-items:center;padding:12px;border-radius:30px;font-size:20px;transition:.2s;margin-bottom:5px;width:fit-content;}
.n-i:hover{background:var(--x-hover)}
.n-i.active{font-weight:bold;}
.ic{width:26px;height:26px;fill:currentColor;margin-left:20px}
.nav-m{display:none;position:fixed;bottom:0;width:100%;background:var(--x-bg-trans);backdrop-filter:blur(15px);-webkit-backdrop-filter:blur(15px);border-top:1px solid var(--x-border);justify-content:space-between;padding:0;z-index:100}
.nav-m a{flex:1;display:flex;justify-content:center;padding:14px 0;}

.confirm-actions {display:flex; gap:10px; margin-top:20px;}
.confirm-btn {flex:1; padding:14px; border-radius:16px; font-weight:bold; font-size:15px; cursor:pointer;}
.confirm-btn.yes {background:#f4212e; color:#fff; border:none;}
.confirm-btn.no {background:var(--x-hover); color:var(--x-black); border:none;}

.pv-list { max-height:250px; overflow-y:auto; margin-top:15px; border-top:1px solid var(--x-border); padding-top:10px; -webkit-overflow-scrolling: touch; }
.pv-item { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--x-hover); }
.pv-item-info { display:flex; align-items:center; gap:12px; }
.pv-item img { width:40px; height:40px; border-radius:50%; object-fit:cover; }
.btn-send-share { padding:8px 16px; background:var(--x-blue); color:#fff; border-radius:12px; font-size:13px; font-weight:bold; cursor:pointer; border:none; transition:0.2s; }
.btn-send-share.sent { background:#00ba7c; pointer-events:none; }

@keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
@keyframes slideDown { 0%{opacity:0; transform:translateY(-20px);} 100%{opacity:1; transform:translateY(0);} }
@keyframes slideUp{0%{transform:translateY(30px);opacity:0}100%{transform:translateY(0);opacity:1}}

@media(max-width:600px){ .side{display:none;} .nav-m{display:flex;} .main{border:none;} }
</style>
</head>
<body>
<div class="toast-container" id="toastBox"></div>

<div class="app">

    <main class="main">
        <?php if(file_exists('header.php')) include 'header.php'; ?>
        <div class="hdr">
            <div class="hdr-top">
                <h2>پیام‌ها</h2>
                <div>
                    <button class="icon-btn" style="background:var(--x-blue); color:#fff;" onclick="tgM('mChat')" title="پیام جدید"><?=$ic_new_msg?></button>
                </div>
            </div>
            <div class="tab-container">
                <button class="tab-btn active" id="btn-personal" onclick="switchTab('personal')">شخصی</button>
                <button class="tab-btn" id="btn-groups" onclick="switchTab('groups')">گروه‌ها</button>
            </div>
        </div>

        <div class="chat-list-wrapper active" id="list-personal">
            <?php if(empty($personal_chats)): ?>
                <div class="empty-state">صندوق ورودی شخصی شما خالی است</div>
            <?php else: ?>
                <?php foreach($personal_chats as $c): 
                    $chat_time = pNum(pTime($c['last_msg_time'] ?? $c['updated_at']));
                    $preview = $c['last_msg'];
                    $unread = $c['unread_count'] > 0;
                    $is_blocked = $c['is_blocked'] > 0;
                    $is_online = ($c['last_seen'] && strtotime($c['last_seen']) >= time() - 300);
                    $title = htmlspecialchars($c['other_name'] ?? 'کاربر');
                    $avatar = !empty($c['other_avatar']) ? htmlspecialchars($c['other_avatar']) : "https://ui-avatars.com/api/?name=".urlencode($title);
                ?>
                <div class="chat-row" onclick="goToChat(this, 'chat_view.php?id=<?=$c['id']?>')">
                    <div class="c-av-wrap">
                        <img src="<?=$avatar?>" class="c-av" loading="lazy">
                        <?php if($is_online): ?><div class="online-dot"></div><?php endif; ?>
                    </div>
                    <div class="c-body">
                        <div class="c-top">
                            <div class="c-title">
                                <?=$title?>
                                <?php if($c['is_verified']): ?><?=$blue_tick?><?php endif; ?>
                                <?php if($is_blocked): ?><span class="blocked-badge">بلاک شده</span><?php endif; ?>
                            </div>
                            <div class="c-time"><?=$chat_time?></div>
                        </div>
                        <div class="c-bot">
                            <div class="c-preview <?=$unread ? 'unread' : ''?>"><?=htmlspecialchars($preview)?></div>
                            <div style="display:flex; align-items:center;">
                                <?php if($unread): ?><div class="unread-badge"><?=pNum($c['unread_count'])?></div><?php endif; ?>
                                <div class="menu-wrap" onclick="event.stopPropagation();">
                                    <button class="menu-btn" onclick="toggleMenu('cMenu<?=$c['id']?>', event, this)"><?=$ic_dots_modern?></button>
                                    <div class="menu-dropdown" id="cMenu<?=$c['id']?>">
                                        <div class="menu-item danger" onclick="askAction('delete_chat_one_side', <?=$c['id']?>, 'آیا از حذف یک‌طرفه چت مطمئن هستید؟')">حذف یه طرفه (فقط من)</div>
                                        <div class="menu-item danger" onclick="askAction('delete_chat_both_sides', <?=$c['id']?>, 'آیا از حذف دوطرفه چت برای هر دو نفر مطمئن هستید؟')">حذف دوطرفه چت</div>
                                        <?php if($is_blocked): ?>
                                            <div class="menu-item success" onclick="askAction('unblock_user', <?=$c['other_id']?>, 'کاربر از بلاک خارج شود؟')">خروج از بلاک</div>
                                        <?php else: ?>
                                            <div class="menu-item danger" onclick="askAction('block_user', <?=$c['other_id']?>, 'آیا از بلاک کردن کاربر مطمئن هستید؟')">بلاک کاربر</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-list-wrapper" id="list-groups">
            <div style="display:flex; gap:10px; margin-bottom:15px;">
                <?php if($user_role === 'admin'): ?>
                <button class="glass-btn" style="flex:1; padding:12px; font-size:14px; background:var(--x-blue); color:#fff; border:none;" onclick="tgM('mNewGroup')">+ ساخت گروه جدید</button>
                <?php endif; ?>
                <button class="glass-btn" style="flex:1; padding:12px; font-size:14px;" onclick="tgM('mGroupsList')">لیست گروه‌های در دسترس</button>
            </div>

            <?php if(empty($group_chats)): ?>
                <div class="empty-state">شما در هیچ گروهی عضو نیستید</div>
            <?php else: ?>
                <?php foreach($group_chats as $c): 
                    $chat_time = pNum(pTime($c['last_msg_time'] ?? $c['updated_at']));
                    $preview = empty($c['last_msg']) ? 'شروع گفتگو...' : $c['last_msg'];
                    $unread = $c['unread_count'] > 0;
                    $title = htmlspecialchars($c['group_name']);
                    $desc_esc = htmlspecialchars(str_replace(["\r", "\n"], ' ', $c['group_description'] ?? 'بدون توضیحات'), ENT_QUOTES);
                    $avatar = !empty($c['group_avatar']) ? htmlspecialchars($c['group_avatar']) : "https://ui-avatars.com/api/?name=".urlencode($title);
                    $is_admin = ($c['admin_id'] == $uid || $user_role === 'admin');
                ?>
                <div class="chat-row" onclick="goToChat(this, 'chat_view.php?id=<?=$c['id']?>')">
                    <div class="c-av-wrap"><img src="<?=$avatar?>" class="c-av"></div>
                    <div class="c-body">
                        <div class="c-top">
                            <div class="c-title"><?=$title?></div>
                            <div class="c-time"><?=$chat_time?></div>
                        </div>
                        <div class="c-bot">
                            <div class="c-preview <?=$unread ? 'unread' : ''?>"><?=htmlspecialchars($preview)?></div>
                            <div style="display:flex; align-items:center;">
                                <?php if($unread): ?><div class="unread-badge"><?=pNum($c['unread_count'])?></div><?php endif; ?>
                                <div class="menu-wrap" onclick="event.stopPropagation();">
                                    <button class="menu-btn" onclick="toggleMenu('cMenu<?=$c['id']?>', event, this)"><?=$ic_dots_modern?></button>
                                    <div class="menu-dropdown" id="cMenu<?=$c['id']?>">
                                        <div class="menu-item" onclick="openShareGroup('<?=$c['invite_link']?>', '<?=htmlspecialchars($c['group_name'], ENT_QUOTES)?>', '<?=$desc_esc?>')">ارسال لینک به پیوی‌ها</div>
                                        <?php if($is_admin): ?>
                                            <div class="menu-item" onclick="openEditGroupModal(<?=$c['id']?>, '<?=htmlspecialchars($c['group_name'], ENT_QUOTES)?>', '<?=$desc_esc?>')">ویرایش اطلاعات گروه</div>
                                            <div class="menu-item danger" onclick="askAction('clear_group_history', <?=$c['id']?>, 'تاریخچه چت گروه کاملاً پاک شود؟')">حذف پیام‌های گروه</div>
                                            <div class="menu-item danger" onclick="askAction('delete_group', <?=$c['id']?>, 'گروه برای همیشه برای همه پاک شود؟')">حذف کل گروه</div>
                                        <?php else: ?>
                                            <div class="menu-item danger" onclick="askAction('leave_group', <?=$c['id']?>, 'از گروه خارج می‌شوید؟')">خروج از گروه (لفت)</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
	
</div>



<div id="mConfirm" class="mod" style="z-index:99999;">
    <div class="m-c" style="max-width:350px; text-align:center;">
        <h3 id="mConfirmTitle" style="margin-bottom:15px; font-size:18px;">آیا مطمئن هستید؟</h3>
        <p id="mConfirmText" style="color:var(--x-gray); font-size:14px; margin-bottom:20px;"></p>
        <div class="confirm-actions">
            <button class="confirm-btn yes" id="btnConfirmYes">بله، انجام بده</button>
            <button class="confirm-btn no" onclick="document.getElementById('mConfirm').style.display='none'">انصراف</button>
        </div>
    </div>
</div>

<?php if($invite_group): ?>
<div id="mInviteJoin" class="mod" style="display:flex;">
    <div class="m-c" style="text-align:center;">
        <div class="m-hdr">
            <h2>دعوت به گروه</h2>
            <button onclick="document.getElementById('mInviteJoin').style.display='none'" class="icon-btn">X</button>
        </div>
        <?php $inv_avatar = !empty($invite_group['group_avatar']) ? htmlspecialchars($invite_group['group_avatar']) : "https://ui-avatars.com/api/?name=".urlencode($invite_group['group_name']); ?>
        <img src="<?=$inv_avatar?>" style="width:90px; height:90px; border-radius:50%; margin-bottom:15px; border:2px solid var(--x-blue);">
        <h3 style="margin-bottom:8px;"><?=htmlspecialchars($invite_group['group_name'])?></h3>
        <p style="color:var(--x-gray); font-size:14px; margin-bottom:20px; line-height:1.6;"><?=htmlspecialchars($invite_group['group_description'])?></p>
        
        <?php if($is_already_member): ?>
            <p style="color:#00ba7c; font-weight:bold; margin-bottom:15px;">شما در این گروه عضو هستید.</p>
            <button class="glass-btn btn-ui" onclick="location.href='chat_view.php?id=<?=$invite_group['id']?>'" style="width:100%; background:var(--x-blue); color:#fff; border-radius:16px;">ورود به چت</button>
        <?php else: ?>
            <button class="glass-btn btn-ui" onclick="joinGroup(<?=$invite_group['id']?>)" style="width:100%; background:var(--x-blue); color:#fff; border-radius:16px;">عضو می‌شوم</button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div id="mGroupsList" class="mod" onclick="if(event.target===this) this.style.display='none'">
    <div class="m-c" style="max-height:85vh; overflow-y:auto; -webkit-overflow-scrolling: touch;">
        <div class="m-hdr">
            <h2>گروه‌های در دسترس</h2>
            <button onclick="document.getElementById('mGroupsList').style.display='none'" class="icon-btn">X</button>
        </div>
        <?php if(empty($available_groups)): ?>
            <div style="text-align:center; padding:40px 20px; color:var(--x-gray);">گروه جدیدی برای عضویت یافت نشد.</div>
        <?php else: ?>
            <?php foreach($available_groups as $g): 
                $g_img = !empty($g['group_avatar']) ? htmlspecialchars($g['group_avatar']) : "https://ui-avatars.com/api/?name=".urlencode($g['group_name']);
                $g_name_esc = htmlspecialchars($g['group_name'], ENT_QUOTES);
                $g_desc_esc = htmlspecialchars(str_replace(["\r", "\n"], ' ', $g['group_description'] ?? 'بدون توضیحات'), ENT_QUOTES);
            ?>
            <div class="chat-row" style="cursor:pointer; background:transparent; border:none; border-bottom:1px solid var(--x-border); border-radius:0; padding:15px 0;" onclick="openJoinModal(<?=$g['id']?>, '<?=$g_name_esc?>', '<?=$g_img?>', '<?=$g_desc_esc?>')">
                <img src="<?=$g_img?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover; flex-shrink:0;">
                <div style="flex:1; margin-right:15px; overflow:hidden;">
                    <div style="font-weight:bold; color:var(--x-black); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?=$g_name_esc?></div>
                    <div style="font-size:13px; color:var(--x-gray); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:3px;"><?=$g_desc_esc?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="mJoinGroupPreview" class="mod">
    <div class="m-c" style="text-align:center;">
        <div style="position:absolute; top:15px; right:15px; width:30px; height:30px; border-radius:50%; background:var(--x-hover); display:flex; align-items:center; justify-content:center; cursor:pointer;" onclick="document.getElementById('mJoinGroupPreview').style.display='none'">X</div>
        <img id="jImg" src="" style="width:90px;height:90px;border-radius:50%;margin-bottom:15px; border:2px solid var(--x-blue);">
        <h3 id="jName" style="margin-bottom:8px; color:var(--x-black);"></h3>
        <p id="jDesc" style="font-size:14px; color:var(--x-gray); margin-bottom:25px; line-height:1.6;"></p>
        <button class="confirm-btn yes" style="width:100%; background:var(--x-blue);" id="btnJoinConfirmAction">عضو می‌شوم</button>
    </div>
</div>

<div id="mEditGroup" class="mod">
    <div class="m-c">
        <div class="m-hdr">
            <h2>ویرایش گروه</h2>
            <button onclick="document.getElementById('mEditGroup').style.display='none'" class="icon-btn">X</button>
        </div>
        <form id="editGroupForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_group">
            <input type="hidden" name="group_id" id="eGroupId">
            
            <div class="inp-wrap"><input type="text" name="group_name" id="eGroupName" class="inp-ui" placeholder="نام گروه" required></div>
            <div class="inp-wrap"><textarea name="group_desc" id="eGroupDesc" class="inp-ui" placeholder="توضیحات گروه" rows="3" style="resize:none; padding-top:10px;"></textarea></div>
            
            <div style="margin-bottom:15px; padding:0 15px;">
                <label style="font-size:13px; font-weight:bold; color:var(--x-gray);">آپلود عکس جدید (اختیاری):</label>
                <input type="file" name="group_avatar" accept="image/*" class="inp-ui" style="padding:10px 0; font-size:13px;">
            </div>
            
            <button type="submit" class="confirm-btn yes" style="width:100%; background:var(--x-blue);">ذخیره تغییرات</button>
        </form>
    </div>
</div>

<div id="mShareGroup" class="mod">
    <div class="m-c" style="max-height:80vh; display:flex; flex-direction:column;">
        <div class="m-hdr" style="margin-bottom:5px;">
            <h2>اشتراک‌گذاری گروه</h2>
            <button onclick="document.getElementById('mShareGroup').style.display='none'" class="icon-btn">X</button>
        </div>
        <p style="font-size:13px; color:var(--x-gray); margin-bottom:10px;">لینک گروه را به کدام چت بفرستیم؟</p>
        
        <div class="pv-list">
            <?php if(empty($personal_chats)): ?>
                <div style="text-align:center; font-size:14px; color:var(--x-gray); padding:20px;">شما هنوز چت خصوصی ندارید.</div>
            <?php else: ?>
                <?php foreach($personal_chats as $pv): 
                    $pv_title = htmlspecialchars($pv['other_name']);
                    $pv_avatar = !empty($pv['other_avatar']) ? htmlspecialchars($pv['other_avatar']) : "https://ui-avatars.com/api/?name=".urlencode($pv_title);
                ?>
                    <div class="pv-item">
                        <div class="pv-item-info">
                            <img src="<?=$pv_avatar?>">
                            <span style="font-size:14px; font-weight:bold; color:var(--x-black);"><?=$pv_title?></span>
                        </div>
                        <button class="btn-send-share" onclick="shareGroupToChat(<?=$pv['id']?>, this)">ارسال</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if($user_role === 'admin'): ?>
<div id="mNewGroup" class="mod" onclick="if(event.target===this) this.style.display='none'">
    <div class="m-c">
        <div class="m-hdr">
            <h2>ساخت گروه جدید</h2>
            <button onclick="document.getElementById('mNewGroup').style.display='none'" class="icon-btn">X</button>
        </div>
        <form action="actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_group">
            <div class="inp-wrap"><input type="text" name="group_name" class="inp-ui" placeholder="نام گروه" required></div>
            <div class="inp-wrap"><input type="text" name="group_description" class="inp-ui" placeholder="توضیح (اختیاری)"></div>
            <div style="margin-bottom:15px; padding:0 15px;">
                <label style="font-size:13px; font-weight:bold; color:var(--x-gray);">عکس گروه (اختیاری):</label>
                <input type="file" name="group_avatar_file" accept="image/*" class="inp-ui" style="padding:10px 0; font-size:13px;">
            </div>
            <button type="submit" class="confirm-btn yes" style="width:100%; background:var(--x-blue);">ایجاد گروه</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div id="mChat" class="mod" onclick="if(event.target===this) this.style.display='none'">
<div class="m-c" style="max-height:85vh; display:flex; flex-direction:column;">
<div class="m-hdr">
<h2>شروع گفتگوی شخصی</h2>
<button onclick="document.getElementById('mChat').style.display='none'" class="icon-btn">X</button>
        </div>
        <div class="inp-wrap">
            <input type="text" id="userSearchInp" class="inp-ui" placeholder="جستجوی نام یا یوزرنیم..." autocomplete="off">
        </div>
        <div id="searchRes" style="overflow-y:auto; flex:1; min-height:150px; -webkit-overflow-scrolling: touch;">
            <div style="padding:20px; text-align:center; color:var(--x-gray);">برای شروع جستجو کنید</div>
        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", () => {
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        window.history.replaceState(null, '', window.location.href);
    }
    
    let savedTab = localStorage.getItem('activeChatTab') || 'personal';
    switchTab(savedTab);
});

const tgM = i => { document.getElementById(i).style.display='flex'; };

function goToChat(el, url) {
    let badge = el.querySelector('.unread-badge');
    let preview = el.querySelector('.c-preview');
    if (badge) badge.style.display = 'none';
    if (preview) preview.classList.remove('unread');
    
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        location.replace(url);
    } else {
        location.href = url;
    }
}

function askAction(action, id, text) {
    document.getElementById('mConfirmText').innerText = text;
    document.getElementById('mConfirm').style.display = 'flex';
    document.getElementById('btnConfirmYes').onclick = () => {
        document.getElementById('mConfirm').style.display = 'none';
        doAction(action, id);
    };
}

function switchTab(tab) {
    localStorage.setItem('activeChatTab', tab);
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.chat-list-wrapper').forEach(wrapper => wrapper.classList.remove('active'));
    
    let tabBtn = document.getElementById('btn-' + tab);
    let tabList = document.getElementById('list-' + tab);
    if(tabBtn && tabList) { tabBtn.classList.add('active'); tabList.classList.add('active'); }
}

function toggleMenu(id, e, btn) {
    e.stopPropagation();
    document.querySelectorAll('.chat-row').forEach(row => row.style.zIndex = '1');
    let current = document.getElementById(id);
    let isShowing = current.classList.contains('show');
    document.querySelectorAll('.menu-dropdown').forEach(m => m.classList.remove('show'));
    if(!isShowing) {
        current.classList.add('show');
        let parentRow = btn.closest('.chat-row');
        if(parentRow) parentRow.style.zIndex = '999';
    }
}
window.addEventListener('click', () => { 
    document.querySelectorAll('.menu-dropdown').forEach(m => m.classList.remove('show')); 
    document.querySelectorAll('.chat-row').forEach(row => row.style.zIndex = '1'); 
});

function doAction(action, id) {
    fetch('actions.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `action=${action}&id=${id}` }).then(() => location.reload());
}

let targetJoinId = 0;
function openJoinModal(id, name, img, desc) {
    document.getElementById('mGroupsList').style.display = 'none';
    targetJoinId = id;
    document.getElementById('jImg').src = img;
    document.getElementById('jName').innerText = name;
    document.getElementById('jDesc').innerText = desc;
    document.getElementById('mJoinGroupPreview').style.display = 'flex';
}
document.getElementById('btnJoinConfirmAction').onclick = () => { joinGroup(targetJoinId); };

function joinGroup(id) {
    fetch('actions.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `action=join_group&id=${id}` }).then(() => {
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
            location.replace('chat_view.php?id=' + id);
        } else {
            location.href = 'chat_view.php?id=' + id;
        }
    });
}

function openEditGroupModal(id, name, desc) {
    document.getElementById('eGroupId').value = id;
    document.getElementById('eGroupName').value = name;
    document.getElementById('eGroupDesc').value = desc;
    document.getElementById('mEditGroup').style.display = 'flex';
}
document.getElementById('editGroupForm').onsubmit = (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    fetch('actions.php', { method: 'POST', body: formData }).then(() => location.reload());
};

let shareGroupData = {};
function openShareGroup(link, name, desc) {
    shareGroupData = {link, name, desc};
    document.getElementById('mShareGroup').style.display = 'flex';
}
function shareGroupToChat(chatId, btnEl) {
    btnEl.disabled = true;
    btnEl.innerText = 'درحال ارسال...';
    const text = `گروه: ${shareGroupData.name}\nتوضیحات: ${shareGroupData.desc}\nلینک عضویت: <?=$base_url?>/chat.php?invite=${shareGroupData.link}`;
    const formData = new FormData();
    formData.append('action', 'send_msg');
    formData.append('conversation_id', chatId);
    formData.append('message_text', text);
    fetch('actions.php', { method: 'POST', body: formData })
    .then(() => {
        btnEl.innerText = 'ارسال شد ✓';
        btnEl.classList.add('sent');
    });
}

const sInp = document.getElementById('userSearchInp');
const sRes = document.getElementById('searchRes');
let sTime;
const blueTickSVG = `<?=$blue_tick?>`;

if(sInp) {
    sInp.addEventListener('input', (e) => {
        clearTimeout(sTime); const q = e.target.value.trim();
        if(!q) { sRes.innerHTML = '<div style="padding:20px; text-align:center;">برای شروع جستجو کنید</div>'; return; }
        sRes.innerHTML = '<div style="padding:20px; text-align:center;">در حال جستجو...</div>';
        sTime = setTimeout(() => {
            fetch('actions.php?action=search_users&q=' + encodeURIComponent(q)).then(r => r.json()).then(data => {
                sRes.innerHTML = '';
                if(data.length === 0) { sRes.innerHTML = '<div style="padding:20px; text-align:center; color:red;">کاربری یافت نشد</div>'; return; }
                data.forEach(u => {
                    let div = document.createElement('div');
                    div.style.padding = '12px 10px'; div.style.cursor = 'pointer'; div.style.borderBottom = '1px solid var(--x-border)';
                    div.style.display = 'flex'; div.style.alignItems = 'center'; div.style.gap = '12px';
                    
                    let av = u.avatar ? u.avatar : `https://ui-avatars.com/api/?name=${u.name}`;
                    let tick = u.is_verified ? blueTickSVG : '';
                    
                    div.innerHTML = `
                        <img src="${av}" style="width:45px; height:45px; border-radius:50%; object-fit:cover;">
                        <div style="display:flex; flex-direction:column;">
                            <span style="font-weight:bold; color:var(--x-black); display:flex; align-items:center;">${u.name} ${tick}</span>
                            <span style="font-size:13px; color:var(--x-gray);">@${u.username}</span>
                        </div>
                    `;
                    div.onclick = () => {
                        let f = document.createElement('form'); f.method = 'POST'; f.action = 'actions.php';
                        f.innerHTML = `<input type="hidden" name="action" value="create_personal"><input type="hidden" name="target_id" value="${u.id}">`;
                        document.body.appendChild(f); f.submit();
                    };
                    sRes.appendChild(div);
                });
            });
        }, 400);
    });
}

setInterval(() => {
    let isModalOpen = Array.from(document.querySelectorAll('.mod')).some(m => window.getComputedStyle(m).display !== 'none');
    let isMenuOpen = document.querySelector('.menu-dropdown.show');
    if (isModalOpen || isMenuOpen) return;

    fetch('chat.php')
        .then(res => res.text())
        .then(html => {
            let parser = new DOMParser();
            let doc = parser.parseFromString(html, 'text/html');
            
            ['list-personal', 'list-groups'].forEach(listId => {
                let oldList = document.getElementById(listId);
                let newList = doc.getElementById(listId);
                if (oldList && newList && oldList.innerHTML !== newList.innerHTML) {
                    oldList.innerHTML = newList.innerHTML;
                }
            });
        })
        .catch(err => console.error('Live reload failed:', err));
}, 4000);
</script>
<?php if(file_exists('footer.php')) include 'footer.php'; ?>

</body>
</html>
