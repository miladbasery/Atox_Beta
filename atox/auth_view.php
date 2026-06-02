<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>ورود و ثبت نام در آتوکس</title>
<script>
    if (localStorage.getItem('theme') === 'dark') { document.documentElement.classList.add('dark'); }
</script>
<style>
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

    :root {
        --bg-color: #ffffff; --text-color: #0f1419; --primary-color: #1d9bf0;
        --primary-hover: #1a8cd8; --border-color: #eff3f4; --input-bg: #f7f9f9;
        --gray-text: #536471; --error-bg: rgba(244, 33, 46, 0.15); --error-text: #f4212e;
        --success-bg: rgba(0, 186, 124, 0.15); --success-text: #00ba7c;
        --bale-color: #20C28A; --bale-hover: #1aa072;
    }
    html.dark {
        --bg-color: #000000; --text-color: #ffffff; --border-color: #38444d;
        --input-bg: #22303c; --gray-text: #8899a6;
    }
    body {
        background-color: var(--bg-color); color: var(--text-color);
        display: flex; align-items: center; justify-content: center;
        min-height: 100vh; margin: 0; transition: background-color 0.3s, color 0.3s;
    }

    .container { width: 100%; max-width: 400px; padding: 15px 20px; display: flex; flex-direction: column; z-index: 10; }
    
    .logo-box { text-align: center; margin-bottom: 15px; display: flex; flex-direction: column; align-items: center; }
    .logo-box svg { width: 45px; height: 45px; fill: var(--text-color); transition: fill 0.3s; }
    .logo-box img { width: 45px; height: auto; display: block; margin: 0 auto; margin-bottom: 5px; }
    .logo-box h1 { font-size: 22px; font-weight: 800; margin: 0 0 2px 0; letter-spacing: -1px; }
    .logo-box p { color: var(--gray-text); font-size: 13px; margin: 0; transition: color 0.3s; }

    .alert { padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; text-align: center; font-size: 13px; font-weight: bold; }
    .alert-error { background-color: var(--error-bg); color: var(--error-text); border: 1px solid rgba(244, 33, 46, 0.3); }
    .alert-success { background-color: var(--success-bg); color: var(--success-text); border: 1px solid rgba(0, 186, 124, 0.3); }

    .form-wrapper { display: none; animation: fadeIn 0.4s ease; }
    .form-wrapper.active { display: block; }
    .inner-form { display: none; }
    .inner-form.active { display: block; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .tabs { display: flex; background: var(--input-bg); border-radius: 8px; padding: 4px; margin-bottom: 15px; transition: background 0.3s; }
    .tab-btn { flex: 1; padding: 8px; text-align: center; cursor: pointer; border-radius: 6px; font-size: 13px; font-weight: bold; color: var(--gray-text); transition: 0.3s; }
    .tab-btn.active { background: var(--bg-color); color: var(--text-color); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    html.dark .tab-btn.active { box-shadow: 0 2px 5px rgba(255,255,255,0.05); }

    input[type="text"], input[type="password"], input[type="tel"] {
        width: 100%; padding: 12px; margin-bottom: 10px; background-color: var(--input-bg);
        border: 1px solid var(--border-color); color: var(--text-color); border-radius: 6px;
        font-size: 14px; outline: none; transition: 0.2s;
    }
    input[type="text"]:focus, input[type="password"]:focus, input[type="tel"]:focus { border-color: var(--primary-color); background-color: transparent; }
    input::placeholder { color: var(--gray-text); }

    .privacy-check {
        display: flex; align-items: flex-start; gap: 8px; margin-bottom: 10px; font-size: 12px; color: var(--gray-text); text-align: justify;
    }
    .privacy-check input[type="checkbox"] {
        width: 16px; height: 16px; margin-top: 2px; cursor: pointer; flex-shrink: 0; accent-color: var(--primary-color);
    }
    .privacy-check a { color: var(--primary-color); cursor: pointer; font-weight: bold; text-decoration: none; }
    .privacy-check a:hover { text-decoration: underline; }

    .captcha-wrap { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
    .captcha-wrap input { margin-bottom: 0; flex: 1; text-align: center; letter-spacing: 2px; font-family: monospace; }
    .captcha-wrap img { border-radius: 4px; user-select: none; border: 1px solid var(--border-color); background-color: var(--input-bg); }

    .forgot-link { text-align: left; margin-top: -3px; margin-bottom: 10px; direction: ltr; }
    .forgot-link a { font-size: 12px; color: var(--primary-color); cursor: pointer; font-weight: bold; transition: opacity 0.2s; }
    .forgot-link a:hover { opacity: 0.8; text-decoration: underline; }

    .btn {
        width: 100%; padding: 12px; border: none; border-radius: 9999px;
        font-size: 14px; font-weight: bold; cursor: pointer; transition: 0.2s; margin-top: 5px;
        display: flex; align-items: center; justify-content: center; text-decoration: none; gap: 8px;
    }
    .btn-primary { background-color: var(--primary-color); color: #fff; }
    .btn-primary:hover { background-color: var(--primary-hover); }

    .btn-guest { background-color: transparent; color: var(--text-color); border: 1px solid var(--border-color); margin-top: 10px; }
    .btn-guest:hover { background-color: rgba(128, 128, 128, 0.1); border-color: var(--gray-text); }
    .btn-guest svg { width: 18px; height: 18px; fill: currentColor; }

    .btn-bale { background-color: var(--bale-color); color: #fff; padding: 10px; font-size: 14px; flex: 1;}
    .btn-bale:hover { background-color: var(--bale-hover); }
    .btn-bale svg { width: 20px; height: 20px; fill: #fff; }

    .divider { display: flex; align-items: center; text-align: center; margin: 15px 0 10px 0; color: var(--gray-text); font-size: 13px; }
    .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--border-color); }
    .divider::before { margin-left: .5em; } .divider::after { margin-right: .5em; }

    .switch-link { text-align: center; color: var(--gray-text); font-size: 13px; margin-top: 15px; }
    .switch-link a { color: var(--primary-color); text-decoration: none; cursor: pointer; font-weight: bold; }
    .switch-link a:hover { text-decoration: underline; }

    .bot-guide { color: var(--gray-text); line-height: 1.6; margin-bottom: 10px; text-align: center; font-size: 13px; }

    .otp-wrapper { display: flex; justify-content: space-between; gap: 6px; margin-bottom: 10px; direction: ltr; }
    .otp-wrapper input { 
        width: 100%; height: 45px; text-align: center; font-size: 20px; font-weight: bold; 
        border-radius: 8px; margin-bottom: 0; background-color: var(--input-bg);
        border: 2px solid var(--border-color); transition: all 0.2s; padding: 0;
    }
    .otp-wrapper input:focus { border-color: var(--primary-color); box-shadow: 0 0 10px rgba(29, 155, 240, 0.2); transform: translateY(-2px); background-color: transparent; }
    
    .timer-wrap { text-align: center; font-size: 13px; color: var(--gray-text); margin-bottom: 15px; font-family: monospace; font-weight: bold; }

    .privacy-modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);
        z-index: 99999; display: none; align-items: center; justify-content: center;
        padding: 20px; opacity: 0; transition: opacity 0.3s ease;
    }
    .privacy-modal-overlay.active { display: flex; opacity: 1; }
    .privacy-modal-content {
        background: var(--bg-color); color: var(--text-color);
        border-radius: 16px; width: 100%; max-width: 600px; max-height: 85vh;
        display: flex; flex-direction: column; overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2); transform: translateY(20px); transition: 0.3s ease;
    }
    html.dark .privacy-modal-content { box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
    .privacy-modal-overlay.active .privacy-modal-content { transform: translateY(0); }

    .privacy-modal-header {
        padding: 15px; border-bottom: 1px solid var(--border-color);
        display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 16px;
    }
    .privacy-modal-body {
        padding: 15px; overflow-y: auto; line-height: 1.6; font-size: 13px; text-align: justify;
    }
    .privacy-modal-footer {
        padding: 12px 15px; border-top: 1px solid var(--border-color); text-align: left;
    }
    .privacy-close-btn {
        background: var(--primary-color); color: #fff; border: none;
        padding: 8px 20px; border-radius: 99px; cursor: pointer; font-weight: bold; font-family: inherit; transition: 0.2s; font-size: 13px;
    }
    .privacy-close-btn:hover { background: var(--primary-hover); }
    .privacy-x-btn {
        background: transparent; border: none; font-size: 20px; cursor: pointer; color: inherit; line-height: 1;
    }
</style>
</head>
<body>

<div class="container">
    <div class="logo-box">
        <a href="index.php" class="h-logo">
            <img src="uploads/logo2.png" alt="آتوکس">
        </a>
        <h1>آتوکس</h1>
        <p>آشنا شو / رزومه بساز / آزاد باش</p>
    </div>

    <?php 
    if (!empty($error)) {
        $alertClass = (strpos($error, 'موفق بود') !== false) ? 'alert-success' : 'alert-error';
        echo "<div class='alert $alertClass'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</div>";
    }
    ?>

    <?php if ($step == 'form'): ?>
        <div id="login-box" class="form-wrapper active">
            <div class="tabs">
                <div class="tab-btn active" onclick="switchMethod('login', 'pass')">ورود با آیدی</div>
                <div class="tab-btn" onclick="switchMethod('login', 'otp')">ورود با شماره</div>
            </div>

            <form method="POST" id="login-form-pass" class="inner-form active">
                <input type="hidden" name="action" value="login_pass">
                <input type="text" name="username" placeholder="نام کاربری (آیدی)" dir="ltr" value="<?= htmlspecialchars($val_username, ENT_QUOTES, 'UTF-8') ?>" required>
                <input type="password" name="password" placeholder="رمز عبور" required>
                
                <div class="forgot-link">
                    <a onclick="toggleForms('forgot')">رمز عبور خود را فراموش کرده‌اید؟</a>
                </div>

                <div class="captcha-wrap">
                    <img src="<?= $captcha_base64 ?>" alt="captcha">
                    <input type="text" name="captcha" placeholder="کد تصویر" dir="ltr" autocomplete="off" required>
                </div>
                
                <button class="btn btn-primary">ورود به حساب</button>
            </form>

            <form method="POST" id="login-form-otp" class="inner-form">
                <input type="hidden" name="action" value="login_otp">
                <input type="tel" name="phone" placeholder="شماره موبایل (09...)" dir="ltr" value="<?= htmlspecialchars($val_phone, ENT_QUOTES, 'UTF-8') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                
                <div class="captcha-wrap">
                    <img src="<?= $captcha_base64 ?>" alt="captcha">
                    <input type="text" name="captcha" placeholder="کد تصویر" dir="ltr" autocomplete="off" required>
                </div>

                <button class="btn btn-primary">دریافت کد ورود (بله)</button>
            </form>
            
            <div class="divider">یا</div>
            
            <a href="index.php" class="btn btn-guest">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                ورود به عنوان مهمان 
            </a>
            
            <div class="switch-link">حساب کاربری ندارید؟ <a onclick="toggleForms('register')">ثبت نام کنید</a></div>
        </div>

        <div id="register-box" class="form-wrapper">
            <form method="POST" id="register-form-otp" class="inner-form active">
                <input type="hidden" name="action" value="register_otp">
                <input type="text" name="name" placeholder="نام نمایشی شما" value="<?= htmlspecialchars($val_name, ENT_QUOTES, 'UTF-8') ?>" required>
                <input type="text" name="username" placeholder="نام کاربری (انگلیسی)" dir="ltr" value="<?= htmlspecialchars($val_username, ENT_QUOTES, 'UTF-8') ?>" required>
                <input type="tel" name="phone" placeholder="شماره موبایل (09...)" dir="ltr" value="<?= htmlspecialchars($val_phone, ENT_QUOTES, 'UTF-8') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                <input type="password" name="password" placeholder="رمز عبور" required>
                <input type="password" name="re_password" placeholder="تکرار رمز عبور" required>
                
                <div class="captcha-wrap">
                    <img src="<?= $captcha_base64 ?>" alt="captcha">
                    <input type="text" name="captcha" placeholder="کد تصویر" dir="ltr" autocomplete="off" required>
                </div>

                <div class="privacy-check">
                    <input type="checkbox" name="privacy" id="privacy-reg" required>
                    <label for="privacy-reg">من <a onclick="openPrivacyModal()">سیاست‌نامه حفظ حریم شخصی</a> را مطالعه کرده و موافقم.</label>
                </div>

                <button class="btn btn-primary">دریافت کد ثبت‌نام (بله)</button>
            </form>
            
            <div class="divider">یا</div>
            
            <a href="index.php" class="btn btn-guest">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                ورود به عنوان مهمان
            </a>

            <div class="switch-link">قبلاً ثبت نام کرده‌اید؟ <a onclick="toggleForms('login')">وارد شوید</a></div>
        </div>

        <div id="forgot-box" class="form-wrapper">
            <div class="alert alert-error" style="background:transparent; border-color:var(--primary-color); color:var(--text-color);">
                شماره موبایل و رمز عبور <b>جدید</b> خود را وارد کنید. پس از تایید پیامک، رمز شما تغییر خواهد کرد.
            </div>
            <form method="POST" id="forgot-form-otp" class="inner-form active">
                <input type="hidden" name="action" value="forgot_otp">
                <input type="tel" name="phone" placeholder="شماره موبایل حساب کاربری (09...)" dir="ltr" value="<?= htmlspecialchars($val_phone, ENT_QUOTES, 'UTF-8') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                <input type="password" name="password" placeholder="رمز عبور جدید" required>
                <input type="password" name="re_password" placeholder="تکرار رمز عبور جدید" required>
                
                <div class="captcha-wrap">
                    <img src="<?= $captcha_base64 ?>" alt="captcha">
                    <input type="text" name="captcha" placeholder="کد تصویر" dir="ltr" autocomplete="off" required>
                </div>

                <button class="btn btn-primary">دریافت کد تغییر رمز (بله)</button>
            </form>
            <div class="switch-link">رمز عبور را به یاد آوردید؟ <a onclick="toggleForms('login')">وارد شوید</a></div>
        </div>

    <?php else: ?>
        <div id="verify-box" class="form-wrapper active">
            <div class="bot-guide">
                برای دریافت کد یک‌بارمصرف، وارد ربات شده و <b>ارسال شماره</b> را بزنید:
            </div>

            <div style="display:flex; gap:10px; margin-bottom: 15px;">
                <a href="https://ble.ir/<?= htmlspecialchars($bot_username, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-bale" style="text-decoration: none; margin:0;">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    ورود به ربات بله
                </a>
                <button type="button" class="btn" onclick="navigator.clipboard.writeText('@Atoxbot'); alert('آیدی بات کپی شد!');" style="background:var(--x-hover); border:1px solid var(--border-color); padding:10px; color:var(--text-color); margin:0; flex:1;">کپی آیدی بات</button>
            </div>

            <form method="POST" id="otp-form" onsubmit="return gatherOtp()">
                <input type="hidden" name="action" value="verify_otp">
                <input type="hidden" name="otp" id="real-otp">
                
                <div class="otp-wrapper" id="otp-inputs">
                    <input type="number" pattern="\d*" maxlength="1" class="otp-box" autofocus>
                    <input type="number" pattern="\d*" maxlength="1" class="otp-box">
                    <input type="number" pattern="\d*" maxlength="1" class="otp-box">
                    <input type="number" pattern="\d*" maxlength="1" class="otp-box">
                    <input type="number" pattern="\d*" maxlength="1" class="otp-box">
                </div>
                
                <div class="timer-wrap" id="otp-timer">02:00</div>

                <button class="btn btn-primary" id="btn-verify">تایید و تکمیل ورود</button>
            </form>
            
            <div class="switch-link" style="margin-top: 15px;">
                <a href="auth.php">بازگشت و تغییر شماره</a>
            </div>
        </div>

        <script>
            let timeLeft = <?= (int)$time_left ?>;
            const timerEl = document.getElementById('otp-timer');
            const btnVerify = document.getElementById('btn-verify');
            const inputs = document.querySelectorAll('.otp-box');

            function updateTimer() {
                if (timeLeft <= 0) {
                    timerEl.innerHTML = "<span style='color:#f4212e'>زمان شما به پایان رسید!</span>";
                    btnVerify.disabled = true;
                    btnVerify.style.opacity = '0.5';
                    btnVerify.style.cursor = 'not-allowed';
                    inputs.forEach(i => i.disabled = true);
                    return;
                }
                let m = Math.floor(timeLeft / 60);
                let s = timeLeft % 60;
                timerEl.innerText = (m < 10 ? '0'+m : m) + ':' + (s < 10 ? '0'+s : s);
                timeLeft--;
                setTimeout(updateTimer, 1000);
            }
            if(timeLeft > 0) updateTimer();

            inputs.forEach((input, index) => {
                input.addEventListener('input', (e) => {
                    if(e.target.value.length > 1) {
                        e.target.value = e.target.value.slice(0, 1);
                    }
                    if(e.target.value !== '') {
                        if(index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        } else if(index === inputs.length - 1) {
                            if (gatherOtp()) {
                                document.getElementById('otp-form').submit();
                            }
                        }
                    }
                });
                
                input.addEventListener('keydown', (e) => {
                    if(e.key === 'Backspace' && e.target.value === '' && index > 0) {
                        inputs[index - 1].focus();
                    }
                });
            });

            function gatherOtp() {
                let otpValue = '';
                inputs.forEach(input => { otpValue += input.value; });
                
                if(otpValue.length < 5) {
                    return false;
                }
                
                document.getElementById('real-otp').value = otpValue;
                return true;
            }
        </script>
    <?php endif; ?>

</div>

<div id="privacy-modal" class="privacy-modal-overlay" onclick="closePrivacyModal(event)">
    <div class="privacy-modal-content" onclick="event.stopPropagation()">
        <div class="privacy-modal-header">
            سیاست‌نامه حفظ حریم شخصی
            <button class="privacy-x-btn" onclick="closePrivacyModal()">×</button>
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
            <button class="privacy-close-btn" onclick="acceptPrivacy()">متوجه شدم</button>
        </div>
    </div>
</div>

<script>
    function toggleTheme() {
        const htmlDoc = document.documentElement;
        if(htmlDoc.classList.contains('dark')) {
            htmlDoc.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            htmlDoc.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
    }

    function toggleForms(target) {
        document.getElementById('login-box').classList.remove('active');
        document.getElementById('register-box').classList.remove('active');
        document.getElementById('forgot-box').classList.remove('active');
        
        if(target === 'register') {
            document.getElementById('register-box').classList.add('active');
        } else if (target === 'forgot') {
            document.getElementById('forgot-box').classList.add('active');
        } else {
            document.getElementById('login-box').classList.add('active');
        }
    }

    function switchMethod(context, method) {
        const box = document.getElementById(context + '-box');
        if(!box) return;
        const tabs = box.querySelectorAll('.tab-btn');
        const forms = box.querySelectorAll('.inner-form');
        
        if(tabs.length === 0) return;

        tabs.forEach(t => t.classList.remove('active'));
        forms.forEach(f => f.classList.remove('active'));
        
        if(method === 'pass') {
            tabs[0].classList.add('active');
            document.getElementById(context + '-form-pass').classList.add('active');
        } else {
            tabs[1].classList.add('active');
            document.getElementById(context + '-form-otp').classList.add('active');
        }
    }

    <?php if (isset($action_error) && $action_error == 'register'): ?>
        toggleForms('register');
    <?php elseif (isset($action_error) && $action_error == 'forgot'): ?>
        toggleForms('forgot');
    <?php else: ?>
        toggleForms('login');
    <?php endif; ?>

    function openPrivacyModal() {
        const modal = document.getElementById('privacy-modal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    }
    
    function closePrivacyModal(e) {
        if(e && e.target !== document.getElementById('privacy-modal')) return;
        const modal = document.getElementById('privacy-modal');
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    }

    function acceptPrivacy() {
        const checkbox = document.getElementById('privacy-reg');
        if(checkbox) checkbox.checked = true;
        closePrivacyModal();
    }
</script>

</body>
</html>
