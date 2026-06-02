<?php
$is_ajax = isset($_GET['ajax_feed']) ? true : false;
$feed_type = isset($_GET['feed_type']) ? $_GET['feed_type'] : 'original';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$hide_tweets = isset($profile_user['hide_tweets']) ? $profile_user['hide_tweets'] : 0;
$user_tweets = [];
$comments = [];
$total_tweets = 0;

$is_logged = isset($_SESSION['user_id']);
$current_user_name = 'کاربر';
$user_data = []; 

if($is_logged) {
    if ($profile_id == $current_user_id) {
        $current_user_name = $profile_user['name'];
        $user_data = $profile_user; 
    } else {
        $cu_stmt = $pdo->prepare("SELECT id, name, username, avatar, role, level FROM users WHERE id = ?");
        $cu_stmt->execute([$_SESSION['user_id']]);
        $user_data = $cu_stmt->fetch(PDO::FETCH_ASSOC);
        if($user_data) $current_user_name = $user_data['name'];
    }
}
$greeting_name = htmlspecialchars($current_user_name);

function fetch_feed_data($pdo, $profile_id, $current_user_id, $limit, $offset, $condition) {
    $t_stmt = $pdo->prepare("
        SELECT t.*, 
        (SELECT COUNT(id) FROM likes WHERE tweet_id = t.id) as lc,
        (SELECT COUNT(id) FROM tweets WHERE parent_id = t.id AND is_comment = 1) as cc,
        (SELECT COUNT(id) FROM likes WHERE tweet_id = t.id AND user_id = ?) as is_liked,
        u.name, u.username, u.avatar, u.is_verified, u.level, r.job
        FROM tweets t 
        JOIN users u ON t.user_id = u.id
        LEFT JOIN resumes r ON u.id = r.user_id
        WHERE t.user_id = ? AND $condition
        ORDER BY t.id DESC LIMIT $limit OFFSET $offset
    ");
    $t_stmt->execute([$current_user_id, $profile_id]);
    $results = $t_stmt->fetchAll();
    
    $comments = [];
    if (!empty($results)) {
        $tweet_ids = array_column($results, 'id');
        $in_clause = implode(',', array_fill(0, count($tweet_ids), '?'));
        $c_stmt = $pdo->prepare("
            SELECT c.*, u.name, u.username, u.avatar, u.is_verified, u.level, r.job 
            FROM tweets c 
            JOIN users u ON c.user_id = u.id 
            LEFT JOIN resumes r ON u.id = r.user_id
            WHERE c.parent_id IN ($in_clause) AND c.is_comment = 1 ORDER BY c.created_at ASC
        ");
        $c_stmt->execute($tweet_ids);
        $all_comments = $c_stmt->fetchAll();
        foreach ($all_comments as $c) {
            $comments[$c['parent_id']][] = $c;
        }
    }
    return [$results, $comments];
}

$original_tweets = []; $original_comments = [];
$retweet_tweets = []; $retweet_comments = [];
$comment_tweets = []; $comment_comments = [];

if (!$hide_tweets || $profile_id == $current_user_id) {
    if ($is_ajax) {
        if ($feed_type === 'original') {
            list($original_tweets, $original_comments) = fetch_feed_data($pdo, $profile_id, $current_user_id, $limit, $offset, "t.is_comment = 0 AND t.is_retweet = 0");
        } elseif ($feed_type === 'retweets') {
            list($retweet_tweets, $retweet_comments) = fetch_feed_data($pdo, $profile_id, $current_user_id, $limit, $offset, "t.is_retweet = 1");
        } elseif ($feed_type === 'comments') {
            list($comment_tweets, $comment_comments) = fetch_feed_data($pdo, $profile_id, $current_user_id, $limit, $offset, "t.is_comment = 1");
        }
    } else {
        list($original_tweets, $original_comments) = fetch_feed_data($pdo, $profile_id, $current_user_id, $limit, 0, "t.is_comment = 0 AND t.is_retweet = 0");
        list($retweet_tweets, $retweet_comments) = fetch_feed_data($pdo, $profile_id, $current_user_id, $limit, 0, "t.is_retweet = 1");
        list($comment_tweets, $comment_comments) = fetch_feed_data($pdo, $profile_id, $current_user_id, $limit, 0, "t.is_comment = 1");
    }
}

$blog_limit = 5;
$blogs_stmt = $pdo->prepare("SELECT * FROM blogs WHERE writer_id = ? ORDER BY id DESC LIMIT $blog_limit OFFSET $offset");
$blogs_stmt->execute([$profile_id]);
$user_blogs = $blogs_stmt->fetchAll();

$proj_limit = 10;
$proj_stmt = $pdo->prepare("SELECT p.* FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = ? ORDER BY p.created_at DESC LIMIT $proj_limit OFFSET $offset");
$proj_stmt->execute([$profile_id]);
$user_projects = $proj_stmt->fetchAll();

if ($is_ajax) {
    while (ob_get_level()) { ob_end_clean(); }
    
    ob_start();
    if ($feed_type === 'original') {
        foreach ($original_tweets as $t) { echo '<div class="tw-wrapper" style="position:relative; z-index:1; overflow:visible;" onmouseenter="this.style.zIndex=100" onmouseleave="this.style.zIndex=1">'; render_tweet_box($t, 'profile', $is_logged, $current_user_id, $user_role ?? 'user', $original_comments); echo '</div>'; }
    } elseif ($feed_type === 'retweets') {
        foreach ($retweet_tweets as $t) { echo '<div class="tw-wrapper" style="position:relative; z-index:1; overflow:visible;" onmouseenter="this.style.zIndex=100" onmouseleave="this.style.zIndex=1">'; render_tweet_box($t, 'profile', $is_logged, $current_user_id, $user_role ?? 'user', $retweet_comments); echo '</div>'; }
    } elseif ($feed_type === 'comments') {
        foreach ($comment_tweets as $t) { echo '<div class="tw-wrapper" style="position:relative; z-index:1; overflow:visible;" onmouseenter="this.style.zIndex=100" onmouseleave="this.style.zIndex=1">'; render_tweet_box($t, 'profile', $is_logged, $current_user_id, $user_role ?? 'user', $comment_comments); echo '</div>'; }
    } elseif ($feed_type === 'articles') {
        foreach($user_blogs as $b) { ?>
            <div class="article-glass-card" onclick="location.href='view_blog.php?id=<?=$b['id']?>'">
                <div class="agc-content">
                    <div class="agc-title"><?=htmlspecialchars($b['title'])?></div>
                    <div class="agc-desc"><?=strip_tags($b['description'])?></div>
                    <div class="agc-meta"><?=$ic_cal?> <?=getJalaliDateShort($b['created_at'])?></div>
                </div>
                <?php if(!empty($b['image'])): ?><img src="<?=htmlspecialchars($b['image'])?>" class="agc-img"><?php else: ?><div class="agc-img"></div><?php endif; ?>
            </div>
        <?php }
    } elseif ($feed_type === 'projects') {
        foreach($user_projects as $p) { ?>
            <div class="project-glass-card" onclick="location.href='project.php?id=<?=$p['id']?>'">
                <div class="pjc-header">
                    <div class="pjc-title"><?=htmlspecialchars($p['name'])?></div>
                    <?php if(!empty($p['image'])): ?><img src="uploads/<?=htmlspecialchars($p['image'])?>" style="width:48px; height:48px; border-radius:12px; object-fit:cover; border:1px solid var(--x-border);"><?php endif; ?>
                </div>
                <div class="pjc-desc"><?=strip_tags($p['description'])?></div>
            </div>
        <?php }
    }
    echo ob_get_clean();
    exit;
}
?>

<style>

:root { --resume-bg: #f7f9fa; --resume-card: #ffffff; --resume-border: #eff3f4; --resume-text: #0f1419; }
.dark { --resume-bg: #000000; --resume-card: #15181c; --resume-border: #2f3336; --resume-text: #e7e9ea; }
.t-bar { display: flex; border-bottom: 1px solid var(--x-border); overflow-x: auto; scrollbar-width: none; margin-top: 20px; }
.t-item { flex: 1; text-align: center; padding: 16px 0; font-size: 15px; font-weight: bold; color: var(--x-gray); cursor: pointer; position: relative; transition: 0.2s; white-space: nowrap; min-width: 90px; }
.t-item:hover { background: var(--x-hover); }
.t-item.active { color: var(--x-black); }
.t-line { position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 56px; height: 4px; background: var(--x-blue); border-radius: 4px 4px 0 0; display: none; }
.t-item.active .t-line { display: block; }
.tab-pane { display: none; animation: fadeIn 0.3s; overflow: visible !important; } 
.tab-pane.active { display: block; }
@keyframes fadeIn { from{opacity:0;} to{opacity:1;} }


.sub-tabs { display: flex; gap: 8px; margin: 15px; background: var(--x-hover); padding: 5px; border-radius: 99px; }
.sub-tab-item { flex: 1; text-align: center; padding: 10px 15px; font-size: 14px; font-weight: bold; color: var(--x-gray); cursor: pointer; border-radius: 99px; transition: all 0.3s ease; }
.sub-tab-item:hover { color: var(--x-black); }
.sub-tab-item.active { background: var(--x-bg); color: var(--x-black); box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.dark .sub-tab-item.active { background: #2f3336; color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
.sub-pane { display: none; overflow: visible !important; } 
.sub-pane.active { display: block; }


.inf-loader { text-align: center; padding: 20px; color: var(--x-gray); font-size: 14px; font-weight: bold; display: none; }
.inf-loader::after { content: "در حال بارگذاری..."; animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { opacity: 0.5; } 50% { opacity: 1; } 100% { opacity: 0.5; } }


.article-glass-card { background: var(--glass-bg); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1px solid var(--glass-border); border-radius: 20px; margin: 15px; padding: 16px; box-shadow: var(--shadow-soft); display: flex; gap: 15px; transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s; cursor: pointer; align-items: center; }
.article-glass-card:hover { border-color: rgba(29, 155, 240, 0.4); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
.dark .article-glass-card:hover { box-shadow: 0 8px 24px rgba(255,255,255,0.03); }
.agc-content { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.agc-title { font-weight: 800; font-size: 17px; color: var(--x-black); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.agc-desc { font-size: 14px; color: var(--x-gray); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; }
.agc-meta { font-size: 12px; color: var(--x-gray); display: flex; align-items: center; gap: 4px; margin-top: 4px; }
.agc-img { width: 90px; height: 90px; border-radius: 14px; object-fit: cover; flex-shrink: 0; border: 1px solid var(--x-border); background: var(--x-hover); }

.project-glass-card { background: var(--glass-bg); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1px solid var(--glass-border); border-radius: 20px; margin: 15px; padding: 20px; box-shadow: var(--shadow-soft); display: flex; flex-direction: column; gap: 10px; transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s; cursor: pointer; }
.project-glass-card:hover { border-color: rgba(29, 155, 240, 0.4); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
.dark .project-glass-card:hover { box-shadow: 0 8px 24px rgba(255,255,255,0.03); }
.pjc-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.pjc-title { font-weight: 800; font-size: 18px; color: var(--x-black); flex: 1; word-wrap: break-word; }
.pjc-desc { font-size: 14px; color: var(--x-gray); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.6; }
.pjc-meta { display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: var(--x-gray); margin-top: 5px; border-top: 1px solid var(--x-border); padding-top: 12px; flex-wrap: wrap; gap: 10px;}
.pjc-links { display: flex; gap: 10px; flex-wrap: wrap;}
.pjc-link-btn { background: var(--x-hover); color: var(--x-black); padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: bold; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; border: 1px solid var(--glass-border);}
.pjc-link-btn:hover { background: var(--x-black); color: var(--x-bg); border-color: var(--x-black);}


.resume-section { background: var(--resume-bg); min-height: 50vh; padding: 20px 15px; color: var(--resume-text); transition: 0.3s; }
.res-group { background: var(--resume-card); border: 1px solid var(--resume-border); border-radius: 16px; margin-bottom: 15px; padding: 5px 15px; box-shadow: var(--shadow-soft); transition: 0.3s; }
.res-row { padding: 12px 0; border-bottom: 1px solid var(--resume-border); display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; transition: 0.3s;}
.res-row:last-child { border-bottom: none; }
.res-col { display: flex; flex-direction: column; gap: 4px; width: 100%; }
.res-lbl { font-size: 13px; color: var(--x-gray); font-weight: 500; }
.res-val { font-size: 15px; color: var(--resume-text); line-height: 1.4; word-wrap: break-word; }
.res-val a { color: var(--x-blue); }
.res-skill-hdr { display: flex; justify-content: space-between; font-size: 13px; font-weight: bold; color: var(--resume-text); margin-bottom: 4px; }
.res-skill-bg { height: 6px; background: var(--resume-border); border-radius: 4px; overflow: hidden; }
.res-skill-fill { height: 100%; background: var(--x-blue); border-radius: 4px; }
.res-pill { background: var(--x-hover); border: 1px solid var(--resume-border); padding: 5px 12px; border-radius: 12px; font-size: 13px; color: var(--resume-text); display: inline-block; margin: 4px 2px; }

@media(max-width: 600px) {
    .article-glass-card, .project-glass-card { margin: 10px; padding: 12px; border-radius: 16px; }
    .res-group { border-radius: 12px; }
}
</style>

<div class="t-bar">
    <div class="t-item <?= (!isset($_GET['tab']) || $_GET['tab'] == 'tweets') ? 'active' : '' ?>" onclick="swTab('tab-tweets', this, 'tweets')">پست‌ها<div class="t-line"></div></div>
    <div class="t-item <?= (isset($_GET['tab']) && $_GET['tab'] == 'resume') ? 'active' : '' ?>" onclick="swTab('tab-resume', this, 'resume')">درباره من<div class="t-line"></div></div>
    <div class="t-item <?= (isset($_GET['tab']) && $_GET['tab'] == 'articles') ? 'active' : '' ?>" onclick="swTab('tab-articles', this, 'articles')">مقالات<div class="t-line"></div></div>
    <div class="t-item <?= (isset($_GET['tab']) && $_GET['tab'] == 'projects') ? 'active' : '' ?>" onclick="swTab('tab-projects', this, 'projects')">پروژه‌ها<div class="t-line"></div></div>
</div>

<div id="tab-tweets" class="tab-pane <?= (!isset($_GET['tab']) || $_GET['tab'] == 'tweets') ? 'active' : '' ?>">
    <?php if($hide_tweets && $profile_id != $current_user_id): ?>
        <div style="padding:50px 20px; text-align:center; color:var(--x-gray); font-weight:bold;">این پروفایل خصوصی است.</div>
    <?php else: ?>
        <div class="sub-tabs">
            <div class="sub-tab-item active" onclick="swSubTab('sub-original', this)">پست‌ها</div>
            <div class="sub-tab-item" onclick="swSubTab('sub-retweets', this)">بازنشرها</div>
            <div class="sub-tab-item" onclick="swSubTab('sub-comments', this)">پاسخ‌ها</div>
        </div>

        <div id="sub-original" class="sub-pane active inf-container" data-feed="original" data-page="1">
            <?php if(empty($original_tweets)): ?><div style="padding:40px 20px; text-align:center; color:var(--x-gray); font-weight:bold;">هنوز پستی منتشر نشده است.</div><?php endif; ?>
            <?php foreach ($original_tweets as $t) { echo '<div class="tw-wrapper" style="position:relative; z-index:1; overflow:visible;" onmouseenter="this.style.zIndex=100" onmouseleave="this.style.zIndex=1">'; render_tweet_box($t, 'profile', $is_logged, $current_user_id, $user_role ?? 'user', $original_comments); echo '</div>'; } ?>
        </div>

        <div id="sub-retweets" class="sub-pane inf-container" data-feed="retweets" data-page="1">
            <?php if(empty($retweet_tweets)): ?><div style="padding:40px 20px; text-align:center; color:var(--x-gray); font-weight:bold;">بازنشری وجود ندارد.</div><?php endif; ?>
            <?php foreach ($retweet_tweets as $t) { echo '<div class="tw-wrapper" style="position:relative; z-index:1; overflow:visible;" onmouseenter="this.style.zIndex=100" onmouseleave="this.style.zIndex=1">'; render_tweet_box($t, 'profile', $is_logged, $current_user_id, $user_role ?? 'user', $retweet_comments); echo '</div>'; } ?>
        </div>

        <div id="sub-comments" class="sub-pane inf-container" data-feed="comments" data-page="1">
            <?php if(empty($comment_tweets)): ?><div style="padding:40px 20px; text-align:center; color:var(--x-gray); font-weight:bold;">پاسخی ثبت نشده است.</div><?php endif; ?>
            <?php foreach ($comment_tweets as $t) { echo '<div class="tw-wrapper" style="position:relative; z-index:1; overflow:visible;" onmouseenter="this.style.zIndex=100" onmouseleave="this.style.zIndex=1">'; render_tweet_box($t, 'profile', $is_logged, $current_user_id, $user_role ?? 'user', $comment_comments); echo '</div>'; } ?>
        </div>
    <?php endif; ?>
</div>

<div id="tab-resume" class="tab-pane <?= (isset($_GET['tab']) && $_GET['tab'] == 'resume') ? 'active' : '' ?>">
    <div class="resume-section">
    <?php if(isset($profile_user['hide_resume']) && $profile_user['hide_resume'] && $profile_id != $current_user_id): ?>
         <div style="text-align:center; color:var(--x-gray); font-weight:bold; padding:40px 0;">اطلاعات مخفی است.</div>
    <?php elseif($resume): 
        $r_skills = !empty($resume['skills']) ? json_decode($resume['skills'], true) : [];
        $r_soft = !empty($resume['soft_skills']) ? json_decode($resume['soft_skills'], true) : [];
    ?>
        <div class="res-group">
            <?php if(!empty($resume['education'])): ?>
            <div class="res-row"><div class="res-col"><span class="res-lbl">تحصیلات</span><span class="res-val"><?=htmlspecialchars($resume['education'])?></span></div></div>
            <?php endif; ?>
            <?php if(!empty($resume['university'])): ?>
            <div class="res-row"><div class="res-col"><span class="res-lbl">دانشگاه</span><span class="res-val"><?=htmlspecialchars($resume['university'])?></span></div></div>
            <?php endif; ?>
            <?php if(!empty($resume['birth_year'])): ?>
            <div class="res-row"><div class="res-col"><span class="res-lbl">سال تولد</span><span class="res-val"><?=pNum(htmlspecialchars($resume['birth_year']))?></span></div></div>
            <?php endif; ?>
        </div>

        <?php if(!empty($resume['email']) || !empty($resume['linkedin'])): ?>
        <div class="res-group">
            <?php if(!empty($resume['linkedin'])): ?>
            <div class="res-row"><div class="res-col"><span class="res-lbl">لینکدین</span><span class="res-val" dir="ltr" style="text-align:right;"><a href="https://<?=htmlspecialchars($resume['linkedin'])?>" target="_blank"><?=$ic_link?> <?=htmlspecialchars($resume['linkedin'])?></a></span></div></div>
            <?php endif; ?>
            <?php if(!empty($resume['email'])): ?>
            <div class="res-row"><div class="res-col"><span class="res-lbl">ایمیل</span><span class="res-val" dir="ltr" style="text-align:right;"><?=htmlspecialchars($resume['email'])?></span></div></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if(!empty($r_skills)): ?>
        <div class="res-group" style="padding-top:12px; padding-bottom:12px;">
            <div class="res-lbl" style="margin-bottom:10px;">مهارت‌های تخصصی</div>
            <?php foreach($r_skills as $sk): if(empty($sk['name'])) continue; ?>
                <div style="margin-top:10px; width:100%;">
                    <div class="res-skill-hdr"><span><?=htmlspecialchars($sk['name'])?></span><span><?=pNum(htmlspecialchars($sk['percent']))?>%</span></div>
                    <div class="res-skill-bg"><div class="res-skill-fill" style="width:<?=htmlspecialchars($sk['percent'])?>%;"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if(!empty($r_soft)): ?>
        <div class="res-group" style="padding-top:12px; padding-bottom:12px;">
            <div class="res-lbl" style="margin-bottom:8px;">مهارت‌های نرم</div>
            <div>
                <?php foreach($r_soft as $psk): ?>
                    <span class="res-pill"><?=htmlspecialchars($psk)?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div style="text-align:center; color:var(--x-gray); font-weight:bold; padding:40px 0;">رزومه‌ای تکمیل نشده است.</div>
    <?php endif; ?>
    </div>
</div>

<div id="tab-articles" class="tab-pane <?= (isset($_GET['tab']) && $_GET['tab'] == 'articles') ? 'active' : '' ?> inf-container" data-feed="articles" data-page="1">
    <?php if(empty($user_blogs)): ?>
        <div style="padding:50px 20px; text-align:center; color:var(--x-gray); font-weight:bold;">مقاله‌ای یافت نشد.</div>
    <?php else: ?>
        <?php foreach($user_blogs as $b): ?>
            <div class="article-glass-card" onclick="location.href='view_blog.php?id=<?=$b['id']?>'">
                <div class="agc-content">
                    <div class="agc-title"><?=htmlspecialchars($b['title'])?></div>
                    <div class="agc-desc"><?=strip_tags($b['description'])?></div>
                    <div class="agc-meta"><?=$ic_cal?> <?=getJalaliDateShort($b['created_at'])?></div>
                </div>
                <?php if(!empty($b['image'])): ?>
                    <img src="<?=htmlspecialchars($b['image'])?>" class="agc-img">
                <?php else: ?>
                    <div class="agc-img"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="tab-projects" class="tab-pane <?= (isset($_GET['tab']) && $_GET['tab'] == 'projects') ? 'active' : '' ?> inf-container" data-feed="projects" data-page="1">
    <?php if(empty($user_projects)): ?>
        <div style="padding:50px 20px; text-align:center; color:var(--x-gray); font-weight:bold;">در پروژه‌ای حضور نداشته است.</div>
    <?php else: ?>
        <?php foreach($user_projects as $p): ?>
            <div class="project-glass-card" onclick="location.href='project.php?id=<?=$p['id']?>'">
                <div class="pjc-header">
                    <div class="pjc-title"><?=htmlspecialchars($p['name'])?></div>
                    <?php if(!empty($p['image'])): ?>
                        <img src="uploads/<?=htmlspecialchars($p['image'])?>" style="width:48px; height:48px; border-radius:12px; object-fit:cover; border:1px solid var(--x-border);">
                    <?php endif; ?>
                </div>
                <div class="pjc-desc"><?=strip_tags($p['description'])?></div>
                <div class="pjc-meta">
                    <div><?=$ic_cal?> ایجاد شده در <?=getJalaliDateShort($p['created_at'])?></div>
                    <div class="pjc-links" onclick="event.stopPropagation()">
                        <?php if(!empty($p['project_link'])): ?>
                            <a href="<?=htmlspecialchars($p['project_link'])?>" target="_blank" class="pjc-link-btn"><?=$ic_link?> مشاهده سایت</a>
                        <?php endif; ?>
                        <?php if(!empty($p['github_link'])): ?>
                            <a href="<?=htmlspecialchars($p['github_link'])?>" target="_blank" class="pjc-link-btn">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.462-1.11-1.462-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.379.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.161 22 16.416 22 12c0-5.523-4.477-10-10-10z"/></svg>
                            سورس کد</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="inf-loader" id="g-loader"></div>

<script>
function swTab(id, el, tabName) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.t-item').forEach(i => i.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    el.classList.add('active');
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

function swSubTab(id, el) {
    document.querySelectorAll('.sub-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.sub-tab-item').forEach(i => i.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    el.classList.add('active');
}

let isFetching = false;
window.addEventListener('scroll', () => {
    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 600 && !isFetching) {
        let activeContainer = document.querySelector('.tab-pane.active.inf-container');
        if (!activeContainer && document.getElementById('tab-tweets').classList.contains('active')) {
            activeContainer = document.querySelector('.sub-pane.active.inf-container');
        }
        if (activeContainer && activeContainer.dataset.end !== 'true') {
            loadMoreData(activeContainer);
        }
    }
});

async function loadMoreData(container) {
    isFetching = true;
    document.getElementById('g-loader').style.display = 'block';
    
    let type = container.dataset.feed;
    let nextPage = parseInt(container.dataset.page) + 1;
    
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax_feed', '1');
        url.searchParams.set('feed_type', type);
        url.searchParams.set('page', nextPage);
        
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const html = await res.text();
        
        if (html.trim() === '') {
            container.dataset.end = 'true';
        } else {
            container.insertAdjacentHTML('beforeend', html);
            container.dataset.page = nextPage;
        }
    } catch (err) {
        console.error("Infinite Scroll Error:", err);
    }
    
    isFetching = false;
    document.getElementById('g-loader').style.display = 'none';
}

async function ajaxLike(e, form) {
    e.preventDefault();
    <?php if(!isset($_SESSION['user_id'])): ?> oM('lM'); return false; <?php endif; ?>
    const btn = form.querySelector('.like');
    const icon = form.querySelector('.like-icon');
    const span = form.querySelector('.like-count');
    const wasLiked = btn.classList.contains('liked');
    btn.classList.toggle('liked');
    icon.innerHTML = !wasLiked ? '<?=$ic_liked?>' : '<?=$ic_like?>';
    let currentCount = parseInt(span.innerText.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))) || 0;
    span.innerText = !wasLiked ? (currentCount+1).toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) : (currentCount > 1 ? (currentCount-1).toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) : '');
    const fd = new FormData(form);
    fd.append('ajax_like', '1');
    try {
        const res = await fetch(location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.liked) { btn.classList.add('liked'); icon.innerHTML = '<?=$ic_liked?>'; } 
        else { btn.classList.remove('liked'); icon.innerHTML = '<?=$ic_like?>'; }
        span.innerText = data.count > 0 ? data.count.toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) : '';
    } catch (err) {}
}
function oM(id) { alert("لطفا ابتدا وارد حساب کاربری خود شوید."); }
</script>

<?php 
include_once 'twx_modals.php'; 
?>
