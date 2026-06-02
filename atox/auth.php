<?php
ob_start();

header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self';");

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
        'domain' => "",
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

require 'db.php';

$lifetime = 604800;

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    if (isset($_SESSION['user_id'])) {
        $del_sess = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $del_sess->execute([session_id()]);
    }
    session_unset();
    session_destroy();
    setcookie('user_auth', '', time() - 3600, "/", "", true, true); 
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['user_id']) && !empty($_COOKIE['user_auth'])) {
    $cookie_data = explode('|', $_COOKIE['user_auth']);
    if (count($cookie_data) === 2) {
        $c_user_id = $cookie_data[0];
        $c_hash = $cookie_data[1];
        
        if (hash_equals(hash_hmac('sha256', $c_user_id, SECRET_KEY), $c_hash)) {
            $_SESSION['user_id'] = $c_user_id;
            
            $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt_role->execute([$c_user_id]);
            if($u_role = $stmt_role->fetchColumn()) {
                $_SESSION['role'] = $u_role;
            }
			$stmt_session = $pdo->prepare("INSERT IGNORE INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at, last_active) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt_session->execute([$_SESSION['user_id'], session_id(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']);
        } else {
            setcookie('user_auth', '', time() - 3600, "/", "", true, true); 
        }
    } else {
        setcookie('user_auth', '', time() - 3600, "/", "", true, true); 
    }
}

if (isset($_SESSION['user_id'])) { 
    header('Location: index.php'); 
    exit; 
}

$pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    ip_address VARCHAR(45), 
    attempt_time DATETIME
)");

$error = '';
$step = 'form'; 
$bot_username = "Atoxbot"; 

$common_passwords = ['12345678', '123456789', '1234567890', 'password', '11111111', 'qwertyui', '123123123'];

$val_username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
$val_name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$val_phone = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = htmlspecialchars($_POST['action'] ?? '', ENT_QUOTES, 'UTF-8');
    $can_proceed = true;
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt_limit = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt_limit->execute([$ip_address]);
    $attempts_count = $stmt_limit->fetchColumn();
    
    if ($attempts_count >= 10 && $action !== 'verify_otp') {
        $error = "شما بیش از حد مجاز تلاش کرده‌اید. لطفا یک ساعت دیگر مجددا امتحان کنید.";
        $can_proceed = false;
    }

    $action_error = 'login';
    if (strpos($action, 'register') !== false) $action_error = 'register';
    if (strpos($action, 'forgot') !== false) $action_error = 'forgot';

    if ($action == 'register_pass' || $action == 'login_pass') {
        $user_captcha = trim($_POST['captcha'] ?? '');
        if (!isset($_SESSION['captcha_code']) || $user_captcha != $_SESSION['captcha_code']) {
            $error = "کد امنیتی (کپچا) وارد شده اشتباه است.";
            $can_proceed = false;
            $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$ip_address]);
        }

        if ($can_proceed && $action == 'register_pass') {
            $username = $val_username;
            $name = $val_name;
            $password = $_POST['password'] ?? '';
            $re_password = $_POST['re_password'] ?? '';
            $privacy = isset($_POST['privacy']) ? true : false;
            
            if (!$privacy) {
                $error = "باید قوانین حریم خصوصی را تایید کنید.";
                $can_proceed = false;
            } elseif (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{3,}[a-zA-Z0-9]$/', $username)) {
                $error = "نام کاربری حداقل ۵ حرف و باید با حرف یا عدد شروع و تمام شود.";
                $can_proceed = false;
            } elseif (strlen($password) < 8) {
                $error = "رمز عبور باید حداقل ۸ کاراکتر باشد.";
                $can_proceed = false;
            } elseif (in_array(strtolower($password), $common_passwords)) {
                $error = "رمز عبور بسیار ساده است. لطفاً رمز قوی‌تری انتخاب کنید.";
                $can_proceed = false;
            } elseif ($password !== $re_password) {
                $error = "رمز عبور و تکرار آن یکسان نیستند.";
                $can_proceed = false;
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() > 0) {
                    $error = "این نام کاربری قبلا ثبت شده است.";
                    $can_proceed = false;
                }
            }

            if ($can_proceed) {
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, username, phone, password, role, privacy_accepted) VALUES (?, ?, '', ?, 'user', 1)");
                $stmt->execute([$name, $username, $hashed_pass]);
                
                $new_user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['role'] = 'user';
				$stmt_session = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at, last_active) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt_session->execute([$_SESSION['user_id'], session_id(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']);
                $cookie_hash = hash_hmac('sha256', $new_user_id, SECRET_KEY);
                setcookie('user_auth', $new_user_id . '|' . $cookie_hash, time() + $lifetime, "/", "", true, true);
                header('Location: index.php'); exit;
            } else {
                $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$ip_address]);
            }
        } 
        elseif ($can_proceed && $action == 'login_pass') {
            $username = $val_username;
            $password = $_POST['password'] ?? '';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
				$stmt_session = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at, last_active) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt_session->execute([$_SESSION['user_id'], session_id(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']);
                $cookie_hash = hash_hmac('sha256', $user['id'], SECRET_KEY);
                setcookie('user_auth', $user['id'] . '|' . $cookie_hash, time() + $lifetime, "/", "", true, true);
                header('Location: index.php'); exit;
            } else {
                $error = "نام کاربری یا رمز عبور اشتباه است.";
                $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$ip_address]);
            }
        }
    }
    elseif ($action == 'register_otp' || $action == 'login_otp' || $action == 'forgot_otp') {
        
        $user_captcha = trim($_POST['captcha'] ?? '');
        if ($can_proceed && (!isset($_SESSION['captcha_code']) || $user_captcha != $_SESSION['captcha_code'])) {
            $error = "کد امنیتی (کپچا) وارد شده اشتباه است.";
            $can_proceed = false;
            $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$ip_address]);
        }

        $phone = $val_phone;
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) != "0") { $phone = "0" . $phone; }
        
        $password = $_POST['password'] ?? '';

        if($can_proceed){
            $stmt = $pdo->prepare("SELECT expires_at FROM otp_requests WHERE phone = ?");
            $stmt->execute([$phone]);
            $existing_otp = $stmt->fetch();
            
            if ($existing_otp && strtotime($existing_otp['expires_at']) > time()) {
                $error = "شما به تازگی کد دریافت کرده‌اید. لطفاً تا پایان ۲ دقیقه صبر کنید.";
                $can_proceed = false;
            }
        }

        if ($can_proceed && $action == 'register_otp') {
            $username = $val_username;
            $name = $val_name;
            $privacy = isset($_POST['privacy']) ? true : false;
            
            if (!$privacy) {
                $error = "باید قوانین حریم خصوصی را تایید کنید.";
                $can_proceed = false;
            } elseif (!preg_match('/^[a-zA-Z0-9_]{5,}$/', $username)) {
                $error = "نام کاربری حداقل ۵ حرف و شامل حروف انگلیسی، اعداد و (_) باشد.";
                $can_proceed = false;
            } elseif (strlen($password) < 8) {
                $error = "رمز عبور باید حداقل ۸ کاراکتر باشد.";
                $can_proceed = false;
            } elseif (in_array(strtolower($password), $common_passwords)) {
                $error = "رمز عبور بسیار ساده است.";
                $can_proceed = false;
            } elseif ($_POST['password'] !== $_POST['re_password']) {
                $error = "رمز عبور و تکرار آن یکسان نیستند.";
                $can_proceed = false;
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR phone = ?");
                $stmt->execute([$username, $phone]);
                if ($stmt->rowCount() > 0) {
                    $error = "این نام کاربری یا شماره موبایل قبلا ثبت شده است.";
                    $can_proceed = false;
                }
            }
        } 
        elseif ($can_proceed && $action == 'login_otp') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->rowCount() == 0) {
                $error = "حسابی با این شماره یافت نشد.";
                $can_proceed = false;
            }
        }
        elseif ($can_proceed && $action == 'forgot_otp') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->rowCount() == 0) {
                $error = "حسابی با این شماره یافت نشد.";
                $can_proceed = false;
            } elseif (strlen($password) < 8) {
                $error = "رمز عبور جدید باید حداقل ۸ کاراکتر باشد.";
                $can_proceed = false;
            } elseif ($_POST['password'] !== $_POST['re_password']) {
                $error = "رمز عبور جدید و تکرار آن یکسان نیستند.";
                $can_proceed = false;
            }
        }

        if ($can_proceed) {
            $otp = rand(10000, 99999);
            $expires = date('Y-m-d H:i:s', strtotime('+2 minutes'));

            $stmt = $pdo->prepare("INSERT INTO otp_requests (phone, otp_code, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE otp_code = ?, expires_at = ?");
            $stmt->execute([$phone, $otp, $expires, $otp, $expires]);

            $_SESSION['temp_phone'] = $phone;
            $_SESSION['temp_password'] = $password;
            $_SESSION['temp_action'] = $action;
            $_SESSION['otp_expire_time'] = time() + 120;
            if ($action == 'register_otp') {
                $_SESSION['temp_username'] = $username;
                $_SESSION['temp_name'] = $name;
            }
            $step = 'verify';
        } else {
            $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$ip_address]);
        }
    }
    elseif ($action == 'verify_otp') {
        $entered_otp = trim(htmlspecialchars($_POST['otp'] ?? '', ENT_QUOTES, 'UTF-8'));
        $phone = $_SESSION['temp_phone'] ?? '';

        if (!$phone) {
            $error = "نشست شما منقضی شده است. مجدداً تلاش کنید.";
            $step = 'form';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM otp_requests WHERE phone = ?");
            $stmt->execute([$phone]);
            $otp_row = $stmt->fetch();

            if ($otp_row && $otp_row['otp_code'] == $entered_otp && strtotime($otp_row['expires_at']) > time()) {
                $stmt = $pdo->prepare("DELETE FROM otp_requests WHERE phone = ?");
                $stmt->execute([$phone]);

                if ($_SESSION['temp_action'] == 'register_otp') {
                    $hashed_pass = password_hash($_SESSION['temp_password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, username, phone, password, role, privacy_accepted) VALUES (?, ?, ?, ?, 'user', 1)");
                    $stmt->execute([$_SESSION['temp_name'], $_SESSION['temp_username'], $phone, $hashed_pass]);
                    
                    $new_user_id = $pdo->lastInsertId();
                    $_SESSION['user_id'] = $new_user_id;
                    $_SESSION['role'] = 'user';

                    $stmt_session = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at, last_active) VALUES (?, ?, ?, ?, NOW(), NOW())");
                    $stmt_session->execute([$_SESSION['user_id'], session_id(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']);

                    $cookie_hash = hash_hmac('sha256', $new_user_id, SECRET_KEY);
                    setcookie('user_auth', $new_user_id . '|' . $cookie_hash, time() + $lifetime, "/", "", true, true);
                    unset($_SESSION['temp_phone'], $_SESSION['temp_password'], $_SESSION['temp_action'], $_SESSION['temp_name'], $_SESSION['temp_username'], $_SESSION['otp_expire_time']);
                    header('Location: index.php'); exit;
                } 
                elseif ($_SESSION['temp_action'] == 'login_otp') {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
                    $stmt->execute([$phone]);
                    $user = $stmt->fetch();
                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];

                        $stmt_session = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at, last_active) VALUES (?, ?, ?, ?, NOW(), NOW())");
                        $stmt_session->execute([$_SESSION['user_id'], session_id(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']);

                        $cookie_hash = hash_hmac('sha256', $user['id'], SECRET_KEY);
                        setcookie('user_auth', $user['id'] . '|' . $cookie_hash, time() + $lifetime, "/", "", true, true);
                        unset($_SESSION['temp_phone'], $_SESSION['temp_password'], $_SESSION['temp_action'], $_SESSION['otp_expire_time']);
                        header('Location: index.php'); exit;
                    } else {
                        $error = "کاربری یافت نشد.";
                        $step = 'form';
                    }
                }
                elseif ($_SESSION['temp_action'] == 'forgot_otp') {
                    $hashed_pass = password_hash($_SESSION['temp_password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE phone = ?");
                    $stmt->execute([$hashed_pass, $phone]);

                    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE phone = ?");
                    $stmt->execute([$phone]);
                    $user = $stmt->fetch();

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];

                    $stmt_session = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at, last_active) VALUES (?, ?, ?, ?, NOW(), NOW())");
                    $stmt_session->execute([$_SESSION['user_id'], session_id(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']);

                    unset($_SESSION['temp_phone'], $_SESSION['temp_password'], $_SESSION['temp_action'], $_SESSION['otp_expire_time']);
                    header('Location: index.php'); exit;
                }
            } else {
                $error = "کد وارد شده اشتباه است یا منقضی شده.";
                $step = 'verify';
                $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$ip_address]);
            }
        }
    }
}

$time_left = 0;
if ($step == 'verify' && isset($_SESSION['otp_expire_time'])) {
    $time_left = max(0, $_SESSION['otp_expire_time'] - time());
    if ($time_left == 0) {
        $error = "کد منقضی شده است. لطفا دوباره درخواست دهید.";
        $step = 'form';
    }
}

$captcha_code = rand(10000, 99999);
$_SESSION['captcha_code'] = $captcha_code;
$svg_width = 130;
$svg_height = 45;
$svg_content = '<svg width="'.$svg_width.'" height="'.$svg_height.'" xmlns="http://www.w3.org/2000/svg" style="background-color: #f7f9f9; border-radius: 4px; border: 1px dashed #71767b;">';
for($i=0; $i<12; $i++) {
    $x1 = rand(0, $svg_width); $y1 = rand(0, $svg_height);
    $x2 = rand(0, $svg_width); $y2 = rand(0, $svg_height);
    $svg_content .= '<line x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'" stroke="#'.dechex(rand(0x444444, 0x999999)).'" stroke-width="'.rand(1,3).'" opacity="0.6"/>';
}
for($i=0; $i<25; $i++) {
    $cx = rand(0, $svg_width); $cy = rand(0, $svg_height);
    $r = rand(1, 3);
    $svg_content .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="#'.dechex(rand(0x333333, 0xaaaaaa)).'" opacity="0.5"/>';
}
$codeStr = (string)$captcha_code;
for($i=0; $i<5; $i++) {
    $angle = rand(-40, 40);
    $x = 15 + ($i * 20) + rand(-3, 3);
    $y = rand(28, 35);
    $fs = rand(22, 28);
    $colors = ['#1d9bf0', '#f91880', '#00ba7c', '#ffad1f', '#71767b'];
    $color = $colors[array_rand($colors)];
    $svg_content .= '<text x="'.$x.'" y="'.$y.'" font-family="monospace" font-size="'.$fs.'" font-weight="bold" fill="'.$color.'" transform="rotate('.$angle.' '.$x.' '.$y.')">'.$codeStr[$i].'</text>';
}
$svg_content .= '</svg>';
$captcha_base64 = 'data:image/svg+xml;base64,' . base64_encode($svg_content);

include 'auth_view.php';
?>
