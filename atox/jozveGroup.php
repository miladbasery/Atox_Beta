<?php
ob_start();
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$group_id = (int)($_GET['id'] ?? 0);
$uid = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// بررسی دسترسی ادمین کل
$is_admin = ($user_role === 'admin' || (isset($_SESSION['username']) && $_SESSION['username'] === 'milad'));

// =========================================================================
// پردازش فرم‌های مربوط به همین صفحه (آپلود، ویرایش و حذف جزوه)
// همه کاربران می‌توانند جزوه اضافه کنند
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // افزودن جزوه (توسط کاربر معمولی یا ادمین)
    if ($action === 'add_jozve') {
        $grp_id = (int)$_POST['group_id'];
        $user_id_publisher = $uid; // فقط به نام کاربری که لاگین است ثبت میشود
        $description = trim($_POST['description'] ?? '');
        $file_link = trim($_POST['file_link'] ?? '');
        
        $language = trim($_POST['language'] ?? '');
        $university_name = trim($_POST['university_name'] ?? '');
        $is_handwritten = isset($_POST['is_handwritten']) ? 1 : 0;
        $author_name = trim($_POST['author_name'] ?? '');
        
        if (!empty($file_link)) {
            $stmt = $pdo->prepare("INSERT INTO jozves (group_id, user_id, description, file_link, language, university_name, is_handwritten, author_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$grp_id, $user_id_publisher, $description, $file_link, $language, $university_name, $is_handwritten, $author_name]);
        }
        header("Location: jozveGroup.php?id=" . $grp_id);
        exit;
    }
    
    // ویرایش جزوه (ادمین همه را میتواند، کاربر فقط جزوه خودش را)
    if ($action === 'edit_jozve') {
        $id = (int)$_POST['id'];
        $grp_id = (int)$_POST['group_id'];
        
        // بررسی مالکیت
        $check = $pdo->prepare("SELECT user_id FROM jozves WHERE id = ?");
        $check->execute([$id]);
        $owner_id = $check->fetchColumn();
        
        if ($is_admin || $owner_id == $uid) {
            $description = trim($_POST['description'] ?? '');
            $file_link = trim($_POST['file_link'] ?? '');
            $language = trim($_POST['language'] ?? '');
            $university_name = trim($_POST['university_name'] ?? '');
            $is_handwritten = isset($_POST['is_handwritten']) ? 1 : 0;
            $author_name = trim($_POST['author_name'] ?? '');
            
            $stmt = $pdo->prepare("UPDATE jozves SET description = ?, file_link = ?, language = ?, university_name = ?, is_handwritten = ?, author_name = ? WHERE id = ?");
            $stmt->execute([$description, $file_link, $language, $university_name, $is_handwritten, $author_name, $id]);
        }
        header("Location: jozveGroup.php?id=" . $grp_id);
        exit;
    }
    
    // حذف جزوه (ادمین همه را میتواند، کاربر فقط جزوه خودش را)
    if ($action === 'delete_jozve') {
        $id = (int)$_POST['id'];
        $grp_id = (int)$_POST['group_id'];
        
        // بررسی مالکیت
        $check = $pdo->prepare("SELECT user_id FROM jozves WHERE id = ?");
        $check->execute([$id]);
        $owner_id = $check->fetchColumn();
        
        if ($is_admin || $owner_id == $uid) {
            // حذف ریکش‌های مربوطه
            $pdo->prepare("DELETE FROM jozve_likes WHERE jozve_id = ?")->execute([$id]);
            // حذف خود جزوه
            $stmt = $pdo->prepare("DELETE FROM jozves WHERE id = ?");
            $stmt->execute([$id]);
        }
        header("Location: jozveGroup.php?id=" . $grp_id);
        exit;
    }
}
// =========================================================================

// دریافت اطلاعات گروه جزوه (درس)
$group = $pdo->prepare("SELECT * FROM jozve_groups WHERE id = ?");
$group->execute([$group_id]);
$g_data = $group->fetch(PDO::FETCH_ASSOC);

if(!$g_data) {
    header("Location: index.php");
    exit;
}

$kanoon_name = 'بدون کانون';
if(!empty($g_data['kanoon_id'])) {
    try {
        $k_stmt = $pdo->prepare("SELECT name FROM kanoons WHERE id = ?");
        $k_stmt->execute([$g_data['kanoon_id']]);
        $k_res = $k_stmt->fetch(PDO::FETCH_ASSOC);
        if($k_res) $kanoon_name = $k_res['name'];
    } catch(Exception $e) {}
}

// دریافت لیست جزوات + اطلاعات کاربر + تعداد واقعی لایک و دیسلایک + وضعیت کاربر روی آن جزوه
// فیلد u.is_verified اضافه شد تا تیک آبی نمایش داده شود
$jozves = $pdo->prepare("
    SELECT j.*, u.name as publisher_name, u.username as publisher_username, u.avatar as publisher_avatar, u.is_verified,
           (SELECT COUNT(*) FROM jozve_likes jl WHERE jl.jozve_id = j.id AND jl.type = 'like') as likes_count,
           (SELECT COUNT(*) FROM jozve_likes jl WHERE jl.jozve_id = j.id AND jl.type = 'dislike') as dislikes_count,
           (SELECT type FROM jozve_likes jl WHERE jl.jozve_id = j.id AND jl.user_id = ?) as user_reaction
    FROM jozves j 
    LEFT JOIN users u ON j.user_id = u.id 
    WHERE j.group_id = ? 
    ORDER BY j.id DESC
");
$jozves->execute([$uid, $group_id]);
$jozve_list = $jozves->fetchAll(PDO::FETCH_ASSOC);

// توابع کمکی
function pNum($str) { return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str); }
function formatLikes($num) {
    if ($num >= 1000000) return pNum(round($num / 1000000, 1)) . 'M';
    if ($num >= 1000) return pNum(round($num / 1000, 1)) . 'K';
    return pNum($num);
}
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


// آیکون‌ها
$ic_back = '<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"></path></svg>';
$ic_plus = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M11 11V4h2v7h7v2h-7v7h-2v-7H4v-2h7z"/></svg>';
$ic_edit = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M19.4 7.34L16.66 4.6c-.39-.39-1.02-.39-1.41 0L3 16.84V19.6c0 .55.45 1 1 1h2.76l12.24-12.24c.39-.39.39-1.02 0-1.42zM5 18.6V17.2l9.83-9.83 1.41 1.41L6.41 18.6H5z"/></svg>';
$ic_delete = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M16 9v10H8V9h8m-1.5-6h-5l-1 1H5v2h14V4h-3.5l-1-1zM18 7H6v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7z"/></svg>';
$ic_view = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
$ic_like = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';
$ic_dislike = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor"><path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/></svg>';
$ic_author = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
$ic_uni = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99v-2.08l-5 2.73-5-2.73v2.08l5 2.73 5-2.73z"/></svg>';
$ic_pen = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
$ic_options = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>';
$ic_verify = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?=htmlspecialchars($g_data['name'])?> - آتوکس</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.85); --x-hover:rgba(15,20,25,0.05); --x-hover-b:rgba(29,155,240,0.1); --x-modal:rgba(0,0,0,0.4); --x-red:#f91880; }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.85); --x-hover:rgba(255,255,255,0.05); --x-modal:rgba(255,255,255,0.1); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,sans-serif}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;overflow-y:scroll;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}

.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:120px;min-height:100vh;}

.glass-wrap { position:sticky; top:0; z-index:10; }
.hdr { padding:0; display:flex; flex-direction:column; background:var(--x-bg-trans); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border-bottom:1px solid var(--x-border); }
.vt-top-bar { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; }
.vt-top-left { display:flex; align-items:center; gap:15px; cursor:pointer; }
.vt-back { width:36px; height:36px; border-radius:50%; display:flex; justify-content:center; align-items:center; transition:0.2s; }
.vt-back:hover { background:var(--x-hover); }
.vt-title { font-size:18px; font-weight:800; display:flex; flex-direction:column; }
.vt-subtitle { font-size:12px; color:var(--x-gray); font-weight:normal; margin-top:2px; }

.vt-top-right { display:flex; gap:8px; align-items:center; }
.glass-btn-ui { background:rgba(15,20,25,0.05); border:1px solid rgba(15,20,25,0.1); backdrop-filter:blur(10px); padding:8px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--x-black); transition:all 0.2s; }
.dark .glass-btn-ui { background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.1); color:#fff; }
.glass-btn-ui:hover { background:var(--x-hover-b); border-color:var(--x-blue); color:var(--x-blue); }

.btn-add-mini { background:var(--x-black); color:var(--x-bg); padding:6px 14px; border-radius:99px; font-size:13px; font-weight:700; display:flex; align-items:center; gap:4px; transition:0.2s; }
.btn-add-mini:hover { opacity:0.8; transform:scale(0.98); }
.dark .btn-add-mini { background:var(--x-bg); color:var(--x-black); }


.t-feed { display: flex; flex-direction: column; }
.j-card { padding: 16px; border-bottom: 1px solid var(--x-border); cursor: pointer; transition: 0.2s; display: flex; flex-direction: column; gap: 10px; position: relative; }
.j-card:hover { background: var(--x-hover); }


.j-header { display: flex; justify-content: space-between; align-items: flex-start; }
.j-title-area { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.j-course { font-size: 16px; font-weight: 800; color: var(--x-black); }
.j-lang { font-size: 11px; background: var(--x-hover-b); color: var(--x-blue); padding: 2px 8px; border-radius: 6px; font-weight: 700; border: 1px solid rgba(29,155,240,0.1); }


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
.j-stat { display: flex; align-items: center; gap: 4px; transition: 0.2s; }
.j-stat.actionable:hover { color: var(--x-blue); }
.j-stat.like.active { color: var(--x-red); }
.j-stat.dislike.active { color: #f98018; }
.j-time { color: var(--x-gray); font-size: 11px; font-weight: normal; margin-left: 4px;}


.t-options { color: var(--x-gray); width: 30px; height: 30px; display: flex; justify-content: center; align-items: center; border-radius: 50%; transition: 0.2s; }
.t-options:hover { background: var(--x-hover-b); color: var(--x-blue); }
.dropdown-menu { display: none; position: absolute; left: 16px; top: 40px; background: var(--x-bg); border: 1px solid var(--x-border); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 100; min-width: 150px; overflow: hidden; }
.dark .dropdown-menu { box-shadow: 0 4px 15px rgba(255,255,255,0.1); }
.dropdown-menu.show { display: block; }
.dd-item { padding: 12px 16px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: 0.2s; color: var(--x-black); }
.dd-item:hover { background: var(--x-hover); }
.dd-item.danger { color: var(--x-red); }


.mod{display:none;position:fixed;inset:0;background:var(--x-modal);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);}
.m-c{position:relative;background:var(--x-bg);border-radius:24px;width:90%;max-width:450px;padding:24px;box-shadow:0 15px 50px rgba(0,0,0,.2);animation:p .3s cubic-bezier(0.175, 0.885, 0.32, 1.275); max-height:90vh; overflow-y:auto;}
.dark .m-c { box-shadow:0 15px 50px rgba(0,0,0,.6); border:1px solid rgba(255,255,255,0.05); }
.m-hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--x-border);padding-bottom:15px;margin-bottom:20px;}
.m-hdr h2 { font-size:18px; font-weight:800; }

.input-ui{width:100%;padding:14px;border:1px solid var(--x-border);border-radius:14px;font-size:15px;margin-bottom:14px;background:rgba(15,20,25,0.02);color:var(--x-black);outline:none;box-sizing:border-box;transition:0.2s;}
.dark .input-ui { background:rgba(255,255,255,0.02); }
.input-ui:focus{border-color:var(--x-blue); background:transparent; box-shadow:0 0 0 4px var(--x-hover-b);}
.check-ui { display: flex; align-items: center; gap: 10px; font-size: 14px; margin-bottom: 14px; cursor: pointer; color: var(--x-black); user-select: none; padding: 12px; border: 1px solid var(--x-border); border-radius: 12px; background: rgba(15,20,25,0.02); }
.dark .check-ui { background:rgba(255,255,255,0.02); }
.check-ui input { width: 18px; height: 18px; accent-color: var(--x-blue); cursor: pointer; }

.btn-submit{background:var(--x-blue);color:#fff;border:none;padding:14px;border-radius:99px;font-weight:700;font-size:15px;cursor:pointer;width:100%;transition:0.2s; box-shadow:0 4px 12px rgba(29,155,240,0.3);}
.btn-submit:hover { transform:translateY(-2px); box-shadow:0 6px 15px rgba(29,155,240,0.4); }
.btn-submit.danger{background:var(--x-red); box-shadow:0 4px 12px rgba(249,24,128,0.3);}
.btn-submit.danger:hover { box-shadow:0 6px 15px rgba(249,24,128,0.4); }

@keyframes p{0%{transform:translateY(30px) scale(0.95);opacity:0}100%{transform:translateY(0) scale(1);opacity:1}}

@media(max-width:600px){ .main{border:none; padding-bottom:100px;} }
</style>
</head>
<body>
<div class="app">
    <main class="main">
        
        <div class="glass-wrap">
            <div class="hdr">
                <?php include 'header.php'; ?>
                <div class="vt-top-bar">
                    <div class="vt-top-left" onclick="window.history.back()">
                        <div class="vt-back"><?=$ic_back?></div>
                        <div class="vt-title">
                            <?=htmlspecialchars($g_data['name'])?>
                            <span class="vt-subtitle">ترم <?=pNum($g_data['term'])?> · <?=htmlspecialchars($kanoon_name)?></span>
                        </div>
                    </div>
                    <div class="vt-top-right">
                        <button class="btn-add-mini" onclick="oM('addJozveModal')"><?=$ic_plus?> جزوه جدید</button>
                        <?php if($is_admin): ?>
                            <button class="glass-btn-ui" onclick="openEditGroupModal('<?=$g_data['id']?>', '<?=htmlspecialchars(addslashes($g_data['name']))?>', '<?=$g_data['term']?>')"><?=$ic_edit?></button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="t-feed">
            <?php foreach($jozve_list as $j): 
                $can_edit = ($is_admin || $j['user_id'] == $uid);
                $avatar = !empty($j['publisher_avatar']) ? $j['publisher_avatar'] : 'assets/default_avatar.png'; 
                if(!file_exists($avatar) && empty($j['publisher_avatar'])) $avatar = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23536471"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
            ?>
                <div class="j-card" onclick="location.href='magazine.php?id=<?=$j['id']?>'">
                    
                    <!-- خط اول: نام درس و آپشن ها -->
                    <div class="j-header">
                        <div class="j-title-area">
                            <span class="j-course"><?=htmlspecialchars($g_data['name'])?></span>
                            <?php if(!empty($j['language'])): ?>
                                <span class="j-lang"><?=htmlspecialchars($j['language'])?></span>
                            <?php endif; ?>
                        </div>

                        <?php if($can_edit): ?>
                        <div style="position:relative;">
                            <div class="t-options" onclick="toggleDropdown(event, 'dd-<?=$j['id']?>')"><?=$ic_options?></div>
                            <div id="dd-<?=$j['id']?>" class="dropdown-menu">
                                <div class="dd-item" onclick="openEditJozve(event, '<?=$j['id']?>', '<?=htmlspecialchars(addslashes($j['description'] ?? ''))?>', '<?=htmlspecialchars(addslashes($j['file_link'] ?? ''))?>', '<?=htmlspecialchars(addslashes($j['author_name'] ?? ''))?>', '<?=htmlspecialchars(addslashes($j['university_name'] ?? ''))?>', '<?=htmlspecialchars(addslashes($j['language'] ?? ''))?>', <?=!empty($j['is_handwritten']) ? 'true' : 'false'?>)">
                                    <?=$ic_edit?> ویرایش جزوه
                                </div>
                                <div class="dd-item danger" onclick="openDelJozve(event, '<?=$j['id']?>')">
                                    <?=$ic_delete?> حذف جزوه
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- مشخصات جزوه -->
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

                    <!-- توضیحات -->
                    <?php if(!empty($j['description'])): ?>
                        <div class="j-desc"><?=htmlspecialchars($j['description'])?></div>
                    <?php endif; ?>

                    <!-- فوتر حرفه‌ای (پروفایل + آمار) -->
                    <div class="j-footer">
                        <div class="j-profile" onclick="event.stopPropagation(); location.href='profile.php?username=<?=htmlspecialchars($j['publisher_username'] ?? '')?>'">
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
                            <div class="j-time" dir="rtl"><?=toJalali($j['created_at'])?></div>
                            <div class="j-stat" title="بازدید">
                                <?=$ic_view?> <span><?=formatLikes($j['views'] ?? 0)?></span>
                            </div>
                            <div class="j-stat actionable like <?=($j['user_reaction'] === 'like') ? 'active' : ''?>" onclick="reactJozve(event, <?=$j['id']?>, 'like')" id="like-btn-<?=$j['id']?>">
                                <?=$ic_like?> <span id="like-cnt-<?=$j['id']?>"><?=pNum($j['likes_count'])?></span>
                            </div>
                            <div class="j-stat actionable dislike <?=($j['user_reaction'] === 'dislike') ? 'active' : ''?>" onclick="reactJozve(event, <?=$j['id']?>, 'dislike')" id="dislike-btn-<?=$j['id']?>">
                                <?=$ic_dislike?> <span id="dislike-cnt-<?=$j['id']?>"><?=pNum($j['dislikes_count'])?></span>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
            
            <?php if(empty($jozve_list)): ?>
                <div style="text-align:center;padding:80px 20px;color:var(--x-gray);font-weight:bold;">
                    <div style="font-size:40px; margin-bottom:10px;">📄</div>
                    هنوز جزوه‌ای برای این درس آپلود نشده است.<br>اولین نفری باشید که جزوه می‌گذارد!
                </div>
            <?php endif; ?>
        </div>
        
    </main>
</div>

<!-- ================= Modals ================= -->

<?php if($is_admin): ?>
<div id="editGroupModal" class="mod">
    <div class="m-c">
        <div class="m-hdr"><h2>ویرایش مشخصات درس</h2><button type="button" onclick="tgM('editGroupModal')" style="font-size:24px;color:var(--x-black);background:none;border:none;">✕</button></div>
        <form action="actions.php" method="POST">
            <input type="hidden" name="action" value="edit_course">
            <input type="hidden" name="id" id="edit_group_id">
            <input type="hidden" name="kanoon_id" value="<?=$g_data['kanoon_id'] ?? 0?>">
            <input type="text" name="name" id="edit_group_name" class="input-ui" required>
            <input type="number" name="term" id="edit_group_term" class="input-ui" min="1" max="10" required>
            <button type="submit" class="btn-submit">ذخیره تغییرات درس</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div id="addJozveModal" class="mod">
    <div class="m-c">
        <div class="m-hdr"><h2>ارسال جزوه جدید</h2><button type="button" onclick="tgM('addJozveModal')" style="font-size:24px;color:var(--x-black);background:none;border:none;">✕</button></div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="add_jozve">
            <input type="hidden" name="group_id" value="<?=$group_id?>">
            <input type="text" name="author_name" class="input-ui" placeholder="نام نویسنده (اختیاری)...">
            <input type="text" name="university_name" class="input-ui" placeholder="نام دانشگاه (اختیاری)...">
            <input type="text" name="language" class="input-ui" placeholder="زبان (مثلاً فارسی)...">
            <label class="check-ui"><input type="checkbox" name="is_handwritten" value="1"> این جزوه کاملاً دست‌نویس است</label>
            <textarea name="description" class="input-ui" placeholder="توضیحات جزوه (مثلا: جلسات ۱ تا ۳)..." style="min-height:80px; resize:vertical;"></textarea>
            <input type="url" name="file_link" class="input-ui" placeholder="لینک دانلود فایل (درایو، تلگرام و ...)" dir="ltr" style="text-align: left;" required>
            <button type="submit" class="btn-submit">انتشار جزوه</button>
        </form>
    </div>
</div>

<div id="editJozveModal" class="mod">
    <div class="m-c">
        <div class="m-hdr"><h2>ویرایش جزوه</h2><button type="button" onclick="tgM('editJozveModal')" style="font-size:24px;color:var(--x-black);background:none;border:none;">✕</button></div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="edit_jozve">
            <input type="hidden" name="id" id="edit_jozve_id">
            <input type="hidden" name="group_id" value="<?=$group_id?>">
            <input type="text" name="author_name" id="edit_jozve_author" class="input-ui" placeholder="نام نویسنده (اختیاری)...">
            <input type="text" name="university_name" id="edit_jozve_uni" class="input-ui" placeholder="نام دانشگاه (اختیاری)...">
            <input type="text" name="language" id="edit_jozve_lang" class="input-ui" placeholder="زبان...">
            <label class="check-ui"><input type="checkbox" name="is_handwritten" id="edit_jozve_hw" value="1"> این جزوه کاملاً دست‌نویس است</label>
            <textarea name="description" id="edit_jozve_desc" class="input-ui" placeholder="توضیحات..." style="min-height:80px; resize:vertical;"></textarea>
            <input type="url" name="file_link" id="edit_jozve_link" class="input-ui" placeholder="لینک دانلود جزوه" dir="ltr" style="text-align: left;" required>
            <button type="submit" class="btn-submit">بروزرسانی جزوه</button>
        </form>
    </div>
</div>

<div id="delJozveModal" class="mod">
    <div class="m-c" style="text-align:center;">
        <button type="button" onclick="tgM('delJozveModal')" style="position:absolute; top:15px; right:15px; font-size:24px;color:var(--x-black);background:none;border:none;">✕</button>
        <div style="margin-bottom:20px; color:var(--x-red); transform:scale(1.5);"><?=$ic_delete?></div>
        <h2 style="margin-bottom:10px;">حذف جزوه؟</h2>
        <p style="color:var(--x-gray); font-size:14px; margin-bottom:25px; line-height:1.6;">آیا از حذف این جزوه مطمئن هستید؟ این کار غیرقابل بازگشت است.</p>
        <form action="" method="POST">
            <input type="hidden" name="action" value="delete_jozve">
            <input type="hidden" name="id" id="del_jozve_id">
            <input type="hidden" name="group_id" value="<?=$group_id?>">
            <button type="submit" class="btn-submit danger">بله، حذف شود</button>
        </form>
    </div>
</div>


<script>
const tgM = i => document.getElementById(i).style.display = 'none';
const oM = i => document.getElementById(i).style.display = 'flex';

window.onclick = e => { 
    if(e.target.classList.contains('mod')) e.target.style.display = 'none'; 
    if(!e.target.closest('.t-options')) {
        document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.remove('show'));
    }
};

function toggleDropdown(e, id) {
    e.stopPropagation();
    document.querySelectorAll('.dropdown-menu').forEach(d => {
        if(d.id !== id) d.classList.remove('show');
    });
    document.getElementById(id).classList.toggle('show');
}

function openEditGroupModal(id, name, term) {
    document.getElementById('edit_group_id').value = id;
    document.getElementById('edit_group_name').value = name;
    document.getElementById('edit_group_term').value = term;
    oM('editGroupModal');
}

function openEditJozve(e, id, desc, link, author, uni, lang, is_hw) {
    e.stopPropagation();
    document.getElementById('edit_jozve_id').value = id;
    document.getElementById('edit_jozve_desc').value = desc;
    document.getElementById('edit_jozve_link').value = link;
    document.getElementById('edit_jozve_author').value = author;
    document.getElementById('edit_jozve_uni').value = uni;
    document.getElementById('edit_jozve_lang').value = lang;
    document.getElementById('edit_jozve_hw').checked = is_hw;
    oM('editJozveModal');
}

function openDelJozve(e, id) {
    e.stopPropagation();
    document.getElementById('del_jozve_id').value = id;
    oM('delJozveModal');
}

function reactJozve(e, id, type) {
    e.stopPropagation(); 
    fetch('actions.php?action=react_jozve', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ jozve_id: id, type: type })
    }).then(res => res.json()).then(data => {
        if(data.status === 'success') {
            document.getElementById('like-cnt-' + id).innerText = data.likes.toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
            document.getElementById('dislike-cnt-' + id).innerText = data.dislikes.toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
            let lBtn = document.getElementById('like-btn-' + id);
            let dBtn = document.getElementById('dislike-btn-' + id);
            if(type === 'like') {
                if(lBtn.classList.contains('active')) lBtn.classList.remove('active');
                else { lBtn.classList.add('active'); dBtn.classList.remove('active'); }
            } else {
                if(dBtn.classList.contains('active')) dBtn.classList.remove('active');
                else { dBtn.classList.add('active'); lBtn.classList.remove('active'); }
            }
        }
    }).catch(err => console.error(err));
}
</script>
<?php include 'footer.php'; ?>

</body>
</html>
<?php ob_end_flush(); ?>
