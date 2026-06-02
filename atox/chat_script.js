document.addEventListener('DOMContentLoaded', () => {
    const chatBody = document.getElementById('chatBody');
    const scrollBtn = document.getElementById('scrollDownBtn');
    let currentAction = 'send';
    let targetMsgId = null;
    let targetMsgText = '';
    let targetMsgSender = '';

    window.autoResizeInput = function(el) {
        el.style.height = 'auto';
        let newHeight = el.scrollHeight;
        if(newHeight > 120) newHeight = 120;
        el.style.height = newHeight + 'px';
        if(el.value.trim() === '') el.style.height = 'auto';
    };

    window.scrollToBottom = (smooth = false) => { 
        if(!chatBody) return;
        if(smooth) chatBody.scrollTo({ top: chatBody.scrollHeight, behavior: 'smooth' });
        else chatBody.scrollTop = chatBody.scrollHeight; 
    };

    scrollToBottom(false);
    setTimeout(() => scrollToBottom(false), 300);

    if(chatBody && scrollBtn) {
        chatBody.addEventListener('scroll', () => {
            const isScrolledUp = chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight > 150;
            if(isScrolledUp) scrollBtn.classList.add('show');
            else scrollBtn.classList.remove('show');
        });
        scrollBtn.addEventListener('click', () => scrollToBottom(true));
    }

    window.fetchNewMessages = function() {
        if (!chatBody) return;
        fetch(window.location.href)
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newChatBody = doc.getElementById('chatBody');
            if(newChatBody && chatBody.innerHTML !== newChatBody.innerHTML) {
                const isScrolledToBottom = chatBody.scrollHeight - chatBody.clientHeight <= chatBody.scrollTop + 50;
                chatBody.innerHTML = newChatBody.innerHTML;
                if(isScrolledToBottom) scrollToBottom();
            }
        }).catch(e => console.log(e));
    };
    setInterval(fetchNewMessages, 3000);

    // -- توابع گرافیکی سراسری و قطعی --
    window.toggleMenu = function(id, e) { 
        e.stopPropagation(); e.preventDefault(); 
        document.getElementById(id).classList.toggle('show'); 
    };

    window.onclick = () => document.querySelectorAll('.menu-dropdown').forEach(m => m.classList.remove('show'));

    window.showProfileModal = function() { 
        document.getElementById('mProfile').style.display='flex'; 
    };

    window.showUserProfileModal = function(uid, name, avatar, isVerified) {
        document.getElementById('uModalAv').src = avatar ? avatar : 'https://ui-avatars.com/api/?name=' + name;
        document.getElementById('uModalName').innerText = name;
        document.getElementById('uModalTick').style.display = isVerified ? 'inline-block' : 'none';
        document.getElementById('uModalBtn').onclick = () => location.href = 'profile.php?id=' + uid;
        document.getElementById('mUserProfile').style.display = 'flex';
    };

    // -- توابع عملیات‌ها (خروج، بلاک و ...) --
    let confirmActionData = null;
    window.doActionConfirm = function(action, id, text) {
        document.querySelectorAll('.menu-dropdown').forEach(m => m.classList.remove('show'));
        document.getElementById('mConfirmText').innerText = text;
        document.getElementById('mConfirm').style.display = 'flex';
        confirmActionData = {action, id};
    };

    const btnConfirmYes = document.getElementById('btnConfirmYes');
    if(btnConfirmYes) {
        btnConfirmYes.onclick = () => {
            if(!confirmActionData) return;
            document.getElementById('mConfirm').style.display = 'none';
            fetch('actions.php', {
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=${confirmActionData.action}&id=${confirmActionData.id}`
            }).then(() => {
                if(confirmActionData.action.includes('delete_chat') || confirmActionData.action.includes('leave')) location.href = 'chat.php';
                else fetchNewMessages();
            });
        };
    }

    // -- توابع پیام (ریپلای، ادیت، حذف) --
    const bsOverlay = document.getElementById('msgActionSheetBg');
    const bsSheet = document.getElementById('msgActionSheet');

    window.openMessageActions = function(msgId, isMe, text, senderName) {
        targetMsgId = msgId; targetMsgText = text; targetMsgSender = senderName;
        document.getElementById('bsEditBtn').style.display = isMe ? 'flex' : 'none';
        document.getElementById('bsDeleteBtn').style.display = (isMe || CHAT_CONFIG.isGroupAdmin) ? 'flex' : 'none';
        document.querySelectorAll('.msg-row').forEach(el => el.classList.remove('active'));
        document.getElementById('msg-'+msgId).classList.add('active');
        if(bsOverlay) bsOverlay.classList.add('show'); 
        if(bsSheet) bsSheet.classList.add('show');
    };

    window.closeMessageActions = function() {
        if(bsOverlay) bsOverlay.classList.remove('show'); 
        if(bsSheet) bsSheet.classList.remove('show');
        document.querySelectorAll('.msg-row').forEach(el => el.classList.remove('active'));
    };

    window.initReply = function() {
        closeMessageActions();
        currentAction = 'reply';
        const previewBox = document.getElementById('actionPreviewBox');
        if(previewBox) previewBox.style.display = 'flex';
        document.getElementById('actionTitle').innerText = 'پاسخ به ' + targetMsgSender;
        document.getElementById('actionText').innerText = targetMsgText;
        document.getElementById('sendIcon').style.display = 'block';
        document.getElementById('editIcon').style.display = 'none';
        const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
        if(msgInput) msgInput.focus();
    };

    window.initDirectReply = function(msgId, text, senderName) {
        targetMsgId = msgId; targetMsgText = text; targetMsgSender = senderName;
        initReply();
    };

    window.initEdit = function() {
        closeMessageActions();
        currentAction = 'edit';
        const previewBox = document.getElementById('actionPreviewBox');
        if(previewBox) previewBox.style.display = 'flex';
        document.getElementById('actionTitle').innerText = 'ویرایش پیام';
        document.getElementById('actionText').innerText = targetMsgText;
        const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
        if(msgInput) {
            msgInput.value = targetMsgText;
            autoResizeInput(msgInput);
            msgInput.focus();
        }
        document.getElementById('sendIcon').style.display = 'none';
        document.getElementById('editIcon').style.display = 'block';
    };

    window.cancelAction = function() {
        currentAction = 'send'; targetMsgId = null;
        const previewBox = document.getElementById('actionPreviewBox');
        if(previewBox) previewBox.style.display = 'none';
        const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
        if(msgInput) {
            msgInput.value = '';
            autoResizeInput(msgInput);
        }
        document.getElementById('sendIcon').style.display = 'block';
        document.getElementById('editIcon').style.display = 'none';
    };

    window.scrollToMsg = function(id) {
        const el = document.getElementById('msg-' + id);
        if(el) { 
            el.scrollIntoView({behavior: "smooth", block: "center"}); 
            el.classList.add('active'); 
            setTimeout(() => el.classList.remove('active'), 1500); 
        }
    };

    window.confirmDeleteMessage = function() {
        closeMessageActions();
        doActionConfirm('delete_msg', targetMsgId, 'آیا از حذف این پیام مطمئن هستید؟');
    };

    // -- حل مشکل پریدن بیرون صفحه در هنگام ارسال --
    window.sendMessage = function() {
        const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
        if(!msgInput) return;
        const text = msgInput.value.trim();
        if(!text) return;

        const formData = new FormData();
        formData.append('conversation_id', CHAT_CONFIG.convId);
        formData.append('message_text', text);
        
        if(currentAction === 'edit') {
            formData.append('action', 'edit_msg');
            formData.append('msg_id', targetMsgId);
        } else {
            formData.append('action', 'send_msg');
            if(currentAction === 'reply') formData.append('reply_to_id', targetMsgId);
        }
        
        fetch('actions.php', { method: 'POST', body: formData }).then(() => {
            cancelAction();
            fetchNewMessages();
            setTimeout(() => scrollToBottom(true), 100);
        });
    };

    // کنترل کامل روی تمامی فرم ها برای جلوگیری از رفرش صفحه
    document.addEventListener('submit', function(e) {
        const form = e.target.closest('form');
        if (form) {
            e.preventDefault();
            sendMessage();
        }
    });

    // ارسال با کلید Enter
    const msgInput = document.getElementById('msgInput') || document.querySelector('.inp-msg');
    if(msgInput) {
        msgInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }

    // -- توابع اشتراک گذاری و گروه --
    window.joinGroupViaLink = function(inviteCode) {
        const fd = new FormData();
        fd.append('join_invite_code', inviteCode);
        fetch('chat_view.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) location.href = 'chat_view.php?id=' + data.id;
            else alert('خطا در پیوستن به گروه. لینک نامعتبر است.');
        }).catch(() => alert('خطا در ارتباط با سرور.'));
    };

    window.copyInviteLink = function() {
        const linkInp = document.getElementById('inviteLinkInp');
        linkInp.select(); linkInp.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(linkInp.value);
        const btn = document.querySelector('.copy-btn-icon');
        if(!btn) return;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:#fff"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
        setTimeout(() => btn.innerHTML = oldHtml, 2000);
    };

    let shareGroupData = {};
    const sInp = document.getElementById('shareSearchInp');
    const sRes = document.getElementById('shareRes');
    let sTime;

    window.openShareGroup = function(link, name, desc) {
        document.getElementById('mProfile').style.display = 'none';
        shareGroupData = {link, name, desc};
        document.getElementById('mShareGroup').style.display = 'flex';
        if(sInp) sInp.value = '';
        if(sRes) sRes.innerHTML = '<p style="text-align:center; color:var(--x-gray); font-size:14px; padding: 20px;">برای جستجو شروع به تایپ کنید.</p>';
    };

    if(sInp) {
        sInp.addEventListener('input', (e) => {
            clearTimeout(sTime); const q = e.target.value.trim();
            if(!q) { sRes.innerHTML = '<p style="text-align:center; color:var(--x-gray); font-size:14px; padding: 20px;">برای جستجو شروع به تایپ کنید.</p>'; return; }
            sRes.innerHTML = '<p style="text-align:center; color:var(--x-gray); font-size:14px; padding: 20px;">در حال جستجو...</p>';
            sTime = setTimeout(() => {
                fetch('actions.php?action=search_users&q=' + encodeURIComponent(q)).then(r => r.json()).then(data => {
                    sRes.innerHTML = '';
                    if(data.length === 0) { sRes.innerHTML = '<p style="text-align:center; color:#f4212e; font-size:14px; padding: 20px;">کاربری یافت نشد</p>'; return; }
                    data.forEach(u => {
                        let div = document.createElement('div');
                        div.className = 'share-item';
                        let av = u.avatar ? u.avatar : `https://ui-avatars.com/api/?name=${u.name}`;
                        div.innerHTML = `
                            <div class="share-item-left">
                                <img src="${av}" style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                                <div style="display:flex; flex-direction:column; text-align:right;">
                                    <span style="font-weight:bold; color:var(--x-black); font-size:14px;">${u.name}</span>
                                    <span style="font-size:12px; color:var(--x-gray);">@${u.username}</span>
                                </div>
                            </div>
                            <button class="share-btn" onclick="shareGroupToNewChat(${u.id}, this)">ارسال</button>
                        `;
                        sRes.appendChild(div);
                    });
                }).catch(() => sRes.innerHTML = '<p style="text-align:center; color:#f4212e; font-size:14px;">خطا در جستجو</p>');
            }, 400);
        });
    }

    // تابع ارسال گروه به پی‌وی (جدید اضافه شده است)
    window.shareGroupToNewChat = function(userId, btn) {
        if(!shareGroupData.link) return;
        const text = `لینک گروه ${shareGroupData.name}:\n${CHAT_CONFIG.baseUrl}/chat.php?invite=${shareGroupData.link}`;
        
        const fd = new FormData();
        fd.append('action', 'send_msg_by_user_id'); // این اکشن برای ایجاد چت مستقیم و ارسال پیام استفاده می‌شود
        fd.append('user_id', userId);
        fd.append('message_text', text);
        
        btn.innerText = 'در حال ارسال...';
        btn.disabled = true;

        fetch('actions.php', { method: 'POST', body: fd })
        .then(res => res.text())
        .then(() => {
            btn.innerText = 'ارسال شد';
            btn.classList.add('sent');
        }).catch(() => {
            btn.innerText = 'خطا';
            btn.disabled = false;
        });
    };
});
