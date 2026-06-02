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

try { $pdo->exec("ALTER TABLE admin_news ADD COLUMN image VARCHAR(255) NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE admin_news ADD COLUMN timer_start DATETIME NULL"); } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action']) && $_POST['admin_action'] === 'update_news' && $user_role === 'admin') {
    $news_text = strip_tags($_POST['news_text'] ?? '');
    $timer_start = !empty($_POST['timer_start']) ? strip_tags($_POST['timer_start']) : date('Y-m-d H:i:s');
    
    $stmt = $pdo->query("SELECT image FROM admin_news LIMIT 1");
    $current_news = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_image = $current_news ? $current_news['image'] : null;

    if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
        
        if (!file_exists($upload_dir . '.htaccess')) {
            file_put_contents($upload_dir . '.htaccess', "php_flag engine off\nOptions -Indexes");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['news_image']['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $ext = strtolower(pathinfo($_FILES['news_image']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed_exts, true) && in_array($mime_type, $allowed_mimes, true)) {
            $file_name = 'news_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['news_image']['tmp_name'], $target_file)) {
                $new_image = $target_file;
                if (!empty($current_news['image']) && file_exists($current_news['image'])) { @unlink($current_news['image']); }
            }
        }
    }

    $pdo->exec("DELETE FROM admin_news");
    $stmt = $pdo->prepare("INSERT INTO admin_news (description, image, timer_start) VALUES (?, ?, ?)");
    $stmt->execute([$news_text, $new_image, $timer_start]);
    header("Location: home.php");
    exit;
}

$news_stmt = $pdo->query("SELECT description, image, timer_start FROM admin_news ORDER BY id DESC LIMIT 1");
$news_data = $news_stmt->fetch(PDO::FETCH_ASSOC);
$news_content = $news_data['description'] ?? '';
$news_image = $news_data['image'] ?? '';
$timer_start_db = !empty($news_data['timer_start']) ? $news_data['timer_start'] : date('Y-m-d H:i:s', strtotime('-42 days'));

$formatted_news = htmlspecialchars($news_content, ENT_QUOTES, 'UTF-8');
$formatted_news = preg_replace('/\*\*(.*?)\*\*/is', '<strong>$1</strong>', $formatted_news);
$formatted_news = preg_replace('/\\\\\\\[b\\\\\\\](.*?)\\\\\\\[\/b\\\\\\\]/is', '<strong>$1</strong>', $formatted_news);
$formatted_news = preg_replace('/\_(.*?)\_/is', '<em>$1</em>', $formatted_news);
$formatted_news = preg_replace('/\\\\\\\[i\\\\\\\](.*?)\\\\\\\[\/i\\\\\\\]/is', '<em>$1</em>', $formatted_news);
$formatted_news = preg_replace('/\\\\\\\[url=(.*?)\\\\\\\](.*?)\\\\\\\[\/url\\\\\\\]/is', '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:var(--x-blue);text-decoration:underline;">$2</a>', $formatted_news);
$formatted_news = nl2br($formatted_news);

$blogs_stmt = $pdo->query("SELECT b.*, u.name as author_name, u.avatar as author_avatar, u.username FROM blogs b LEFT JOIN users u ON b.writer_id = u.id ORDER BY b.created_at DESC LIMIT 3");
$latest_blogs = $blogs_stmt->fetchAll(PDO::FETCH_ASSOC);

$jozves_stmt = $pdo->query("SELECT j.*, g.name as course_name, u.name as publisher_name, u.avatar as publisher_avatar FROM jozves j JOIN jozve_groups g ON j.group_id = g.id LEFT JOIN users u ON j.user_id = u.id ORDER BY j.created_at DESC LIMIT 3");
$latest_jozves = $jozves_stmt->fetchAll(PDO::FETCH_ASSOC);

$projects_stmt = $pdo->query("
    SELECT p.*, 
           (SELECT image_path FROM project_images pi WHERE pi.project_id = p.id ORDER BY RAND() LIMIT 1) as random_image 
    FROM projects p 
    ORDER BY p.created_at DESC 
    LIMIT 3
");
$latest_projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);


function getImgProject($path, $default_img = 'default-project.png') {
    if (!empty($path)) {
        if (strpos($path, 'http') === 0) return htmlspecialchars($path);
        
        if (file_exists('uploads/' . $path)) return 'uploads/' . htmlspecialchars($path);
        
        if (file_exists($path)) return htmlspecialchars($path);
    }
    return htmlspecialchars($default_img);
}

function fa_num($number) {
    $en = array("0","1","2","3","4","5","6","7","8","9");
    $fa = array("۰","۱","۲","۳","۴","۵","۶","۷","۸","۹");
    return str_replace($en, $fa, (string)$number);
}

function timeAgoPersian($datetime) {
    if(empty($datetime)) return '';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'لحظاتی پیش';
    if ($diff < 3600) return fa_num(round($diff / 60)) . ' دقیقه پیش';
    if ($diff < 86400) return fa_num(round($diff / 3600)) . ' ساعت پیش';
    if ($diff < 2592000) return fa_num(round($diff / 86400)) . ' روز پیش';
    return fa_num(round($diff / 2592000)) . ' ماه پیش';
}


$ic_logo = '<svg viewBox="0 0 24 24" style="width:30px;height:30px;fill:var(--x-blue)"><path d="M12 1L14.5 8.5L22 11L14.5 13.5L12 21L9.5 13.5L2 11L9.5 8.5L12 1Z"></path></svg>';
$ic_telegram = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:#fff"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>';
$ic_bale = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:#fff"><path d="M12 2a10 10 0 0 0-9.95 9A10 10 0 0 0 12 22a10 10 0 0 0 10-10A10 10 0 0 0 12 2zm-2.5 14.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm-2.5-4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/></svg>';
$ic_book = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>';
$ic_uni = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>';
$ic_pen = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
$ic_person = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
$ic_lang = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm6.93 6h-2.95c-.32-1.25-.78-2.45-1.38-3.56 1.84.63 3.37 1.91 4.33 3.56zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14C4.09 13.36 4 12.69 4 12s.09-1.36.26-2h3.38c-.08.66-.14 1.32-.14 2 0 .68.06 1.34.14 2H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56-1.84-.63-3.37-1.9-4.33-3.56zm2.95-8H5.08c-.96 1.66-2.49 2.93-4.33 3.56C6.35 10.45 6.81 9.25 7.13 8zm4.87 11.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66c-.09-.66-.16-1.32-.16-2 0-.68.07-1.34.16-2h4.68c.09.66.16 1.32.16 2 0 .68-.07 1.34-.16 2zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95c-.96 1.65-2.49 2.93-4.33 3.56zM16.36 14c.08-.66.14-1.32.14-2 0-.68-.06-1.34-.14-2h3.38c.17.64.26 1.31.26 2s-.09 1.36-.26 2h-3.38z"/></svg>';
$ic_code = '<svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:var(--x-blue);"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>';
$ic_github = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.462-1.11-1.462-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.379.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.161 22 16.416 22 12c0-5.523-4.477-10-10-10z"/></svg>';
$ic_link = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>آتوکس - خانه داشبورد</title>
<link rel="manifest" href="/manifest.json?v=3">
<meta name="theme-color" content="#1DA1F2">
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.85); --x-hover:rgba(15,20,25,0.05); --x-hover-b:rgba(29,155,240,0.1); --x-modal:rgba(0,0,0,0.4); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.85); --x-hover:rgba(255,255,255,0.05); --x-modal:rgba(255,255,255,0.1); }
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;overflow-y:scroll; overflow-x:hidden;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}
.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.side{width:275px;padding:0 12px;position:sticky;top:0;height:100vh;display:flex;flex-direction:column;align-items:flex-start}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:100px; min-height:100vh;}
.left-side{width:350px;padding:12px 24px;position:sticky;top:0;height:100vh;display:block;}
.btn{background:var(--x-blue);color:#fff;padding:0 32px;border-radius:9999px;font-weight:700;font-size:17px;min-height:52px;width:90%;transition:.2s;margin-top:15px;display:flex;align-items:center;justify-content:center}
.btn:hover{background:#1a8cd8}
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
.compact-timer { display: flex; align-items: center; justify-content: space-between; background: var(--x-bg); border: 1px solid var(--x-border); border-radius: 12px; padding: 14px 16px; margin: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.dark .compact-timer { background: rgba(255,255,255,0.02); }
.ct-status { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 14px; color: var(--x-black); }
.ct-pulse { width: 8px; height: 8px; background: #f91880; border-radius: 50%; box-shadow: 0 0 8px #f91880; animation: ct-pulse 1.5s infinite alternate; }
@keyframes ct-pulse { from { opacity: 0.4; transform: scale(0.8); } to { opacity: 1; transform: scale(1.2); } }
.ct-time { display: flex; align-items: center; gap: 4px; font-family: monospace; font-size: 16px; font-weight: 900; color: #f91880; direction: ltr; }
.ct-box { display: flex; align-items: baseline; gap: 2px; }
.ct-l { font-size: 10px; color: var(--x-gray); font-weight: bold; font-family: sans-serif; text-transform: uppercase; }

.dash-card { background: rgba(29, 155, 240, 0.05); backdrop-filter: blur(16px); border: 1px solid rgba(29, 155, 240, 0.15); border-radius: 20px; margin: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02); }
.dark .dash-card { background: rgba(29, 155, 240, 0.05); border-color: rgba(29, 155, 240, 0.15); }
.news-wrapper { overflow: hidden; display: flex; flex-direction: column; }
.news-header-ui { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--x-border); }
.news-badge { display: flex; align-items: center; gap: 8px; color: #f91880; font-weight: 900; font-size: 16px; }
.news-badge svg { fill: #f91880; width: 22px; height: 22px; animation: pulseRed 2s infinite; }
@keyframes pulseRed { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.1); opacity: 0.7; } 100% { transform: scale(1); opacity: 1; } }
.news-image-box { width: 100%; max-height: 400px; background: #000; border-bottom: 1px solid var(--x-border); }
.news-image-box img { width: 100%; height: 100%; max-height: 400px; object-fit: contain; display: block; }
.news-body { padding: 17px; font-size: 15px; line-height: 1.8; color: var(--x-black); word-wrap: break-word; }
.blogs-section { margin: 24px 0 16px 0; overflow: hidden; }
.b-sec-title { padding: 0 20px; font-size: 18px; font-weight: 900; color: var(--x-black); display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.b-sec-title svg { fill: var(--x-blue); width: 22px; height: 22px; }
.blogs-slider { display: flex; gap: 16px; padding: 0 20px 20px 20px; overflow-x: auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; direction: rtl; }
.blogs-slider::-webkit-scrollbar { display: none; }
.blog-card { min-width: 85%; max-width: 320px; scroll-snap-align: center; background: var(--x-bg); border: 1px solid var(--x-border); border-radius: 20px; overflow: hidden; flex-shrink: 0; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: transform 0.3s ease; position: relative; }
.blog-img { width: 100%; height: 160px; object-fit: cover; border-bottom: 1px solid var(--x-border); }
.blog-content { padding: 16px; display: flex; flex-direction: column; flex-grow: 1; }
.blog-title { font-size: 16px; font-weight: 900; color: var(--x-black); margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.blog-desc { font-size: 14px; color: var(--x-gray); margin-bottom: 16px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.blog-meta { display: flex; align-items: center; justify-content: space-between; margin-top: auto; border-top: 1px dashed var(--x-border); padding-top: 12px; }
.b-author { display: flex; align-items: center; gap: 8px; }
.b-author img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--x-border); }
.b-author-name { font-size: 13px; font-weight: bold; color: var(--x-black); display:block; }
.b-time { font-size: 11px; color: var(--x-gray); }
.btn-read { background: var(--x-blue); color: #fff; padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: bold; }


.j-meta-top { display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap; }
.j-meta-tag { font-size:11px; color:var(--x-gray); background:rgba(15,20,25,0.03); padding:4px 8px; border-radius:8px; display:inline-flex; align-items:center; gap:4px; border:1px solid var(--x-border); font-weight:bold; transition: background 0.2s;}
.j-meta-tag:hover { background: var(--x-hover-b); color: var(--x-blue); border-color: rgba(29, 155, 240, 0.3); }


.project-card { min-width: 85%; max-width: 320px; scroll-snap-align: center; background: var(--x-bg); border: 1px solid var(--x-border); border-radius: 20px; overflow: hidden; flex-shrink: 0; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: transform 0.3s ease, box-shadow 0.3s ease; position: relative; }
.project-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(29,155,240,0.1); }
.project-img-wrapper { position: relative; width: 100%; height: 160px; background: var(--x-hover); border-bottom: 1px solid var(--x-border); }
.project-img { width: 100%; height: 100%; object-fit: cover; }
.project-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 100%); display: flex; align-items: flex-end; padding: 16px; }
.project-title-overlay { color: #fff; font-size: 18px; font-weight: 900; text-shadow: 0 2px 4px rgba(0,0,0,0.5); display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; width: 100%; text-align: right;}
.project-content { padding: 16px; display: flex; flex-direction: column; flex-grow: 1; }
.project-desc { font-size: 14px; color: var(--x-gray); margin-bottom: 16px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height:1.6; }
.project-actions { display: flex; gap: 8px; margin-top: auto; }
.btn-project-link { flex: 1; display:flex; align-items:center; justify-content:center; gap:6px; background: rgba(29,155,240,0.1); color: var(--x-blue); padding: 10px; border-radius: 12px; font-size: 13px; font-weight: bold; transition: 0.2s; border: 1px solid rgba(29,155,240,0.2); }
.btn-project-link:hover { background: var(--x-blue); color: #fff; }
.btn-project-github { flex: 1; display:flex; align-items:center; justify-content:center; gap:6px; background: var(--x-hover); color: var(--x-black); padding: 10px; border-radius: 12px; font-size: 13px; font-weight: bold; transition: 0.2s; border: 1px solid var(--x-border); }
.btn-project-github:hover { background: var(--x-black); color: var(--x-bg); border-color: var(--x-black); }

.pwa-card { background: linear-gradient(135deg, rgba(29,155,240,0.08), rgba(29,155,240,0.02)); border: 1px solid rgba(29, 155, 240, 0.3); border-radius: 20px; padding: 24px 20px; margin: 16px; text-align: center; }
.pwa-title { font-size: 18px; font-weight: 900; color: var(--x-black); margin-bottom: 20px; display:flex; align-items:center; justify-content:center; gap:8px;}
.pwa-title svg { width: 22px; height: 22px; fill: var(--x-black); }
.pwa-actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.btn-direct-dl { background: var(--x-blue); color: #fff; border: none; padding: 12px 24px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; flex: 1; min-width: 180px; justify-content: center;}
.btn-direct-dl svg { width: 20px; height: 20px; fill: currentColor; }
.btn-guide { background: var(--x-bg); border: 1px solid var(--x-blue); color: var(--x-blue); padding: 12px 24px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; flex: 1; min-width: 140px; }
.pwa-link-box { display: flex; align-items: center; justify-content: space-between; background: var(--x-bg); border: 1px dashed var(--x-blue); border-radius: 12px; padding: 8px 12px; direction: ltr; }
.pwa-url { font-family: monospace; font-size: 14px; font-weight:bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 65%; }
.btn-copy { background: var(--x-hover-b); color: var(--x-blue); border: none; padding: 8px 14px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size:13px;}
.contact-section { margin: 30px 16px 40px; }
.contact-title { font-size: 18px; font-weight: 100; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; justify-content: center;}
.contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.contact-card { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px; border-radius: 16px; font-weight: bold; color: #fff; font-size: 15px; text-decoration: none; }
.contact-telegram { background: linear-gradient(135deg, #0088cc, #00aaff); }
.contact-bale { background: linear-gradient(135deg, #0ba360, #3cba92); }
.mod{display:none;position:fixed;inset:0;background:var(--x-modal);z-index:1000;align-items:center;justify-content:center; backdrop-filter:blur(5px);}
.m-c{background:var(--x-bg);border-radius:24px;width:90%;max-width:500px;padding:24px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:p .3s;}
.m-hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--x-border);padding-bottom:15px; margin-bottom:15px;}
.m-c textarea { width:100%; border:1px solid var(--x-border); border-radius:12px; padding:12px; background:transparent; color:var(--x-black); resize:vertical; outline:0; font-size:15px; min-height:120px; margin-bottom:15px; font-family:inherit;}
.m-input { width:100%; padding:12px; border:1px solid var(--x-border); border-radius:12px; background:transparent; color:var(--x-black); margin-bottom:15px; font-family:inherit; direction:ltr;}
.m-file-label { display:flex; align-items:center; justify-content:center; flex-direction:column; gap:8px; padding:24px; background:var(--x-hover-b); color:var(--x-blue); border:2px dashed var(--x-blue); border-radius:12px; text-align:center; cursor:pointer; font-weight:bold; margin-bottom:15px;}
.m-file-input { display:none; }
.input-title { font-size:13px; font-weight:bold; color:var(--x-gray); margin-bottom:8px; display:block; }
.guide-box { margin-bottom: 16px; padding: 16px; border-radius: 16px; text-align: right; line-height: 1.8; font-size: 14px; }
.guide-box-android { background: rgba(23, 191, 99, 0.08); border: 1px dashed rgba(23, 191, 99, 0.4); }
.guide-box-ios { background: rgba(249, 24, 128, 0.08); border: 1px dashed rgba(249, 24, 128, 0.4); }
.guide-title { font-size: 16px; font-weight: 900; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
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
@keyframes p{0%{transform:translateY(30px) scale(0.9);opacity:0}100%{transform:translateY(0) scale(1);opacity:1}}
@media(max-width:1050px){ .left-side{display:none;} }
@media(max-width:600px){ .side{display:none;} .main{border:none; padding-bottom:90px;} }

.twx-toast { position:fixed; bottom:30px; left:50%; transform:translateX(-50%) translateY(100px); background:var(--x-black); color:var(--x-bg); padding:12px 24px; border-radius:99px; font-weight:bold; font-size:14px; opacity:0; transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index:999999; box-shadow:0 4px 15px rgba(0,0,0,0.2); pointer-events:none; text-align:center;}
.twx-toast.show { transform:translateX(-50%) translateY(0); opacity:1; }
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
                <a href="home.php" class="m-tab-btn active">
                    داشبورد و اعلانات
                    <div class="m-tab-indicator"></div>
                </a>
                <a href="home_rate.php" class="m-tab-btn">
                    رده‌بندی
                    <div class="m-tab-indicator"></div>
                </a>
            </div>
        </div>
        
        <div id="feed-dash" class="feed-content">
            <div class="compact-timer">
                <div class="ct-status"><span class="ct-pulse"></span> زمان اختلال:</div>
                <div class="ct-time">
                    <span class="ct-box"><span id="t-days">00</span><span class="ct-l">d</span></span>:
                    <span class="ct-box"><span id="t-hours">00</span><span class="ct-l">h</span></span>:
                    <span class="ct-box"><span id="t-mins">00</span><span class="ct-l">m</span></span>:
                    <span class="ct-box"><span id="t-secs">00</span><span class="ct-l">s</span></span>
                </div>
            </div>

            <?php if (!empty($news_content) || !empty($news_image) || $user_role === 'admin'): ?>
            <div class="dash-card news-wrapper">
                <div class="news-header-ui">
                    <div class="news-badge"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg> آخرین آپدیت ها</div>
                    <?php if($user_role === 'admin'): ?><button onclick="oM('newsM')" style="font-size:12px; background:var(--x-hover-b); color:var(--x-blue); padding:6px 12px; border-radius:16px; font-weight:bold; border:1px solid rgba(29, 155, 240, 0.3);">ویرایش</button><?php endif; ?>
                </div>
                <?php if(!empty($news_image) && file_exists($news_image)): ?><div class="news-image-box"><img src="<?=htmlspecialchars($news_image, ENT_QUOTES, 'UTF-8')?>" alt="News Image"></div><?php endif; ?>
                <?php if(!empty($news_content)): ?><div class="news-body"><?= $formatted_news ?></div><?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if(!empty($latest_projects)): ?>
<div class="blogs-section">
    <div class="b-sec-title"><?=$ic_code?> آخرین پروژه‌ها</div>
    <div class="blogs-slider">
        <?php foreach($latest_projects as $project): 
            $p_img = getImgProject($project['random_image'], 'default-project.png');
            $p_name = htmlspecialchars($project['name'] ?? 'بدون نام', ENT_QUOTES, 'UTF-8');
            $p_desc = htmlspecialchars(strip_tags($project['description'] ?? 'توضیحاتی ثبت نشده است.'), ENT_QUOTES, 'UTF-8');
            $p_id = htmlspecialchars($project['id'], ENT_QUOTES, 'UTF-8');
            $p_link = htmlspecialchars($project['project_link'] ?? '', ENT_QUOTES, 'UTF-8');
            $p_github = htmlspecialchars($project['github_link'] ?? '', ENT_QUOTES, 'UTF-8');
        ?>
        <div class="project-card" onclick="window.location.href='project.php?id=<?=$p_id?>'" style="cursor: pointer;">
            <div class="project-img-wrapper">
                <img src="<?=$p_img?>" class="project-img" alt="<?=$p_name?>">
                <div class="project-overlay">
                    <div class="project-title-overlay"><?=$p_name?></div>
                </div>
            </div>
            <div class="project-content">
                <div class="project-desc"><?=$p_desc?></div>
                <div class="project-actions">
                    <?php if(!empty($p_link)): ?>
                    <a href="<?=$p_link?>" target="_blank" class="btn-project-link" onclick="event.stopPropagation();"><?=$ic_link?> مشاهده سایت</a>
                    <?php endif; ?>
                    <?php if(!empty($p_github)): ?>
                    <a href="<?=$p_github?>" target="_blank" class="btn-project-github" onclick="event.stopPropagation();"><?=$ic_github?> گیت‌هاب</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

            <?php if(!empty($latest_jozves)): ?>
            <div class="blogs-section">
                <div class="b-sec-title"><svg viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg> آخرین جزوات منتشر شده</div>
                <div class="blogs-slider">
                    <?php foreach($latest_jozves as $jozve): 
                        $j_pub_av = !empty($jozve['publisher_avatar']) ? htmlspecialchars($jozve['publisher_avatar'], ENT_QUOTES, 'UTF-8') : 'default-avatar.png';
                        $j_pub_nm = !empty($jozve['publisher_name']) ? htmlspecialchars($jozve['publisher_name'], ENT_QUOTES, 'UTF-8') : 'مدیریت';
                    ?>
                    <a href="magazine.php?id=<?=htmlspecialchars($jozve['id'], ENT_QUOTES, 'UTF-8')?>" class="blog-card" style="padding:16px; justify-content:space-between;">
                        <div>
                            <div class="j-meta-top">
                                <span class="j-meta-tag"><?=$ic_book ?? '📚'?> <?=htmlspecialchars($jozve['course_name'] ?: 'نامشخص', ENT_QUOTES, 'UTF-8')?></span>
                                <?php if(!empty($jozve['university_name'])): ?><span class="j-meta-tag"><?=$ic_uni ?? '🎓'?> <?=htmlspecialchars($jozve['university_name'], ENT_QUOTES, 'UTF-8')?></span><?php endif; ?>
                                <?php if(!empty($jozve['language'])): ?><span class="j-meta-tag"><?=$ic_lang ?? '🌐'?> <?=htmlspecialchars($jozve['language'], ENT_QUOTES, 'UTF-8')?></span><?php endif; ?>
                                <?php if(!empty($jozve['author_name'])): ?><span class="j-meta-tag"><?=$ic_person ?? '👤'?> <?=htmlspecialchars($jozve['author_name'], ENT_QUOTES, 'UTF-8')?></span><?php endif; ?>
                                <span class="j-meta-tag" style="color:<?=($jozve['is_handwritten']??0)?'#e0245e':'#17bf63'?>; border-color:<?=($jozve['is_handwritten']??0)?'rgba(224,36,94,0.3)':'rgba(23,191,99,0.3)'?>;"><?=$ic_pen ?? '✒️'?> <?=($jozve['is_handwritten']??0)?'دست‌نویس':'تایپ و چاپی'?></span>
                            </div>
                            <div class="blog-desc" style="margin-top:12px;"><?=htmlspecialchars(strip_tags($jozve['description'] ?: ''), ENT_QUOTES, 'UTF-8')?></div>
                        </div>
                        <div class="blog-meta" style="margin-top:12px;">
                            <div class="b-author"><img src="<?=$j_pub_av?>"><div class="b-author-name"><?=$j_pub_nm?><br><span class="b-time"><?=timeAgoPersian($jozve['created_at'])?></span></div></div>
                            <span class="btn-read">مشاهده</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if(!empty($latest_blogs)): ?>
            <div class="blogs-section">
                <div class="b-sec-title"><svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg> آخرین مقالات</div>
                <div class="blogs-slider">
                    <?php foreach($latest_blogs as $blog): 
                        $b_img = !empty($blog['image']) ? htmlspecialchars($blog['image'], ENT_QUOTES, 'UTF-8') : 'default-blog.png';
                        $b_aut_av = !empty($blog['author_avatar']) ? htmlspecialchars($blog['author_avatar'], ENT_QUOTES, 'UTF-8') : 'default-avatar.png';
                        $b_aut_nm = !empty($blog['author_name']) ? htmlspecialchars($blog['author_name'], ENT_QUOTES, 'UTF-8') : 'ناشناس';
                    ?>
                    <a href="view_blog.php?id=<?=htmlspecialchars($blog['id'], ENT_QUOTES, 'UTF-8')?>" class="blog-card">
                        <img src="<?=$b_img?>" class="blog-img">
                        <div class="blog-content">
                            <div class="blog-title"><?=htmlspecialchars($blog['title'], ENT_QUOTES, 'UTF-8')?></div>
                            <div class="blog-desc"><?=htmlspecialchars(strip_tags($blog['description']), ENT_QUOTES, 'UTF-8')?></div>
                            <div class="blog-meta">
                                <div class="b-author"><img src="<?=$b_aut_av?>"><div class="b-author-name"><?=$b_aut_nm?><br><span class="b-time"><?=timeAgoPersian($blog['created_at'])?></span></div></div>
                                <span class="btn-read">مطالعه</span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="pwa-card">
                <div class="pwa-title"><svg viewBox="0 0 24 24"><path d="M17 1H7c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-2-2-2zm0 18H7V5h10v14zM8 6h8v2H8V6zm0 4h8v2H8v-2zm0 4h5v2H8v-2z"/></svg> اپلیکیشن آتوکس</div>
                <div class="pwa-actions">
                    <button id="btn-pwa-install" class="btn-direct-dl"><svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg> نصب مستقیم</button>
                    <button class="btn-guide" onclick="oM('pwaGuideModal')">راهنمای دستی</button>
                </div>
                <div class="pwa-link-box">
                    <button class="btn-copy" onclick="copyPwaLink()">کپی لینک</button>
                    <span class="pwa-url" id="pwaUrlDisplay"></span>
                </div>
            </div>

            <div class="contact-section">
                <div class="contact-title"> ارتباط با آتوکس</div>
                <div class="contact-grid">
                    <a href="https://t.me/miladbasery" target="_blank" class="contact-card contact-telegram"><?=$ic_telegram ?? '💬'?> تلگرام</a>
                    <a href="https://ble.ir/miladxd" target="_blank" class="contact-card contact-bale"><?=$ic_bale ?? '💬'?> بله</a>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="twx-toast" class="twx-toast">لینک با موفقیت کپی شد</div>

<div id="pwaGuideModal" class="mod"><div class="m-c"><div class="m-hdr"><h2>راهنمای نصب دستی اپلیکیشن</h2><button onclick="tgM('pwaGuideModal')" style="font-size:24px;color:var(--x-black);border-radius:50%;padding:4px 8px;background:none;">✕</button></div>
    <div class="guide-box guide-box-android">
        <div class="guide-title">نصب در اندروید (Google Chrome)</div>
        ۱. در مرورگر کروم، روی آیکون <b>سه نقطه (⋮)</b> در گوشه بالا کلیک کنید.<br>
        ۲. گزینه <b>Add to Home screen</b> (افزودن به صفحه اصلی) را انتخاب کنید.<br>
        ۳. در پنجره باز شده، روی دکمه <b>Add</b> کلیک کنید.
    </div>
    <div class="guide-box guide-box-ios">
        <div class="guide-title">نصب در آیفون (Safari)</div>
        ۱. در مرورگر سافاری، روی آیکون <b>اشتراک‌گذاری</b> (مربع با فلش رو به بالا) در پایین صفحه کلیک کنید.<br>۲. منو را به پایین بکشید و گزینه <b>Add to Home Screen</b> را انتخاب کنید.<br>
        ۳. در گوشه بالا سمت راست، روی <b>Add</b> کلیک کنید.
    </div>
</div></div>

<div id="twx-info-modal" class="twx-modal-overlay" onclick="closeTwxInfoModal()"><div class="twx-modal-box" onclick="event.stopPropagation()"><div class="twx-header"><button type="button" class="twx-close" onclick="closeTwxInfoModal()">×</button><div class="twx-title">راهنمای صفحه</div></div><div class="twx-body"><b><?=htmlspecialchars($current_user_name, ENT_QUOTES, 'UTF-8')?> عزیز،</b><br>به خانه آتوکس خوش آمدید. از طریق تب‌های بالا می‌توانید بین "داشبورد" و "رده‌بندی" جابجا شوید.<br><br>- در فید رده‌بندی، با کلیک روی تب‌های شیشه‌ای می‌توانید کاربران برتر را بر اساس امتیاز، میزان فعالیت (توییت) و محبوبیت (لایک) مشاهده کنید.<br>- با کلیک روی هر کاربر، به پروفایل اختصاصی او منتقل می‌شوید.</div><div class="twx-footer"><button type="button" class="twx-btn-save" onclick="closeTwxInfoModal()">متوجه شدم</button></div></div></div>

<?php if($user_role === 'admin'): ?>
<div id="newsM" class="mod"><div class="m-c"><div class="m-hdr"><h2>مدیریت</h2><button onclick="tgM('newsM')" style="font-size:24px;border-radius:50%;padding:4px 8px;background:none;">✕</button></div>
    <form action="home.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="admin_action" value="update_news">
        <span class="input-title">متن اخبار:</span>
        <textarea name="news_text" required><?=htmlspecialchars($news_content, ENT_QUOTES, 'UTF-8')?></textarea>
        <span class="input-title">تصویر:</span>
        <label class="m-file-label" id="file-label-box"><span id="file-label-text">انتخاب عکس</span><input type="file" name="news_image" accept="image/jpeg, image/png, image/webp, image/gif" class="m-file-input" onchange="document.getElementById('file-label-text').innerHTML=this.files[0].name;"></label>
        <span class="input-title">شروع تایمر:</span>
<input type="datetime-local" name="timer_start" class="m-input" value="<?=date('Y-m-d\TH:i', strtotime($timer_start_db))?>" required>
        <button class="btn" style="width:100%;">ثبت</button>
    </form>
</div></div>
<?php endif; ?>

<script>
const tgM=i=>{document.getElementById(i).style.display='none'};
const oM=i=>document.getElementById(i).style.display='flex';
window.onclick=e=>{if(e.target.classList.contains('mod'))e.target.style.display='none';};
function openTwxInfoModal(e) { if(e) e.stopPropagation(); const m = document.getElementById('twx-info-modal'); m.style.display='flex'; setTimeout(()=>m.classList.add('active'), 10); }
function closeTwxInfoModal() { const m = document.getElementById('twx-info-modal'); m.classList.remove('active'); setTimeout(()=>m.style.display='none', 200); }

const outageStartDate = new Date("<?=date('Y-m-d\TH:i:s', strtotime($timer_start_db))?>");
const faNum = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
function updateOutageTimer() {
    const now = new Date(); 
    let diff;
    if (outageStartDate > now) {
        diff = outageStartDate - now;
    } else {
        diff = now - outageStartDate;
    }
    
    document.getElementById('t-days').innerText = faNum(String(Math.floor(diff/(1000*60*60*24))).padStart(2,'0'));
    document.getElementById('t-hours').innerText = faNum(String(Math.floor((diff/(1000*60*60))%24)).padStart(2,'0'));
    document.getElementById('t-mins').innerText = faNum(String(Math.floor((diff/1000/60)%60)).padStart(2,'0'));
    document.getElementById('t-secs').innerText = faNum(String(Math.floor((diff/1000)%60)).padStart(2,'0'));
}
setInterval(updateOutageTimer, 1000); updateOutageTimer();

const currentDomain = window.location.origin; 
document.getElementById('pwaUrlDisplay').innerText = currentDomain;

function copyPwaLink() { 
    navigator.clipboard.writeText(currentDomain).then(() => { 
        const toast = document.getElementById('twx-toast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }); 

}

let deferredPrompt; 
window.addEventListener('beforeinstallprompt', (e) => { 
    e.preventDefault(); 
    deferredPrompt = e; 
});

document.getElementById('btn-pwa-install').addEventListener('click', async () => { 
    if(deferredPrompt){ 
        deferredPrompt.prompt(); 
        deferredPrompt=null; 
    } else { 
        oM('pwaGuideModal'); 
    } 
});
</script>
<?php include 'footer.php'; ?>

</body>
</html>
<?php ob_end_flush(); ?>
