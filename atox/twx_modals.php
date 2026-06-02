<?php
$current_user_level = isset($user_data['level']) ? (int)$user_data['level'] : 1;
$current_user_role = isset($user_data['role']) ? htmlspecialchars($user_data['role'], ENT_QUOTES, 'UTF-8') : 'user';
$current_user_avatar = !empty($user_data['avatar']) ? htmlspecialchars($user_data['avatar'], ENT_QUOTES, 'UTF-8') : 'https://ui-avatars.com/api/?name=User&background=random&color=fff&bold=true';
$current_user_name = !empty($user_data['name']) ? htmlspecialchars($user_data['name'], ENT_QUOTES, 'UTF-8') : 'کاربر';
$current_user_username = !empty($user_data['username']) ? htmlspecialchars($user_data['username'], ENT_QUOTES, 'UTF-8') : 'user';
$is_user_logged = isset($is_logged) ? (bool)$is_logged : false;
$csrf_token = isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') : '';

$tweet_count_24h = 0;
$comment_count_24h = 0;
$rt_count_24h = 0;

if ($is_user_logged && isset($pdo)) {
    $uid = isset($user_data['id']) ? $user_data['id'] : $user_id;
    $stmtCounts = $pdo->prepare("SELECT 
        SUM(CASE WHEN is_comment = 0 AND is_retweet = 0 THEN 1 ELSE 0 END) as tw_count,
        SUM(CASE WHEN is_comment = 1 THEN 1 ELSE 0 END) as cm_count,
        SUM(CASE WHEN is_retweet = 1 THEN 1 ELSE 0 END) as rt_count
        FROM tweets WHERE user_id = ? AND created_at >= NOW() - INTERVAL 24 HOUR");
    $stmtCounts->execute([$uid]);
    $counts = $stmtCounts->fetch(PDO::FETCH_ASSOC);
    $tweet_count_24h = (int)($counts['tw_count'] ?? 0);
    $comment_count_24h = (int)($counts['cm_count'] ?? 0);
    $rt_count_24h = (int)($counts['rt_count'] ?? 0);
}
?>
<style>
.twx-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 16px; opacity: 0; transition: opacity 0.25s ease; will-change: opacity; }
.twx-modal-overlay.active { display: flex; opacity: 1; }
.twx-modal-box { background: var(--x-bg, #fff); border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); transform: scale(0.95) translateY(10px); transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; flex-direction: column; overflow: hidden; position: relative; width: 100%; max-width: 550px; max-height: 90vh; will-change: transform; }
.dark .twx-modal-box { background: #000; box-shadow: 0 10px 40px rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
.twx-modal-box.lg { max-width: 600px; }
.twx-modal-box.sm { max-width: 340px; padding: 32px 24px 24px; text-align: center; }
.twx-modal-overlay.active .twx-modal-box { transform: scale(1) translateY(0); }
.twx-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--x-border, #eff3f4); flex-direction: row-reverse; flex-shrink: 0; }
.dark .twx-header { border-bottom-color: #2f3336; }
.twx-title { font-size: 18px; font-weight: 800; color: var(--x-black, #0f1419); position: absolute; left: 50%; transform: translateX(-50%); }
.dark .twx-title { color: #e7e9ea; }
.twx-close { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--x-black, #0f1419); cursor: pointer; transition: 0.2s; user-select: none; z-index: 2; border: none; background: transparent; }
.dark .twx-close { color: #e7e9ea; }
.twx-close:hover { background: var(--x-hover, rgba(15,20,25,0.1)); }
.dark .twx-close:hover { background: rgba(255,255,255,0.1); }
.twx-body { padding: 16px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; flex: 1; }
.twx-user-info { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-shrink: 0; }
.twx-user-info img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
.twx-user-details { display: flex; flex-direction: column; line-height: 1.2; }
.twx-user-name { font-weight: bold; font-size: 15px; color: var(--x-black, #0f1419); }
.dark .twx-user-name { color: #e7e9ea; }
.twx-user-id { font-size: 14px; color: var(--x-gray, #536471); }
.twx-textarea { width: 100%; min-height: 120px; border: none; background: transparent; color: var(--x-black, #0f1419); font-size: 18px; resize: none; outline: none; font-family: inherit; line-height: 1.6; flex-shrink: 0; }
.dark .twx-textarea { color: #e7e9ea; }
.twx-textarea::placeholder { color: var(--x-gray, #536471); }
.twx-footer { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px 16px; border-top: 1px solid var(--x-border, #eff3f4); flex-shrink: 0; }
.dark .twx-footer { border-top-color: #2f3336; }
.twx-counter { font-size: 14px; color: var(--x-gray, #536471); font-family: Consolas, monospace; font-weight: 500; transition: color 0.2s; }
.twx-counter.limit { color: #f91880; font-weight: bold; }
.twx-btn-save { background: var(--x-black, #0f1419); color: var(--x-bg, #fff); border: none; padding: 8px 20px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
.dark .twx-btn-save { background: #eff3f4; color: #0f1419; }
.twx-btn-save:hover { opacity: 0.8; }
.twx-btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
.twx-btn-primary { background: var(--x-blue, #1d9bf0); color: #fff; }
.twx-btn-primary:hover { background: #1a8cd8; }
.twx-img-preview-wrap { position: relative; margin-top: 12px; border-radius: 16px; overflow: hidden; border: 1px solid var(--x-border, #eff3f4); display: none; background: var(--x-bg, #fff); flex-shrink: 0; }
.dark .twx-img-preview-wrap { border-color: #2f3336; background: #000; }
.twx-img-preview-wrap img { width: 100%; max-height: 350px; object-fit: contain; display: block; background: #000; border-bottom: 1px solid var(--x-border, #eff3f4); }
.dark .twx-img-preview-wrap img { border-bottom-color: #2f3336; }
.twx-img-remove { position: absolute; top: 8px; right: 8px; width: 32px; height: 32px; border-radius: 50%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: 0.2s; z-index: 10; }
.twx-img-remove:hover { background: rgba(0,0,0,0.9); }
.twx-img-success { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; background: rgba(29, 155, 240, 0.05); color: var(--x-blue, #1d9bf0); font-weight: bold; font-size: 14px; user-select: none; }
.dark .twx-img-success { background: rgba(29, 155, 240, 0.1); }
.twx-img-success svg { width: 20px; height: 20px; fill: currentColor; }
.twx-toolbar { display: flex; align-items: center; gap: 12px; padding-top: 8px; }
.twx-tool-btn { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--x-blue, #1d9bf0); background: transparent; cursor: pointer; transition: 0.2s; position: relative; overflow: hidden; }
.twx-tool-btn:hover { background: rgba(29,155,240,0.1); }
.twx-tool-btn input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
@keyframes twx-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.twx-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: currentColor; border-radius: 50%; animation: twx-spin 0.8s linear infinite; display: none; }
.twx-btn-save.loading .twx-spinner { display: block; }
.twx-btn-save.loading span { display: none; }
#twx-level-modal { z-index: 100005 !important; }
.twx-del-title { font-size: 20px; font-weight: 800; color: var(--x-black, #0f1419); margin-bottom: 12px; }
.dark .twx-del-title { color: #e7e9ea; }
.twx-del-desc { font-size: 15px; color: var(--x-gray, #536471); margin-bottom: 24px; line-height: 1.5; }
.twx-btn-danger { background: #f91880; color: #fff; border: none; padding: 14px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; width: 100%; margin-bottom: 10px; }
.twx-btn-danger:hover { background: #e01673; }
.twx-btn-cancel { background: transparent; color: var(--x-black, #0f1419); border: 1px solid var(--x-border, #eff3f4); padding: 14px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; width: 100%; }
.dark .twx-btn-cancel { color: #e7e9ea; border-color: #536471; }
</style>

<div id="twx-login-modal" class="twx-modal-overlay" onclick="closeModal('twx-login-modal')">
    <div class="twx-modal-box sm" onclick="event.stopPropagation()">
        <h2 class="twx-del-title">ورود به آتوکس</h2>
        <p class="twx-del-desc">برای ارسال پست، لایک و تعامل با دیگران، وارد حساب خود شوید.</p>
        <button class="twx-btn-save twx-btn-primary" style="width: 100%; justify-content:center; margin-bottom:10px" onclick="location.href='auth.php'"><span>ورود / ثبت‌نام</span></button>
        <button class="twx-btn-cancel" onclick="closeModal('twx-login-modal')">انصراف</button>
    </div>
</div>

<div id="twx-level-modal" class="twx-modal-overlay" onclick="closeModal('twx-level-modal')">
    <div class="twx-modal-box sm" onclick="event.stopPropagation()">
        <h2 class="twx-del-title">نیاز به ارتقاء سطح</h2>
        <p class="twx-del-desc">برای استفاده از قابلیت آپلود عکس، باید حداقل به لول ۱۵ برسید. فعالیت خود را بیشتر کنید تا سطح شما افزایش یابد!</p>
        <button class="twx-btn-cancel" onclick="closeModal('twx-level-modal')" style="background:var(--x-blue); color:#fff; border:none;">متوجه شدم</button>
    </div>
</div>

<?php if ($is_user_logged): ?>

<?php ob_start(); ?>
<div class="twx-user-info">
    <img src="<?=$current_user_avatar?>">
    <div class="twx-user-details">
        <span class="twx-user-name"><?=$current_user_name?></span>
        <span class="twx-user-id" dir="ltr">@<?=$current_user_username?></span>
    </div>
</div>
<?php $user_header_html = ob_get_clean(); ?>

<div id="twx-add-modal" class="twx-modal-overlay" onclick="closeModal('twx-add-modal')">
    <div class="twx-modal-box lg" onclick="event.stopPropagation()">
        <form method="POST" action="actions.php?action=tweet" enctype="multipart/form-data" style="margin:0; display:flex; flex-direction:column; height:100%;" onsubmit="return loadingBtn(this, event)">
            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
            <div class="twx-header">
                <button type="button" class="twx-close" onclick="closeModal('twx-add-modal')">×</button>
                <div class="twx-title">پست جدید</div>
                <button type="submit" class="twx-btn-save twx-btn-primary twx-submit-btn" disabled><span>پست کردن</span><div class="twx-spinner"></div></button>
            </div>
            <div class="twx-body">
                <?=$user_header_html?>
                <textarea name="description" class="twx-textarea twx-live-input" data-target="add" placeholder="چه اتفاقی در حال رخ دادن است؟" maxlength="1000"></textarea>
                
                <div id="add-img-wrap" class="twx-img-preview-wrap">
                    <img id="add-img-src" src="">
                    <button type="button" class="twx-img-remove" onclick="removeSelectedImage('add')">×</button>
                    <div class="twx-img-success">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
                        تصویر با موفقیت پیوست شد
                    </div>
                </div>
            </div>
            <div class="twx-footer">
                <div class="twx-toolbar">
                    <div class="twx-tool-btn" title="پیوست تصویر">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="25" height="25"><path d="M16.3 21.949H12.98C12.566 21.949 12.23 21.613 12.23 21.199C12.23 20.785 12.566 20.449 12.98 20.449H16.3C18.827 20.449 20.46 18.722 20.46 16.05V7.899C20.46 5.226 18.827 3.5 16.3 3.5H7.65C5.129 3.5 3.5 5.226 3.5 7.899V16.05C3.5 18.722 5.129 20.449 7.65 20.449H8.371C8.785 20.449 9.121 20.785 9.121 21.199C9.121 21.613 8.785 21.949 8.371 21.949H7.65C4.271 21.949 2 19.578 2 16.05V7.899C2 4.371 4.271 2 7.65 2H16.3C19.686 2 21.96 4.371 21.96 7.899V16.05C21.96 19.578 19.686 21.949 16.3 21.949ZM5.2815 17.1824C5.0965 17.1824 4.9105 17.1144 4.7655 16.9774C4.4655 16.6924 4.4525 16.2174 4.7375 15.9164L6.2665 14.3024C6.6745 13.8704 7.2265 13.6314 7.8215 13.6294C8.3955 13.6674 8.9705 13.8624 9.3825 14.2904L10.2635 15.1884C10.4005 15.3284 10.5805 15.4064 10.7815 15.3924C10.9775 15.3834 11.1545 15.2954 11.2795 15.1434L13.5085 12.4314C13.9485 11.8964 14.5985 11.5724 15.2915 11.5434C15.9825 11.5224 16.6585 11.7854 17.1405 12.2824L19.2175 14.4234C19.5055 14.7214 19.4985 15.1964 19.2005 15.4844C18.9035 15.7734 18.4285 15.7644 18.1405 15.4684L16.0635 13.3274C15.8755 13.1334 15.6295 13.0244 15.3525 13.0424C15.0825 13.0534 14.8395 13.1754 14.6675 13.3844L12.4385 16.0964C12.0455 16.5744 11.4655 16.8644 10.8475 16.8914C10.2285 16.9044 9.6265 16.6804 9.1925 16.2394L8.3065 15.3354C8.1765 15.2014 8.0115 15.0834 7.8275 15.1294C7.6465 15.1304 7.4795 15.2034 7.3555 15.3334L5.8255 16.9484C5.6785 17.1044 5.4805 17.1824 5.2815 17.1824ZM8.5603 11.6376C7.1793 11.6376 6.0563 10.5146 6.0563 9.1336C6.0563 7.7526 7.1793 6.6296 8.5603 6.6296C9.9403 6.6296 11.0633 7.7526 11.0633 9.1336C11.0633 10.5146 9.9403 11.6376 8.5603 11.6376ZM8.5603 8.1296C8.0063 8.1296 7.5563 8.5796 7.5563 9.1336C7.5563 9.6876 8.0063 10.1376 8.5603 10.1376C9.1133 10.1376 9.5633 9.6876 9.5633 9.1336C9.5633 8.5796 9.1133 8.1296 8.5603 8.1296Z" fill="#1ab2ff"></path></svg>
                        <input type="file" name="image" id="add-img-input" accept="image/jpeg,image/png,image/webp,image/gif" onclick="checkLevel(event)" onchange="handleImageUpload(this, 'add')">
                    </div>
                </div>
                <span id="add-counter" class="twx-counter" dir="ltr">0 / 1000</span>
            </div>
        </form>
    </div>
</div>

<div id="twx-reply-modal" class="twx-modal-overlay" onclick="closeModal('twx-reply-modal')">
    <div class="twx-modal-box lg" onclick="event.stopPropagation()">
        <form method="POST" action="actions.php?action=tweet" enctype="multipart/form-data" style="margin:0; display:flex; flex-direction:column; height:100%;" onsubmit="return loadingBtn(this, event)">
            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
            <input type="hidden" name="is_comment" value="1">
            <input type="hidden" name="parent_id" id="reply-parent-id">
            
            <div class="twx-header">
                <button type="button" class="twx-close" onclick="closeModal('twx-reply-modal')">×</button>
                <div class="twx-title">ارسال پاسخ</div>
                <button type="submit" class="twx-btn-save twx-btn-primary twx-submit-btn" disabled><span>پاسخ دادن</span><div class="twx-spinner"></div></button>
            </div>
            
            <div class="twx-body">
                <?=$user_header_html?>
                <textarea name="description" class="twx-textarea twx-live-input" data-target="reply" placeholder="پاسخ خود را بنویسید..." maxlength="1000"></textarea>
                
                <div id="reply-img-wrap" class="twx-img-preview-wrap">
                    <img id="reply-img-src" src="">
                    <button type="button" class="twx-img-remove" onclick="removeSelectedImage('reply')">×</button>
                    <div class="twx-img-success">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
                        تصویر با موفقیت پیوست شد
                    </div>
                </div>
            </div>
            <div class="twx-footer">
                <div class="twx-toolbar">
                    <div class="twx-tool-btn" title="پیوست تصویر">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="25" height="25"><path d="M16.3 21.949H12.98C12.566 21.949 12.23 21.613 12.23 21.199C12.23 20.785 12.566 20.449 12.98 20.449H16.3C18.827 20.449 20.46 18.722 20.46 16.05V7.899C20.46 5.226 18.827 3.5 16.3 3.5H7.65C5.129 3.5 3.5 5.226 3.5 7.899V16.05C3.5 18.722 5.129 20.449 7.65 20.449H8.371C8.785 20.449 9.121 20.785 9.121 21.199C9.121 21.613 8.785 21.949 8.371 21.949H7.65C4.271 21.949 2 19.578 2 16.05V7.899C2 4.371 4.271 2 7.65 2H16.3C19.686 2 21.96 4.371 21.96 7.899V16.05C21.96 19.578 19.686 21.949 16.3 21.949ZM5.2815 17.1824C5.0965 17.1824 4.9105 17.1144 4.7655 16.9774C4.4655 16.6924 4.4525 16.2174 4.7375 15.9164L6.2665 14.3024C6.6745 13.8704 7.2265 13.6314 7.8215 13.6294C8.3955 13.6674 8.9705 13.8624 9.3825 14.2904L10.2635 15.1884C10.4005 15.3284 10.5805 15.4064 10.7815 15.3924C10.9775 15.3834 11.1545 15.2954 11.2795 15.1434L13.5085 12.4314C13.9485 11.8964 14.5985 11.5724 15.2915 11.5434C15.9825 11.5224 16.6585 11.7854 17.1405 12.2824L19.2175 14.4234C19.5055 14.7214 19.4985 15.1964 19.2005 15.4844C18.9035 15.7734 18.4285 15.7644 18.1405 15.4684L16.0635 13.3274C15.8755 13.1334 15.6295 13.0244 15.3525 13.0424C15.0825 13.0534 14.8395 13.1754 14.6675 13.3844L12.4385 16.0964C12.0455 16.5744 11.4655 16.8644 10.8475 16.8914C10.2285 16.9044 9.6265 16.6804 9.1925 16.2394L8.3065 15.3354C8.1765 15.2014 8.0115 15.0834 7.8275 15.1294C7.6465 15.1304 7.4795 15.2034 7.3555 15.3334L5.8255 16.9484C5.6785 17.1044 5.4805 17.1824 5.2815 17.1824ZM8.5603 11.6376C7.1793 11.6376 6.0563 10.5146 6.0563 9.1336C6.0563 7.7526 7.1793 6.6296 8.5603 6.6296C9.9403 6.6296 11.0633 7.7526 11.0633 9.1336C11.0633 10.5146 9.9403 11.6376 8.5603 11.6376ZM8.5603 8.1296C8.0063 8.1296 7.5563 8.5796 7.5563 9.1336C7.5563 9.6876 8.0063 10.1376 8.5603 10.1376C9.1133 10.1376 9.5633 9.6876 9.5633 9.1336C9.5633 8.5796 9.1133 8.1296 8.5603 8.1296Z" fill="#1ab2ff"></path></svg>
                        <input type="file" name="image" id="add-img-input" accept="image/jpeg,image/png,image/webp,image/gif" onclick="checkLevel(event)" onchange="handleImageUpload(this, 'reply')">
                    </div>
                </div>
                <span id="reply-counter" class="twx-counter" dir="ltr">0 / 1000</span>
            </div>
        </form>
    </div>
</div>

<div id="twx-edit-modal" class="twx-modal-overlay" onclick="closeModal('twx-edit-modal')">
    <div class="twx-modal-box lg" onclick="event.stopPropagation()">
        <form method="POST" action="actions.php?action=edit_tweet" enctype="multipart/form-data" style="margin:0; display:flex; flex-direction:column; height:100%;" onsubmit="return loadingBtn(this, event)">
            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
            <input type="hidden" name="tweet_id" id="edit-id">
            
            <div class="twx-header">
                <button type="button" class="twx-close" onclick="closeModal('twx-edit-modal')">×</button>
                <div class="twx-title">ویرایش پست</div>
                <button type="submit" class="twx-btn-save twx-submit-btn"><span>ذخیره تغییرات</span><div class="twx-spinner"></div></button>
            </div>
            
            <div class="twx-body">
                <?=$user_header_html?>
                <textarea name="description" id="edit-textarea" class="twx-textarea twx-live-input" data-target="edit" placeholder="متن پست..." maxlength="1000" required></textarea>
                
                <div id="edit-old-img-wrap" class="twx-img-preview-wrap">
                    <img id="edit-old-img-src" src="">
                    <label style="display:flex; align-items:center; gap:8px; margin-top:12px; color:#f91880; font-size:14px; cursor:pointer; padding: 4px; justify-content:center; background: rgba(249, 24, 128, 0.1); border-radius: 8px; margin-bottom: 8px;">
                        <input type="checkbox" name="remove_image" id="edit-remove-check"> حذف تصویر قبلی
                    </label>
                </div>

                <div id="edit-img-wrap" class="twx-img-preview-wrap">
                    <img id="edit-img-src" src="">
                    <button type="button" class="twx-img-remove" onclick="removeSelectedImage('edit')">×</button>
                    <div class="twx-img-success">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
                        تصویر جدید با موفقیت پیوست شد
                    </div>
                </div>
            </div>
            
            <div class="twx-footer">
                <div class="twx-toolbar">
                    <div class="twx-tool-btn" title="تغییر/پیوست تصویر">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="25" height="25"><path d="M16.3 21.949H12.98C12.566 21.949 12.23 21.613 12.23 21.199C12.23 20.785 12.566 20.449 12.98 20.449H16.3C18.827 20.449 20.46 18.722 20.46 16.05V7.899C20.46 5.226 18.827 3.5 16.3 3.5H7.65C5.129 3.5 3.5 5.226 3.5 7.899V16.05C3.5 18.722 5.129 20.449 7.65 20.449H8.371C8.785 20.449 9.121 20.785 9.121 21.199C9.121 21.613 8.785 21.949 8.371 21.949H7.65C4.271 21.949 2 19.578 2 16.05V7.899C2 4.371 4.271 2 7.65 2H16.3C19.686 2 21.96 4.371 21.96 7.899V16.05C21.96 19.578 19.686 21.949 16.3 21.949ZM5.2815 17.1824C5.0965 17.1824 4.9105 17.1144 4.7655 16.9774C4.4655 16.6924 4.4525 16.2174 4.7375 15.9164L6.2665 14.3024C6.6745 13.8704 7.2265 13.6314 7.8215 13.6294C8.3955 13.6674 8.9705 13.8624 9.3825 14.2904L10.2635 15.1884C10.4005 15.3284 10.5805 15.4064 10.7815 15.3924C10.9775 15.3834 11.1545 15.2954 11.2795 15.1434L13.5085 12.4314C13.9485 11.8964 14.5985 11.5724 15.2915 11.5434C15.9825 11.5224 16.6585 11.7854 17.1405 12.2824L19.2175 14.4234C19.5055 14.7214 19.4985 15.1964 19.2005 15.4844C18.9035 15.7734 18.4285 15.7644 18.1405 15.4684L16.0635 13.3274C15.8755 13.1334 15.6295 13.0244 15.3525 13.0424C15.0825 13.0534 14.8395 13.1754 14.6675 13.3844L12.4385 16.0964C12.0455 16.5744 11.4655 16.8644 10.8475 16.8914C10.2285 16.9044 9.6265 16.6804 9.1925 16.2394L8.3065 15.3354C8.1765 15.2014 8.0115 15.0834 7.8275 15.1294C7.6465 15.1304 7.4795 15.2034 7.3555 15.3334L5.8255 16.9484C5.6785 17.1044 5.4805 17.1824 5.2815 17.1824ZM8.5603 11.6376C7.1793 11.6376 6.0563 10.5146 6.0563 9.1336C6.0563 7.7526 7.1793 6.6296 8.5603 6.6296C9.9403 6.6296 11.0633 7.7526 11.0633 9.1336C11.0633 10.5146 9.9403 11.6376 8.5603 11.6376ZM8.5603 8.1296C8.0063 8.1296 7.5563 8.5796 7.5563 9.1336C7.5563 9.6876 8.0063 10.1376 8.5603 10.1376C9.1133 10.1376 9.5633 9.6876 9.5633 9.1336C9.5633 8.5796 9.1133 8.1296 8.5603 8.1296Z" fill="#1ab2ff"></path></svg>
                        <input type="file" name="image" id="edit-img-input" accept="image/jpeg,image/png,image/webp,image/gif" onclick="checkLevel(event)" onchange="handleImageUpload(this, 'edit')">
                    </div>
                </div>
                <span id="edit-counter" class="twx-counter" dir="ltr">0 / 1000</span>
            </div>
        </form>
    </div>
</div>

<div id="twx-rt-modal" class="twx-modal-overlay" onclick="closeModal('twx-rt-modal')">
    <div class="twx-modal-box lg" onclick="event.stopPropagation()">
        <form method="POST" action="actions.php?action=retweet" style="margin:0; display:flex; flex-direction:column; height:100%;" onsubmit="return loadingBtn(this, event)">
            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
            <input type="hidden" name="tweet_id" id="rt-id">
            <input type="hidden" name="is_retweet" value="1">
            
            <div class="twx-header">
                <button type="button" class="twx-close" onclick="closeModal('twx-rt-modal')">×</button>
                <div class="twx-title">بازنشر پست</div>
                <button type="submit" class="twx-btn-save twx-submit-btn"><span>بازنشر</span><div class="twx-spinner"></div></button>
            </div>
            
            <div class="twx-body">
                <?=$user_header_html?>
                <textarea name="description" class="twx-textarea twx-live-input" data-target="rt" placeholder="نظرتان را بنویسید (اختیاری)..." maxlength="1000"></textarea>
            </div>
            
            <div class="twx-footer">
                <div></div>
                <span id="rt-counter" class="twx-counter" dir="ltr">0 / 1000</span>
            </div>
        </form>
    </div>
</div>

<div id="twx-del-modal" class="twx-modal-overlay" onclick="closeModal('twx-del-modal')">
    <div class="twx-modal-box sm" onclick="event.stopPropagation()">
        <div class="twx-del-title">حذف این پست؟</div>
        <div class="twx-del-desc">این عمل غیرقابل بازگشت است و پست برای همیشه از حساب شما حذف خواهد شد.</div>
        <form method="POST" action="actions.php?action=delete_tweet" style="margin:0;" onsubmit="return loadingBtn(this, event)">
            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
            <input type="hidden" name="tweet_id" id="del-id">
            <button type="submit" class="twx-btn-danger twx-submit-btn"><span>حذف پست</span><div class="twx-spinner" style="margin:0 auto"></div></button>
        </form>
        <button type="button" class="twx-btn-cancel" onclick="closeModal('twx-del-modal')">انصراف</button>
    </div>
</div>

<div id="twx-limit-modal" class="twx-modal-overlay" onclick="closeModal('twx-limit-modal')">
    <div class="twx-modal-box sm" onclick="event.stopPropagation()">
        <h2 class="twx-del-title">محدودیت ارسال</h2>
        <p class="twx-del-desc"><?=$current_user_name?> عزیز، شما به سقف مجاز روزانه ارسال پست رسیده‌اید. لطفاً فردا دوباره امتحان کنید.</p>
        <button class="twx-btn-cancel" onclick="closeModal('twx-limit-modal')" style="background:var(--x-blue, #1d9bf0); color:#fff; border:none;">متوجه شدم</button>
    </div>
</div>

<?php endif; ?>

<script>
const IS_LOGGED = <?= $is_user_logged ? 'true' : 'false' ?>;
const USER_LEVEL = parseInt("<?= $current_user_level ?>") || 1;
const USER_ROLE = "<?= $current_user_role ?>";

const TWEET_COUNT_24H = parseInt("<?= $tweet_count_24h ?>") || 0;
const COMMENT_COUNT_24H = parseInt("<?= $comment_count_24h ?>") || 0;
const RT_COUNT_24H = parseInt("<?= $rt_count_24h ?>") || 0;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.twx-live-input').forEach(textarea => {
        const targetId = textarea.getAttribute('data-target');
        const counter = document.getElementById(targetId + '-counter');
        const submitBtn = textarea.closest('form').querySelector('.twx-submit-btn');
        const maxLen = 1000; 

        textarea.addEventListener('input', function() {
            if (this.value.length > maxLen) {
                this.value = this.value.substring(0, maxLen);
            }

            const len = this.value.length;
            if(counter) counter.innerText = len + ' / ' + maxLen;
            
            if (targetId === 'rt') {
                submitBtn.disabled = len > maxLen;
                if(counter) counter.classList.toggle('limit', len > maxLen);
                return;
            }

            const fileInput = textarea.closest('form').querySelector('input[type="file"]');
            const hasFile = fileInput && fileInput.files.length > 0;

            if (len > maxLen) {
                if(counter) counter.classList.add('limit');
                if(submitBtn) submitBtn.disabled = true;
            } else if (len === 0 && !hasFile && textarea.hasAttribute('required')) {
                if(submitBtn) submitBtn.disabled = true;
                if(counter) counter.classList.remove('limit');
            } else if (len === 0 && hasFile) {
                if(submitBtn) submitBtn.disabled = false;
                if(counter) counter.classList.remove('limit');
            } else if (len === 0 && !textarea.hasAttribute('required') && !hasFile && targetId === 'add') {
                 if(submitBtn) submitBtn.disabled = true;
                 if(counter) counter.classList.remove('limit');
            } else {
                if(counter) counter.classList.remove('limit');
                if(submitBtn) submitBtn.disabled = false;
            }
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.twx-modal-overlay.active').forEach(m => {
                closeModal(m.id);
            });
        }
    });
});

function openModal(id) {
    const modal = document.getElementById(id);
    if(modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if(modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            const form = modal.querySelector('form');
            if(form) {
                form.reset();
                form.querySelectorAll('textarea').forEach(t => { t.value = ''; t.dispatchEvent(new Event('input')); });
                form.querySelectorAll('.twx-img-preview-wrap').forEach(p => p.style.display = 'none');
            }
        }, 250);
    }
}

function requireLogin() {
    if(!IS_LOGGED) { openModal('twx-login-modal'); return false; }
    return true;
}

function checkLevel(e) {
    if(USER_LEVEL < 15 && USER_ROLE !== 'admin') {
        e.preventDefault();
        openModal('twx-level-modal');
    }
}

function loadingBtn(form, event) {
    const textarea = form.querySelector('textarea[name="description"]');
    if (textarea && textarea.value.length > 1000) {
        if (event) event.preventDefault();
        alert('متن شما بیشتر از 1000 کاراکتر است!');
        return false;
    }

    const btn = form.querySelector('.twx-submit-btn');
    if(btn) { btn.classList.add('loading'); btn.disabled = true; }
    return true;
}

function handleImageUpload(input, prefix) {
    if (input.files && input.files[0]) {
        if(input.files[0].size > 500 * 1024) {
            alert('حجم عکس نمی‌تواند بیشتر از 500 کیلوبایت باشد. لطفاً عکس کم‌حجم‌تری انتخاب کنید.');
            input.value = "";
            return;
        }
        
        if(prefix === 'edit') {
            const oldImgWrap = document.getElementById('edit-old-img-wrap');
            const removeCheck = document.getElementById('edit-remove-check');
            if(oldImgWrap) oldImgWrap.style.display = 'none';
            if(removeCheck) removeCheck.checked = true;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(prefix + '-img-src');
            const wrap = document.getElementById(prefix + '-img-wrap');
            if(img) img.src = e.target.result;
            if(wrap) wrap.style.display = 'block';
            
            const form = input.closest('form');
            if(form) {
                const textarea = form.querySelector('textarea');
                if(textarea) textarea.dispatchEvent(new Event('input'));
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function removeSelectedImage(prefix) {
    const wrap = document.getElementById(prefix + '-img-wrap');
    const input = document.getElementById(prefix + '-img-input');
    if(wrap) wrap.style.display = 'none';
    if(input) input.value = '';
    
    if (prefix === 'edit') {
        const removeCheck = document.getElementById('edit-remove-check');
        const oldImgSrc = document.getElementById('edit-old-img-src');
        const oldImgWrap = document.getElementById('edit-old-img-wrap');
        
        if (oldImgSrc && oldImgSrc.getAttribute('src') && oldImgSrc.getAttribute('src').trim() !== '') {
            if(oldImgWrap) oldImgWrap.style.display = 'block';
            if(removeCheck) removeCheck.checked = false;
        }
    }

    const textarea = document.getElementById(prefix + '-textarea') || document.querySelector(`#twx-${prefix}-modal textarea`);
    if(textarea) textarea.dispatchEvent(new Event('input'));
}

function twxOpenAddModal(event) {
    if(event) event.stopPropagation();
    if(!requireLogin()) return;
    if(TWEET_COUNT_24H >= 5) {
        openModal('twx-limit-modal');
        return;
    }
    openModal('twx-add-modal');
}

function twxOpenReplyModal(parentId, event) {
    if(event) event.stopPropagation();
    if(!requireLogin()) return;
    if(COMMENT_COUNT_24H >= 23) {
        openModal('twx-limit-modal');
        return;
    }
    document.getElementById('reply-parent-id').value = parentId;
    openModal('twx-reply-modal');
}

function twxOpenRtModal(tweetId, event) {
    if(event) event.stopPropagation();
    if(!requireLogin()) return;
    if(RT_COUNT_24H >= 2) {
        openModal('twx-limit-modal');
        return;
    }
    document.getElementById('rt-id').value = tweetId;
    openModal('twx-rt-modal');
}

function twxOpenEditModal(tweetId, descId, imgSrc, event) {
    if(event) event.stopPropagation();
    if(!requireLogin()) return;
    
    const textarea = document.getElementById('edit-textarea');
    document.getElementById('edit-id').value = tweetId;
    
    const oldText = document.getElementById(descId);
    if(oldText) textarea.value = oldText.value;
    
    const oldImgWrap = document.getElementById('edit-old-img-wrap');
    const oldImgSrc = document.getElementById('edit-old-img-src');
    const removeCheck = document.getElementById('edit-remove-check');
    if(removeCheck) removeCheck.checked = false;
    
    if (imgSrc && imgSrc.trim() !== '') {
        oldImgSrc.src = imgSrc;
        oldImgWrap.style.display = 'block';
    } else {
        oldImgSrc.src = '';
        oldImgWrap.style.display = 'none';
    }
    
    openModal('twx-edit-modal');
    setTimeout(() => {
        textarea.dispatchEvent(new Event('input'));
    }, 100);
}

function twxOpenDelModal(tweetId, event) {
    if(event) event.stopPropagation();
    if(!requireLogin()) return;
    document.getElementById('del-id').value = tweetId;
    openModal('twx-del-modal');
}
</script>
