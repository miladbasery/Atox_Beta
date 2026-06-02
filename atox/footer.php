<?php
$current_page = basename($_SERVER['PHP_SELF']);
$logged_in_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_logged_in = ($logged_in_user_id > 0);

$is_home_active = ($current_page === 'home.php');
$is_wall_active = ($current_page === 'index.php');
$is_kanoon_active = ($current_page === 'general.php');
$is_chat_active = ($current_page === 'chat.php');

$req_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$is_profile_active = ($current_page === 'profile.php' && ($req_id !== null ? $req_id === $logged_in_user_id : true));

$active_color = "#1d9bf0";
$active_bg = "rgba(29, 155, 240, 0.1)";
?>

<style>
.nav-m {
    display: none;
    position: fixed;
    bottom: max(16px, env(safe-area-inset-bottom)); 
    left: 50%;
    transform: translateX(-50%);
    width: calc(100% - 32px);
    max-width: 420px;
    height: 64px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--x-border, #eff3f4);
    border-radius: 24px;
    z-index: 999;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0,0,0,0.04);
}

.dark .nav-m {
    background: rgba(0, 0, 0, 0.85);
    border-color: #2f3336;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.nav-m-container {
    display: flex; justify-content: space-between; align-items: center;
    height: 100%; width: 100%; padding: 0 8px; box-sizing: border-box;
}

.nav-item {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: calc(100% - 12px); margin: 6px 2px; border-radius: 20px;
    color: #0f1419; 
    text-decoration: none; transition: 0.3s; gap: 3px;
}
.dark .nav-item { color: #e7e9ea; } 

.nav-item.active { color: <?= htmlspecialchars($active_color, ENT_QUOTES, 'UTF-8') ?>; background-color: <?= htmlspecialchars($active_bg, ENT_QUOTES, 'UTF-8') ?>; }
.dark .nav-item.active { background-color: rgba(29, 155, 240, 0.15); }

.nav-ic-box { width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.nav-ic-box svg { width: 100%; height: 100%; fill: currentColor; }
.nav-item:active .nav-ic-box { transform: scale(0.75); }
.nav-text { font-size: 11px; font-weight: 600; line-height: 1; }

.sidebar-desktop {
    display: none;
}

@media(max-width: 1050px) {
    .nav-m { display: block; }
    body { padding-bottom: 85px; } 
}

@media(min-width: 1051px) {
    body { 
        padding-right: 300px;
        margin: 0; 
        max-width: 100%; 
        background: #f7f9fa;
    }

    .sidebar-desktop {
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 20px;
        right: 20px;
        bottom: 20px;
        width: 260px;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--x-border, #eff3f4);
        border-radius: 24px;
        padding: 24px 20px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.04);
        box-sizing: border-box;
        z-index: 998;
        overflow-y: auto;
    }
    
    .dark .sidebar-desktop { 
        background: rgba(0, 0, 0, 0.85); 
        border-color: #2f3336; 
        box-shadow: 0 8px 32px rgba(0,0,0,0.2); 
    }

    .sidebar-brand-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding-bottom: 30px;
        margin-bottom: 10px;
        border-bottom: 1px solid var(--x-border, #eff3f4);
        text-decoration: none;
    }
    .dark .sidebar-brand-logo { border-color: #2f3336; }
    .sidebar-brand-logo img { width: 38px; height: 38px; }
    .sidebar-brand-logo h1 { font-size: 24px; font-weight: 900; color: var(--x-black, #0f1419); margin: 0; }
    .dark .sidebar-brand-logo h1 { color: #e7e9ea; }
    
    .d-nav-menu { display: flex; flex-direction: column; gap: 8px; }
    .d-nav-item {
        display: flex; align-items: center; gap: 16px;
        padding: 14px 20px; border-radius: 16px; text-decoration: none;
        color: var(--x-black, #0f1419); font-size: 17px; font-weight: 600;
        transition: all 0.2s ease;
    }
    .dark .d-nav-item { color: #e7e9ea; }
    .d-nav-item:hover { background: rgba(0, 0, 0, 0.05); }
    .dark .d-nav-item:hover { background: rgba(255, 255, 255, 0.05); }

    .d-nav-item.active { color: <?= htmlspecialchars($active_color, ENT_QUOTES, 'UTF-8') ?>; background-color: <?= htmlspecialchars($active_bg, ENT_QUOTES, 'UTF-8') ?>; font-weight: 800; box-shadow: 0 4px 12px rgba(29, 155, 240, 0.1); }
    .dark .d-nav-item.active { background-color: rgba(29, 155, 240, 0.15); box-shadow: none; }

    .d-nav-ic { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
    .d-nav-ic svg { width: 100%; height: 100%; fill: currentColor; }
}
</style>

<aside class="sidebar-desktop">
    <a href="index.php" class="sidebar-brand-logo">
        <img src="uploads/logo2.png" alt="آتوکس">
        <h1>آتوکس</h1>
    </a>

    <div class="d-nav-menu">
        <a href="home.php" class="d-nav-item <?= $is_home_active ? 'active' : '' ?>"><div class="d-nav-ic"><svg viewBox="0 0 24 24"><path d="M12 1.696L.622 8.807l1.06 1.696L3 9.679V19.5C3 20.881 4.119 22 5.5 22h13c1.381 0 2.5-1.119 2.5-2.5V9.679l1.318.824 1.06-1.696L12 1.696zM12 16.5c-1.933 0-3.5-1.567-3.5-3.5s1.567-3.5 3.5-3.5 3.5 1.567 3.5 3.5-1.567 3.5-3.5 3.5z"/></svg></div><span>خانه</span></a>
        
        <a href="index.php" class="d-nav-item <?= $is_wall_active ? 'active' : '' ?>"><div class="d-nav-ic"><svg viewBox="0 0 24 24"><path d="M3 3h8v10H3V3zm10 0h8v6h-8V3zm0 8h8v10h-8V11zM3 15h8v6H3v-6z"/></svg></div><span>دیوار</span></a>
        
        <a <?= $is_logged_in ? 'href="general.php"' : 'href="javascript:void(0);" onclick="requireLogin(event)"' ?> class="d-nav-item <?= $is_kanoon_active ? 'active' : '' ?>"><div class="d-nav-ic"><svg viewBox="0 0 24 24"><path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/></svg></div><span>کانون</span></a>
        
        <a <?= $is_logged_in ? 'href="chat.php"' : 'href="javascript:void(0);" onclick="requireLogin(event)"' ?> class="d-nav-item <?= $is_chat_active ? 'active' : '' ?>"><div class="d-nav-ic"><svg viewBox="0 0 24 24"><path d="M1.998 5.5c0-1.381 1.119-2.5 2.5-2.5h15c1.381 0 2.5 1.119 2.5 2.5v13c0 1.381-1.119 2.5-2.5 2.5h-15c-1.381 0-2.5-1.119-2.5-2.5v-13zm2.5-.5c-.276 0-.5.224-.5.5v2.764l8 3.638 8-3.636V5.5c0-.276-.224-.5-.5-.5h-15zm15.5 5.463l-8 3.636-8-3.638V18.5c0 .276.224.5.5.5h15c.276 0 .5-.224.5-.5v-8.037z"/></svg></div><span>پیام‌ها</span></a>
        
        <a <?= $is_logged_in ? 'href="profile.php?id='.$logged_in_user_id.'"' : 'href="javascript:void(0);" onclick="requireLogin(event)"' ?> class="d-nav-item <?= $is_profile_active ? 'active' : '' ?>"><div class="d-nav-ic"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg></div><span>پروفایل</span></a>
    </div>
</aside>

<nav class="nav-m">
    <div class="nav-m-container">
        <a href="home.php" class="nav-item <?= $is_home_active ? 'active' : '' ?>"><div class="nav-ic-box"><svg viewBox="0 0 24 24"><path d="M12 1.696L.622 8.807l1.06 1.696L3 9.679V19.5C3 20.881 4.119 22 5.5 22h13c1.381 0 2.5-1.119 2.5-2.5V9.679l1.318.824 1.06-1.696L12 1.696zM12 16.5c-1.933 0-3.5-1.567-3.5-3.5s1.567-3.5 3.5-3.5 3.5 1.567 3.5 3.5-1.567 3.5-3.5 3.5z"/></svg></div><span class="nav-text">خانه</span></a>
        
        <a href="index.php" class="nav-item <?= $is_wall_active ? 'active' : '' ?>"><div class="nav-ic-box"><svg viewBox="0 0 24 24"><path d="M3 3h8v10H3V3zm10 0h8v6h-8V3zm0 8h8v10h-8V11zM3 15h8v6H3v-6z"/></svg></div><span class="nav-text">دیوار</span></a>
        
        <a <?= $is_logged_in ? 'href="general.php"' : 'href="javascript:void(0);" onclick="requireLogin(event)"' ?> class="nav-item <?= $is_kanoon_active ? 'active' : '' ?>"><div class="nav-ic-box"><svg viewBox="0 0 24 24"><path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/></svg></div><span class="nav-text">کانون</span></a>
        
        <a <?= $is_logged_in ? 'href="chat.php"' : 'href="javascript:void(0);" onclick="requireLogin(event)"' ?> class="nav-item <?= $is_chat_active ? 'active' : '' ?>"><div class="nav-ic-box"><svg viewBox="0 0 24 24"><path d="M1.998 5.5c0-1.381 1.119-2.5 2.5-2.5h15c1.381 0 2.5 1.119 2.5 2.5v13c0 1.381-1.119 2.5-2.5 2.5h-15c-1.381 0-2.5-1.119-2.5-2.5v-13zm2.5-.5c-.276 0-.5.224-.5.5v2.764l8 3.638 8-3.636V5.5c0-.276-.224-.5-.5-.5h-15zm15.5 5.463l-8 3.636-8-3.638V18.5c0 .276.224.5.5.5h15c.276 0 .5-.224.5-.5v-8.037z"/></svg></div><span class="nav-text">پیام‌ها</span></a>
        
        <a <?= $is_logged_in ? 'href="profile.php?id='.$logged_in_user_id.'"' : 'href="javascript:void(0);" onclick="requireLogin(event)"' ?> class="nav-item <?= $is_profile_active ? 'active' : '' ?>"><div class="nav-ic-box"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg></div><span class="nav-text">پروفایل</span></a>
    </div>
</nav>

<?php if(!$is_logged_in): ?>
<style>

.twxx-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 16px; opacity: 0; transition: opacity 0.25s ease; will-change: opacity; }
.twxx-modal-overlay.active { display: flex; opacity: 1; }
.twx-modal-box { background: var(--x-bg, #fff); border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); transform: scale(0.95) translateY(10px); transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; flex-direction: column; overflow: hidden; position: relative; width: 100%; max-width: 550px; max-height: 90vh; will-change: transform; }
.dark .twx-modal-box { background: #000; box-shadow: 0 10px 40px rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
.twx-modal-box.sm { max-width: 340px; padding: 32px 24px 24px; text-align: center; }
.twxx-modal-overlay.active .twx-modal-box { transform: scale(1) translateY(0); }
.twx-del-title { font-size: 20px; font-weight: 800; color: var(--x-black, #0f1419); margin-bottom: 12px; margin-top: 0;}
.dark .twx-del-title { color: #e7e9ea; }
.twx-del-desc { font-size: 15px; color: var(--x-gray, #536471); margin-bottom: 24px; line-height: 1.5; margin-top: 0;}
.twx-btn-save { background: var(--x-black, #0f1419); color: var(--x-bg, #fff); border: none; padding: 12px 20px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
.dark .twx-btn-save { background: #eff3f4; color: #0f1419; }
.twx-btn-save:hover { opacity: 0.8; }
.twx-btn-primary { background: var(--x-blue, #1d9bf0); color: #fff; }
.dark .twx-btn-primary { background: var(--x-blue, #1d9bf0); color: #fff; }
.twx-btn-primary:hover { background: #1a8cd8; }
.twx-btn-cancel { background: transparent; color: var(--x-black, #0f1419); border: 1px solid var(--x-border, #eff3f4); padding: 12px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; width: 100%; }
.dark .twx-btn-cancel { color: #e7e9ea; border-color: #536471; }
.twx-btn-cancel:hover { background: rgba(15,20,25,0.1); }
.dark .twx-btn-cancel:hover { background: rgba(255,255,255,0.1); }
</style>

<div id="twxx-login-modal" class="twxx-modal-overlay" onclick="closeModal('twxx-login-modal')">
    <div class="twx-modal-box sm" onclick="event.stopPropagation()">
        <h2 class="twx-del-title">ورود به آتوکس</h2>
        <p class="twx-del-desc">برای ارسال پست، لایک و تعامل با دیگران، وارد حساب خود شوید.</p>
        <button class="twx-btn-save twx-btn-primary" style="width: 100%; justify-content:center; margin-bottom:10px" onclick="location.href='auth.php'"><span>ورود / ثبت‌نام</span></button>
        <button class="twx-btn-cancel" onclick="closeModal('twxx-login-modal')">انصراف</button>
    </div>
</div>
<?php endif; ?>

<script>
const IS_LOGGED = <?= $is_logged_in ? 'true' : 'false' ?>;

function requireLogin(event) {
    if(!IS_LOGGED) { 
        if(event) {
            event.preventDefault();
            event.stopPropagation();
        }
        openModal('twxx-login-modal'); 
        return false; 
    }
    return true;
}

function openModal(id) {
    const modal = document.getElementById(id);
    if(modal) {
        modal.style.display = 'flex';
        void modal.offsetWidth;
        modal.classList.add('active');
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if(modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 250); 
    }
}

function oM(id) { openModal('twxx-login-modal'); }
function tgM(id) { closeModal('twxx-login-modal'); }
</script>
