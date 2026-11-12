<?php

require_once __DIR__ . '/db.php';

$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $currentUser = $conn->execute_query("SELECT id, uid, username, account_type, email, name, avatar, role, bio FROM users WHERE id = ?", [$_SESSION['user_id']])->fetch_assoc();
    if (!$currentUser) {
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['account_type'], $_SESSION['account_id']);
    } else {
        $_SESSION['user_role'] = $currentUser['role'] ?? 'student';
        $_SESSION['account_type'] = $currentUser['account_type'] ?? $currentUser['role'] ?? 'student';
        if (!empty($currentUser['uid'])) {
            $_SESSION['account_id'] = $currentUser['uid'];
        }
    }
}
$currentUserId = $currentUser ? (int)$currentUser['id'] : 0;
function time_ago(?string $datetime): string {
    if (!$datetime) return 'just now';
    $d = time() - strtotime($datetime);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    if ($d < 604800) return floor($d / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));

}
function upload_post_images(?array $files, int $postId, mysqli $conn): void {
    if (empty($files['name'][0])) return;
    for ($i = 0; $i < min(count($files['name']), 4); $i++) {
        if (($files['error'][$i] ?? 1) === UPLOAD_ERR_OK) {
            $tmp = $files['tmp_name'][$i];
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $mime = mime_content_type($tmp) ?: "image/$ext";
                $base64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($tmp));
                $conn->execute_query("INSERT INTO post_images (post_id, image_url) VALUES (?, ?)", [$postId, $base64]);
            }
        }
    }
}

function upload_single_image(?array $file): ?string {
    if (empty($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) return null;
    $tmp = $file['tmp_name'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $mime = mime_content_type($tmp) ?: "image/$ext";
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($tmp));
    }
    return null;
}



// Recursively render a reply and its child replies

function renderReplyNode(array $rep, array $childrenByParent, array $repliesById, ?array $currentUser, int $postId, int $depth = 0, bool $isGroupRestricted = false): void {

    $isRepAuthor = ($currentUser && (int)$rep['user_id'] === (int)$currentUser['id']);

    $canModifyReply = (!$isGroupRestricted || is_admin());

    $repId = (int)$rep['id'];

    $parentId = !empty($rep['parent_id']) ? (int)$rep['parent_id'] : 0;

    $parentAuthor = ($parentId > 0 && isset($repliesById[$parentId])) ? $repliesById[$parentId]['author_username'] : null;

    $children = $childrenByParent[$repId] ?? [];

    ?>

    <div class="reply-thread-item" id="reply-<?= $repId ?>" style="position: relative; margin-bottom: 12px;">

      <div class="reply-card">

        <div class="reply-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">

          <div class="reply-author" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">

            <?php if (($rep['author_role'] ?? '') !== 'admin' || is_admin()): ?>
            <a href="profile.php?id=<?= (int)$rep['user_id'] ?>" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: inherit;">
              <img src="<?= e($rep['author_avatar']) ?>" alt="<?= e($rep['author_name']) ?>" class="reply-avatar" style="width: 24px; height: 24px; border-radius: 50%;">
              <span class="reply-author-name" style="font-size: 13px; font-weight: 700; color: var(--text-main);"><?= e($rep['author_name']) ?></span>
            </a>
            <?php else: ?>
            <span style="display: inline-flex; align-items: center; gap: 8px; color: inherit;">
              <img src="<?= e($rep['author_avatar']) ?>" alt="<?= e($rep['author_name']) ?>" class="reply-avatar" style="width: 24px; height: 24px; border-radius: 50%;">
              <span class="reply-author-name" style="font-size: 13px; font-weight: 700; color: var(--text-main);"><?= e($rep['author_name']) ?></span>
            </span>
            <?php endif; ?>
            <?php if (!empty($parentAuthor)): ?>
              <span class="reply-to-tag" style="font-size: 11.5px; color: var(--text-dim); display: inline-flex; align-items: center; gap: 3px;">
                <span>replied to</span>
                <a href="profile.php?u=<?= urlencode($parentAuthor) ?>" style="color: var(--primary-blue); font-weight: 600; text-decoration: none;">@<?= e($parentAuthor) ?></a>
              </span>
            <?php endif; ?>

            <span class="reply-timestamp" style="font-size: 12px; color: var(--text-dim);">· <?= time_ago($rep['created_at']) ?></span>

            <?php if (!empty($rep['updated_at'])): ?>

              <span style="font-size: 11px; color: var(--text-dim);">(edited)</span>

            <?php endif; ?>

          </div>



          <!-- Actions for reply author / admin (Edit, Delete) -->

          <div style="display: flex; align-items: center; gap: 8px;">

            <?php if ($isRepAuthor && $canModifyReply): ?>

              <button type="button" class="btn-edit-reply" onclick="toggleEditReplyForm(<?= $repId ?>)" style="background: none; border: none; color: var(--primary-blue); font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">

                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>

                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>

                </svg>

                Edit

              </button>

            <?php endif; ?>
            <?php if (($isRepAuthor && $canModifyReply) || is_admin()): ?>
              <a href="index.php?action=delete_reply&id=<?= $repId ?>" class="btn-delete-reply" onclick="return confirm('<?= is_admin() && !$isRepAuthor ? 'Admin: Remove this reply?' : 'Delete this reply?' ?>')" style="background: none; border: none; color: var(--danger-red); font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                  <polyline points="3 6 5 6 21 6"></polyline>

                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>

                </svg>

                <?= is_admin() && !$isRepAuthor ? 'Moderate (Delete)' : 'Delete' ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
        <!-- Reply Body Text -->

        <div class="reply-body-text" id="reply-text-<?= $repId ?>" style="font-size: 13.5px; color: var(--text-main); line-height: 1.5; margin-bottom: 6px;">
          <?= nl2br(e($rep['content'])) ?>
        </div>

        <!-- Inline Reply Edit Form -->

        <?php if ($isRepAuthor && $canModifyReply): ?>

          <form action="index.php" method="POST" id="reply-edit-form-<?= $repId ?>" style="display: none; margin-bottom: 10px;">

            <input type="hidden" name="action" value="edit_reply">

            <input type="hidden" name="reply_id" value="<?= $repId ?>">

            <textarea name="content" class="reply-textarea" style="width: 100%; min-height: 60px; margin-bottom: 8px;" required><?= e($rep['content']) ?></textarea>

            <div style="display: flex; gap: 8px;">

              <button type="submit" class="btn-post-reply" style="padding: 5px 14px; font-size: 12px;">Save</button>

              <button type="button" class="btn-modal-cancel" style="padding: 5px 14px; font-size: 12px;" onclick="toggleEditReplyForm(<?= $repId ?>)">Cancel</button>

            </div>

          </form>

        <?php endif; ?>



        <!-- Reply Photo Attachment -->

        <?php if (!empty($rep['image_url'])): ?>

          <div class="reply-image-attachment" onclick="openLightbox('<?= e($rep['image_url']) ?>')" style="margin: 6px 0; max-width: 240px; border-radius: 8px; overflow: hidden; cursor: pointer;">

            <img src="<?= e($rep['image_url']) ?>" alt="Reply photo" style="width: 100%; height: auto; display: block;">

          </div>

        <?php endif; ?>



        <!-- Nested Reply Action Trigger -->

        <?php if ($canModifyReply): ?>
        <div class="reply-actions-bar" style="margin-top: 6px;">

          <button type="button" class="btn-nested-reply" onclick="<?= $currentUser ? "toggleNestedComposer($repId)" : "window.location.href='register.php'" ?>" style="background: none; border: none; color: var(--text-muted); font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">

            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

              <path d="M3 10h10a5 5 0 0 1 5 5v5"></path>

              <path d="m7 6-4 4 4 4"></path>

            </svg>

            Reply

          </button>

        </div>



        <!-- Nested Inline Composer Box -->

        <div class="inline-nested-composer" id="nested-composer-<?= $repId ?>" style="display: none; margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border-color);">

          <form action="index.php" method="POST" enctype="multipart/form-data">

            <input type="hidden" name="action" value="create_reply">

            <input type="hidden" name="post_id" value="<?= $postId ?>">

            <input type="hidden" name="parent_id" value="<?= $repId ?>">

            <textarea name="content" class="reply-textarea" style="width: 100%; min-height: 55px; margin-bottom: 8px;" placeholder="Replying to @<?= e($rep['author_username']) ?>..." required></textarea>

            <div style="display: flex; align-items: center; justify-content: space-between;">

              <label class="btn-attach-image" style="cursor: pointer; font-size: 12px; color: var(--primary-blue);">

                <span>Photo</span>

                <input type="file" name="reply_image" accept="image/*" style="display: none;">

              </label>

              <div style="display: flex; gap: 8px;">

                <button type="button" class="btn-modal-cancel" style="padding: 5px 12px; font-size: 12px;" onclick="toggleNestedComposer(<?= $repId ?>)">Cancel</button>

                <button type="submit" class="btn-post-reply" style="padding: 5px 14px; font-size: 12px;">Reply</button>

              </div>

            </div>

          </form>

        </div>
        <?php endif; ?>

      </div>



      <!-- Threaded Sub-Replies -->

      <?php if (!empty($children)): ?>

        <div class="reply-children-list">

          <?php foreach ($children as $child): ?>

            <?php renderReplyNode($child, $childrenByParent, $repliesById, $currentUser, $postId, $depth + 1, $isGroupRestricted); ?>

          <?php endforeach; ?>

        </div>

      <?php endif; ?>

    </div>

    <?php

}



$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action !== '' && !$currentUser) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'redirect' => 'register.php']);
        exit;
    }
    header("Location: register.php");
    exit;
}



// Join Group Action

if ($action === 'join_group') {

    $groupId = (int)($_REQUEST['group_id'] ?? 0);

    if ($groupId > 0) {

        $conn->execute_query("INSERT IGNORE INTO group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())", [$groupId, $currentUser['id']]);

    }

    $redirect = !empty($_REQUEST['redirect']) ? $_REQUEST['redirect'] : "index.php?group=$groupId&msg=joined";

    header("Location: $redirect");

    exit;

}



// Leave Group Action

if ($action === 'leave_group') {

    $groupId = (int)($_REQUEST['group_id'] ?? 0);

    if ($groupId > 0) {

        $conn->execute_query("DELETE FROM group_members WHERE group_id = ? AND user_id = ?", [$groupId, $currentUser['id']]);

    }

    $redirect = !empty($_REQUEST['redirect']) ? $_REQUEST['redirect'] : "index.php?group=$groupId&msg=left";

    header("Location: $redirect");

    exit;

}



// Admin: Create Group

if ($action === 'create_group') {

    if (!$currentUser) {
        header("Location: register.php");
        exit;
    }
    if (!is_admin()) {

        header("Location: index.php?error=unauthorized");

        exit;

    }

    $name        = trim($_POST['name'] ?? '');

    $description = trim($_POST['description'] ?? '');

    $status      = in_array($_POST['status'] ?? '', ['active', 'restricted']) ? $_POST['status'] : 'active';

    $imageUrl    = upload_single_image($_FILES['group_image'] ?? null);



    if ($name !== '') {

        $conn->execute_query(

            "INSERT INTO groups (name, description, image_url, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())",

            [$name, $description, $imageUrl, $status, $currentUser['id']]

        );

        $newGid = (int)$conn->insert_id;

        $conn->execute_query("INSERT IGNORE INTO group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())", [$newGid, $currentUser['id']]);

        header("Location: index.php?group=$newGid&msg=group_created");

        exit;

    }

    header("Location: index.php?view=admin&error=empty_name");

    exit;

}



// Admin: Edit Group

if ($action === 'edit_group') {

    if (!$currentUser) {
        header("Location: register.php");
        exit;
    }
    if (!is_admin()) {

        header("Location: index.php?error=unauthorized");

        exit;

    }

    $groupId     = (int)($_POST['group_id'] ?? 0);

    $name        = trim($_POST['name'] ?? '');

    $description = trim($_POST['description'] ?? '');

    $status      = in_array($_POST['status'] ?? '', ['active', 'restricted']) ? $_POST['status'] : 'active';

    $newImage    = upload_single_image($_FILES['group_image'] ?? null);



    if ($groupId > 0 && $name !== '') {

        if ($newImage) {

            $conn->execute_query("UPDATE groups SET name = ?, description = ?, status = ?, image_url = ? WHERE id = ?", [$name, $description, $status, $newImage, $groupId]);

        } else {

            $conn->execute_query("UPDATE groups SET name = ?, description = ?, status = ? WHERE id = ?", [$name, $description, $status, $groupId]);

        }

        $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : "index.php?group=$groupId&msg=group_updated";

        header("Location: $redirect");

        exit;

    }

    header("Location: index.php?view=admin");

    exit;

}



// Admin: Toggle Group Status (Active / Restricted)

if ($action === 'toggle_group_status') {

    if (!$currentUser) {
        header("Location: register.php");
        exit;
    }
    if (!is_admin()) {

        header("Location: index.php?error=unauthorized");

        exit;

    }

    $groupId = (int)($_REQUEST['group_id'] ?? 0);

    $status  = ($_REQUEST['status'] ?? '') === 'restricted' ? 'restricted' : 'active';

    if ($groupId > 0) {

        $conn->execute_query("UPDATE groups SET status = ? WHERE id = ?", [$status, $groupId]);

    }

    $redirect = !empty($_REQUEST['redirect']) ? $_REQUEST['redirect'] : "index.php?group=$groupId&msg=status_updated";

    header("Location: $redirect");

    exit;

}



// Admin: Delete Group

if ($action === 'delete_group') {

    if (!$currentUser) {
        header("Location: register.php");
        exit;
    }
    if (!is_admin()) {

        header("Location: index.php?error=unauthorized");

        exit;

    }

    $groupId = (int)($_REQUEST['group_id'] ?? 0);

    if ($groupId > 0) {

        $imgs = $conn->execute_query("SELECT image_url FROM post_images WHERE post_id IN (SELECT id FROM posts WHERE group_id = ?)", [$groupId]);

        while ($img = $imgs->fetch_assoc()) {

            if (str_starts_with($img['image_url'], 'assets/')) @unlink(__DIR__ . '/' . $img['image_url']);

        }

        $conn->execute_query("DELETE FROM groups WHERE id = ?", [$groupId]);

        header("Location: index.php?view=admin&msg=group_deleted");

        exit;

    }

    header("Location: index.php?view=admin");

    exit;

}



// Create Post (Group membership required, group must be active)
if ($action === 'create_post') {
    $isAjax  = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $groupId = (int)($_POST['group_id'] ?? 0);
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $respondError = function(string $msg, string $redirectUrl, string $field = '') use ($isAjax) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg, 'field' => $field]);
            exit;
        }
        header("Location: $redirectUrl");
        exit;
    };

    if ($groupId <= 0) {
        $respondError('Please select a group before publishing your discussion.', 'index.php?error=select_group', 'group_id');
    }

    $grp = $conn->execute_query("SELECT id, status, name FROM groups WHERE id = ?", [$groupId])->fetch_assoc();
    if (!$grp) {
        $respondError('The selected group could not be found.', 'index.php?error=group_not_found', 'group_id');
    }

    if ($grp['status'] !== 'active') {
        $respondError('This group is currently restricted and cannot accept new discussions.', "index.php?group=$groupId&error=group_restricted", 'group_id');
    }

    if (!is_group_member($currentUser['id'], $groupId, $conn)) {
        $respondError('You must join this group before publishing a discussion.', "index.php?group=$groupId&error=must_join_group", 'group_id');
    }

    if ($title === '' || $content === '') {
        $respondError('Please enter both a discussion title and content.', "index.php?group=$groupId&error=empty_fields", $title === '' ? 'title' : 'content');
    }

    $conn->execute_query(
        "INSERT INTO posts (user_id, group_id, title, content, created_at) VALUES (?, ?, ?, ?, NOW())",
        [$currentUser['id'], $groupId, $title, $content]
    );
    $postId = (int)$conn->insert_id;
    upload_post_images($_FILES['images'] ?? null, $postId, $conn);

    $redirectUrl = "index.php?group=$groupId&post=$postId#post-$postId";
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => $redirectUrl, 'message' => 'Discussion published!']);
        exit;
    }
    header("Location: $redirectUrl");
    exit;
}



// Edit Post (Owner only; blocked in restricted groups for non-admins)

if ($action === 'edit_post') {

    $postId  = (int)($_POST['post_id'] ?? 0);

    $title   = trim($_POST['title'] ?? '');

    $content = trim($_POST['content'] ?? '');

    $post = $conn->execute_query(
        "SELECT p.id, p.group_id, p.user_id, g.status AS group_status
         FROM posts p
         JOIN groups g ON p.group_id = g.id
         WHERE p.id = ?",
        [$postId]
    )->fetch_assoc();

    if ($post && $post['group_status'] === 'restricted' && !is_admin()) {
        header("Location: index.php?group={$post['group_id']}&post=$postId&error=group_restricted");
        exit;
    }

    if ($post && (int)$post['user_id'] === (int)$currentUser['id'] && $title !== '' && $content !== '') {

        $conn->execute_query("UPDATE posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?", [$title, $content, $postId]);

        foreach ((array)($_POST['delete_image_ids'] ?? []) as $id) {

            $img = $conn->execute_query("SELECT image_url FROM post_images WHERE id = ? AND post_id = ?", [(int)$id, $postId])->fetch_assoc();

            if ($img) {

                if (str_starts_with($img['image_url'], 'assets/')) @unlink(__DIR__ . '/' . $img['image_url']);

                $conn->execute_query("DELETE FROM post_images WHERE id = ?", [(int)$id]);

            }

        }

        upload_post_images($_FILES['images'] ?? null, $postId, $conn);

        header("Location: index.php?group={$post['group_id']}&post=$postId#post-$postId");

        exit;

    }

    header("Location: index.php");

    exit;

}



// Delete Post (Owner in active group OR Admin)

if ($action === 'delete_post') {

    $postId = (int)($_REQUEST['id'] ?? $_POST['post_id'] ?? 0);

    $post = $conn->execute_query(
        "SELECT p.id, p.group_id, p.user_id, g.status AS group_status
         FROM posts p
         JOIN groups g ON p.group_id = g.id
         WHERE p.id = ?",
        [$postId]
    )->fetch_assoc();

    if ($post && $post['group_status'] === 'restricted' && !is_admin()) {
        header("Location: index.php?group={$post['group_id']}&post=$postId&error=group_restricted");
        exit;
    }

    if ($post && ((int)$post['user_id'] === (int)$currentUser['id'] || is_admin())) {

        $imgResult = $conn->execute_query("SELECT image_url FROM post_images WHERE post_id = ?", [$postId]);

        while ($img = $imgResult->fetch_assoc()) {

            if (str_starts_with($img['image_url'], 'assets/')) @unlink(__DIR__ . '/' . $img['image_url']);

        }

        $conn->execute_query("DELETE FROM posts WHERE id = ?", [$postId]);

        $redirect = !empty($_REQUEST['redirect']) ? $_REQUEST['redirect'] : (!empty($post['group_id']) ? "index.php?group={$post['group_id']}&msg=deleted" : "index.php?msg=deleted");

        header("Location: $redirect");

        exit;

    }

    header("Location: index.php");

    exit;

}



// Create Reply (Blocked in restricted groups for non-admins)

if ($action === 'create_reply') {

    $postId   = (int)($_POST['post_id'] ?? 0);

    $postRow = $conn->execute_query(
        "SELECT p.group_id, g.status AS group_status
         FROM posts p
         JOIN groups g ON p.group_id = g.id
         WHERE p.id = ?",
        [$postId]
    )->fetch_assoc();

    if (!$postRow) {
        header("Location: index.php?error=post_not_found");
        exit;
    }

    if ($postRow['group_status'] === 'restricted' && !is_admin()) {
        header("Location: index.php?group={$postRow['group_id']}&post=$postId&error=group_restricted");
        exit;
    }

    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    $content  = trim($_POST['content'] ?? '');

    $imageUrl = upload_single_image($_FILES['reply_image'] ?? null);



    if ($content !== '' || $imageUrl) {

        $conn->execute_query(

            "INSERT INTO replies (post_id, user_id, parent_id, content, image_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())",

            [$postId, $currentUser['id'], $parentId, $content, $imageUrl]

        );

        $newRepId = (int)$conn->insert_id;

        $gParam = !empty($postRow['group_id']) ? "group={$postRow['group_id']}&" : "";

        header("Location: index.php?{$gParam}post=$postId#reply-$newRepId");

        exit;

    }

    header("Location: index.php?post=$postId#post-$postId");

    exit;

}



// Edit Reply (Owner only; blocked in restricted groups for non-admins)

if ($action === 'edit_reply') {

    $replyId = (int)($_POST['reply_id'] ?? 0);

    $content = trim($_POST['content'] ?? '');

    $reply = $conn->execute_query(
        "SELECT r.post_id, r.user_id, p.group_id, g.status AS group_status
         FROM replies r
         JOIN posts p ON r.post_id = p.id
         JOIN groups g ON p.group_id = g.id
         WHERE r.id = ?",
        [$replyId]
    )->fetch_assoc();

    if ($reply && $reply['group_status'] === 'restricted' && !is_admin()) {
        header("Location: index.php?group={$reply['group_id']}&post={$reply['post_id']}&error=group_restricted");
        exit;
    }

    if ($reply && (int)$reply['user_id'] === (int)$currentUser['id'] && $content !== '') {

        $conn->execute_query("UPDATE replies SET content = ?, updated_at = NOW() WHERE id = ?", [$content, $replyId]);

        $gParam = !empty($reply['group_id']) ? "group={$reply['group_id']}&" : "";

        header("Location: index.php?{$gParam}post={$reply['post_id']}#reply-$replyId");

        exit;

    }

    header("Location: index.php");

    exit;

}



// Delete Reply (Owner in active group OR Admin)

if ($action === 'delete_reply') {

    $replyId = (int)($_REQUEST['id'] ?? 0);

    $reply = $conn->execute_query(
        "SELECT r.post_id, r.user_id, r.image_url, p.group_id, g.status AS group_status
         FROM replies r
         JOIN posts p ON r.post_id = p.id
         JOIN groups g ON p.group_id = g.id
         WHERE r.id = ?",
        [$replyId]
    )->fetch_assoc();

    if ($reply && $reply['group_status'] === 'restricted' && !is_admin()) {
        header("Location: index.php?group={$reply['group_id']}&post={$reply['post_id']}&error=group_restricted");
        exit;
    }

    if ($reply && ((int)$reply['user_id'] === (int)$currentUser['id'] || is_admin())) {

        if (!empty($reply['image_url']) && str_starts_with($reply['image_url'], 'assets/')) {

            @unlink(__DIR__ . '/' . $reply['image_url']);

        }

        $conn->execute_query("DELETE FROM replies WHERE id = ?", [$replyId]);

        $gParam = !empty($reply['group_id']) ? "group={$reply['group_id']}&" : "";

        header("Location: index.php?{$gParam}post={$reply['post_id']}");

        exit;

    }

    header("Location: index.php");

    exit;

}



// View & Parameter Resolution

$current_view      = trim($_GET['view'] ?? '');

$selected_group_id = (int)($_GET['group'] ?? 0);

$search_query      = trim($_GET['q'] ?? '');

$single_post_id    = (int)($_GET['post'] ?? $_GET['id'] ?? 0);



// Get current user's joined active groups for post composer dropdown

$userJoinedGroupIds = [];

$gmStmt = $conn->execute_query("SELECT group_id FROM group_members WHERE user_id = ?", [$currentUserId]);

while ($grow = $gmStmt->fetch_assoc()) {

    $userJoinedGroupIds[] = (int)$grow['group_id'];

}



$userJoinedGroups = [];

if (!empty($userJoinedGroupIds)) {

    $placeholders = implode(',', array_fill(0, count($userJoinedGroupIds), '?'));

    $userJoinedGroups = $conn->execute_query(

        "SELECT id, name, description, status, image_url, (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = groups.id) as member_count FROM groups WHERE id IN ($placeholders) AND status = 'active' ORDER BY name ASC",

        $userJoinedGroupIds

    )->fetch_all(MYSQLI_ASSOC);

}



// If viewing a specific group

$currentGroup = null;

if ($selected_group_id > 0) {

    $currentGroup = $conn->execute_query("

        SELECT g.*, 

               (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as member_count,

               (SELECT COUNT(*) FROM posts p WHERE p.group_id = g.id) as post_count,

               (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.user_id = ?) as is_member

        FROM groups g 

        WHERE g.id = ?

    ", [$currentUserId, $selected_group_id])->fetch_assoc();

}



// If viewing Admin Console

$adminGroups = [];

$adminRecentPosts = [];

$stats = [];

if ($current_view === 'admin') {

    if (!$currentUser) {
        header("Location: register.php");
        exit;
    }
    if (!is_admin()) {

        header("Location: index.php?error=unauthorized");

        exit;

    }

    $adminGroups = $conn->query("

        SELECT g.*, u.name as creator_name,

               (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as member_count,

               (SELECT COUNT(*) FROM posts p WHERE p.group_id = g.id) as post_count

        FROM groups g

        LEFT JOIN users u ON g.created_by = u.id

        ORDER BY g.created_at DESC

    ")->fetch_all(MYSQLI_ASSOC);



    $adminRecentPosts = $conn->query("

        SELECT p.id, p.title, p.created_at, p.group_id, g.name as group_name, u.name as author_name, u.username as author_username,

               (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count

        FROM posts p

        JOIN groups g ON p.group_id = g.id

        JOIN users u ON p.user_id = u.id

        ORDER BY p.created_at DESC

        LIMIT 25

    ")->fetch_all(MYSQLI_ASSOC);



    $stats['total_groups'] = (int)$conn->query("SELECT COUNT(*) FROM groups")->fetch_row()[0];

    $stats['active_groups'] = (int)$conn->query("SELECT COUNT(*) FROM groups WHERE status = 'active'")->fetch_row()[0];

    $stats['total_posts'] = (int)$conn->query("SELECT COUNT(*) FROM posts")->fetch_row()[0];

    $stats['total_students'] = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetch_row()[0];

    $stats['total_faculty'] = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'faculty'")->fetch_row()[0];

}



// If viewing Groups Directory

$allGroups = [];

if ($current_view === 'groups') {

    $gSql = "

        SELECT g.*, 

               (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as member_count,

               (SELECT COUNT(*) FROM posts p WHERE p.group_id = g.id) as post_count,

               (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.user_id = ?) as is_member

        FROM groups g

    ";

    $gParams = [$currentUserId];

    if ($search_query !== '') {

        $gSql .= " WHERE (g.name LIKE ? OR g.description LIKE ?)";

        $gParams[] = "%$search_query%";

        $gParams[] = "%$search_query%";

    }

    $gSql .= " ORDER BY g.status ASC, member_count DESC, g.name ASC";

    $allGroups = $conn->execute_query($gSql, $gParams)->fetch_all(MYSQLI_ASSOC);

}



// Fetch Feed Posts (Home Feed or Group Feed)

$posts = [];

$postImages     = [];

$postImagesData = [];

$repliesByPost  = [];



if ($current_view !== 'admin' && $current_view !== 'groups') {

    $sql = "

        SELECT p.*, g.name as group_name, g.image_url as group_image, g.status as group_status,

               u.name as author_name, u.username as author_username, u.avatar as author_avatar, u.role as author_role,

               (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count

        FROM posts p

        JOIN groups g ON p.group_id = g.id

        JOIN users u ON p.user_id = u.id

    ";

    $params = [];



    if ($single_post_id > 0) {

        $sql .= " WHERE p.id = ?";

        $params[] = $single_post_id;

    } elseif ($selected_group_id > 0) {

        $sql .= " WHERE p.group_id = ?";

        $params[] = $selected_group_id;

        if ($search_query !== '') {

            $sql .= " AND (p.title LIKE ? OR p.content LIKE ?)";

            $params[] = "%$search_query%";

            $params[] = "%$search_query%";

        }

    } else {

        // Home feed: recent discussions from all active groups

        $sql .= " WHERE g.status = 'active'";

        if ($search_query !== '') {

            $sql .= " AND (p.title LIKE ? OR p.content LIKE ?)";

            $params[] = "%$search_query%";

            $params[] = "%$search_query%";

        }

    }



    $sql .= " ORDER BY p.created_at DESC";

    $posts = $conn->execute_query($sql, $params)->fetch_all(MYSQLI_ASSOC);

    $post_ids = array_column($posts, 'id');



    if (!empty($post_ids)) {

        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));



        $imgResult = $conn->execute_query("SELECT id, post_id, image_url FROM post_images WHERE post_id IN ($placeholders) ORDER BY id ASC", $post_ids);

        while ($row = $imgResult->fetch_assoc()) {

            $postImages[$row['post_id']][]     = $row['image_url'];

            $postImagesData[$row['post_id']][] = ['id' => (int)$row['id'], 'url' => $row['image_url']];

        }



        $repResult = $conn->execute_query("

            SELECT r.*, u.name as author_name, u.username as author_username, u.avatar as author_avatar, u.role as author_role

            FROM replies r

            JOIN users u ON r.user_id = u.id

            WHERE r.post_id IN ($placeholders)

            ORDER BY r.created_at ASC

        ", $post_ids);

        while ($row = $repResult->fetch_assoc()) {

            $repliesByPost[$row['post_id']][] = $row;

        }

    }

}



// Right Sidebar widgets

$recentDiscussions = $conn->query("

    SELECT p.id, p.title, p.created_at, p.group_id, g.name as group_name, u.name, u.avatar,

       (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count

    FROM posts p

    JOIN groups g ON p.group_id = g.id

    JOIN users u ON p.user_id = u.id

    WHERE g.status = 'active'

    ORDER BY p.created_at DESC LIMIT 5

")->fetch_all(MYSQLI_ASSOC);



$popularGroups = $conn->execute_query("

    SELECT g.id, g.name, g.status, g.image_url,

           (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as member_count,

           (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.user_id = ?) as is_member

    FROM groups g

    WHERE g.status = 'active'

    ORDER BY member_count DESC, g.id ASC

    LIMIT 5

", [$currentUserId])->fetch_all(MYSQLI_ASSOC);

?>



<!-- ========================================

HTML PART starts here

======================================== -->



<!DOCTYPE html>

<html lang="en">

<head>

  <meta charset="UTF-8">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Quaddit - College Discussion Board</title>

  <meta name="description" content="Quaddit is the modern discussion board for college students to share questions, images, setups, and campus moments.">

  <link rel="stylesheet" href="assets/css/style.css?v=<?= file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time() ?>">

  <link rel="icon" type="image/png" sizes="32x32" href="assets/images/logo-transparent.png?v=7">

  <link rel="shortcut icon" href="assets/images/favicon.ico?v=7">

  <script>

    (function() {

      const savedTheme = localStorage.getItem('campus_theme') || 'dark';

      document.documentElement.setAttribute('data-theme', savedTheme);

    })();

  </script>

</head>

<body>

<?php require_once __DIR__ . '/includes/mobile-nav.php'; ?>

<div class="app-container<?= $current_view === 'admin' ? ' app-admin-mode' : '' ?>">



  <!-- ======================================================== -->

  <!-- LEFT SIDEBAR                                             -->

  <!-- ======================================================== -->

  <aside class="sidebar-left">

    <div>

      <!-- Brand Logo -->

      <a href="index.php" class="brand-header">

        <div class="brand-logo-icon">

          <img src="assets/images/logo-dark-theme.png?v=9" alt="Quaddit Logo" class="brand-logo-img logo-for-dark">

          <img src="assets/images/logo-light-theme.png?v=9" alt="Quaddit Logo" class="brand-logo-img logo-for-light">

        </div>

        <div class="brand-text">Quad<span>dit</span></div>

      </a>



      <!-- Navigation Menu -->

      <ul class="nav-menu">

        <li class="nav-item <?= empty($current_view) && empty($selected_group_id) ? 'active' : '' ?>">

          <a href="index.php" id="nav-home">

            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">

              <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>

            </svg>

            <span>Home</span>

          </a>

        </li>

        <li class="nav-item <?= $current_view === 'groups' || $selected_group_id > 0 ? 'active' : '' ?>">

          <a href="index.php?view=groups" id="nav-groups">

            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">

              <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>

            </svg>

            <span>Groups</span>

          </a>

        </li>

        <?php if (is_admin()): ?>

          <li class="nav-item nav-item-admin <?= $current_view === 'admin' ? 'active' : '' ?>">

            <a href="index.php?view=admin" id="nav-admin" class="nav-link-admin">

              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">

                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>

              </svg>

              <span>Admin</span>

              <span class="sidebar-admin-pill">Staff</span>

            </a>

          </li>

        <?php endif; ?>

        <li class="nav-item">

          <a href="profile.php">

            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">

              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>

            </svg>

            <span>Profile</span>

          </a>

        </li>

        <li class="nav-item">

          <a href="javascript:void(0)" id="theme-toggle-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode">

            <span id="theme-icon-container">

              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="theme-icon-sun" style="display:none;">

                <circle cx="12" cy="12" r="5"></circle>

                <line x1="12" y1="1" x2="12" y2="3"></line>

                <line x1="12" y1="21" x2="12" y2="23"></line>

                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>

                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>

                <line x1="1" y1="12" x2="3" y2="12"></line>

                <line x1="21" y1="12" x2="23" y2="12"></line>

                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>

                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>

              </svg>

              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="theme-icon-moon">

                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>

              </svg>

            </span>

            <span id="theme-label-text">Theme: Dark</span>

          </a>

        </li>

      </ul>



      <!-- Primary Post Button -->

      <button class="btn-create-thread-sidebar" id="btn-open-create-modal" onclick="<?= $currentUser ? 'openCreateThreadModal()' : "window.location.href='register.php'" ?>">

        <span>Post</span>

      </button>

    </div>



    <!-- User Profile Bar -->
    <?php if ($currentUser): ?>
    <div class="user-profile-bar" id="user-profile-bar" onclick="toggleProfileDropdown(event)">

      <div class="user-profile-info">

        <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="user-avatar-img">

        <div class="user-names">

          <div style="display: flex; align-items: center; gap: 4px;">

            <span class="user-display-name"><?= e($currentUser['name']) ?></span>

            <?php if (is_admin()): ?>

              <span class="user-role-badge badge-admin">Admin</span>

            <?php elseif (is_faculty()): ?>

              <span class="user-role-badge badge-faculty">Faculty</span>

            <?php else: ?>

              <span class="user-role-badge badge-student">Student</span>

            <?php endif; ?>

          </div>

          <span class="user-handle"><?= !empty($currentUser['uid']) ? 'ID: ' . e($currentUser['uid']) : '@' . e($currentUser['username']) ?></span>

        </div>

      </div>

      <div class="user-dropdown-arrow">

        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

          <polyline points="6 9 12 15 18 9"></polyline>

        </svg>

      </div>



      <div class="profile-dropdown-menu" id="profile-dropdown-menu">

        <div style="padding: 8px 12px; font-size: 13px; font-weight: 700; color: var(--text-main);">

          <?= e($currentUser['name']) ?>

          <span style="font-size: 12px; font-weight: 500; color: var(--text-dim); display: block;"><?= !empty($currentUser['uid']) ? 'ID: ' . e($currentUser['uid']) : '@' . e($currentUser['username']) ?></span>

        </div>

        <?php if (is_admin()): ?>

          <a href="index.php?view=admin" style="color: #38bdf8; display: flex; align-items: center; gap: 8px;">

            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>

            Admin Dashboard

          </a>

        <?php endif; ?>

        <a href="profile.php" style="color: var(--text-main); display: flex; align-items: center; gap: 8px;">

          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>

          View Profile

        </a>

        <a href="index.php?view=groups" style="color: var(--text-main); display: flex; align-items: center; gap: 8px;">

          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3z"/></svg>

          Browse Groups

        </a>

        <div class="divider"></div>

        <a href="logout.php" style="color: var(--danger-red);">

          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>

            <polyline points="16 17 21 12 16 7"></polyline>

            <line x1="21" y1="12" x2="9" y2="12"></line>

          </svg>

          Sign Out

        </a>

      </div>

    </div>
    <?php else: ?>
    <div class="guest-sidebar-auth" style="padding: 14px; border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-card); display: flex; flex-direction: column; gap: 8px; margin-top: 12px;">
      <div style="font-size: 13px; font-weight: 700; color: var(--text-main);">Join Quaddit</div>
      <div style="font-size: 12px; color: var(--text-muted); line-height: 1.4;">Register or sign in to post, reply, and join groups.</div>
      <a href="register.php" class="btn-join-group" style="text-align: center; justify-content: center; padding: 8px 12px; font-size: 13px; text-decoration: none;">Create Account</a>
      <a href="login.php" style="text-align: center; font-size: 12.5px; font-weight: 600; color: var(--primary-blue); text-decoration: none; padding: 4px;">Sign In</a>
    </div>
    <?php endif; ?>

  </aside>





  

  <!-- ======================================================== -->

  <!-- MAIN FEED COLUMN                                         -->

  <!-- ======================================================== -->

  <main class="main-feed-column">

    <!-- Search Bar -->

    <div class="search-container">

      <form action="index.php" method="GET" class="search-box">

        <?php if (!empty($selected_group_id)): ?>

          <input type="hidden" name="group" value="<?= $selected_group_id ?>">

        <?php elseif (!empty($current_view)): ?>

          <input type="hidden" name="view" value="<?= e($current_view) ?>">

        <?php endif; ?>

        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

          <circle cx="11" cy="11" r="8"></circle>

          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>

        </svg>

        <input type="text" name="q" placeholder="<?= $current_view === 'groups' ? 'Search groups...' : 'Search discussions...' ?>" value="<?= e($search_query) ?>" autocomplete="off">

      </form>

    </div>



    <!-- Alert / Toast Messages -->

    <?php

      $msgType = $_GET['msg'] ?? '';

      $errType = $_GET['error'] ?? '';

    ?>

    <?php if ($msgType === 'joined'): ?>

      <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #6ee7b7; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 8px;">

        <span>✓</span> You have successfully joined the academic group! You can now publish and participate in discussions.

      </div>

    <?php elseif ($msgType === 'left'): ?>

      <div style="background: rgba(148, 163, 184, 0.15); border: 1px solid rgba(148, 163, 184, 0.3); color: var(--text-main); padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600;">

        You have left the group.

      </div>

    <?php elseif ($msgType === 'group_created'): ?>

      <div style="background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600;">

        ✓ New academic group created successfully!

      </div>

    <?php elseif ($msgType === 'group_updated'): ?>

      <div style="background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600;">

        ✓ Group details updated successfully.

      </div>

    <?php elseif ($msgType === 'group_deleted'): ?>

      <div style="background: rgba(244, 33, 46, 0.15); border: 1px solid rgba(244, 33, 46, 0.35); color: #fca5a5; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600;">

        Group and all its discussions were deleted.

      </div>

    <?php elseif ($msgType === 'status_updated'): ?>

      <div style="background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600;">

        ✓ Group status updated successfully.

      </div>

    <?php elseif ($errType === 'must_join_group'): ?>

      <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.35); color: #fbbf24; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600;">

        ⚠️ Permission denied: You must join this academic group before you can publish a discussion.

      </div>

    <?php elseif ($errType === 'group_restricted'): ?>

      <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.35); color: #fbbf24; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600;">

        ⚠️ Group Restricted: New discussions are currently paused by the administrator.

      </div>

    <?php elseif ($errType === 'select_group'): ?>

      <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600;">

        ⚠️ Please select a group you belong to before publishing your post.

      </div>

    <?php elseif ($errType === 'unauthorized'): ?>

      <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; font-weight: 600;">

        ⚠️ Access Denied: You do not have administrator permissions for that action.

      </div>

    <?php endif; ?>



    <!-- VIEW 1: ADMINISTRATOR CONSOLE VIEW -->

    <?php if ($current_view === 'admin'): ?>

      <div class="admin-dashboard-container">

        <!-- Admin Dashboard Header -->
        <div class="admin-dashboard-header">
          <div class="admin-header-left">
            <div class="admin-header-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </svg>
            </div>
            <div>
              <div class="admin-title-row">
                <h1 class="admin-page-title">Admin Dashboard</h1>
                <span class="auth-admin-badge">Admin</span>
              </div>
              <p class="admin-page-subtitle">
                Central place to oversee Quaddit activity, manage discussion groups, and moderate content.
              </p>
            </div>
          </div>

          <div>
            <button type="button" class="btn-admin-primary" onclick="openCreateGroupModal()" title="Create a new discussion group on Quaddit">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
              <span>Create Group</span>
            </button>
          </div>
        </div>

        <!-- Admin Section Sub-Navigation -->
        <nav class="admin-subnav" aria-label="Admin sections">
          <a href="#admin-overview" class="admin-subnav-link active">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Overview</span>
          </a>
          <a href="#admin-groups-section" class="admin-subnav-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Groups</span>
            <span class="admin-subnav-count"><?= count($adminGroups ?? []) ?></span>
          </a>
          <a href="#admin-moderation-section" class="admin-subnav-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <span>Content Moderation</span>
            <span class="admin-subnav-count"><?= count($adminRecentPosts ?? []) ?></span>
          </a>
        </nav>

        <!-- Overview Section -->
        <section id="admin-overview" class="admin-overview-wrap">
          <div class="admin-block-heading">
            <h2 class="admin-block-title">Overview</h2>
            <p class="admin-block-subtitle">Summary of platform groups, discussions, and registered accounts.</p>
          </div>

          <div class="admin-stats-grid">
            <div class="admin-stat-card">
              <div class="admin-stat-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>Total Groups</span>
              </div>
              <div class="admin-stat-num"><?= (int)($stats['total_groups'] ?? 0) ?></div>
              <div class="admin-stat-sub">All created discussion hubs</div>
            </div>

            <div class="admin-stat-card">
              <div class="admin-stat-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Active Groups</span>
              </div>
              <div class="admin-stat-num"><?= (int)($stats['active_groups'] ?? 0) ?></div>
              <div class="admin-stat-sub">Currently open for posting</div>
            </div>

            <div class="admin-stat-card">
              <div class="admin-stat-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span>Discussions</span>
              </div>
              <div class="admin-stat-num"><?= (int)($stats['total_posts'] ?? 0) ?></div>
              <div class="admin-stat-sub">Total posts across groups</div>
            </div>

            <div class="admin-stat-card">
              <div class="admin-stat-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>Students</span>
              </div>
              <div class="admin-stat-num"><?= (int)($stats['total_students'] ?? 0) ?></div>
              <div class="admin-stat-sub">Registered student accounts</div>
            </div>

            <div class="admin-stat-card">
              <div class="admin-stat-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                <span>Faculty</span>
              </div>
              <div class="admin-stat-num"><?= (int)($stats['total_faculty'] ?? 0) ?></div>
              <div class="admin-stat-sub">Registered faculty accounts</div>
            </div>
          </div>
        </section>

        <!-- Group Management Section -->
        <section class="admin-section" id="admin-groups-section">
          <div class="admin-section-header">
            <div class="admin-section-title-wrap">
              <h2 class="admin-section-title">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Group Management</span>
              </h2>
              <span class="admin-section-desc">Create, view, edit and manage the discussion groups available on Quaddit.</span>
            </div>

            <div class="admin-toolbar">
              <input type="search" id="admin-groups-search" class="admin-search-input" placeholder="Search groups by name..." aria-label="Search groups by name">
              <div class="admin-status-dropdown" id="admin-status-dropdown">
                <input type="hidden" id="admin-groups-status-filter" value="all">
                <button type="button" class="admin-status-trigger" id="admin-status-trigger" aria-haspopup="listbox" aria-expanded="false" aria-label="Filter groups by status">
                  <span class="admin-status-dot dot-all" id="admin-status-trigger-dot"></span>
                  <span class="admin-status-label" id="admin-status-trigger-label">All Statuses</span>
                  <svg class="admin-status-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </button>
                <div class="admin-status-menu" id="admin-status-menu" role="listbox" aria-label="Group status filter">
                  <button type="button" class="admin-status-option active" role="option" aria-selected="true" data-value="all" data-label="All Statuses" data-dot="dot-all">
                    <span class="admin-status-dot dot-all"></span>
                    <span class="admin-status-option-text">All Statuses</span>
                    <svg class="admin-status-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  </button>
                  <button type="button" class="admin-status-option" role="option" aria-selected="false" data-value="active" data-label="Active Only" data-dot="dot-active">
                    <span class="admin-status-dot dot-active"></span>
                    <span class="admin-status-option-text">Active Only</span>
                    <svg class="admin-status-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  </button>
                  <button type="button" class="admin-status-option" role="option" aria-selected="false" data-value="restricted" data-label="Restricted Only" data-dot="dot-restricted">
                    <span class="admin-status-dot dot-restricted"></span>
                    <span class="admin-status-option-text">Restricted Only</span>
                    <svg class="admin-status-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  </button>
                </div>
              </div>
              <button type="button" class="btn-admin-action" onclick="openCreateGroupModal()" title="Create a new discussion group">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>New Group</span>
              </button>
            </div>
          </div>

          <?php if (!empty($adminGroups)): ?>
            <div class="admin-group-list" id="admin-groups-table">
              <?php foreach ($adminGroups as $ag): ?>
                <div class="admin-group-row" data-status="<?= e($ag['status']) ?>">
                  <div class="admin-group-main">
                    <div class="admin-group-thumb">
                      <?php if (!empty($ag['image_url'])): ?>
                        <img src="<?= e($ag['image_url']) ?>" alt="<?= e($ag['name']) ?>" class="widget-group-img">
                      <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                          <circle cx="9" cy="7" r="4"></circle>
                          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                      <?php endif; ?>
                    </div>

                    <div class="admin-group-info">
                      <div class="admin-group-title-line">
                        <a href="index.php?group=<?= (int)$ag['id'] ?>" class="admin-group-name">
                          <?= e($ag['name']) ?>
                        </a>
                        <?php if ($ag['status'] === 'active'): ?>
                          <span class="group-status-badge status-active" title="Members can create new discussions in this group">Active</span>
                        <?php else: ?>
                          <span class="group-status-badge status-restricted" title="New discussions are paused; existing posts remain readable">Restricted</span>
                        <?php endif; ?>
                      </div>
                      <p class="admin-group-desc"><?= e($ag['description'] ?: 'No description provided.') ?></p>
                      <div class="admin-group-meta">
                        <span><strong><?= (int)$ag['member_count'] ?></strong> <?= (int)$ag['member_count'] === 1 ? 'Member' : 'Members' ?></span>
                        <span>·</span>
                        <span><strong><?= (int)$ag['post_count'] ?></strong> <?= (int)$ag['post_count'] === 1 ? 'Discussion' : 'Discussions' ?></span>
                        <span>·</span>
                        <span>Created <?= date('M j, Y', strtotime($ag['created_at'])) ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="admin-group-actions">
                    <button type="button" class="btn-admin-action" onclick="openEditGroupModal(<?= (int)$ag['id'] ?>, '<?= e(addslashes($ag['name'])) ?>', '<?= e(addslashes($ag['description'] ?? '')) ?>', '<?= e($ag['status']) ?>')" title="Modify group name, description, image, or status">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                      </svg>
                      <span>Edit</span>
                    </button>

                    <?php if ($ag['status'] === 'active'): ?>
                      <a href="index.php?action=toggle_group_status&group_id=<?= (int)$ag['id'] ?>&status=restricted&redirect=<?= urlencode('index.php?view=admin') ?>" class="btn-admin-action btn-admin-warn" title="Restrict group to pause new discussions">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Restrict</span>
                      </a>
                    <?php else: ?>
                      <a href="index.php?action=toggle_group_status&group_id=<?= (int)$ag['id'] ?>&status=active&redirect=<?= urlencode('index.php?view=admin') ?>" class="btn-admin-action btn-admin-ok" title="Activate group to allow new discussions">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                          <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
                        </svg>
                        <span>Activate</span>
                      </a>
                    <?php endif; ?>

                    <a href="index.php?action=delete_group&group_id=<?= (int)$ag['id'] ?>" class="btn-admin-action btn-admin-danger" onclick="return confirm('WARNING: Are you sure you want to delete this group? All discussions and memberships inside this group will be deleted.')" title="Permanently delete this group and all its discussions">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                      </svg>
                      <span>Delete</span>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="admin-empty-state">No groups created yet. Use "Create Group" to add the first discussion hub.</div>
          <?php endif; ?>
        </section>

        <!-- Content Moderation Section -->
        <section class="admin-section" id="admin-moderation-section">
          <div class="admin-section-header">
            <div class="admin-section-title-wrap">
              <h2 class="admin-section-title">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="16" y1="13" x2="8" y2="13"></line>
                  <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <span>Content Moderation</span>
              </h2>
              <span class="admin-section-desc">Review recent discussions across all groups and remove inappropriate content.</span>
            </div>

            <div class="admin-toolbar">
              <input type="search" id="admin-posts-search" class="admin-search-input" placeholder="Search by title, group, or author..." aria-label="Search recent discussions">
            </div>
          </div>

          <?php if (!empty($adminRecentPosts)): ?>
            <div style="overflow-x: auto;">
              <table class="admin-table" id="admin-posts-table">
                <thead>
                  <tr>
                    <th>Discussion</th>
                    <th>Group</th>
                    <th>Author</th>
                    <th>Replies</th>
                    <th>Posted</th>
                    <th style="text-align: right;">Moderation</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($adminRecentPosts as $rp): ?>
                    <tr>
                      <td style="max-width: 320px;">
                        <a href="index.php?group=<?= (int)$rp['group_id'] ?>&post=<?= (int)$rp['id'] ?>#post-<?= (int)$rp['id'] ?>" style="font-weight: 600; color: var(--text-main); text-decoration: none;">
                          <?= e($rp['title']) ?>
                        </a>
                      </td>
                      <td>
                        <a href="index.php?group=<?= (int)$rp['group_id'] ?>" class="post-group-badge" style="font-size: 11.5px;">
                          <?= e($rp['group_name']) ?>
                        </a>
                      </td>
                      <td>
                        <span style="font-weight: 600;"><?= e($rp['author_name']) ?></span>
                        <span style="font-size: 11.5px; color: var(--text-dim); display: block;">@<?= e($rp['author_username']) ?></span>
                      </td>
                      <td style="font-variant-numeric: tabular-nums; font-weight: 600;"><?= (int)$rp['reply_count'] ?></td>
                      <td style="font-size: 12px; color: var(--text-dim); white-space: nowrap;"><?= time_ago($rp['created_at']) ?></td>
                      <td style="text-align: right;">
                        <a href="index.php?action=delete_post&id=<?= (int)$rp['id'] ?>&redirect=<?= urlencode('index.php?view=admin&msg=deleted') ?>" class="btn-admin-action btn-admin-danger" onclick="return confirm('Admin Moderation: Delete this discussion?')" title="Permanently remove this discussion from the platform">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                          </svg>
                          <span>Remove</span>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="admin-empty-state">No posts available for moderation.</div>
          <?php endif; ?>
        </section>

      </div>



    <!-- VIEW 2: GROUPS DIRECTORY VIEW -->

    <?php elseif ($current_view === 'groups'): ?>

      <div class="groups-directory-container">

        <div class="groups-directory-header">

          <div>

            <h1 class="groups-directory-title">
              Groups
              <?php if (!empty($allGroups)): ?>
                <span class="groups-count-pill"><?= count($allGroups) ?></span>
              <?php endif; ?>
            </h1>

            <p style="font-size: 13.5px; color: var(--text-muted); margin: 4px 0 0 0;">

              Browse subject, semester, and department discussion hubs. Join groups to participate.

            </p>

          </div>

          <?php if (is_admin()): ?>

            <button type="button" class="btn-admin-primary" onclick="openCreateGroupModal()">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
              <span>Create Group</span>
            </button>

          <?php endif; ?>

        </div>

        <?php if (!empty($allGroups)): ?>

          <div class="groups-toolbar">
            <input type="search" id="groups-directory-search" class="groups-search-input" placeholder="Search groups by name or topic..." aria-label="Search groups">
          </div>

          <div class="groups-grid" id="groups-directory-grid">

            <?php foreach ($allGroups as $g): ?>

              <?php

                $isMem = ((int)$g['is_member'] > 0);

                $isRestricted = ($g['status'] === 'restricted');

              ?>

              <div class="group-card">

                <?php if ($isRestricted): ?>
                  <span class="group-status-badge status-restricted">Restricted</span>
                <?php else: ?>
                  <span class="group-status-badge status-active">Active</span>
                <?php endif; ?>

                <div class="group-card-body">

                  <a href="index.php?group=<?= (int)$g['id'] ?>" class="group-card-icon" aria-label="<?= e($g['name']) ?>">

                    <?php if (!empty($g['image_url'])): ?>

                      <img src="<?= e($g['image_url']) ?>" alt="<?= e($g['name']) ?>">

                    <?php else: ?>

                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                      </svg>

                    <?php endif; ?>

                  </a>

                  <a href="index.php?group=<?= (int)$g['id'] ?>" class="group-card-title" title="<?= e($g['name']) ?>">

                    <?= e($g['name']) ?>

                  </a>

                  <p class="group-card-desc">

                    <?= e($g['description'] ?: 'Academic discussions and peer collaboration.') ?>

                  </p>

                  <div class="group-card-meta">

                    <span><strong><?= (int)$g['member_count'] ?></strong> <?= (int)$g['member_count'] === 1 ? 'Member' : 'Members' ?></span>

                    <span>·</span>

                    <span><strong><?= (int)$g['post_count'] ?></strong> <?= (int)$g['post_count'] === 1 ? 'Discussion' : 'Discussions' ?></span>

                  </div>

                </div>

                <div class="group-card-actions">

                  <a href="index.php?group=<?= (int)$g['id'] ?>" class="btn-view-group" title="View Discussions">

                    <span>View Discussions</span>

                  </a>

                  <?php if ($isMem): ?>

                    <a href="index.php?action=leave_group&group_id=<?= (int)$g['id'] ?>&redirect=<?= urlencode('index.php?view=groups') ?>" class="btn-leave-group" onclick="return confirm('Leave this group?')">

                      Leave

                    </a>

                  <?php else: ?>

                    <a href="index.php?action=join_group&group_id=<?= (int)$g['id'] ?>&redirect=<?= urlencode('index.php?view=groups') ?>" class="btn-join-group">

                      Join

                    </a>

                  <?php endif; ?>

                </div>

              </div>

            <?php endforeach; ?>

          </div>

        <?php else: ?>

          <div class="empty-state-card" style="text-align: center; padding: 50px 24px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; margin-top: 10px;">

            <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.25); display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 16px auto;">
              👥
            </div>

            <h3 style="font-size: 17px; font-weight: 700; color: var(--text-main); margin: 0 0 6px 0;">No Academic Groups Found</h3>

            <p style="color: var(--text-muted); font-size: 13.5px; margin: 0 0 18px 0; max-width: 380px; margin-left: auto; margin-right: auto;">
              <?= !empty($search_query) ? 'No academic groups matched your search query.' : 'No academic discussion groups have been created yet.' ?>
            </p>

            <?php if (is_admin()): ?>
              <button type="button" class="btn-join-group" onclick="openCreateGroupModal()" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                + Create Academic Group
              </button>
            <?php endif; ?>

          </div>

        <?php endif; ?>

      </div>




    <!-- VIEW 3: REGULAR FEED VIEW (HOME FEED OR SPECIFIC GROUP) -->

    <?php else: ?>



      <?php if ($single_post_id > 0): ?>

        <!-- Single Discussion View Top Bar -->

        <div class="single-discussion-header">

          <a href="<?= $selected_group_id > 0 ? 'index.php?group=' . $selected_group_id : 'index.php' ?>" class="btn-back-discussion">

            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">

              <line x1="19" y1="12" x2="5" y2="12"></line>

              <polyline points="12 19 5 12 12 5"></polyline>

            </svg>

            <span><?= $selected_group_id > 0 ? 'Back to ' . e($currentGroup['name'] ?? 'Group') : 'Back to All Discussions' ?></span>

          </a>

          <span class="single-discussion-label">Discussion</span>

        </div>



      <?php elseif ($selected_group_id > 0 && $currentGroup): ?>

        <!-- GROUP HEADER BANNER -->

        <div class="group-header-banner">

          <div style="margin-bottom: 12px;">

            <a href="index.php?view=groups" style="font-size: 12.5px; font-weight: 700; color: var(--primary-blue); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">

              &larr; Browse All Groups

            </a>

          </div>



          <div class="group-header-top">

            <div class="group-title-area">

              <div class="group-icon-wrap">

                <?php if (!empty($currentGroup['image_url'])): ?>

                  <img src="<?= e($currentGroup['image_url']) ?>" alt="<?= e($currentGroup['name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 14px;">

                <?php else: ?>

                  👥

                <?php endif; ?>

              </div>

              <div>

                <div class="group-name-row">

                  <h1 class="group-header-title"><?= e($currentGroup['name']) ?></h1>

                  <?php if ($currentGroup['status'] === 'active'): ?>

                    <span class="group-status-badge status-active">Active</span>

                  <?php else: ?>

                    <span class="group-status-badge status-restricted">Restricted</span>

                  <?php endif; ?>

                </div>

                <div class="group-meta-stats" style="margin-top: 6px;">

                  <span class="group-meta-item">

                    <span class="group-meta-num"><?= (int)$currentGroup['member_count'] ?></span> <?= (int)$currentGroup['member_count'] === 1 ? 'member' : 'members' ?>

                  </span>

                  <span>·</span>

                  <span class="group-meta-item">

                    <span class="group-meta-num"><?= (int)$currentGroup['post_count'] ?></span> discussions

                  </span>

                  <span>·</span>

                  <span class="group-meta-item" style="color: var(--text-dim); font-size: 12px;">

                    Created <?= date('M Y', strtotime($currentGroup['created_at'])) ?>

                  </span>

                </div>

              </div>

            </div>



            <div class="group-actions-row">

              <?php if ((int)$currentGroup['is_member'] > 0): ?>

                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 13px; font-weight: 700; color: #34d399; background: rgba(16, 185, 129, 0.12); padding: 6px 14px; border-radius: 9999px;">

                  ✓ Joined

                </span>

                <a href="index.php?action=leave_group&group_id=<?= $selected_group_id ?>&redirect=<?= urlencode("index.php?group=$selected_group_id") ?>" class="btn-leave-group" onclick="return confirm('Leave this group?')">

                  Leave Group

                </a>

              <?php else: ?>

                <a href="index.php?action=join_group&group_id=<?= $selected_group_id ?>&redirect=<?= urlencode("index.php?group=$selected_group_id") ?>" class="btn-join-group">

                  + Join Group

                </a>

              <?php endif; ?>

            </div>

          </div>



          <?php if (!empty($currentGroup['description'])): ?>

            <p class="group-header-desc"><?= e($currentGroup['description']) ?></p>

          <?php endif; ?>



          <?php if ($currentGroup['status'] === 'restricted'): ?>

            <div class="group-restricted-alert">

              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>

                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>

              </svg>

              <span><strong>Group Restricted:</strong> New discussions cannot be published. Existing posts remain accessible for study and review.</span>

            </div>

          <?php endif; ?>



          <!-- Admin Quick Controls Toolbar -->

          <?php if (is_admin()): ?>

            <div class="group-admin-toolbar">

              <span style="font-size: 11px; font-weight: 700; color: var(--primary-blue); text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 5px; margin-right: 6px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <span>Admin Controls</span>
              </span>

              <button type="button" class="btn-admin-action" onclick="openEditGroupModal(<?= $selected_group_id ?>, '<?= e(addslashes($currentGroup['name'])) ?>', '<?= e(addslashes($currentGroup['description'] ?? '')) ?>', '<?= e($currentGroup['status']) ?>')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                <span>Edit Group</span>
              </button>

              <?php if ($currentGroup['status'] === 'active'): ?>

                <a href="index.php?action=toggle_group_status&group_id=<?= $selected_group_id ?>&status=restricted&redirect=<?= urlencode("index.php?group=$selected_group_id") ?>" class="btn-admin-action btn-admin-warn">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                  <span>Restrict Group</span>
                </a>

              <?php else: ?>

                <a href="index.php?action=toggle_group_status&group_id=<?= $selected_group_id ?>&status=active&redirect=<?= urlencode("index.php?group=$selected_group_id") ?>" class="btn-admin-action btn-admin-ok">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>
                  <span>Activate Group</span>
                </a>

              <?php endif; ?>

              <a href="index.php?action=delete_group&group_id=<?= $selected_group_id ?>" class="btn-admin-action btn-admin-danger" onclick="return confirm('Delete this group and all its discussions? This cannot be undone.')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                <span>Delete Group</span>
              </a>

            </div>

          <?php endif; ?>

        </div>



        <!-- Composer for Group View -->

        <?php if ($currentUser && (int)$currentGroup['is_member'] > 0 && $currentGroup['status'] === 'active'): ?>

          <div class="x-quick-composer" onclick="openCreateThreadModal(<?= $selected_group_id ?>)">

            <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="composer-avatar">

            <div class="composer-content-box">

              <div class="composer-placeholder">Start a discussion in <?= e($currentGroup['name']) ?>...</div>

              <div class="composer-bottom-bar">

                <div class="composer-tools">

                  <button type="button" class="composer-tool-btn" title="Add Photos">

                    <svg viewBox="0 0 24 24" fill="currentColor">

                      <path d="M3 5.5C3 4.119 4.119 3 5.5 3h13C19.881 3 21 4.119 21 5.5v13c0 1.381-1.119 2.5-2.5 2.5h-13C4.119 21 3 19.881 3 18.5v-13zM5.5 5c-.276 0-.5.224-.5.5v9.086l3.293-3.293a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414l-1.293 1.293H18.5c.276 0 .5-.224.5-.5V5.5c0-.276-.224-.5-.5-.5h-13zM15 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>

                    </svg>

                  </button>

                </div>

                <button type="button" class="composer-post-btn">Post to Group</button>

              </div>

            </div>

          </div>

        <?php elseif ((int)$currentGroup['is_member'] <= 0): ?>

          <div style="background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: 16px; padding: 20px; text-align: center; margin-bottom: 20px;">

            <p style="font-size: 14.5px; font-weight: 600; color: var(--text-main); margin: 0 0 8px 0;">

              Want to ask questions or share insights in this group?

            </p>

            <p style="font-size: 13px; color: var(--text-muted); margin: 0 0 14px 0;">

              You must join <strong><?= e($currentGroup['name']) ?></strong> before publishing discussions.

            </p>

            <?php if ($currentUser): ?>

              <a href="index.php?action=join_group&group_id=<?= $selected_group_id ?>&redirect=<?= urlencode("index.php?group=$selected_group_id") ?>" class="btn-join-group">

                Join <?= e($currentGroup['name']) ?>

              </a>

            <?php else: ?>

              <a href="register.php" class="btn-join-group">

                Register to Join &rarr;

              </a>

            <?php endif; ?>

          </div>

        <?php endif; ?>



      <?php else: ?>

        <!-- HOME FEED COMPOSER -->

        <?php if ($currentUser && !empty($userJoinedGroups)): ?>

          <div class="x-quick-composer" onclick="openCreateThreadModal()">

            <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="composer-avatar">

            <div class="composer-content-box">

              <div class="composer-placeholder">Share an idea or ask a question in your groups...</div>

              <div class="composer-bottom-bar">

                <div class="composer-tools">

                  <button type="button" class="composer-tool-btn" title="Add Photos">

                    <svg viewBox="0 0 24 24" fill="currentColor">

                      <path d="M3 5.5C3 4.119 4.119 3 5.5 3h13C19.881 3 21 4.119 21 5.5v13c0 1.381-1.119 2.5-2.5 2.5h-13C4.119 21 3 19.881 3 18.5v-13zM5.5 5c-.276 0-.5.224-.5.5v9.086l3.293-3.293a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414l-1.293 1.293H18.5c.276 0 .5-.224.5-.5V5.5c0-.276-.224-.5-.5-.5h-13zM15 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>

                    </svg>

                  </button>

                </div>

                <button type="button" class="composer-post-btn">Post</button>

              </div>

            </div>

          </div>

        <?php elseif ($currentUser): ?>

          <div style="background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: 16px; padding: 18px 22px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">

            <div>

              <div style="font-weight: 700; font-size: 14.5px; color: var(--text-main);">Welcome to Quaddit Groups!</div>

              <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">Join an academic group to publish your discussions and connect with classmates.</div>

            </div>

            <a href="index.php?view=groups" class="btn-join-group" style="padding: 8px 16px; font-size: 13px;">Explore Groups &rarr;</a>

          </div>

        <?php else: ?>

          <div style="background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: 16px; padding: 18px 22px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">

            <div>

              <div style="font-weight: 700; font-size: 14.5px; color: var(--text-main);">Welcome to Quaddit!</div>

              <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">Browse public discussions freely. Register or sign in to create posts, reply, and join groups.</div>

            </div>

            <a href="register.php" class="btn-join-group" style="padding: 8px 16px; font-size: 13px;">Register to Post &rarr;</a>

          </div>

        <?php endif; ?>



      <?php endif; ?>



      <!-- Discussion Posts Feed -->

      <div class="posts-feed" id="posts-feed-container">

        <?php if (!empty($posts)): ?>

          <?php foreach ($posts as $post): ?>

            <?php

              $postId    = (int)$post['id'];

              $isAuthor  = ($currentUser && (int)$post['user_id'] === (int)$currentUser['id']);

              $images    = $postImages[$postId] ?? [];

              $imgCount  = count($images);

              $replies   = $repliesByPost[$postId] ?? [];

              $hasReplied = count($replies) > 0;

            ?>

            <?php
              $isGroupFeed = ($selected_group_id > 0);
              $canViewAuthorProfile = (($post['author_role'] ?? '') !== 'admin' || is_admin());
              $isPostGroupRestricted = (($post['group_status'] ?? 'active') === 'restricted');
              $canModifyPost = (!$isPostGroupRestricted || is_admin());
            ?>
            <article class="post-card post-card-reddit" id="post-<?= $postId ?>" data-post-id="<?= $postId ?>" onclick="handlePostCardClick(event, <?= $postId ?>, <?= (int)$post['group_id'] ?>)" style="cursor: pointer;">

              <div class="post-main-col">

                <?php if (!$isGroupFeed): ?>
                  <!-- Homepage / Feed: Group Name (Row 1) -> Username -> Timestamp (Row 2) -->
                  <div class="post-header post-header-community-row">
                    <a href="index.php?group=<?= (int)$post['group_id'] ?>" class="post-community-link" onclick="event.stopPropagation()" title="View <?= e($post['group_name']) ?>">
                      <?php if (!empty($post['group_image'])): ?>
                        <img src="<?= e($post['group_image']) ?>" alt="<?= e($post['group_name']) ?>" class="post-community-avatar">
                      <?php else: ?>
                        <span class="post-community-avatar-fallback" aria-hidden="true">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                          </svg>
                        </span>
                      <?php endif; ?>
                      <span class="post-community-name"><?= e($post['group_name']) ?></span>
                    </a>
                <?php else: ?>
                  <!-- Inside Individual Group Page: Username -> Timestamp (No repeated Group Name) -->
                  <div class="post-header post-header-group-context">
                    <div class="post-author-meta">
                      <?php if ($canViewAuthorProfile): ?>
                        <a href="profile.php?id=<?= (int)$post['user_id'] ?>" class="post-group-author-link" onclick="event.stopPropagation()">
                      <?php else: ?>
                        <span class="post-group-author-link">
                      <?php endif; ?>
                          <img src="<?= e($post['author_avatar']) ?>" alt="<?= e($post['author_name']) ?>" class="post-group-author-avatar">
                          <span class="author-name"><?= e($post['author_name']) ?></span>
                          <svg class="verified-badge" viewBox="0 0 24 24" width="16" height="16" fill="#1d9bf0" style="display: inline-block; vertical-align: middle;">
                            <path d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.79-4-4-4-.495 0-.965.084-1.4.238C14.55 2.475 13.18 1.6 11.6 1.6c-1.58 0-2.95.875-3.6 2.148-.435-.154-.905-.238-1.4-.238-2.21 0-4 1.79-4 4 0 .495.084.965.238 1.4C1.575 10.45.7 11.82.7 13.4c0 1.58.875 2.95 2.148 3.6-.154.435-.238.905-.238 1.4 0 2.21 1.79 4 4 4 .495 0 .965-.084 1.4-.238.65 1.273 2.02 2.148 3.6 2.148 1.58 0 2.95-.875 3.6-2.148.435.154.905.238 1.4.238 2.21 0 4-1.79 4-4 0-.495-.084-.965-.238-1.4 1.273-.65 2.148-2.02 2.148-3.6zm-12.87 4.16l-3.29-3.29 1.41-1.41 1.88 1.88 5.18-5.18 1.41 1.41-6.59 6.59z"/>
                          </svg>
                          <span class="user-handle">@<?= e($post['author_username']) ?></span>
                      <?php if ($canViewAuthorProfile): ?>
                        </a>
                      <?php else: ?>
                        </span>
                      <?php endif; ?>
                      <span class="post-dot">·</span>
                      <span class="post-timestamp"><?= time_ago($post['created_at']) ?></span>
                      <?php if (!empty($post['updated_at'])): ?>
                        <span class="post-edited-tag">(edited)</span>
                      <?php endif; ?>
                    </div>
                <?php endif; ?>

                    <div class="post-header-right">
                      <!-- 3-Dots Action Menu (Author in active group or Admin) -->
                      <?php if (($isAuthor && $canModifyPost) || is_admin()): ?>
                      <div class="post-actions-menu" onclick="event.stopPropagation()">
                        <button type="button" class="btn-three-dots" title="More" onclick="togglePostMenu(event, 'post-menu-<?= $postId ?>')">
                          <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                            <path d="M3 12c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm9 2c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm7 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                          </svg>
                        </button>
                        <div class="three-dots-dropdown" id="post-menu-<?= $postId ?>">
                          <?php if ($isAuthor && $canModifyPost): ?>
                            <button type="button" 
                                    data-id="<?= $postId ?>" 
                                    data-title="<?= e($post['title']) ?>" 
                                    data-content="<?= e($post['content']) ?>" 
                                    data-images='<?= htmlspecialchars(json_encode($postImagesData[$postId] ?? []), ENT_QUOTES, 'UTF-8') ?>'
                                    onclick="openEditPostModal(this)">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                              </svg>
                              Edit Discussion
                            </button>
                            <button type="button" class="btn-delete" onclick="confirmDeletePost(<?= $postId ?>)">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                              </svg>
                              Delete Discussion
                            </button>
                          <?php else: ?>
                            <!-- Admin Moderation Button -->
                            <button type="button" class="btn-delete" onclick="confirmDeletePost(<?= $postId ?>)" style="color: var(--danger-red);">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                              </svg>
                              Moderate (Delete)
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>
                      <?php endif; ?>
                    </div>
                  </div>

                <?php if (!$isGroupFeed): ?>
                  <div class="post-header-author-row">
                    <?php if ($canViewAuthorProfile): ?>
                      <a href="profile.php?id=<?= (int)$post['user_id'] ?>" class="post-inline-author-link" onclick="event.stopPropagation()">
                    <?php else: ?>
                      <span class="post-inline-author-link">
                    <?php endif; ?>
                        <img src="<?= e($post['author_avatar']) ?>" alt="<?= e($post['author_name']) ?>" class="post-inline-avatar">
                        <span class="author-name-inline"><?= e($post['author_name']) ?></span>
                        <svg class="verified-badge" viewBox="0 0 24 24" width="14" height="14" fill="#1d9bf0" style="display: inline-block; vertical-align: middle;">
                          <path d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.79-4-4-4-.495 0-.965.084-1.4.238C14.55 2.475 13.18 1.6 11.6 1.6c-1.58 0-2.95.875-3.6 2.148-.435-.154-.905-.238-1.4-.238-2.21 0-4 1.79-4 4 0 .495.084.965.238 1.4C1.575 10.45.7 11.82.7 13.4c0 1.58.875 2.95 2.148 3.6-.154.435-.238.905-.238 1.4 0 2.21 1.79 4 4 4 .495 0 .965-.084 1.4-.238.65 1.273 2.02 2.148 3.6 2.148 1.58 0 2.95-.875 3.6-2.148.435.154.905.238 1.4.238 2.21 0 4-1.79 4-4 0-.495-.084-.965-.238-1.4 1.273-.65 2.148-2.02 2.148-3.6zm-12.87 4.16l-3.29-3.29 1.41-1.41 1.88 1.88 5.18-5.18 1.41 1.41-6.59 6.59z"/>
                        </svg>
                        <span class="user-handle-inline">@<?= e($post['author_username']) ?></span>
                    <?php if ($canViewAuthorProfile): ?>
                      </a>
                    <?php else: ?>
                      </span>
                    <?php endif; ?>
                    <span class="post-dot">·</span>
                    <span class="post-timestamp"><?= time_ago($post['created_at']) ?></span>
                    <?php if (!empty($post['updated_at'])): ?>
                      <span class="post-edited-tag">(edited)</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>



                <!-- Post Title -->

                <h2 class="post-title" style="margin: 4px 0 8px 0;">

                  <a href="index.php?group=<?= (int)$post['group_id'] ?>&post=<?= $postId ?>#post-<?= $postId ?>" class="post-title-link" style="color: inherit; text-decoration: none;">

                    <?= e($post['title']) ?>

                  </a>

                </h2>



                <!-- Post Content Text -->

                <p class="post-body-text"><?= nl2br(e($post['content'])) ?></p>



                <!-- Image Gallery -->

                <?php if ($imgCount > 0): ?>

                  <div class="post-media-grid grid-<?= min($imgCount, 4) ?>" onclick="event.stopPropagation()">

                    <?php foreach ($images as $img): ?>

                      <div class="post-media-item" onclick="openLightbox('<?= e($img) ?>')">

                        <img src="<?= e($img) ?>" alt="Post photo" loading="lazy">

                      </div>

                    <?php endforeach; ?>

                  </div>

                <?php endif; ?>



                <!-- Post Action Bar (Reply & Like) -->

                <div class="post-footer" onclick="event.stopPropagation()">

                  <!-- Reply Button -->

                  <button type="button" class="post-action-btn action-reply" onclick="event.stopPropagation(); toggleRepliesSection(<?= $postId ?>)" title="Replies">

                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">

                      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>

                    </svg>

                    <span id="reply-count-badge-<?= $postId ?>"><?= count($replies) > 0 ? count($replies) . (count($replies) === 1 ? ' reply' : ' replies') : 'Reply' ?></span>

                  </button>

                </div>



                <!-- ==================================================== -->

                <!-- INLINE REPLIES & REPLY COMPOSER                      -->

                <!-- ==================================================== -->

                <?php $isExpanded = ($single_post_id === $postId); ?>

                <div class="post-replies-collapse" id="replies-section-<?= $postId ?>" style="display: <?= $isExpanded ? 'block' : 'none' ?>;" onclick="event.stopPropagation()">

                  

                  <!-- Reply Composer Box -->

                  <?php if ($isPostGroupRestricted && !is_admin()): ?>

                  <div class="group-restricted-alert" style="margin-bottom: 16px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                      <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span><strong>Restricted Group:</strong> Posting and replying are currently unavailable.</span>
                  </div>

                  <?php elseif ($currentUser): ?>

                  <form action="index.php" method="POST" enctype="multipart/form-data" class="reply-composer-form" id="reply-form-<?= $postId ?>" style="margin-bottom: 16px; padding: 12px; background: var(--bg-input); border-radius: 14px; border: 1px solid var(--border-color);">

                    <input type="hidden" name="action" value="create_reply">

                    <input type="hidden" name="post_id" value="<?= $postId ?>">

                    

                    <div style="display: flex; gap: 10px;">

                      <img src="<?= e($currentUser['avatar']) ?>" alt="Avatar" style="width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;">

                      <div style="flex: 1;">

                        <textarea name="content" class="reply-textarea" placeholder="Post your reply..." rows="2" style="width: 100%; background: transparent; border: none; color: var(--text-main); font-family: inherit; font-size: 13.5px; resize: vertical; outline: none;" required></textarea>

                        

                        <div class="reply-image-preview-slot" id="reply-preview-<?= $postId ?>" style="display: none; margin-top: 8px;">

                          <div style="position: relative; display: inline-block;">

                            <img src="" alt="Selected reply image" style="max-height: 120px; border-radius: 8px; border: 1px solid var(--border-color);">

                            <button type="button" onclick="clearReplyImage(<?= $postId ?>)" style="position: absolute; top: -6px; right: -6px; background: rgba(0,0,0,0.7); color: #fff; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 11px; cursor: pointer;">✕</button>

                          </div>

                        </div>



                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border-subtle);">

                          <label for="reply-image-input-<?= $postId ?>" style="cursor: pointer; color: var(--primary-blue); display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 600;">

                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">

                              <path d="M3 5.5C3 4.119 4.119 3 5.5 3h13C19.881 3 21 4.119 21 5.5v13c0 1.381-1.119 2.5-2.5 2.5h-13C4.119 21 3 19.881 3 18.5v-13zM5.5 5c-.276 0-.5.224-.5.5v9.086l3.293-3.293a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414l-1.293 1.293H18.5c.276 0 .5-.224.5-.5V5.5c0-.276-.224-.5-.5-.5h-13zM15 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>

                            </svg>

                            <span>Add Photo</span>

                          </label>

                          <input type="file" name="reply_image" id="reply-image-input-<?= $postId ?>" accept="image/*" style="display: none;" onchange="previewReplyImage(this, <?= $postId ?>)">

                          

                          <button type="submit" class="btn-submit-reply" style="background: var(--primary-blue); color: #fff; border: none; border-radius: 9999px; padding: 6px 14px; font-size: 12.5px; font-weight: 700; cursor: pointer;">

                            Reply

                          </button>

                        </div>

                      </div>

                    </div>

                  </form>

                  <?php else: ?>

                  <div style="margin-bottom: 16px; padding: 12px 16px; background: var(--bg-input); border-radius: 14px; border: 1px dashed var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">

                    <span style="font-size: 13px; color: var(--text-muted);">Join Quaddit to reply to this discussion.</span>

                    <div style="display: flex; gap: 8px; align-items: center;">

                      <a href="register.php" class="btn-join-group" style="padding: 6px 14px; font-size: 12.5px;">Register</a>

                      <a href="login.php" style="font-size: 12.5px; color: var(--primary-blue); font-weight: 600; text-decoration: none;">Sign In</a>

                    </div>

                  </div>

                  <?php endif; ?>



                  <!-- Existing Replies Thread -->

                  <?php if (!empty($replies)): ?>

                    <?php

                      $repliesById = [];

                      $childrenByParent = [];

                      $topLevelReplies = [];



                      foreach ($replies as $r) {

                          $repliesById[$r['id']] = $r;

                      }

                      foreach ($replies as $r) {

                          $pId = !empty($r['parent_id']) ? (int)$r['parent_id'] : 0;

                          if ($pId > 0 && isset($repliesById[$pId])) {

                              $childrenByParent[$pId][] = $r;

                          } else {

                              $topLevelReplies[] = $r;

                          }

                      }

                    ?>

                    <div class="replies-tree-list">

                      <?php foreach ($topLevelReplies as $rep): ?>

                        <?php renderReplyNode($rep, $childrenByParent, $repliesById, $currentUser, $postId, 0, $isPostGroupRestricted); ?>

                      <?php endforeach; ?>

                    </div>

                  <?php endif; ?>

                </div>

              </div>

            </article>

          <?php endforeach; ?>

        <?php else: ?>

          <!-- Empty State Card -->

          <div class="empty-state-card" style="text-align: center; padding: 56px 28px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; margin-top: 10px; max-width: 600px; margin-left: auto; margin-right: auto;">

            <div class="empty-state-icon-wrap" style="width: 64px; height: 64px; border-radius: 50%; background: rgba(29, 155, 240, 0.12); border: 1px solid rgba(29, 155, 240, 0.28); display: flex; align-items: center; justify-content: center; color: var(--primary-blue);">

              <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">

                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>

              </svg>

            </div>

            <h2 class="empty-state-title" style="font-size: 20px; font-weight: 800; color: var(--text-main); margin: 0;">

              <?= !empty($search_query) ? 'No matching discussions' : 'No discussions yet' ?>

            </h2>

            <p class="empty-state-subtitle" style="font-size: 14px; color: var(--text-muted); max-width: 400px; line-height: 1.5; margin: 0;">

              <?= !empty($search_query) ? 'No discussions matched your query. Try different words or start a fresh thread.' : 'Be the first to share an idea, ask a campus question, or showcase your project.' ?>

            </p>

            <?php if ($currentUser && !empty($userJoinedGroups)): ?>

              <button type="button" class="btn-empty-state-cta" onclick="openCreateThreadModal(<?= $selected_group_id ?: '' ?>)" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px; padding: 12px 26px; border-radius: 9999px; font-size: 14px; font-weight: 700; background: linear-gradient(135deg, #1d9bf0 0%, #0284c7 100%); color: #ffffff; border: none; cursor: pointer; text-decoration: none; box-shadow: 0 4px 16px rgba(29, 155, 240, 0.35);">

                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">

                  <path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/>

                </svg>

                <span>Start a New Discussion</span>

              </button>

            <?php else: ?>

              <a href="index.php?view=groups" class="btn-empty-state-cta" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px; padding: 12px 26px; border-radius: 9999px; font-size: 14px; font-weight: 700; background: linear-gradient(135deg, #1d9bf0 0%, #0284c7 100%); color: #ffffff; border: none; cursor: pointer; text-decoration: none; box-shadow: 0 4px 16px rgba(29, 155, 240, 0.35);">

                <span>Browse &amp; Join Groups &rarr;</span>

              </a>

            <?php endif; ?>

          </div>

        <?php endif; ?>

      </div>

    <?php endif; ?>

  </main>



  <!-- ======================================================== -->

  <!-- RIGHT SIDEBAR (Recent Discussions)                       -->

  <!-- ======================================================== -->

  <aside class="sidebar-right">

    <!-- Popular Groups Card -->

    <div class="recent-discussions-card" style="margin-bottom: 16px;">

      <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 8px 16px;">

        <h3 class="recent-card-header" style="padding: 0; margin: 0; font-size: 15px;">Groups</h3>

        <a href="index.php?view=groups" style="font-size: 12px; font-weight: 700; color: var(--primary-blue); text-decoration: none;">View All</a>

      </div>

      <div class="recent-discussions-list" style="padding: 0 8px 10px 8px;">

        <?php if (!empty($popularGroups)): ?>

          <?php foreach ($popularGroups as $pg): ?>

            <div class="widget-group-item">

              <a href="index.php?group=<?= (int)$pg['id'] ?>" class="widget-group-left" style="text-decoration: none;">

                <div class="widget-group-icon">
                  <?php if (!empty($pg['image_url'])): ?>
                    <img src="<?= e($pg['image_url']) ?>" alt="<?= e($pg['name']) ?>" class="widget-group-img">
                  <?php else: ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                      <circle cx="9" cy="7" r="4"></circle>
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                  <?php endif; ?>
                </div>

                <div style="min-width: 0;">

                  <div class="widget-group-name"><?= e($pg['name']) ?></div>

                  <div class="widget-group-members"><?= (int)$pg['member_count'] ?> <?= (int)$pg['member_count'] === 1 ? 'member' : 'members' ?></div>

                </div>

              </a>

              <?php if ((int)$pg['is_member'] > 0): ?>

                <span class="widget-joined-badge">Joined</span>

              <?php else: ?>

                <a href="index.php?action=join_group&group_id=<?= (int)$pg['id'] ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? 'index.php') ?>" class="btn-join-group" style="padding: 4px 10px; font-size: 11px;">Join</a>

              <?php endif; ?>

            </div>

          <?php endforeach; ?>

        <?php endif; ?>

      </div>

    </div>



    <!-- Recent Discussions Card -->

    <div class="recent-discussions-card">

      <h3 class="recent-card-header">Recent Discussions</h3>

      <div class="recent-discussions-list">

        <?php if (!empty($recentDiscussions)): ?>

          <?php foreach ($recentDiscussions as $item): ?>

            <a href="index.php?post=<?= (int)$item['id'] ?>" class="recent-discussion-item">

              <div class="recent-discussion-left">

                <img src="<?= e($item['avatar']) ?>" alt="<?= e($item['name']) ?>" class="recent-avatar">

                <div class="recent-info">

                  <div class="recent-title"><?= e($item['title']) ?></div>

                  <div class="recent-meta"><?= (int)$item['reply_count'] ?> replies · <?= time_ago($item['created_at']) ?></div>

                </div>

              </div>

              <div class="recent-chevron">

                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">

                  <polyline points="9 18 15 12 9 6"></polyline>

                </svg>

              </div>

            </a>

          <?php endforeach; ?>

        <?php else: ?>

          <p style="font-size: 13px; color: var(--text-dim); padding: 8px;">No discussions yet.</p>

        <?php endif; ?>

      </div>

    </div>

  </aside>



</div>



<!-- ======================================================== -->

<!-- MODALS (COMPOSE & EDIT)                                  -->

<!-- ======================================================== -->

<?php if ($currentUser): ?>

<!-- CREATE THREAD MODAL -->

<div class="modal-overlay" id="create-thread-modal">

  <div class="modal-dialog x-compose-dialog">

    <div class="x-modal-top-bar">

      <button type="button" class="x-compose-close-btn" onclick="closeCreateThreadModal()" title="Close">

        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">

          <path d="M10.59 12L4.54 5.96l1.42-1.42L12 10.59l6.04-6.05 1.42 1.42L13.41 12l6.05 6.04-1.42 1.42L12 13.41l-6.04 6.05-1.42-1.42L10.59 12z"/>

        </svg>

      </button>

      <button type="button" class="x-compose-drafts-btn" onclick="alert('No saved drafts.')">Drafts</button>

    </div>



    <form action="index.php" method="POST" enctype="multipart/form-data" id="create-post-form">

      <input type="hidden" name="action" value="create_post">

      <div class="x-compose-body">

        <div class="x-compose-left">

          <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="x-compose-avatar">

        </div>

        <div class="x-compose-right">

          <!-- Group Picker Pill Trigger -->
          <div class="composer-group-field">
            <input type="hidden" name="group_id" id="post-group-id" value="<?= !empty($selected_group_id) ? (int)$selected_group_id : '' ?>">
            <button type="button" class="group-picker-pill" id="group-picker-trigger" onclick="openChooseGroupModal()">
              <span class="group-picker-pill-thumb" id="composer-selected-group-icon" style="display: none;"></span>
              <span class="group-picker-pill-label" id="composer-selected-group-label">Select Group</span>
              <svg class="group-picker-pill-chevrons" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="7 15 12 20 17 15"></polyline>
                <polyline points="7 9 12 4 17 9"></polyline>
              </svg>
            </button>
            <div class="composer-field-error" id="create-post-group-error" role="alert" style="display: none;"></div>
          </div>

          <?php if (empty($userJoinedGroups)): ?>
            <div class="composer-no-groups-notice">
              <span>You must join a group before publishing discussions.</span>
              <a href="index.php?view=groups" class="btn-join-group" style="padding: 5px 12px; font-size: 12px;">Browse Groups</a>
            </div>
          <?php endif; ?>

          <div class="composer-form-feedback" id="create-post-form-feedback" role="alert" style="display: none;"></div>

          <!-- Title Input -->
          <input type="text" name="title" id="post-title" class="x-compose-title-input" placeholder="Title of your discussion..." required>

          <!-- Main Textarea -->
          <textarea name="content" id="post-content" class="x-compose-textarea" placeholder="What’s happening?" required></textarea>

          <!-- Previews for selected photos -->
          <div class="image-preview-strip" id="create-image-previews"></div>

        </div>

      </div>



      <div class="x-compose-bottom-toolbar">

        <div class="x-toolbar-icons">

          <button type="button" class="x-tool-btn" onclick="document.getElementById('post-images-input').click()" title="Add photos">

            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">

              <path d="M3 5.5C3 4.119 4.119 3 5.5 3h13C19.881 3 21 4.119 21 5.5v13c0 1.381-1.119 2.5-2.5 2.5h-13C4.119 21 3 19.881 3 18.5v-13zM5.5 5c-.276 0-.5.224-.5.5v9.086l3.293-3.293a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414l-1.293 1.293H18.5c.276 0 .5-.224.5-.5V5.5c0-.276-.224-.5-.5-.5h-13zM15 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>

            </svg>

          </button>

          <input type="file" name="images[]" id="post-images-input" accept="image/*" multiple style="display: none;" onchange="handleImageSelection(this)">

        </div>



        <button type="submit" class="x-compose-submit-btn" id="btn-submit-post">Post</button>

      </div>

    </form>

  </div>

</div>

<!-- CHOOSE GROUP POPUP MODAL -->
<div class="modal-overlay" id="choose-group-modal" onclick="if(event.target===this) closeChooseGroupModal()">
  <div class="modal-dialog choose-group-dialog">
    <div class="choose-group-header">
      <h3 class="choose-group-title">Choose group</h3>
      <button type="button" class="choose-group-close" onclick="closeChooseGroupModal()" aria-label="Close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>

    <div class="choose-group-search-wrap">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="choose-group-search-icon">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <input type="search" id="choose-group-search" class="choose-group-search-input" placeholder="Search" autocomplete="off">
    </div>

    <div class="choose-group-list" id="choose-group-list">
      <?php if (empty($userJoinedGroups)): ?>
        <div class="choose-group-empty">
          <p>You have not joined any active groups yet.</p>
          <a href="index.php?view=groups" class="btn-join-group" style="padding: 6px 14px; font-size: 12.5px;">Browse Groups</a>
        </div>
      <?php else: ?>
        <?php foreach ($userJoinedGroups as $jg): ?>
          <button type="button" class="choose-group-item" data-id="<?= (int)$jg['id'] ?>" data-name="<?= e($jg['name']) ?>" data-image="<?= e($jg['image_url'] ?? '') ?>" onclick="selectComposerGroup(<?= (int)$jg['id'] ?>, this.getAttribute('data-name'), this.getAttribute('data-image'))">
            <div class="choose-group-item-avatar">
              <?php if (!empty($jg['image_url'])): ?>
                <img src="<?= e($jg['image_url']) ?>" alt="<?= e($jg['name']) ?>">
              <?php else: ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              <?php endif; ?>
            </div>
            <div class="choose-group-item-info">
              <div class="choose-group-item-name"><?= e($jg['name']) ?></div>
              <div class="choose-group-item-meta"><?= (int)($jg['member_count'] ?? 0) ?> <?= (int)($jg['member_count'] ?? 0) === 1 ? 'member' : 'members' ?></div>
              <?php if (!empty($jg['description'])): ?>
                <div class="choose-group-item-desc"><?= e($jg['description']) ?></div>
              <?php endif; ?>
            </div>
          </button>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- EDIT POST MODAL -->

<div class="modal-overlay" id="edit-post-modal">

  <div class="modal-dialog">

    <div class="modal-header">

      <h3 class="modal-title">Edit Discussion Thread</h3>

      <button class="btn-close-modal" onclick="closeEditPostModal()">✕</button>

    </div>

    <form action="index.php" method="POST" enctype="multipart/form-data" id="edit-post-form">

      <input type="hidden" name="action" value="edit_post">

      <input type="hidden" name="post_id" id="edit-post-id">

      <div class="modal-body">

        <div class="form-group">

          <label class="form-label" for="edit-post-title">Thread Title</label>

          <input type="text" name="title" id="edit-post-title" class="form-input" required>

        </div>



        <div class="form-group">

          <label class="form-label" for="edit-post-content">Discussion Content</label>

          <textarea name="content" id="edit-post-content" class="form-textarea" required></textarea>

        </div>



        <!-- Existing Photos (With Delete / Remove Option) -->

        <div class="form-group" id="edit-existing-images-group" style="display: none;">

          <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">

            <span>Current Photos</span>

            <span style="font-size: 11.5px; color: var(--text-muted); font-weight: normal;">Click ✕ on photo to delete</span>

          </label>

          <div class="existing-images-grid" id="edit-existing-images-container" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px;"></div>

        </div>



        <div class="form-group">

          <label class="form-label">Add Photos (Upload up to 4 photos)</label>

          <div class="image-upload-zone" onclick="document.getElementById('edit-images-input').click()">

            <div class="upload-text">Click to add photos</div>

            <input type="file" name="images[]" id="edit-images-input" accept="image/*" multiple style="display: none;" onchange="handleEditImageSelection(this)">

          </div>

          <div class="image-preview-strip" id="edit-image-previews" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;"></div>

        </div>

      </div>

      <div class="modal-footer">

        <button type="button" class="btn-modal-cancel" onclick="closeEditPostModal()">Cancel</button>

        <button type="submit" class="btn-modal-submit" id="btn-save-edit-post">Save Changes</button>

      </div>

    </form>

  </div>

</div>



<?php if (is_admin()): ?>

<!-- CREATE GROUP MODAL (Admin Only) -->

<div class="modal-overlay" id="create-group-modal">

  <div class="modal-dialog">

    <div class="modal-header">

      <h3 class="modal-title" style="display: flex; align-items: center; gap: 10px;">

        <span class="modal-title-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          </svg>
        </span>

        <span>Create New Group</span>

      </h3>

      <button class="btn-close-modal" onclick="closeCreateGroupModal()">✕</button>

    </div>

    <form action="index.php" method="POST" enctype="multipart/form-data">

      <input type="hidden" name="action" value="create_group">

      <div class="modal-body">

        <div class="form-group">

          <label class="form-label" for="new-group-name">Group Name *</label>

          <input type="text" name="name" id="new-group-name" class="form-input" placeholder="e.g. Data Structures & Algorithms, Semester 4 CS..." required>

        </div>

        <div class="form-group">

          <label class="form-label" for="new-group-desc">Description & Purpose</label>

          <textarea name="description" id="new-group-desc" class="form-textarea" placeholder="Describe the topics, courses, or subjects covered in this group..."></textarea>

        </div>

        <div class="form-group">

          <label class="form-label" for="new-group-status">Initial Status</label>

          <select name="status" id="new-group-status" class="form-input">

            <option value="active">Active (Students can join and publish discussions)</option>

            <option value="restricted">Restricted (Discussions paused)</option>

          </select>

        </div>

        <div class="form-group">

          <label class="form-label" for="new-group-image">Group Image / Icon (Optional)</label>

          <div class="group-upload-control" data-upload-control>
            <input type="file" name="group_image" id="new-group-image" accept="image/*" class="group-upload-native-input">
            <label for="new-group-image" class="group-upload-dropzone">
              <span class="group-upload-preview" data-upload-preview>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="3" width="18" height="18" rx="4" ry="4"></rect>
                  <circle cx="8.5" cy="8.5" r="1.5"></circle>
                  <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
              </span>
              <span class="group-upload-meta">
                <span class="group-upload-filename" data-upload-filename>Upload group logo or icon</span>
                <span class="group-upload-hint">PNG, JPG, WEBP · Optional</span>
              </span>
              <span class="group-upload-btn">Choose Image</span>
            </label>
            <button type="button" class="group-upload-clear" data-upload-clear title="Remove selected image" style="display: none;">✕</button>
          </div>

        </div>

      </div>

      <div class="modal-footer">

        <button type="button" class="btn-modal-cancel" onclick="closeCreateGroupModal()">Cancel</button>

        <button type="submit" class="btn-modal-submit" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">Create Group</button>

      </div>

    </form>

  </div>

</div>



<!-- EDIT GROUP MODAL (Admin Only) -->

<div class="modal-overlay" id="edit-group-modal">

  <div class="modal-dialog">

    <div class="modal-header">

      <h3 class="modal-title" style="display: flex; align-items: center; gap: 10px;">

        <span class="modal-title-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
          </svg>
        </span>

        <span>Edit Group</span>

      </h3>

      <button class="btn-close-modal" onclick="closeEditGroupModal()">✕</button>

    </div>

    <form action="index.php" method="POST" enctype="multipart/form-data">

      <input type="hidden" name="action" value="edit_group">

      <input type="hidden" name="group_id" id="edit-group-id">

      <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">

      <div class="modal-body">

        <div class="form-group">

          <label class="form-label" for="edit-group-name">Group Name *</label>

          <input type="text" name="name" id="edit-group-name" class="form-input" required>

        </div>

        <div class="form-group">

          <label class="form-label" for="edit-group-desc">Description</label>

          <textarea name="description" id="edit-group-desc" class="form-textarea"></textarea>

        </div>

        <div class="form-group">

          <label class="form-label" for="edit-group-status">Group Status</label>

          <select name="status" id="edit-group-status" class="form-input">

            <option value="active">Active (Open for discussions)</option>

            <option value="restricted">Restricted (Paused for posting)</option>

          </select>

        </div>

        <div class="form-group">

          <label class="form-label" for="edit-group-image">Update Group Image (Optional)</label>

          <div class="group-upload-control" data-upload-control>
            <input type="file" name="group_image" id="edit-group-image" accept="image/*" class="group-upload-native-input">
            <label for="edit-group-image" class="group-upload-dropzone">
              <span class="group-upload-preview" data-upload-preview>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="3" width="18" height="18" rx="4" ry="4"></rect>
                  <circle cx="8.5" cy="8.5" r="1.5"></circle>
                  <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
              </span>
              <span class="group-upload-meta">
                <span class="group-upload-filename" data-upload-filename>Upload new group logo or icon</span>
                <span class="group-upload-hint">PNG, JPG, WEBP · Leave empty to keep current</span>
              </span>
              <span class="group-upload-btn">Choose Image</span>
            </label>
            <button type="button" class="group-upload-clear" data-upload-clear title="Remove selected image" style="display: none;">✕</button>
          </div>

        </div>

      </div>

      <div class="modal-footer">

        <button type="button" class="btn-modal-cancel" onclick="closeEditGroupModal()">Cancel</button>

        <button type="submit" class="btn-modal-submit">Save Changes</button>

      </div>

    </form>

  </div>

</div>

<?php endif; ?>

<?php endif; ?>



<!-- FULL IMAGE LIGHTBOX PREVIEW -->

<div class="lightbox-overlay" id="lightbox-overlay" onclick="closeLightbox(event)">

  <button class="lightbox-close" onclick="closeLightbox(event)">✕</button>

  <img src="" alt="Enlarged view" id="lightbox-img" class="lightbox-img" onclick="event.stopPropagation()">

</div>



<!-- TOAST CONTAINER -->

<div class="toast-container" id="toast-container"></div>



<script>

// Inline Fallback for Immediate Reply & Accordion Interactivity

function toggleRepliesSection(postId) {

  const section = document.getElementById('replies-section-' + postId);

  if (!section) return;

  const currentDisplay = window.getComputedStyle(section).display;

  const isHidden = (currentDisplay === 'none');

  section.style.display = isHidden ? 'block' : 'none';

  if (isHidden) {

    const textarea = section.querySelector('textarea');

    if (textarea) {

      setTimeout(function() {

        textarea.focus();

        textarea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

      }, 50);

    }

  }

}

function toggleEditReplyForm(replyId) {

  const form = document.getElementById('reply-edit-form-' + replyId);

  const text = document.getElementById('reply-text-' + replyId);

  if (!form) return;

  const isHidden = window.getComputedStyle(form).display === 'none';

  form.style.display = isHidden ? 'block' : 'none';

  if (text) text.style.display = isHidden ? 'none' : 'block';

  if (isHidden) {

    const textarea = form.querySelector('textarea');

    if (textarea) textarea.focus();

  }

}

function toggleNestedComposer(replyId) {

  const composer = document.getElementById('nested-composer-' + replyId);

  if (!composer) return;

  const isHidden = window.getComputedStyle(composer).display === 'none';

  composer.style.display = isHidden ? 'block' : 'none';

  if (isHidden) {

    const textarea = composer.querySelector('textarea');

    if (textarea) textarea.focus();

  }

}

function handlePostCardClick(event, postId, groupId) {

  const target = event.target;

  if (target.closest('button, a, input, textarea, label, .post-actions-menu, .post-replies-collapse, .post-media-grid, .post-action-btn, .reply-card')) {

    return;

  }

  const gParam = groupId ? 'group=' + groupId + '&' : '';

  window.location.href = 'index.php?' + gParam + 'post=' + postId + '#post-' + postId;

}



function openCreateGroupModal() {

  const m = document.getElementById('create-group-modal');

  if (m) m.classList.add('active');

}

function closeCreateGroupModal() {

  const m = document.getElementById('create-group-modal');

  if (m) m.classList.remove('active');

}

function openEditGroupModal(id, name, desc, status) {

  const idInput = document.getElementById('edit-group-id');

  const nameInput = document.getElementById('edit-group-name');

  const descInput = document.getElementById('edit-group-desc');

  const statusInput = document.getElementById('edit-group-status');

  if (idInput) idInput.value = id;

  if (nameInput) nameInput.value = name;

  if (descInput) descInput.value = desc;

  if (statusInput) statusInput.value = status;

  const m = document.getElementById('edit-group-modal');

  if (m) m.classList.add('active');

}

function closeEditGroupModal() {

  const m = document.getElementById('edit-group-modal');

  if (m) m.classList.remove('active');

}



function togglePostMenu(e, menuId) {

  e.stopPropagation();

  const currentMenu = document.getElementById(menuId);

  const isOpen = currentMenu && currentMenu.classList.contains('show');

  document.querySelectorAll('.three-dots-dropdown.show').forEach(el => el.classList.remove('show'));

  if (currentMenu && !isOpen) {

    currentMenu.classList.add('show');

  }

}



function openEditPostModal(idOrBtn, title, content) {

  document.querySelectorAll('.three-dots-dropdown.show').forEach(el => el.classList.remove('show'));

  

  const modal = document.getElementById('edit-post-modal');

  if (!modal) return;



  let id = idOrBtn;

  let rawImages = [];

  if (typeof idOrBtn === 'object' && idOrBtn !== null) {

    const btn = idOrBtn;

    id = btn.getAttribute('data-id');

    title = btn.getAttribute('data-title') || '';

    content = btn.getAttribute('data-content') || '';

    const imgData = btn.getAttribute('data-images');

    if (imgData) {

      try {

        rawImages = JSON.parse(imgData);

      } catch (e) {

        rawImages = [];

      }

    }

  }



  const idInput = document.getElementById('edit-post-id');

  const titleInput = document.getElementById('edit-post-title');

  const contentInput = document.getElementById('edit-post-content');



  if (idInput) idInput.value = id || '';

  if (titleInput) titleInput.value = title || '';

  if (contentInput) contentInput.value = content || '';



  // Remove any previously appended delete_image_ids hidden inputs

  const form = document.getElementById('edit-post-form');

  if (form) {

    form.querySelectorAll('input[name="delete_image_ids[]"]').forEach(el => el.remove());

  }



  // Render current existing images

  const existingGroup = document.getElementById('edit-existing-images-group');

  const existingContainer = document.getElementById('edit-existing-images-container');

  if (existingGroup && existingContainer) {

    existingContainer.innerHTML = '';

    if (Array.isArray(rawImages) && rawImages.length > 0) {

      existingGroup.style.display = 'block';

      rawImages.forEach(img => {

        const item = document.createElement('div');

        item.className = 'existing-img-card';

        item.id = `existing-img-${img.id}`;

        item.innerHTML = `

          <img src="${img.url}" alt="Post image">

          <button type="button" class="btn-remove-existing-img" onclick="removeExistingImage(${img.id}, this)" title="Remove image">✕</button>

        `;

        existingContainer.appendChild(item);

      });

    } else {

      existingGroup.style.display = 'none';

    }

  }



  // Reset file input and preview strip for new uploads

  const editPreviewStrip = document.getElementById('edit-image-previews');

  if (editPreviewStrip) editPreviewStrip.innerHTML = '';

  const fileInput = document.getElementById('edit-images-input');

  if (fileInput) fileInput.value = '';



  modal.classList.add('active');

}



function removeExistingImage(imageId, btn) {

  const card = document.getElementById(`existing-img-${imageId}`);

  if (card) {

    card.classList.add('marked-for-delete');

    btn.outerHTML = `

      <button type="button" class="btn-undo-existing-img" onclick="undoRemoveExistingImage(${imageId}, this)" title="Undo remove">↩</button>

      <div class="delete-badge">Delete</div>

    `;



    const form = document.getElementById('edit-post-form');

    if (form && !document.getElementById(`input-del-img-${imageId}`)) {

      const hidden = document.createElement('input');

      hidden.type = 'hidden';

      hidden.name = 'delete_image_ids[]';

      hidden.id = `input-del-img-${imageId}`;

      hidden.value = imageId;

      form.appendChild(hidden);

    }

  }

}



function undoRemoveExistingImage(imageId, btn) {

  const card = document.getElementById(`existing-img-${imageId}`);

  if (card) {

    card.classList.remove('marked-for-delete');

    const badge = card.querySelector('.delete-badge');

    if (badge) badge.remove();



    btn.outerHTML = `

      <button type="button" class="btn-remove-existing-img" onclick="removeExistingImage(${imageId}, this)" title="Remove image">✕</button>

    `;



    const hidden = document.getElementById(`input-del-img-${imageId}`);

    if (hidden) hidden.remove();

  }

}



function closeEditPostModal() {

  const modal = document.getElementById('edit-post-modal');

  if (modal) modal.classList.remove('active');

}



function handleEditImageSelection(input) {

  const previewContainer = document.getElementById('edit-image-previews');

  if (!previewContainer) return;

  previewContainer.innerHTML = '';



  const files = Array.from(input.files);

  files.slice(0, 4).forEach((file) => {

    if (!file.type.startsWith('image/')) return;

    const reader = new window.FileReader();

    reader.onload = (e) => {

      const thumb = document.createElement('div');

      thumb.className = 'preview-thumb-container';

      thumb.style.cssText = 'position: relative; width: 70px; height: 70px; border-radius: 8px; overflow: hidden; display: inline-block; margin-right: 8px; margin-top: 8px; border: 1px solid var(--border-color);';

      thumb.innerHTML = `

        <img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">

        <button type="button" class="btn-remove-thumb" onclick="removeSelectedEditFile(this)" style="position: absolute; top: 3px; right: 3px; width: 18px; height: 18px; border-radius: 50%; background: rgba(0,0,0,0.75); color: #fff; border: none; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center;">✕</button>

      `;

      previewContainer.appendChild(thumb);

    };

    reader.readAsDataURL(file);

  });

}



function removeSelectedEditFile(btn) {

  btn.closest('.preview-thumb-container').remove();

  const previewContainer = document.getElementById('edit-image-previews');

  if (previewContainer && previewContainer.children.length === 0) {

    const fileInput = document.getElementById('edit-images-input');

    if (fileInput) fileInput.value = '';

  }

}



window.handlePostCardClick = handlePostCardClick;

window.toggleRepliesSection = toggleRepliesSection;

window.toggleEditReplyForm = toggleEditReplyForm;

window.toggleNestedComposer = toggleNestedComposer;

window.togglePostMenu = togglePostMenu;

window.openEditPostModal = openEditPostModal;

window.closeEditPostModal = closeEditPostModal;

window.removeExistingImage = removeExistingImage;

window.undoRemoveExistingImage = undoRemoveExistingImage;

window.handleEditImageSelection = handleEditImageSelection;

window.removeSelectedEditFile = removeSelectedEditFile;

</script>

<script src="assets/js/main.js?v=<?= file_exists(__DIR__ . '/assets/js/main.js') ? filemtime(__DIR__ . '/assets/js/main.js') : time() ?>"></script>

</body>

</html>

