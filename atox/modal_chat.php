<style>
.mod { display:none; position:fixed; inset:0; background:var(--x-modal); z-index:99999; align-items:center; justify-content:center; padding:15px; backdrop-filter:blur(5px); animation:fadeIn 0.2s; }
.m-c { background:var(--x-bg); border-radius:24px; width:100%; max-width:400px; padding:24px; box-shadow:0 15px 35px rgba(0,0,0,0.2); animation:slideUp 0.3s; text-align:center; position:relative; max-height: 90vh; overflow-y: auto; -webkit-overflow-scrolling: touch;}
.close-modal { position:absolute; top:15px; right:15px; width:30px; height:30px; border-radius:50%; background:var(--x-hover); display:flex; align-items:center; justify-content:center; font-weight:bold; cursor:pointer; color:var(--x-gray);}
.profile-modal-av { width:100px; height:100px; border-radius:50%; border:3px solid var(--x-blue); margin:0 auto 15px; padding:3px; }
.profile-modal-av img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
.m-btn { display:block; width:100%; padding:14px; margin-top:10px; border-radius:16px; font-size:14px; font-weight:bold; cursor:pointer; transition:0.2s; }
.m-btn.primary { background:var(--x-blue); color:#fff; border: none; }
.m-btn.danger { background:rgba(244,33,46,0.1); color:#f4212e; border: none; }
.m-btn.outline { border: 1px solid var(--x-border); color: var(--x-black); background: transparent; }

.members-list { margin-top: 20px; border-top: 1px solid var(--x-border); padding-top: 15px; text-align: right; }
.member-item { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding: 8px; border-radius: 12px; cursor: pointer; transition: 0.2s; background: var(--x-hover);}
.member-av { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.member-name { font-size: 14px; font-weight: bold; color: var(--x-black); display:flex; align-items:center; gap:5px;}

.copy-link-container { display: flex; flex-direction: column; align-items: flex-start; margin-top: 15px; background: var(--x-hover); border-radius: 16px; padding: 12px; }
.copy-link-title { font-size: 12px; color: var(--x-gray); margin-bottom: 5px; font-weight: bold;}
.copy-link-row { display: flex; width: 100%; align-items: center; gap: 10px; }
.copy-link-input { flex: 1; background: transparent; border: none; outline: none; color: var(--x-black); font-size: 13px; text-align: left; direction: ltr; user-select: all; }
.copy-btn-icon { width: 36px; height: 36px; border-radius: 12px; background: var(--x-blue); color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: 0.2s; }
.copy-btn-icon:active { transform: scale(0.9); }
</style>

<?php if(!$is_joining_via_link): ?>
<div id="mProfile" class="mod">
    <div class="m-c">
        <div class="close-modal" onclick="document.getElementById('mProfile').style.display='none'">X</div>
        <div class="profile-modal-av"><img src="<?=$chat_avatar?>"></div>
        <h2 style="font-size:20px; font-weight:900; margin-bottom:5px; color:var(--x-black);"><?=$chat_title?> <?php if($is_verified) echo $blue_tick; ?></h2>
        
        <?php if($chat_info['is_group']): ?>
            <p style="font-size:13px; color:var(--x-gray); margin-bottom:15px;"><?=htmlspecialchars($chat_info['group_description'] ?? 'توضیحاتی ثبت نشده است.')?></p>
            
            <div class="copy-link-container">
                <div class="copy-link-title">لینک دعوت گروه</div>
                <div class="copy-link-row">
                    <input type="text" class="copy-link-input" id="inviteLinkInp" readonly value="<?=$base_url?>/chat.php?invite=<?=$chat_info['invite_link']?>">
                    <div class="copy-btn-icon" onclick="copyInviteLink()"><?=$ic_copy?></div>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:15px;">
                <?php if(!$is_group_admin): ?>
                    <button class="m-btn danger" onclick="doActionConfirm('leave_group', <?=$conv_id?>, 'از گروه خارج می‌شوید؟')">خروج</button>
                <?php endif; ?>
            </div>

            <div class="members-list">
                <h4 style="color:var(--x-gray); font-size:13px; margin-bottom:10px; text-align:right;">اعضای گروه (<?=count($group_members)?>)</h4>
                <?php foreach($group_members as $member): ?>
                    <div class="member-item" onclick="location.href='profile.php?id=<?=$member['id']?>'">
                        <img src="<?=!empty($member['avatar']) ? htmlspecialchars($member['avatar']) : 'https://ui-avatars.com/api/?name='.$member['name']?>" class="member-av">
                        <span class="member-name">
                            <?=htmlspecialchars($member['name'])?> 
                            <?php if(!empty($member['is_verified'])) echo $blue_tick; ?>
                            <?php if($member['id'] == $group_admin_id): ?><span class="admin-badge">سازنده</span><?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <button class="m-btn primary" style="margin-top:20px;" onclick="location.href='profile.php?id=<?=$other_user_id?>'">مشاهده پروفایل</button>
            <?php if(!$i_blocked_them): ?>
                <button class="m-btn danger" onclick="doActionConfirm('block_user', <?=$other_user_id?>, 'مسدود کردن کاربر؟')">بلاک کردن کاربر</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div id="mUserProfile" class="mod">
    <div class="m-c">
        <div class="close-modal" onclick="document.getElementById('mUserProfile').style.display='none'">X</div>
        <div class="profile-modal-av"><img id="uModalAv" src=""></div>
        <h2 style="font-size:20px; font-weight:900; margin-bottom:5px; color:var(--x-black);"><span id="uModalName"></span> <span id="uModalTick" style="display:none;"><?=$blue_tick?></span></h2>
        <button class="m-btn primary" id="uModalBtn" style="margin-top:20px;">مشاهده پروفایل</button>
    </div>
</div>

<div id="mConfirm" class="mod">
    <div class="m-c">
        <h3 style="margin-bottom:10px; font-size:18px; color:var(--x-black);">تایید عملیات</h3>
        <p id="mConfirmText" style="color:var(--x-gray); font-size:15px; margin-bottom:20px;"></p>
        <div style="display:flex; gap:10px;">
            <button class="m-btn primary" id="btnConfirmYes" style="margin:0; background:#f4212e;">بله</button>
            <button class="m-btn outline" style="margin:0;" onclick="document.getElementById('mConfirm').style.display='none'">لغو</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
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
                else if(typeof window.fetchNewMessages === 'function') window.fetchNewMessages();
            });
        };
    }

    window.copyInviteLink = function() {
        const linkInp = document.getElementById('inviteLinkInp');
        if(!linkInp) return;
        linkInp.select(); linkInp.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(linkInp.value);
        const btn = document.querySelector('.copy-btn-icon');
        if(!btn) return;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:#fff"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
        setTimeout(() => btn.innerHTML = oldHtml, 2000);
    };
});
</script>
