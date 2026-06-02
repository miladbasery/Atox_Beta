<?php
require 'db.php';
date_default_timezone_set('Asia/Tehran');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blog_id INT NOT NULL,
        user_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("ALTER TABLE blogs ADD COLUMN views_count INT DEFAULT 0");
} catch (PDOException $e) {}

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'user';

$is_admin = ($user_role === 'admin' || $user_role == 1 || (is_numeric($user_role) && $user_role >= 20));

$blog_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['blog_id']) ? intval($_POST['blog_id']) : 0);

if ($blog_id === 0) {
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif; background:var(--x-bg); color:var(--x-black);'>مقاله مورد نظر یافت نشد. <a href='blog.php' style='color:var(--x-blue);'>بازگشت</a></div>");
}

try {
    $stmt = $pdo->prepare("SELECT b.*, u.name, u.username, u.avatar, u.is_verified FROM blogs b LEFT JOIN users u ON b.writer_id = u.id WHERE b.id = ?");
    $stmt->execute([$blog_id]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$blog) {
        die("<div style='text-align:center; padding: 50px; color:var(--x-black);'>مقاله وجود ندارد. <a href='blog.php'>بازگشت</a></div>");
    }
} catch (PDOException $e) {
    die("خطای دیتابیس");
}

$can_manage_blog = ($is_admin || ($user_id > 0 && $user_id == $blog['writer_id']));

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

        if ($_POST['action'] === 'edit_blog' && $can_manage_blog) {
            $e_title = trim($_POST['title']);
            $e_desc = trim($_POST['description']);
            $e_tags = trim($_POST['tags']);

            $update_query = "UPDATE blogs SET title = ?, description = ?, tags = ? WHERE id = ?";
            $params = [$e_title, $e_desc, $e_tags, $blog_id];

            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

                $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($file_extension, $allowed) && $_FILES["image"]["size"] <= 2000000) {
                    $new_filename = uniqid('blog_') . '.' . $file_extension;
                    $target_file = $target_dir . $new_filename;

                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                        $update_query = "UPDATE blogs SET title = ?, description = ?, tags = ?, image = ? WHERE id = ?";
                        $params = [$e_title, $e_desc, $e_tags, $target_file, $blog_id];
                    }
                }
            }

            $pdo->prepare($update_query)->execute($params);
            header("Location: view_blog.php?id=" . $blog_id);
            exit;
        }

        if ($_POST['action'] === 'delete_blog' && $can_manage_blog) {
            $pdo->prepare("DELETE FROM blog_comments WHERE blog_id = ?")->execute([$blog_id]);
            $pdo->prepare("DELETE FROM blog_likes WHERE blog_id = ?")->execute([$blog_id]);
            $pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$blog_id]);
            header("Location: blog.php");
            exit;
        }

        if ($user_id === 0 && $_POST['action'] !== 'like') {
            die("برای این عملیات باید وارد حساب کاربری شوید.");
        }

        if ($_POST['action'] === 'like') {
            if ($user_id === 0) {
                echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد حساب شوید.']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id FROM blog_likes WHERE user_id = ? AND blog_id = ?");
            $stmt->execute([$user_id, $blog_id]);

            if ($stmt->rowCount() > 0) {
                $pdo->prepare("DELETE FROM blog_likes WHERE user_id = ? AND blog_id = ?")->execute([$user_id, $blog_id]);
                $pdo->prepare("UPDATE blogs SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?")->execute([$blog_id]);
                $action_type = 'unliked';
            } else {
                $pdo->prepare("INSERT INTO blog_likes (user_id, blog_id) VALUES (?, ?)")->execute([$user_id, $blog_id]);
                $pdo->prepare("UPDATE blogs SET likes_count = likes_count + 1 WHERE id = ?")->execute([$blog_id]);
                $action_type = 'liked';
            }

            $new_count = $pdo->query("SELECT likes_count FROM blogs WHERE id = $blog_id")->fetchColumn();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'action' => $action_type, 'new_count' => $new_count]);
            exit;
        }

        if ($_POST['action'] === 'comment' && !empty(trim($_POST['comment_text']))) {
            $comment_text = trim($_POST['comment_text']);
            $current_time = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO blog_comments (blog_id, user_id, comment, created_at) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$blog_id, $user_id, $comment_text, $current_time])) {
                $pdo->prepare("UPDATE blogs SET comments_count = comments_count + 1 WHERE id = ?")->execute([$blog_id]);
            }
            header("Location: view_blog.php?id=" . $blog_id . "#comments");
            exit;
        }

        if ($_POST['action'] === 'delete_comment' && isset($_POST['comment_id'])) {
            $comment_id = intval($_POST['comment_id']);
            $c_owner = $pdo->query("SELECT user_id FROM blog_comments WHERE id = $comment_id")->fetchColumn();

            if ($c_owner == $user_id || $is_admin) {
                $pdo->prepare("DELETE FROM blog_comments WHERE id = ?")->execute([$comment_id]);
                $pdo->prepare("UPDATE blogs SET comments_count = GREATEST(comments_count - 1, 0) WHERE id = ?")->execute([$blog_id]);
            }
            header("Location: view_blog.php?id=" . $blog_id . "#comments");
            exit;
        }

        if ($_POST['action'] === 'edit_comment' && isset($_POST['comment_id']) && !empty(trim($_POST['edit_text']))) {
            $comment_id = intval($_POST['comment_id']);
            $edit_text = trim($_POST['edit_text']);
            $c_owner = $pdo->query("SELECT user_id FROM blog_comments WHERE id = $comment_id")->fetchColumn();

            if ($c_owner == $user_id || $is_admin) {
                $pdo->prepare("UPDATE blog_comments SET comment = ? WHERE id = ?")->execute([$edit_text, $comment_id]);
            }
            header("Location: view_blog.php?id=" . $blog_id . "#comments");
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['cpage'])) {
        $pdo->prepare("UPDATE blogs SET views_count = views_count + 1 WHERE id = ?")->execute([$blog_id]);
        $blog['views_count'] = ($blog['views_count'] ?? 0) + 1;
    }

    $has_liked = false;
    if ($user_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM blog_likes WHERE user_id = ? AND blog_id = ?");
        $stmt->execute([$user_id, $blog_id]);
        $has_liked = $stmt->rowCount() > 0;
    }

    $c_page = isset($_GET['cpage']) ? max(1, intval($_GET['cpage'])) : 1;
    $c_limit = 10;
    $c_offset = ($c_page - 1) * $c_limit;

    $total_comments_stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_comments WHERE blog_id = ?");
    $total_comments_stmt->execute([$blog_id]);
    $total_comments = $total_comments_stmt->fetchColumn();
    $total_pages = ceil($total_comments / $c_limit);

    $stmt = $pdo->prepare("SELECT c.*, u.name, u.username, u.avatar, u.is_verified FROM blog_comments c JOIN users u ON c.user_id = u.id WHERE c.blog_id = ? ORDER BY c.created_at DESC LIMIT $c_limit OFFSET $c_offset");
    $stmt->execute([$blog_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div style='direction:rtl; padding:20px; color:red;'>خطای دیتابیس: " . $e->getMessage() . "</div>");
}

function getJalaliDateTime($datetime_string) {
    try {
        $date = new DateTime($datetime_string);
        $formatter = new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::FULL, IntlDateFormatter::SHORT, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL, 'd MMMM yyyy / HH:mm');
        return $formatter->format($date);
    } catch (Exception $e) { return $datetime_string; }
}

function formatCounts($num) {
    if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
    if ($num >= 1000) return round($num / 1000, 1) . 'K';
    return $num;
}

function parseRichText($text) {
    if (empty($text)) return '';

    $codeBlocks = [];

    $text = preg_replace_callback('/[\s\r\n]*\x60{3}([a-zA-Z0-9\+#\-]+)?[\r\n]+(.*?)\x60{3}[\s\r\n]*/is', function($matches) use (&$codeBlocks) {
        $id = '%%CODEBLOCK_' . count($codeBlocks) . '%%';
        $lang = !empty($matches[1]) ? strtolower(trim($matches[1])) : '';
        $display_lang = $lang ? strtoupper($lang) : 'CODE';

        $raw_code = trim($matches[2]);
        while (preg_match('/&[a-z]+;/', $raw_code)) {
            $raw_code = html_entity_decode($raw_code, ENT_QUOTES, 'UTF-8');
        }
        $code = htmlspecialchars($raw_code, ENT_QUOTES, 'UTF-8');
        $lang_class = $lang ? 'language-'.$lang : '';

        $html = '<div class="tw-code-box" dir="ltr"><div class="tw-code-header"><span class="tw-code-lang">' . $display_lang . '</span><button type="button" class="tw-code-copy" onclick="copyTwCode(this, event)">کپی</button></div><div class="tw-code-body"><pre><code class="'.$lang_class.' hljs">' . $code . '</code></pre></div></div>';

        $codeBlocks[$id] = $html;
        return "\n\n" . $id . "\n\n";
    }, $text);

    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $text = preg_replace('/^# (.*?)$/m', '<h2 class="art-h1">$1</h2>', $text);
    $text = preg_replace('/^## (.*?)$/m', '<h3 class="art-h2">$1</h3>', $text);

    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong style="color:var(--x-black);font-weight:800;">$1</strong>', $text);
    $text = preg_replace('/(?<!\w)\*(.*?)\*(?!\w)/s', '<em>$1</em>', $text);

    $text = preg_replace_callback('/\\\\?\[(.*?)\\\\?\]\\\\?\((.*?)\\\\?\)/', function($matches) {
        $link_text = $matches[1];
        $url = trim($matches[2]);
        if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = "https://" . $url;
        }
        return '<a href="' . $url . '" target="_blank" rel="nofollow" class="art-link">' . $link_text . '</a>';
    }, $text);

    $text = str_replace("\r\n", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text); 

    $blocks = explode("\n\n", $text);
    $out = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;

        if (preg_match('/^(<h[23]|%%CODEBLOCK_)/', $block)) {
            $out .= $block . "\n";
        } else {
            $block = nl2br($block);
            $out .= '<p class="art-p">' . $block . "</p>\n";
        }
    }
    $text = $out;

    foreach ($codeBlocks as $id => $html) {
        $text = str_replace($id, $html, $text);
    }

    return $text;
}

$tags = !empty($blog['tags']) ? explode(',', $blog['tags']) : [];
$author_name = !empty($blog['name']) ? $blog['name'] : (!empty($blog['username']) ? $blog['username'] : 'کاربر');
$author_avatar = !empty($blog['avatar']) ? $blog['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($author_name);
$blue_tick = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo htmlspecialchars($blog['title']); ?></title>

<!-- استایل گیت‌هاب برای رنگی کردن کدها -->
<link rel="stylesheet" href="assets/github-dark.min.css">

<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { --x-bg: #ffffff; --x-black: #0f1419; --x-gray: #536471; --x-border: #eff3f4; --x-blue: #1d9bf0; --x-red: #f91880; --x-green: #00ba7c; --x-hover: rgba(15,20,25,0.05); --x-bg-trans: rgba(255,255,255,0.85); --x-comment-bg: #f7f9f9; }
.dark { --x-bg: #000000; --x-black: #e7e9ea; --x-gray: #71767b; --x-border: #2f3336; --x-hover: rgba(255,255,255,0.05); --x-bg-trans: rgba(0,0,0,0.85); --x-comment-bg: #16181c; }

body { background-color: var(--x-bg); color: var(--x-black); font-family: -apple-system, system-ui, sans-serif; margin: 0; padding-bottom: 80px; }
a, button { text-decoration: none; color: inherit; background: 0 0; border: 0; cursor: pointer; outline: 0; }

.app { display: flex; justify-content: center; min-height: 100vh; max-width: 1250px; margin: 0 auto; }
.main-container { width: 100%; max-width: 600px; min-height: 100vh; display: flex; flex-direction: column; background: var(--x-bg); }

.hdr { padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: var(--x-bg-trans); backdrop-filter: blur(12px); z-index: 10; border-bottom: 1px solid var(--x-border); }
.hdr-left { display: flex; align-items: center; gap: 15px; flex: 1; min-width: 0; }
.btn-back { color: var(--x-black); font-size: 20px; font-weight: bold; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.2s; flex-shrink: 0; }
.btn-back:hover { background: var(--x-hover); }
.hdr h2 { margin: 0; font-size: 18px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--x-black); }

.post-controls { display: flex; gap: 8px; flex-shrink: 0; }
.pc-btn { display: flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; transition: 0.2s; }
.pc-edit { background: rgba(29, 155, 240, 0.1); color: var(--x-blue); }
.pc-edit:hover { background: rgba(29, 155, 240, 0.2); }
.pc-delete { background: rgba(249, 24, 128, 0.1); color: var(--x-red); }
.pc-delete:hover { background: rgba(249, 24, 128, 0.2); }

.hero-image-wrapper { margin: 16px; width: calc(100% - 32px); aspect-ratio: 16/9; background: var(--x-hover); position: relative; overflow: hidden; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
.hero-image-wrapper img { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; }

.article-body { padding: 24px 20px; }


.article-title { 
    font-size: clamp(24px, 6vw, 32px); 
    font-weight: 950; 
    margin: 0 0 24px 0; 
    line-height: 1.5; 
    color: var(--x-black); 
    letter-spacing: -0.5px; 
    text-align: right; 
    word-wrap: break-word; 
}

.article-meta-wrapper { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--x-hover); padding-bottom: 16px; margin-bottom: 24px; }
.article-meta { display: flex; align-items: center; gap: 14px; }
.author-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; background: var(--x-hover); border: 1px solid var(--x-border); }
.meta-details { display: flex; flex-direction: column; justify-content: center; }
.meta-name { font-weight: 800; font-size: 15px; color: var(--x-black); display: flex; align-items: center; transition: 0.2s; }
.meta-name:hover { color: var(--x-blue); text-decoration: underline; }
.meta-date { font-size: 13px; color: var(--x-gray); margin-top: 4px; }

.btn-title-share { background: var(--x-hover); border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; color: var(--x-gray); transition: 0.2s; flex-shrink: 0; }
.btn-title-share:hover { color: var(--x-green); background: rgba(0, 186, 124, 0.1); }


.article-text { font-size: 17px; line-height: 1.9; color: var(--x-black); margin-bottom: 30px; text-align: justify; text-justify: inter-word; word-wrap: break-word; }
.art-p { margin-top: 0; margin-bottom: 1.2em; }
.art-h1 { font-size: clamp(20px, 5vw, 24px); font-weight: 800; margin: 30px 0 16px 0; color: var(--x-black); }
.art-h2 { font-size: clamp(18px, 4vw, 20px); font-weight: 800; margin: 24px 0 12px 0; color: var(--x-black); }
.art-link { color: var(--x-blue); text-decoration: underline; transition: 0.2s; }
.art-link:hover { opacity: 0.8; }


.tw-code-box { background: #0d1117; border: 1px solid var(--x-border); border-radius: 12px; margin: 20px 0; overflow: hidden; font-family: monospace; direction: ltr; text-align: left; }
.tw-code-header { display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.05); padding: 8px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.tw-code-lang { font-size: 13px; font-weight: bold; color: #8b949e; text-transform: uppercase; }
.tw-code-copy { background: transparent; border: 1px solid #30363d; color: #c9d1d9; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight:bold; font-family: sans-serif; cursor: pointer; transition: 0.2s; }
.tw-code-copy:hover { background: #30363d; color: #ffffff; }
.tw-code-body { padding: 0; overflow-x: auto; }
.tw-code-body pre { margin: 0; }
.tw-code-body code { display: block; font-size: 14.5px; line-height: 1.6; padding: 16px; }

.tags-container { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; padding-top: 10px; border-top: 1px solid var(--x-hover); }
.tag-chip { background: var(--x-hover); color: var(--x-gray); padding: 6px 14px; border-radius: 999px; font-size: 14px; font-weight: 600; transition: 0.2s; }
.tag-chip:hover { background: rgba(29, 155, 240, 0.1); color: var(--x-blue); }

.action-bar { display: flex; border-top: 1px solid var(--x-border); border-bottom: 1px solid var(--x-border); padding: 8px 16px; justify-content: space-around; background: var(--x-bg); }
.action-btn { display: flex; align-items: center; gap: 8px; color: var(--x-gray); font-size: 15px; font-weight: 600; transition: 0.2s; padding: 10px; border-radius: 999px; cursor: pointer; }
.action-btn:hover { background: var(--x-hover); color: var(--x-blue); }
.action-btn svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.action-btn.like-btn:hover { background: rgba(249, 24, 128, 0.1); color: var(--x-red); }
.action-btn.like-btn.liked { color: var(--x-red); }
.action-btn.like-btn.liked svg path { fill: currentColor; }

.action-btn.share-btn:hover { background: rgba(0, 186, 124, 0.1); color: var(--x-green); }

.comments-section { padding: 24px 20px; flex: 1; scroll-margin-top: 60px; }
.comments-title { font-size: 20px; font-weight: 900; margin-bottom: 24px; color: var(--x-black); }

.comment-form { display: flex; gap: 12px; margin-bottom: 30px; align-items: flex-start; }
.comment-input-wrapper { flex: 1; }
.comment-input { width: 100%; box-sizing: border-box; border: 1px solid var(--x-border); padding: 14px; border-radius: 16px; font-size: 15px; resize: none; outline: none; transition: 0.2s; font-family: inherit; background: var(--x-bg); color: var(--x-black); }
.comment-input:focus { border-color: var(--x-blue); box-shadow: 0 0 0 1px var(--x-blue); }
.btn-submit-comment { background: var(--x-black); color: var(--x-bg); border: none; padding: 10px 24px; border-radius: 999px; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 12px; float: left; transition: 0.2s; }
.btn-submit-comment:hover { opacity: 0.8; }

.comment-item { display: flex; gap: 12px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--x-border); }
.comment-item:last-child { border-bottom: none; }
.comment-item .avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; background: var(--x-hover); }
.comment-body { flex: 1; }
.comment-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.comment-name-wrap { display: flex; align-items: center; gap: 8px; }
.comment-name { font-weight: 800; font-size: 15px; color: var(--x-black); margin: 0; transition: 0.2s;}
.comment-name:hover { color: var(--x-blue); text-decoration: underline; }
.comment-date { font-size: 13px; color: var(--x-gray); }
.comment-text { font-size: 15px; margin: 0 0 10px 0; line-height: 1.6; color: var(--x-black); }
.comment-actions { display: flex; gap: 12px; }
.c-btn { font-size: 13px; font-weight: 600; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: 0.2s; }
.btn-edit { color: var(--x-gray); background: var(--x-hover); }
.btn-edit:hover { background: var(--x-border); color: var(--x-blue); }
.btn-del { color: var(--x-red); background: rgba(249,24,128,0.1); }
.btn-del:hover { background: rgba(249,24,128,0.2); }


.pagination { display: flex; justify-content: center; gap: 8px; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--x-border); flex-wrap: wrap;}
.page-link { display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 8px; font-size: 14px; font-weight: 600; color: var(--x-gray); background: var(--x-hover); transition: 0.2s; border: 1px solid transparent; }
.page-link:hover { background: var(--x-border); color: var(--x-blue); }
.page-link.active { background: var(--x-blue); color: #fff; border-color: var(--x-blue); pointer-events: none; }


.mod { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:1000; align-items:center; justify-content:center; backdrop-filter:blur(4px); overflow-y: auto; padding: 20px; }
.m-c { background:var(--x-bg); border-radius:24px; width:100%; max-width:450px; padding:30px 24px; box-shadow:0 10px 40px rgba(0,0,0,.2); margin: auto; }
.m-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto; }
.m-icon.red { background: rgba(249, 24, 128, 0.1); color: var(--x-red); }
.input-group { margin-bottom: 15px; }
.input-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: bold; color: var(--x-gray); }


@media (max-width: 600px) {
    .article-body { padding: 20px 16px; }
    .hdr { padding: 10px 12px; }
    .tw-code-body code { font-size: 13px; padding: 12px; }
}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="app">

<div class="main-container">

<div class="hdr">
<div class="hdr-left">
<a href="blog.php" class="btn-back" onclick="event.stopPropagation()" style="background:0 0; border:0; cursor:pointer; color:inherit; display:flex;">
<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"></path></svg>
</a>
<h2><?php echo htmlspecialchars($blog['title']); ?></h2>
</div>

<?php if ($can_manage_blog): ?>
<div class="post-controls">
<button type="button" class="pc-btn pc-edit" onclick="document.getElementById('editBlogMod').style.display='flex'">
<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
ویرایش
</button>

<button type="button" class="pc-btn pc-delete" onclick="document.getElementById('delBlogMod').style.display='flex'">
<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
حذف
</button>
</div>
<?php endif; ?>
</div>

<?php if (!empty($blog['image'])): ?>
<div class="hero-image-wrapper">
<img src="<?php echo htmlspecialchars($blog['image']); ?>" alt="کاور" loading="lazy">
</div>
<?php endif; ?>

<div class="article-body">
<h1 class="article-title">
<?php echo htmlspecialchars($blog['title']); ?>
</h1>

<div class="article-meta-wrapper">
<div class="article-meta">
<a href="profile.php?id=<?php echo $blog['writer_id']; ?>">
<img src="<?php echo htmlspecialchars($author_avatar); ?>" class="author-avatar" alt="Avatar">
</a>
<div class="meta-details">
<a href="profile.php?id=<?php echo $blog['writer_id']; ?>" class="meta-name" style="text-decoration:none;">
<?php echo htmlspecialchars($author_name); ?>
<?php if($blog['is_verified']) echo $blue_tick; ?>
</a>
<span class="meta-date">منتشر شده در <?php echo getJalaliDateTime($blog['created_at']); ?></span>
</div>
</div>
<button onclick="shareArticle()" class="btn-title-share" title="اشتراک‌گذاری">
<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
</button>
</div>

<div class="article-text"><?php echo parseRichText($blog['description']); ?></div>

<?php if (!empty($tags)): ?>
<div class="tags-container">
<?php foreach($tags as $tag): if(trim($tag) === '') continue; ?>
<span class="tag-chip">#<?php echo htmlspecialchars(trim($tag)); ?></span>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<div class="action-bar">
<div class="action-btn like-btn <?php echo $has_liked ? 'liked' : ''; ?>" id="likeBtn" onclick="toggleLike()" title="لایک">
<svg viewBox="0 0 24 24" id="likeIconOuter">
<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
</svg>
<span id="likesCount" style="margin-right:4px; font-family:sans-serif;"><?php echo formatCounts(intval($blog['likes_count'])); ?></span>
</div>

<div class="action-btn" title="نظرات" onclick="window.scrollTo({top: document.getElementById('comments').offsetTop, behavior: 'smooth'});">
<svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
<span style="margin-right:4px; font-family:sans-serif;"><?php echo formatCounts(intval($blog['comments_count'])); ?></span>
</div>

<div class="action-btn share-btn" title="اشتراک‌گذاری" onclick="shareArticle()">
<svg viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>
</div>

<div class="action-btn" title="تعداد بازدید" style="cursor:default;">
<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
<span style="margin-right:4px; font-family:sans-serif;"><?php echo formatCounts(intval($blog['views_count'])); ?></span>
</div>
</div>

<div class="comments-section" id="comments">
<h3 class="comments-title">نظرات</h3>

<?php if ($user_id > 0): ?>
<form method="POST" class="comment-form">
<input type="hidden" name="action" value="comment">
<input type="hidden" name="blog_id" value="<?php echo $blog_id; ?>">
<div class="comment-input-wrapper">
<textarea name="comment_text" class="comment-input" rows="3" placeholder="نظر خود را بنویسید..." required></textarea>
<button type="submit" class="btn-submit-comment">ارسال نظر</button>
</div>
</form>
<div style="clear: both;"></div>
<?php else: ?>
<div style="background:var(--x-hover); padding: 15px; border-radius: 12px; text-align:center; margin-bottom:30px; border:1px solid var(--x-border); color:var(--x-gray);">
برای ثبت نظر لطفاً <a href="auth.php" style="color:var(--x-blue); font-weight:bold;">وارد شوید</a>.
</div>
<?php endif; ?>

<div class="comments-list">
<?php if (count($comments) > 0): ?>
<?php foreach ($comments as $c): 
$c_name = !empty($c['name']) ? $c['name'] : (!empty($c['username']) ? $c['username'] : 'کاربر');
$c_avatar = !empty($c['avatar']) ? $c['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($c_name);
$can_edit_comment = ($c['user_id'] == $user_id || $is_admin);
?>
<div class="comment-item" id="c-<?php echo $c['id']; ?>">
<a href="profile.php?id=<?php echo $c['user_id']; ?>">
<img src="<?php echo htmlspecialchars($c_avatar); ?>" class="avatar" alt="Avatar" loading="lazy">
</a>
<div class="comment-body">
<div class="comment-header">
<div class="comment-name-wrap">
<a href="profile.php?id=<?php echo $c['user_id']; ?>" class="comment-name" style="text-decoration:none;">
<?php echo htmlspecialchars($c_name); ?>
</a>
<?php if($c['is_verified']) echo $blue_tick; ?>
<span class="comment-date"><?php echo getJalaliDateTime($c['created_at']); ?></span>
</div>
</div>
<p class="comment-text" id="ctext-<?php echo $c['id']; ?>"><?php echo nl2br(htmlspecialchars($c['comment'])); ?></p>

<?php if ($can_edit_comment): ?>
<div class="comment-actions">
<button class="c-btn btn-edit" onclick="openEditMod(<?php echo $c['id']; ?>)">ویرایش</button>
<button class="c-btn btn-del" onclick="openDelCommentMod(<?php echo $c['id']; ?>)">حذف</button>
</div>
<textarea id="raw-<?php echo $c['id']; ?>" style="display:none;"><?php echo htmlspecialchars($c['comment']); ?></textarea>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>

<!-- بخش صفحه‌بندی کامنت‌ها -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
<?php if ($c_page > 1): ?>
<a href="?id=<?php echo $blog_id; ?>&cpage=<?php echo $c_page - 1; ?>#comments" class="page-link">قبلی</a>
<?php endif; ?>

<?php for ($i = 1; $i <= $total_pages; $i++): ?>
<a href="?id=<?php echo $blog_id; ?>&cpage=<?php echo $i; ?>#comments" class="page-link <?php echo ($i == $c_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
<?php endfor; ?>

<?php if ($c_page < $total_pages): ?>
<a href="?id=<?php echo $blog_id; ?>&cpage=<?php echo $c_page + 1; ?>#comments" class="page-link">بعدی</a>
<?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<p style="text-align:center; color:var(--x-gray); font-size:15px; margin-top:20px; font-weight:bold;">اولین نفری باشید که نظر می‌دهد!</p>
<?php endif; ?>
</div>
</div>
</div>
</div>

<!-- پاپ‌آپ ویرایش کل مقاله -->
<?php if ($can_manage_blog): ?>
<div id="editBlogMod" class="mod">
<div class="m-c" style="max-width: 500px;">
<h3 style="margin:0 0 20px 0; color:var(--x-black); font-size: 20px;">ویرایش مقاله</h3>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="action" value="edit_blog">
<input type="hidden" name="blog_id" value="<?php echo $blog_id; ?>">

<div class="input-group">
<label>عنوان مقاله</label>
<input type="text" name="title" class="comment-input" value="<?php echo htmlspecialchars($blog['title']); ?>" required>
</div>

<div class="input-group">
<label>متن مقاله</label>
<div style="display: flex; gap: 8px; margin-bottom: 8px;">
<button type="button" onclick="insertFormat('**', '**')" style="padding: 4px 12px; background: var(--x-hover); border: 1px solid var(--x-border); border-radius: 6px; font-weight: bold; cursor: pointer; color: var(--x-black);" title="بولد">B</button>
<button type="button" onclick="insertFormat('*', '*')" style="padding: 4px 12px; background: var(--x-hover); border: 1px solid var(--x-border); border-radius: 6px; font-style: italic; font-family: serif; cursor: pointer; color: var(--x-black);" title="ایتالیک">I</button>
<button type="button" onclick="insertLink()" style="padding: 4px 12px; background: var(--x-hover); border: 1px solid var(--x-border); border-radius: 6px; cursor: pointer; color: var(--x-blue); font-weight: bold;" title="افزودن لینک">🔗 لینک</button>
</div>
<textarea name="description" id="edit_desc" class="comment-input" rows="8" required><?php echo htmlspecialchars($blog['description']); ?></textarea>
</div>

<div class="input-group">
<label>تگ‌ها (با کاما جدا کنید)</label>
<input type="text" name="tags" class="comment-input" value="<?php echo htmlspecialchars($blog['tags'] ?? ''); ?>">
</div>

<div class="input-group">
<label>کاور مقاله (اختیاری - برای تغییر عکس قبلی)</label>
<input type="file" name="image" class="comment-input" accept="image/*" style="padding: 10px;">
</div>

<div style="display:flex; gap:10px; margin-top:24px;">
<button type="submit" class="btn-submit-comment" style="margin:0; width:100%;">ذخیره تغییرات</button>
<button type="button" class="btn-submit-comment" style="margin:0; width:100%; background:var(--x-border); color:var(--x-black);" onclick="document.getElementById('editBlogMod').style.display='none'">لغو</button>
</div>
</form>
</div>
</div>

<!-- پاپ‌آپ حذف مقاله -->
<div id="delBlogMod" class="mod">
<div class="m-c" style="text-align: center;">
<div class="m-icon red">
<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
</div>
<h3 style="color:var(--x-black); margin:0 0 10px 0; font-size: 18px;">حذف کامل مقاله</h3>
<p style="color:var(--x-gray); font-size:15px; margin: 0 0 24px 0; line-height: 1.5;">آیا مطمئن هستید؟ با این کار تمام لایک‌ها و کامنت‌های این مقاله نیز برای همیشه حذف خواهند شد.</p>
<form method="POST">
<input type="hidden" name="action" value="delete_blog">
<input type="hidden" name="blog_id" value="<?php echo $blog_id; ?>">
<div style="display:flex; gap:10px;">
<button type="button" class="btn-submit-comment" style="margin:0; width:100%; background:var(--x-border); color:var(--x-black);" onclick="document.getElementById('delBlogMod').style.display='none'">لغو</button>
<button type="submit" class="btn-submit-comment" style="margin:0; width:100%; background:var(--x-red); color:#fff;">بله، حذف مقاله</button>
</div>
</form>
</div>
</div>
<?php endif; ?>

<!-- مودال ویرایش نظر -->
<div id="editMod" class="mod">
<div class="m-c">
<h3 style="margin:0 0 16px 0; color:var(--x-black);">ویرایش نظر</h3>
<form method="POST">
<input type="hidden" name="action" value="edit_comment">
<input type="hidden" name="blog_id" value="<?php echo $blog_id; ?>">
<input type="hidden" name="comment_id" id="edit_cid" value="">
<textarea name="edit_text" id="edit_val" class="comment-input" rows="4" required></textarea>
<div style="display:flex; gap:10px; margin-top:16px;">
<button type="submit" class="btn-submit-comment" style="margin:0; width:100%;">ثبت تغییرات</button>
<button type="button" class="btn-submit-comment" style="margin:0; width:100%; background:var(--x-border); color:var(--x-black);" onclick="document.getElementById('editMod').style.display='none'">لغو</button>
</div>
</form>
</div>
</div>

<!-- پاپ‌آپ حذف نظر -->
<div id="delCommentMod" class="mod">
<div class="m-c" style="text-align: center;">
<div class="m-icon red">
<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
</div>
<h3 style="color:var(--x-black); margin:0 0 10px 0; font-size: 18px;">حذف نظر</h3>
<p style="color:var(--x-gray); font-size:15px; margin: 0 0 24px 0; line-height: 1.5;">آیا از حذف این نظر اطمینان دارید؟ این عمل غیرقابل بازگشت است.</p>
<form method="POST">
<input type="hidden" name="action" value="delete_comment">
<input type="hidden" name="blog_id" value="<?php echo $blog_id; ?>">
<input type="hidden" name="comment_id" id="del_cid" value="">
<div style="display:flex; gap:10px;">
<button type="button" class="btn-submit-comment" style="margin:0; width:100%; background:var(--x-border); color:var(--x-black);" onclick="document.getElementById('delCommentMod').style.display='none'">لغو</button>
<button type="submit" class="btn-submit-comment" style="margin:0; width:100%; background:var(--x-red); color:#fff;">حذف کن</button>
</div>
</form>
</div>
</div>

<script>
function insertFormat(startTag, endTag) {
const textarea = document.getElementById('edit_desc');
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
const textarea = document.getElementById('edit_desc');
const start = textarea.selectionStart;
const end = textarea.selectionEnd;
const selectedText = textarea.value.substring(start, end) || "متن لینک";
const replacement = `[${selectedText}](${url})`;
textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
textarea.focus();
}
}

function shareArticle() {
const title = <?php echo json_encode($blog['title']); ?>;
const rawDesc = <?php echo json_encode(mb_substr($blog['description'], 0, 120)); ?>;
const author = <?php echo json_encode($author_name); ?>;
const url = window.location.href;
const shareText = `مقاله: ${title}\nنویسنده: ${author}\n\n${rawDesc}...\n`;

if (navigator.share) {
navigator.share({ title: title, text: shareText, url: url }).catch(err => console.log('Error sharing:', err));
} else {
navigator.clipboard.writeText(shareText + "\n" + url).then(() => {
alert('لینک و توضیحات مقاله در کلیپ‌بورد کپی شد.');
});
}
}

function copyTwCode(btn, event) {
event.stopPropagation();
const codeBox = btn.closest('.tw-code-box');
const code = codeBox.querySelector('code').innerText;

navigator.clipboard.writeText(code).then(() => {
const originalText = btn.innerText;
btn.innerText = 'کپی شد!';
btn.style.color = '#7ee787'; 
btn.style.borderColor = '#7ee787';
setTimeout(() => {
btn.innerText = originalText;
btn.style.color = '';
btn.style.borderColor = '';
}, 2000);
}).catch(err => {
console.error('Failed to copy: ', err);
});
}

function toggleLike() {
const formData = new FormData();
formData.append('action', 'like');
formData.append('blog_id', <?php echo $blog_id; ?>);

fetch('view_blog.php', { method: 'POST', body: formData })
.then(response => response.json())
.then(data => {
if (data.success) {
const likeBtn = document.getElementById('likeBtn');
const likesCount = document.getElementById('likesCount');
if (data.action === 'liked') {
likeBtn.classList.add('liked');
} else {
likeBtn.classList.remove('liked');
}
let count = parseInt(data.new_count);
if (count >= 1000000) count = (count / 1000000).toFixed(1) + 'M';
else if (count >= 1000) count = (count / 1000).toFixed(1) + 'K';

likesCount.innerText = count;
} else {
alert(data.message || 'خطایی رخ داد.');
}
})
.catch(error => console.error('Error:', error));
}

function openEditMod(cid) {
document.getElementById('edit_cid').value = cid;
document.getElementById('edit_val').value = document.getElementById('raw-' + cid).value;
document.getElementById('editMod').style.display = 'flex';
}

function openDelCommentMod(cid) {
document.getElementById('del_cid').value = cid;
document.getElementById('delCommentMod').style.display = 'flex';
}

window.onclick = e => { 
if(e.target.classList.contains('mod')) {
e.target.style.display = 'none';
}
};
</script>

<!-- اضافه کردن اسکریپت رندر رنگی کدها -->
<script src="assets/highlight.min.js"></script>

<?php include 'footer.php'; ?>
</body>
</html>
