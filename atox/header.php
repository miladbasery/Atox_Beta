<?php
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

$search_query = isset($_GET['q']) ? htmlspecialchars(strip_tags($_GET['q']), ENT_QUOTES, 'UTF-8') : '';
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_logged_in = $user_id > 0;
$privacy_accepted = 0;

if ($is_logged_in) {
    if (isset($_SESSION['privacy_accepted'])) {
        $privacy_accepted = (int)$_SESSION['privacy_accepted'];
    } else {
        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT privacy_accepted FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $privacy_accepted = (int)$stmt->fetchColumn();
            $_SESSION['privacy_accepted'] = $privacy_accepted;
        } catch (Exception $e) {
            $privacy_accepted = 0;
        }
    }
}
?>

<style>
:root {
    --x-blue: #1d9bf0;
    --x-blue-hover: #1a8cd8;
    --x-blue-bg: rgba(29, 155, 240, 0.1);
    --x-blue-bg-hover: rgba(29, 155, 240, 0.2);
}

@font-face {
    font-family: 'MyCustomFont';
    src: url('fonts/font.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'MyCustomFont';
    src: url('fonts/font-bold.ttf') format('truetype');
    font-weight: bold;
    font-style: normal;
    font-display: swap;
}

* { 
    margin:0; 
    padding:0; 
    box-sizing:border-box; 
    font-family: 'MyCustomFont', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; 
}

body.dark, .dark body { background: #000 !important; }

.glass-header {
    position: sticky;
    top: 3px; 
    margin: 0; 
    z-index: 1000;
    width: 100%; 
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 0; 
    box-sizing: border-box;
    transition: background 0.3s ease, border-color 0.3s ease;
    direction: rtl;
    box-shadow: 0 4px 16px rgba(0,0,0,0.02);
}

body.dark .glass-header, .dark .glass-header {
    background: rgba(0, 0, 0, 0.85);
    border-bottom-color: #2f3336;
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
}

.h-col { flex: 1; display: flex; align-items: center; }
.h-col-right { justify-content: flex-start; } 
.h-col-center { justify-content: center; }    
.h-col-left { justify-content: flex-end; gap: 6px; } 

.h-logo { display: flex; align-items: center; gap: 4px; text-decoration: none; transition: transform 0.2s; }
.h-logo:active { transform: scale(0.95); }
.h-logo h1 { font-size: 16px; font-weight: 900; color: var(--x-black, #0f1419); margin: 0; }
body.dark .h-logo h1, .dark .h-logo h1 { color: #e7e9ea; }

.btn-icon {
    background: transparent; border: none; color: var(--x-black, #0f1419); cursor: pointer;
    display: flex; align-items: center; justify-content: center; padding: 8px; border-radius: 50%; transition: 0.2s;
}
.btn-icon:hover { background: var(--x-hover, rgba(15,20,25,0.05)); }
body.dark .btn-icon, .dark .btn-icon { color: #e7e9ea; }
body.dark .btn-icon:hover, .dark .btn-icon:hover { background: rgba(255,255,255,0.1); }
.btn-icon svg { width: 22px; height: 22px; fill: currentColor; }

.glass-btn-login {
    display: none; background: var(--x-blue-bg); color: var(--x-blue);
    border: 1px solid var(--x-blue-bg-hover); padding: 8px 20px; border-radius: 999px;
    font-size: 14px; font-weight: bold; cursor: pointer; transition: all 0.2s ease;
    backdrop-filter: blur(5px); white-space: nowrap;
}
.glass-btn-login:hover {
    background: var(--x-blue); color: #fff; transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--x-blue-bg-hover);
}
.glass-btn-login:active { transform: translateY(0) scale(0.96); }

.header-search-box {
    display: none; background: rgba(0, 0, 0, 0.04); border-radius: 999px;
    align-items: center; padding: 8px 16px; width: 280px; border: 1px solid transparent; transition: all 0.3s ease;
}
body.dark .header-search-box, .dark .header-search-box { background: rgba(255, 255, 255, 0.08); }
.header-search-box:focus-within { background: var(--x-bg, #fff); border-color: var(--x-blue); box-shadow: 0 0 0 4px var(--x-blue-bg); }
body.dark .header-search-box:focus-within, .dark .header-search-box:focus-within { background: #000; }
.header-search-box input { background: transparent; border: none; outline: none; width: 100%; color: var(--x-black, #0f1419); font-size: 14px; margin-right: 8px; }
body.dark .header-search-box input, .dark .header-search-box input { color: #e7e9ea; }

.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 2000;
    background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    -webkit-transform: translateZ(0); transform: translateZ(0); display: none; align-items: flex-start;
    justify-content: center; padding-top: 60px; opacity: 0; transition: opacity 0.3s ease; direction: rtl;
}
.modal-overlay.active { display: flex; opacity: 1; }
.modal-content {
    background: var(--x-bg, #fff); width: 90%; max-width: 420px; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    transform: translateY(-20px) scale(0.95); transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden; border: 1px solid var(--x-border, #eff3f4); display: flex; flex-direction: column;
}
body.dark .modal-content, .dark .modal-content { background: #000; border-color: #2f3336; }
.modal-overlay.active .modal-content { transform: translateY(0) scale(1); }
.modal-h { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--x-border, #eff3f4); }
body.dark .modal-h, .dark .modal-h { border-color: #2f3336; }
.modal-h h2 { font-size: 16px; font-weight: 900; margin: 0; color: var(--x-black, #0f1419); }
body.dark .modal-h h2, .dark .modal-h h2 { color: #e7e9ea; }
.modal-close-btn { background: transparent; border: none; font-size: 18px; cursor: pointer; color: var(--x-gray, #536471); transition: color 0.2s; line-height: 1; font-weight: bold; padding: 4px; }
.modal-close-btn:hover { color: var(--x-black, #0f1419); }
body.dark .modal-close-btn:hover, .dark .modal-close-btn:hover { color: #fff; }

.theme-switcher-container {
    display: flex; position: relative;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 12px; margin: 12px 18px; padding: 4px;
}
body.dark .theme-switcher-container { background: rgba(255, 255, 255, 0.05); }
.theme-btn {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 0; border: none; background: transparent; cursor: pointer;
    border-radius: 8px; color: var(--x-gray, #536471); font-size: 13px; font-weight: bold;
    transition: color 0.3s ease; z-index: 1; font-family: inherit;
}
.theme-btn svg { width: 18px; height: 18px; fill: currentColor; }
.theme-slider {
    position: absolute; top: 4px; bottom: 4px; width: calc(50% - 4px);
    background: var(--x-bg, #fff); border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 0; pointer-events: none;
}
body.dark .theme-slider { background: #2f3336; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }

html:not(.dark) .tb-light { color: var(--x-black, #0f1419); }
html.dark .tb-dark { color: #fff; }
html:not(.dark) .theme-slider { transform: translateX(0); }
html.dark .theme-slider { transform: translateX(-100%); }

.menu-items-list { padding: 10px 18px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.m-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 12px 8px; text-decoration: none; color: var(--x-black, #0f1419); font-size: 13px; font-weight: 600; transition: background 0.15s ease, border-color 0.15s ease; border-radius: 12px; background: var(--x-hover, rgba(15,20,25,0.03)); border: 1px solid transparent; text-align: center; }
body.dark .m-item, .dark .m-item { color: #e7e9ea; background: rgba(255,255,255,0.03); }
.m-item:hover { background: rgba(29, 155, 240, 0.1); border-color: rgba(29, 155, 240, 0.3); color: var(--x-blue); }
body.dark .m-item:hover { background: rgba(29, 155, 240, 0.15); }
.m-item svg { width: 22px; height: 22px; fill: currentColor; color: var(--x-gray, #536471); transition: color 0.1s; }
body.dark .m-item svg, .dark .m-item svg { color: #8b98a5; }
.m-item:hover svg { color: var(--x-blue); }
.m-item.m-danger { color: #f4212e; }
body.dark .m-item.m-danger, .dark .m-item.m-danger { color: #f4212e; }
.m-item.m-danger svg { color: #f4212e; }
.m-item.m-danger:hover { background: rgba(244, 33, 46, 0.1); border-color: rgba(244, 33, 46, 0.3); color: #f4212e; }
.m-item.m-danger:hover svg { color: #f4212e; }

.s-box-wrapper { padding: 15px 20px; border-bottom: 1px solid var(--x-border, #eff3f4); }
body.dark .s-box-wrapper, .dark .s-box-wrapper { border-color: #2f3336; }
.s-box-ui { background: rgba(0, 0, 0, 0.04); border-radius: 999px; display: flex; align-items: center; padding: 10px 18px; width: 100%; border: 1px solid transparent; transition: all 0.3s ease; }
body.dark .s-box-ui, .dark .s-box-ui { background: rgba(255, 255, 255, 0.08); }
.s-box-ui:focus-within { background: var(--x-bg, #fff); border-color: var(--x-blue); box-shadow: 0 0 0 4px var(--x-blue-bg); }
body.dark .s-box-ui:focus-within, .dark .s-box-ui:focus-within { background: #000; }
.s-box-ui input { background: transparent; border: none; outline: none; width: 100%; color: var(--x-black, #0f1419); font-size: 15px; min-width: 0; }
body.dark .s-box-ui input, .dark .s-box-ui input { color: #e7e9ea; }

.search-results-container { max-height: 350px; overflow-y: auto; padding-bottom: 10px; }
.search-result-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; text-decoration: none; color: inherit; border-bottom: 1px solid rgba(0,0,0,0.05); transition: background 0.2s; direction: rtl; }
.search-result-item:hover { background: rgba(0,0,0,0.03); }
body.dark .search-result-item:hover, .dark .search-result-item:hover { background: rgba(255,255,255,0.05); }
body.dark .search-result-item, .dark .search-result-item { border-color: rgba(255,255,255,0.05); }
.search-result-item:last-child { border-bottom: none; }
.search-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.search-info { display: flex; flex-direction: column; text-align: right; }
.search-name { font-weight: bold; font-size: 15px; color: var(--x-black, #0f1419); }
body.dark .search-name, .dark .search-name { color: #e7e9ea; }
.search-username { font-size: 13px; color: var(--x-gray, #536471); direction: ltr; text-align: right; }

@media (min-width: 600px) {
    .glass-btn-login { display: inline-block; }
}

@media (min-width: 1051px) {
    .glass-header {
        width: calc(100% - 40px); margin: 20px 20px 20px 20px; border-radius: 20px;
        border: 1px solid var(--x-border, #eff3f4); box-shadow: 0 8px 32px rgba(0,0,0,0.04);
        padding: 12px 24px; background: rgba(255, 255, 255, 0.85); top: 20px;
    }
    body.dark .glass-header, .dark .glass-header { border-color: #2f3336; background: rgba(0, 0, 0, 0.85); box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
    .h-col-center { display: none; }
    .h-col-left { justify-content: flex-end; width: 100%; flex: 1; }
    .header-search-box { display: flex; }
    .btn-icon-mobile-search { display: none; }
    .app { max-width: 100% !important; padding: 0 20px !important; box-sizing: border-box !important; }
    .main { max-width: 100% !important; border-radius: 20px !important; border: 1px solid var(--x-border) !important; background: var(--x-bg) !important; margin-bottom: 20px !important; box-shadow: 0 8px 32px rgba(0,0,0,0.02) !important; }
    body.dark .main, .dark .main { background: #000 !important; border-color: #2f3336 !important; box-shadow: 0 8px 32px rgba(0,0,0,0.2) !important; }
    .hdr { border-radius: 20px 20px 0 0 !important; }
    body.dark .hdr, .dark .hdr { background: rgba(0, 0, 0, 0.85) !important; border-bottom-color: #2f3336 !important; }
}

.privacy-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);
    z-index: 99999; display: none; align-items: center; justify-content: center;
    padding: 20px; opacity: 0; transition: opacity 0.3s ease; direction: rtl;
}
.privacy-modal-overlay.active { display: flex; opacity: 1; }
.privacy-modal-content {
    background: #fff; color: var(--x-black, #0f1419); border-radius: 16px; width: 100%; max-width: 600px; max-height: 85vh;
    display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.2); transform: translateY(20px); transition: 0.3s ease;
}
html.dark .privacy-modal-content, body.dark .privacy-modal-content { background: #000; color: #e7e9ea; border: 1px solid #2f3336; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
.privacy-modal-overlay.active .privacy-modal-content { transform: translateY(0); }
.privacy-modal-header { padding: 20px; border-bottom: 1px solid var(--x-border, #eff3f4); display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 18px; }
html.dark .privacy-modal-header, body.dark .privacy-modal-header { border-color: #2f3336; }
.privacy-modal-body { padding: 20px; overflow-y: auto; line-height: 1.8; font-size: 14px; text-align: justify; }
.privacy-modal-footer { padding: 15px 20px; border-top: 1px solid var(--x-border, #eff3f4); text-align: left; }
html.dark .privacy-modal-footer, body.dark .privacy-modal-footer { border-color: #2f3336; }
.privacy-close-btn { background: var(--x-blue); color: #fff; border: none; padding: 10px 25px; border-radius: 99px; cursor: pointer; font-weight: bold; font-family: inherit; transition: 0.2s; }
.privacy-close-btn:hover { background: var(--x-blue-hover); }
</style>

<script>
    (function() {
        var savedTheme = localStorage.getItem('theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.documentElement.classList.add('dark');
            document.body.classList.add('dark');
        }
    })();
</script>

<header class="glass-header">
    <div class="h-col h-col-right">
        <button class="btn-icon" onclick="<?= $is_logged_in ? "openModal('hMenuOverlay')" : "oM('lM')" ?>" aria-label="منو">
            <svg viewBox="0 0 24 24"><path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"></path></svg>
        </button>
    </div>
    
    <div class="h-col h-col-center">
        <a href="index.php" class="h-logo">
            <img src="uploads/logo2.png" alt="آتوکس" style="width:29px;height:28px;">
            <h1>آتوکس | بتا</h1>
        </a>
    </div>
    
    <div class="h-col h-col-left">
        <div class="header-search-box" onclick="<?= $is_logged_in ? "openSearchModal()" : "oM('lM')" ?>">
            <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:var(--x-gray);"><path d="M10.25 3.75c-3.59 0-6.5 2.91-6.5 6.5s2.91 6.5 6.5 6.5c1.795 0 3.419-.726 4.596-1.904 1.178-1.177 1.904-2.801 1.904-4.596 0-3.59-2.91-6.5-6.5-6.5zm-8.5 6.5c0-4.694 3.806-8.5 8.5-8.5s8.5 3.806 8.5 8.5c0 1.986-.682 3.815-1.824 5.262l4.781 4.781-1.414 1.414-4.781-4.781c-1.447 1.142-3.276 1.824-5.262 1.824-4.694 0-8.5-3.806-8.5-8.5z"/></svg>
            <input type="text" placeholder="جستجو در آتوکس..." readonly style="cursor:pointer;">
        </div>

        <?php if(!$is_logged_in): ?>
            <button type="button" class="btn-icon btn-icon-mobile-search" onclick="oM('lM')" aria-label="جستجو">
                <svg viewBox="0 0 24 24"><path d="M10.25 3.75c-3.59 0-6.5 2.91-6.5 6.5s2.91 6.5 6.5 6.5c1.795 0 3.419-.726 4.596-1.904 1.178-1.177 1.904-2.801 1.904-4.596 0-3.59-2.91-6.5-6.5-6.5zm-8.5 6.5c0-4.694 3.806-8.5 8.5-8.5s8.5 3.806 8.5 8.5c0 1.986-.682 3.815-1.824 5.262l4.781 4.781-1.414 1.414-4.781-4.781c-1.447 1.142-3.276 1.824-5.262 1.824-4.694 0-8.5-3.806-8.5-8.5z"/></svg>
            </button>
            <button type="button" class="glass-btn-login" onclick="oM('lM')">ورود</button>
        <?php else: ?>
            <button type="button" class="btn-icon btn-icon-mobile-search" onclick="openSearchModal()" aria-label="جستجو">
                <svg viewBox="0 0 24 24"><path d="M10.25 3.75c-3.59 0-6.5 2.91-6.5 6.5s2.91 6.5 6.5 6.5c1.795 0 3.419-.726 4.596-1.904 1.178-1.177 1.904-2.801 1.904-4.596 0-3.59-2.91-6.5-6.5-6.5zm-8.5 6.5c0-4.694 3.806-8.5 8.5-8.5s8.5 3.806 8.5 8.5c0 1.986-.682 3.815-1.824 5.262l4.781 4.781-1.414 1.414-4.781-4.781c-1.447 1.142-3.276 1.824-5.262 1.824-4.694 0-8.5-3.806-8.5-8.5z"/></svg>
            </button>
        <?php endif; ?>
    </div>
</header>

<div class="modal-overlay" id="hMenuOverlay">
    <div class="modal-content" style="max-width: 300px;">
        <div class="modal-h">
            <h2>دسترسی سریع</h2>
            <button class="modal-close-btn" onclick="closeModal('hMenuOverlay')">✕</button>
        </div>
        
        <div class="theme-switcher-container">
            <div class="theme-slider"></div>
            <button class="theme-btn tb-light" onclick="setThemeState('light')">
                <svg viewBox="0 0 1024 1024"><path d="M548 818v126c0 8.837-7.163 16-16 16h-40c-8.837 0-16-7.163-16-16V818c15.845 1.643 27.845 2.464 36 2.464 8.155 0 20.155-.821 36-2.464m205.251-115.66 89.096 89.095c6.248 6.248 6.248 16.38 0 22.627l-28.285 28.285c-6.248 6.248-16.379 6.248-22.627 0L702.34 753.25c12.365-10.043 21.431-17.947 27.198-23.713 5.766-5.767 13.67-14.833 23.713-27.198m-482.502 0c10.043 12.365 17.947 21.431 23.713 27.198 5.767 5.766 14.833 13.67 27.198 23.713l-89.095 89.096c-6.248 6.248-16.38 6.248-22.627 0l-28.285-28.285c-6.248-6.248-6.248-16.379 0-22.627zM512 278c129.235 0 234 104.765 234 234S641.235 746 512 746 278 641.235 278 512s104.765-234 234-234M206 476c-1.643 15.845-2.464 27.845-2.464 36 0 8.155-.821 20.155 2.464 36H80c-8.837 0-16-7.163-16-16v-40c0-8.837 7.163-16 16-16zm738 0c8.837 0 16 7.163 16 16v40c0 8.837-7.163 16-16 16H818c1.643-15.845 2.464-27.845 2.464-36 0-8.155-.821-20.155-2.464-36ZM814.062 180.653l28.285 28.285c6.248 6.248 6.248 16.379 0 22.627L753.25 320.66c-10.043-12.365-17.947-21.431-23.713-27.198-5.767-5.766-14.833-13.67-27.198-23.713l89.095-89.096c6.248-6.248 16.38-6.248 22.627 0m-581.497 0 89.095 89.096c-12.365 10.043-21.431 17.947-27.198 23.713-5.766 5.767-13.67 14.833-23.713 27.198l-89.096-89.095c-6.248-6.248-6.248-16.38 0-22.627l28.285-28.285c6.248-6.248 16.379-6.248 22.627 0M532 64c8.837 0 16 7.163 16 16v126c-15.845-1.643-27.845-2.464-36-2.464-8.155 0-20.155.821-36 2.464V80c0-8.837 7.163-16 16-16z"/></svg>
                روشن
            </button>
            <button class="theme-btn tb-dark" onclick="setThemeState('dark')">
                <svg viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36-.98 1.37-2.58 2.26-4.4 2.26-3.03 0-5.5-2.47-5.5-5.5 0-1.82.89-3.42 2.26-4.4C12.92 3.04 12.46 3 12 3z"/></svg>
                تاریک
            </button>
        </div>

        <div class="menu-items-list">
            <a href="home.php" class="m-item"><svg viewBox="0 0 24 24"><path d="M12 3l9 8h-3v8h-4v-6h-4v6H6v-8H3l9-8z"/></svg> خانه</a>
            <a href="index.php" class="m-item"><svg viewBox="0 0 24 24"><path d="M3 3h18v18H3V3zm16 16V5H5v14h14zm-3-9h-8v2h8v-2z"/></svg> دیوار</a>
            <a href="blog.php" class="m-item"><svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg> مقالات</a>
            <a href="general.php" class="m-item"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg> کانون</a>
            <a href="chat.php" class="m-item"><svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg> پیام‌ها</a>
            <a href="profile.php" class="m-item"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg> پروفایل</a>
            <a href="https://www.atoxcomputer.ir/general.php?tab=jozves" class="m-item"><svg viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg> جزوات</a>
            <a href="https://www.atoxcomputer.ir/university.php?id=5" class="m-item"><svg viewBox="0 0 24 24"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg> درباره ما</a>
            <a href="settings.php" class="m-item"><svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.56-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.73 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .43-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.49-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg> تنظیمات</a>
            <a href="gozaresh.php" class="m-item m-danger"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg> ارسال گزارش</a>
        </div>
    </div>
</div>

<?php if($is_logged_in): ?>
<div class="modal-overlay" id="searchOverlay">
    <div class="modal-content">
        <div class="modal-h">
            <h2>جستجو در آتوکس</h2>
            <button class="modal-close-btn" onclick="closeModal('searchOverlay')">✕</button>
        </div>
        <div class="s-box-wrapper">
            <form onsubmit="return false;" class="s-box-ui">
                <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:var(--x-gray, #536471);margin-left:8px;flex-shrink:0"><path d="M10.25 3.75c-3.59 0-6.5 2.91-6.5 6.5s2.91 6.5 6.5 6.5c1.795 0 3.419-.726 4.596-1.904 1.178-1.177 1.904-2.801 1.904-4.596 0-3.59-2.91-6.5-6.5-6.5zm-8.5 6.5c0-4.694 3.806-8.5 8.5-8.5s8.5 3.806 8.5 8.5c0 1.986-.682 3.815-1.824 5.262l4.781 4.781-1.414 1.414-4.781-4.781c-1.447 1.142-3.276 1.824-5.262 1.824-4.694 0-8.5-3.806-8.5-8.5z"/></svg>
                <input type="text" id="liveSearchInput" placeholder="جستجو افراد با نام یا نام کاربری..." autocomplete="off">
            </form>
        </div>
        <div class="search-results-container" id="searchResults">
            <div style="padding: 20px; text-align: center; color: var(--x-gray, #536471); font-size: 14px;">
                برای جستجو نام کاربری را تایپ کنید.
            </div>
        </div>
    </div>
</div>

<?php if($privacy_accepted != 1): ?>
<div id="privacy-modal" class="privacy-modal-overlay">
    <div class="privacy-modal-content">
        <div class="privacy-modal-header">
            <span>دوست عزیز، به آتوکس خوش آمدید!</span>
        </div>
        <div class="privacy-modal-body">
            <p><strong>مقدمه</strong><br>
            حریم خصوصی کاربران یکی از مهم‌ترین اولویت‌های پلتفرم آتوکس است. این سیاست‌نامه به منظور شفاف‌سازی عملکرد سایت در خصوص جمع‌آوری، استفاده و حفاظت از اطلاعات شما، منطبق بر قوانین جمهوری اسلامی ایران (از جمله قانون جرایم رایانه‌ای و قانون تجارت الکترونیک) تدوین شده است.</p>
            
            <p><strong>چه اطلاعاتی از شما ذخیره می‌شود؟</strong><br>
            برای ارائه خدمات بهتر، حفظ امنیت حساب کاربری و جلوگیری از سوءاستفاده‌های احتمالی، اطلاعات زیر در پایگاه داده‌های امن آتوکس ذخیره می‌گردد:</p>
            <ul>
                <li><strong>شماره تلفن همراه:</strong> صرفاً جهت احراز هویت (از طریق ارسال کد یک‌بار مصرف OTP)، بازیابی حساب کاربری و جلوگیری از ساخت حساب‌های جعلی.</li>
                <li><strong>آدرس IP (اینترنت پروتکل):</strong> جهت بررسی نشست‌های فعال، تامین امنیت شبکه و شناسایی فعالیت‌های مشکوک یا حملات سایبری.</li>
                <li><strong>مشخصات دستگاه و مرورگر (User Agent):</strong> برای تطبیق رابط کاربری، رفع باگ‌ها و مدیریت نشست‌های شما (Sessions).</li>
                <li><strong>اطلاعات پروفایل:</strong> نام، نام کاربری و بیوگرافی که شما به صورت اختیاری وارد می‌کنید.</li>
            </ul>

            <p><strong>نحوه استفاده و محافظت از اطلاعات</strong><br>
            تمامی گذرواژه‌های شما با استفاده از الگوریتم‌های استاندارد (Hash) رمزنگاری شده و حتی برای مدیران سایت نیز قابل خواندن نیستند. آتوکس متعهد می‌شود که اطلاعات هویتی و ارتباطی شما را بدون حکم قضایی یا دستور مراجع ذی‌صلاح قانونی جمهوری اسلامی ایران، در اختیار هیچ شخص حقیقی یا حقوقی ثالث قرار ندهد.</p>

            <p><strong>حقوق شما</strong><br>
            شما حق دارید در هر زمان نسبت به ویرایش اطلاعات پروفایل خود اقدام نمایید. با تایید این قوانین، شما موافقت خود را با شرایط ذکر شده اعلام می‌دارید.</p>
        </div>
        <div class="privacy-modal-footer">
            <button class="privacy-close-btn" id="acceptPrivacyBtn" onclick="acceptPrivacyAction()">قوانین را قبول می‌کنم</button>
        </div>
    </div>
</div>

<?php endif; ?>

<?php endif; ?>

<script>
function setThemeState(mode) {
    const isDark = mode === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.body.classList.toggle('dark', isDark);
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

function openModal(id) {
    const modal = document.getElementById(id);
    if(modal) {
        modal.style.display = 'flex';
        void modal.offsetWidth;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}
function closeModal(id) {
    const modal = document.getElementById(id);
    if(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    }
}
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    if(overlay.id !== 'privacy-modal') {
        overlay.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
    }
});

const escapeHTML = (str) => {
    if (!str) return '';
    return str.toString().replace(/[&<>'"]/g, tag => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[tag]));
};

<?php if($is_logged_in): ?>
function openSearchModal() {
    openModal('searchOverlay');
    setTimeout(() => { document.getElementById('liveSearchInput').focus(); }, 100);
}
const searchInput = document.getElementById('liveSearchInput');
const searchResults = document.getElementById('searchResults');
let searchTimeout = null;

if(searchInput) {
    searchInput.addEventListener('input', function() {
        let query = this.value.trim();
        clearTimeout(searchTimeout);
        if(query.length > 0) {
            searchResults.innerHTML = `<div style="padding: 20px; text-align: center; color: var(--x-blue);">در حال جستجو...</div>`; 
            searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`search_ajax.php?q=${encodeURIComponent(query)}`);
                    const data = await response.json();
                    if (data.length > 0) {
                        let html = '';
                        data.forEach(user => {
                            let avatar = user.avatar ? escapeHTML(user.avatar) : 'default-avatar.png';
                            html += `
                                <a href="profile.php?id=${escapeHTML(user.id)}" class="search-result-item">
                                    <img src="${avatar}" class="search-avatar" alt="${escapeHTML(user.name)}">
                                    <div class="search-info">
                                        <span class="search-name">${escapeHTML(user.name)}</span>
                                        <span class="search-username">@${escapeHTML(user.username)}</span>
                                    </div>
                                </a>`;
                        });
                        searchResults.innerHTML = html;
                    } else {
                        searchResults.innerHTML = `<div style="padding: 20px; text-align: center; color: var(--x-gray, #536471);">کاربری با این مشخصات یافت نشد.</div>`;
                    }
                } catch (error) {
                    searchResults.innerHTML = `<div style="padding: 20px; text-align: center; color: #f4212e;">خطا در ارتباط با سرور.</div>`;
                }
            }, 300);
        } else {
            searchResults.innerHTML = `<div style="padding: 20px; text-align: center; color: var(--x-gray, #536471); font-size: 14px;">برای جستجو نام کاربری را تایپ کنید.</div>`;
        }
    });
}

<?php if($privacy_accepted != 1): ?>
document.addEventListener('DOMContentLoaded', function() {
    openModal('privacy-modal');
});

function acceptPrivacyAction() {
    const btn = document.getElementById('acceptPrivacyBtn');
    btn.innerText = "در حال ثبت...";
    btn.disabled = true;

    fetch('actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=accept_privacy'
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            closeModal('privacy-modal');
        } else {
            alert('خطا در ثبت درخواست. لطفاً دوباره تلاش کنید.');
            btn.innerText = "قوانین را قبول می‌کنم";
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('خطای ارتباطی با سرور.');
        btn.innerText = "قوانین را قبول می‌کنم";
        btn.disabled = false;
    });
}
<?php endif; ?>

<?php endif; ?>
</script>
