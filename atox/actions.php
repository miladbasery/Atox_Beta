<?php
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self';");

session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { 
    header('Location: index.php'); 
    exit; 
}

$action = htmlspecialchars($_REQUEST['action'] ?? '', ENT_QUOTES, 'UTF-8');
$user_id = (int)$_SESSION['user_id'];
$uid = $user_id;
$user_role = htmlspecialchars($_SESSION['role'] ?? 'user', ENT_QUOTES, 'UTF-8');
$is_logged = ($uid > 0);
$is_admin = ($user_role === 'admin' || (isset($_SESSION['username']) && $_SESSION['username'] === 'milad'));

$kanoon_id = isset($_REQUEST['kanoon_id']) ? (int)$_REQUEST['kanoon_id'] : 0;

function handleSecureImageUpload($file, $prefix = 'img_') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $max_size = 2 * 1024 * 1024; 
    if ($file['size'] > $max_size) return null;
    
    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    } catch (Exception $e) {
        $mime = mime_content_type($file['tmp_name']);
    }

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed_mimes, true)) return null;
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed_exts, true)) return null;

    if (!is_dir('uploads')) mkdir('uploads', 0755, true);
    $filename = $prefix . bin2hex(random_bytes(10)) . '.' . $ext;
    $filepath = 'uploads/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename; 
    }
    return null;
}

function canManageKanoon($pdo, $kanoon_id, $uid) {
    global $user_role;
    $stmt = $pdo->prepare("SELECT creator_id FROM kanoons WHERE id = ?");
    $stmt->execute([$kanoon_id]);
    $creator_id = $stmt->fetchColumn();
    return ($user_role === 'admin' || (isset($_SESSION['username']) && $_SESSION['username'] === 'milad') || $uid === (int)$creator_id);
}

if ($kanoon_id > 0) {
    $stmt = $pdo->prepare("SELECT creator_id FROM kanoons WHERE id = ?");
    $stmt->execute([$kanoon_id]);
    $kanoon_owner = $stmt->fetchColumn();
    $is_kanoon_admin = ($is_admin || ($kanoon_owner == $uid));

    if ($is_kanoon_admin) {
        if ($action === 'add_member' && isset($_POST['username'])) {
            $username = htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8');
            $role_title = htmlspecialchars(trim($_POST['role_title'] ?? 'عضو عادی'), ENT_QUOTES, 'UTF-8');
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user) {
                $chk = $pdo->prepare("SELECT id FROM kanoon_members WHERE kanoon_id = ? AND user_id = ?");
                $chk->execute([$kanoon_id, $user['id']]);
                if (!$chk->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO kanoon_members (kanoon_id, user_id, role_title) VALUES (?, ?, ?)");
                    $ins->execute([$kanoon_id, $user['id'], $role_title]);
                }
            }
            header("Location: university.php?id=$kanoon_id&tab=members");
            exit;
        }

        if ($action === 'remove_member') {
            $target_user_id = (int)($_POST['user_id'] ?? 0);
            if ($target_user_id > 0) {
                $pdo->prepare("DELETE FROM kanoon_members WHERE kanoon_id=? AND user_id=?")->execute([$kanoon_id, $target_user_id]);
            }
            header("Location: university.php?id=$kanoon_id&tab=members");
            exit;
        }

        if ($action === 'set_chat') {
            $conv_id = (int)($_POST['conversation_id'] ?? 0);
            $pdo->prepare("UPDATE kanoons SET conversation_id = ? WHERE id = ?")->execute([$conv_id, $kanoon_id]);
            header("Location: university.php?id=$kanoon_id");
            exit;
        }
    }
}

if ($is_logged && $action === 'add_kanoon') {
    $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $category = htmlspecialchars(trim($_POST['category'] ?? 'متفرقه'), ENT_QUOTES, 'UTF-8');
    $hide_magazines = isset($_POST['hide_magazines']) ? 1 : 0;
    $hide_projects = isset($_POST['hide_projects']) ? 1 : 0;
    $hide_members = isset($_POST['hide_members']) ? 1 : 0;
    $image_name = null; 
    
    if (!empty($name) && !empty($category)) {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_name = handleSecureImageUpload($_FILES['image'], 'kanoon_');
        }

        try {
            $pdo->beginTransaction();

            $invite_link = substr(bin2hex(random_bytes(10)), 0, 10);
            $conv_stmt = $pdo->prepare("INSERT INTO conversations (is_group, group_name, group_description, admin_id, invite_link, created_at, updated_at) VALUES (1, ?, ?, ?, ?, NOW(), NOW())");
            $conv_stmt->execute([$name, "گروه رسمی کانون " . $name, $uid, $invite_link]);
            $conv_id = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO participants (conversation_id, user_id) VALUES (?, ?)")->execute([$conv_id, $uid]);

            $stmt = $pdo->prepare("INSERT INTO kanoons (name, description, category, image, creator_id, conversation_id, hide_magazines, hide_projects, hide_members) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $desc, $category, $image_name, $uid, $conv_id, $hide_magazines, $hide_projects, $hide_members]);
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();

            header("Location: general.php?error=creation_failed");
            exit;
        }
    }
    header("Location: general.php?success=kanoon_added");
    exit;
}

if ($action === 'edit_kanoon') {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("SELECT creator_id FROM kanoons WHERE id = ?");
    $stmt->execute([$id]);
    $kanoon_owner = $stmt->fetchColumn();

    if ($id > 0 && ($is_admin || $kanoon_owner == $uid)) {
        $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $category = htmlspecialchars(trim($_POST['category'] ?? 'متفرقه'), ENT_QUOTES, 'UTF-8');
        $hide_magazines = isset($_POST['hide_magazines']) ? 1 : 0;
        $hide_projects = isset($_POST['hide_projects']) ? 1 : 0;
        $hide_members = isset($_POST['hide_members']) ? 1 : 0;

        if (!empty($name) && !empty($category)) {
            $stmt = $pdo->prepare("UPDATE kanoons SET name = ?, description = ?, category = ?, hide_magazines = ?, hide_projects = ?, hide_members = ? WHERE id = ?");
            $stmt->execute([$name, $desc, $category, $hide_magazines, $hide_projects, $hide_members, $id]);

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $img_name = handleSecureImageUpload($_FILES['image'], 'kanoon_');
                if ($img_name) {
                    $pdo->prepare("UPDATE kanoons SET image = ? WHERE id = ?")->execute([$img_name, $id]);
                }
            }
        }
    }
    header("Location: general.php");
    exit;
}

if ($action === 'delete_kanoon') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT creator_id, conversation_id FROM kanoons WHERE id = ?");
    $stmt->execute([$id]);
    $kanoon = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($id > 0 && $kanoon && ($is_admin || $kanoon['creator_id'] == $uid)) {
        $conv_id = $kanoon['conversation_id'];
        
        try {
            $pdo->beginTransaction();
            if($conv_id > 0) {
                $pdo->prepare("DELETE FROM participants WHERE conversation_id = ?")->execute([$conv_id]);
                $pdo->prepare("DELETE FROM conversations WHERE id = ?")->execute([$conv_id]);
            }
            $pdo->prepare("DELETE FROM kanoon_members WHERE kanoon_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM kanoons WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch(Exception $e) {
            $pdo->rollBack();
            header("Location: general.php?error=delete_failed");
            exit;
        }
    }
    header("Location: general.php");
    exit;
}

if ($is_logged && $action === 'toggle_follow_kanoon') {
    $kanoon_id = (int)($_POST['kanoon_id'] ?? 0);
    if ($kanoon_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM kanoon_members WHERE kanoon_id = ? AND user_id = ?");
        $stmt->execute([$kanoon_id, $uid]);
        if($stmt->fetch()) {
            $pdo->prepare("DELETE FROM kanoon_members WHERE kanoon_id = ? AND user_id = ?")->execute([$kanoon_id, $uid]);
        } else {
            $pdo->prepare("INSERT INTO kanoon_members (kanoon_id, user_id, role_title) VALUES (?, ?, ?)")->execute([$kanoon_id, $uid, 'عضو']);
        }
    }
    header("Location: university.php?id=$kanoon_id");
    exit;
}

if ($is_logged && $action === 'create_group' && isset($_POST['group_name'])) {
    $name = htmlspecialchars(trim($_POST['group_name']), ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars(trim($_POST['group_description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $invite_link = substr(bin2hex(random_bytes(10)), 0, 10);
    $avatar_name = null;

    if (isset($_FILES['group_avatar_file']) && $_FILES['group_avatar_file']['error'] == 0) {
        $avatar_name = handleSecureImageUpload($_FILES['group_avatar_file'], 'group_');
    }
    
    if(!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO conversations (is_group, group_name, group_description, group_avatar, invite_link, admin_id, created_at, updated_at) VALUES (1, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $desc, $avatar_name, $invite_link, $uid]);
        $conv_id = $pdo->lastInsertId();
        
        $pdo->prepare("INSERT INTO participants (conversation_id, user_id) VALUES (?, ?)")->execute([$conv_id, $uid]);
    }

    header("Location: chat.php");
    exit;
}



if ($action === 'add_project') {
	$name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
	$desc = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
	$plink = filter_var(trim($_POST['project_link'] ?? ''), FILTER_SANITIZE_URL);
	$glink = filter_var(trim($_POST['github_link'] ?? ''), FILTER_SANITIZE_URL);
	
	if ($name) {
		$pdo->prepare("INSERT INTO projects (kanoon_id, name, description, project_link, github_link) VALUES (?, ?, ?, ?, ?)")
			->execute([$kanoon_id, $name, $desc, $plink, $glink]);
	}
	header("Location: university.php?id=$kanoon_id&tab=projects");
	exit;
}

if ($action === 'add_project') {
    $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $plink = filter_var(trim($_POST['project_link'] ?? ''), FILTER_SANITIZE_URL);
    $glink = filter_var(trim($_POST['github_link'] ?? ''), FILTER_SANITIZE_URL);
    
    if ($name) {
        $pdo->prepare("INSERT INTO projects (kanoon_id, name, description, project_link, github_link) VALUES (?, ?, ?, ?, ?)")
            ->execute([$kanoon_id, $name, $desc, $plink, $glink]);
        $new_project_id = $pdo->lastInsertId();

        if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
            $file_count = count($_FILES['images']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name'     => $_FILES['images']['name'][$i],
                        'type'     => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error'    => $_FILES['images']['error'][$i],
                        'size'     => $_FILES['images']['size'][$i]
                    ];
                    $uploaded = handleSecureImageUpload($file, 'proj_');
                    if ($uploaded) {
                        $image_path = basename($uploaded);
                        $pdo->prepare("INSERT INTO project_images (project_id, image_path) VALUES (?, ?)")->execute([$new_project_id, $image_path]);
                    }
                }
            }
        }
    }
    header("Location: university.php?id=$kanoon_id&tab=projects");
    exit;
}

if ($action === 'edit_project') {
    $project_id = (int)$_POST['id'];
    $kanoon_id = (int)$_POST['kanoon_id'];
    $name = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(trim($_POST['description']), ENT_QUOTES, 'UTF-8');
    $project_link = filter_var(trim($_POST['project_link']), FILTER_SANITIZE_URL);
    $github_link = filter_var(trim($_POST['github_link']), FILTER_SANITIZE_URL);
    
    $stmt = $pdo->prepare("UPDATE projects SET name = ?, description = ?, project_link = ?, github_link = ? WHERE id = ?");
    $stmt->execute([$name, $description, $project_link, $github_link, $project_id]);

    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $file_count = count($_FILES['images']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name'     => $_FILES['images']['name'][$i],
                    'type'     => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error'    => $_FILES['images']['error'][$i],
                    'size'     => $_FILES['images']['size'][$i]
                ];
                $uploaded = handleSecureImageUpload($file, 'proj_');
                if ($uploaded) {
                    $image_path = basename($uploaded);
                    $pdo->prepare("INSERT INTO project_images (project_id, image_path) VALUES (?, ?)")->execute([$project_id, $image_path]);
                }
            }
        }
    }
    header("Location: project.php?id=" . $project_id);
    exit;
}

if ($action === 'delete_project_image') {
    $image_id = (int)$_POST['image_id'];
    $project_id = (int)$_POST['project_id'];

    $stmt = $pdo->prepare("SELECT image_path FROM project_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($img) {
        $file_path = 'uploads/' . $img['image_path'];
        if (!empty($img['image_path']) && file_exists($file_path) && !is_dir($file_path)) {
            unlink($file_path);
        }
        
        $stmt = $pdo->prepare("DELETE FROM project_images WHERE id = ?");
        $stmt->execute([$image_id]);
    }

    header("Location: project.php?id=" . $project_id);
    exit;
}

if ($action === 'delete_project') {
    $project_id = (int)$_POST['id'];
    $kanoon_id = (int)$_POST['kanoon_id'];

    $stmt = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($images as $img) {
        $image_path = 'uploads/' . $img['image_path'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);

    header("Location: university.php?id=" . $kanoon_id . "&tab=projects");
    exit;
}

if ($action === 'add_course') {
    $kanoon_id = (int)($_POST['kanoon_id'] ?? 0);
    $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $term = (int)($_POST['term'] ?? 0);

    if ($name && $term >= 1 && $term <= 10) {
        $pdo->prepare("INSERT INTO jozve_groups (kanoon_id, name, term) VALUES (?, ?, ?)")
            ->execute([$kanoon_id, $name, $term]);
    }
    header("Location: university.php?id=$kanoon_id&tab=jozves");
    exit;
}

if ($action === 'edit_course' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $name = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
    $term = (int)$_POST['term'];
    $kanoon_id = (int)$_POST['kanoon_id'];
    
    if ($name && $term >= 1 && $term <= 10) {
        $stmt = $pdo->prepare("UPDATE jozve_groups SET name = ?, term = ? WHERE id = ?");
        $stmt->execute([$name, $term, $id]);
    }
    header("Location: university.php?id=" . $kanoon_id . "&tab=jozves");
    exit;
}

if ($action === 'delete_course' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $kanoon_id = (int)$_POST['kanoon_id'];
    $stmt = $pdo->prepare("DELETE FROM jozve_groups WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: university.php?id=" . $kanoon_id . "&tab=jozves");
    exit;
}

if ($action === 'react_jozve') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $j_id = (int)($data['jozve_id'] ?? 0);
    $type = htmlspecialchars($data['type'] ?? '', ENT_QUOTES, 'UTF-8');

    if ($j_id > 0 && in_array($type, ['like', 'dislike'])) {
        $stmt = $pdo->prepare("SELECT user_id FROM jozves WHERE id = ?");
        $stmt->execute([$j_id]);
        $owner_id = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT type FROM jozve_likes WHERE jozve_id = ? AND user_id = ?");
        $stmt->execute([$j_id, $uid]);
        $existing = $stmt->fetchColumn();

        $points_diff = 0;

        if ($existing === $type) {
            $pdo->prepare("DELETE FROM jozve_likes WHERE jozve_id = ? AND user_id = ?")->execute([$j_id, $uid]);
            if ($type === 'like') $points_diff = -2;
            if ($type === 'dislike') $points_diff = 1;
        } elseif ($existing) {
            $pdo->prepare("UPDATE jozve_likes SET type = ? WHERE jozve_id = ? AND user_id = ?")->execute([$type, $j_id, $uid]);
            if ($type === 'like') $points_diff = 3;   
            if ($type === 'dislike') $points_diff = -3; 
        } else {
            $pdo->prepare("INSERT INTO jozve_likes (jozve_id, user_id, type) VALUES (?, ?, ?)")->execute([$j_id, $uid, $type]);
            if ($type === 'like') $points_diff = 2;
            if ($type === 'dislike') $points_diff = -1;
        }

        if ($owner_id > 0 && $owner_id !== $uid && $points_diff != 0) {
            $pdo->prepare("UPDATE users SET points = GREATEST(0, points + ?) WHERE id = ?")->execute([$points_diff, $owner_id]);
            $pdo->prepare("UPDATE users SET level = GREATEST(1, FLOOR(points / 5) + 1) WHERE id = ?")->execute([$owner_id]);
        }

        $stmtLikes = $pdo->prepare("SELECT COUNT(*) FROM jozve_likes WHERE jozve_id = ? AND type = 'like'");
        $stmtLikes->execute([$j_id]);
        $likes_count = $stmtLikes->fetchColumn();

        $stmtDislikes = $pdo->prepare("SELECT COUNT(*) FROM jozve_likes WHERE jozve_id = ? AND type = 'dislike'");
        $stmtDislikes->execute([$j_id]);
        $dislikes_count = $stmtDislikes->fetchColumn();

        echo json_encode(['status' => 'success', 'likes' => $likes_count, 'dislikes' => $dislikes_count, 'current' => ($existing === $type ? '' : $type)]);
        exit;
    }
    echo json_encode(['error' => 'invalid_data']);
    exit;
}

if ($action === 'edit_member_role') {
    $member_id = (int)$_POST['member_id']; 
    $role_title = htmlspecialchars(trim($_POST['role_title']) ?: 'عضو عادی', ENT_QUOTES, 'UTF-8');
    $kanoon_id = (int)$_POST['kanoon_id'];
    
    $stmt = $pdo->prepare("UPDATE kanoon_members SET role_title = ? WHERE id = ?");
    $stmt->execute([$role_title, $member_id]);
    header("Location: university.php?id=$kanoon_id&tab=members");
    exit;
}

if ($action === 'send_msg_by_user_id') {
    $target_id = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
    $text = htmlspecialchars(trim($_POST['message_text'] ?? ''), ENT_QUOTES, 'UTF-8');

    if ($target_id == 0 || empty($text)) {
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT c.id FROM conversations c 
                           JOIN participants p1 ON c.id = p1.conversation_id 
                           JOIN participants p2 ON c.id = p2.conversation_id 
                           WHERE c.is_group = 0 AND p1.user_id = ? AND p2.user_id = ?");
    $stmt->execute([$uid, $target_id]);
    $conv_id = $stmt->fetchColumn();
    
    if (!$conv_id) {
        $pdo->prepare("INSERT INTO conversations (is_group, created_at) VALUES (0, NOW())")->execute();
        $conv_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO participants (conversation_id, user_id) VALUES (?, ?), (?, ?)")
            ->execute([$conv_id, $uid, $conv_id, $target_id]);
    }
    
    if ($conv_id) {
        $pdo->prepare("INSERT INTO messages (conversation_id, user_id, message_text) VALUES (?, ?, ?)")
            ->execute([$conv_id, $uid, $text]);
        $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conv_id]);
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'خطا در ایجاد مکالمه']);
    }
    exit;
}

if ($action == 'logout') {
    if (isset($_SESSION['user_id'])) {
        $current_session_id = session_id();
        $del_sess = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $del_sess->execute([$current_session_id]);
    }
    session_unset();
    session_destroy();
    if (isset($_COOKIE['user_auth'])) {
        setcookie('user_auth', '', time() - 3600, '/', '', true, true);
    }
    header('Location: auth.php');
    exit;
}

if ($action == 'tweet' && isset($_POST['description'])) {
    $desc = htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8');
    $is_comment = isset($_POST['is_comment']) ? 1 : 0;
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $image_path = null;

    $limit = $is_comment ? 23 : 5;
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tweets WHERE user_id = ? AND is_comment = ? AND is_retweet = 0 AND created_at >= NOW() - INTERVAL 24 HOUR");
    $stmtCount->execute([$user_id, $is_comment]);
    $post_count_24h = $stmtCount->fetchColumn();

    if ($post_count_24h >= $limit) {
        header('Location: ' . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php', ENT_QUOTES, 'UTF-8') . '?error=limit_reached');
        exit;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $stmtLvl = $pdo->prepare("SELECT level, role FROM users WHERE id = ?");
        $stmtLvl->execute([$user_id]);
        $u_info = $stmtLvl->fetch(PDO::FETCH_ASSOC);

        if ($u_info['role'] == 'admin' || $u_info['level'] >= 15) {
            $image_path = handleSecureImageUpload($_FILES['image'], 'tw_img_');
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO tweets (user_id, description, is_comment, parent_id, image, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if ($stmt->execute([$user_id, $desc, $is_comment, $parent_id, $image_path])) {
        if (function_exists('apply_tweet_gamification')) apply_tweet_gamification($pdo, $user_id, false);
    }
    header('Location: ' . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php', ENT_QUOTES, 'UTF-8'));
    exit;
}


if ($action == 'edit_tweet' && isset($_POST['tweet_id']) && isset($_POST['description'])) {
    $tweet_id = intval($_POST['tweet_id']);
    $new_desc = htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8');
    $remove_image = isset($_POST['remove_image']) ? true : false;
    $new_image_path = null;
    $has_new_image = false;

    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $stmtLvl = $pdo->prepare("SELECT level, role FROM users WHERE id = ?");
        $stmtLvl->execute([$user_id]);
        $u_info = $stmtLvl->fetch(PDO::FETCH_ASSOC);

        if ($u_info['role'] == 'admin' || $u_info['level'] >= 15) {
            $uploaded = handleSecureImageUpload($_FILES['image'], 'tw_img_edit_');
            if ($uploaded) {
                $new_image_path = $uploaded;
                $has_new_image = true;
            }
        }
    }
    
    $auth_cond = $is_admin ? "" : " AND user_id = ?";
    $params = [$tweet_id];
    if (!$is_admin) $params[] = $user_id;
    
    if ($remove_image || $has_new_image) {
        $stmtImg = $pdo->prepare("SELECT image FROM tweets WHERE id = ?" . $auth_cond);
        $stmtImg->execute($params);
        $old_image = $stmtImg->fetchColumn();
        if ($old_image && file_exists($old_image)) unlink($old_image);
    }

    if ($has_new_image) {
        $qParams = [$new_desc, $new_image_path, $tweet_id];
        if (!$is_admin) $qParams[] = $user_id;
        $stmt = $pdo->prepare("UPDATE tweets SET description = ?, image = ? WHERE id = ?" . $auth_cond);
        $stmt->execute($qParams);
    } elseif ($remove_image) {
        $qParams = [$new_desc, $tweet_id];
        if (!$is_admin) $qParams[] = $user_id;
        $stmt = $pdo->prepare("UPDATE tweets SET description = ?, image = NULL WHERE id = ?" . $auth_cond);
        $stmt->execute($qParams);
    } else {
        $qParams = [$new_desc, $tweet_id];
        if (!$is_admin) $qParams[] = $user_id;
        $stmt = $pdo->prepare("UPDATE tweets SET description = ? WHERE id = ?" . $auth_cond);
        $stmt->execute($qParams);
    }
    
    header('Location: ' . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php', ENT_QUOTES, 'UTF-8'));
    exit;
}

if ($action == 'delete_tweet' && isset($_POST['tweet_id'])) {
    $tweet_id = intval($_POST['tweet_id']);
    
    $stmtInfo = $pdo->prepare("SELECT user_id, image FROM tweets WHERE id = ?");
    $stmtInfo->execute([$tweet_id]);
    $tweet_info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    $tweet_author = $tweet_info ? $tweet_info['user_id'] : null;
    $tweet_image = $tweet_info ? $tweet_info['image'] : null;

    $stmtChildImages = $pdo->prepare("SELECT image FROM tweets WHERE parent_id = ?");
    $stmtChildImages->execute([$tweet_id]);
    $child_images = $stmtChildImages->fetchAll(PDO::FETCH_COLUMN);
    foreach ($child_images as $c_img) {
        if ($c_img && file_exists($c_img)) unlink($c_img);
    }
    
    $pdo->prepare("DELETE FROM likes WHERE tweet_id = ?")->execute([$tweet_id]);
    $pdo->prepare("DELETE FROM tweets WHERE parent_id = ?")->execute([$tweet_id]);

    $deleted = false;
    if ($is_admin) {
        $stmt = $pdo->prepare("DELETE FROM tweets WHERE id = ?");
        $deleted = $stmt->execute([$tweet_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM tweets WHERE id = ? AND user_id = ?");
        $stmt->execute([$tweet_id, $user_id]);
        if ($stmt->rowCount() > 0) $deleted = true;
    }
    
    if ($deleted && $tweet_image && file_exists($tweet_image)) {
        unlink($tweet_image);
    }

    if ($deleted && $tweet_author && function_exists('apply_tweet_gamification')) {
        apply_tweet_gamification($pdo, $tweet_author, true);
    }
    
    header('Location: ' . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php', ENT_QUOTES, 'UTF-8'));
    exit;
}

if ($action == 'check_user' && isset($_GET['username'])) {
    $uname = trim($_GET['username']);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$uname]);
    if ($stmt->fetch()) {
         echo json_encode(['exists' => true]);
    } else {
         echo json_encode(['exists' => false]);
    }
    exit;
}

if ($action == 'retweet' && isset($_POST['tweet_id'])) {
    $parent_id = intval($_POST['tweet_id']); 
    $desc = isset($_POST['description']) ? htmlspecialchars(trim($_POST['description']), ENT_QUOTES, 'UTF-8') : '';
    
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tweets WHERE user_id = ? AND is_retweet = 1 AND created_at >= NOW() - INTERVAL 24 HOUR");
    $stmtCount->execute([$user_id]);
    $rt_count_24h = $stmtCount->fetchColumn();

    if ($rt_count_24h >= 2) {
        header("Location: " . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php', ENT_QUOTES, 'UTF-8') . '?error=limit_reached');
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO tweets (user_id, description, image, parent_id, is_comment, is_retweet, created_at) VALUES (?, ?, NULL, ?, 0, 1, NOW())");
    if ($stmt->execute([$user_id, $desc, $parent_id])) {
        if (function_exists('apply_tweet_gamification')) apply_tweet_gamification($pdo, $user_id, false);
    }
    header("Location: " . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php', ENT_QUOTES, 'UTF-8'));
    exit;
}

if ($action === 'accept_privacy') {
    header('Content-Type: application/json');
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET privacy_accepted = 1 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $_SESSION['privacy_accepted'] = 1; 
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database update failed']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
    }
    exit;
}

if ($action == 'like_ajax') {
    header('Content-Type: application/json');
    $tweet_id = (int)($_POST['tweet_id'] ?? 0);
    
    $authorStmt = $pdo->prepare("SELECT user_id FROM tweets WHERE id = ?");
    $authorStmt->execute([$tweet_id]);
    $tweet_author = $authorStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND tweet_id = ?");
    $stmt->execute([$user_id, $tweet_id]);
    $liked = false;
    
    if ($stmt->rowCount() > 0) {
        $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND tweet_id = ?")->execute([$user_id, $tweet_id]);
        if ($tweet_author && $tweet_author != $user_id) {
            apply_tweet_like_gamification($pdo, $tweet_author, true);
        }
    } else {
        $pdo->prepare("INSERT INTO likes (user_id, tweet_id) VALUES (?, ?)")->execute([$user_id, $tweet_id]);
        $liked = true;
        if ($tweet_author && $tweet_author != $user_id) {
            apply_tweet_like_gamification($pdo, $tweet_author, false);
        }
    }
    
    $count = $pdo->prepare("SELECT COUNT(id) FROM likes WHERE tweet_id = ?");
    $count->execute([$tweet_id]);
    $total = $count->fetchColumn();
    
    echo json_encode(['success' => true, 'liked' => $liked, 'likes' => (int)$total]);
    exit;
}

if ($action == 'edit_profile') {
    $name = htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $username = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');
    $bio = htmlspecialchars($_POST['bio'] ?? '', ENT_QUOTES, 'UTF-8');
    
    $query_parts = ["name = ?", "username = ?", "bio = ?"];
    $params = [$name, $username, $bio];
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $avatar_path = handleSecureImageUpload($_FILES['avatar'], 'avatar_' . $user_id . '_');
        if ($avatar_path) {
            $query_parts[] = "avatar = ?";
            $params[] = $avatar_path;
        }
    }

    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $cover_path = handleSecureImageUpload($_FILES['cover'], 'cover_' . $user_id . '_');
        if ($cover_path) {
            $query_parts[] = "cover = ?";
            $params[] = $cover_path;
        }
    }
    
    $params[] = $user_id;
    $set_query = implode(', ', $query_parts);
    
    $stmt = $pdo->prepare("UPDATE users SET $set_query WHERE id = ?");
    $stmt->execute($params);
    header('Location: profile.php?id=' . $user_id);
    exit;
}

if ($action == 'delete_account' && isset($_POST['confirm_delete'])) {
    $pdo->prepare("DELETE FROM likes WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM follows WHERE follower_id = ? OR following_id = ?")->execute([$user_id, $user_id]);
    $stmt = $pdo->prepare("SELECT id FROM tweets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_tweets = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($user_tweets as $t_id) {
        $pdo->prepare("DELETE FROM likes WHERE tweet_id = ?")->execute([$t_id]);
    }
    $pdo->prepare("DELETE FROM tweets WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    
    session_unset();
	session_destroy();
    if (isset($_COOKIE['user_auth'])) {
        setcookie('user_auth', '', time() - 3600, '/', '', true, true);
    }
    header('Location: auth.php');
    exit;
}

if ($action == 'follow' && isset($_POST['following_id'])) {
    $following_id = (int)$_POST['following_id'];
    if ($user_id != $following_id) {
        $stmt = $pdo->prepare("SELECT * FROM follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$user_id, $following_id]);
        if ($stmt->rowCount() > 0) {
            $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?")->execute([$user_id, $following_id]); 
        } else {
            $pdo->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)")->execute([$user_id, $following_id]); 
        }
    }
    header('Location: profile.php?id=' . $following_id);
    exit;
}

if ($action == 'search_users' && isset($_GET['q'])) {
    $q = '%' . htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') . '%';
    $stmt = $pdo->prepare("SELECT id, name, username, avatar, is_verified FROM users WHERE id != ? AND (name LIKE ? OR username LIKE ?) LIMIT 15");
    $stmt->execute([$uid, $q, $q]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action == 'create_personal' && isset($_POST['target_id'])) {
    $target_id = (int)$_POST['target_id'];
    if ($target_id == $uid) { header('Location: chat.php'); exit; }

    $stmt = $pdo->prepare("
        SELECT c.id FROM conversations c
        JOIN participants p1 ON c.id = p1.conversation_id
        JOIN participants p2 ON c.id = p2.conversation_id
        WHERE c.is_group = 0 AND p1.user_id = ? AND p2.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$uid, $target_id]);
    $existing_chat = $stmt->fetchColumn();

    if ($existing_chat) {
        $pdo->prepare("INSERT IGNORE INTO participants (conversation_id, user_id) VALUES (?, ?)")->execute([$existing_chat, $uid]);
        header("Location: chat_view.php?id=" . $existing_chat);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO conversations (is_group, updated_at) VALUES (0, NOW())");
        $stmt->execute();
        $conv_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
        $stmt->execute([$conv_id, $uid, $conv_id, $target_id]);
        $pdo->commit();
        header("Location: chat_view.php?id=" . $conv_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: chat.php');
        exit;
    }
}

define('CHAT_SECRET_KEY', 'Atox_Secure_Chat_Key_2026_@!#123'); 

function encryptMessage($text) {
    if (empty($text)) return $text;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($text, 'aes-256-cbc', CHAT_SECRET_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}


if ($action === 'join_group_link') {
    $group_id = (int)$_POST['group_id'];
    $stmt = $pdo->prepare("SELECT 1 FROM participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $uid]);
    if (!$stmt->fetchColumn()) {
        $stmt = $pdo->prepare("INSERT INTO participants (conversation_id, user_id) VALUES (?, ?)");
        $stmt->execute([$group_id, $uid]);
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($action === 'delete_chat') {
    $chat_id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ?");
    $stmt->execute([$chat_id]);
    exit;
}

if ($action === 'bulk_delete') {
    $ids = explode(',', $_POST['ids'] ?? '');
    foreach ($ids as $id) {
        $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ?");
        $stmt->execute([(int)$id]);
    }
    exit;
}

if ($action === 'block_user') {
    $target_id = (int)$_POST['id'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)");
    $stmt->execute([$uid, $target_id]);
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($action === 'unblock_user') {
    $target_id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$uid, $target_id]);
    echo json_encode(['status' => 'ok']);
    exit;
}


if ($action === 'send_msg') {
    $conv_id = (int)$_POST['conversation_id'];
    $text = htmlspecialchars(trim($_POST['message_text'] ?? ''), ENT_QUOTES, 'UTF-8');
    $reply_to = isset($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;

    if (!empty($text) && $conv_id > 0) {
        $encrypted_text = encryptMessage($text); 
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, user_id, message_text, reply_to_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$conv_id, $uid, $encrypted_text, $reply_to]);
        $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conv_id]);
    }
    exit;
}

if ($action === 'edit_msg') {
    $msg_id = (int)$_POST['msg_id'];
    $text = htmlspecialchars(trim($_POST['message_text'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (!empty($text)) {
        $encrypted_text = encryptMessage($text); 
        $stmt = $pdo->prepare("UPDATE messages SET message_text = ?, is_edited = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$encrypted_text, $msg_id, $uid]);
    }
    exit;
}

if ($action === 'delete_msg') {
    $msg_id = (int)$_POST['id'];
    $stmt = $pdo->prepare("SELECT m.user_id, c.admin_id FROM messages m JOIN conversations c ON m.conversation_id = c.id WHERE m.id = ?");
    $stmt->execute([$msg_id]);
    $msg_data = $stmt->fetch();

    if ($msg_data && ($msg_data['user_id'] == $uid || $msg_data['admin_id'] == $uid)) {
        $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$msg_id]);
    }
    exit;
}

if ($action === 'join_group' && isset($_POST['id'])) {
    $conv_id = (int)$_POST['id'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO participants (conversation_id, user_id) VALUES (?, ?)");
    $stmt->execute([$conv_id, $uid]);
    exit;
}

if ($action === 'leave_group' && isset($_POST['id'])) {
    $conv_id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conv_id, $uid]);
    exit;
}

if ($action === 'clear_group_history' && isset($_POST['id'])) {
    $conv_id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE conversation_id = ?");
    $stmt->execute([$conv_id]);
    exit;
}

if ($action === 'delete_group' && isset($_POST['id'])) {
    $conv_id = (int)$_POST['id'];
    $stmt = $pdo->prepare("SELECT admin_id FROM conversations WHERE id = ? AND is_group = 1");
    $stmt->execute([$conv_id]);
    $admin_id = $stmt->fetchColumn();
    if ($admin_id == $uid) {
        $pdo->prepare("DELETE FROM conversations WHERE id = ?")->execute([$conv_id]);
    }
    exit;
}

if ($action === 'edit_group') {
    $group_id = (int)$_POST['group_id'];
    $name = htmlspecialchars(trim($_POST['group_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars(trim($_POST['group_desc'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    $stmtAdmin = $pdo->prepare("SELECT admin_id FROM conversations WHERE id = ? AND is_group = 1");
    $stmtAdmin->execute([$group_id]);
    $admin_id = $stmtAdmin->fetchColumn();
    
    if ($admin_id == $uid) {
        $avatarQuery = "";
        $params = [$name, $desc];
        
        if (isset($_FILES['group_avatar']) && $_FILES['group_avatar']['error'] === UPLOAD_ERR_OK) {
            $filepath = handleSecureImageUpload($_FILES['group_avatar'], 'group_' . $group_id . '_');
            if ($filepath) {
                $avatarQuery = ", group_avatar = ?";
                $params[] = $filepath;
            }
        }
        
        $params[] = $group_id;
        $stmt = $pdo->prepare("UPDATE conversations SET group_name = ?, group_description = ? $avatarQuery WHERE id = ?");
        $stmt->execute($params);
    }
    exit;
}

if ($action === 'delete_chat_both_sides' && isset($_POST['id'])) {
    $chat_id = (int)$_POST['id'];
    $pdo->prepare("DELETE FROM conversations WHERE id = ? AND is_group = 0")->execute([$chat_id]);
    exit;
}

if ($action === 'delete_chat_one_side' && isset($_POST['id'])) {
    $chat_id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$chat_id, $uid]);
    exit;
}

if ($action == 'edit_resume') {
    $job = htmlspecialchars($_POST['job'] ?? '', ENT_QUOTES, 'UTF-8');
    $uni = htmlspecialchars($_POST['university'] ?? '', ENT_QUOTES, 'UTF-8');
    $edu = htmlspecialchars($_POST['education'] ?? '', ENT_QUOTES, 'UTF-8');
    $loc = htmlspecialchars($_POST['location'] ?? '', ENT_QUOTES, 'UTF-8');
    $byear = htmlspecialchars($_POST['birth_year'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $linkedin = filter_var($_POST['linkedin'] ?? '', FILTER_SANITIZE_URL);

    $skills = [];
    $s_names = $_POST['s_name'] ?? [];
    $s_pcts = $_POST['s_pct'] ?? [];
    for($i = 0; $i < count($s_names); $i++) {
        $n = htmlspecialchars(trim($s_names[$i]), ENT_QUOTES, 'UTF-8');
        $p = intval($s_pcts[$i]);
        if (!empty($n) && $p > 0) {
            $skills[] = ['name' => $n, 'percent' => min($p, 100)];
        }
    }
    $skills_json = !empty($skills) ? json_encode($skills, JSON_UNESCAPED_UNICODE) : null;

    $langs = [];
    $l_names = $_POST['l_name'] ?? [];
    $l_pcts = $_POST['l_pct'] ?? [];
    for($i = 0; $i < count($l_names); $i++) {
        $n = htmlspecialchars(trim($l_names[$i]), ENT_QUOTES, 'UTF-8');
        $p = intval($l_pcts[$i]);
        if (!empty($n) && $p > 0) {
            $langs[] = ['name' => $n, 'percent' => min($p, 100)];
        }
    }
    $langs_json = !empty($langs) ? json_encode($langs, JSON_UNESCAPED_UNICODE) : null;

    $soft_skills = [];
    if (isset($_POST['soft_skills']) && is_array($_POST['soft_skills'])) {
        $posted_soft = array_slice($_POST['soft_skills'], 0, 3);
        foreach($posted_soft as $sk) {
            $soft_skills[] = htmlspecialchars($sk, ENT_QUOTES, 'UTF-8');
        }
    }
    $soft_json = !empty($soft_skills) ? json_encode($soft_skills, JSON_UNESCAPED_UNICODE) : null;

    $stmt = $pdo->prepare("SELECT user_id FROM resumes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    if ($stmt->rowCount() > 0) {
        $pdo->prepare("UPDATE resumes SET job=?, university=?, education=?, location=?, birth_year=?, email=?, linkedin=?, skills=?, languages=?, soft_skills=? WHERE user_id=?")
            ->execute([$job, $uni, $edu, $loc, $byear, $email, $linkedin, $skills_json, $langs_json, $soft_json, $user_id]);
    } else {
        $pdo->prepare("INSERT INTO resumes (user_id, job, university, education, location, birth_year, email, linkedin, skills, languages, soft_skills) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$user_id, $job, $uni, $edu, $loc, $byear, $email, $linkedin, $skills_json, $langs_json, $soft_json]);
    }
    header('Location: profile.php?id=' . $user_id);
    exit;
}

if ($action == 'change_password') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($old_password) || empty($new_password)) {
        $error = "لطفاً هر دو فیلد رمز عبور را پر کنید.";
        $_SESSION['error'] = $error;
        header('Location: settings.php?id=' . $user_id);
        exit;
    }

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error'] = "کاربری با این مشخصات یافت نشد.";
        header('Location: settings.php?id=' . $user_id);
        exit;
    }

    if (!password_verify($old_password, $user['password'])) {
        $_SESSION['error'] = "رمز عبور فعلی اشتباه است. رمز تغییر نکرد.";
        header('Location: settings.php?id=' . $user_id);
        exit;
    }

    $new_pass_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->execute([$new_pass_hash, $user_id]);

    $_SESSION['success'] = "رمز عبور با موفقیت تغییر کرد.";
    header('Location: profile.php?id=' . $user_id);
    exit;
}

if ($action === 'delete_anon_reply') {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE ar FROM anon_replies ar JOIN tweets t ON ar.tweet_id = t.id WHERE ar.id = ? AND t.user_id = ?");
    $stmt->execute([$id, $uid]);
    exit;
}

$current_session_id = session_id();
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device', ENT_QUOTES, 'UTF-8');

$stmt_session = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at, last_active) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE last_active = NOW()");
$stmt_session->execute([$uid, $current_session_id, $ip_address, $user_agent]);

if ($action === 'revoke_session') {
    header('Content-Type: application/json');
    $session_to_revoke = (int)($_POST['session_id'] ?? 0);

    try {
        $stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$session_to_revoke, $uid]);
        
        if ($stmt->rowCount() > 0) {
            $pdo->prepare("DELETE FROM user_sessions WHERE id = ?")->execute([$session_to_revoke]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'نشست یافت نشد یا شما دسترسی ندارید.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'خطای سرور']);
    }
    exit;
}
?>