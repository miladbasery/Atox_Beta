<?php
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'>شما دسترسی ادمین ندارید. <a href='blog.php'>بازگشت</a></div>");
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $writer_id = $_SESSION['user_id'] ?? 0;

    if (empty($title) || empty($description)) {
        $message = "<div class='msg error'>لطفاً عنوان و متن مقاله را کامل کنید.</div>";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO blogs (writer_id, title, description, image) VALUES (?, ?, ?, ?)");
            $stmt->execute([$writer_id, $title, $description, $image]);
            $message = "<div class='msg success'>مقاله منتشر شد! در حال انتقال...</div>
                        <meta http-equiv='refresh' content='2;url=blog.php'>";
        } catch (PDOException $e) {
            $message = "<div class='msg error'>خطا: " . $e->getMessage() . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>نوشتن مقاله جدید</title>
    <style>
        :root { --x-bg: #ffffff; --x-black: #0f1419; --x-gray: #536471; --x-border: #eff3f4; --x-blue: #1d9bf0; }
        body { background-color: var(--x-bg); color: var(--x-black); font-family: system-ui, sans-serif; margin: 0; padding-bottom: 80px; }
        .main-container { max-width: 600px; margin: 0 auto; border-left: 1px solid var(--x-border); border-right: 1px solid var(--x-border); min-height: 100vh; display: flex; flex-direction: column; }
        
        .header-top { padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); z-index: 10; border-bottom: 1px solid var(--x-border); }
        .header-top h2 { margin: 0; font-size: 18px; font-weight: 800; }
        .btn-back { color: var(--x-black); text-decoration: none; font-size: 24px; line-height: 1; padding: 4px; border-radius: 50%; transition: 0.2s; }
        .btn-back:hover { background: var(--x-border); }
        .btn-submit { background: var(--x-blue); color: white; border: none; padding: 8px 16px; border-radius: 999px; font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { background: #1a8cd8; }

        .form-area { padding: 16px; flex: 1; }
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 700; color: var(--x-black); }
        .input-ui { width: 100%; box-sizing: border-box; padding: 14px; border: 1px solid var(--x-border); border-radius: 12px; font-size: 15px; font-family: inherit; background: transparent; outline: none; transition: 0.2s; }
        .input-ui:focus { border-color: var(--x-blue); box-shadow: 0 0 0 1px var(--x-blue); }
        textarea.input-ui { resize: vertical; min-height: 200px; line-height: 1.6; }

        .msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .msg.error { background: #ffe9e9; color: #f4212e; }
        .msg.success { background: #e0f2e9; color: #00ba7c; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="main-container">
        <form method="POST" action="">
            <div class="header-top">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <a href="blog.php" class="btn-back">➔</a>
                    <h2>نوشتن مقاله</h2>
                </div>
                <button type="submit" class="btn-submit">انتشار</button>
            </div>

            <div class="form-area">
                <?= $message ?>

                <div class="input-group">
                    <label>عنوان مقاله</label>
                    <input type="text" name="title" class="input-ui" required placeholder="یک عنوان جذاب بنویسید...">
                </div>

                <div class="input-group">
                    <label>لینک عکس کاور (اختیاری)</label>
                    <input type="text" name="image" class="input-ui" placeholder="https://...">
                </div>

                <div class="input-group">
                    <label>متن اصلی مقاله</label>
                    <textarea name="description" class="input-ui" required placeholder="شروع به نوشتن کنید..."></textarea>
                </div>
            </div>
        </form>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
