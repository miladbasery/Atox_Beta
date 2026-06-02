<?php
?>
<div class="chat-footer-wrap">
    <div class="action-preview-box" id="actionPreviewBox">
        <div class="action-preview-info">
            <div class="action-preview-title" id="actionTitle">در حال پاسخ به ...</div>
            <div class="action-preview-text" id="actionText">متن پیام...</div>
        </div>
        <div class="action-cancel-btn" onclick="cancelAction()">X</div>
    </div>

    <?php if(isset($they_blocked_me) && $they_blocked_me): ?>
        <div style="text-align:center; padding:15px; color:var(--x-black); background:var(--glass-bg); font-weight:bold;">شما توسط این کاربر مسدود شده‌اید.</div>
    <?php elseif(isset($i_blocked_them) && $i_blocked_them): ?>
        <div style="text-align:center; padding:15px; color:#fff; background:#f4212e; font-weight:bold; cursor:pointer;" onclick="doActionConfirm('unblock_user', <?=$other_user_id?>, 'رفع مسدودیت؟')">رفع مسدودیت کاربر</div>
    <?php else: ?>
        <form class="chat-footer-box" id="chatForm">
            <button type="submit" class="btn-send">
                <?=$ic_send?>
                <?=$ic_edit_btn?>
            </button>
            <textarea id="msgInput" class="inp-msg" rows="1" placeholder="پیام..." autocomplete="off" required oninput="autoResizeInput(this)"></textarea>
        </form>
    <?php endif; ?>
</div>
