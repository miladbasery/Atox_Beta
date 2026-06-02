<?php

header("X-Content-Type-Options: nosniff");
header("Content-Type: application/json; charset=UTF-8");

$botToken = "618952213:EhPeNr_W4H4AD-eLHRmHajEv5bZlz_xQdt0"; 
$apiUrl = "https://tapi.bale.ai/bot" . $botToken . "/";
$bot_domain = "https://atoxcomputer.ir"; 

$channel_1 = "@chaatino"; 
$channel_2 = "@atoxcomputer"; 

$db_host = 'localhost';
$db_name = 'atoxcomp_chat';
$db_user = 'atoxcomp_chat';
$db_pass = '8fF1iRx9L80BnQan';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    exit;
}

$pdo->exec("UPDATE users SET step = 'idle' WHERE step LIKE 'in_queue%' AND last_activity < NOW() - INTERVAL 5 MINUTE");

function sendMessage($chat_id, $text, $reply_markup = null) {
    global $apiUrl;
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($reply_markup) $data['reply_markup'] = $reply_markup;
    
    $ch = curl_init($apiUrl . "sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function checkChannelMembership($user_id, $channel_id) {
    global $apiUrl;
    $data = ['chat_id' => $channel_id, 'user_id' => (int)$user_id];
    $ch = curl_init($apiUrl . "getChatMember");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) return false;
    $res = json_decode($response, true);
    if (isset($res['ok']) && $res['ok'] == true && isset($res['result']['status'])) {
        $status = $res['result']['status'];
        return in_array($status, ['member', 'administrator', 'creator']);
    }
    return false;
}

function sendMediaMessage($chat_id, $type, $file_id, $caption = "", $reply_markup = null) {
    global $apiUrl;
    $method = "";
    $data = ['chat_id' => $chat_id, 'caption' => $caption, 'parse_mode' => 'HTML'];
    if ($reply_markup) $data['reply_markup'] = $reply_markup;
    
    if ($type == 'photo') { $method = "sendPhoto"; $data['photo'] = $file_id; }
    elseif ($type == 'voice') { $method = "sendVoice"; $data['voice'] = $file_id; }
    elseif ($type == 'sticker') { $method = "sendSticker"; $data['sticker'] = $file_id; }
    elseif ($type == 'animation') { $method = "sendAnimation"; $data['animation'] = $file_id; }
    
    $ch = curl_init($apiUrl . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
}

function compressAndSaveImage($file_id) {
    global $apiUrl, $botToken;
    
    $ch = curl_init($apiUrl . "getFile?file_id=" . $file_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (!$res['ok']) return false;
    
    $file_path = $res['result']['file_path'];
    $file_size = $res['result']['file_size'] ?? 0;
    
    if ($file_size > 512000) return 'TOO_LARGE';

    $downloadUrl = "https://tapi.bale.ai/file/bot" . $botToken . "/" . $file_path;
    $imgData = file_get_contents($downloadUrl);
    if (!$imgData) return false;

    $filename = time() . '_' . rand(1000, 9999) . '.jpg';
    $savePath = __DIR__ . '/uploads_bot/' . $filename;

    $im = imagecreatefromstring($imgData);
    if ($im !== false) {
        imagejpeg($im, $savePath, 40);
        imagedestroy($im);
        return $filename;
    }
    
    return false;
}

function timeAgo($datetime) {
    if (!$datetime) return 'نامشخص';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "چند لحظه قبل";
    if ($diff < 3600) return floor($diff / 60) . " دقیقه قبل";
    if ($diff < 86400) return floor($diff / 3600) . " ساعت قبل";
    return floor($diff / 86400) . " روز قبل";
}

function getJalaliExact($dateStr) {
    if (!$dateStr) return 'نامشخص';
    $timestamp = strtotime($dateStr);
    $g_y = date('Y', $timestamp); $g_m = date('m', $timestamp); $g_d = date('d', $timestamp);
    $time = date('H:i', $timestamp);
    $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
    $gy = $g_y - 1600; $gm = $g_m - 1; $gd = $g_d - 1;
    $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
    for ($i = 0; $i < $gm; ++$i) $g_day_no += $g_days_in_month[$i];
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) $g_day_no++;
    $g_day_no += $gd;
    $j_day_no = $g_day_no - 79;
    $j_np = floor($j_day_no / 12053); $j_day_no %= 12053;
    $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461); $j_day_no %= 1461;
    if ($j_day_no >= 366) { $jy += floor(($j_day_no - 1) / 365); $j_day_no = ($j_day_no - 1) % 365; }
    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) $j_day_no -= $j_days_in_month[$i];
    $jm = $i + 1; $jd = $j_day_no + 1;
    return sprintf("%s - %04d/%02d/%02d", $time, $jy, $jm, $jd);
}

function isProfileCompleteForAction($user) {
    return (!empty($user['fullname']) && !empty($user['gender']) && !empty($user['age']) && !empty($user['province']));
}

$mainMenu = [
    "keyboard" => [
        [["text" => "💬 چت ناشناس"]],
        [["text" => "👤 پروفایل من"], ["text" => "🔍 جستجوی پیشرفته"]],
        [["text" => "🔗 دعوت از دوستان"], ["text" => "☎️ ارتباط با ما"], ["text" => "📜 قوانین"]]
    ],
    "resize_keyboard" => true
];

$chatTypeMenu = [
    "keyboard" => [
        [["text" => "🎲 چت تصادفی (رایگان)"]],
        [["text" => "👩 چت با دختر (1 سکه)"], ["text" => "👨 چت با پسر (1 سکه)"]],
        [["text" => "🔙 بازگشت"]]
    ],
    "resize_keyboard" => true
];

$searchMenu = [
    "keyboard" => [
        [["text" => "📍 جستجو بر اساس استان"], ["text" => "🚻 جستجو بر اساس جنسیت"]],
        [["text" => "🟢 آخرین آنلاین‌ها"], ["text" => "🗺 نزدیک‌ترین افراد"]],
        [["text" => "🔙 بازگشت"]]
    ],
    "resize_keyboard" => true
];

$chatMenu = [
    "keyboard" => [
        [["text" => "❌ پایان چت"], ["text" => "🚫 بلاک کردن"]],
        [["text" => "👍 پسندیدن کاربر مقابل"]]
    ],
    "resize_keyboard" => true
];

$confirmMenu = [
    "keyboard" => [
        [["text" => "بله، مطمئنم"]],
        [["text" => "خیر، بازگشت به چت"]]
    ],
    "resize_keyboard" => true
];

$confirmDeleteMenu = [
    "keyboard" => [
        [["text" => "بله، حذف اکانت"]],
        [["text" => "خیر، بازگشت"]]
    ],
    "resize_keyboard" => true
];

$cancelMenu = [
    "keyboard" => [ [["text" => "❌ لغو"]] ],
    "resize_keyboard" => true
];

$genderMenu = [
    "keyboard" => [ [["text" => "پسر 👨"], ["text" => "دختر 👩"]], [["text" => "❌ لغو"]] ],
    "resize_keyboard" => true
];

$locationMenu = [
    "keyboard" => [
        [["text" => "📍 ارسال لوکیشن (نقشه)", "request_location" => true]],
        [["text" => "رد کردن ⏭️"]],
        [["text" => "❌ لغو"]]
    ],
    "resize_keyboard" => true,
    "one_time_keyboard" => true
];

$joinMenu = [
    "inline_keyboard" => [
        [["text" => "1️⃣ @chaatino", "url" => "https://ble.ir/chaatino"]],
        [["text" => "2️⃣ @atoxcomputer", "url" => "https://ble.ir/atoxcomputer"]]
    ]
];

$backOnlyMenu = [
    "keyboard" => [ [["text" => "🔙 بازگشت"]] ],
    "resize_keyboard" => true
];

$myProfileInlineMenu = [
    "inline_keyboard" => [
        [["text" => "✏️ ویرایش پروفایل", "callback_data" => "action_edit_profile"], ["text" => "❤️ لایک کنندگان", "callback_data" => "action_likers"]],
        [["text" => "🚫 افراد بلاک شده", "callback_data" => "action_blocks"]],
        [["text" => "🗑 حذف کامل اکانت", "callback_data" => "action_delete_account"]]
    ]
];

$profileEditMenu = [
    "inline_keyboard" => [
        [["text" => "تغییر نام", "callback_data" => "edit_name"], ["text" => "تغییر جنسیت", "callback_data" => "edit_gender"]],
        [["text" => "تغییر سن", "callback_data" => "edit_age"], ["text" => "تغییر استان", "callback_data" => "edit_province"]],
        [["text" => "تغییر شهر", "callback_data" => "edit_city"], ["text" => "تغییر لوکیشن", "callback_data" => "edit_location"]],
        [["text" => "تغییر عکس پروفایل", "callback_data" => "edit_image"]]
    ]
];

function getAgeKeyboard() {
    $kb = []; $row = [];
    for ($i = 15; $i <= 40; $i++) {
        $row[] = ["text" => (string)$i];
        if (count($row) == 4) { $kb[] = $row; $row = []; }
    }
    if (count($row) > 0) $kb[] = $row;
    $kb[] = [["text" => "❌ لغو"]];
    return ["keyboard" => $kb, "resize_keyboard" => true];
}

function getProvinceKeyboard() {
    $provinces = ["آذربایجان شرقی","آذربایجان غربی","اردبیل","اصفهان","البرز","ایلام","بوشهر","تهران","چهارمحال و بختیاری","خراسان جنوبی","خراسان رضوی","خراسان شمالی","خوزستان","زنجان","سمنان","سیستان و بلوچستان","فارس","قزوین","قم","کردستان","کرمان","کرمانشاه","کهگیلویه و بویراحمد","گلستان","گیلان","لرستان","مازندران","مرکزی","هرمزگان","همدان","یزد"];
    $kb = []; $row = [];
    foreach ($provinces as $p) {
        $row[] = ["text" => $p];
        if (count($row) == 2) { $kb[] = $row; $row = []; }
    }
    if (count($row) > 0) $kb[] = $row;
    $kb[] = [["text" => "❌ لغو"]];
    return ["keyboard" => $kb, "resize_keyboard" => true];
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);

$is_callback = false;
$message = null;
if (isset($update["callback_query"])) {
    $is_callback = true;
    $callback = $update["callback_query"];
    $message = $callback["message"];
    $chat_id = $message["chat"]["id"];
    $user_id = $callback["from"]["id"];
    $text = $callback["data"];
    $location = null;
} elseif (isset($update["message"])) {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $user_id = $message["from"]["id"];
    $text = trim($message["text"] ?? '');
    $location = $message["location"] ?? null;
} else {
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
$stmt->execute([$chat_id]);
$user = $stmt->fetch();

$ref_id = null;
if (!$user && strpos($text, '/start ref_') === 0) {
    $ref_id = str_replace('/start ref_', '', $text);
}

if (!$user) {
    if ($text !== '/start' && strpos($text, '/start') !== 0) {
        sendMessage($chat_id, "شما حساب کاربری ندارید. برای شروع ربات روی /start کلیک کنید.", ["remove_keyboard" => true]);
        exit;
    }
    $unique_user = 'chaatino_' . substr(md5(time() . $chat_id), 0, 6);
    $stmt = $pdo->prepare("INSERT INTO users (chat_id, username, step, coins, profile_completed, last_activity) VALUES (?, ?, 'start', 0, 0, NOW())");
    $stmt->execute([$chat_id, $unique_user]);
    $user = ['chat_id' => $chat_id, 'username' => $unique_user, 'step' => 'start', 'coins' => 0, 'profile_completed' => 0, 'last_nag_time' => null];
    
    if ($ref_id && $ref_id != $chat_id) {
        $pdo->prepare("UPDATE users SET coins = coins + 10 WHERE chat_id = ?")->execute([$ref_id]);
        $pdo->prepare("INSERT INTO referrals (inviter_id, invitee_id) VALUES (?, ?)")->execute([$ref_id, $chat_id]);
        sendMessage($ref_id, "🎉 یک کاربر با لینک شما وارد ربات شد و 10 سکه دریافت کردید!");
    }
} else {
    $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE chat_id = ?")->execute([$chat_id]);
}

$step = $user['step'];

if (strpos($text, '/chaatino_') === 0) {
    $target_username = str_replace('/', '', $text);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$target_username]);
    $target = $stmt->fetch();
    
    if ($target) {
        $stmtL = $pdo->prepare("SELECT COUNT(*) as count FROM profile_likes WHERE liked_id = ?");
        $stmtL->execute([$target['chat_id']]);
        $likes = $stmtL->fetch()['count'];
        
        $last_seen_relative = timeAgo($target['last_activity']);
        
        $profile_text = "<b>پروفایل کاربر</b>\n" .
                        "آیدی: /" . $target['username'] . "\n" .
                        "نام: " . ($target['fullname'] ?? '') . "\n" .
                        "جنسیت: " . ($target['gender'] ?? '') . "\n" .
                        "سن: " . ($target['age'] ?? '') . "\n" .
                        "استان: " . ($target['province'] ?? '') . "\n" .
                        "شهر: " . ($target['city'] ?? '') . "\n" .
                        "آخرین بازدید: " . $last_seen_relative . "\n" .
                        "لایک‌ها: " . $likes . " ❤️\n";
        
        $actionMenu = null;
        if ($target['chat_id'] != $chat_id) {
            $chkLike = $pdo->prepare("SELECT 1 FROM profile_likes WHERE liker_id = ? AND liked_id = ?");
            $chkLike->execute([$chat_id, $target['chat_id']]);
            $has_liked = $chkLike->fetch();
            
            $like_text = $has_liked ? "❤️ لایک شده" : "🤍 لایک کردن";
            $like_data = $has_liked ? "unlike_" . $target['chat_id'] : "like_" . $target['chat_id'];

            $chkBlock = $pdo->prepare("SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
            $chkBlock->execute([$chat_id, $target['chat_id']]);
            $is_blocked = $chkBlock->fetch();
            
            $block_text = $is_blocked ? "🔓 خارج کردن از بلاک" : "🚫 بلاک کردن";
            $block_data = $is_blocked ? "punblock_" . $target['chat_id'] : "pblock_" . $target['chat_id'];

            $actionMenu = [
                "inline_keyboard" => [
                    [["text" => $like_text, "callback_data" => $like_data], ["text" => $block_text, "callback_data" => $block_data]],
                    [["text" => "💬 درخواست چت (1 سکه)", "callback_data" => "reqchat_" . $target['chat_id']]]
                ]
            ];
        }

        $photo = $target['image'] ?? null;
        if (!empty($photo) && file_exists(__DIR__ . '/uploads_bot/' . $photo)) {
            $photo_url = $bot_domain . "/uploads_bot/" . $photo;
        } else {
            $photo_url = $bot_domain . "/default.png";
        }
        
        sendMediaMessage($chat_id, 'photo', $photo_url, $profile_text, $actionMenu);
    } else {
        sendMessage($chat_id, "کاربر یافت نشد.");
    }
    exit; 
}
function checkProfileCompletion($u, $pdo) {
    if ($u['profile_completed'] == 0 && !empty($u['fullname']) && !empty($u['gender']) && !empty($u['age']) && !empty($u['province']) && !empty($u['lat']) && !empty($u['image'])) {
        $pdo->prepare("UPDATE users SET coins = coins + 10, profile_completed = 1 WHERE chat_id = ?")->execute([$u['chat_id']]);
        sendMessage($u['chat_id'], "🎉 تبریک! اطلاعات پروفایل شما (از جمله لوکیشن و عکس) کاملاً تکمیل شد و 10 سکه هدیه دریافت کردید!");
        return true;
    }
    return false;
}

if ($user['profile_completed'] == 0 && $step == 'idle' && !$is_callback && $text !== '/start') {
    $last_nag = $user['last_nag_time'] ? strtotime($user['last_nag_time']) : 0;
    if (time() - $last_nag > 1800) {
        $missing = [];
        if (empty($user['fullname'])) $missing[] = "نام";
        if (empty($user['gender'])) $missing[] = "جنسیت";
        if (empty($user['age'])) $missing[] = "سن";
        if (empty($user['province'])) $missing[] = "استان";
        if (empty($user['lat'])) $missing[] = "لوکیشن";
        if (empty($user['image'])) $missing[] = "عکس پروفایل";
        
        $nag_msg = "⚠️ کاربر عزیز، پروفایل شما هنوز کامل نیست!\n\nبرای دریافت 10 سکه رایگان لطفا موارد زیر را از بخش (پروفایل من -> ویرایش پروفایل) تکمیل کنید:\n" . implode("، ", $missing);
        sendMessage($chat_id, $nag_msg);
        $pdo->prepare("UPDATE users SET last_nag_time = NOW() WHERE chat_id = ?")->execute([$chat_id]);
    }
}

if ($text == "❌ لغو" || $text == "🔙 بازگشت" || $text == "❌ لغو جستجو" || $text == "خیر، بازگشت") {
    if (empty($user['fullname']) || empty($user['gender']) || empty($user['age']) || empty($user['province'])) {
        $pdo->prepare("UPDATE users SET step = 'wait_for_start' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "شما حساب کاربری خود را تکمیل نکرده‌اید. باید استارت کنید بات رو (/start).", ["remove_keyboard" => true]);
        exit;
    }
    $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id = ?")->execute([$chat_id]);
    sendMessage($chat_id, "عملیات لغو شد. به منوی اصلی برگشتید.", $mainMenu);
    checkProfileCompletion($user, $pdo);
    exit;
}

if (!$is_callback && (strpos($text, '/start') === 0)) {
    if ($step == 'start' || $step == 'wait_for_start') {
        $pdo->prepare("UPDATE users SET step = 'ask_name' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "✅ به ربات خوش آمدید!\n\nبرای شروع، لطفا نام خود را وارد کنید:", $cancelMenu);
        exit;
    } else {
        if (!in_array($step, ['in_queue', 'in_queue_girl', 'in_queue_boy', 'in_chat', 'confirm_end', 'confirm_block'])) {
            $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id = ?")->execute([$chat_id]);
            sendMessage($chat_id, "منوی اصلی:", $mainMenu);
            exit;
        }
    }
}

if ($step == 'start' || $step == 'wait_for_start') {
    $msg = "⚠️ جهت حمایت از ما می‌توانید در دو کانال زیر عضو شوید:\n\n✅ برای ورود به ربات، دستور /start را ارسال کنید.";
    $pdo->prepare("UPDATE users SET step = 'wait_for_start' WHERE chat_id = ?")->execute([$chat_id]);
    sendMessage($chat_id, $msg, $joinMenu);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM chats WHERE status = 'active' AND (user1_id = ? OR user2_id = ?) LIMIT 1");
$stmt->execute([$chat_id, $chat_id]);
$active_chat = $stmt->fetch();

if ($active_chat && in_array($step, ['in_chat', 'confirm_end', 'confirm_block'])) {
    $partner_id = ($active_chat['user1_id'] == $chat_id) ? $active_chat['user2_id'] : $active_chat['user1_id'];
    
    if ($text == "❌ پایان چت") {
        $pdo->prepare("UPDATE users SET step = 'confirm_end' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "آیا مطمئن هستید که می‌خواهید چت را ببندید؟", $confirmMenu);
        exit;
    }
    if ($text == "🚫 بلاک کردن") {
        if (!isProfileCompleteForAction($user)) {
            sendMessage($chat_id, "شما به دلیل ناقص بودن پروفایلتان (نام، سن، جنسیت، استان) اجازه بلاک کردن ندارید. لطفا پروفایل خود را تکمیل کنید.");
            exit;
        }
        $pdo->prepare("UPDATE users SET step = 'confirm_block' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "آیا مطمئن هستید که می‌خواهید کاربر مقابل را بلاک کنید؟\nاین عملیات قابل بازگشت نیست.", $confirmMenu);
        exit;
    }

    if ($text == "خیر، بازگشت به چت" && in_array($step, ['confirm_end', 'confirm_block'])) {
        $pdo->prepare("UPDATE users SET step = 'in_chat' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "به چت برگشتید.", $chatMenu);
        exit;
    }

    if ($text == "بله، مطمئنم") {
        if ($step == 'confirm_end') {
            $pdo->prepare("UPDATE chats SET status = 'closed' WHERE id = ?")->execute([$active_chat['id']]);
            $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id IN (?, ?)")->execute([$chat_id, $partner_id]);
            sendMessage($partner_id, "کاربر مقابل چت را بست.", $mainMenu);
            sendMessage($chat_id, "چت پایان یافت. به منوی اصلی برگشتید.", $mainMenu);
            exit;
        }
        if ($step == 'confirm_block') {
            $pdo->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)")->execute([$chat_id, $partner_id]);
            $pdo->prepare("UPDATE chats SET status = 'closed' WHERE id = ?")->execute([$active_chat['id']]);
            $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id IN (?, ?)")->execute([$chat_id, $partner_id]);
            sendMessage($partner_id, "شما توسط کاربر مقابل بلاک شدید و چت بسته شد.", $mainMenu);
            sendMessage($chat_id, "کاربر بلاک شد و دیگر با او متصل نخواهید شد.", $mainMenu);
            exit;
        }
    }

    if ($text == "👍 پسندیدن کاربر مقابل") {
        if (!isProfileCompleteForAction($user)) {
            sendMessage($chat_id, "شما به دلیل ناقص بودن پروفایلتان مجاز به لایک کردن نیستید. لطفا از بخش ویرایش پروفایل اقدام کنید.");
            exit;
        }
        $stmtLike = $pdo->prepare("SELECT * FROM profile_likes WHERE liker_id = ? AND liked_id = ?");
        $stmtLike->execute([$chat_id, $partner_id]);
        if (!$stmtLike->fetch()) {
            $pdo->prepare("INSERT INTO profile_likes (liker_id, liked_id) VALUES (?, ?)")->execute([$chat_id, $partner_id]);
            sendMessage($chat_id, "شما کاربر مقابل را لایک کردید! ❤️");
            sendMessage($partner_id, "❤️ هم‌صحبت شما، پروفایلتان را لایک کرد! (آیدی کاربر: /" . $user['username'] . ")");
        } else {
            sendMessage($chat_id, "شما قبلاً این کاربر را لایک کرده‌اید.");
        }
        exit;
    }

    if ($step == 'in_chat') {
        if (isset($message['photo'])) {
            sendMediaMessage($partner_id, 'photo', end($message['photo'])['file_id'], $message['caption'] ?? "");
        } elseif (isset($message['voice'])) {
            sendMediaMessage($partner_id, 'voice', $message['voice']['file_id']);
        } elseif (isset($message['sticker'])) {
            sendMediaMessage($partner_id, 'sticker', $message['sticker']['file_id']);
        } elseif (isset($message['animation']) || isset($message['document'])) {
            $file_id = isset($message['animation']) ? $message['animation']['file_id'] : $message['document']['file_id'];
            sendMediaMessage($partner_id, 'animation', $file_id, $message['caption'] ?? "");
        } else {
            sendMessage($partner_id, $text);
        }
    }
    exit;
}

if ($step == 'confirm_delete_account') {
    if ($text == "بله، حذف اکانت") {
        $pdo->prepare("DELETE FROM profile_likes WHERE liker_id = ? OR liked_id = ?")->execute([$chat_id, $chat_id]);
        $pdo->prepare("DELETE FROM blocks WHERE blocker_id = ? OR blocked_id = ?")->execute([$chat_id, $chat_id]);
        $pdo->prepare("DELETE FROM chats WHERE user1_id = ? OR user2_id = ?")->execute([$chat_id, $chat_id]);
        $pdo->prepare("DELETE FROM users WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "✅ حساب کاربری شما کاملاً حذف شد.\nباید استارت کنید بات رو (/start)", ["remove_keyboard" => true]);
    } else {
        $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "عملیات لغو شد.", $mainMenu);
    }
    exit;
}

if ($is_callback) {
    if ($text == "action_edit_profile") {
        sendMessage($chat_id, "بخش مورد نظر را برای ویرایش انتخاب کنید:", $profileEditMenu);
        exit;
    }
    if ($text == "action_likers") {
        $stmt = $pdo->prepare("SELECT u.username, u.fullname FROM profile_likes pl JOIN users u ON pl.liker_id = u.chat_id WHERE pl.liked_id = ? LIMIT 20");
        $stmt->execute([$chat_id]);
        $likers = $stmt->fetchAll();
        if ($likers) {
            $msg = "❤️ لیست کسانی که شما را لایک کرده‌اند:\n\n";
            foreach($likers as $l) {
                $msg .= "- " . ($l['fullname'] ?? 'ناشناس') . " ( /" . $l['username'] . " )\n";
            }
            sendMessage($chat_id, $msg);
        } else {
            sendMessage($chat_id, "تاکنون کسی پروفایل شما را لایک کرده است.");
        }
        exit;
    }
    if ($text == "action_blocks") {
        $stmt = $pdo->prepare("SELECT u.username, u.fullname FROM blocks b JOIN users u ON b.blocked_id = u.chat_id WHERE b.blocker_id = ? LIMIT 20");
        $stmt->execute([$chat_id]);
        $blocks = $stmt->fetchAll();
        if ($blocks) {
            $msg = "🚫 لیست افراد بلاک شده:\n\nجهت خروج از بلاک روی پروفایل کلیک کرده و دکمه مربوطه را بزنید.\n\n";
            foreach($blocks as $b) {
                $msg .= "- " . ($b['fullname'] ?? 'ناشناس') . " ( /" . $b['username'] . " )\n";
            }
            sendMessage($chat_id, $msg);
        } else {
            sendMessage($chat_id, "شما هیچ کاربری را بلاک نکرده‌اید.");
        }
        exit;
    }
    if ($text == "action_delete_account") {
        $pdo->prepare("UPDATE users SET step = 'confirm_delete_account' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "⚠️ اخطار مهم!\nآیا از حذف کامل حساب کاربری خود مطمئن هستید؟ تمامی اطلاعات، پیام‌ها و سکه‌ها غیرقابل بازگشت پاک خواهند شد.", $confirmDeleteMenu);
        exit;
    }

    if (strpos($text, "page_") === 0) {
        $parts = explode('_', $text);
        $type = $parts[1];
        $offset = (int)$parts[2];
        $param1 = isset($parts[3]) ? $parts[3] : null; 
        $param2 = isset($parts[4]) ? $parts[4] : null; 
        
        $results = [];
        if ($type == 'prov') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE province = ? AND gender = ? AND chat_id != ? ORDER BY last_activity DESC LIMIT 10 OFFSET $offset");
            $stmt->execute([$param1, $param2, $chat_id]);
            $results = $stmt->fetchAll();
        } elseif ($type == 'gen') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE gender = ? AND chat_id != ? ORDER BY last_activity DESC LIMIT 10 OFFSET $offset");
            $stmt->execute([$param1, $chat_id]);
            $results = $stmt->fetchAll();
        } elseif ($type == 'onl') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE last_activity >= NOW() - INTERVAL 30 MINUTE AND gender = ? AND chat_id != ? ORDER BY last_activity DESC LIMIT 10 OFFSET $offset");
            $stmt->execute([$param1, $chat_id]);
            $results = $stmt->fetchAll();
        } elseif ($type == 'near') {
            if ($user['lat'] && $user['lon']) {
                $stmt = $pdo->prepare("SELECT *, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lon) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance FROM users WHERE chat_id != ? AND gender = ? AND lat IS NOT NULL ORDER BY last_activity DESC LIMIT 10 OFFSET $offset");
                $stmt->execute([$user['lat'], $user['lon'], $user['lat'], $chat_id, $param1]);
                $results = $stmt->fetchAll();
            }
        }
        formatSearchResults($results, $chat_id, $type, $param1, $offset, $param2);
        exit;
    }

    if (strpos($text, "edit_") === 0) {
        $pdo->prepare("UPDATE users SET step = ? WHERE chat_id = ?")->execute([$text, $chat_id]);
        $msg = "لطفا مقدار جدید را وارد کنید:";
        $kb = $cancelMenu;
        if ($text == "edit_age") $kb = getAgeKeyboard();
        if ($text == "edit_province") $kb = getProvinceKeyboard();
        if ($text == "edit_gender") $kb = $genderMenu;
        if ($text == "edit_location") $kb = $locationMenu;
        if ($text == "edit_image") {
            $msg = "لطفا یک عکس برای پروفایل خود ارسال کنید (حجم کمتر از 500 کیلوبایت):";
        }
        sendMessage($chat_id, $msg, $kb);
        exit;
    }
    
    if (strpos($text, "like_") === 0) {
        if (!isProfileCompleteForAction($user)) {
            sendMessage($chat_id, "شما به دلیل ناقص بودن پروفایلتان مجاز به لایک کردن نیستید. لطفا پروفایل خود را تکمیل کنید.");
            exit;
        }
        $liked_id = str_replace("like_", "", $text);
        $stmt = $pdo->prepare("SELECT * FROM profile_likes WHERE liker_id = ? AND liked_id = ?");
        $stmt->execute([$chat_id, $liked_id]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO profile_likes (liker_id, liked_id) VALUES (?, ?)")->execute([$chat_id, $liked_id]);
            sendMessage($chat_id, "پروفایل کاربر لایک شد! ❤️ (جهت مشاهده تغییر رنگ مجددا پروفایل را لود کنید)");
            sendMessage($liked_id, "❤️ یک نفر پروفایل شما را لایک کرد! (آیدی کاربر: /" . $user['username'] . ")");
        }
        exit;
    }

    if (strpos($text, "unlike_") === 0) {
        $liked_id = str_replace("unlike_", "", $text);
        $pdo->prepare("DELETE FROM profile_likes WHERE liker_id = ? AND liked_id = ?")->execute([$chat_id, $liked_id]);
        sendMessage($chat_id, "لایک شما برداشته شد 🤍 (جهت مشاهده تغییر مجددا پروفایل را لود کنید)");
        exit;
    }
    
    if (strpos($text, "pblock_") === 0) {
        $target_id = str_replace("pblock_", "", $text);
        $pdo->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)")->execute([$chat_id, $target_id]);
        sendMessage($chat_id, "🚫 کاربر با موفقیت بلاک شد. (جهت مشاهده تغییر، مجددا پروفایل را لود کنید)");
        exit;
    }

    if (strpos($text, "punblock_") === 0) {
        $target_id = str_replace("punblock_", "", $text);
        $pdo->prepare("DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?")->execute([$chat_id, $target_id]);
        sendMessage($chat_id, "✅ کاربر از لیست بلاک خارج شد. (جهت مشاهده تغییر، مجددا پروفایل را لود کنید)");
        exit;
    }

    if (strpos($text, "reqchat_") === 0) {
        if (!isProfileCompleteForAction($user)) {
            sendMessage($chat_id, "شما به دلیل ناقص بودن پروفایلتان مجاز به ارسال درخواست چت نیستید. لطفا پروفایل خود را تکمیل کنید.");
            exit;
        }
        $target_id = str_replace("reqchat_", "", $text);
        
        $stmtT = $pdo->prepare("SELECT last_activity FROM users WHERE chat_id = ?");
        $stmtT->execute([$target_id]);
        $targetUser = $stmtT->fetch();
        if (!$targetUser || (time() - strtotime($targetUser['last_activity']) > 1800)) {
            sendMessage($chat_id, "❌ این کاربر در ۳۰ دقیقه اخیر فعال نبوده است و نمی‌توانید به او درخواست چت بدهید.");
            exit;
        }
        
        if ($user['coins'] < 1) {
            sendMessage($chat_id, "موجودی سکه شما کافی نیست! (نیاز به 1 سکه)");
            exit;
        }
        $blockCheck = $pdo->prepare("SELECT id FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
        $blockCheck->execute([$chat_id, $target_id, $target_id, $chat_id]);
        if ($blockCheck->fetch()) {
            sendMessage($chat_id, "🚫 ارتباط با این کاربر امکان‌پذیر نیست.");
            exit;
        }
        $reqMenu = ["inline_keyboard" => [[["text" => "✅ قبول چت", "callback_data" => "accchat_" . $chat_id], ["text" => "❌ رد کردن", "callback_data" => "rejchat_" . $chat_id]]]];
        sendMessage($target_id, "کاربر /" . $user['username'] . " درخواست چت با شما را دارد.", $reqMenu);
        sendMessage($chat_id, "درخواست چت برای کاربر ارسال شد. در صورت تایید، 1 سکه کسر خواهد شد.");
        exit;
    }
    
    if (strpos($text, "accchat_") === 0) {
        $requester_id = str_replace("accchat_", "", $text);
        $stmt = $pdo->prepare("SELECT coins, username FROM users WHERE chat_id = ?");
        $stmt->execute([$requester_id]);
        $requester = $stmt->fetch();
        
        if ($requester['coins'] >= 1) {
            $pdo->prepare("UPDATE users SET coins = coins - 1 WHERE chat_id = ?")->execute([$requester_id]);
            $pdo->prepare("INSERT INTO chats (user1_id, user2_id, status) VALUES (?, ?, 'active')")->execute([$requester_id, $chat_id]);
            $pdo->prepare("UPDATE users SET step = 'in_chat' WHERE chat_id IN (?, ?)")->execute([$requester_id, $chat_id]);
            sendMessage($requester_id, "✅ درخواست شما قبول شد! 1 سکه کسر شد. چت با /" . $user['username'] . " شروع شد.", $chatMenu);
            sendMessage($chat_id, "شما درخواست را قبول کردید. چت با /" . $requester['username'] . " شروع شد.", $chatMenu);
        } else {
            sendMessage($chat_id, "کاربر درخواست‌دهنده سکه کافی ندارد.");
        }
        exit;
    }
    
    if (strpos($text, "rejchat_") === 0) {
        $requester_id = str_replace("rejchat_", "", $text);
        sendMessage($requester_id, "❌ کاربر درخواست چت شما را رد کرد.");
        sendMessage($chat_id, "درخواست چت رد شد.");
        exit;
    }
}

$is_editing = strpos($step, 'edit_') === 0;

if ($step == 'ask_name' || $step == 'edit_name') {
    if (!$is_editing && empty($text)) { sendMessage($chat_id, "برای لغو لطفا ❌ لغو بزنید.", $cancelMenu); exit; }
    $pdo->prepare("UPDATE users SET fullname = ?, step = ? WHERE chat_id = ?")->execute([htmlspecialchars($text), $is_editing ? 'idle' : 'ask_gender', $chat_id]);
    if ($is_editing) { sendMessage($chat_id, "نام تغییر یافت.", $backOnlyMenu); checkProfileCompletion($user, $pdo); exit; }
    sendMessage($chat_id, "جنسیت خود را انتخاب کنید:", $genderMenu);
} 
elseif ($step == 'ask_gender' || $step == 'edit_gender') {
    if (!in_array($text, ["پسر 👨", "دختر 👩"])) { sendMessage($chat_id, "لطفا از منوی پایین انتخاب کنید یا لغو بزنید:", $genderMenu); exit; }
    $gender_val = str_replace([" 👨", " 👩"], "", $text);
    $pdo->prepare("UPDATE users SET gender = ?, step = ? WHERE chat_id = ?")->execute([$gender_val, $is_editing ? 'idle' : 'ask_age', $chat_id]);
    if ($is_editing) { sendMessage($chat_id, "جنسیت تغییر یافت.", $backOnlyMenu); checkProfileCompletion($user, $pdo); exit; }
    sendMessage($chat_id, "سن خود را انتخاب کنید:", getAgeKeyboard());
}
elseif ($step == 'ask_age' || $step == 'edit_age') {
    $age = (int)$text;
    if ($age < 15 || $age > 40) { sendMessage($chat_id, "لطفا سن معتبر (15 تا 40) را از کیبورد انتخاب کنید:", getAgeKeyboard()); exit; }
    $pdo->prepare("UPDATE users SET age = ?, step = ? WHERE chat_id = ?")->execute([$age, $is_editing ? 'idle' : 'ask_province', $chat_id]);
    if ($is_editing) { sendMessage($chat_id, "سن تغییر یافت.", $backOnlyMenu); checkProfileCompletion($user, $pdo); exit; }
    sendMessage($chat_id, "استان محل سکونت خود را انتخاب کنید:", getProvinceKeyboard());
} 
elseif ($step == 'ask_province' || $step == 'edit_province') {
    $pdo->prepare("UPDATE users SET province = ?, step = ? WHERE chat_id = ?")->execute([htmlspecialchars($text), $is_editing ? 'idle' : 'ask_city', $chat_id]);
    if ($is_editing) { sendMessage($chat_id, "استان تغییر یافت.", $backOnlyMenu); checkProfileCompletion($user, $pdo); exit; }
    sendMessage($chat_id, "شهر خود را تایپ کنید:", $cancelMenu);
}
elseif ($step == 'ask_city' || $step == 'edit_city') {
    $pdo->prepare("UPDATE users SET city = ?, step = ? WHERE chat_id = ?")->execute([htmlspecialchars($text), $is_editing ? 'idle' : 'ask_location', $chat_id]);
    if ($is_editing) { sendMessage($chat_id, "شهر تغییر یافت.", $backOnlyMenu); checkProfileCompletion($user, $pdo); exit; }
    sendMessage($chat_id, "در صورت تمایل لوکیشن ارسال کنید یا «رد کردن» را بزنید:", $locationMenu);
}
elseif ($step == 'ask_location' || $step == 'edit_location') {
    if ($text == "رد کردن ⏭️" || $location) {
        if ($location) {
            $pdo->prepare("UPDATE users SET lat = ?, lon = ? WHERE chat_id = ?")->execute([$location['latitude'], $location['longitude'], $chat_id]);
        }
        $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id = ?")->execute([$chat_id]);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
        $updated_user = $stmt->fetch();
        checkProfileCompletion($updated_user, $pdo);
        
        sendMessage($chat_id, "اطلاعات با موفقیت ثبت شد! برای آپلود عکس میتوانید از بخش پروفایل اقدام کنید.", $is_editing ? $backOnlyMenu : $mainMenu);
    } else {
        sendMessage($chat_id, "لطفا لوکیشن بفرستید، رد کنید یا لغو بزنید.", $locationMenu);
    }
}
elseif ($step == 'edit_image') {
    if (isset($message['photo'])) {
        $file_id = end($message['photo'])['file_id'];
        $result = compressAndSaveImage($file_id);
        
        if ($result === 'TOO_LARGE') {
            sendMessage($chat_id, "❌ حجم فایل بیشتر از 500 کیلوبایت است. لطفاً عکس کم‌حجم‌تری ارسال کنید.");
        } elseif ($result) {
            $pdo->prepare("UPDATE users SET image = ?, step = 'idle' WHERE chat_id = ?")->execute([$result, $chat_id]);
            sendMessage($chat_id, "✅ عکس پروفایل با موفقیت تغییر کرد.", $backOnlyMenu);
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
            $stmt->execute([$chat_id]);
            $updated_user = $stmt->fetch();
            checkProfileCompletion($updated_user, $pdo);
        } else {
            sendMessage($chat_id, "❌ خطا در آپلود عکس. دوباره تلاش کنید.");
        }
    } else {
        sendMessage($chat_id, "لطفا فقط یک عکس به عنوان پروفایل ارسال کنید.");
    }
}
elseif (in_array($step, ['in_queue', 'in_queue_girl', 'in_queue_boy'])) {
    sendMessage($chat_id, "لطفا منتظر بمانید یا برای لغو جستجو دکمه ❌ لغو جستجو را بزنید.");
}
elseif (strpos($step, 'filter_') === 0) {
    if ($step == 'filter_province_gender') {
        $g = str_replace([" 👨", " 👩"], "", $text);
        if (!in_array($g, ["پسر", "دختر"])) { sendMessage($chat_id, "لطفا جنسیت را انتخاب کنید:", $genderMenu); exit; }
        $pdo->prepare("UPDATE users SET step = ? WHERE chat_id = ?")->execute(["search_province_".$g, $chat_id]);
        sendMessage($chat_id, "استان مورد نظر را انتخاب کنید:", getProvinceKeyboard());
    }
    elseif ($step == 'filter_onl_gender') {
        $g = str_replace([" 👨", " 👩"], "", $text);
        if (!in_array($g, ["پسر", "دختر"])) { sendMessage($chat_id, "لطفا جنسیت را انتخاب کنید:", $genderMenu); exit; }
        $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id = ?")->execute([$chat_id]);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE last_activity >= NOW() - INTERVAL 30 MINUTE AND gender = ? AND chat_id != ? ORDER BY last_activity DESC LIMIT 10");
        $stmt->execute([$g, $chat_id]);
        $results = $stmt->fetchAll();
        formatSearchResults($results, $chat_id, 'onl', $g, 0, null);
    }
    elseif ($step == 'filter_near_gender') {
        $g = str_replace([" 👨", " 👩"], "", $text);
        if (!in_array($g, ["پسر", "دختر"])) { sendMessage($chat_id, "لطفا جنسیت را انتخاب کنید:", $genderMenu); exit; }
        $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id = ?")->execute([$chat_id]);
        if ($user['lat'] && $user['lon']) {
            $stmt = $pdo->prepare("SELECT *, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lon) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance FROM users WHERE chat_id != ? AND gender = ? AND lat IS NOT NULL ORDER BY last_activity DESC LIMIT 10");
            $stmt->execute([$user['lat'], $user['lon'], $user['lat'], $chat_id, $g]);
            $results = $stmt->fetchAll();
            formatSearchResults($results, $chat_id, 'near', $g, 0, null);
        }
    }
}
elseif (strpos($step, 'search_') === 0) {
    if (strpos($step, 'search_province_') === 0) {
        $g = str_replace('search_province_', '', $step);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE province = ? AND gender = ? AND chat_id != ? ORDER BY last_activity DESC LIMIT 10");
        $stmt->execute([$text, $g, $chat_id]);
        $results = $stmt->fetchAll();
        formatSearchResults($results, $chat_id, 'prov', $text, 0, $g);
        $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id = ?")->execute([$chat_id]);
    }
    elseif ($step == 'search_gender') {
        $g = str_replace([" 👨", " 👩"], "", $text);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE gender = ? AND chat_id != ? ORDER BY last_activity DESC LIMIT 10");
        $stmt->execute([$g, $chat_id]);
        $results = $stmt->fetchAll();
        formatSearchResults($results, $chat_id, 'gen', $g, 0, null);
        $pdo->prepare("UPDATE users SET step = 'idle' WHERE chat_id = ?")->execute([$chat_id]);
    }
}
elseif ($step == 'idle') {
    if ($text == "👤 پروفایل من") {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM profile_likes WHERE liked_id = ?");
        $stmt->execute([$chat_id]);
        $likes = $stmt->fetch()['count'];
        
        $profile_text = "پروفایل شما\n\n" .
                        "نام: " . ($user['fullname'] ?? 'ثبت نشده') . "\n" .
                        "جنسیت: " . ($user['gender'] ?? 'ثبت نشده') . "\n" .
                        "سن: " . ($user['age'] ?? 'ثبت نشده') . "\n" .
                        "استان: " . ($user['province'] ?? 'ثبت نشده') . "\n" .
                        "شهر: " . ($user['city'] ?? 'ثبت نشده') . "\n" .
                        "سکه: " . $user['coins'] . " 🪙\n" .
                        "لایک‌ها: " . $likes . " ❤️\n\n" .
                        "لینک پروفایل شما: /" . $user['username'];
        
        $photo = $user['image'] ?? null;
        if (!empty($photo) && file_exists(__DIR__ . '/uploads_bot/' . $photo)) {
            $photo_url = $bot_domain . "/uploads_bot/" . $photo;
        } else {
            $photo_url = $bot_domain . "/default.png";
        }
        
        sendMessage($chat_id, "تنظیمات پروفایل شما:", $backOnlyMenu);
        sendMediaMessage($chat_id, 'photo', $photo_url, $profile_text, $myProfileInlineMenu);
    }
    elseif ($text == "☎️ ارتباط با ما") {
        $info = "تیم آتوکس معرفی:\n" .
                "ما یک وب اپلیکیشن تعاملی داریم که می‌توانید در آن پست بگذارید و با دوستان خود در ارتباط باشید. \n" .
                "🌐 آدرس: atoxcomputer.ir\n\n" .
                "کانال‌های ما در بله:\n" .
                "🆔 @atoxcomputer\n" .
                "🆔 @chaatino\n\n" .
                "راه ارتباطی مستقیم گروه اتوکس:\n" .
                "👥 @atoxgroup";
        sendMessage($chat_id, $info);
    }
    elseif ($text == "📜 قوانین") {
        $rules = "قوانین ربات:\n" .
                 "1. ارسال محتوای مستهجن و غیر اخلاقی اکیداً ممنوع است.\n" .
                 "2. ایجاد مزاحمت برای کاربران دیگر باعث مسدود شدن حساب شما خواهد شد.\n" .
                 "3. مسئولیت تبادل اطلاعات شخصی به عهده خود کاربر می‌باشد.\n" .
                 "4. کلاهبرداری و درخواست وجه ممنوع است.\n" .
                 "5. رعایت احترام متقابل الزامی است.\n\n" .
                 "تابع اپلیکیشن بله هستیم.\n" .
                 "با تشکر تیم آتوکس";
        sendMessage($chat_id, $rules);
    }
    elseif ($text == "🔗 دعوت از دوستان") {
        $msg = "🎉 به چتینو بپیوندید!\nمحیطی امن و جذاب برای چت ناشناس و پیدا کردن دوستان جدید در بله.\n\nبا دعوت دوستان خود به ربات، 10 سکه رایگان دریافت کنید!\n\nلینک اختصاصی شما:\nhttps://ble.ir/chaatinobot?start=ref_" . $chat_id;
        $photo_url = $bot_domain . "/default123.png";
        sendMediaMessage($chat_id, 'photo', $photo_url, $msg);
    }
    elseif ($text == "💬 چت ناشناس") {
        sendMessage($chat_id, "نوع چت را انتخاب کنید:", $chatTypeMenu);
    }
    elseif ($text == "🔍 جستجوی پیشرفته") {
        sendMessage($chat_id, "نوع جستجو را انتخاب کنید:", $searchMenu);
    }
    elseif ($text == "📍 جستجو بر اساس استان") {
        $pdo->prepare("UPDATE users SET step = 'filter_province_gender' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "بین دختر و پسر کدام را انتخاب می‌کنید؟", $genderMenu);
    }
    elseif ($text == "🚻 جستجو بر اساس جنسیت") {
        $pdo->prepare("UPDATE users SET step = 'search_gender' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "جنسیت مورد نظر را انتخاب کنید:", $genderMenu);
    }
    elseif ($text == "🟢 آخرین آنلاین‌ها") {
        $pdo->prepare("UPDATE users SET step = 'filter_onl_gender' WHERE chat_id = ?")->execute([$chat_id]);
        sendMessage($chat_id, "بین دختر و پسر کدام را انتخاب می‌کنید؟", $genderMenu);
    }
    elseif ($text == "🗺 نزدیک‌ترین افراد") {
        if ($user['lat'] && $user['lon']) {
            $pdo->prepare("UPDATE users SET step = 'filter_near_gender' WHERE chat_id = ?")->execute([$chat_id]);
            sendMessage($chat_id, "بین دختر و پسر کدام را انتخاب می‌کنید؟", $genderMenu);
        } else {
            sendMessage($chat_id, "ابتدا باید لوکیشن خود را در پروفایل ثبت کنید.");
        }
    }
    elseif ($text == "🎲 چت تصادفی (رایگان)") {
        if (!isProfileCompleteForAction($user)) { sendMessage($chat_id, "جهت چت ابتدا باید پروفایل خود را تکمیل کنید (نام، سن، جنسیت، استان)."); exit; }
        matchChat($pdo, $chat_id, $user, 'in_queue', null);
    }
    elseif ($text == "👩 چت با دختر (1 سکه)") {
        if (!isProfileCompleteForAction($user)) { sendMessage($chat_id, "جهت چت ابتدا باید پروفایل خود را تکمیل کنید (نام، سن، جنسیت، استان)."); exit; }
        if ($user['coins'] >= 1) {
            matchChat($pdo, $chat_id, $user, 'in_queue_girl', 'دختر');
        } else {
            sendMessage($chat_id, "سکه کافی ندارید! برای جستجوی دختر نیاز به 1 سکه دارید.");
        }
    }
    elseif ($text == "👨 چت با پسر (1 سکه)") {
        if (!isProfileCompleteForAction($user)) { sendMessage($chat_id, "جهت چت ابتدا باید پروفایل خود را تکمیل کنید (نام، سن، جنسیت، استان)."); exit; }
        if ($user['coins'] >= 1) {
            matchChat($pdo, $chat_id, $user, 'in_queue_boy', 'پسر');
        } else {
            sendMessage($chat_id, "سکه کافی ندارید! برای جستجوی پسر نیاز به 1 سکه دارید.");
        }
    }
    else {
        sendMessage($chat_id, "لطفا از منوها استفاده کنید:", $mainMenu);
    }
}

function formatSearchResults($results, $chat_id, $type, $param1, $offset, $param2) {
    global $mainMenu;
    if ($results) {
        $msg = "✨ <b>نتایج یافت شده:</b>\n\n";
        foreach($results as $r) {
            $last_seen = isset($r['last_activity']) ? timeAgo($r['last_activity']) : 'نامشخص';
            $msg .= "👤 کاربر: /" . $r['username'] . "\n";
            $msg .= "🔖 نام: " . ($r['fullname'] ?? 'نامشخص') . " | " . ($r['gender'] ?? '') . " | " . ($r['age'] ?? '') . " ساله\n";
            if(isset($r['distance'])) {
                $msg .= "📍 فاصله تقریبی: " . round($r['distance'], 1) . " کیلومتر\n";
            }
            $msg .= "🕒 آخرین بازدید: " . $last_seen . "\n";
            $msg .= "➖〰️➖〰️➖〰️➖\n";
        }
        
        $keyboard = ["inline_keyboard" => []];
        $nav_row = [];

        if ($offset >= 10) {
            $prev_offset = $offset - 10;
            $cb_data_prev = "page_{$type}_{$prev_offset}" . ($param1 ? "_{$param1}" : "") . ($param2 ? "_{$param2}" : "");
            $nav_row[] = ["text" => "⬅️ صفحه قبلی", "callback_data" => substr($cb_data_prev, 0, 64)];
        }
        if (count($results) == 10) {
            $next_offset = $offset + 10;
            $cb_data_next = "page_{$type}_{$next_offset}" . ($param1 ? "_{$param1}" : "") . ($param2 ? "_{$param2}" : "");
            $nav_row[] = ["text" => "صفحه بعدی ➡️", "callback_data" => substr($cb_data_next, 0, 64)];
        }

        if (count($nav_row) > 0) {
            $keyboard["inline_keyboard"][] = $nav_row;
            sendMessage($chat_id, $msg, $keyboard);
        } else {
            sendMessage($chat_id, $msg, $mainMenu);
        }
        
    } else {
        sendMessage($chat_id, "مورد دیگری یافت نشد.", $mainMenu);
    }
}

function matchChat($pdo, $chat_id, $user, $queue_step, $target_gender) {
    global $chatMenu;
    
    $my_gender = $user['gender'] ?? '';
    
    $query = "SELECT chat_id, username FROM users u 
              WHERE chat_id != :me 
              AND NOT EXISTS (SELECT 1 FROM blocks WHERE (blocker_id = :me AND blocked_id = u.chat_id) OR (blocker_id = u.chat_id AND blocked_id = :me))";
    
    if ($target_gender == 'دختر') {
        if ($my_gender == 'پسر') {
            $query .= " AND gender = 'دختر' AND step IN ('in_queue', 'in_queue_boy')";
        } else {
            $query .= " AND gender = 'دختر' AND step IN ('in_queue', 'in_queue_girl')";
        }
    } elseif ($target_gender == 'پسر') {
        if ($my_gender == 'پسر') {
            $query .= " AND gender = 'پسر' AND step IN ('in_queue', 'in_queue_boy')";
        } else {
            $query .= " AND gender = 'پسر' AND step IN ('in_queue', 'in_queue_girl')";
        }
    } else {
        if ($my_gender == 'پسر') {
            $query .= " AND step IN ('in_queue', 'in_queue_boy')";
        } else {
            $query .= " AND step IN ('in_queue', 'in_queue_girl')";
        }
    }
    
    $stmt = $pdo->prepare($query . " LIMIT 1");
    $stmt->execute(['me' => $chat_id]);
    $partner = $stmt->fetch();

    if ($partner) {
        $pid = $partner['chat_id'];
        
        if ($target_gender == 'دختر' || $target_gender == 'پسر') {
            $pdo->prepare("UPDATE users SET coins = coins - 1 WHERE chat_id = ?")->execute([$chat_id]);
            sendMessage($chat_id, "1 سکه بابت پیدا شدن فرد مورد نظر کسر شد.");
        }
        
        $pdo->prepare("INSERT INTO chats (user1_id, user2_id, status) VALUES (?, ?, 'active')")->execute([$chat_id, $pid]);
        $pdo->prepare("UPDATE users SET step = 'in_chat' WHERE chat_id IN (?, ?)")->execute([$chat_id, $pid]);
        
        sendMessage($chat_id, "✅ یک نفر پیدا شد! چت با /" . $partner['username'] . " شروع شد.", $chatMenu);
        sendMessage($pid, "✅ یک نفر پیدا شد! چت با /" . $user['username'] . " شروع شد.", $chatMenu);
    } else {
        $pdo->prepare("UPDATE users SET step = ? WHERE chat_id = ?")->execute([$queue_step, $chat_id]);
        sendMessage($chat_id, "⏳ در حال جستجو برای فرد مناسب...\n(سکه شما تنها پس از یافتن شخص کسر خواهد شد)\nاگر در 5 دقیقه کسی پیدا نشد، جستجو لغو می‌شود.\nبرای لغو دستی کلیک کنید:", ["keyboard" => [[["text" => "❌ لغو جستجو"]]], "resize_keyboard" => true]);
    }
}
?>
