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

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        rating INT NOT NULL DEFAULT 5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (PDOException $e) {
}

$not_logged_in = !isLoggedIn();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$not_logged_in) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(htmlspecialchars('درخواست نامعتبر است.', ENT_QUOTES, 'UTF-8'));
    }

    $title = trim(filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW));
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT) ?: 5;
    $user_id = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
    
    if (empty($title) || empty($description)) {
        $msg = "لطفاً تمامی فیلدها را پر کنید.";
        $msgType = 'error';
    } elseif ($rating < 1 || $rating > 5) {
        $msg = "امتیاز باید بین ۱ تا ۵ باشد.";
        $msgType = 'error';
    } else {
        $stmt = $pdo->prepare("INSERT INTO reports (user_id, title, description, rating) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $title, $description, $rating])) {
            $msg = "گزارش شما با موفقیت ثبت شد. از همراهی شما برای بهبود آتوکس سپاسگزاریم!";
            $msgType = 'success';
        } else {
            $msg = "متاسفانه خطایی در ثبت اطلاعات رخ داد.";
            $msgType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ارسال گزارش - آتوکس</title>
    <script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
    <style>
        :root {
            --x-blue: #1d9bf0;
            --x-black: #0f1419;
            --x-gray: #536471;
            --x-border: #eff3f4;
            --x-bg: #fff;
            --x-bg-trans: rgba(255, 255, 255, 0.85);
            --x-hover: rgba(15, 20, 25, 0.05);
            --x-shadow: 0 8px 20px rgba(0,0,0,0.06);
            --x-red: #f91880;
            --x-green: #00ba7c;
        }
        .dark {
            --x-black: #e7e9ea;
            --x-gray: #71767b;
            --x-border: #2f3336;
            --x-bg: #000;
            --x-bg-trans: rgba(0, 0, 0, 0.85);
            --x-hover: rgba(255, 255, 255, 0.05);
            --x-shadow: 0 8px 20px rgba(255, 255, 255, 0.03);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background: var(--x-bg); color: var(--x-black); -webkit-tap-highlight-color: transparent; }
        
        .main {
            width: 100%;
            max-width: 650px;
            margin: 0 auto;
            border-left: 1px solid var(--x-border);
            border-right: 1px solid var(--x-border);
            min-height: 100vh;
            background: var(--x-bg);
            display: flex;
            flex-direction: column;
        }
        
        .report-wrapper {
            padding: 20px;
            flex: 1;
            animation: fadeIn 0.4s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-title {
            font-size: 22px;
            font-weight: 900;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title svg {
            color: var(--x-blue);
            width: 28px;
            height: 28px;
        }

        .subtitle {
            color: var(--x-gray);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .form-group { margin-bottom: 20px; }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 14px 16px;
            background: transparent;
            border: 1px solid var(--x-border);
            border-radius: 16px;
            color: var(--x-black);
            font-size: 15px;
            outline: none;
            transition: all 0.2s;
        }

        .form-input:focus, .form-textarea:focus {
            border-color: var(--x-blue);
            box-shadow: 0 0 0 1px var(--x-blue);
        }

        .form-textarea { resize: vertical; min-height: 120px; }

        .rating-group {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 8px;
        }
        
        .rating-group input { display: none; }

        .rating-group label {
            cursor: pointer;
            color: var(--x-border);
            transition: color 0.2s;
        }

        .rating-group label svg {
            width: 34px;
            height: 34px;
            fill: currentColor;
        }

        .rating-group input:checked ~ label,
        .rating-group label:hover,
        .rating-group label:hover ~ label {
            color: #ffd700;
        }

        .btn-submit {
            background: var(--x-blue);
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 999px;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover { background: #1a8cd8; }
        .btn-submit:active { transform: scale(0.98); }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .alert-success { background: rgba(0, 186, 124, 0.1); color: var(--x-green); border: 1px solid rgba(0, 186, 124, 0.2); }
        .alert-error { background: rgba(249, 24, 128, 0.1); color: var(--x-red); border: 1px solid rgba(249, 24, 128, 0.2); }

        .glass-box {
            background: var(--x-bg-trans);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--x-border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--x-shadow);
        }

        @media(max-width: 600px) {
            .main { border: none; }
            .glass-box { padding: 18px; border-radius: 16px; }
        }
    </style>
</head>
<body>

<div class="main">
    <?php if(file_exists('header.php')) include 'header.php'; ?>
    
    <div class="report-wrapper">
        <div class="glass-box">
            <h1 class="header-title">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                ارسال گزارش و نظرات
            </h1>
            <p class="subtitle">لطفاً هرگونه مشکل، باگ یا پیشنهاد خود را برای بهبود آتوکس با ما در میان بگذارید. نظرات شما برای ما بسیار ارزشمند است.</p>

            <?php if($not_logged_in): ?>
                <div class="alert alert-error" style="text-align:center;">
                    برای ارسال نظرات و گزارش سیستم باید وارد حساب کاربری خود شوید.<br><br>
                    <button class="btn-submit" onclick="if(typeof oM === 'function') oM('lM'); else location.href='index.php';" style="width:auto; padding:8px 24px; margin: 0 auto;">ورود به حساب</button>
                </div>
            <?php else: ?>
                <?php if($msg): ?>
                    <div class="alert alert-<?=$msgType?>"><?=htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div>
                <?php endif; ?>

                <form method="POST" action="gozaresh.php">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8')?>">
                    <div class="form-group">
                        <label class="form-label">موضوع گزارش</label>
                        <input type="text" name="title" class="form-input" placeholder="مثلاً: مشکل در باز شدن پیام‌ها / پیشنهاد برای تم دارک" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">توضیحات تکمیلی</label>
                        <textarea name="description" class="form-textarea" placeholder="لطفاً جزئیات مشکل یا پیشنهاد خود را به طور کامل بنویسید..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">امتیاز شما به تجربه کاربری سایت</label>
                        <div class="rating-group">
                            <input type="radio" id="star5" name="rating" value="5" checked>
                            <label for="star5" title="۵ ستاره">
                                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            </label>

                            <input type="radio" id="star4" name="rating" value="4">
                            <label for="star4" title="۴ ستاره">
                                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            </label>

                            <input type="radio" id="star3" name="rating" value="3">
                            <label for="star3" title="۳ ستاره">
                                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            </label>

                            <input type="radio" id="star2" name="rating" value="2">
                            <label for="star2" title="۲ ستاره">
                                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            </label>

                            <input type="radio" id="star1" name="rating" value="1">
                            <label for="star1" title="۱ ستاره">
                                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor;"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                        ارسال گزارش
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if(file_exists('footer.php')) include 'footer.php'; ?>
</div>

</body>
</html>
<?php ob_end_flush(); ?>
