<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>تنظیمات حساب - آتوییتر</title>
<script>if(localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
<style>
@font-face { font-family: 'MyCustomFont'; src: url('fonts/font.ttf') format('truetype'); font-weight: normal; font-style: normal; font-display: swap; }
@font-face { font-family: 'MyCustomFont'; src: url('fonts/font-bold.ttf') format('truetype'); font-weight: bold; font-style: normal; font-display: swap; }
* { margin:0; padding:0; box-sizing:border-box; font-family: 'MyCustomFont', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; }	
:root { --x-blue:#1d9bf0; --x-black:#0f1419; --x-gray:#536471; --x-border:#eff3f4; --x-bg:#fff; --x-hover:rgba(15,20,25,0.05); --modal-bg:rgba(0,0,0,0.6); }
.dark { --x-black:#e7e9ea; --x-gray:#71767b; --x-border:#2f3336; --x-bg:#000; --x-hover:rgba(255,255,255,0.05); --modal-bg:rgba(255,255,255,0.15); }
body{background:var(--x-bg);color:var(--x-black); overflow-x:hidden;}
.app{display:flex;justify-content:center;min-height:100vh;max-width:1250px;margin:0 auto}
.main{width:100%;max-width:480px;border-left:1px solid var(--x-border);border-right:1px solid var(--x-border);padding-bottom:100px; position:relative;}
@media (max-width: 600px) { .main { border-left:none !important; border-right:none !important; } }
.sticky-top-area { position:sticky; top:0; z-index:40; background:rgba(255,255,255,0.85); backdrop-filter:blur(12px); border-bottom:1px solid var(--x-border); }
.dark .sticky-top-area { background:rgba(0,0,0,0.85); }
.hdr { padding:15px 20px; display:flex; align-items:center; justify-content:space-between; font-size:20px; font-weight:bold; }
.hdr-right {display:flex; align-items:center; gap:20px;}
.set-tabs {display:flex;}
.set-tab {flex:1; padding:15px; text-align:center; font-size:15px; font-weight:bold; color:var(--x-gray); cursor:pointer; transition:0.2s; border-bottom:3px solid transparent;}
.set-tab.active {color:var(--x-black); border-bottom-color:var(--x-blue);}
.set-tab:hover {background:var(--x-hover);}
.set-pane {display:none; padding:20px;}
.set-pane.active {display:block; animation:fadeIn 0.3s;}
.inp-wrap { position: relative; margin-bottom: 15px; }
.inp-wrap .f-inp { margin-bottom: 0; padding-left: 55px; } 
.counter { position: absolute; left: 12px; bottom: 14px; font-size: 12px; color: var(--x-gray); direction: ltr; font-weight: bold; }
.inp-wrap input ~ .counter { top: 50%; bottom: auto; transform: translateY(-50%); }
.inp-wrap textarea ~ .counter { bottom: 14px; top: auto; transform: none; }
#in-user { padding-left: 80px; } 
.status-icon { position: absolute; left: 52px; top: 50%; bottom: auto; transform: translateY(-50%); font-size: 14px; display:flex; align-items:center; justify-content:center; }
.f-inp{width:100%;padding:14px 16px;background:var(--x-bg);border:1px solid var(--x-border);border-radius:8px;color:var(--x-black);outline:0;font-size:15px;transition:0.2s;}
.f-inp:focus{border-color:var(--x-blue); box-shadow: 0 0 0 1px var(--x-blue);}
.f-inp:disabled, .f-inp[readonly] {background:var(--x-hover); cursor:not-allowed; opacity:0.8;}
.btn{background:var(--x-blue);color:#fff;padding:12px 24px;border-radius:9999px;font-weight:700;font-size:15px;transition:.2s; border:none; cursor:pointer; display:inline-block; text-align:center;}
.btn:hover{background:#1a8cd8}
.btn-red{background:transparent;color:#f91880;border:1px solid var(--x-border);padding:12px 24px; border-radius:99px; cursor:pointer; font-weight:bold; width:100%; transition:0.2s;}
.btn-red:hover{background:rgba(249, 24, 128, 0.1); border-color:#f91880;}
.btn-out{background:transparent;color:var(--x-black);border:1px solid var(--x-border);padding:12px 24px; border-radius:99px; cursor:pointer; font-weight:bold; width:100%; transition:0.2s;}
.btn-out:hover{background:var(--x-hover);}
.lbl-s {font-size:14px; font-weight:bold; margin:20px 0 10px; display:block; color:var(--x-black);}
.s-row {display:flex; gap:10px; margin-bottom:10px;}
.ck-pills {display:flex; flex-wrap:wrap; gap:8px;}
.ck-lbl {cursor:pointer;}
.ck-lbl input {display:none;}
.ck-lbl span {display:inline-block; padding:8px 16px; border-radius:99px; border:1px solid var(--x-border); font-size:13px; color:var(--x-gray); transition:0.2s;}
.ck-lbl input:checked + span {background:var(--x-blue); color:#fff; border-color:var(--x-blue);}
.file-upload-box { border: 2px dashed var(--x-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; margin-bottom:15px; position:relative; overflow:hidden; transition:0.2s;}
.file-upload-box:hover { border-color: var(--x-blue); background: var(--x-hover); }
.file-upload-box input { position:absolute; inset:0; opacity:0; cursor:pointer; }
.err-msg { color: #f91880; font-size: 12px; margin-top: -10px; margin-bottom: 15px; display: none; font-weight:bold; }
.m-alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; text-align: center; font-size: 13px; font-weight: bold; }
.m-alert-err { background: rgba(244, 33, 46, 0.15); color: #f4212e; border: 1px solid rgba(244, 33, 46, 0.3); }
.m-alert-suc { background: rgba(0, 186, 124, 0.15); color: #00ba7c; border: 1px solid rgba(0, 186, 124, 0.3); }
.modal-overlay {position:fixed; top:0; left:0; right:0; bottom:0; background:var(--modal-bg); backdrop-filter:blur(5px); z-index:100; display:none; align-items:center; justify-content:center; transition:0.3s;}
.modal-overlay.active {display:flex; animation:fadeIn 0.2s;}
.modal-content {background:var(--x-bg); width:90%; max-width:420px; border-radius:20px; padding:25px; box-shadow:0 15px 35px rgba(0,0,0,0.2); position:relative;}
.modal-close {position:absolute; top:20px; left:20px; background:var(--x-hover); border:none; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; color:var(--x-black); cursor:pointer; transition:0.2s;}
.modal-close:hover {background:rgba(249, 24, 128, 0.1); color:#f91880;}
.modal-close svg {width:18px; height:18px; fill:currentColor;}
.modal-title {font-size: 20px; font-weight: bold; margin-bottom: 20px; text-align: center; color: var(--x-black);}
.block-item {display:flex; justify-content:space-between; align-items:center; padding:15px 0; border-bottom:1px solid var(--x-border);}
.block-item:last-child {border-bottom:none;}
.bot-guide { color: var(--x-gray); line-height: 1.6; margin-bottom: 15px; text-align: center; font-size: 13px; }
.btn-bale { background-color: #20C28A; color: #fff; margin-bottom: 15px; width:100%; display:flex; justify-content:center; align-items:center; gap:8px; padding:12px 24px; border-radius:9999px; font-weight:bold; text-decoration:none; transition:0.2s;}
.btn-bale:hover { background-color: #1aa072; }
.sess-item { display:flex; justify-content:space-between; align-items:center; padding:15px; border:1px solid var(--x-border); border-radius:12px; margin-bottom:10px; transition:0.3s; }
.sess-info { display:flex; flex-direction:column; gap:5px; }
.sess-browser { font-weight:bold; font-size:14px; color:var(--x-black); display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.sess-meta { font-size:12px; color:var(--x-gray); }
.badge-current { background:rgba(0, 186, 124, 0.15); color:#00ba7c; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:bold; border:1px solid rgba(0, 186, 124, 0.3); }
@keyframes fadeIn { from{opacity:0; transform:scale(0.95);} to{opacity:1; transform:scale(1);} }
.status-icon-svg { width: 18px; height: 18px; animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
.pulse-loader { width: 14px; height: 14px; border-radius: 50%; background: var(--x-blue); animation: pulseAnim 0.8s infinite alternate; box-shadow: 0 0 8px rgba(29, 155, 240, 0.5); }
@keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes pulseAnim { 0% { transform: scale(0.6); opacity: 0.5; } 100% { transform: scale(1.2); opacity: 1; } }
</style>
</head>
<body>
<?php if(file_exists('header.php')) include 'header.php'; ?>

<div class="app">
    <main class="main">
        <div class="sticky-top-area">
            <div class="hdr">
                <div class="hdr-right">
                    <button onclick="history.back()" style="background:0 0; border:0; cursor:pointer; color:inherit; display:flex;">
                        <svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:currentColor"><path d="M7.414 13l5.043 5.04-1.414 1.42L3.586 12l7.457-7.46 1.414 1.42L7.414 11H21v2H7.414z"></path></svg>
                    </button>
                    <span>تنظیمات حساب</span>
                </div>
            </div>

            <div class="set-tabs">
                <div class="set-tab active" onclick="sTab('s-prof', this)">پروفایل</div>
                <div class="set-tab" onclick="sTab('s-res', this)">رزومه</div>
                <div class="set-tab" onclick="sTab('s-sec', this)">امنیت</div>
            </div>
        </div>

        <div id="s-prof" class="set-pane active">
            <form action="actions.php?action=edit_profile" method="POST" enctype="multipart/form-data" onsubmit="return validateProfile(event)">
                <label class="lbl-s">تغییر عکس کاور (حداکثر 500KB)</label>
                <div class="file-upload-box">
                    <span id="c-name" style="color:var(--x-gray);">برای انتخاب عکس کاور کلیک کنید</span>
                    <input type="file" name="cover" id="f-cover" accept="image/jpeg,image/png,image/webp,image/gif" onchange="updFile(this, 'c-name', 'err-c')">
                </div>
                <div id="err-c" class="err-msg">حجم فایل بیش از 500 کیلوبایت است!</div>

                <label class="lbl-s">تغییر عکس آواتار (حداکثر 500KB)</label>
                <div class="file-upload-box">
                    <span id="a-name" style="color:var(--x-gray);">برای انتخاب آواتار کلیک کنید</span>
                    <input type="file" name="avatar" id="f-avatar" accept="image/jpeg,image/png,image/webp,image/gif" onchange="updFile(this, 'a-name', 'err-a')">
                </div>
                <div id="err-a" class="err-msg">حجم فایل بیش از 500 کیلوبایت است!</div>

                <label class="lbl-s">اطلاعات شخصی</label>
                <div class="inp-wrap">
                    <input type="text" name="name" id="in-name" value="<?=htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="نام نمایشی" required maxlength="20" oninput="countChar(this, 'cnt-name', 20)">
                    <span class="counter" id="cnt-name">0/20</span>
                </div>
                <div class="inp-wrap">
                    <input type="text" name="username" id="in-user" value="<?=htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="نام کاربری" required dir="ltr" pattern="^[a-zA-Z][a-zA-Z0-9._]{3,18}[a-zA-Z0-9]$" title="آیدی باید با حرف شروع شود، حداقل ۵ کاراکتر باشد و با نقطه یا خط‌فاصله تمام نشود" maxlength="20" oninput="countChar(this, 'cnt-user', 20); checkUsername(this.value)">
                    <span class="status-icon" id="st-user"></span>
                    <span class="counter" id="cnt-user">0/20</span>
                </div>
                <div class="inp-wrap">
                    <textarea name="bio" id="in-bio" class="f-inp" placeholder="بیوگرافی" rows="3" style="resize:none" maxlength="120" oninput="countChar(this, 'cnt-bio', 120)"><?=htmlspecialchars($user['bio'] ?? '', ENT_QUOTES, 'UTF-8')?></textarea>
                    <span class="counter" id="cnt-bio">0/120</span>
                </div>
                <button type="submit" id="submit-prof" class="btn" style="width:100%;">ذخیره اطلاعات پروفایل</button>
            </form>
        </div>

        <div id="s-res" class="set-pane">
            <form action="actions.php?action=edit_resume" method="POST" onsubmit="return validateResume(event)">
                <span class="lbl-s">اطلاعات پایه</span>
                <div class="inp-wrap">
                    <input type="text" name="job" id="in-job" value="<?=htmlspecialchars($resume['job'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="شغل (مثلاً برنامه‌نویس وب)" maxlength="20" oninput="countChar(this, 'cnt-job', 20)">
                    <span class="counter" id="cnt-job">0/20</span>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="inp-wrap" style="flex:1;">
                        <input type="text" name="university" id="in-uni" value="<?=htmlspecialchars($resume['university'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="نام دانشگاه" maxlength="70" oninput="countChar(this, 'cnt-uni', 70)">
                        <span class="counter" id="cnt-uni">0/70</span>
                    </div>
                    <div class="inp-wrap" style="flex:1;">
                        <input type="text" name="education" id="in-edu" value="<?=htmlspecialchars($resume['education'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="مدرک تحصیلی" maxlength="50" oninput="countChar(this, 'cnt-edu', 50)">
                        <span class="counter" id="cnt-edu">0/50</span>
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="inp-wrap" style="flex:1;">
                        <input type="text" name="birth_year" value="<?=htmlspecialchars($resume['birth_year'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="سال تولد (مثلا ۱۳۷۵)" maxlength="4" pattern="\d{4}" title="فقط ۴ رقم مجاز است">
                    </div>
                    <div class="inp-wrap" style="flex:1;">
                        <input type="text" name="location" id="in-loc" value="<?=htmlspecialchars($resume['location'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="محل سکونت" maxlength="50" oninput="countChar(this, 'cnt-loc', 50)">
                        <span class="counter" id="cnt-loc">0/50</span>
                    </div>
                </div>
                <div class="inp-wrap">
                    <input type="email" name="email" id="in-email" value="<?=htmlspecialchars($resume['email'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="ایمیل کاری" dir="ltr" maxlength="100" oninput="countChar(this, 'cnt-email', 100)">
                    <span class="counter" id="cnt-email">0/100</span>
                </div>
                <div class="inp-wrap">
                    <input type="url" name="linkedin" id="in-link" value="<?=htmlspecialchars($resume['linkedin'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="لینک پروفایل لینکدین" dir="ltr" maxlength="100" oninput="countChar(this, 'cnt-link', 100)">
                    <span class="counter" id="cnt-link">0/100</span>
                </div>

                <span class="lbl-s">مهارت‌های تخصصی (تا ۵ مهارت)</span>
                <?php for($i=0; $i<5; $i++): $sk = $r_skills[$i] ?? ['name'=>'', 'percent'=>'']; ?>
                <div class="s-row">
                    <div class="inp-wrap" style="flex:2; margin-bottom:0;">
                        <input type="text" name="s_name[]" id="in-sk<?=$i?>" value="<?=htmlspecialchars($sk['name'], ENT_QUOTES, 'UTF-8')?>" class="f-inp" placeholder="نام مهارت" maxlength="30" oninput="countChar(this, 'cnt-sk<?=$i?>', 30)">
                        <span class="counter" id="cnt-sk<?=$i?>">0/30</span>
                    </div>
                    <input type="number" name="s_pct[]" value="<?=htmlspecialchars((string)$sk['percent'], ENT_QUOTES, 'UTF-8')?>" class="f-inp" style="flex:1; margin-bottom:0;" placeholder="درصد" min="1" max="100">
                </div>
                <?php endfor; ?>

                <span class="lbl-s">مهارت‌های نرم (انتخاب حداکثر ۳ مورد)</span>
                <div class="ck-pills">
                    <?php foreach($predefined_soft_skills as $psk): $is_chk = in_array($psk, $r_soft); ?>
                    <label class="ck-lbl">
                        <input type="checkbox" name="soft_skills[]" value="<?=htmlspecialchars($psk, ENT_QUOTES, 'UTF-8')?>" class="soft-chk" <?=$is_chk?'checked':''?>>
                        <span><?=htmlspecialchars($psk, ENT_QUOTES, 'UTF-8')?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <button class="btn" style="width:100%; margin-top:20px;">ذخیره اطلاعات رزومه</button>
            </form>
        </div>

        <div id="s-sec" class="set-pane">
            <form action="actions.php?action=change_password" method="POST" style="margin-bottom:30px;">
                <span class="lbl-s">تغییر رمز عبور</span>
                <div class="inp-wrap"><input type="password" name="old_password" class="f-inp" placeholder="رمز عبور فعلی" required></div>
                <div class="inp-wrap"><input type="password" name="new_password" class="f-inp" placeholder="رمز عبور جدید (حداقل ۸ حرف)" required pattern="[a-zA-Z0-9_]{8,}" title="فقط حروف a-z، اعداد 0-9 و _ مجاز است (حداقل ۸ کاراکتر)"></div>
                <button class="btn" style="width:100%;">تغییر رمز عبور</button>
            </form>

            <span class="lbl-s">مدیریت شماره و بلاک‌لیست</span>
            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:30px;">
                <button type="button" class="btn btn-out" onclick="openModal('phoneModal')">تغییر شماره موبایل</button>
                <button type="button" class="btn btn-out" onclick="openModal('blockListModal')">لیست کاربران مسدود شده</button>
            </div>

            <span class="lbl-s">دستگاه‌ها و نشست‌های فعال</span>
            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:30px;">
                <button type="button" class="btn btn-out" onclick="openModal('sessionListModal')">مدیریت دستگاه‌های متصل</button>
            </div>

            <span class="lbl-s">مدیریت حساب</span>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <form action="actions.php" method="POST">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn btn-out">خروج از حساب</button>
                </form>
                <form action="actions.php?action=delete_account" method="POST">
                    <input type="hidden" name="confirm_delete" value="1">
                    <button class="btn btn-red" onclick="return confirm('آیا از حذف دائم اکانت مطمئن هستید؟')">حذف اکانت</button>
                </form>
            </div>
        </div>
    </main>
</div>

<div class="modal-overlay <?= $open_phone_modal ? 'active' : '' ?>" id="phoneModal">
    <div class="modal-content">
        <button type="button" class="modal-close" onclick="closeModal('phoneModal')"><svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
        <div class="modal-title">تغییر شماره موبایل</div>
        
        <?php if($phone_error): ?> <div class="m-alert m-alert-err"><?= htmlspecialchars($phone_error, ENT_QUOTES, 'UTF-8') ?></div> <?php endif; ?>
        <?php if($phone_success): ?> <div class="m-alert m-alert-suc"><?= htmlspecialchars($phone_success, ENT_QUOTES, 'UTF-8') ?></div> <?php endif; ?>

        <?php if(!isset($_SESSION['temp_new_phone'])): ?>
            <p style="font-size:14px; color:var(--x-gray); margin-bottom:15px; text-align:center;">شماره فعلی: <?= htmlspecialchars($user['phone'] ?? 'ثبت نشده', ENT_QUOTES, 'UTF-8') ?></p>
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="request_phone_change">
                <div class="inp-wrap"><input type="text" name="new_phone" class="f-inp" placeholder="شماره موبایل جدید (09...)" dir="ltr" required pattern="09[0-9]{9}"></div>
                <button type="submit" class="btn" style="width:100%;">دریافت کد تایید</button>
            </form>
        <?php else: ?>
            <p style="font-size:14px; color:var(--x-gray); margin-bottom:15px; text-align:center;">کد ارسال شده به ربات را برای شماره <?= htmlspecialchars($_SESSION['temp_new_phone'], ENT_QUOTES, 'UTF-8') ?> وارد کنید</p>
            
            <div class="bot-guide">برای دریافت کد، در ربات بله ما عضو شوید:</div>
            <a href="https://ble.ir/Atoxbot" target="_blank" class="btn-bale">
                <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor;"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg> ورود به ربات بله
            </a>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="verify_phone_change">
                <div class="inp-wrap"><input type="text" name="otp" class="f-inp" placeholder="کد ۵ رقمی" dir="ltr" required pattern="[0-9]{5}" style="text-align:center; letter-spacing:10px; font-weight:bold; font-size:20px;"></div>
                <button type="submit" class="btn" style="width:100%; margin-bottom:10px;">تایید و تغییر شماره</button>
            </form>
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="cancel_phone_change">
                <button type="submit" class="btn btn-out" style="width:100%;">تغییر شماره / لغو</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay <?= $open_block_modal ? 'active' : '' ?>" id="blockListModal">
    <div class="modal-content" style="max-height:80vh; overflow-y:auto;">
        <button type="button" class="modal-close" onclick="closeModal('blockListModal')"><svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
        <div class="modal-title">کاربران مسدود شده</div>
        
        <?php if(empty($blocked_users)): ?>
            <p style="text-align:center; color:var(--x-gray); font-size:14px; padding:20px 0;">هیچ کاربری در لیست مسدودی شما نیست.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column;">
                <?php foreach($blocked_users as $b_user): ?>
                    <div class="block-item">
                        <div style="display:flex; flex-direction:column; gap:3px;">
                            <strong style="font-size:15px; color:var(--x-black);"><?= htmlspecialchars($b_user['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span style="font-size:13px; color:var(--x-gray);" dir="ltr">@<?= htmlspecialchars($b_user['username'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <form method="POST" action="settings.php">
                            <input type="hidden" name="action" value="unblock_user_internal">
                            <input type="hidden" name="blocked_id" value="<?= (int)$b_user['blocked_id'] ?>">
                            <button class="btn btn-out" style="padding:6px 14px; font-size:13px; width:auto;">رفع مسدودی</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="sessionListModal">
    <div class="modal-content" style="max-height:80vh; overflow-y:auto; max-width:500px;">
        <button type="button" class="modal-close" onclick="closeModal('sessionListModal')"><svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
        <div class="modal-title">دستگاه‌های فعال شما</div>
        
        <?php if(empty($active_sessions)): ?>
            <p style="text-align:center; color:var(--x-gray); font-size:14px; padding:20px 0;">هیچ دستگاه فعالی یافت نشد.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column;">
                <?php foreach($active_sessions as $sess): 
                    $is_current = ($sess['session_id'] === $current_session_id);
                ?>
                    <div class="sess-item" id="sess-<?= (int)$sess['id'] ?>">
                        <div class="sess-info">
                            <div class="sess-browser">
                                <span dir="ltr"><?= htmlspecialchars(substr($sess['user_agent'], 0, 35), ENT_QUOTES, 'UTF-8') ?>...</span>
                                <?php if($is_current): ?><span class="badge-current">دستگاه فعلی</span><?php endif; ?>
                            </div>
                            <div class="sess-meta">
                                IP: <span dir="ltr"><?= htmlspecialchars($sess['ip_address'], ENT_QUOTES, 'UTF-8') ?></span> • 
                                آخرین فعالیت: <span dir="ltr"><?= htmlspecialchars(date('Y/m/d H:i', strtotime($sess['last_active'])), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                        <?php if(!$is_current): ?>
                            <?php if($can_revoke_others): ?>
                                <button type="button" class="btn btn-red" style="padding:6px 14px; font-size:13px; width:auto;" onclick="revokeDeviceSession(<?= (int)$sess['id'] ?>, this)">خروج</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-red" style="padding:6px 14px; font-size:13px; width:auto; opacity:0.5;" onclick="alert('شما تنها با قدیمی ترین نشست فعال میتوانید بقیه را بیرون کنید.')">خروج</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>


<script>
if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }

function countChar(input, counterId, max) {
    let len = input.value.length;
    document.getElementById(counterId).innerText = len + '/' + max;
}

let unTimer;
function checkUsername(val) {
    clearTimeout(unTimer);
    const icon = document.getElementById('st-user');
    const btnSubmit = document.getElementById('submit-prof');
    
    btnSubmit.disabled = true;
    btnSubmit.style.opacity = '0.5';
    btnSubmit.style.cursor = 'not-allowed';

    const regex = /^[a-zA-Z][a-zA-Z0-9._]{3,18}[a-zA-Z0-9]$/;
    const errIcon = '<svg class="status-icon-svg" style="fill:#f91880" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.59 13.17l-1.41 1.41L12 13.41l-3.17 3.17-1.41-1.41L10.59 12 7.41 8.83l1.41-1.41L12 10.59l3.17-3.17 1.41 1.41L13.41 12l3.17 3.17z"/></svg>';
    const okIcon = '<svg class="status-icon-svg" style="fill:#00ba7c" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
    const loadIcon = '<div class="pulse-loader"></div>';
    
    if (!regex.test(val) || val.length > 20) {
        icon.innerHTML = errIcon;
        return;
    }

    icon.innerHTML = loadIcon;

    unTimer = setTimeout(() => {
        fetch('settings.php?check_username=' + encodeURIComponent(val))
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                icon.innerHTML = okIcon;
                btnSubmit.disabled = false;
                btnSubmit.style.opacity = '1';
                btnSubmit.style.cursor = 'pointer';
            } else {
                icon.innerHTML = errIcon;
            }
        }).catch(() => {
            icon.innerHTML = errIcon;
        });
    }, 600);
}

function validateProfile(e) {
    if(!checkFiles()) { e.preventDefault(); return false; }
    
    const user = document.getElementById('in-user').value;
    const name = document.getElementById('in-name').value;
    const bio = document.getElementById('in-bio').value;
    
    const userRegex = /^[a-zA-Z][a-zA-Z0-9._]{3,18}[a-zA-Z0-9]$/;
    
    if(!userRegex.test(user) || user.length > 20) {
        alert('نام کاربری نامعتبر است یا طول آن بیش از ۲۰ کاراکتر است.');
        e.preventDefault(); return false;
    }
    if(name.length > 20) {
        alert('نام نمایشی نمی‌تواند بیش از ۲۰ کاراکتر باشد.');
        e.preventDefault(); return false;
    }
    if(bio.length > 120) {
        alert('بیوگرافی نمی‌تواند بیش از ۱۲۰ کاراکتر باشد.');
        e.preventDefault(); return false;
    }
    
    return true;
}

function validateResume(e) {
    const job = document.getElementById('in-job').value;
    if(job.length > 20) { alert('شغل نباید بیشتر از ۲۰ حرف باشد.'); e.preventDefault(); return false; }
    return true;
}

window.addEventListener('DOMContentLoaded', () => {
    countChar(document.getElementById('in-name'), 'cnt-name', 20);
    countChar(document.getElementById('in-user'), 'cnt-user', 20);
    countChar(document.getElementById('in-bio'), 'cnt-bio', 120);
    checkUsername(document.getElementById('in-user').value);

    countChar(document.getElementById('in-job'), 'cnt-job', 20);
    countChar(document.getElementById('in-uni'), 'cnt-uni', 70);
    countChar(document.getElementById('in-edu'), 'cnt-edu', 50);
    countChar(document.getElementById('in-loc'), 'cnt-loc', 50);
    countChar(document.getElementById('in-email'), 'cnt-email', 100);
    countChar(document.getElementById('in-link'), 'cnt-link', 100);
    for(let i=0; i<5; i++){
        const el = document.getElementById('in-sk'+i);
        if(el) countChar(el, 'cnt-sk'+i, 30);
    }
});

function sTab(id, el) {
    document.querySelectorAll('.set-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.set-tab').forEach(i => i.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    el.classList.add('active');
}

function updFile(input, txtId, errId) {
    const file = input.files[0];
    const errEl = document.getElementById(errId);
    if(file) {
        if(file.size > 500 * 1024) {
            errEl.style.display = 'block';
            input.value = ''; 
            document.getElementById(txtId).innerText = 'فایل نامعتبر است';
        } else {
            errEl.style.display = 'none';
            document.getElementById(txtId).innerText = file.name;
        }
    }
}

function checkFiles() {
    const cSize = document.getElementById('f-cover').files[0]?.size || 0;
    const aSize = document.getElementById('f-avatar').files[0]?.size || 0;
    if(cSize > 500*1024 || aSize > 500*1024) {
        alert("لطفا حجم فایل‌ها را زیر ۵۰۰ کیلوبایت نگه دارید.");
        return false;
    }
    return true;
}

const maxChecks = 3;
document.querySelectorAll('.soft-chk').forEach(chk => {
    chk.addEventListener('change', function() {
        if (document.querySelectorAll('.soft-chk:checked').length > maxChecks) {
            this.checked = false;
            alert('شما حداکثر می‌توانید ۳ مهارت نرم انتخاب کنید.');
        }
    });
});

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.onclick = function(event) { if (event.target.classList.contains('modal-overlay')) event.target.classList.remove('active'); }

function revokeDeviceSession(sessionId, btnElement) {
    if(!confirm('آیا مطمئن هستید که می‌خواهید دسترسی این دستگاه را قطع کنید؟')) return;
    
    const originalText = btnElement.innerText;
    btnElement.innerText = 'صبر کنید...';
    btnElement.disabled = true;

    const formData = new FormData();
    formData.append('action', 'revoke_session');
    formData.append('session_id', sessionId);

    fetch('actions.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            const item = document.getElementById('sess-' + sessionId);
            item.style.opacity = '0';
            setTimeout(() => { item.remove(); }, 300);
        } else {
            alert(data.error || 'خطایی رخ داد.');
            btnElement.innerText = originalText;
            btnElement.disabled = false;
        }
    })
    .catch(err => {
        alert('خطا در برقراری ارتباط با سرور.');
        btnElement.innerText = originalText;
        btnElement.disabled = false;
    });
}

<?php if((isset($_POST['action']) && strpos($_POST['action'], 'phone') !== false) || $open_block_modal): ?>
    sTab('s-sec', document.querySelectorAll('.set-tab')[2]);
<?php endif; ?>
</script>
<?php if(file_exists('footer.php')) include 'footer.php'; ?>

</body>
</html>
