<?php
// webhook.php

// تنظیم هدرهای امنیتی برای جلوگیری از تفسیر اشتباه فایل
header("X-Content-Type-Options: nosniff");
header("Content-Type: application/json; charset=UTF-8");

require 'db.php'; // اتصال به دیتابیس

$token = "496523818:-O8WYpL_W65TXhuNhhix3PsISalNHwDuwZw";
$apiUrl = "https://tapi.bale.ai/bot" . $token . "/";

// دریافت و اعتبارسنجی ورودی (جلوگیری از حملات XSS و Injection)
$content = file_get_contents("php://input");
if (empty($content)) {
    http_response_code(400);
    exit;
}

$update = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($update["message"])) {
    http_response_code(400);
    exit;
}

$message = $update["message"];
$chat_id = htmlspecialchars(trim($message["chat"]["id"] ?? ''), ENT_QUOTES, 'UTF-8');
$from_id = htmlspecialchars(trim($message["from"]["id"] ?? ''), ENT_QUOTES, 'UTF-8');

if (empty($chat_id) || empty($from_id)) {
    exit;
}

// اگر کاربر دستور /start را زد
if (isset($message["text"])) {
    $text = trim($message["text"]);
    if ($text == "/start") {
        $keyboard = [
            "keyboard" => [
                [
                    ["text" => "📱 ارسال شماره تلفن من برای دریافت کد", "request_contact" => true]
                ]
            ],
            "resize_keyboard" => true,
            "one_time_keyboard" => true
        ];
        
        sendMessage($chat_id, "سلام! برای دریافت کد تایید سایت، لطفاً فقط روی دکمه زیر کلیک کنید تا شماره خودتان تایید شود.", $keyboard);
    } else {
        // نادیده گرفتن متون متفرقه
        sendMessage($chat_id, "لطفا فقط از دکمه تعبیه شده برای تایید شماره استفاده کنید.");
    }
}
// اگر کاربر اطلاعات کانتکت را ارسال کرد
elseif (isset($message["contact"])) {
    $contact = $message["contact"];
    $contact_user_id = $contact["user_id"] ?? null;
    $phone = htmlspecialchars(trim($contact["phone_number"] ?? ''), ENT_QUOTES, 'UTF-8');
    
    // **بررسی امنیتی بسیار مهم:** 
    if (empty($contact_user_id) || $contact_user_id != $from_id) {
        sendMessage($chat_id, "❌ خطای امنیتی: شما فقط مجاز به ارسال شماره تلفن اکانت خودتان از طریق دکمه پایین صفحه هستید. ارسال شماره افراد دیگر از لیست مخاطبین مجاز نیست.");
        exit;
    }

    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (substr($phone, 0, 2) == "98") { $phone = "0" . substr($phone, 2); }
    elseif (substr($phone, 0, 3) == "+98") { $phone = "0" . substr($phone, 3); }
    elseif (substr($phone, 0, 1) != "0") { $phone = "0" . $phone; }

    try {
        // ذخیره chat_id برای این شماره در دیتابیس با PDO
        $stmt = $pdo->prepare("INSERT INTO bale_contacts (phone, chat_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE chat_id = ?");
        $stmt->execute([$phone, $chat_id, $chat_id]);

        // بررسی اینکه آیا این شماره الان درخواست OTP در سایت داشته؟
        $stmt = $pdo->prepare("SELECT otp_code, expires_at FROM otp_requests WHERE phone = ?");
        $stmt->execute([$phone]);
        $otp_req = $stmt->fetch();

        if ($otp_req) {
            if (strtotime($otp_req['expires_at']) > time()) {
                sendMessage($chat_id, "🔐 کد تایید شما برای ورود/ثبت‌نام:\n\n" . htmlspecialchars($otp_req['otp_code'], ENT_QUOTES, 'UTF-8'));
            } else {
                sendMessage($chat_id, "❌ کد تایید شما منقضی شده است. لطفا در سایت دوباره درخواست دهید.");
            }
        } else {
            sendMessage($chat_id, "شما در حال حاضر هیچ درخواست فعالِ ورود، ثبت‌نام یا فراموشی رمزی در سایت ندارید.");
        }
    } catch (PDOException $e) {
        sendMessage($chat_id, "خطا در ارتباط با سرور رخ داد. لطفا لحظاتی بعد تلاش کنید.");
    }
}
else {
    sendMessage($chat_id, "❌ ارسال این نوع پیام (عکس، فایل، موقعیت مکانی و...) مجاز نیست. فقط دکمه ارسال شماره را فشار دهید.");
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    global $apiUrl;
    $data = [
        'chat_id' => $chat_id, 
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($reply_markup) { 
        $data['reply_markup'] = json_encode($reply_markup); 
    }
    
    $ch = curl_init($apiUrl . "sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Content-Type-Options: nosniff'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // جلوگیری از گیر کردن اسکریپت
    curl_exec($ch);
    curl_close($ch);
}
?>
