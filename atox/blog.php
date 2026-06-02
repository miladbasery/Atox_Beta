<?php
ob_start();
session_start();
require 'db.php';

$uid = $_SESSION['user_id'] ?? 0;
$user_role = 'user';
$user_name = 'کاربر';
$is_logged = false;
$user_level = 0; // متغیر ذخیره سطح کاربر

// توابع کمکی
function pNum($str) {
    return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str);
}

// تابع تبدیل اعداد به K و M
function formatLikes($num) {
    if ($num >= 1000000) return pNum(round($num / 1000000, 1)) . 'M';
    if ($num >= 1000) return pNum(round($num / 1000, 1)) . 'K';
    return pNum($num);
}

// تابع تبدیل قالب‌بندی‌ها برای پیش‌نمایش (بدون ایجاد تگ a تو در تو برای جلوگیری از بهم ریختگی HTML کارت‌ها)
function parseRichTextPreview($text) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\w)\*(.*?)\*(?!\w)/s', '<em>$1</em>', $text);
    // نمایش لینک‌ها به شکل متن آبی رنگ به جای تگ <a> واقعی
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<span style="color:var(--x-blue);text-decoration:underline;">$1</span>', $text);
    return nl2br($text);
}

// ساخت جدول لایک مقالات در صورت عدم وجود (برای جلوگیری از لایک بینهایت)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        blog_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (user_id, blog_id)
    )");
} catch (PDOException $e) {}

if ($uid) {
    $stmt = $pdo->prepare("SELECT role, username, name, level FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) { session_destroy(); header("Location: index.php"); exit; }
    
    $user_role = $user_data['role'] ?? 'user';
    $user_level = (int)($user_data['level'] ?? 0);
    // گرفتن نام کاربر (یا یوزرنیم در صورت نداشتن نام) برای پاپ آپ
    $user_name = !empty($user_data['name']) ? $user_data['name'] : $user_data['username'];
    
    if ($user_data['username'] === 'milad') { $user_role = 'admin'; $_SESSION['role'] = 'admin'; }
    $is_logged = true;
}

$is_admin = ($user_role === 'admin');
// دسترسی به افزودن مقاله: ادمین یا کاربری که لول ۲۰ یا بالاتر دارد
$can_add_blog = ($is_admin || $user_level >= 20);

$db_error = "";

// پردازش فرم افزودن مقاله (پاپ‌آپ) با آپلود فایل و تگ‌ها - فقط کسانی که دسترسی دارند
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_blog']) && $can_add_blog) {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $tags_input = trim($_POST['tags'] ?? '');
    $image_path = '';

    $tags_array = array_filter(array_map('trim', explode(',', $tags_input)));
    $tags_array = array_slice($tags_array, 0, 5);
    $final_tags = implode(',', $tags_array);

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['image']['tmp_name'];
        $size = $_FILES['image']['size'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if ($size <= 1048576) { 
            if (in_array($ext, $allowed)) {
                if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
                $new_name = 'uploads/blog_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($tmp_name, $new_name)) {
                    $image_path = $new_name;
                }
            } else {
                $db_error = "فرمت عکس مجاز نیست.";
            }
        } else {
            $db_error = "حجم عکس نباید بیشتر از 1 مگابایت باشد.";
        }
    }

    if (empty($db_error) && !empty($title) && !empty($desc)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO blogs (writer_id, title, description, image, tags) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$uid, $title, $desc, $image_path, $final_tags]);
            header("Location: blog.php");
            exit;
        } catch (PDOException $e) { $db_error = $e->getMessage(); }
    }
}

// پردازش لایک یک‌باره واقعی
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_blog_id']) && $is_logged) {
    $b_id = (int)$_POST['like_blog_id'];
    
    $check_stmt = $pdo->prepare("SELECT id FROM blog_likes WHERE user_id = ? AND blog_id = ?");
    $check_stmt->execute([$uid, $b_id]);
    
    if ($check_stmt->rowCount() > 0) {
        $pdo->prepare("DELETE FROM blog_likes WHERE user_id = ? AND blog_id = ?")->execute([$uid, $b_id]);
        $pdo->exec("UPDATE blogs SET likes_count = GREATEST(0, likes_count - 1) WHERE id = $b_id");
    } else {
        $pdo->prepare("INSERT INTO blog_likes (user_id, blog_id) VALUES (?, ?)")->execute([$uid, $b_id]);
        $pdo->exec("UPDATE blogs SET likes_count = likes_count + 1 WHERE id = $b_id");
    }
    
    header("Location: blog.php" . (isset($_GET['tab']) ? "?tab=" . $_GET['tab'] : ""));
    exit;
}

$user_liked_blogs = [];
if ($is_logged) {
    $stmt_likes = $pdo->prepare("SELECT blog_id FROM blog_likes WHERE user_id = ?");
    $stmt_likes->execute([$uid]);
    $user_liked_blogs = $stmt_likes->fetchAll(PDO::FETCH_COLUMN);
}

// تنظیمات تب و صفحه‌بندی
$tab = $_GET['tab'] ?? 'global';
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_pages = 1;
$blogs = [];

try {
    if ($tab === 'following' && $is_logged) {
        $c_stmt = $pdo->prepare("SELECT COUNT(b.id) FROM blogs b INNER JOIN follows f ON f.following_id = b.writer_id AND f.follower_id = ? WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $c_stmt->execute([$uid]);
        $total_pages = ceil($c_stmt->fetchColumn() / $limit);

        $query = "SELECT b.*, u.name AS writer_name, u.is_verified, u.avatar AS writer_avatar 
                  FROM blogs b 
                  INNER JOIN follows f ON f.following_id = b.writer_id AND f.follower_id = ?
                  JOIN users u ON b.writer_id = u.id 
                  WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$uid]);
        $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $c_stmt = $pdo->query("SELECT COUNT(id) FROM blogs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $total_pages = ceil($c_stmt->fetchColumn() / $limit);

        $query = "SELECT b.*, u.name AS writer_name, u.is_verified, u.avatar AS writer_avatar 
                  FROM blogs b 
                  JOIN users u ON b.writer_id = u.id 
                  WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->query($query);
        $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

$blue_tick = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg>';
$ic_like_outline = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M16.697 5.5c-1.222-.06-2.679.51-3.89 2.16l-.805 1.09-.806-1.09C9.984 6.01 8.526 5.44 7.304 5.5c-1.243.07-2.349.78-2.91 1.91-.552 1.12-.633 2.78.479 4.82 1.074 1.97 3.257 4.27 7.129 6.61 3.87-2.34 6.052-4.64 7.126-6.61 1.111-2.04 1.03-3.7.477-4.82-.561-1.13-1.666-1.84-2.908-1.91zm4.187 7.69c-1.351 2.48-4.001 5.12-8.379 7.67l-.503.3-.504-.3c-4.379-2.55-7.029-5.19-8.382-7.67-1.36-2.5-1.41-4.86-.514-6.67.887-1.79 2.647-2.91 4.601-3.01 1.651-.09 3.368.56 4.798 2.01 1.429-1.45 3.146-2.1 4.796-2.01 1.954.1 3.714 1.22 4.601 3.01.896 1.81.846 4.17-.514 6.67z"/></svg>';
$ic_like_filled = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#f91880"><path d="M20.884 13.19c-1.351 2.48-4.001 5.12-8.379 7.67l-.503.3-.504-.3C7.121 18.31 4.471 15.67 3.118 13.19 1.758 10.69 1.708 8.33 2.604 6.52c.887-1.79 2.647-2.91 4.601-3.01 1.651-.09 3.368.56 4.798 2.01 1.429-1.45 3.146-2.1 4.796-2.01 1.954.1 3.714 1.22 4.601 3.01.896 1.81.846 4.17-.514 6.67z"/></svg>';
$ic_plus = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M11 11V4h2v7h7v2h-7v7h-2v-7H4v-2h7z"/></svg>';
$ic_view = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:currentColor"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
$ic_share = '<svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>مقالات - آتوکس</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.85); --x-hover:rgba(15,20,25,0.05); --x-hover-b:rgba(29,155,240,0.1); --x-modal:rgba(0,0,0,0.4); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.85); --x-hover:rgba(255,255,255,0.05); --x-modal:rgba(255,255,255,0.1); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,sans-serif}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;overflow-y:scroll;overflow-x:hidden;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}

.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:120px;min-height:100vh;}
.left-side{width:350px;padding:12px 24px;position:sticky;top:0;height:100vh;display:block;}

.hdr{position:sticky;top:0;background:var(--x-bg-trans);backdrop-filter:blur(12px);z-index:10;border-bottom:1px solid var(--x-border)}
.page-title{padding:12px 16px;font-size:20px;font-weight:900;display:flex;justify-content:space-between;align-items:center;}

.btn-add{background:var(--x-black);color:var(--x-bg);padding:6px 16px;border-radius:99px;font-size:14px;font-weight:700;display:flex;align-items:center;gap:4px;transition:0.2s;}
.btn-add:hover{opacity:0.8;}

.blog-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; padding: 20px; }
.b-card { background: rgba(255,255,255,0.4); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.5); border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: 0.3s ease; display: flex; flex-direction: column; overflow: hidden; }
.dark .b-card { background: rgba(30,30,30,0.4); border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
.b-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
.dark .b-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.4); }

.b-img { width: 100%; aspect-ratio: 16/9; object-fit: cover; border-bottom: 1px solid var(--x-border); background: var(--x-hover); }
.b-content { padding: 14px; display: flex; flex-direction: column; flex: 1; text-decoration: none; color: inherit; }

.b-title { font-size: 16px; font-weight: 800; margin-bottom: 6px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.b-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 8px; }
.b-tag { font-size: 11px; color: var(--x-blue); background: var(--x-hover-b); padding: 2px 8px; border-radius: 99px; font-weight: 600; }
.b-desc { font-size: 14px; color: var(--x-gray); line-height: 1.6; margin-bottom: 16px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }

.b-footer { display: flex; justify-content: space-between; align-items: center; padding: 0 14px 14px 14px; background: transparent; }
.b-author { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; }
.b-author img { width: 26px; height: 26px; border-radius: 50%; object-fit: cover; background: var(--x-hover); }

.like-btn { display: flex; align-items: center; gap: 4px; color: var(--x-gray); font-size: 13px; font-weight: 600; padding: 4px 8px; border-radius: 99px; transition: 0.2s; }
.like-btn.liked { color: #f91880; }
.like-btn:hover { color: #f91880; background: rgba(249,24,128,0.1); }
.action-btn { display: flex; align-items: center; justify-content: center; color: var(--x-gray); font-size: 13px; padding: 4px 8px; border-radius: 99px; transition: 0.2s; cursor: pointer; }
.action-btn:hover { color: var(--x-blue); background: rgba(29,155,240,0.1); }


.mod{display:none;position:fixed;inset:0;background:var(--x-modal);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px);}
.m-c{position:relative;background:var(--x-bg);border-radius:24px;width:90%;max-width:500px;padding:24px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:p .3s cubic-bezier(0.175, 0.885, 0.32, 1.275);box-sizing:border-box;}
.m-hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--x-border);padding-bottom:15px;margin-bottom:15px;}
.input-ui{width:100%;padding:12px;border:1px solid var(--x-border);border-radius:12px;font-size:15px;margin-bottom:12px;background:transparent;color:var(--x-black);outline:none;box-sizing:border-box;}
.input-ui:focus{border-color:var(--x-blue);}
.file-ui{padding:8px 12px; border:1px dashed var(--x-border); border-radius:12px; margin-bottom:12px; width:100%; font-size:14px; color:var(--x-gray);}
.btn-submit{background:var(--x-blue);color:#fff;border:none;padding:12px;border-radius:99px;font-weight:700;font-size:15px;cursor:pointer;width:100%;transition:0.2s;}


.pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin: 20px 12px 50px; flex-wrap: wrap; direction: ltr; }
.page-link { padding: 8px 16px; border-radius: 14px; background: rgba(255,255,255,0.4); border: 1px solid rgba(255,255,255,0.6); color: var(--x-black); font-weight: bold; font-size: 15px; transition: 0.2s; backdrop-filter: blur(10px); }
.dark .page-link { background: rgba(30,30,30,0.4); border-color: rgba(255,255,255,0.1); }
.page-link:hover { background: var(--x-hover); transform: translateY(-2px); }
.page-link.active { background: var(--x-blue); color: #fff; border-color: var(--x-blue); }

@keyframes p{0%{transform:translateY(30px) scale(0.9);opacity:0}100%{transform:translateY(0) scale(1);opacity:1}}
@media(max-width:1050px){ .left-side{display:none;} }
@media(max-width:600px){ 
    .main{border:none; padding-bottom:100px;} 
    .blog-grid { grid-template-columns: 1fr; gap: 16px; padding: 16px; }
}
</style>
</head>
<body>
<div class="app">
    <main class="main">
        <div class="hdr">
            <?php include 'header.php'; ?>
            
            <div class="page-title">
                <span>مقالات</span>
                <?php if($can_add_blog): ?>
                    <button class="btn-add" onclick="oM('addBlogModal')"><?=$ic_plus?> مقاله جدید</button>
                <?php else: ?>
                    <button class="btn-add" onclick="<?=$is_logged ? "oM('upgradeModal')" : "oM('lM')"?>"><?=$ic_plus?> مقاله جدید</button>
                <?php endif; ?>
            </div>

            <!-- خط جداکننده اینجا (border-top) حذف شد -->
            <div style="display:flex; width:100%;">
                <a href="?tab=global" style="flex:1; text-align:center; padding:14px 0; font-size:14px; font-weight:bold; position:relative; transition:0.2s; color:<?=$tab==='global'?'var(--x-black)':'var(--x-gray)'?>;">دیوار جهانی<?php if($tab === 'global'): ?><div style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:40px; height:4px; background:var(--x-blue); border-radius:4px;"></div><?php endif; ?></a>
                <a href="<?=$is_logged ? '?tab=following' : '#'?>" onclick="<?=$is_logged?'':'oM(\'lM\')'?>" style="flex:1; text-align:center; padding:14px 0; font-size:14px; font-weight:bold; position:relative; transition:0.2s; color:<?=$tab==='following'?'var(--x-black)':'var(--x-gray)'?>;">دنبال می‌کنید<?php if($tab === 'following'): ?><div style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:84px; height:4px; background:var(--x-blue); border-radius:4px;"></div><?php endif; ?></a>
            </div>
        </div>

        <?php if($db_error): ?>
            <div style="padding: 20px; color: #f91880; text-align: center; font-weight: bold;"><?= htmlspecialchars($db_error) ?></div>
        <?php elseif(empty($blogs)): ?>
            <div style="text-align:center; padding:80px 20px; color:var(--x-gray); font-size:16px; font-weight:bold;">در ۳۰ روز گذشته مقاله‌ای منتشر نشده است.</div>
        <?php else: ?>
            <div class="blog-grid">
                <?php foreach($blogs as $blog): ?>
                    <div class="b-card">
                        <a href="view_blog.php?id=<?= $blog['id'] ?>" style="display:contents">
                            <?php if(!empty($blog['image'])): ?>
                                <img src="<?= htmlspecialchars($blog['image']) ?>" class="b-img" alt="cover" loading="lazy">
                            <?php endif; ?>
                            <div class="b-content">
                                <h3 class="b-title"><?= htmlspecialchars($blog['title']) ?></h3>
                                <?php if(!empty($blog['tags'])): ?>
                                <div class="b-tags">
                                    <?php foreach(explode(',', $blog['tags']) as $tag): ?>
                                        <span class="b-tag">#<?= htmlspecialchars(trim($tag)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <!-- استفاده از تابع پیش‌نمایش به جای strip_tags برای نمایش قوانین فرمت -->
                                <p class="b-desc"><?= parseRichTextPreview($blog['description']) ?></p>
                            </div>
                        </a>
                        
                        <div class="b-footer">
                            <div class="b-author">
                                <img src="<?= htmlspecialchars($blog['writer_avatar'] ?: "https://ui-avatars.com/api/?name=".urlencode($blog['writer_name'])) ?>" alt="avatar" loading="lazy">
                                <span><?= htmlspecialchars($blog['writer_name']) ?></span>
                                <?= $blog['is_verified'] ? $blue_tick : '' ?>
                            </div>
                            
                            <div style="display:flex; align-items:center; gap:6px;">
                                <!-- دکمه اشتراک‌گذاری -->
                                <button type="button" class="action-btn" onclick="shareBlog('<?= htmlspecialchars(addslashes($blog['title'])) ?>', '<?= $blog['id'] ?>')" title="اشتراک‌گذاری">
                                    <?= $ic_share ?>
                                </button>
                                
                                <!-- آمار بازدید -->
                                <div class="action-btn" title="بازدیدها" style="cursor:default; gap:4px;">
                                    <?= $ic_view ?>
                                    <span><?= formatLikes($blog['views_count'] ?? 0) ?></span>
                                </div>

                                <!-- فرم لایک -->
                                <form method="POST" style="margin:0" onsubmit="<?=$is_logged?"":"oM('lM');return false;"?>">
                                    <input type="hidden" name="like_blog_id" value="<?= $blog['id'] ?>">
                                    <?php $has_liked = in_array($blog['id'], $user_liked_blogs); ?>
                                    <button type="submit" class="like-btn <?= $has_liked ? 'liked' : '' ?>">
                                        <?= $has_liked ? $ic_like_filled : $ic_like_outline ?>
                                        <span><?= formatLikes($blog['likes_count'] ?? 0) ?></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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

<!-- مودال لاگین -->
<div id="lM" class="mod"><div class="m-c"><div class="m-hdr"><h2>ورود به آتوکس</h2><button onclick="tgM('lM')" style="font-size:24px;color:var(--x-black);border-radius:50%;padding:4px 8px;transition:.2s;background:none;border:none;">✕</button></div><p style="margin:20px 0;color:var(--x-gray);font-size:16px;">برای انجام این عملیات وارد شوید.</p><a href="auth.php" class="btn-submit" style="display:block;text-align:center;">ورود / ثبت‌نام</a></div></div>

<!-- مودال ارتقا اکانت (مخصوص کاربرانی که لول زیر ۲۰ دارند) -->
<?php if(!$can_add_blog && $is_logged): ?>
<div id="upgradeModal" class="mod">
    <div class="m-c" style="text-align:center; padding: 40px 24px;">
        <button type="button" onclick="tgM('upgradeModal')" style="position:absolute; top:15px; right:15px; font-size:24px;color:var(--x-gray);border-radius:50%;padding:4px 8px;background:none;border:none;">✕</button>
        
        <svg viewBox="0 0 24 24" style="width:64px;height:64px;fill:var(--x-blue);margin:0 auto 16px;"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
        
        <h2 style="font-size:22px; font-weight:900; margin-bottom:12px; color:var(--x-black);">ارتقا به نویسنده (سطح ۲۰)</h2>
        <p style="color:var(--x-gray); font-size:15px; line-height:1.8; margin-bottom:24px;">
            <strong><?= htmlspecialchars($user_name) ?> جان</strong>، برای اینکه بتونی مقاله بزاری باید تو کارت حرفه‌ای شی و به <strong>سطح ۲۰</strong> برسی. با تکمیل کردن پروفایل و رزومه، و انتشار توییت‌های جذاب به این مرحله برس. می‌دونم می‌تونی برسی! 🚀
            <br><span style="font-size:13px; color:var(--x-blue); margin-top:8px; display:inline-block;">سطح فعلی شما: <?= $user_level ?></span>
        </p>
        
        <button class="btn-submit" onclick="tgM('upgradeModal')" style="width:auto; padding:12px 32px;">تلاشم رو می‌کنم!</button>
    </div>
</div>
<?php endif; ?>

<!-- مودال افزودن مقاله برای ادمین و لول ۲۰ به بالا -->
<?php if($can_add_blog): ?>
<div id="addBlogModal" class="mod">
    <div class="m-c">
        <div class="m-hdr">
            <h2>نوشتن مقاله جدید</h2>
            <button type="button" onclick="tgM('addBlogModal')" style="font-size:24px;color:var(--x-black);border-radius:50%;padding:4px 8px;transition:.2s;background:none;border:none;">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="add_blog" value="1">
            <input type="text" name="title" class="input-ui" placeholder="عنوان مقاله" required>
            <input type="text" name="tags" class="input-ui" placeholder="تگ‌ها را با کاما (,) جدا کنید (حداکثر ۵ تگ)">
            <input type="file" name="image" class="file-ui" accept=".jpg,.jpeg,.png,.gif,.webp" title="آپلود عکس کاور (زیر ۱ مگابایت)">
            
            <!-- نوار ابزار قالب‌بندی متن -->
            <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                <button type="button" onclick="insertFormat('**', '**')" style="padding: 4px 12px; background: var(--x-hover); border: 1px solid var(--x-border); border-radius: 6px; font-weight: bold; cursor: pointer; color: var(--x-black);" title="بولد">B</button>
                <button type="button" onclick="insertFormat('*', '*')" style="padding: 4px 12px; background: var(--x-hover); border: 1px solid var(--x-border); border-radius: 6px; font-style: italic; font-family: serif; cursor: pointer; color: var(--x-black);" title="ایتالیک">I</button>
                <button type="button" onclick="insertLink()" style="padding: 4px 12px; background: var(--x-hover); border: 1px solid var(--x-border); border-radius: 6px; cursor: pointer; color: var(--x-blue); font-weight: bold;" title="افزودن لینک">🔗 لینک</button>
            </div>
            
            <textarea id="new_desc" name="description" class="input-ui" placeholder="متن اصلی مقاله..." style="min-height:150px; resize:vertical;" required></textarea>
            <button type="submit" class="btn-submit">انتشار مقاله</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- فراخوانی فوتر جداگانه -->

<script>
const tgM = i => document.getElementById(i).style.display = 'none';
const oM = i => document.getElementById(i).style.display = 'flex';
window.onclick = e => { if(e.target.classList.contains('mod')) e.target.style.display = 'none'; };

// توابع نوار ابزار متن
function insertFormat(startTag, endTag) {
    const textarea = document.getElementById('new_desc');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    const replacement = startTag + selectedText + endTag;
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    textarea.focus();
    textarea.selectionStart = start + startTag.length;
    textarea.selectionEnd = end + startTag.length;
}

function insertLink() {
    const url = prompt("آدرس لینک را وارد کنید (مثلا https://google.com):");
    if (url) {
        const textarea = document.getElementById('new_desc');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end) || "متن لینک";
        const replacement = `[${selectedText}](${url})`;
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        textarea.focus();
    }
}

// تابع اشتراک‌گذاری
function shareBlog(title, id) {
    const url = window.location.origin + window.location.pathname.replace('blog.php', '') + 'view_blog.php?id=' + id;
    if (navigator.share) {
        navigator.share({
            title: title,
            url: url
        }).catch(err => console.log('Error sharing:', err));
    } else {
        navigator.clipboard.writeText(title + "\n" + url).then(() => {
            alert('لینک مقاله در کلیپ‌بورد کپی شد.');
        });
    }
}
</script>
<?php include 'footer.php'; ?>

</body>
</html>
<?php ob_end_flush(); ?>
