<?php
ob_start();
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

require 'db.php';

$kanoon_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$uid = filter_var($_SESSION['user_id'] ?? 0, FILTER_VALIDATE_INT);
$user_role = $_SESSION['role'] ?? 'user';

$stmt = $pdo->prepare("SELECT * FROM kanoons WHERE id = ? LIMIT 1");
$stmt->execute([$kanoon_id]);
$kanoon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$kanoon) die(htmlspecialchars('کانون یافت نشد.', ENT_QUOTES, 'UTF-8'));

$can_manage = ($user_role === 'admin' || (isset($_SESSION['username']) && $_SESSION['username'] === 'milad') || $uid === (int)$kanoon['creator_id']);

$is_following = false;
if ($uid > 0) {
    $stmtF = $pdo->prepare("SELECT 1 FROM kanoon_members WHERE kanoon_id=? AND user_id=? LIMIT 1");
    $stmtF->execute([$kanoon_id, $uid]);
    $is_following = (bool)$stmtF->fetchColumn();
}

$tab = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tab'] ?? '');
if (empty($tab)) {
    if (empty($kanoon['hide_members']) || $can_manage) $tab = 'members';
    elseif (empty($kanoon['hide_terms']) || $can_manage) $tab = 'jozves';
    elseif (empty($kanoon['hide_projects']) || $can_manage) $tab = 'projects';
    else $tab = 'members';
}

$chat_href = "";
$convData = null;
if (!empty($kanoon['conversation_id'])) {
    $stmtC = $pdo->prepare("SELECT id, group_name, group_description, invite_link FROM conversations WHERE id=? LIMIT 1");
    $stmtC->execute([$kanoon['conversation_id']]);
    $convData = $stmtC->fetch(PDO::FETCH_ASSOC);

    if ($convData) {
        $stmtM = $pdo->prepare("SELECT 1 FROM participants WHERE conversation_id=? AND user_id=? LIMIT 1");
        $stmtM->execute([$kanoon['conversation_id'], $uid]);
        $is_member = $stmtM->fetchColumn();

        if ($is_member) {
            $chat_href = "chat_view.php?id=" . (int)$kanoon['conversation_id'];
        } else {
            if (!empty($convData['invite_link'])) {
                $chat_href = "chat.php?invite=" . urlencode($convData['invite_link']);
            } else {
                $chat_href = "chat_view.php?id=" . (int)$kanoon['conversation_id'];
            }
        }
    }
}

function pNum($str) { return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str); }
function toJalali($date) {
    if(empty($date)) return '';
    $ts = strtotime($date); $diff = time() - $ts;
    if($diff < 60) return 'لحظاتی پیش';
    if($diff < 3600) return pNum(floor($diff / 60)) . ' دقیقه پیش';
    if($diff < 86400) return pNum(floor($diff / 3600)) . ' ساعت پیش';
    return pNum(date('Y/m/d', $ts));
}

function getImg($path, $name, $is_blue = false) {
    if (!empty($path)) {
        if (strpos($path, 'http') === 0) return htmlspecialchars($path, ENT_QUOTES, 'UTF-8'); 
        if (file_exists($path) && !is_dir($path)) return htmlspecialchars($path, ENT_QUOTES, 'UTF-8'); 
        if (file_exists('uploads/' . basename($path)) && !is_dir('uploads/' . basename($path))) return 'uploads/' . htmlspecialchars(basename($path), ENT_QUOTES, 'UTF-8'); 
    }
    $bg = $is_blue ? '0D8cd7&color=fff' : 'random';
    return "https://ui-avatars.com/api/?name=".urlencode($name)."&background=".$bg;
}
$ic_plus = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M11 11V4h2v7h7v2h-7v7h-2v-7H4v-2h7z"/></svg>';
$ic_edit = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M19.4 7.34L16.66 4.6c-.39-.39-1.02-.39-1.41 0L3 16.84V19.6c0 .55.45 1 1 1h2.76l12.24-12.24c.39-.39.39-1.02 0-1.42zM5 18.6V17.2l9.83-9.83 1.41 1.41L6.41 18.6H5z"/></svg>';
$ic_delete = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#f91880"><path d="M16 9v10H8V9h8m-1.5-6h-5l-1 1H5v2h14V4h-3.5l-1-1zM18 7H6v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7z"/></svg>';
$ic_down = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>';
$ic_link = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>';
$ic_verified = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg>';
$ic_chat = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M21.4274 2.5783C20.9274 2.0673 20.1874 1.8783 19.4974 2.0783L3.40742 6.7273C2.67942 6.9293 2.16342 7.5063 2.02442 8.2383C1.88242 8.9843 2.37842 9.9323 3.02642 10.3283L8.05742 13.4003C8.57342 13.7163 9.23942 13.6373 9.66642 13.2093L15.4274 7.4483C15.7174 7.1473 16.1974 7.1473 16.4874 7.4483C16.7774 7.7373 16.7774 8.2083 16.4874 8.5083L10.7164 14.2693C10.2884 14.6973 10.2084 15.3613 10.5234 15.8783L13.5974 20.9283C13.9574 21.5273 14.5774 21.8683 15.2574 21.8683C15.3374 21.8683 15.4274 21.8683 15.5074 21.8573C16.2874 21.7583 16.9074 21.2273 17.1374 20.4773L21.9074 4.5083C22.1174 3.8283 21.9274 3.0883 21.4274 2.5783Z" fill="#1499ff"/> <path xmlns="http://www.w3.org/2000/svg" opacity="0.4" fill-rule="evenodd" clip-rule="evenodd" d="M3.01049 16.8078C2.81849 16.8078 2.62649 16.7348 2.48049 16.5878C2.18749 16.2948 2.18749 15.8208 2.48049 15.5278L3.84549 14.1618C4.13849 13.8698 4.61349 13.8698 4.90649 14.1618C5.19849 14.4548 5.19849 14.9298 4.90649 15.2228L3.54049 16.5878C3.39449 16.7348 3.20249 16.8078 3.01049 16.8078ZM6.77169 18.0002C6.57969 18.0002 6.38769 17.9272 6.24169 17.7802C5.94869 17.4872 5.94869 17.0132 6.24169 16.7202L7.60669 15.3542C7.89969 15.0622 8.37469 15.0622 8.66769 15.3542C8.95969 15.6472 8.95969 16.1222 8.66769 16.4152L7.30169 17.7802C7.15569 17.9272 6.96369 18.0002 6.77169 18.0002ZM7.02539 21.5682C7.17139 21.7152 7.36339 21.7882 7.55539 21.7882C7.74739 21.7882 7.93939 21.7152 8.08539 21.5682L9.45139 20.2032C9.74339 19.9102 9.74339 19.4352 9.45139 19.1422C9.15839 18.8502 8.68339 18.8502 8.39039 19.1422L7.02539 20.5082C6.73239 20.8012 6.73239 21.2752 7.02539 21.5682Z" fill="#1499ff"/> </g></svg>';

$kanoon_img = getImg($kanoon['image'], $kanoon['name'], true);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?=htmlspecialchars($kanoon['name'], ENT_QUOTES, 'UTF-8')?> - آتوکس</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.7); --x-hover:rgba(15,20,25,0.05); --x-hover-b:rgba(29,155,240,0.1); --x-modal:rgba(0,0,0,0.4); --x-shadow: 0 8px 20px rgba(0,0,0,0.06); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.6); --x-hover:rgba(255,255,255,0.05); --x-modal:rgba(255,255,255,0.1); --x-shadow: 0 8px 20px rgba(0,0,0,0.4); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,sans-serif}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}
.main{width:100%;max-width:650px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);min-height:100vh; margin: 0 auto;}
.hdr{position:sticky;top:0;background:var(--x-bg-trans);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);z-index:10;}
.prof-header { padding: 20px 16px; position: relative; }
.prof-header-top { display: flex; justify-content: space-between; align-items: flex-start; }
.prof-avatar { width: 90px; height: 90px; border-radius: 20%; border: 2px solid var(--x-bg); background: var(--x-bg); object-fit: cover; box-shadow: var(--x-shadow); }
.prof-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap;}
.btn-outline { border: 1px solid var(--x-border); border-radius: 99px; padding: 6px 14px; font-weight: bold; font-size: 13px; transition: 0.2s; background: var(--x-bg-trans); backdrop-filter: blur(5px); }
.btn-outline:hover { background: var(--x-hover); }
.prof-name { font-size: 22px; font-weight: 900; margin-top: 12px; }
.prof-desc { font-size: 14px; color: var(--x-black); margin-top: 6px; line-height: 1.6; }
.verified-badge { color: var(--x-blue); margin-right: 4px; vertical-align: text-bottom; }
.tabs { display:flex; border-bottom:1px solid var(--x-border); overflow-x:auto; scrollbar-width: none; background:var(--x-bg);}
.tabs::-webkit-scrollbar { display: none; }
.tab-link { flex:1; text-align:center; padding:14px 10px; font-size:14px; font-weight:bold; position:relative; transition:0.2s; white-space:nowrap; color:var(--x-gray); }
.tab-link:hover { background: var(--x-hover); }
.tab-link.active { color:var(--x-black); }
.tab-link.active::after { content:''; position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:50%; height:4px; background:var(--x-blue); border-radius:4px; }
.grid-wrap { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px; }
.glass-box { background: var(--x-bg-trans); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid var(--x-border); border-radius: 16px; transition: 0.2s ease-out; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; }
.glass-box:hover { transform: translateY(-3px); box-shadow: var(--x-shadow); border-color: var(--x-blue); z-index: 2; }
.list-item { display: flex; padding: 16px; align-items: center; position: relative; }
.avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-left: 12px; flex-shrink: 0; border: 1px solid var(--x-border); }
.item-content { flex: 1; min-width: 0; }
.item-title { font-weight: bold; font-size: 15px; color: var(--x-black); display: flex; align-items: center; gap: 2px; }
.item-sub { color: var(--x-gray); font-size: 13px; margin-top: 4px; }
.badge-role { display: inline-block; background: var(--x-hover-b); color: var(--x-blue); font-size: 11px; padding: 2px 8px; border-radius: 8px; font-weight: bold; margin-right: 5px; vertical-align: middle; }
.term-container { padding: 12px 16px; }
details.term-acc { margin-bottom: 12px; overflow: hidden; }
summary.term-sum { padding: 16px; font-weight: 800; font-size: 16px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; list-style: none; user-select: none; transition: 0.2s; }
summary.term-sum:hover { background: var(--x-hover); }
details.term-acc[open] summary.term-sum .ic-arrow { transform: rotate(180deg); }
summary.term-sum-special { color: #d97706; background: rgba(217, 119, 6, 0.05); }
summary.term-sum-special:hover { background: rgba(217, 119, 6, 0.1); }
summary.term-sum-mag { color: #7e22ce; background: rgba(126, 34, 206, 0.05); }
summary.term-sum-mag:hover { background: rgba(126, 34, 206, 0.1); }
.course-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-top: 1px solid var(--x-border); transition: 0.2s; }
.course-row:hover { background: var(--x-hover); }
.course-name { font-size: 15px; font-weight: bold; color: var(--x-blue); flex: 1; }
.proj-card { display: flex; gap: 15px; padding: 16px; align-items: flex-start; }
.proj-clickable { flex: 1; display: flex; gap: 15px; cursor: pointer; align-items: flex-start; }
.proj-img { width: 70px; height: 70px; border-radius: 12px; object-fit: cover; flex-shrink: 0; border: 1px solid var(--x-border); }
.proj-desc { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 13px; color: var(--x-gray); margin: 8px 0; line-height: 1.5; }
.proj-meta { font-size: 12px; color: var(--x-gray); display: flex; gap: 15px; }
.action-grp { display: flex; gap: 5px; flex-shrink: 0; }
.btn-back { color: var(--x-black); font-size: 20px; font-weight: bold; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.2s; flex-shrink: 0; }
.btn-back:hover { background: var(--x-hover); }
.mod{display:none;position:fixed;inset:0;background:var(--x-modal);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);}
.m-c{position:relative;background:var(--x-bg);border-radius:24px;width:90%;max-width:450px;padding:24px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:p .3s cubic-bezier(0.175, 0.885, 0.32, 1.275);max-height:90vh;overflow-y:auto;}
.m-hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--x-border);padding-bottom:15px;margin-bottom:15px;}
.input-ui{width:100%;padding:12px;border:1px solid var(--x-border);border-radius:12px;font-size:15px;margin-bottom:12px;background:transparent;color:var(--x-black);outline:none;box-sizing:border-box;}
.input-ui:focus{border-color:var(--x-blue);}
.btn-submit{background:var(--x-black);color:var(--x-bg);border:none;padding:12px;border-radius:99px;font-weight:700;font-size:15px;cursor:pointer;width:100%;transition:0.2s;}
.btn-submit:hover{opacity:0.8;}
.btn-submit.danger{background:#f91880; color:#fff;}
@keyframes p{0%{transform:translateY(30px) scale(0.9);opacity:0}100%{transform:translateY(0) scale(1);opacity:1}}
.search-res { position: absolute; width: 100%; background: var(--x-bg); border: 1px solid var(--x-border); border-radius: 12px; max-height: 200px; overflow-y: auto; z-index: 10; display: none; box-shadow: var(--x-shadow); margin-top: -8px; margin-bottom: 12px; }
.s-item { padding: 10px; border-bottom: 1px solid var(--x-border); cursor: pointer; display: flex; align-items: center; gap: 10px; }
.s-item:hover { background: var(--x-hover); }
.s-item img { width: 30px; height: 30px; border-radius: 50%; }
@media(max-width:600px){ 
    .main{border:none;} 
    .grid-wrap { grid-template-columns: 1fr; } 
}
</style>
</head>
<body>
<div style="display:flex;justify-content:center;">
    <main class="main">
		<?php if(file_exists('header.php')) include 'header.php'; ?>

        <div class="hdr">
            <div style="padding:12px 16px; display:flex; align-items:center; justify-content:space-between;">
                <div style="font-size:18px;font-weight:900; display:flex; align-items:center; gap:10px;">
                    <a href="general.php"class="btn-back"onclick="event.stopPropagation()" style="background:0 0; border:0; cursor:pointer; color:inherit; display:flex;">
					<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"></path></svg>
					</a>
                    <span><?=htmlspecialchars($kanoon['name'], ENT_QUOTES, 'UTF-8')?></span>
                </div>
                <?php if($kanoon['conversation_id'] && !empty($chat_href)): ?>
                    <a href="<?=htmlspecialchars($chat_href, ENT_QUOTES, 'UTF-8')?>" class="btn-outline" style="color:var(--x-blue); border-color:var(--x-blue); padding:5px 12px; font-size:12px; background:var(--x-hover-b); display:flex; align-items:center; gap:4px;">
                        <?=$ic_chat ?? '💬'?> چت رسمی
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="prof-header">
            <div class="prof-header-top">
                <img src="<?=$kanoon_img?>" class="prof-avatar" alt="لوگو">
                <div class="prof-actions">
                    <?php if($uid > 0): ?>
                        <form action="actions.php" method="POST" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>">
                            <input type="hidden" name="action" value="toggle_follow_kanoon">
                            <input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>">
                            <?php if($is_following): ?>
                                <button type="submit" class="btn-outline" style="color:var(--x-black); border-color:var(--x-border);">لغو عضویت</button>
                            <?php else: ?>
                                <button type="submit" class="btn-outline" style="background:var(--x-black); color:var(--x-bg); border-color:var(--x-black);">عضو شدن</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <?php if($can_manage): ?>
                        <button class="btn-outline" onclick="oM('editKanoonModal')">ویرایش کانون</button>
                        
                        <?php if(empty($kanoon['conversation_id'])): ?>
                            <button class="btn-outline" onclick="oM('createGroupModal')">ساخت گروه چت</button>
                        <?php else: ?>
                            <button class="btn-outline" onclick="oM('editGroupModal')">تنظیمات گروه</button>
                            <button class="btn-outline" onclick="oM('deleteGroupModal')" style="color:#f91880; border-color:rgba(249,24,128,0.3);">حذف گروه</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <h1 class="prof-name">
                <?=htmlspecialchars($kanoon['name'], ENT_QUOTES, 'UTF-8')?>
                <?php if($kanoon['is_verified']): ?><span class="verified-badge" title="تایید شده"><?=$ic_verified ?? '✔'?></span><?php endif; ?>
            </h1>
            <p class="prof-desc"><?=htmlspecialchars($kanoon['description'], ENT_QUOTES, 'UTF-8')?></p>
        </div>

        <div class="tabs">
            <?php if(empty($kanoon['hide_members']) || $can_manage): ?>
                <a href="?id=<?=$kanoon_id?>&tab=members" class="tab-link <?=htmlspecialchars($tab, ENT_QUOTES, 'UTF-8')==='members'?'active':''?>">اعضا</a>
            <?php endif; ?>
            <?php if(empty($kanoon['hide_terms']) || $can_manage): ?>
                <a href="?id=<?=$kanoon_id?>&tab=jozves" class="tab-link <?=htmlspecialchars($tab, ENT_QUOTES, 'UTF-8')==='jozves'?'active':''?>">جزوات و دروس</a>
            <?php endif; ?>
            <?php if(empty($kanoon['hide_projects']) || $can_manage): ?>
                <a href="?id=<?=$kanoon_id?>&tab=projects" class="tab-link <?=htmlspecialchars($tab, ENT_QUOTES, 'UTF-8')==='projects'?'active':''?>">پروژه‌ها</a>
            <?php endif; ?>
        </div>

        <div>
            <?php if($tab === 'members'): ?>
                <?php if($can_manage): ?>
                    <div style="padding:16px; padding-bottom:0;">
                        <button class="btn-submit" style="width:auto; padding:8px 20px; font-size:14px; display:inline-flex; align-items:center; gap:5px;" onclick="oM('memberModal')"><?=$ic_plus ?? '+'?> افزودن عضو</button>
                    </div>
                <?php endif; ?>
                <?php
                $members = $pdo->prepare("SELECT km.id as km_id, u.id, u.name, u.username, u.avatar, u.is_verified, km.role_title FROM kanoon_members km JOIN users u ON km.user_id=u.id WHERE km.kanoon_id=? ORDER BY u.is_verified DESC, km.id ASC");
                $members->execute([$kanoon_id]);
                $m_list = $members->fetchAll();
                
                if(empty($m_list)): ?>
                    <div style="text-align:center; padding:50px; color:var(--x-gray);">هنوز عضوی ثبت نشده است.</div>
                <?php else: ?>
                    <div class="grid-wrap">
                        <?php foreach($m_list as $m): 
                            $avatar = getImg($m['avatar'], $m['name']);
                        ?>
                            <div class="list-item glass-box">
                                <a href="profile.php?id=<?=(int)$m['id']?>" style="display:flex; flex:1; align-items:center; text-decoration:none; color:inherit; overflow:hidden;">
                                    <img src="<?=$avatar?>" class="avatar">
                                    <div class="item-content">
                                        <div class="item-title">
                                            <?=htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8')?>
                                            <?php if($m['is_verified']): ?><span class="verified-badge" title="تایید شده"><?=$ic_verified ?? '✔'?></span><?php endif; ?>
                                        </div>
                                        <div class="item-sub">
                                            @<?=htmlspecialchars($m['username'], ENT_QUOTES, 'UTF-8')?> 
                                            <?php if(!empty($m['role_title']) && $m['role_title'] !== 'عضو عادی'): ?>
                                                <span class="badge-role"><?=htmlspecialchars($m['role_title'], ENT_QUOTES, 'UTF-8')?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                                <?php if($can_manage): ?>
                                    <div class="action-grp">
                                        <button class="btn-icon" onclick="openEditRole('<?=(int)$m['km_id']?>', '<?=htmlspecialchars(addslashes($m['role_title']), ENT_QUOTES, 'UTF-8')?>', '<?=htmlspecialchars(addslashes($m['name']), ENT_QUOTES, 'UTF-8')?>')" title="ویرایش"><?=$ic_edit ?? '✎'?></button>
                                        <form action="actions.php" method="POST" style="margin:0;" onsubmit="return confirm('حذف شود؟');">
                                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>">
                                            <input type="hidden" name="action" value="remove_member"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="hidden" name="user_id" value="<?=(int)$m['id']?>">
                                            <button type="submit" class="btn-icon del" title="حذف"><?=$ic_delete ?? '✕'?></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($tab === 'jozves'): ?>
                <?php if($can_manage): ?>
                    <div style="padding:16px; padding-bottom:0;">
                        <button class="btn-submit" style="width:auto; padding:8px 20px; font-size:14px; display:inline-flex; align-items:center; gap:5px;" onclick="oM('courseModal')"><?=$ic_plus ?? '+'?> افزودن محتوا جدید</button>
                    </div>
                <?php endif; ?>
                
                <div class="term-container">
                    
                    <?php if(empty($kanoon['hide_special']) || $can_manage): 
                        $c_spec = $pdo->prepare("SELECT * FROM jozve_groups WHERE kanoon_id=? AND term=9 ORDER BY id DESC");
                        $c_spec->execute([$kanoon_id]);
                        $l_spec = $c_spec->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                        <details class="term-acc glass-box">
                            <summary class="term-sum term-sum-special"><span>جزوه‌های خاص</span><span class="ic-arrow" style="transition:0.3s;"><?=$ic_down ?? '▼'?></span></summary>
                            <div>
                                <?php if(empty($l_spec)): ?>
                                    <div style="padding:15px; color:var(--x-gray); font-size:14px; text-align:center; border-top:1px solid var(--x-border);">محتوایی ثبت نشده.</div>
                                <?php else: foreach($l_spec as $c): ?>
                                    <div class="course-row">
                                        <a href="jozveGroup.php?id=<?=(int)$c['id']?>" class="course-name" style="color:#d97706;"><?=htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8')?></a>
                                        <?php if($can_manage): ?>
                                            <div class="action-grp">
                                                <button class="btn-icon" onclick="openEditCourse('<?=(int)$c['id']?>', '<?=htmlspecialchars(addslashes($c['name']), ENT_QUOTES, 'UTF-8')?>', '<?=(int)$c['term']?>')" title="ویرایش"><?=$ic_edit ?? '✎'?></button>
                                                <button class="btn-icon del" onclick="openDelCourse('<?=(int)$c['id']?>')" title="حذف"><?=$ic_delete ?? '✕'?></button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </details>
                    <?php endif; ?>

                    <?php if(empty($kanoon['hide_magazines']) || $can_manage): 
                        $c_mag = $pdo->prepare("SELECT * FROM jozve_groups WHERE kanoon_id=? AND term=10 ORDER BY id DESC");
                        $c_mag->execute([$kanoon_id]);
                        $l_mag = $c_mag->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                        <details class="term-acc glass-box">
                            <summary class="term-sum term-sum-mag"><span>مجلات</span><span class="ic-arrow" style="transition:0.3s;"><?=$ic_down ?? '▼'?></span></summary>
                            <div>
                                <?php if(empty($l_mag)): ?>
                                    <div style="padding:15px; color:var(--x-gray); font-size:14px; text-align:center; border-top:1px solid var(--x-border);">مجلاتی ثبت نشده.</div>
                                <?php else: foreach($l_mag as $c): ?>
                                    <div class="course-row">
                                        <a href="jozveGroup.php?id=<?=(int)$c['id']?>" class="course-name" style="color:#7e22ce;"><?=htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8')?></a>
                                        <?php if($can_manage): ?>
                                            <div class="action-grp">
                                                <button class="btn-icon" onclick="openEditCourse('<?=(int)$c['id']?>', '<?=htmlspecialchars(addslashes($c['name']), ENT_QUOTES, 'UTF-8')?>', '<?=(int)$c['term']?>')" title="ویرایش"><?=$ic_edit ?? '✎'?></button>
                                                <button class="btn-icon del" onclick="openDelCourse('<?=(int)$c['id']?>')" title="حذف"><?=$ic_delete ?? '✕'?></button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </details>
                    <?php endif; ?>

                    <?php if(empty($kanoon['hide_terms']) || $can_manage): 
                        for($i = 1; $i <= 8; $i++): 
                        $courses = $pdo->prepare("SELECT * FROM jozve_groups WHERE kanoon_id=? AND term=? ORDER BY id DESC");
                        $courses->execute([$kanoon_id, $i]);
                        $list = $courses->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                        <details class="term-acc glass-box">
                            <summary class="term-sum"><span>ترم <?= pNum($i) ?></span><span class="ic-arrow" style="transition:0.3s;"><?=$ic_down ?? '▼'?></span></summary>
                            <div>
                                <?php if(empty($list)): ?>
                                    <div style="padding:15px; color:var(--x-gray); font-size:14px; text-align:center; border-top:1px solid var(--x-border);">درسی ثبت نشده.</div>
                                <?php else: foreach($list as $c): ?>
                                    <div class="course-row">
                                        <a href="jozveGroup.php?id=<?=(int)$c['id']?>" class="course-name"><?=htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8')?></a>
                                        <?php if($can_manage): ?>
                                            <div class="action-grp">
                                                <button class="btn-icon" onclick="openEditCourse('<?=(int)$c['id']?>', '<?=htmlspecialchars(addslashes($c['name']), ENT_QUOTES, 'UTF-8')?>', '<?=(int)$c['term']?>')" title="ویرایش"><?=$ic_edit ?? '✎'?></button>
                                                <button class="btn-icon del" onclick="openDelCourse('<?=(int)$c['id']?>')" title="حذف"><?=$ic_delete ?? '✕'?></button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </details>
                    <?php endfor; endif; ?>

                </div>
            <?php endif; ?>

            <?php if($tab === 'projects'): ?>
                <?php if($can_manage): ?>
                    <div style="padding:16px; padding-bottom:0;">
                        <button class="btn-submit" style="width:auto; padding:8px 20px; font-size:14px; display:inline-flex; align-items:center; gap:5px;" onclick="oM('projectModal')"><?=$ic_plus ?? '+'?> افزودن پروژه</button>
                    </div>
                <?php endif; ?>
                <?php
                $projs = $pdo->prepare("SELECT * FROM projects WHERE kanoon_id=? ORDER BY id DESC");
                $projs->execute([$kanoon_id]);
                $p_list = $projs->fetchAll();
                if(empty($p_list)): ?>
                    <div style="text-align:center; padding:50px; color:var(--x-gray);">هنوز پروژه‌ای ثبت نشده است.</div>
                <?php else: ?>
                    <div class="grid-wrap">
                        <?php foreach($p_list as $p): 
                            $img_stmt = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = ? ORDER BY id ASC LIMIT 1");
                            $img_stmt->execute([$p['id']]);
                            $first_image = $img_stmt->fetchColumn();
                            
                            $p_img = getImg($first_image, $p['name'], true);
                        ?>
                            <div class="proj-card glass-box">
                               <div class="proj-clickable" onclick="location.href='project.php?id=<?=(int)$p['id']?>'">
                                    <img src="<?=$p_img?>" class="proj-img">
                                    <div style="flex:1;">
                                        <div class="item-title"><?=htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8')?></div>
                                        <div class="proj-desc"><?=htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8')?></div>
                                        <div class="proj-meta">
                                            <span><?=toJalali($p['created_at'])?></span>
                                            <?php if($p['project_link'] || $p['github_link']): ?><span style="color:var(--x-blue); display:flex; align-items:center; gap:2px;"><?=$ic_link ?? '🔗'?> لینک دارد</span><?php endif; ?>
                                        </div>
                                    </div>
                               </div>
                               <?php if($can_manage): ?>
                                    <div class="action-grp">
                                        <button class="btn-icon" onclick="openEditProject(<?=(int)$p['id']?>, '<?=htmlspecialchars(addslashes($p['name']), ENT_QUOTES, 'UTF-8')?>', '<?=htmlspecialchars(addslashes($p['description']), ENT_QUOTES, 'UTF-8')?>', '<?=htmlspecialchars(addslashes($p['project_link']), ENT_QUOTES, 'UTF-8')?>', '<?=htmlspecialchars(addslashes($p['github_link']), ENT_QUOTES, 'UTF-8')?>')" title="ویرایش"><?=$ic_edit ?? '✎'?></button>
                                        <button class="btn-icon del" onclick="openDelProject(<?=(int)$p['id']?>)" title="حذف"><?=$ic_delete ?? '✕'?></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php if($can_manage): ?>

<div id="editKanoonModal" class="mod">
    <div class="m-c">
        <div class="m-hdr"><h2>ویرایش اطلاعات کانون</h2><button onclick="tgM('editKanoonModal')">✕</button></div>
        <form action="actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>">
            <input type="hidden" name="action" value="edit_kanoon">
            <input type="hidden" name="id" value="<?=$kanoon_id?>">
            <input type="text" name="name" class="input-ui" value="<?=htmlspecialchars($kanoon['name'], ENT_QUOTES, 'UTF-8')?>" placeholder="نام کانون" required>
            <textarea name="description" class="input-ui" rows="3" placeholder="توضیحات"><?=htmlspecialchars($kanoon['description'], ENT_QUOTES, 'UTF-8')?></textarea>
            
            <label style="font-size:13px;color:var(--x-gray);display:block;margin-bottom:5px;">عکس کانون (حداکثر ۵۰۰ کیلوبایت):</label>
            <input type="file" name="image" class="input-ui" accept="image/png, image/jpeg, image/jpg, image/webp" onchange="checkFileSize(this)">

            <div style="margin: 15px 0; border: 1px solid var(--x-border); padding: 12px; border-radius: 12px; background:var(--x-hover);">
                <label style="display:block; margin-bottom:10px; font-weight:bold; font-size:14px;">تنظیمات نمایش تب‌ها (مخفی کردن از کاربران):</label>
                <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer; font-size:14px;">
                    <input type="checkbox" name="hide_members" value="1" <?=!empty($kanoon['hide_members'])?'checked':''?>> مخفی کردن تب «اعضا»
                </label>
                <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer; font-size:14px;">
                    <input type="checkbox" name="hide_projects" value="1" <?=!empty($kanoon['hide_projects'])?'checked':''?>> مخفی کردن تب «پروژه‌ها»
                </label>
                <hr style="border:0; border-top:1px solid var(--x-border); margin:10px 0;">
                <label style="display:block; margin-bottom:10px; font-weight:bold; font-size:14px;">تنظیمات نمایش (بخش جزوات):</label>
                <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer; font-size:14px;">
                    <input type="checkbox" name="hide_special" value="1" <?=!empty($kanoon['hide_special'])?'checked':''?>> مخفی کردن «جزوه‌های خاص»
                </label>
                <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer; font-size:14px;">
                    <input type="checkbox" name="hide_magazines" value="1" <?=!empty($kanoon['hide_magazines'])?'checked':''?>> مخفی کردن «مجلات»
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:14px;">
                    <input type="checkbox" name="hide_terms" value="1" <?=!empty($kanoon['hide_terms'])?'checked':''?>> مخفی کردن «۸ ترم تحصیلی»
                </label>
            </div>

            <button type="submit" class="btn-submit">ذخیره تغییرات</button>
        </form>
    </div>
</div>

<!-- مدیریت گروه چت -->
<div id="createGroupModal" class="mod"><div class="m-c"><div class="m-hdr"><h2>ساخت گروه چت رسمی</h2><button onclick="tgM('createGroupModal')">✕</button></div><form action="actions.php" method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="create_kanoon_group"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="text" name="group_name" class="input-ui" placeholder="نام گروه" value="گروه <?=htmlspecialchars($kanoon['name'], ENT_QUOTES, 'UTF-8')?>" required><textarea name="group_desc" class="input-ui" rows="2" placeholder="توضیحات گروه..."></textarea><label style="font-size:13px;color:var(--x-gray);display:block;margin-bottom:5px;">عکس گروه (حداکثر ۵۰۰ کیلوبایت):</label><input type="file" name="group_avatar" class="input-ui" accept="image/*" onchange="checkFileSize(this)"><button type="submit" class="btn-submit">ایجاد گروه</button></form></div></div>

<?php if(!empty($kanoon['conversation_id']) && $convData): ?>
<div id="editGroupModal" class="mod"><div class="m-c"><div class="m-hdr"><h2>ویرایش گروه چت</h2><button onclick="tgM('editGroupModal')">✕</button></div><form action="actions.php" method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="edit_kanoon_group"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="hidden" name="conv_id" value="<?=$kanoon['conversation_id']?>"><input type="text" name="group_name" class="input-ui" value="<?=htmlspecialchars($convData['group_name']??'', ENT_QUOTES, 'UTF-8')?>" required><textarea name="group_desc" class="input-ui" rows="2"><?=htmlspecialchars($convData['group_description']??'', ENT_QUOTES, 'UTF-8')?></textarea><label style="font-size:13px;color:var(--x-gray);display:block;margin-bottom:5px;">تغییر عکس گروه (حداکثر ۵۰۰ کیلوبایت):</label><input type="file" name="group_avatar" class="input-ui" accept="image/*" onchange="checkFileSize(this)"><button type="submit" class="btn-submit">ذخیره تغییرات</button></form></div></div>
<div id="deleteGroupModal" class="mod"><div class="m-c" style="text-align:center;"><h2 style="margin-bottom:10px;">حذف گروه چت؟</h2><p style="color:var(--x-gray); font-size:14px; margin-bottom:20px;">این عمل غیرقابل بازگشت است و تمام پیام‌های گروه پاک خواهد شد.</p><form action="actions.php" method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="delete_kanoon_group"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="hidden" name="conv_id" value="<?=$kanoon['conversation_id']?>"><button type="submit" class="btn-submit danger">بله، گروه حذف شود</button><button type="button" onclick="tgM('deleteGroupModal')" style="background:none;width:100%;margin-top:10px;">انصراف</button></form></div></div>
<?php endif; ?>

<div id="courseModal" class="mod"><div class="m-c"><div class="m-hdr"><h2>افزودن محتوا</h2><button onclick="tgM('courseModal')">✕</button></div><form action="actions.php" method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="add_course"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="text" name="name" class="input-ui" placeholder="نام محتوا / درس" required><select name="term" class="input-ui"><option value="9">جزوه‌های خاص</option><option value="10">مجلات</option><?php for($i=1;$i<=8;$i++) echo "<option value='".(int)$i."'>ترم " . pNum($i) . "</option>"; ?></select><button type="submit" class="btn-submit">ثبت</button></form></div></div>

<div id="editCourseModal" class="mod"><div class="m-c"><div class="m-hdr"><h2>ویرایش محتوا</h2><button onclick="tgM('editCourseModal')">✕</button></div><form action="actions.php" method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="edit_course"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="hidden" name="id" id="e_c_id"><input type="text" name="name" id="e_c_name" class="input-ui" required><select name="term" id="e_c_term" class="input-ui"><option value="9">جزوه‌های خاص</option><option value="10">مجلات</option><?php for($i=1;$i<=8;$i++) echo "<option value='".(int)$i."'>ترم " . pNum($i) . "</option>"; ?></select><button type="submit" class="btn-submit">ذخیره</button></form></div></div>

<div id="memberModal" class="mod"><div class="m-c" style="overflow:visible;"><div class="m-hdr"><h2>افزودن عضو</h2><button onclick="tgM('memberModal')">✕</button></div><form action="actions.php" method="POST" autocomplete="off"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="add_member"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><div style="position:relative;"><input type="text" id="userSearch" name="username" class="input-ui" placeholder="جستجوی آیدی یا نام کاربر..." required onkeyup="searchUsers(this.value)"><div id="searchBox" class="search-res"></div></div><input type="text" name="role_title" class="input-ui" placeholder="لقب (اختیاری)"><button type="submit" class="btn-submit">افزودن</button></form></div></div>
<div id="editRoleModal" class="mod"><div class="m-c"><div class="m-hdr"><h2>ویرایش لقب <span id="r_u_name" style="color:var(--x-blue); font-size:16px;"></span></h2><button onclick="tgM('editRoleModal')">✕</button></div><form action="actions.php" method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="edit_member_role"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="hidden" name="member_id" id="edit_role_id"><input type="text" name="role_title" id="edit_role_title" class="input-ui" placeholder="لقب..." required><button type="submit" class="btn-submit">ذخیره</button></form></div></div>
<div id="delCourseModal" class="mod"><div class="m-c" style="text-align:center;"><h2 style="margin-bottom:10px;">حذف؟</h2><p style="color:var(--x-gray); font-size:14px; margin-bottom:20px;">این عمل غیرقابل بازگشت است.</p><form action="actions.php" method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="delete_course"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="hidden" name="id" id="d_c_id"><button type="submit" class="btn-submit danger">بله، حذف کن</button><button type="button" onclick="tgM('delCourseModal')" style="background:none;width:100%;margin-top:10px;">انصراف</button></form></div></div>

<div id="projectModal" class="mod"><div class="m-c"><div class="m-hdr"><h2>ثبت پروژه جدید</h2><button onclick="tgM('projectModal')">✕</button></div><form action="actions.php" method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="add_project"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="text" name="name" class="input-ui" placeholder="نام پروژه" required><textarea name="description" class="input-ui" placeholder="توضیحات کوتاه..." rows="3"></textarea><input type="url" name="project_link" class="input-ui" placeholder="لینک پروژه (اختیاری)"><input type="url" name="github_link" class="input-ui" placeholder="لینک گیت‌هاب (اختیاری)"><label style="font-size:13px;color:var(--x-gray);display:block;margin-bottom:5px;">عکس پروژه (حداکثر ۵۰۰ کیلوبایت - چندتایی مجاز است):</label><input type="file" name="images[]" class="input-ui" accept="image/*" multiple onchange="checkFileSize(this)"><button type="submit" class="btn-submit">ثبت پروژه</button></form></div></div>
<div id="editProjectModal" class="mod"><div class="m-c"><div class="m-hdr"><h2>ویرایش پروژه</h2><button onclick="tgM('editProjectModal')">✕</button></div><form action="actions.php" method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="edit_project"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="hidden" name="id" id="e_p_id"><input type="text" name="name" id="e_p_name" class="input-ui" required><textarea name="description" id="e_p_desc" class="input-ui" rows="3"></textarea><input type="url" name="project_link" id="e_p_link" class="input-ui" placeholder="لینک پروژه"><input type="url" name="github_link" id="e_p_github" class="input-ui" placeholder="لینک گیت‌هاب"><label style="font-size:13px;color:var(--x-gray);display:block;margin-bottom:5px;">افزودن عکس‌های جدید (حداکثر ۵۰۰ کیلوبایت):</label><input type="file" name="images[]" class="input-ui" accept="image/*" multiple onchange="checkFileSize(this)"><button type="submit" class="btn-submit">ذخیره تغییرات</button></form></div></div>
<div id="delProjectModal" class="mod"><div class="m-c" style="text-align:center;"><h2 style="margin-bottom:10px;">حذف پروژه؟</h2><p style="color:var(--x-gray); font-size:14px; margin-bottom:20px;">این عمل غیرقابل بازگشت است.</p><form action="actions.php" method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>"><input type="hidden" name="action" value="delete_project"><input type="hidden" name="kanoon_id" value="<?=$kanoon_id?>"><input type="hidden" name="id" id="d_p_id"><button type="submit" class="btn-submit danger">بله، حذف کن</button><button type="button" onclick="tgM('delProjectModal')" style="background:none;width:100%;margin-top:10px;">انصراف</button></form></div></div>

<?php 
$all_users = $pdo->query("SELECT username, name FROM users ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
$safe_users = array_map(function($u) { return ['username' => htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8'),'name' => htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8')]; }, $all_users);
?>
<script>
const users = <?=json_encode($safe_users, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)?>;
function searchUsers(val){const box=document.getElementById('searchBox');if(!val){box.style.display='none';return;}val=val.toLowerCase();let res=users.filter(u=>u.username.toLowerCase().includes(val)||u.name.toLowerCase().includes(val)).slice(0,5);if(res.length>0){box.innerHTML=res.map(u=>`<div class="s-item" onclick="selectUser('${u.username}')"><img src="https://ui-avatars.com/api/?name=${u.name}&background=random"><div><b>${u.name}</b> <br><span style="font-size:11px;color:gray;">@${u.username}</span></div></div>`).join('');box.style.display='block';}else{box.innerHTML='<div style="padding:10px; color:gray; font-size:13px; text-align:center;">کاربری یافت نشد.</div>';box.style.display='block';}}
function selectUser(username){document.getElementById('userSearch').value=username;document.getElementById('searchBox').style.display='none';}
function openEditRole(id,role,name){document.getElementById('edit_role_id').value=id;document.getElementById('edit_role_title').value=role;document.getElementById('r_u_name').innerText='('+name+')';oM('editRoleModal');}
function openEditCourse(id,name,term){document.getElementById('e_c_id').value=id;document.getElementById('e_c_name').value=name;document.getElementById('e_c_term').value=term;oM('editCourseModal');}
function openDelCourse(id){document.getElementById('d_c_id').value=id;oM('delCourseModal');}
function openEditProject(id,name,desc,link,github){document.getElementById('e_p_id').value=id;document.getElementById('e_p_name').value=name;document.getElementById('e_p_desc').value=desc;document.getElementById('e_p_link').value=link;document.getElementById('e_p_github').value=github;oM('editProjectModal');}
function openDelProject(id){document.getElementById('d_p_id').value=id;oM('delProjectModal');}
function checkFileSize(input) {
    if (input.files && input.files.length > 0) {
        for(let i=0; i<input.files.length; i++) {
            if (input.files[i].size > 500 * 1024) {
                alert('حجم عکس انتخاب شده نباید بیشتر از 500 کیلوبایت باشد.');
                input.value = '';
                return false;
            }
        }
    }
}
const tgM=i=>{document.getElementById(i).style.display='none';if(document.getElementById('searchBox'))document.getElementById('searchBox').style.display='none';};
const oM=i=>document.getElementById(i).style.display='flex';
window.onclick=e=>{if(e.target.classList.contains('mod'))tgM(e.target.id);if(document.getElementById('userSearch') && e.target.id!=='userSearch')document.getElementById('searchBox').style.display='none';};
</script>

<?php endif; ?>
<?php include 'footer.php'; ?>

</body>
</html>
<?php ob_end_flush(); ?>
