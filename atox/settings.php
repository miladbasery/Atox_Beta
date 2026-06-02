<?php
ob_start();
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self';");

require 'db.php';

if (!isset($_SESSION['user_id'])) { 
    header('Location: index.php'); 
    exit; 
}

if (isset($_SESSION['error'])) {
    echo '<div style="color:red; background:#ffebee; padding:10px; border:1px solid red;">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']); 
}

if (isset($_SESSION['success'])) {
    echo '<div style="color:green; background:#e8f5e9; padding:10px; border:1px solid green;">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}

$current_user_id = (int)$_SESSION['user_id'];

if (isset($_GET['check_username'])) {
    header('Content-Type: application/json');
    $un = trim($_GET['check_username']);
    
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9._]{3,18}[a-zA-Z0-9]$/', $un)) {
        echo json_encode(['status' => 'invalid']); 
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_un = $stmt->fetchColumn();

    if ($un === $current_un) {
        echo json_encode(['status' => 'ok']); 
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$un]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'taken']); 
        exit;
    }
    echo json_encode(['status' => 'ok']); 
    exit;
}

$phone_error = '';
$phone_success = '';
$open_phone_modal = false;
$open_block_modal = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = htmlspecialchars($_POST['action'] ?? '', ENT_QUOTES, 'UTF-8');

    if ($action === 'request_phone_change') {
        $open_phone_modal = true;
        $new_phone = preg_replace('/[^0-9]/', '', $_POST['new_phone'] ?? '');
        if (substr($new_phone, 0, 1) != "0") { $new_phone = "0" . $new_phone; }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $stmt->execute([$new_phone, $current_user_id]);
        if ($stmt->rowCount() > 0) {
            $phone_error = "این شماره قبلا توسط کاربر دیگری ثبت شده است.";
        } else {
            $otp = random_int(10000, 99999);
            $expires = date('Y-m-d H:i:s', strtotime('+2 minutes'));
            
            $stmt = $pdo->prepare("INSERT INTO otp_requests (phone, otp_code, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE otp_code = ?, expires_at = ?");
            $stmt->execute([$new_phone, $otp, $expires, $otp, $expires]);
            
            $_SESSION['temp_new_phone'] = $new_phone;
            $phone_success = "کد تایید به ربات بله ارسال شد.";
        }
    }
    elseif ($action === 'verify_phone_change') {
        $open_phone_modal = true;
        $entered_otp = preg_replace('/[^0-9]/', '', $_POST['otp'] ?? '');
        $new_phone = $_SESSION['temp_new_phone'] ?? '';
        
        if (!$new_phone) {
            $phone_error = "جلسه شما منقضی شده است. لطفا مجددا تلاش کنید.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM otp_requests WHERE phone = ?");
            $stmt->execute([$new_phone]);
            $otp_row = $stmt->fetch();
            
            if ($otp_row && $otp_row['otp_code'] === $entered_otp && strtotime($otp_row['expires_at']) > time()) {
                $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
                $stmt->execute([$new_phone, $current_user_id]);
                
                $stmt = $pdo->prepare("DELETE FROM otp_requests WHERE phone = ?");
                $stmt->execute([$new_phone]);
                unset($_SESSION['temp_new_phone']);
                
                $phone_success = "شماره موبایل با موفقیت تغییر کرد.";
            } else {
                $phone_error = "کد وارد شده اشتباه است یا منقضی شده.";
            }
        }
    }
    elseif ($action === 'cancel_phone_change') {
        unset($_SESSION['temp_new_phone']);
        $open_phone_modal = true;
    }
    elseif ($action === 'unblock_user_internal') {
        $blocked_id = (int)($_POST['blocked_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$current_user_id, $blocked_id]);
        $open_block_modal = true;
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$r_stmt = $pdo->prepare("SELECT * FROM resumes WHERE user_id = ?");
$r_stmt->execute([$current_user_id]);
$resume = $r_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$r_skills = !empty($resume['skills']) ? json_decode($resume['skills'], true) : [];
$r_langs = !empty($resume['languages']) ? json_decode($resume['languages'], true) : [];
$r_soft = !empty($resume['soft_skills']) ? json_decode($resume['soft_skills'], true) : [];

$predefined_soft_skills = [
    "حل مسئله", "کار تیمی", "مدیریت زمان", "ارتباط موثر", "تفکر انتقادی", 
    "انعطاف‌پذیری", "رهبری", "خلاقیت", "مذاکره", "هوش هیجانی",
    "تصمیم‌گیری", "مدیریت استرس", "سازگاری", "انتقادپذیری", "شبکه‌سازی",
    "مسئولیت‌پذیری", "حل تعارض", "تمرکز و دقت", "گوش دادن فعال", "خودآموزی"
];

$b_stmt = $pdo->prepare("SELECT b.*, u.username, u.name FROM blocks b JOIN users u ON b.blocked_id = u.id WHERE b.blocker_id = ?");
$b_stmt->execute([$current_user_id]);
$blocked_users = $b_stmt->fetchAll(PDO::FETCH_ASSOC);

$active_sessions = [];
$current_session_id = session_id();
$can_revoke_others = false;

try {
    $s_stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_active DESC");
    $s_stmt->execute([$current_user_id]);
    $raw_sessions = $s_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $oldest_session_time = null;
    foreach ($raw_sessions as $sess) {
        $st = strtotime($sess['created_at']);
        if ($oldest_session_time === null || $st < $oldest_session_time) {
            $oldest_session_time = $st;
        }
    }
    
    foreach ($raw_sessions as $sess) {
        if ($sess['session_id'] === $current_session_id) {
            if (strtotime($sess['created_at']) <= $oldest_session_time) {
                $can_revoke_others = true;
            }
            break;
        }
    }
    
    $filtered = [];
    $seen_ips = [];
    
    foreach ($raw_sessions as $sess) {
        if ($sess['session_id'] === $current_session_id) {
            $filtered[] = $sess;
            $seen_ips[] = $sess['ip_address'];
            break;
        }
    }
    foreach ($raw_sessions as $sess) {
        if ($sess['session_id'] !== $current_session_id && !in_array($sess['ip_address'], $seen_ips)) {
            $filtered[] = $sess;
            $seen_ips[] = $sess['ip_address'];
        }
    }
    $active_sessions = $filtered;
    
} catch(PDOException $e) {}

require_once 'settings_view.php';
ob_end_flush();
?>
