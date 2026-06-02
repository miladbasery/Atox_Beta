<?php
ob_start();
session_start();
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

$uid = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);

$ic_logo = '<svg viewBox="0 0 24 24" style="width:100%;height:100%;fill:currentColor"><path d="M12 1L14.5 8.5L22 11L14.5 13.5L12 21L9.5 13.5L2 11L9.5 8.5L12 1Z"></path></svg>';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>صفحه یافت نشد - آتوکس</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
:root { 
    --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; 
    --x-bg:#fff; --x-bg-trans:rgba(255,255,255,0.7); --x-hover:rgba(15,20,25,0.05); 
}
.dark { 
    --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; 
    --x-bg:#000; --x-bg-trans:rgba(0,0,0,0.7); --x-hover:rgba(255,255,255,0.05); 
}
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
body{background:var(--x-bg);color:var(--x-black);-webkit-tap-highlight-color:transparent;overflow-y:scroll;}
a,button{text-decoration:none;color:inherit;background:0 0;border:0;cursor:pointer;outline:0}

.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.main{width:100%;max-width:600px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border); display:flex; flex-direction:column; min-height:100vh;} 

.not-found-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px 20px;
    animation: fadeIn 0.5s ease-out;
}

.error-code-wrapper {
    position: relative;
    margin-bottom: 20px;
}

.error-code {
    font-size: 160px;
    font-weight: 900;
    color: transparent;
    -webkit-text-stroke: 4px var(--x-blue);
    background: linear-gradient(45deg, var(--x-blue), #00ba7c, #f91880);
    -webkit-background-clip: text;
    line-height: 1;
    letter-spacing: 8px;
    text-shadow: 0 20px 50px rgba(29, 155, 240, 0.4);
    animation: float 4s ease-in-out infinite, pulseColors 8s infinite alternate;
    user-select: none;
    position: relative;
    z-index: 2;
}

.error-icon-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 140px;
    height: 140px;
    color: var(--x-bg);
    opacity: 0.9;
    z-index: 3;
    animation: pulseIcon 3s infinite;
}

.error-title {
    font-size: 28px;
    font-weight: 900;
    color: var(--x-black);
    margin-bottom: 15px;
    letter-spacing: -0.5px;
}

.error-desc {
    font-size: 16px;
    color: var(--x-gray);
    max-width: 400px;
    line-height: 1.7;
    margin-bottom: 40px;
}

.back-home-btn {
    background: var(--x-black);
    color: var(--x-bg);
    padding: 16px 45px;
    border-radius: 99px;
    font-size: 17px;
    font-weight: 800;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 12px;
}

.dark .back-home-btn {
    box-shadow: 0 10px 25px rgba(255, 255, 255, 0.1);
}

.back-home-btn:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    background: var(--x-blue);
    color: #fff;
}

.dark .back-home-btn:hover {
    box-shadow: 0 15px 35px rgba(29, 155, 240, 0.3);
}

.back-home-btn:active {
    transform: translateY(1px);
}

@keyframes fadeIn { from{opacity:0; transform:translateY(30px);} to{opacity:1; transform:translateY(0);} }
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}
@keyframes pulseColors {
    0% { filter: hue-rotate(0deg); }
    100% { filter: hue-rotate(360deg); }
}
@keyframes pulseIcon {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.08); filter: drop-shadow(0 0 15px rgba(255,255,255,0.5)); }
}

@media(max-width:600px){ 
    .main{border:none;} 
    .error-code { font-size: 120px; }
    .error-icon-overlay { width: 100px; height: 100px; }
}
</style>
</head>
<body>

<div class="app">
    <main class="main">
        <?php if(file_exists('header.php')) include 'header.php'; ?>

        <div class="not-found-container">
            <div class="error-code-wrapper">
                <div class="error-code">دنیای عجیب</div>

            </div>
            
            <h2 class="error-title">مسیر اشتباه است!</h2>
            <p class="error-desc">
                به نظر می‌رسد در فضا گم شده‌اید. صفحه‌ای که به دنبال آن بودید پیدا نشد یا به کهکشان دیگری منتقل شده است.
            </p>
            
            <a href="index.php" class="back-home-btn">
                <svg viewBox="0 0 24 24" style="width:22px; height:22px; fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"></path></svg>
                بازگشت به دنیای آشنا
            </a>
        </div>

        <?php if(file_exists('footer.php')) include 'footer.php'; ?>
    </main>
</div>

</body>
</html>
<?php ob_end_flush(); ?>
