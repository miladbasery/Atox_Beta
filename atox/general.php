<?php
ob_start();
session_start();
require 'db.php';

$uid = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'user';
$is_admin = ($user_role === 'admin' || (isset($_SESSION['username']) && $_SESSION['username'] === 'milad'));
$is_logged = ($uid > 0);

function pNum($str) {
    return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str);
}
function formatLikes($num) {
    if ($num >= 1000000) return pNum(round($num / 1000000, 1)) . 'M';
    if ($num >= 1000) return pNum(round($num / 1000, 1)) . 'K';
    return pNum($num);
}
function toJalali($date) {
    if(empty($date)) return '';
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    if($diff < 60) return 'همین الان';
    if($diff < 3600) return pNum(floor($diff / 60)) . ' دقیقه پیش';
    if($diff < 86400) return pNum(floor($diff / 3600)) . ' ساعت پیش';
    if($diff < 604800) return pNum(floor($diff / 86400)) . ' روز پیش';
    return pNum(date('Y/m/d', $timestamp));
}

$tab = $_GET['tab'] ?? 'kanoons';
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_pages = 1;

$kanoons = [];
$recent_jozves = [];
$categories = ['مهندسی', 'علوم پزشکی', 'علوم پایه', 'علوم انسانی', 'هنر', 'زبان‌های خارجی', 'فناوری اطلاعات', 'کارآفرینی', 'ورزشی', 'متفرقه'];
$active_category = $_GET['category'] ?? 'all';


if ($tab === 'kanoons') {
    $sql_count = "SELECT COUNT(k.id) FROM kanoons k";
    $sql = "SELECT k.*, 
            (SELECT COUNT(*) FROM kanoon_members WHERE kanoon_id = k.id) as m_count 
            FROM kanoons k";
    $params = [];
    if ($active_category !== 'all') {
        $sql_count .= " WHERE k.category = ?";
        $sql .= " WHERE k.category = ?";
        $params[] = $active_category;
    }
    
    $c_stmt = $pdo->prepare($sql_count);
    $c_stmt->execute($params);
    $total_kanoons = $c_stmt->fetchColumn();
    $total_pages = ceil($total_kanoons / $limit);

    $sql .= " ORDER BY k.id DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $kanoons = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($tab === 'jozves') {
    $c_stmt = $pdo->query("SELECT COUNT(id) FROM jozves WHERE created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)");
    $total_pages = ceil($c_stmt->fetchColumn() / $limit);

    $recent_jozves = $pdo->query("
        SELECT j.*, g.name as course_name, k.name as kanoon_name, 
               u.name as publisher_name, u.username as publisher_username, u.avatar as publisher_avatar, u.is_verified 
        FROM jozves j 
        JOIN jozve_groups g ON j.group_id = g.id 
        JOIN kanoons k ON g.kanoon_id = k.id 
        LEFT JOIN users u ON j.user_id = u.id
        WHERE j.created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
        ORDER BY j.created_at DESC 
        LIMIT $limit OFFSET $offset
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$ic_plus = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M11 11V4h2v7h7v2h-7v7h-2v-7H4v-2h7z"/></svg>';
$ic_edit = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M19.4 7.34L16.66 4.6c-.39-.39-1.02-.39-1.41 0L3 16.84V19.6c0 .55.45 1 1 1h2.76l12.24-12.24c.39-.39.39-1.02 0-1.42zM5 18.6V17.2l9.83-9.83 1.41 1.41L6.41 18.6H5z"/></svg>';
$ic_delete = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:#f91880"><path d="M16 9v10H8V9h8m-1.5-6h-5l-1 1H5v2h14V4h-3.5l-1-1zM18 7H6v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7z"/></svg>';
$ic_view = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
$ic_like = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';
$ic_dislike = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/></svg>';
$ic_author = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
$ic_uni = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99v-2.08l-5 2.73-5-2.73v2.08l5 2.73 5-2.73z"/></svg>';
$ic_pen = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
$ic_verify = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>کانون‌ها و جزوات - آتوکس</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.85); --x-hover:rgba(15,20,25,0.05); --x-hover-b:rgba(29,155,240,0.1); --x-modal:rgba(0,0,0,0.4); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.85); --x-hover:rgba(255,255,255,0.05); --x-modal:rgba(255,255,255,0.1); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,sans-serif}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;overflow-y:scroll;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}
.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:120px;min-height:100vh;}
.hdr{position:sticky;top:0;background:var(--x-bg-trans);backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); z-index:10;border-bottom:1px solid var(--x-border)}
.page-title{padding:12px 16px;font-size:20px;font-weight:900;display:flex;justify-content:space-between;align-items:center;}
.btn-add{background:var(--x-black);color:var(--x-bg);padding:6px 16px;border-radius:99px;font-size:14px;font-weight:700;display:flex;align-items:center;gap:4px;transition:0.2s;}
.btn-add:hover{opacity:0.8;}
.k-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; padding: 20px; }
.k-card { background: rgba(15,20,25,0.02); border-radius: 20px; padding: 20px; text-align: center; cursor: pointer; transition: 0.3s ease; position: relative; overflow: hidden; display: flex; flex-direction: column; }
.dark .k-card { background: rgba(255,255,255,0.02); }
.k-card:hover { background: var(--x-hover); transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
.dark .k-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
.k-img-wrap { width: 70px; height: 70px; margin: 0 auto 12px; border-radius: 20px; overflow: hidden; background: var(--x-hover); border: 1px solid var(--x-border); flex-shrink: 0; }
.k-img { width: 100%; height: 100%; object-fit: cover; }
.k-title-row { display: flex; align-items: center; justify-content: center; gap: 4px; margin-bottom: 5px; }
.k-title { font-size: 16px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1; }
.k-verified-icon svg { width: 18px; height: 18px; display: block;}
.k-meta { font-size: 12px; color: var(--x-gray); font-weight: 600; margin-bottom: 8px; display: flex; justify-content: center; gap: 8px; }
.k-meta-badge { background: rgba(15,20,25,0.03); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-radius: 8px; padding: 4px 10px; font-size: 11px; font-weight: 700; color: var(--x-gray); border: 1px solid var(--x-border); }
.dark .k-meta-badge { background: rgba(255,255,255,0.03); }
.k-desc { font-size: 12px; color: var(--x-gray); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; margin-top: auto; }
.kanoon-actions-menu { position: absolute; top: 10px; right: 10px; z-index: 1; }
.kanoon-actions-btn { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--x-bg); border: 1px solid var(--x-border); color: var(--x-gray); transition: 0.2s; }
.kanoon-actions-btn:hover { background: var(--x-hover); color: var(--x-black); }
.kanoon-actions-dropdown { display: none; position: absolute; left: 0; top: 100%; background: var(--x-bg); border: 1px solid var(--x-border); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; min-width: 120px; }
.dark .kanoon-actions-dropdown { box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
.kanoon-actions-dropdown button { width: 100%; padding: 10px 15px; text-align: right; border-bottom: 1px solid var(--x-border); font-size: 14px; display: flex; align-items: center; gap: 8px; }
.kanoon-actions-dropdown button:last-child { border-bottom: none; }
.kanoon-actions-dropdown button:hover { background: var(--x-hover); }
.kanoon-actions-dropdown button.del { color: #f91880; }
.kanoon-actions-dropdown button.del:hover { background: rgba(249,24,128,0.1); }
.j-grid { padding: 20px; }
.j-card { padding: 16px; border: 1px solid var(--x-border); border-radius: 16px; background: rgba(15, 20, 25, 0.02); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); cursor: pointer; transition: 0.2s; display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.dark .j-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
.j-card:hover { background: var(--x-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
.dark .j-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
.j-header { display: flex; justify-content: space-between; align-items: flex-start; }
.j-title-area { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.j-course { font-size: 16px; font-weight: 800; color: var(--x-black); }
.j-kanoon-tag { font-size: 11px; background: rgba(15,20,25,0.05); color: var(--x-gray); padding: 2px 8px; border-radius: 6px; font-weight: bold; border: 1px solid var(--x-border); margin-right:4px;}
.dark .j-kanoon-tag { background: rgba(255,255,255,0.05); }
.j-lang { font-size: 11px; background: rgba(29,155,240,0.1); color: #1d9bf0; padding: 2px 8px; border-radius: 6px; font-weight: 700; border: 1px solid rgba(29,155,240,0.1); }
.j-props { display: flex; flex-wrap: wrap; gap: 6px; }
.j-prop { font-size: 12px; color: var(--x-gray); display: flex; align-items: center; gap: 4px; background: rgba(15,20,25,0.03); padding: 4px 10px; border-radius: 8px; }
.dark .j-prop { background: rgba(255,255,255,0.03); }
.j-desc { font-size: 14px; color: var(--x-black); line-height: 1.6; white-space: pre-wrap; margin: 4px 0; }
.j-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; border-top: 1px dashed var(--x-border); padding-top: 12px; }
.j-profile { display: flex; align-items: center; gap: 8px; }
.j-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; background: var(--x-border); }
.j-user-info { display: flex; flex-direction: column; }
.j-name-row { display: flex; align-items: center; gap: 4px; font-size: 13px; font-weight: 700; color: var(--x-black); }
.j-username { font-size: 12px; color: var(--x-gray); }
.j-stats { display: flex; align-items: center; gap: 14px; color: var(--x-gray); font-size: 12px; font-weight: 600;}
.j-stat { display: flex; align-items: center; gap: 4px; }
.j-time { color: var(--x-gray); font-size: 11px; font-weight: normal; margin-left: 4px;}
.pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin: 20px 12px 50px; flex-wrap: wrap; direction: ltr; }
.page-link { padding: 8px 16px; border-radius: 14px; background: rgba(255,255,255,0.4); border: 1px solid rgba(255,255,255,0.6); color: var(--x-black); font-weight: bold; font-size: 15px; transition: 0.2s; backdrop-filter: blur(10px); }
.dark .page-link { background: rgba(30,30,30,0.4); border-color: rgba(255,255,255,0.1); }
.page-link:hover { background: var(--x-hover); transform: translateY(-2px); }
.page-link.active { background: var(--x-blue); color: #fff; border-color: var(--x-blue); }
.mod{display:none;position:fixed;inset:0;background:var(--x-modal);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px);}
.m-c{position:relative;background:var(--x-bg);border-radius:24px;width:90%;max-width:450px;padding:24px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:p .3s cubic-bezier(0.175, 0.885, 0.32, 1.275);}
.m-hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--x-border);padding-bottom:15px;margin-bottom:15px;}
.input-ui{width:100%;padding:12px;border:1px solid var(--x-border);border-radius:12px;font-size:15px;margin-bottom:12px;background:transparent;color:var(--x-black);outline:none;box-sizing:border-box;}
.input-ui:focus{border-color:var(--x-blue);}
.file-ui{padding:8px 12px; border:1px dashed var(--x-border); border-radius:12px; margin-bottom:12px; width:100%; font-size:14px; color:var(--x-gray);}
.btn-submit{background:var(--x-blue);color:#fff;border:none;padding:12px;border-radius:99px;font-weight:700;font-size:15px;cursor:pointer;width:100%;transition:0.2s;}
.btn-submit.danger{background:#f91880;}
.category-filter-bar { display: flex; overflow-x: auto; padding: 10px 12px 12px; gap: 8px; white-space: nowrap; -ms-overflow-style: none; scrollbar-width: none; border-bottom: 1px solid var(--x-border);}
.category-filter-bar::-webkit-scrollbar { display: none; }
.cat-btn { padding: 6px 16px; border-radius: 99px; font-size: 14px; font-weight: bold; border: none; transition: 0.2s; background: rgba(15,20,25,0.03); }
.dark .cat-btn { background: rgba(255,255,255,0.03); }
.cat-btn:hover { background: var(--x-hover); }
.cat-btn.active { background: var(--x-black); color: var(--x-bg); border-color: var(--x-black); }
.form-checkbox-group { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor:pointer;}
@keyframes p{0%{transform:translateY(30px) scale(0.9);opacity:0}100%{transform:translateY(0) scale(1);opacity:1}}
@media(max-width:600px){ .main{border:none; padding-bottom:100px;} .k-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; padding: 12px; } .k-card { padding: 15px 10px; border-radius: 16px; } .k-img-wrap { width: 55px; height: 55px; margin-bottom: 8px; border-radius: 16px; } .k-title { font-size: 14px; } .k-desc { font-size: 11px; } .k-meta { font-size: 11px; } .j-grid { padding: 12px; }}

.three-dots-icon {
    width: 20px;
    height: 20px;
    transform: rotate(90deg);
}
</style>
</head>
<body>
<div class="app">
    <main class="main">
		<?php include 'header.php'; ?>

        <div class="hdr">
            <div class="page-title">
                <span>کانون‌ها و جزوات</span>
                <?php if($is_logged): ?>
                    <button class="btn-add" onclick="oM('addKanoonModal')"><?=$ic_plus?> کانون جدید</button>
                <?php endif; ?>
            </div>

            <div style="display:flex; width:100%;">
                <a href="?tab=kanoons" style="flex:1; text-align:center; padding:14px 0; font-size:14px; font-weight:bold; position:relative; transition:0.2s; color:<?=$tab==='kanoons'?'var(--x-black)':'var(--x-gray)'?>;">
                    کانون‌ها
                    <?php if($tab === 'kanoons'): ?><div style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:40px; height:4px; background:var(--x-blue); border-radius:4px;"></div><?php endif; ?>
                </a>
                <a href="?tab=jozves" style="flex:1; text-align:center; padding:14px 0; font-size:14px; font-weight:bold; position:relative; transition:0.2s; color:<?=$tab==='jozves'?'var(--x-black)':'var(--x-gray)'?>;">
                    جزوات تازه
                    <?php if($tab === 'jozves'): ?><div style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:60px; height:4px; background:var(--x-blue); border-radius:4px;"></div><?php endif; ?>
                </a>
            </div>
             <?php if ($tab === 'kanoons'): ?>
            <div class="category-filter-bar">
                <a href="?tab=kanoons&category=all" class="cat-btn <?= $active_category === 'all' ? 'active' : '' ?>">همه</a>
                <?php foreach($categories as $cat): ?>
                    <a href="?tab=kanoons&category=<?=urlencode($cat)?>" class="cat-btn <?= $active_category === $cat ? 'active' : '' ?>"><?=htmlspecialchars($cat)?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($tab === 'kanoons'): ?>
            <div class="k-grid">
                <?php foreach($kanoons as $k): 
                    $img = !empty($k['image']) ? "uploads/" . htmlspecialchars($k['image']) : "https://ui-avatars.com/api/?name=".urlencode($k['name'])."&background=random";
                    $can_manage = ($is_admin || ($is_logged && $k['creator_id'] == $uid));
                ?>
                    <div class="k-card" onclick="location.href='university.php?id=<?=$k['id']?>'">
                        <?php if($can_manage): ?>
                            <div class="kanoon-actions-menu" onclick="event.stopPropagation()">
                                <button class="kanoon-actions-btn" onclick="toggleKanoonDropdown(this)">
                                    <svg class="three-dots-icon" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><g><path d="M3 12c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm9 0c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm7 0c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2z"></path></g></svg>
                                </button>
                                <div class="kanoon-actions-dropdown">
                                    <button onclick="openEditKanoon(this)" 
                                        data-id="<?=$k['id']?>" 
                                        data-name="<?=htmlspecialchars($k['name'])?>" 
                                        data-desc="<?=htmlspecialchars($k['description'])?>"
                                        data-category="<?=htmlspecialchars($k['category'])?>"
                                        data-hide-magazines="<?=$k['hide_magazines']?>"
                                        data-hide-projects="<?=$k['hide_projects']?>"
                                        data-hide-members="<?=$k['hide_members']?>"
                                        title="ویرایش"><?=$ic_edit?> ویرایش</button>
                                    <button class="del" onclick="openDelKanoon('<?=$k['id']?>')" title="حذف"><?=$ic_delete?> حذف</button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="k-img-wrap">
                            <img src="<?=$img?>" class="k-img" alt="<?=htmlspecialchars($k['name'])?>">
                        </div>
                        <div class="k-title-row">
                            <h3 class="k-title"><?=htmlspecialchars($k['name'])?></h3>
                            <?php if(!empty($k['is_verified'])): ?>
                                <span class="k-verified-icon"><?=$ic_verify?></span>
                            <?php endif; ?>
                        </div>
                        <div class="k-meta">
                            <span class="k-meta-badge"><?=htmlspecialchars($k['category'])?></span>
                            <span class="k-meta-badge"><?=pNum($k['m_count'])?> عضو</span>
                        </div>
                        <p class="k-desc"><?=htmlspecialchars($k['description'])?></p>
                    </div>
                <?php endforeach; ?>
                
                <?php if(empty($kanoons)): ?>
                    <div style="grid-column:1/-1;text-align:center;padding:80px 20px;color:var(--x-gray);font-weight:bold;">در این دسته‌بندی کانونی ثبت نشده است.</div>
                <?php endif; ?>
            </div>

            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php 
                $start_p = max(1, $page - 2);
                $end_p = min($total_pages, $page + 2);
                if($page > 1): ?>
                    <a href="?tab=<?=$tab?>&category=<?=urlencode($active_category)?>&p=<?=$page-1?>" class="page-link">قبلی</a>
                <?php endif; ?>
                <?php for($i = $start_p; $i <= $end_p; $i++): ?>
                    <a href="?tab=<?=$tab?>&category=<?=urlencode($active_category)?>&p=<?=$i?>" class="page-link <?= $page == $i ? 'active' : '' ?>"><?= pNum($i) ?></a>
                <?php endfor; ?>
                <?php if($page < $total_pages): ?>
                    <a href="?tab=<?=$tab?>&category=<?=urlencode($active_category)?>&p=<?=$page+1?>" class="page-link">بعدی</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'jozves'): ?>
            <div class="j-grid">
                <?php foreach($recent_jozves as $j): 
                    $avatar = !empty($j['publisher_avatar']) ? $j['publisher_avatar'] : 'assets/default_avatar.png'; 
                ?>
                    <a href="magazine.php?id=<?=$j['id']?>" style="text-decoration: none; display: block; color: inherit;">
                        <div class="j-card">
                            <div class="j-header">
                                <div class="j-title-area">
                                    <span class="j-course"><?=htmlspecialchars($j['course_name'])?></span>
                                    <span class="j-kanoon-tag"><?=htmlspecialchars($j['kanoon_name'])?></span>
                                    <?php if(!empty($j['language'])): ?>
                                        <span class="j-lang"><?=htmlspecialchars($j['language'])?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if(!empty($j['author_name']) || !empty($j['university_name']) || !empty($j['is_handwritten'])): ?>
                            <div class="j-props">
                                <?php if(!empty($j['author_name'])): ?>
                                    <span class="j-prop"><?=$ic_author?> <?=htmlspecialchars($j['author_name'])?></span>
                                <?php endif; ?>
                                <?php if(!empty($j['university_name'])): ?>
                                    <span class="j-prop"><?=$ic_uni?> <?=htmlspecialchars($j['university_name'])?></span>
                                <?php endif; ?>
                                <?php if(!empty($j['is_handwritten'])): ?>
                                    <span class="j-prop"><?=$ic_pen?> دست‌نویس</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($j['description'])): ?>
                                <div class="j-desc"><?=htmlspecialchars($j['description'])?></div>
                            <?php endif; ?>
                            <div class="j-footer">
                                <div class="j-profile">
                                    <img src="<?=htmlspecialchars($avatar)?>" class="j-avatar" alt="avatar">
                                    <div class="j-user-info">
                                        <div class="j-name-row">
                                            <?=htmlspecialchars($j['publisher_name'] ?: 'کاربر آتوکس')?>
                                            <?php if(!empty($j['is_verified'])) echo $ic_verify; ?>
                                        </div>
                                        <?php if(!empty($j['publisher_username'])): ?>
                                            <div class="j-username">@<?=htmlspecialchars($j['publisher_username'])?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="j-stats">
                                    <div class="j-time"><?=toJalali($j['created_at'])?></div>
                                    <div class="j-stat"><?=$ic_view?> <span><?=formatLikes($j['views'] ?? 0)?></span></div>
                                    <div class="j-stat"><?=$ic_like?> <span><?=formatLikes($j['likes'] ?? 0)?></span></div>
                                    <div class="j-stat"><?=$ic_dislike?> <span><?=formatLikes($j['dislikes'] ?? 0)?></span></div>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
                
                <?php if(empty($recent_jozves)): ?>
                    <div style="text-align:center;padding:80px 20px;color:var(--x-gray);font-weight:bold;">در ۷۲ ساعت گذشته جزوه‌ای اضافه نشده است.</div>
                <?php endif; ?>
            </div>

            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php 
                $start_p = max(1, $page - 2);
                $end_p = min($total_pages, $page + 2);
                if($page > 1): ?>
                    <a href="?tab=<?=$tab?>&p=<?=$page-1?>" class="page-link">قبلی</a>
                <?php endif; ?>
                <?php for($i = $start_p; $i <= $end_p; $i++): ?>
                    <a href="?tab=<?=$tab?>&p=<?=$i?>" class="page-link <?= $page == $i ? 'active' : '' ?>"><?= pNum($i) ?></a>
                <?php endfor; ?>
                <?php if($page < $total_pages): ?>
                    <a href="?tab=<?=$tab?>&p=<?=$page+1?>" class="page-link">بعدی</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php if($is_logged): ?>
<div id="addKanoonModal" class="mod">
    <div class="m-c">
        <div class="m-hdr">
            <h2>ثبت کانون جدید</h2>
            <button type="button" onclick="tgM('addKanoonModal')" style="font-size:24px;color:var(--x-black);background:none;border:none;">✕</button>
        </div>
        <form action="actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_kanoon">
            <input type="text" name="name" class="input-ui" placeholder="نام کانون" required>
            <select name="category" class="input-ui" required>
                <option value="">انتخاب دسته‌بندی...</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
                <?php endforeach; ?>
            </select>
            <textarea name="description" class="input-ui" placeholder="توضیحات کانون..." style="min-height:100px; resize:vertical;"></textarea>
            <label style="font-size:13px; color:var(--x-gray); margin-bottom:5px; display:block;">لوگوی کانون (اختیاری):</label>
            <input type="file" name="image" class="file-ui" accept=".jpg,.jpeg,.png,.webp">
            <label style="font-size:13px; color:var(--x-gray); margin:10px 0 5px; display:block;">تنظیمات نمایش بخش‌ها:</label>
            <label class="form-checkbox-group"><input type="checkbox" name="hide_magazines"> مخفی کردن بخش جزوات</label>
            <label class="form-checkbox-group"><input type="checkbox" name="hide_projects"> مخفی کردن بخش پروژه‌ها</label>
            <label class="form-checkbox-group"><input type="checkbox" name="hide_members"> مخفی کردن بخش اعضا</label>
            <button type="submit" class="btn-submit" style="margin-top:15px;">ایجاد کانون</button>
        </form>
    </div>
</div>

<div id="editKanoonModal" class="mod">
    <div class="m-c">
        <div class="m-hdr">
            <h2>ویرایش کانون</h2>
            <button type="button" onclick="tgM('editKanoonModal')" style="font-size:24px;color:var(--x-black);background:none;border:none;">✕</button>
        </div>
        <form action="actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_kanoon">
            <input type="hidden" name="id" id="edit_kanoon_id">
            <input type="text" name="name" id="edit_kanoon_name" class="input-ui" placeholder="نام کانون" required>
            <select name="category" id="edit_kanoon_category" class="input-ui" required>
                <?php foreach($categories as $cat): ?>
                    <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
                <?php endforeach; ?>
            </select>
            <textarea name="description" id="edit_kanoon_desc" class="input-ui" placeholder="توضیحات..." style="min-height:100px; resize:vertical;"></textarea>
            <label style="font-size:13px; color:var(--x-gray); margin-bottom:5px; display:block;">آپلود لوگوی جدید (جایگزین قبلی می‌شود):</label>
            <input type="file" name="image" class="file-ui" accept=".jpg,.jpeg,.png,.webp">
            <label style="font-size:13px; color:var(--x-gray); margin:10px 0 5px; display:block;">تنظیمات نمایش بخش‌ها:</label>
            <label class="form-checkbox-group"><input type="checkbox" id="edit_hide_magazines" name="hide_magazines"> مخفی کردن بخش جزوات</label>
            <label class="form-checkbox-group"><input type="checkbox" id="edit_hide_projects" name="hide_projects"> مخفی کردن بخش پروژه‌ها</label>
            <label class="form-checkbox-group"><input type="checkbox" id="edit_hide_members" name="hide_members"> مخفی کردن بخش اعضا</label>
            <button type="submit" class="btn-submit" style="margin-top:15px;">ذخیره تغییرات</button>
        </form>
    </div>
</div>

<div id="delKanoonModal" class="mod">
    <div class="m-c" style="text-align:center;">
        <button type="button" onclick="tgM('delKanoonModal')" style="position:absolute; top:15px; right:15px; font-size:24px;color:var(--x-black);background:none;border:none;">✕</button>
        <div style="margin-bottom:20px; color:#f91880; transform:scale(1.5);"><?=$ic_delete?></div>
        <h2 style="margin-bottom:10px;">حذف کانون؟</h2>
        <p style="color:var(--x-gray); font-size:14px; margin-bottom:20px; line-height:1.6;">آیا از حذف این کانون و گروه چت مرتبط با آن مطمئن هستید؟ این عمل غیرقابل بازگشت است.</p>
        <form action="actions.php" method="POST">
            <input type="hidden" name="action" value="delete_kanoon">
            <input type="hidden" name="id" id="del_kanoon_id">
            <button type="submit" class="btn-submit danger">بله، حذف شود</button>
        </form>
    </div>
</div>

<script>
function openEditKanoon(btn) {
    document.getElementById('edit_kanoon_id').value = btn.dataset.id;
    document.getElementById('edit_kanoon_name').value = btn.dataset.name;
    document.getElementById('edit_kanoon_desc').value = btn.dataset.desc;
    document.getElementById('edit_kanoon_category').value = btn.dataset.category;
    document.getElementById('edit_hide_magazines').checked = btn.dataset.hideMagazines == '1';
    document.getElementById('edit_hide_projects').checked = btn.dataset.hideProjects == '1';
    document.getElementById('edit_hide_members').checked = btn.dataset.hideMembers == '1';
    oM('editKanoonModal');
}
function openDelKanoon(id) {
    document.getElementById('del_kanoon_id').value = id;
    oM('delKanoonModal');
}

function toggleKanoonDropdown(button) {
    const dropdown = button.nextElementSibling;
    document.querySelectorAll('.kanoon-actions-dropdown').forEach(dd => {
        if (dd !== dropdown) dd.style.display = 'none';
    });
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

window.addEventListener('click', function(event) {
    if (!event.target.closest('.kanoon-actions-menu')) {
        document.querySelectorAll('.kanoon-actions-dropdown').forEach(dd => {
            dd.style.display = 'none';
        });
    }
});
</script>
<?php endif; ?>

<script>
const tgM = i => document.getElementById(i).style.display = 'none';
const oM = i => document.getElementById(i).style.display = 'flex';
window.onclick = e => { if(e.target.classList.contains('mod')) e.target.style.display = 'none'; };
</script>
<?php include 'footer.php'; ?>

</body>
</html>
<?php ob_end_flush(); ?>
