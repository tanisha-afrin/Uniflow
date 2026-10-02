<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$isModerator = isLostFoundModerator();
$returnStatus = $_POST['return_status'] ?? $_GET['return_status'] ?? 'Pending';
if (!in_array($returnStatus, ['Pending', 'Verified', 'Resolved', 'Rejected', 'All'], true)) {
    $returnStatus = 'Pending';
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM lost_found_posts WHERE post_id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post || !canManagePost($post)) {
    http_response_code(403);
    die('You do not have permission to edit this post.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postType = $_POST['post_type'] ?? '';
    $itemName = trim($_POST['item_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $eventDate = $_POST['event_date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if (!in_array($postType, ['Lost', 'Found'], true) || !$itemName || !$location || !$eventDate || !$description) {
        $error = 'Please complete all required fields.';
    }

    $photoName = $post['photo'];

    if ($error === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK || $_FILES['photo']['size'] > 5 * 1024 * 1024) {
            $error = 'Photo upload failed or the file is larger than 5 MB.';
        } else {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = mime_content_type($_FILES['photo']['tmp_name']);
            if (!isset($allowed[$mime])) {
                $error = 'Only JPG, PNG or WEBP images are allowed.';
            } else {
                $photoName = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/uploads/' . $photoName)) {
                    $error = 'Could not save the photo.';
                } elseif (!empty($post['photo']) && is_file(__DIR__ . '/uploads/' . $post['photo'])) {
                    @unlink(__DIR__ . '/uploads/' . $post['photo']);
                }
            }
        }
    }

    if ($error === '') {
        $updateSql = $isModerator
            ? 'UPDATE lost_found_posts SET post_type=?, item_name=?, photo=?, location=?, event_date=?, description=? WHERE post_id=?'
            : "UPDATE lost_found_posts SET post_type=?, item_name=?, photo=?, location=?, event_date=?, description=?, moderation_status='Pending', admin_note=NULL, moderated_by=NULL, moderated_at=NULL WHERE post_id=?";
        $update = $pdo->prepare($updateSql);
        $update->execute([
            $postType, $itemName, $photoName, $location, $eventDate,
            $description, $id
        ]);
        if (!$isModerator) {
            $_SESSION['lf_flash'] = 'Your edited post was sent to the moderator for review.';
        }
        header('Location: ' . ($isModerator ? 'moderator.php?' . http_build_query(['status' => $returnStatus]) : 'index.php'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Post | UniFlow</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="../css/buttons.css">
</head>
<body class="form-page">
<header class="topbar">
    <a class="brand" href="../index.php"><span class="brand-logo">UF</span><span><strong>UniFlow</strong><small>SMART CAMPUS PLATFORM</small></span></a>
    <nav><a href="index.php">Lost &amp; Found</a><a class="nav-login" href="logout.php">Logout</a></nav>
</header>

<main class="create-layout single">
<section class="form-card">
    <div class="form-title">
        <span class="eyebrow">YOUR POST</span>
        <h1>Edit <span>Lost &amp; Found</span> Post</h1>
        <p><?= isLostFoundModerator() ? 'Moderator edit' : 'Edit your post.' ?></p>
    </div>

    <?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?php if ($isModerator): ?><input type="hidden" name="return_status" value="<?= e($returnStatus) ?>"><?php endif; ?>
        <label>What happened?</label>
        <div class="type-choice">
            <label class="type-option"><input type="radio" name="post_type" value="Lost" <?= $post['post_type']==='Lost'?'checked':'' ?>><span>🔶<b>I Lost Something</b></span></label>
            <label class="type-option"><input type="radio" name="post_type" value="Found" <?= $post['post_type']==='Found'?'checked':'' ?>><span>🟢<b>I Found Something</b></span></label>
        </div>

        <label>Item Name</label>
        <input name="item_name" maxlength="150" required value="<?= e($post['item_name']) ?>">

        <label>Replace Photo <span class="optional">(optional)</span></label>
        <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">

        <div class="two-col">
            <div><label>📍 Location</label><input name="location" required value="<?= e($post['location']) ?>"></div>
            <div><label>📅 Date</label><input type="date" name="event_date" required value="<?= e($post['event_date']) ?>"></div>
        </div>

        <label>Description</label>
        <textarea name="description" maxlength="2000" required><?= e($post['description']) ?></textarea>

        <div class="form-actions">
            <a class="cancel-btn" href="index.php">Cancel</a>
            <button class="primary-btn" type="submit">💾 Save Changes</button>
        </div>
    </form>
</section>
</main>
</body>
</html>
