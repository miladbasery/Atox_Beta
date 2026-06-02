<?php
define('SECRET_KEY', 'A_VERY_huiuhlih;ijhoi9oy87tg7gfmydul8y9pybipbio;8gyuftrjd5_123456');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
        'domain' => '',
        'secure' => true, 
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    $host = 'localhost';
    $dbname = 'atox_db'; 
    $user = 'root';      
    $pass = '';          
} else {
    $host = 'localhost';
    $dbname = '';
    $user = ''; 
    $pass = ''; 
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    die(htmlspecialchars("خطا در اتصال به دیتابیس.", ENT_QUOTES, 'UTF-8'));
}

if (isset($_SESSION['user_id'])) {
    $uid = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
    if ($uid) {
        $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$uid]);
        
        $current_session_id = session_id();
        $check_sess = $pdo->prepare("SELECT id FROM user_sessions WHERE session_id = ? LIMIT 1");
        $check_sess->execute([$current_session_id]);
        
        if ($check_sess->rowCount() === 0) {
            session_unset();
            session_destroy();
            setcookie('user_auth', '', time() - 3600, "/", "", true, true);
            header("Location: index.php");
            exit;
        } else {
            $pdo->prepare("UPDATE user_sessions SET last_active = NOW() WHERE session_id = ?")->execute([$current_session_id]);
        }
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) !== false;
}
?>