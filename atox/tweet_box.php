<?php
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
}

if (file_exists('gamification.php')) {
    include_once 'gamification.php';
}

if (!function_exists('persian_num')) {
    function persian_num($str) {
        $en = array('0','1','2','3','4','5','6','7','8','9');
        $fa = array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹');
        return str_replace($en, $fa, (string)$str);
    }
}

if (!function_exists('format_jalali_date')) {
    function format_jalali_date($date_string) {
        $timestamp = strtotime($date_string);
        $g_y = date('Y', $timestamp); $g_m = date('m', $timestamp); $g_d = date('d', $timestamp);
        $time = date('H:i', $timestamp);
        $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
        $j_month_name = array("", "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند");
        $gy = $g_y-1600; $gm = $g_m-1; $gd = $g_d-1;
        $g_day_no = 365*$gy+floor(($gy+3)/4)-floor(($gy+99)/100)+floor(($gy+399)/400);
        for ($i=0; $i < $gm; ++$i) $g_day_no += $g_days_in_month[$i];
        if ($gm>1 && (($gy%4==0 && $gy%100!=0) || ($gy%400==0))) $g_day_no++;
        $g_day_no += $gd; $j_day_no = $g_day_no-79;
        $j_np = floor($j_day_no/12053); $j_day_no %= 12053;
        $jy = 979+33*$j_np+4*floor($j_day_no/1461); $j_day_no %= 1461;
        if ($j_day_no >= 366) { $jy += floor(($j_day_no-1)/365); $j_day_no = ($j_day_no-1)%365; }
        for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) $j_day_no -= $j_days_in_month[$i];
        $jm = $i+1; $jd = $j_day_no+1;
        return persian_num($jd) . " " . $j_month_name[$jm] . " " . persian_num($jy) . " / " . persian_num($time);
    }
}

if (!function_exists('time_ago_fa')) {
    function time_ago_fa($datetime) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->y > 0) return persian_num($diff->y) . ' سال پیش';
        if ($diff->m > 0) return persian_num($diff->m) . ' ماه پیش';
        if ($diff->d > 0) return persian_num($diff->d) . ' روز پیش';
        if ($diff->h > 0) return persian_num($diff->h) . ' ساعت پیش';
        if ($diff->i > 0) return persian_num($diff->i) . ' دقیقه پیش';
        return 'لحظاتی پیش';
    }
}

if (!function_exists('format_tweet_text')) {
    function format_tweet_text($text) {
        if (empty($text)) return '';
        $codeBlocks = [];
        $text = preg_replace_callback('/[\s\r\n]*\x60{3}([a-zA-Z0-9\+#\-]+)?[\r\n]+(.*?)\x60{3}[\s\r\n]*/is', function($matches) use (&$codeBlocks) {
            $id = '%%CODEBLOCK_' . count($codeBlocks) . '%%';
            $lang = !empty($matches[1]) ? strtolower(trim(htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8'))) : '';
            $display_lang = $lang ? strtoupper($lang) : 'CODE';
            $raw_code = trim($matches[2]);
            while (preg_match('/&[a-z]+;/', $raw_code)) {
                $raw_code = html_entity_decode($raw_code, ENT_QUOTES, 'UTF-8');
            }
            $code = htmlspecialchars($raw_code, ENT_QUOTES, 'UTF-8');
            $lang_class = $lang ? 'language-'.$lang : '';
            $html = '<div class="tw-code-box" dir="ltr" onclick="event.stopPropagation();"><div class="tw-code-header"><span class="tw-code-lang">' . htmlspecialchars($display_lang, ENT_QUOTES, 'UTF-8') . '</span><button type="button" class="tw-code-copy" onclick="copyTwCode(this, event)">کپی</button></div><div class="tw-code-body"><pre><code class="'.$lang_class.'">' . $code . '</code></pre></div></div>';
            $codeBlocks[$id] = $html;
            return $id;
        }, $text);

        $text = strip_tags($text, '<b><i><a><span>');
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        $text = preg_replace('/(?<![&a-zA-Z0-9_\x{0600}-\x{06FF}])#([a-zA-Z0-9_\x{0600}-\x{06FF}]+)/u', 
            '<span style="color: var(--x-blue); cursor: text; font-weight: 500;">#$1</span>', 
            $text
        );
        
        $text = preg_replace('/(?<![a-zA-Z0-9_])@([a-zA-Z0-9_]+)/', 
            '<a href="profile.php?username=$1" class="tw-link" dir="ltr" onclick="checkMention(\'$1\', event)">@$1</a>', 
            $text
        );
        
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<b style="color:var(--x-black)">$1</b>', $text);
        $text = preg_replace('/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/s', '<i style="opacity:0.9">$1</i>', $text);
        
        $text = preg_replace_callback('/(?<!href=["\'])(?<!src=["\'])\b((?:https?:\/\/|www\.)?(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(?:\/[^\s<]*)?)/i', function($matches) {
            $url = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $url = rtrim($url, '.,;:!?'); 
            $href = (strpos(strtolower($url), 'http') !== 0) ? 'https://' . $url : $url;
            $display = preg_replace('/^https?:\/\/(www\.)?/i', '', $url);
            if (mb_strlen($display) > 30) $display = mb_substr($display, 0, 27) . '...';
            return '<a href="'.$href.'" target="_blank" rel="noopener noreferrer" class="tw-link" dir="ltr" onclick="event.stopPropagation();">'.$display.'</a>';
        }, $text);

        $text = preg_replace('/(%%CODEBLOCK_\d+%%)/', "\n$1\n", $text);
        $text = trim($text, "\n\r");
        
        $lines = explode("\n", $text);
        $auto_dir_text = '';
        foreach ($lines as $line) {
            $clean_line = str_replace("\r", "", $line);
            if (trim($clean_line) === '') {
                $auto_dir_text .= '<br>';
            } else {
                $safe_line = strip_tags($clean_line, '<a><span><b><i>');
                $auto_dir_text .= '<div dir="auto" style="text-align: start; white-space: pre-wrap; word-wrap: break-word;">' . $safe_line . '</div>';
            }
        }
        $text = $auto_dir_text;
        
        foreach ($codeBlocks as $id => $html) {
            $text = str_replace($id, $html, $text);
        }
        return $text;
    }
}

if (!function_exists('format_rt_desc')) {
    function format_rt_desc($text) {
        if (empty($text)) return '';
        $text = htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8');
        
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<b style="color:var(--x-black)">$1</b>', $text);
        $text = preg_replace('/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/s', '<i style="opacity:0.9">$1</i>', $text);
        
        $text = preg_replace_callback('/(?<!href=["\'])(?<!src=["\'])\b((?:https?:\/\/|www\.)?(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(?:\/[^\s<]*)?)/i', function($matches) {
            $url = $matches[1];
            $url = rtrim($url, '.,;:!?'); 
            $href = (strpos(strtolower($url), 'http') !== 0) ? 'https://' . $url : $url;
            $display = preg_replace('/^https?:\/\/(www\.)?/i', '', $url);
            if (mb_strlen($display) > 30) $display = mb_substr($display, 0, 27) . '...';
            return '<a href="'.$href.'" target="_blank" rel="noopener noreferrer" class="tw-link" dir="ltr" onclick="event.stopPropagation();">'.$display.'</a>';
        }, $text);
        
        return $text;
    }
}
?>

<link rel="stylesheet" href="assets/github-dark.min.css">
<script src="assets/highlight.min.js"></script>

<style>
.glass-card { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 2px solid rgba(255, 255, 255, 0.5); border-radius: 20px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03); transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; padding: 16px; display: flex; flex-direction: column; margin-bottom: 12px; overflow: visible; content-visibility: auto; contain-intrinsic-size: 200px; will-change: transform; }
.dark .glass-card { background: rgba(30, 30, 30, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); }
.glass-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
.dark .glass-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
.av{width:48px;height:48px;border-radius:50%;object-fit:cover;background:var(--x-border);display:flex;align-items:center;justify-content:center;font-weight:bold;color:var(--x-gray)}
.tw-c{width: 100%;} 
.u-n{display:flex;flex-direction:column;gap:3px;font-size:15px;line-height:1.3; word-break:break-word;}
.u-n-line1{display:flex;align-items:center;flex-wrap:wrap;gap:2px;}
.u-n-line2{display:flex;align-items:center;flex-wrap:wrap;gap:6px;}
.u-n b{color:var(--x-black);white-space:nowrap;} 
.u-n span{color:var(--x-gray);font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;}
.job-badge { font-size: 11px; padding: 2px 8px; border-radius: 6px; color: var(--x-gray); background: var(--x-hover); border: 1px solid var(--x-border); font-weight: normal; white-space: nowrap; }
.tw-t{font-size:15px;line-height:24px;margin-top:2px;white-space:pre-wrap;overflow-wrap:break-word;word-wrap:break-word; max-width:100%;}
.tw-footer { display: flex; flex-direction: column; margin-top: 10px; gap: 8px; }
.acts { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 0 8px; }
.tw-meta-row { display: flex; justify-content: space-between; align-items: center; width: 100%; padding-top: 8px; border-top: 1px solid rgba(150,150,150,0.15); }
.dark .tw-meta-row { border-color: rgba(255,255,255,0.08); }
.tw-time { font-size: 11px; color: var(--x-gray); white-space: nowrap; font-weight: 500; display: flex; align-items: center; }
.f-labels-wrap { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 600; color: var(--x-gray); }
.f-label { color: var(--x-gray); text-decoration: none; padding-left: 8px; border-left: 1px solid var(--x-border); transition: 0.2s; white-space: nowrap; }
.f-label:hover { color: var(--x-blue); }
.dark .f-label { border-color: rgba(255,255,255,0.1); }
.atox-mini-logo { display: flex; align-items: center; gap: 4px; text-decoration: none; color: var(--x-black); }
.atox-mini-logo h1 { font-size: 14px; margin: 0; font-weight: 800; font-family: inherit; }
.atox-mini-logo svg { width: 18px; height: 18px; fill: var(--x-blue); }
.act-b{display:flex;align-items:center;justify-content:center;color:var(--x-gray);font-size:14px;transition:.2s; background:transparent; border:none; cursor:pointer; padding:0; gap:4px; font-family:inherit;}
.act-i{padding:8px;border-radius:50%;transition:.2s;display:flex; align-items:center; justify-content:center;}
.ic-a{width:20px;height:20px;fill:currentColor}
.act-b.reply:hover{color:var(--x-blue)} .act-b.reply:hover .act-i{background:var(--x-hover-b);color:var(--x-blue)}
.act-b.like:hover{color:#f91880} .act-b.like:hover .act-i{background:var(--x-hover-r);color:#f91880}
.act-b.like.liked {color: #f91880;}
.act-b.view:hover{color:var(--x-blue)} .act-b.view:hover .act-i{background:var(--x-hover-b);color:var(--x-blue)}
.act-b.retweet:hover{color:#00ba7c} .act-b.retweet:hover .act-i{background:rgba(0, 186, 124, 0.1);color:#00ba7c}
.top-acts-wrap { display: flex; align-items: center; gap: 4px; }
.top-act-btn { background: transparent; border: none; padding: 6px; border-radius: 50%; color: var(--x-gray); cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
.top-act-btn:hover { background: var(--x-hover-b); color: var(--x-blue); }
.menu-wrap { position: relative; display: inline-block; }
.menu-content { display: none; position: absolute; left: 0; top: 100%; background: var(--x-bg); min-width: 130px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); border-radius: 12px; border: 1px solid var(--x-border); z-index: 100; overflow: hidden; }
.dark .menu-content { box-shadow: 0 4px 15px rgba(255,255,255,0.05); }
.menu-wrap.active .menu-content { display: block; }
.menu-item { display: flex; align-items: center; gap: 8px; padding: 12px 16px; font-size: 14px; color: var(--x-black); transition: 0.2s; cursor: pointer; width: 100%; text-align: right; border: none; background: transparent;}
.menu-item:hover { background: var(--x-hover); }
.menu-item.danger { color: #f91880; }
.menu-item.danger:hover { background: var(--x-hover-r); color: #f91880; }
.cm-box{display:none;padding-top:12px;border-top:1px solid rgba(150,150,150,0.1);margin-top:12px;width:100%;}
.cm-row{ display:flex; padding:12px; margin-bottom:8px; border-radius:16px; background: rgba(255, 255, 255, 0.5); border: 1px solid rgba(255, 255, 255, 0.6); backdrop-filter: blur(10px); max-width:100%; overflow: visible; cursor: pointer; }
.dark .cm-row { background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.05); }
.cm-av{width:36px;height:36px;border-radius:50%;margin-left:12px;flex-shrink:0;}
.cm-body{flex:1;min-width:0;} 
.cm-inp-box{display:flex;gap:12px;margin-top:10px;width:100%; align-items:center; max-width:100%; position: relative; flex-wrap:wrap;}
.cm-inp{flex:1;min-width:0;border:1px solid rgba(150,150,150,0.2);background:rgba(255,255,255,0.5);color:var(--x-black);padding:14px 20px;border-radius:99px;outline:none;font-size:15px;transition:0.2s; backdrop-filter: blur(5px);}
.dark .cm-inp { background:rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.1); }
.cm-inp:focus{border-color:var(--x-blue); background:var(--x-bg);}
.cm-s-btn{background:var(--x-blue);color:#fff;border-radius:99px;padding:0 20px;height:44px;font-weight:bold;flex-shrink:0; border:none; cursor:pointer;}
.tw-link { color: var(--x-blue); text-decoration: none; word-break: break-all; }
.tw-link:hover { text-decoration: underline; }
.lvl-badge-icon { display: inline-flex; align-items: center; justify-content: center; margin: 0 4px; user-select: none; transition: transform 0.2s ease; cursor: help; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15)); }
.lvl-badge-icon:hover { transform: scale(1.15) rotate(5deg); }
.dark .lvl-badge-icon { filter: drop-shadow(0 2px 4px rgba(255,255,255,0.1)); }
.tw-code-box { background: #0d1117; border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; margin: 8px 0; overflow: hidden; font-family: Consolas, "Courier New", monospace; direction: ltr; text-align: left; max-width: 100%; box-shadow: inset 0 0 10px rgba(0,0,0,0.5); }
.tw-code-header { background: #161b22; padding: 6px 12px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); }
.tw-code-lang { color: #8b949e; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;}
.tw-code-copy { background: #21262d; border: 1px solid rgba(240,246,252,0.1); color: #c9d1d9; padding: 4px 12px; border-radius: 6px; font-size: 11px; cursor: pointer; transition: 0.2s; font-family: inherit; }
.tw-code-copy:hover { background: #30363d; border-color: #8b949e; color: #fff; }
.tw-code-body { overflow-x: auto; padding: 0; background: #0d1117; }
.tw-code-box pre { margin: 0 !important; padding: 0 !important; background: transparent; display: block; }
.tw-code-box pre code { margin: 0 !important; padding: 14px !important; display: block; font-size: 13px; line-height: 1.6; tab-size: 4; color: #e6edf3; text-shadow: none; background: transparent !important; font-family: inherit; }
.tw-header-row { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 6px; width: 100%; }
.tw-user-info { display: flex; align-items: center; gap: 12px; flex: 1; }
.rt-box { margin-top: 14px; border: 1px solid var(--x-border); border-radius: 16px; padding: 14px; transition: all 0.2s ease-in-out; background: rgba(0,0,0,0.015); overflow: hidden; cursor: pointer; }
.dark .rt-box { background: rgba(255,255,255,0.015); border-color: rgba(255,255,255,0.1); }
.rt-box:hover { background: rgba(0,0,0,0.04); border-color: var(--x-blue); }
.dark .rt-box:hover { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.25); }
.rt-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
.rt-av { width: 26px; height: 26px; border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.rt-user-details { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; flex: 1; }
.rt-u-n { font-size: 14px; font-weight: bold; color: var(--x-black); white-space: nowrap; }
.rt-u-id { font-size: 13px; color: var(--x-gray); white-space: nowrap; direction: ltr; }
.rt-time { font-size: 12px; color: var(--x-gray); margin-right: auto; white-space: nowrap; background: rgba(150,150,150,0.1); padding: 2px 8px; border-radius: 10px; }
.rt-desc { font-size: 14.5px; color: var(--x-black); line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; word-break: break-word; white-space: pre-wrap; margin-bottom: 8px; }
.rt-img { width: 100%; max-height: 250px; object-fit: cover; border-radius: 12px; border: 1px solid var(--x-border); display: block; margin-top: 10px; }
.dark .rt-img { border-color: rgba(255,255,255,0.1); }
.twx-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 16px; opacity: 0; transition: opacity 0.2s ease; }
.twx-modal-overlay.active { display: flex; opacity: 1; }
.twx-modal-box { background: var(--x-bg); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); transform: scale(0.95); transition: transform 0.2s ease; display: flex; flex-direction: column; overflow: hidden; position: relative; width: 100%; max-width: 500px; }
.dark .twx-modal-box { box-shadow: 0 8px 32px rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
.twx-modal-box.sm { max-width: 320px; padding: 32px 24px 24px; text-align: center; }
.twx-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--x-border); }
.twx-title { font-size: 17px; font-weight: 800; color: var(--x-black); flex: 1; text-align: center; margin-right: -36px; }
.twx-close { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--x-black); cursor: pointer; transition: 0.2s; user-select: none; z-index: 2; border:none; background:transparent;}
.twx-close:hover { background: var(--x-hover); }
.twx-body { padding: 16px; }
.twx-textarea { width: 100%; min-height: 140px; border: none; background: transparent; color: var(--x-black); font-size: 16px; resize: none; outline: none; font-family: inherit; line-height: 1.6; }
.twx-textarea::placeholder { color: var(--x-gray); }
.twx-footer { display: flex; justify-content: space-between; align-items: center; padding: 0 16px 16px; }
.twx-counter { font-size: 14px; color: var(--x-gray); font-family: Consolas, monospace; font-weight: 500; transition: color 0.2s; }
.twx-counter.limit { color: #f91880; font-weight: bold; }
.twx-btn-save { background: var(--x-black); color: var(--x-bg); border: none; padding: 8px 24px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; }
.dark .twx-btn-save { background: #fff; color: #000; }
.twx-btn-save:hover { opacity: 0.8; }
.twx-btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
.twx-del-title { font-size: 20px; font-weight: 800; color: var(--x-black); margin-bottom: 12px; }
.twx-del-desc { font-size: 15px; color: var(--x-gray); margin-bottom: 24px; line-height: 1.5; }
.twx-del-actions { display: flex; flex-direction: column; gap: 12px; }
.twx-btn-danger { background: #f91880; color: #fff; border: none; padding: 14px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; width: 100%; }
.twx-btn-danger:hover { background: #e01673; }
.twx-btn-cancel { background: transparent; color: var(--x-black); border: 1px solid var(--x-border); padding: 14px; border-radius: 99px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; width: 100%; }
.twx-btn-cancel:hover { background: var(--x-hover); }
.tw-media-wrap { margin-top: 12px; border-radius: 16px; overflow: hidden; border: 1px solid var(--x-border); cursor: zoom-in; max-height: 500px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.05); }
.dark .tw-media-wrap { border-color: rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); }
.tw-media-wrap img { width: 100%; height: 100%; max-height: 500px; object-fit: cover; display: block; transition: filter 0.2s; }
.tw-media-wrap:hover img { filter: brightness(0.95); }
.dark .tw-media-wrap:hover img { filter: brightness(1.1); }
.lightbox-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 999999; display: none; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; cursor: zoom-out; backdrop-filter: blur(10px); }
.lightbox-overlay.active { display: flex; opacity: 1; }
.lightbox-img { max-width: 95vw; max-height: 95vh; border-radius: 12px; object-fit: contain; transform: scale(0.9); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.lightbox-overlay.active .lightbox-img { transform: scale(1); }
.lightbox-close { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.1); color: #fff; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; cursor: pointer; border: none; backdrop-filter: blur(5px); transition: 0.2s; }
.lightbox-close:hover { background: rgba(255,255,255,0.2); }
.cm-img-btn { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.5); color: var(--x-blue); cursor: pointer; transition: 0.2s; border: 1px solid rgba(150,150,150,0.2); backdrop-filter: blur(5px); flex-shrink: 0; position: relative; overflow: hidden; }
.dark .cm-img-btn { background: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.1); }
.cm-img-btn:hover { background: var(--x-hover-b); }
.cm-img-btn svg { width: 22px; height: 22px; fill: currentColor; pointer-events: none; }
.cm-img-btn input[type="file"] { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.cm-img-preview { display: none; width: 100%; margin-top: 8px; font-size: 13px; color: var(--x-blue); padding: 0 12px; }
@media (max-width: 480px) {
    .twx-modal-box:not(.sm) { position: absolute; bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
    .twx-modal-overlay.active .twx-modal-box:not(.sm) { transform: translateY(0); scale: 1; }
    .twx-modal-box:not(.sm) { transform: translateY(100%); scale: 1; }
    .tw-media-wrap { max-height: 350px; }
}
</style>

<?php


function render_tweet_box($t, $tab, $is_logged, $uid, $user_role, $comments) {
    global $pdo, $ic_dots, $ic_del, $ic_send, $ic_edit, $ic_reply, $ic_liked, $ic_like;
    $blue_tick = '<span class="verified-badge" title="تایید شده" style="display:inline-flex; align-items:center; margin-right:4px; vertical-align:-3px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="32"><defs></defs><g transform="translate(12, 12) rotate(0) scale(1, 1) scale(1) translate(-12, -12)" > <path xmlns="http://www.w3.org/2000/svg" d="M22.0199 11.1635C21.8868 10.8973 21.6913 10.6674 21.4499 10.4935L20.1199 9.49346C20.0507 9.44576 20.001 9.37477 19.9798 9.29346C19.95 9.21281 19.95 9.12412 19.9798 9.04346L20.5299 7.41346C20.6182 7.12194 20.6386 6.81411 20.5898 6.51346C20.5437 6.20727 20.4197 5.91806 20.2298 5.67346C20.0469 5.42886 19.8065 5.2331 19.5299 5.10346C19.2653 4.97641 18.973 4.91794 18.6799 4.93346H17.1799C17.0912 4.93238 17.0052 4.90256 16.9349 4.84846C16.8646 4.79437 16.8137 4.71893 16.7899 4.63346L16.3598 3.13346C16.2769 2.82915 16.1187 2.55059 15.8999 2.32346C15.6816 2.10166 15.4144 1.93388 15.1199 1.83346C14.822 1.74208 14.5071 1.72154 14.1999 1.77346C13.8953 1.83295 13.6101 1.96694 13.3699 2.16346L12.2298 3.06346C12.1667 3.12041 12.0849 3.1524 11.9999 3.15346C11.9231 3.16079 11.846 3.14327 11.7799 3.10346L10.6499 2.20346C10.4179 2.01389 10.1433 1.88348 9.84984 1.82346C9.56068 1.75345 9.25899 1.75345 8.96983 1.82346C8.67986 1.90401 8.41284 2.05127 8.18993 2.25346C7.96185 2.47441 7.78738 2.74465 7.67992 3.04346L7.24986 4.55346C7.22803 4.64248 7.17474 4.72062 7.09984 4.77346C7.02078 4.82763 6.92536 4.8524 6.82994 4.84346H5.4099C5.10311 4.83144 4.79789 4.89316 4.51988 5.02346C4.2378 5.14869 3.99317 5.34512 3.80992 5.59346C3.62585 5.8377 3.50248 6.12218 3.44994 6.42346C3.39909 6.71736 3.4196 7.01918 3.50987 7.30346L3.99986 8.99346C4.02462 9.07496 4.02462 9.16197 3.99986 9.24346C3.97459 9.3228 3.92574 9.39255 3.85985 9.44346L2.52989 10.4435C2.28774 10.6235 2.0895 10.8559 1.94994 11.1235C1.81856 11.3893 1.75011 11.6819 1.75011 11.9785C1.75011 12.275 1.81856 12.5676 1.94994 12.8335C2.0895 13.101 2.28774 13.3335 2.52989 13.5135L3.85985 14.5135C3.92574 14.5644 3.97459 14.6341 3.99986 14.7135C4.02462 14.795 4.02462 14.882 3.99986 14.9635L3.44994 16.5935C3.35678 16.8873 3.33275 17.1988 3.37987 17.5035C3.4305 17.8023 3.55415 18.0839 3.73985 18.3235C3.92315 18.5742 4.16765 18.7739 4.44994 18.9035C4.7148 19.0297 5.00687 19.0881 5.29991 19.0735H6.7899C6.88009 19.0696 6.96872 19.0979 7.0399 19.1535C7.11178 19.2029 7.16192 19.2781 7.17992 19.3635L7.60985 20.8735C7.69872 21.1723 7.85633 21.4463 8.06993 21.6735C8.39605 22.0131 8.83718 22.2188 9.30699 22.2502C9.7768 22.2817 10.2414 22.1366 10.6098 21.8435L11.7599 20.9335C11.8292 20.8775 11.9157 20.8469 12.0049 20.8469C12.094 20.8469 12.1805 20.8775 12.2499 20.9335L13.3799 21.8335C13.62 22.0361 13.91 22.1708 14.2198 22.2235C14.333 22.2331 14.4468 22.2331 14.5599 22.2235C14.7568 22.2245 14.9526 22.1941 15.1399 22.1335C15.4367 22.0401 15.7057 21.8742 15.9222 21.6507C16.1388 21.4272 16.296 21.1531 16.3799 20.8535L16.8199 19.3335C16.8379 19.2481 16.8879 19.1729 16.9598 19.1235C17.0372 19.0649 17.1331 19.0365 17.2298 19.0435H18.6599C18.9657 19.0556 19.2702 18.9975 19.5499 18.8735C19.8257 18.7419 20.0659 18.5461 20.2504 18.3025C20.4348 18.0589 20.558 17.7746 20.6098 17.4735C20.6616 17.1657 20.6377 16.8499 20.5399 16.5535L19.9999 14.9335C19.97 14.8528 19.97 14.7641 19.9999 14.6835C20.021 14.6022 20.0707 14.5312 20.1399 14.4835L21.4698 13.4835C21.7116 13.3058 21.9072 13.0726 22.0399 12.8035C22.1796 12.5384 22.2517 12.243 22.2499 11.9435C22.231 11.6698 22.1525 11.4036 22.0199 11.1635ZM16.5799 10.4035L12.1599 14.8235C11.9888 14.991 11.789 15.1265 11.5699 15.2235C11.3478 15.3149 11.11 15.3624 10.8699 15.3635C10.6252 15.3648 10.3831 15.3137 10.1599 15.2135C9.93572 15.1205 9.73191 14.9846 9.55992 14.8135L7.37987 12.6235C7.21604 12.4321 7.1304 12.1861 7.14012 11.9344C7.14984 11.6827 7.25426 11.444 7.43236 11.2659C7.61045 11.0878 7.84914 10.9835 8.10081 10.9737C8.35249 10.964 8.5986 11.0496 8.7899 11.2135L10.8699 13.2935L15.1699 8.98345C15.3573 8.7972 15.6107 8.69266 15.8749 8.69266C16.139 8.69266 16.3926 8.7972 16.5799 8.98345C16.6799 9.07699 16.7595 9.19005 16.8139 9.31562C16.8684 9.44119 16.8965 9.5766 16.8965 9.71346C16.8965 9.85033 16.8684 9.98574 16.8139 10.1113C16.7595 10.2369 16.6799 10.3499 16.5799 10.4435V10.4035Z" fill="#009dff"> </path></g></svg></span>';


    $lvl_data = getLvlData((int)($t['level'] ?? 1));
    $formatted_text = format_tweet_text($t['description']);
    $l_count = (int)($t['lc'] ?? 0);
    $c_count = (int)($t['cc'] ?? 0);
    $tweet_id = (int)($t['id'] ?? 1);
    $view_count = (int)($t['views'] ?? 0); 

    $formatted_l_count = $l_count > 0 ? persian_num($l_count) : '';
    $formatted_c_count = $c_count > 0 ? persian_num($c_count) : '';
    $formatted_views = $view_count > 999 ? persian_num(round($view_count/1000, 1)) . 'K' : ($view_count > 0 ? persian_num($view_count) : '۰');

    $ic_view = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M8.75 21V3h2v18h-2zM18 21V8.5h2V21h-2zM4 21l.004-10h2L6 21H4zm9.248 0v-7h2v7h-2z"></path></svg>';
    $ic_share = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M12 2.59l5.7 5.7-1.41 1.42L13 6.41V16h-2V6.41l-3.3 3.3-1.41-1.42L12 2.59zM21 15l-.02 3.51c0 1.38-1.12 2.49-2.5 2.49H5.5C4.11 21 3 19.88 3 18.5V15h2v3.5c0 .28.22.5.5.5h12.98c.28 0 .5-.22.5-.5L19 15h2z"></path></svg>';
    $ic_retweet = '<svg viewBox="0 0 24 24" class="ic-a"><path d="M4.5 3.88l4.432 4.14-1.364 1.46L5.5 7.55V16c0 1.1.896 2 2 2H13v2H7.5c-2.209 0-4-1.79-4-4V7.55L1.432 9.48.068 8.02 4.5 3.88zM16.5 6H11V4h5.5c2.209 0 4 1.79 4 4v8.45l2.068-1.93 1.364 1.46-4.432 4.14-4.432-4.14 1.364-1.46 2.068 1.93V8c0-1.1-.896-2-2-2z"></path></svg>';
    
    $share_url = "https://atoxcomputer.ir/view_tweet.php?id=" . $tweet_id;
    $is_retweet = isset($t['is_retweet']) && $t['is_retweet'] == 1;

    if ($is_retweet && !isset($t['parent_tweet']) && isset($pdo)) {
        $parent_id = (int)$t['parent_id'];
        $stmtParent = $pdo->prepare("SELECT t.*, u.name, u.username, u.avatar, u.level, u.is_verified FROM tweets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
        $stmtParent->execute([$parent_id]);
        $t['parent_tweet'] = $stmtParent->fetch(PDO::FETCH_ASSOC);
    }
    
    $csrf_token = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
    ?>

    <div class="glass-card" onclick="location.href='view_tweet.php?id=<?=$tweet_id?>'">
        <div class="tw-c">
            
            <div class="tw-header-row">
                <div class="tw-user-info no-select">
                    <?php if(!empty($t['avatar'])): ?>
                        <img src="<?=htmlspecialchars($t['avatar'], ENT_QUOTES, 'UTF-8')?>" class="av" loading="lazy" onclick="event.stopPropagation(); <?=$is_logged?"location.href='profile.php?id=".(int)$t['user_id']."'":"oM('lM')"?>" style="cursor:pointer; outline: 2px solid <?=htmlspecialchars($lvl_data['c'], ENT_QUOTES, 'UTF-8')?>; outline-offset: 2px;">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?=urlencode($t['name'])?>&background=random&color=fff&bold=true" class="av" loading="lazy" onclick="event.stopPropagation(); <?=$is_logged?"location.href='profile.php?id=".(int)$t['user_id']."'":"oM('lM')"?>" style="cursor:pointer; outline: 2px solid <?=htmlspecialchars($lvl_data['c'], ENT_QUOTES, 'UTF-8')?>; outline-offset: 2px;">  			 	
                    <?php endif; ?>

                    <div class="u-n" onclick="event.stopPropagation(); location.href='view_tweet.php?id=<?=$tweet_id?>';" style="cursor:pointer;">
                        <div class="u-n-line1">
                            <b><?=htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8')?></b><?=(isset($t['is_verified']) && $t['is_verified']) ? $blue_tick : ''?>
                            <span class="lvl-badge-icon" title="سطح <?=persian_num($t['level'] ?? 1)?> (<?=htmlspecialchars($lvl_data['n'], ENT_QUOTES, 'UTF-8')?>)" style="color: <?=htmlspecialchars($lvl_data['c'], ENT_QUOTES, 'UTF-8')?>;">
                                <?=$lvl_data['i']?>
                            </span>
                        </div>
                        <div class="u-n-line2">
                            <?php if(!empty($t['job'])): ?><span class="job-badge"><?=htmlspecialchars($t['job'], ENT_QUOTES, 'UTF-8')?></span><?php endif; ?>
                            <span>@<?=htmlspecialchars($t['username'], ENT_QUOTES, 'UTF-8')?></span>
                        </div>
                    </div>
                </div>
                
                <div class="top-acts-wrap" onclick="event.stopPropagation();">
                    <button class="top-act-btn" title="کپی لینک" onclick="copyOnlyLink('<?=htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8')?>', event)">
                        <?=$ic_share?>
                    </button>

                    <?php if($is_logged && ($uid == $t['user_id'] || $user_role == 'admin')): ?>
                    <div class="menu-wrap" id="m-t-<?=$tweet_id?>">
                        <div class="menu-btn" onclick="tglMenu('m-t-<?=$tweet_id?>', event);"><?=$ic_dots?></div>
                        <div class="menu-content" onclick="event.stopPropagation();">
                            <textarea id="r-t-<?=$tweet_id?>" style="display:none"><?=htmlspecialchars($t['description'], ENT_QUOTES, 'UTF-8')?></textarea>
							<div class="menu-item" onclick="twxOpenEditModal(<?=$tweet_id?>, 'r-t-<?=$tweet_id?>', '<?=htmlspecialchars($t['image']??'', ENT_QUOTES, 'UTF-8')?>', event)"><?=$ic_edit?> <span>ویرایش</span></div>
                            <div class="menu-item danger" onclick="twxOpenDelModal(<?=$tweet_id?>, event)"><?=$ic_del?> <span>حذف پست</span></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($t['description'])): ?>
                <div class="tw-t"><?=$formatted_text?></div>
            <?php endif; ?>
            
            <?php if (!empty($t['image'])): ?>
            <div class="tw-media-wrap" onclick="openLightbox('<?=htmlspecialchars($t['image'], ENT_QUOTES, 'UTF-8')?>', event)">
                <img src="<?=htmlspecialchars($t['image'], ENT_QUOTES, 'UTF-8')?>" loading="lazy" alt="تصویر پیوست">
            </div>
            <?php endif; ?>

            <?php if ($is_retweet && isset($t['parent_tweet']) && $t['parent_tweet']): $pt = $t['parent_tweet']; ?>
            <div class="rt-box" onclick="event.stopPropagation(); location.href='view_tweet.php?id='+(int)$pt['id']">
                <div class="rt-header">
                    <img src="<?=!empty($pt['avatar']) ? htmlspecialchars($pt['avatar'], ENT_QUOTES, 'UTF-8') : 'https://ui-avatars.com/api/?name='.urlencode($pt['name'] ?? 'User').'&background=random&color=fff&bold=true'?>" class="rt-av" style="outline: 1.5px solid <?=htmlspecialchars(getLvlData($pt['level']??1)['c'], ENT_QUOTES, 'UTF-8')?>; outline-offset:1px;">
                    <div class="rt-user-details">
                        <span class="rt-u-n"><?=htmlspecialchars($pt['name'] ?? 'کاربر', ENT_QUOTES, 'UTF-8')?><?=(isset($pt['is_verified']) && $pt['is_verified']) ? $blue_tick : ''?></span>
                        <span class="rt-u-id">@<?=htmlspecialchars($pt['username'] ?? '', ENT_QUOTES, 'UTF-8')?></span>
                    </div>
                    <span class="rt-time"><?=time_ago_fa($pt['created_at'])?></span>
                </div>
                
                <?php if(!empty($pt['description'])): ?>
                <div class="rt-desc"><?=format_rt_desc($pt['description'])?></div>
                <?php endif; ?>

                <?php if (!empty($pt['image'])): ?>
                <img src="<?=htmlspecialchars($pt['image'], ENT_QUOTES, 'UTF-8')?>" class="rt-img" loading="lazy" alt="تصویر ریتوییت" onclick="openLightbox('<?=htmlspecialchars($pt['image'], ENT_QUOTES, 'UTF-8')?>', event)">
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="tw-footer">
                <div class="acts" onclick="event.stopPropagation()">
                    <button class="act-b view" title="بازدیدها" style="cursor:default;" onclick="location.href='view_tweet.php?id=<?=$tweet_id?>'">
                        <div class="act-i"><?=$ic_view?></div>
                        <span><?=$formatted_views?></span>
                    </button>

                    <form method="POST" onsubmit="return ajaxLike(event, this);" style="margin:0">
                        <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
                        <input type="hidden" name="tweet_id" value="<?=$tweet_id?>">
                        <button class="act-b like <?=!empty($t['is_liked'])?'liked':''?>" type="submit" title="لایک">
                            <div class="act-i like-icon"><?=!empty($t['is_liked']) ? $ic_liked : $ic_like?></div> 
                            <span class="like-count"><?=$formatted_l_count?></span>
                        </button>
                    </form>

                    <button class="act-b reply" onclick="event.stopPropagation(); twxOpenReplyModal(<?=$tweet_id?>, event)" title="پاسخ">
                        <div class="act-i"><?=$ic_reply?></div> 
                        <span><?=$formatted_c_count?></span>
                    </button>
                    
                    <button class="act-b retweet" type="button" title="بازنشر / نقل قول متنی" onclick="event.stopPropagation(); twxOpenRtModal(<?=$tweet_id?>, event);">
                        <div class="act-i"><?=$ic_retweet?></div>
                    </button>
                </div>
                
                <div class="tw-meta-row">
                    <div class="tw-time" dir="rtl"><?=format_jalali_date($t['created_at'])?></div>
                    
                    <div class="f-labels-wrap">
                        <?php if (isset($t['is_comment']) && $t['is_comment'] == 1): ?>
                            <a href="view_tweet.php?id=<?=(int)($t['parent_id'] ?? 1)?>" class="f-label" onclick="event.stopPropagation();" title="مشاهده پیام اصلی">کامنت شده</a>
                        <?php elseif ($is_retweet): ?>
                            <a href="view_tweet.php?id=<?=(int)($t['parent_id'] ?? 1)?>" class="f-label" onclick="event.stopPropagation();" title="مشاهده توییت اصلی">بازنشر شده</a>
                        <?php endif; ?>

                        <a href="index.php" class="atox-mini-logo" onclick="event.stopPropagation()">
                            <h1>آتوکس</h1>
                            <img src="uploads/logo2.png" alt="آتوکس" style="width:16px;height:16px;object-fit:contain;">
                        </a>
                    </div>
                </div>
            </div>
            
            <div id="c-<?=$tweet_id?>" class="cm-box" onclick="event.stopPropagation()">
                <?php if(isset($comments[$tweet_id])): foreach($comments[$tweet_id] as $c): 
                    $cLvlData = getLvlData((int)($c['level'] ?? 1));
                    $cid = (int)$c['id'];
                ?>
                    <div class="cm-row" onclick="location.href='view_tweet.php?id=<?=$cid?>'">
                        <div class="cm-av-col no-select" onclick="event.stopPropagation(); <?=$is_logged?"location.href='profile.php?id=".(int)$c['user_id']."'":"oM('lM')"?>" style="cursor:pointer">
                            <?php if(!empty($c['avatar'])): ?>
                            <img src="<?=htmlspecialchars($c['avatar'], ENT_QUOTES, 'UTF-8')?>" class="cm-av" loading="lazy" style="outline: 2px solid <?=htmlspecialchars($cLvlData['c'], ENT_QUOTES, 'UTF-8')?>; outline-offset: 2px;">
                            <?php else: ?>
                            <div class="av cm-av" style="font-size:14px; outline: 2px solid <?=htmlspecialchars($cLvlData['c'], ENT_QUOTES, 'UTF-8')?>; outline-offset: 2px;"><?=htmlspecialchars(mb_substr($c['name'],0,1), ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
                        </div>
                        <div class="cm-body">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                                <div class="u-n no-select" style="cursor:pointer; flex:1;">
                                    <div class="u-n-line1">
                                        <b><?=htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8')?></b><?=(isset($c['is_verified']) && $c['is_verified']) ? $blue_tick : ''?>
                                        <span class="lvl-badge-icon" title="سطح <?=persian_num($c['level'] ?? 1)?> (<?=htmlspecialchars($cLvlData['n'], ENT_QUOTES, 'UTF-8')?>)" style="color: <?=htmlspecialchars($cLvlData['c'], ENT_QUOTES, 'UTF-8')?>;">
                                            <?=$cLvlData['i']?>
                                        </span>
                                    </div>
                                    <div class="u-n-line2">
                                        <?php if(!empty($c['job'])): ?><span class="job-badge"><?=htmlspecialchars($c['job'], ENT_QUOTES, 'UTF-8')?></span><?php endif; ?>
                                        <span>@<?=htmlspecialchars($c['username'], ENT_QUOTES, 'UTF-8')?></span>
                                    </div>
                                </div>
                                
                                <?php if($is_logged && ($uid == $c['user_id'] || $user_role == 'admin')): ?>
                                <div class="menu-wrap" id="m-c-<?=$cid?>">
                                    <div class="menu-btn" onclick="tglMenu('m-c-<?=$cid?>', event); event.stopPropagation();"><?=$ic_dots?></div>
                                    <div class="menu-content" onclick="event.stopPropagation();">
                                        <textarea id="r-c-<?=$cid?>" style="display:none"><?=htmlspecialchars($c['description'], ENT_QUOTES, 'UTF-8')?></textarea>
                                        <div class="menu-item" onclick="twxOpenEditModal(<?=$cid?>, 'r-c-<?=$cid?>', '<?=htmlspecialchars($c['image']??'', ENT_QUOTES, 'UTF-8')?>', event)"><?=$ic_edit?> <span>ویرایش</span></div>
                                        <div class="menu-item danger" onclick="twxOpenDelModal(<?=$cid?>, event)"><?=$ic_del?> <span>حذف</span></div>
</div>
</div>
<?php endif; ?></div>
<div style="font-size:15px;line-height:22px;margin-top:4px;overflow-wrap:break-word;word-wrap:break-word;color:var(--x-black)"><?=format_tweet_text($c['description'])?></div>
                            
<?php if (!empty($c['image'])): ?>
<div class="tw-media-wrap" onclick="openLightbox('<?=htmlspecialchars($c['image'], ENT_QUOTES, 'UTF-8')?>', event)" style="max-height:300px;">
<img src="<?=htmlspecialchars($c['image'], ENT_QUOTES, 'UTF-8')?>" loading="lazy" alt="تصویر پیوست">
</div>
<?php endif; ?>
                            
<div class="tw-footer" style="margin-top:8px;">
<div class="acts"></div>
<div class="tw-time" style="font-size:11px" dir="rtl"><?=format_jalali_date($c['created_at'])?></div>
</div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
                
            </div>
        </div>
    </div>
    <?php
}
?>

<script>
async function checkMention(username, e) {
    e.preventDefault();
    e.stopPropagation();
    try {
let res = await fetch(`actions.php?action=check_user&username=${username}`);
let data = await res.json();
if(data.exists) {
location.href = `profile.php?username=${username}`;
        } else {
showUserNotFoundModal();
        }
    } catch(err) {
        console.error(err);
    }
}

function showUserNotFoundModal() {
    const modal = document.getElementById('twx-user-not-found-modal');
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    }
}

function closeUserNotFoundModal() {
    const modal = document.getElementById('twx-user-not-found-modal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 200);
    }
}

function copyOnlyLink(url, e) {
    e.stopPropagation();
    navigator.clipboard.writeText(url).then(() => {
        const t = document.createElement('div');
        t.innerText = "لینک کپی شد!";
        t.style.cssText = "position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:var(--x-blue);color:#fff;padding:8px 20px;border-radius:50px;z-index:9999;font-size:14px;box-shadow:0 4px 15px rgba(0,0,0,0.2);transition:0.3s;";
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2000);
    });
}

function copyTwCode(btn, e) {
    e.stopPropagation();
    const codeBlock = btn.parentElement.nextElementSibling.querySelector('code');
    if (codeBlock) {
        navigator.clipboard.writeText(codeBlock.innerText).then(() => {
            const originalText = btn.innerText;
btn.innerText = 'کپی شد';
btn.style.color = '#3fb950';
btn.style.borderColor = '#3fb950';
setTimeout(() => {
btn.innerText = originalText;
btn.style.color = '';
btn.style.borderColor = '';
            }, 2000);
        });
    }
}

const applySyntaxHighlighting = () => {
document.querySelectorAll('.tw-code-box pre code:not(.hljs)').forEach((el) => {
        try {
hljs.highlightElement(el);
let detectedLang = el.result?.language || 'TEXT';
let headerLang = el.closest('.tw-code-box').querySelector('.tw-code-lang');
if (headerLang && (headerLang.innerText === 'CODE' || headerLang.innerText === '')) {
                headerLang.innerText = detectedLang.toUpperCase();
            }
        } catch (error) { console.error("Highlighting error: ", error); }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    applySyntaxHighlighting();

    const observer = new MutationObserver((mutations) => {
        let shouldHighlight = false;
        mutations.forEach(mutation => { if (mutation.addedNodes.length > 0) shouldHighlight = true; });
        if (shouldHighlight) applySyntaxHighlighting();
    });
    observer.observe(document.body, { childList: true, subtree: true });

    if (!document.getElementById('twx-modals-wrap')) {
        const csrfTokenVal = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
        const modalHTML = `
        <div id="twx-modals-wrap">
            <div id="twx-lightbox" class="lightbox-overlay" onclick="closeLightbox()">
                <button class="lightbox-close" onclick="closeLightbox()">×</button>
                <img id="twx-lightbox-img" class="lightbox-img" src="" alt="بزرگنمایی" onclick="event.stopPropagation()">
            </div>

            <div id="twx-rt-modal" class="twx-modal-overlay" onclick="closeTwxRetweetModal()">
                <div class="twx-modal-box" onclick="event.stopPropagation()">
                    <form id="twx-rt-form" method="POST" action="actions.php?action=retweet" style="margin:0;">
<input type="hidden" name="csrf_token" value="${csrfTokenVal}">
<input type="hidden" name="tweet_id" id="twx-rt-id">
<input type="hidden" name="is_retweet" value="1">
                        
<div class="twx-header">
<button type="button" class="twx-close" onclick="closeTwxRetweetModal()">×</button>
<div class="twx-title">بازنشر همراه با نقل قول</div>
<div style="width:36px;"></div>
</div>
<div class="twx-body">
<textarea name="description" id="twx-rt-desc" class="twx-textarea" placeholder="نظرتان را درباره این توییت بنویسید (در صورت خالی بودن فقط بازنشر میشود)..." maxlength="750"></textarea>
                        </div>
                        <div class="twx-footer">
                            <span id="twx-rt-counter" class="twx-counter" dir="ltr">0 / 750</span>
                            <button type="submit" id="twx-rt-save" class="twx-btn-save">بازنشر</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="twx-edit-modal" class="twx-modal-overlay" onclick="closeTwxEditModal()">
                <div class="twx-modal-box" onclick="event.stopPropagation()">
                    <form id="twx-edit-form" method="POST" action="actions.php?action=edit_tweet" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="${csrfTokenVal}">
                        <input type="hidden" name="tweet_id" id="twx-edit-id">
                        <div class="twx-header">
                            <button type="button" class="twx-close" onclick="closeTwxEditModal()">×</button>
                            <div class="twx-title">ویرایش پست</div>
                            <div style="width:36px;"></div>
                        </div>
                        <div class="twx-body">
                            <textarea name="description" id="twx-edit-desc" class="twx-textarea" placeholder="متن پست (حداکثر ۵۰۰ کاراکتر)..." maxlength="750" required></textarea>
</div>
<div class="twx-footer">
<span id="twx-edit-counter" class="twx-counter" dir="ltr">0 / 750</span>
<button type="submit" id="twx-edit-save" class="twx-btn-save">ذخیره</button>
</div>
</form>
                </div>
            </div>

            <div id="twx-del-modal" class="twx-modal-overlay" onclick="closeTwxDelModal()">
                <div class="twx-modal-box sm" onclick="event.stopPropagation()">
                    <div class="twx-del-title">حذف پست؟</div>
                    <div class="twx-del-desc">این کار غیرقابل بازگشت است و پست شما برای همیشه حذف خواهد شد.</div>
                    <div class="twx-del-actions">
                        <form id="twx-del-form" method="POST" action="actions.php?action=delete_tweet" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="${csrfTokenVal}">
                            <input type="hidden" name="tweet_id" id="twx-del-id">
                            <button type="submit" class="twx-btn-danger">حذف</button>
                        </form>
                        <button type="button" class="twx-btn-cancel" onclick="closeTwxDelModal()">انصراف</button>
                    </div>
                </div>
            </div>
            
            <div id="twx-user-not-found-modal" class="twx-modal-overlay" onclick="closeUserNotFoundModal()">
<div class="twx-modal-box sm" onclick="event.stopPropagation()">
<div class="twx-del-title" style="margin-bottom: 8px;">خطا</div>
<div class="twx-del-desc" style="margin-bottom: 20px;">کاربر مورد نظر یافت نشد.</div>
<div class="twx-del-actions">
<button type="button" class="twx-btn-cancel" onclick="closeUserNotFoundModal()">بستن</button>
                    </div>
                </div>
            </div>

        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        const editDesc = document.getElementById('twx-edit-desc');
        const editCounter = document.getElementById('twx-edit-counter');
        const saveBtn = document.getElementById('twx-edit-save');
        if(editDesc) {
            editDesc.addEventListener('input', function() {
                const len = this.value.length;
                editCounter.innerText = len + ' / 750';
                if (len > 750) { editCounter.classList.add('limit'); saveBtn.disabled = true; }
                else if (len === 0 || this.value.trim() === '') { saveBtn.disabled = true; editCounter.classList.remove('limit'); }
                else { editCounter.classList.remove('limit'); saveBtn.disabled = false; }
            });
        }

        const rtDesc = document.getElementById('twx-rt-desc');
        const rtCounter = document.getElementById('twx-rt-counter');
        const rtSaveBtn = document.getElementById('twx-rt-save');
        if(rtDesc) {
            rtDesc.addEventListener('input', function() {
                const len = this.value.length;
                rtCounter.innerText = len + ' / 750';
                if (len > 750) { rtCounter.classList.add('limit'); rtSaveBtn.disabled = true; }
                else { rtCounter.classList.remove('limit'); rtSaveBtn.disabled = false; }
            });
        }
    }
});

function openLightbox(src, event) {
    event.stopPropagation();
    const lightbox = document.getElementById('twx-lightbox');
    const img = document.getElementById('twx-lightbox-img');
    img.src = src;
    lightbox.style.display = 'flex';
    setTimeout(() => lightbox.classList.add('active'), 10);
}

function closeLightbox() {
    const lightbox = document.getElementById('twx-lightbox');
    lightbox.classList.remove('active');
    setTimeout(() => {
        lightbox.style.display = 'none';
        document.getElementById('twx-lightbox-img').src = '';
    }, 300);
}

function previewCmImg(input, id) {
    const previewBox = document.getElementById('cm-preview-' + id);
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!allowedTypes.includes(file.type)) {
            alert('فقط آپلود تصویر مجاز است.');
            input.value = "";
            previewBox.style.display = 'none';
            return;
        }

        if(file.size > 5 * 1024 * 1024) {
            alert('حجم عکس نمی‌تواند بیشتر از 5 مگابایت باشد.');
            input.value = "";
            previewBox.style.display = 'none';
            return;
        }
        
        previewBox.innerText = 'عکس پیوست شد: ' + file.name + ' (در صورت داشتن لول 15 ارسال می‌شود)';
        previewBox.style.display = 'block';
    } else {
        previewBox.style.display = 'none';
    }
}
</script>
