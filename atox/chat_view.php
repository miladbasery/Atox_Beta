<?php
require 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

define('CHAT_SECRET_KEY', 'Atox_Secure_Chat_Key_2026_@!#123'); 

function decryptMessage($text) {
    if (empty($text)) return $text;
    $decoded = base64_decode($text, true);
    if ($decoded !== false && strpos($decoded, '::') !== false) {
        list($encrypted_data, $iv) = explode('::', $decoded, 2);
        if (strlen($iv) === openssl_cipher_iv_length('aes-256-cbc')) {
            $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', CHAT_SECRET_KEY, 0, $iv);
            return $decrypted !== false ? $decrypted : $text;
        }
    }
    return $text;
}
$uid = $_SESSION['user_id'];

if (isset($_POST['join_invite_code']) && !empty($_POST['join_invite_code'])) {
    header('Content-Type: application/json');
    $inv = $_POST['join_invite_code'];
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE invite_link = ? AND is_group = 1");
    $stmt->execute([$inv]);
    $g = $stmt->fetch();
    if ($g) {
        $pdo->prepare("INSERT IGNORE INTO participants (conversation_id, user_id) VALUES (?, ?)")->execute([$g['id'], $uid]);
        echo json_encode(['success' => true, 'id' => $g['id']]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

if (isset($_GET['invite']) && !empty($_GET['invite'])) {
    $invite_code = $_GET['invite'];
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE invite_link = ? AND is_group = 1");
    $stmt->execute([$invite_code]);
    $group_invite_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($group_invite_data) {
        $conv_id = $group_invite_data['id'];
        $stmt = $pdo->prepare("SELECT 1 FROM participants WHERE conversation_id = ? AND user_id = ?");
        $stmt->execute([$conv_id, $uid]);
        if ($stmt->fetchColumn()) { header("Location: chat_view.php?id=" . $conv_id); exit; }
        $is_joining_via_link = true; 
    } else { die("لینک دعوت نامعتبر است یا گروه حذف شده است."); }
} else {
    $is_joining_via_link = false;
    $conv_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$conv_id) { header("Location: chat.php"); exit; }

    $stmt = $pdo->prepare("SELECT * FROM participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conv_id, $uid]);
    if ($stmt->rowCount() === 0) { header("Location: chat.php"); exit; }

    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND user_id != ? AND is_read = 0")->execute([$conv_id, $uid]);
}

$is_group_admin = false;
$group_admin_id = 0;

if (!$is_joining_via_link) {
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
    $stmt->execute([$conv_id]);
    $chat_info = $stmt->fetch();

    $chat_title = 'ناشناس'; $chat_avatar = ''; $is_verified = false; $is_online = false; $last_seen_text = ''; $other_user_id = 0;
    $i_blocked_them = false; $they_blocked_me = false;
    $other_user_data = null; $group_members = [];

    if ($chat_info && $chat_info['is_group']) {
        $chat_title = htmlspecialchars($chat_info['group_name'] ?? 'گروه');
        $chat_avatar = !empty($chat_info['group_avatar']) ? htmlspecialchars($chat_info['group_avatar']) : "https://ui-avatars.com/api/?name=".urlencode($chat_title)."&background=random&color=fff&bold=true";
        $group_admin_id = $chat_info['admin_id'];
        if ($group_admin_id == $uid) $is_group_admin = true;
        
        $stmt = $pdo->prepare("SELECT u.id, u.name, u.avatar, u.username, u.is_verified FROM users u JOIN participants p ON u.id = p.user_id WHERE p.conversation_id = ?");
        $stmt->execute([$conv_id]);
        $group_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $stmt = $pdo->prepare("SELECT u.* FROM users u JOIN participants p ON u.id = p.user_id WHERE p.conversation_id = ? AND p.user_id != ?");
        $stmt->execute([$conv_id, $uid]);
        $other_user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($other_user_data) {
            $other_user_id = $other_user_data['id'];
            $chat_title = htmlspecialchars($other_user_data['name'] ?? 'کاربر');
            $chat_avatar = !empty($other_user_data['avatar']) ? htmlspecialchars($other_user_data['avatar']) : "https://ui-avatars.com/api/?name=".urlencode($chat_title)."&background=random&color=fff&bold=true";
            $is_verified = !empty($other_user_data['is_verified']);
            
            if ($other_user_data['last_seen'] && strtotime($other_user_data['last_seen']) >= time() - 300) {
                $is_online = true; $last_seen_text = 'آنلاین';
            } else { $last_seen_text = 'آخرین بازدید اخیراً'; }
            
            $stmt_blk = $pdo->prepare("SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
            $stmt_blk->execute([$uid, $other_user_id]);
            if($stmt_blk->fetchColumn()) $i_blocked_them = true;

            $stmt_blk->execute([$other_user_id, $uid]);
            if($stmt_blk->fetchColumn()) $they_blocked_me = true;
        }
    }

    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.name, u.avatar, u.is_verified,
                   r.message_text as reply_msg_text, ru.name as reply_user_name
            FROM messages m 
            JOIN users u ON m.user_id = u.id 
            LEFT JOIN messages r ON m.reply_to_id = r.id
            LEFT JOIN users ru ON r.user_id = ru.id
            WHERE m.conversation_id = ? AND m.is_deleted = 0
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conv_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("SELECT m.*, u.name, u.avatar, u.is_verified FROM messages m JOIN users u ON m.user_id = u.id WHERE m.conversation_id = ? ORDER BY m.created_at ASC");
        $stmt->execute([$conv_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $max_read_id = 0;
    foreach($messages as &$m) {
        $m['message_text'] = decryptMessage($m['message_text']);
        if(isset($m['reply_msg_text'])) {
            $m['reply_msg_text'] = decryptMessage($m['reply_msg_text']);
        }
        if ($m['user_id'] == $uid && $m['is_read']) $max_read_id = max($max_read_id, $m['id']);
    }
    unset($m);
}

$blue_tick = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg>';
$ic_dots = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>';
$ic_send = '<svg viewBox="0 0 24 24" id="sendIcon" style="width:20px;height:20px;fill:#fff;transform:translateY(1px) translateX(-2px)"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';
$ic_edit_btn = '<svg viewBox="0 0 24 24" id="editIcon" style="width:20px;height:20px;fill:#fff;display:none;"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>';
$ic_reply = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M10 9V5l-7 7 7 7v-4.1c5 0 8.5 1.6 11 5.1-1-5-4-10-11-11z"/></svg>';
$ic_reply_small = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;cursor:pointer;opacity:0.7"><path d="M10 9V5l-7 7 7 7v-4.1c5 0 8.5 1.6 11 5.1-1-5-4-10-11-11z"/></svg>';
$ic_edit = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
$ic_trash = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M15 3H9v2H4v2h16V5h-5V3zM6 9v10c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V9H6z"></path></svg>';
$ic_copy = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';
$ic_arrow_down = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M12 19.5L4.5 12l1.41-1.41L11 15.17V3h2v12.17l5.09-5.08L19.5 12 12 19.5z"/></svg>';



function getDayLabel($date) {
    $ts = strtotime($date);
    $diff = (strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $ts))) / 86400;
    if($diff == 0) return 'امروز';
    if($diff == 1) return 'دیروز';
    return date('Y/m/d', $ts); 
}

function formatMessageText($text) {
    $text = htmlspecialchars($text ?? '');
    $text = preg_replace_callback('/(?P<url>(https?:\/\/|www\.)[^\s<]+)/i', function($matches) {
        $url = $matches['url'];
        $href = str_starts_with(strtolower($url), 'www.') ? 'http://' . $url : $url;
        return '<a href="'.$href.'" target="_blank" rel="noopener noreferrer" style="color:#1d9bf0; text-decoration:underline; font-weight:bold; direction:ltr; display:inline-block;" onclick="event.stopPropagation();">'.$url.'</a>';
    }, $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text);
    return nl2br($text);
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0, viewport-fit=cover">
<title><?= $is_joining_via_link ? 'عضویت در گروه' : 'گفتگو با ' . $chat_title ?></title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { 
    --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; 
    --x-hover:rgba(15,20,25,0.05); --x-modal:rgba(0,0,0,0.5);
    --msg-me:#1d9bf0; --msg-them:#f1f1f1; --text-them:#0f1419; --text-me:#fff;
    --chat-bg:#ffffff; --glass-bg:rgba(255,255,255,0.85); --glass-border:rgba(0,0,0,0.05);
    --inp-bg: rgba(0,0,0,0.05);
}
.dark { 
    --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; 
    --x-hover:rgba(255,255,255,0.05); --x-modal:rgba(255,255,255,0.2);
    --msg-me:#2b5278; --msg-them:#182533; --text-them:#e9edef; --text-me:#fff;
    --chat-bg:#000; --glass-bg:rgba(0,0,0,0.85); --glass-border:rgba(255,255,255,0.1); 
    --inp-bg: rgba(255,255,255,0.1);
}

*{margin:0;padding:0;box-sizing:border-box; font-family:"Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;}

body, html { height: 100%; height: 94dvh; display: flex; flex-direction: column; overflow: hidden; width: 100%; top: 0; left: 0; background:var(--chat-bg); color:var(--x-black); -webkit-tap-highlight-color:transparent; overscroll-behavior-y: none; touch-action: none; }
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0; touch-action: manipulation;}

.chat-container { display: flex; flex-direction: column; flex: 1; width: 100%; position: relative; background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); background-blend-mode: overlay; overflow: hidden; }

.chat-hdr { flex-shrink: 0; height: 60px; z-index: 50; background: var(--glass-bg); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); padding: 0 10px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--glass-border); color: var(--x-black); }
.hdr-left { display:flex; align-items:center; flex:1; cursor:pointer; gap:10px; overflow:hidden; }
.back-btn { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:0.2s; flex-shrink:0;}
.back-btn:hover { background:var(--x-hover); }
.hdr-av { width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid var(--glass-border); flex-shrink:0;}
.hdr-info { display:flex; flex-direction:column; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
.hdr-name { font-weight:bold; font-size:15px; display:flex; align-items:center; color:var(--x-black); overflow:hidden; text-overflow:ellipsis; }
.hdr-status { font-size:11px; color:var(--x-gray); margin-top:2px; overflow:hidden; text-overflow:ellipsis; }
.hdr-status.online { color:#00ba7c; font-weight:bold; }

.menu-wrap {position:relative; flex-shrink:0;}
.icon-btn { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--x-black); transition:0.3s ease; }
.icon-btn:hover, .icon-btn:active { background:var(--x-hover); }
.menu-dropdown { position:absolute; top:50px; left:0; background:var(--x-bg); border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.3); width:190px; display:none; flex-direction:column; z-index:999; overflow:hidden; border:1px solid var(--glass-border); }
.menu-dropdown.show {display:flex; animation: fadeIn 0.2s ease;}
.menu-item {padding:14px 15px; font-size:14px; font-weight:bold; color:var(--x-black); transition:0.2s; text-align:right;}
.menu-item:hover {background:var(--x-hover);}
.menu-item.danger {color:#f4212e;}

.chat-body { flex: 1; overflow-y: auto; padding: 15px; padding-bottom: calc(85px + env(safe-area-inset-bottom)); display:flex; flex-direction:column; gap:12px; scroll-behavior: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch; touch-action: pan-y; overscroll-behavior-y: contain; }
.chat-body::before { content: ""; flex: 1 1 auto; }
.chat-body::-webkit-scrollbar { width:4px; }
.chat-body::-webkit-scrollbar-thumb { background:rgba(128,128,128,0.3); border-radius:10px; }

.date-divider { display:flex; justify-content:center; margin:15px 0; }
.date-badge { background:var(--glass-bg); color:var(--x-black); padding:4px 12px; border-radius:20px; font-size:12px; font-weight:bold; backdrop-filter:blur(5px); border: 1px solid var(--glass-border); }

.msg-row { display:flex; width:100%; animation: slideUp 0.3s ease forwards; transition: transform 0.2s, background 0.3s; position: relative; margin-bottom: 2px; gap: 8px;}
.msg-row.active { background: rgba(128,128,128,0.1); border-radius: 12px; }
.msg-row.me { justify-content: flex-start; flex-direction: row;} 
.msg-row.them { justify-content: flex-start; flex-direction: row-reverse;} 

.msg-av-wrap { width: 36px; display: flex; flex-direction: column; justify-content: flex-end; flex-shrink:0; cursor: pointer;}
.msg-av { width:36px; height:36px; border-radius:50%; object-fit:cover; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}

.msg-bubble { max-width:75%; padding:8px 12px; font-size:15px; line-height:1.5; word-wrap:break-word; position:relative; min-width:90px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); cursor: pointer; user-select: none; }
.msg-row.me .msg-bubble { background:var(--msg-me); color:var(--text-me); border-radius:18px 18px 4px 18px; }
.msg-row.them .msg-bubble { background:var(--msg-them); color:var(--text-them); border-radius:18px 18px 18px 4px; }

.msg-reply-box { background: rgba(0,0,0,0.08); border-right: 3px solid #fff; padding: 4px 8px; border-radius: 6px; margin-bottom: 6px; font-size: 13px; opacity: 0.9; }
.dark .msg-reply-box { background: rgba(255,255,255,0.1); }
.msg-row.them .msg-reply-box { border-right: 3px solid var(--x-blue); }
.reply-sender { font-weight: bold; margin-bottom: 2px; font-size: 12px; }
.reply-text-preview { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

.sender-name-wrap { display:flex; align-items:center; gap:5px; margin-bottom:4px;}
.sender-name { font-size:13px; font-weight:bold; color:var(--x-blue); }
.admin-badge { background: var(--x-blue); color: #fff; font-size: 9px; padding: 2px 5px; border-radius: 4px; font-weight: bold; }

.msg-text { word-break: break-word; }
.msg-meta { display:flex; align-items:center; justify-content:flex-end; margin-top:4px; font-size:11px; gap:4px; }
.msg-row.me .msg-meta { color:rgba(255,255,255,0.7); }
.msg-row.them .msg-meta { color:var(--x-gray); }
.dark .msg-row.them .msg-meta { color:rgba(255,255,255,0.5); }
.edited-tag { font-style: italic; font-size: 10px; margin-left: 5px; }

.chat-footer-wrap { position: fixed; bottom: 0; left: 0; width: 100%; z-index: 90; background: var(--glass-bg); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); border-top:1px solid var(--glass-border); padding-bottom: env(safe-area-inset-bottom); display: flex; flex-direction: column; transition: 0.3s;}
.action-preview-box { display: none; padding: 10px 15px; background: rgba(128,128,128,0.1); border-bottom: 1px solid var(--glass-border); align-items: center; justify-content: space-between; }
.action-preview-info { flex: 1; border-right: 3px solid var(--x-blue); padding-right: 10px; overflow: hidden; }
.action-preview-title { font-size: 12px; color: var(--x-blue); font-weight: bold; margin-bottom: 2px; }
.action-preview-text { font-size: 13px; color: var(--x-gray); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.action-cancel-btn { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; color: var(--x-gray); border-radius: 50%; background: var(--x-hover); cursor: pointer; }

.chat-footer-box { display:flex; align-items:flex-end; padding:10px 15px; gap:10px; width: 100%;}
.inp-msg { flex:1; background:var(--inp-bg); border:1px solid transparent; padding:12px 15px; font-size:15px; border-radius: 20px; color:var(--x-black); outline:none; font-family: inherit; transition: border 0.2s; resize: none; max-height: 120px; overflow-y: auto; line-height: 1.4; display: block;}
.inp-msg::placeholder { color:var(--x-gray); }
.inp-msg:focus { border-color:var(--x-blue); background:var(--glass-bg); }
.btn-send { background:var(--x-blue); width:46px; height:46px; border-radius:50%; display:flex; justify-content:center; align-items:center; cursor:pointer; transition:transform 0.2s; flex-shrink:0; box-shadow: 0 4px 10px rgba(29,155,240,0.3); margin-bottom: 0;}
.btn-send:active { transform:scale(0.9); }

.btn-scroll-down { position: fixed; right: 20px; bottom: calc(90px + env(safe-area-inset-bottom)); width: 42px; height: 42px; border-radius: 50%; background: var(--glass-bg); border: 1px solid var(--glass-border); box-shadow: 0 4px 15px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; color: var(--x-blue); cursor: pointer; z-index: 85; opacity: 0; transform: translateY(20px) scale(0.9); pointer-events: none; transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); backdrop-filter: blur(10px); }
.btn-scroll-down.show { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }

.bs-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100000; opacity: 0; pointer-events: none; transition: 0.3s; backdrop-filter: blur(3px); }
.bs-overlay.show { opacity: 1; pointer-events: auto; }
.bs-content { position: fixed; bottom: 0; left: 0; right: 0; background: var(--x-bg); border-radius: 24px 24px 0 0; padding: 20px 15px 30px; z-index: 100001; transform: translateY(100%); transition: 0.3s cubic-bezier(0.1, 1, 0.2, 1); box-shadow: 0 -10px 40px rgba(0,0,0,0.2); }
.bs-content.show { transform: translateY(0); }
.bs-handle { width: 40px; height: 5px; background: var(--x-border); border-radius: 10px; margin: 0 auto 20px; }
.bs-item { display: flex; align-items: center; gap: 15px; padding: 15px; border-radius: 16px; font-size: 16px; font-weight: bold; color: var(--x-black); cursor: pointer; transition: 0.2s; }
.bs-item:hover { background: var(--x-hover); }
.bs-item.danger { color: #f4212e; }

.ic-tick { width:16px; height:16px; fill:currentColor; }
.ic-read { width:16px; height:16px; fill:#4ade80;}

@keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
@keyframes slideUp { 0%{opacity:0; transform:translateY(10px);} 100%{opacity:1; transform:translateY(0);} }
</style>
</head>
<body>

<?php if(file_exists('header.php')) include 'header.php'; ?>

<main class="chat-container">
    <?php if($is_joining_via_link): ?>
        <div style="display:flex; height:100%; align-items:center; justify-content:center; padding:20px; background: var(--chat-bg);">
            <div class="m-c" style="width:100%;">
                <img src="<?=!empty($group_invite_data['group_avatar']) ? $group_invite_data['group_avatar'] : 'https://ui-avatars.com/api/?name='.$group_invite_data['group_name']?>" style="width:100px; height:100px; border-radius:50%; margin-bottom:15px; object-fit:cover; border:3px solid var(--x-blue); padding:3px;">
                <h2 style="margin-bottom:10px; color:var(--x-black);">عضویت در گروه</h2>
                <h3 style="margin-bottom:15px; color:var(--x-blue);"><?=htmlspecialchars($group_invite_data['group_name'])?></h3>
                <button class="m-btn primary" onclick="joinGroupViaLink('<?=htmlspecialchars($group_invite_data['invite_link'])?>')">پیوستن به گروه</button>
                <button class="m-btn outline" style="margin-top:10px;" onclick="location.href='chat.php'">انصراف</button>
            </div>
        </div>
    <?php else: ?>
        
        <header class="chat-hdr">
            <div class="hdr-left" onclick="showProfileModal()">
                <a href="chat.php" class="back-btn" onclick="event.stopPropagation()">
                    <svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"></path></svg>
                </a>
                <img src="<?=$chat_avatar?>" class="hdr-av">
                <div class="hdr-info">
                    <div class="hdr-name"><?=$chat_title?> <?php if($is_verified) echo $blue_tick; ?></div>
                    <div class="hdr-status <?=$is_online ? 'online' : ''?>"><?=$chat_info['is_group'] ? count($group_members).' عضو' : $last_seen_text?></div>
                </div>
            </div>
            <div class="menu-wrap">
                <button class="icon-btn" onclick="toggleMenu('cMenu', event)"><?=$ic_dots?></button>
                <div class="menu-dropdown" id="cMenu">
                    <?php if($chat_info['is_group']): ?>
                        <?php if($is_group_admin): ?>
                            <div class="menu-item danger" onclick="doActionConfirm('delete_group', <?=$conv_id?>, 'گروه کاملاً حذف شود؟')">حذف کل گروه</div>
                        <?php else: ?>
                            <div class="menu-item danger" onclick="doActionConfirm('leave_group', <?=$conv_id?>, 'از گروه خارج می‌شوید؟')">خروج از گروه</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if($they_blocked_me): ?>
                            <div class="menu-item" style="color:var(--x-gray)">شما مسدود شده‌اید</div>
                        <?php elseif($i_blocked_them): ?>
                            <div class="menu-item" onclick="doActionConfirm('unblock_user', <?=$other_user_id?>, 'رفع مسدودیت کاربر؟')">آن‌بلاک کردن</div>
                        <?php else: ?>
                            <div class="menu-item danger" onclick="doActionConfirm('block_user', <?=$other_user_id?>, 'مسدود کردن کاربر؟')">بلاک کردن</div>
                        <?php endif; ?>
                        <div class="menu-item danger" onclick="doActionConfirm('delete_chat_both_sides', <?=$conv_id?>, 'حذف دوطرفه چت')">حذف دوطرفه چت</div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="chat-body" id="chatBody">
            <?php 
            $last_date = '';
            foreach($messages as $m): 
                $is_me = ($m['user_id'] == $uid);
                $time = date('H:i', strtotime($m['created_at']));
                $current_date = getDayLabel($m['created_at']);
                $is_sender_admin = ($chat_info['is_group'] && $m['user_id'] == $group_admin_id);
                $sender_name_html = htmlspecialchars($m['name']);
                
                if($current_date !== $last_date) {
                    echo '<div class="date-divider"><div class="date-badge">'.$current_date.'</div></div>';
                    $last_date = $current_date;
                }
            ?>
                <div class="msg-row <?=$is_me ? 'me' : 'them'?>" id="msg-<?=$m['id']?>" 
                     data-id="<?=$m['id']?>" 
                     data-text="<?=htmlspecialchars($m['message_text'])?>" 
                     data-sender="<?=$sender_name_html?>">
                    
                    <?php if(!$is_me && $chat_info['is_group']): ?>
                        <div class="msg-av-wrap" onclick="showUserProfileModal(<?=$m['user_id']?>, '<?=$sender_name_html?>', '<?=!empty($m['avatar']) ? htmlspecialchars($m['avatar']) : ''?>', <?=$m['is_verified']?'true':'false'?>)">
                            <img src="<?=!empty($m['avatar']) ? htmlspecialchars($m['avatar']) : 'https://ui-avatars.com/api/?name='.$sender_name_html?>" class="msg-av">
                        </div>
                    <?php endif; ?>
                    
                    <div class="msg-bubble" onclick="openMessageActions(<?=$m['id']?>, <?=$is_me?'true':'false'?>, `<?=htmlspecialchars($m['message_text'])?>`, `<?=$sender_name_html?>`)">
                        
                        <?php if(!$is_me && $chat_info['is_group']): ?>
                            <div class="sender-name-wrap">
                                <span class="sender-name"><?=$sender_name_html?></span>
                                <?php if($is_sender_admin): ?><span class="admin-badge">سازنده</span><?php endif; ?>
                                <span style="margin-right:auto;" onclick="initDirectReply(<?=$m['id']?>, `<?=htmlspecialchars($m['message_text'])?>`, `<?=$sender_name_html?>`); event.stopPropagation();"><?=$ic_reply_small?></span>
                            </div>
                        <?php endif; ?>

                        <?php if(!empty($m['reply_msg_text'])): ?>
                            <div class="msg-reply-box" onclick="scrollToMsg(<?=$m['reply_to_id'] ?? 0?>); event.stopPropagation();">
                                <div class="reply-sender"><?=htmlspecialchars($m['reply_user_name'] ?? 'کاربر')?></div>
                                <div class="reply-text-preview"><?=htmlspecialchars($m['reply_msg_text'])?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="msg-text" id="msg-text-<?=$m['id']?>"><?=formatMessageText($m['message_text'])?></div>
                        
                        <div class="msg-meta">
                            <?php if(isset($m['is_edited']) && $m['is_edited']): ?><span class="edited-tag">(ویرایش شده)</span><?php endif; ?>
                            <span><?=$time?></span>
                            <?php if($is_me): ?>
                                <span class="tick-box">
                                    <?php if($m['id'] <= $max_read_id || $m['is_read']): ?>
                                        <svg class="ic-read" viewBox="0 0 24 24"><path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/></svg>
                                    <?php else: ?>
                                        <svg class="ic-tick" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="btn-scroll-down" id="scrollDownBtn"><?=$ic_arrow_down?></div>


    <?php endif; ?>
</main>

<div class="bs-overlay" id="msgActionSheetBg" onclick="closeMessageActions()"></div>
<div class="bs-content" id="msgActionSheet">
    <div class="bs-handle"></div>
    <div class="bs-item" onclick="initReply()">
        <?=$ic_reply?> <span>پاسخ دادن (Reply)</span>
    </div>
    <div class="bs-item" id="bsEditBtn" style="display:none;" onclick="initEdit()">
        <?=$ic_edit?> <span>ویرایش پیام</span>
    </div>
    <div class="bs-item danger" id="bsDeleteBtn" style="display:none;" onclick="confirmDeleteMessage()">
        <?=$ic_trash?> <span>حذف پیام</span>
    </div>
</div>

<?php include 'modal_chat.php'; ?>

<script>
    const CHAT_CONFIG = {
        isGroupAdmin: <?=$is_group_admin ? 'true' : 'false'?>,
        convId: <?=(int)($conv_id ?? 0)?>,
        baseUrl: "<?=$base_url?>"
    };

    document.addEventListener('DOMContentLoaded', () => {
        const chatBody = document.getElementById('chatBody');
        const scrollBtn = document.getElementById('scrollDownBtn');
        let currentAction = 'send';
        let targetMsgId = null;
        let targetMsgText = '';
        let targetMsgSender = '';
        
        window.autoResizeInput = function(el) {
            el.style.height = 'auto';
            let newHeight = el.scrollHeight;
            if(newHeight > 120) newHeight = 120;
            el.style.height = newHeight + 'px';
            if(el.value.trim() === '') el.style.height = 'auto';
        };

        window.scrollToBottom = (smooth = false) => { 
            if(!chatBody) return;
            if(smooth) chatBody.scrollTo({ top: chatBody.scrollHeight, behavior: 'smooth' });
            else chatBody.scrollTop = chatBody.scrollHeight; 
        };

        scrollToBottom(false);
        setTimeout(() => scrollToBottom(false), 300);

        if(chatBody && scrollBtn) {
            chatBody.addEventListener('scroll', () => {
                const isScrolledUp = chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight > 150;
                if(isScrolledUp) scrollBtn.classList.add('show');
                else scrollBtn.classList.remove('show');
            });
            scrollBtn.addEventListener('click', () => scrollToBottom(true));
        }

        window.fetchNewMessages = function() {
            if (!chatBody) return;
            fetch(window.location.href)
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newChatBody = doc.getElementById('chatBody');
                if(newChatBody && chatBody.innerHTML !== newChatBody.innerHTML) {
                    const isScrolledToBottom = chatBody.scrollHeight - chatBody.clientHeight <= chatBody.scrollTop + 50;
                    chatBody.innerHTML = newChatBody.innerHTML;
                    if(isScrolledToBottom) scrollToBottom();
                }
            }).catch(e => console.log(e));
        };
        setInterval(fetchNewMessages, 3000);

        window.toggleMenu = function(id, e) { 
            e.stopPropagation(); e.preventDefault(); 
            document.getElementById(id).classList.toggle('show'); 
        };
        
        window.onclick = () => document.querySelectorAll('.menu-dropdown').forEach(m => m.classList.remove('show'));

        const bsOverlay = document.getElementById('msgActionSheetBg');
        const bsSheet = document.getElementById('msgActionSheet');
        
        window.openMessageActions = function(msgId, isMe, text, senderName) {
            targetMsgId = msgId; targetMsgText = text; targetMsgSender = senderName;
            document.getElementById('bsEditBtn').style.display = isMe ? 'flex' : 'none';
            document.getElementById('bsDeleteBtn').style.display = (isMe || CHAT_CONFIG.isGroupAdmin) ? 'flex' : 'none';
            document.querySelectorAll('.msg-row').forEach(el => el.classList.remove('active'));
            document.getElementById('msg-'+msgId).classList.add('active');
            if(bsOverlay) bsOverlay.classList.add('show'); 
            if(bsSheet) bsSheet.classList.add('show');
        };

        window.closeMessageActions = function() {
            if(bsOverlay) bsOverlay.classList.remove('show'); 
            if(bsSheet) bsSheet.classList.remove('show');
            document.querySelectorAll('.msg-row').forEach(el => el.classList.remove('active'));
        };

        window.initReply = function() {
            closeMessageActions();
            currentAction = 'reply';
            const previewBox = document.getElementById('actionPreviewBox');
            if(previewBox) previewBox.style.display = 'flex';
            document.getElementById('actionTitle').innerText = 'پاسخ به ' + targetMsgSender;
            document.getElementById('actionText').innerText = targetMsgText;
            document.getElementById('sendIcon').style.display = 'block';
            document.getElementById('editIcon').style.display = 'none';
            const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
            if(msgInput) msgInput.focus();
        };

        window.initDirectReply = function(msgId, text, senderName) {
            targetMsgId = msgId; targetMsgText = text; targetMsgSender = senderName;
            initReply();
        };

        window.initEdit = function() {
            closeMessageActions();
            currentAction = 'edit';
            const previewBox = document.getElementById('actionPreviewBox');
            if(previewBox) previewBox.style.display = 'flex';
            document.getElementById('actionTitle').innerText = 'ویرایش پیام';
            document.getElementById('actionText').innerText = targetMsgText;
            const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
            if(msgInput) {
                msgInput.value = targetMsgText;
                autoResizeInput(msgInput);
                msgInput.focus();
            }
            document.getElementById('sendIcon').style.display = 'none';
            document.getElementById('editIcon').style.display = 'block';
        };

        window.cancelAction = function() {
            currentAction = 'send'; targetMsgId = null;
            const previewBox = document.getElementById('actionPreviewBox');
            if(previewBox) previewBox.style.display = 'none';
            const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
            if(msgInput) {
                msgInput.value = '';
                autoResizeInput(msgInput);
            }
            document.getElementById('sendIcon').style.display = 'block';
            document.getElementById('editIcon').style.display = 'none';
        };

        window.scrollToMsg = function(id) {
            const el = document.getElementById('msg-' + id);
            if(el) { 
                el.scrollIntoView({behavior: "smooth", block: "center"}); 
                el.classList.add('active'); 
                setTimeout(() => el.classList.remove('active'), 1500); 
            }
        };

        window.confirmDeleteMessage = function() {
            closeMessageActions();
            if(typeof window.doActionConfirm === 'function') {
                window.doActionConfirm('delete_msg', targetMsgId, 'آیا از حذف این پیام مطمئن هستید؟');
            }
        };

        window.sendMessage = function() {
            const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
            if(!msgInput) return;
            const text = msgInput.value.trim();
            if(!text) return;

            const formData = new FormData();
            formData.append('conversation_id', CHAT_CONFIG.convId);
            formData.append('message_text', text);
            
            if(currentAction === 'edit') {
                formData.append('action', 'edit_msg');
                formData.append('msg_id', targetMsgId);
            } else {
                formData.append('action', 'send_msg');
                if(currentAction === 'reply') formData.append('reply_to_id', targetMsgId);
            }
            
            fetch('actions.php', { method: 'POST', body: formData }).then(() => {
                cancelAction();
                fetchNewMessages();
                setTimeout(() => scrollToBottom(true), 100);
            });
        };

        document.addEventListener('submit', function(e) {
            const form = e.target.closest('form');
            if (form) {
                e.preventDefault();
                sendMessage();
            }
        });

        const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
        if(msgInput) {
            msgInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }

        window.joinGroupViaLink = function(inviteCode) {
            const fd = new FormData();
            fd.append('join_invite_code', inviteCode);
            fetch('chat_view.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.success) location.href = 'chat_view.php?id=' + data.id;
                else alert('خطا در پیوستن به گروه. لینک نامعتبر است.');
            }).catch(() => alert('خطا در ارتباط با سرور.'));
        };
    });
</script>
<?php include 'footer_chat.php'; ?>

</body>
</html>
