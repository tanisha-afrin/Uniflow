<?php
require_once __DIR__ . '/auth.php';
requireLogin();

if (!isLostFoundModerator()) {
    http_response_code(403);
    exit('Only Lost & Found moderators can open this page.');
}

$statuses = ['Pending', 'Verified', 'Resolved', 'Rejected'];
$filter = $_GET['status'] ?? 'Pending';
if ($filter !== 'All' && !in_array($filter, $statuses, true)) {
    $filter = 'Pending';
}
$search = trim($_GET['search'] ?? '');
$flash = (string)($_SESSION['lf_moderation_flash'] ?? '');
unset($_SESSION['lf_moderation_flash']);

$counts = array_fill_keys($statuses, 0);
$countStmt = $pdo->query('SELECT moderation_status, COUNT(*) AS total FROM lost_found_posts GROUP BY moderation_status');
foreach ($countStmt->fetchAll() as $row) {
    if (isset($counts[$row['moderation_status']])) {
        $counts[$row['moderation_status']] = (int)$row['total'];
    }
}
$counts['All'] = array_sum($counts);

$sql = "
    SELECT p.*,
        COALESCE(s.name, a.name, 'UniFlow Admin') AS author_name,
        (SELECT COUNT(*) FROM lost_found_comments c WHERE c.post_id = p.post_id) AS comment_count
    FROM lost_found_posts p
    LEFT JOIN students s ON s.student_id = p.student_id
    LEFT JOIN admins a ON a.admin_id = p.admin_id
    WHERE 1 = 1
";
$params = [];

if ($filter !== 'All') {
    $sql .= ' AND p.moderation_status = ?';
    $params[] = $filter;
}
if ($search !== '') {
    $sql .= ' AND (p.item_name LIKE ? OR p.location LIKE ? OR p.description LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$sql .= " ORDER BY FIELD(p.moderation_status, 'Pending', 'Verified', 'Resolved', 'Rejected'), p.created_at DESC";

$postStmt = $pdo->prepare($sql);
$postStmt->execute($params);
$posts = $postStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderation | UniFlow Lost &amp; Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">
        <span class="brand-logo">UF</span>
        <span><strong>UniFlow</strong><small>LOST &amp; FOUND MODERATION</small></span>
    </a>
    <nav>
        <a href="index.php">Public feed</a>
        <a class="nav-login moderator-nav-active" href="moderator.php">Moderation (<?= (int)$counts['Pending'] ?>)</a>
        <span class="user-chip">Hi, <?= e(currentUserName()) ?> · Moderator</span>
        <a class="nav-login" href="logout.php">Logout</a>
    </nav>
</header>

<main class="page moderator-page">
    <section class="hero moderator-hero">
        <div>
            <span class="eyebrow">LOST &amp; FOUND</span>
            <h1>Moderator <span>Inbox</span></h1>
            <p>Review Lost &amp; Found submissions before they appear in the community feed.</p>
        </div>
        <a class="primary-btn" href="index.php">View Public Feed</a>
    </section>

    <?php if ($flash !== ''): ?>
        <div class="submission-notice" role="status"><?= e($flash) ?></div>
    <?php endif; ?>

    <form class="search-form moderator-search" method="GET">
        <input type="hidden" name="status" value="<?= e($filter) ?>">
        <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search item, location or description">
        <button class="primary-btn small" type="submit">Search</button>
    </form>

    <div class="layout moderator-layout">
        <aside class="sidebar">
            <div class="side-card">
                <h3>Review Queue</h3>
                <?php foreach (['Pending', 'Verified', 'Resolved', 'Rejected', 'All'] as $tab): ?>
                    <a class="side-link <?= $filter === $tab ? 'active' : '' ?>" href="moderator.php?<?= http_build_query(['status' => $tab]) ?>">
                        <?= e($tab === 'Verified' ? 'Published' : ($tab === 'All' ? 'All Posts' : $tab)) ?>
                        <span><?= (int)$counts[$tab] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="side-card">
                <h3>Waiting Review</h3>
                <p><?= (int)$counts['Pending'] ?> post<?= $counts['Pending'] === 1 ? '' : 's' ?> waiting for moderator approval.</p>
            </div>
        </aside>

        <section class="feed moderator-feed" aria-live="polite">
        <?php if (!$posts): ?>
            <div class="empty-card">
                <h2>No submissions here</h2>
                <p>Try another status or search term.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($posts as $post): ?>
            <?php $statusClass = strtolower((string)$post['moderation_status']); ?>
            <article class="post-card moderator-post">
                <header class="post-head">
                    <div class="avatar"><?= e(strtoupper(substr($post['author_name'], 0, 1))) ?></div>
                    <div class="author">
                        <strong><?= e($post['author_name']) ?></strong>
                        <small>Submitted <?= date('M d, Y · h:i A', strtotime($post['created_at'])) ?></small>
                    </div>
                    <div class="post-actions">
                        <span class="type-badge <?= $post['post_type'] === 'Lost' ? 'lost' : 'found' ?>"><?= e($post['post_type']) ?></span>
                        <span class="moderation-status <?= e($statusClass) ?>"><?= e($post['moderation_status'] === 'Verified' ? 'Published' : $post['moderation_status']) ?></span>
                    </div>
                </header>

                <div class="post-content">
                    <h2><?= e($post['item_name']) ?></h2>
                    <p><?= nl2br(e($post['description'])) ?></p>
                    <div class="meta-row">
                        <span>Location: <?= e($post['location']) ?></span>
                        <span>Date: <?= date('M d, Y', strtotime($post['event_date'])) ?></span>
                    </div>
                    <?php if (!empty($post['photo'])): ?>
                        <img class="post-photo" src="uploads/<?= e($post['photo']) ?>" alt="<?= e($post['item_name']) ?>">
                    <?php endif; ?>
                </div>

                <form action="moderate_post.php" method="POST" class="moderation-form">
                    <input type="hidden" name="post_id" value="<?= (int)$post['post_id'] ?>">
                    <input type="hidden" name="return_status" value="<?= e($filter) ?>">
                    <label>
                        Status
                        <select name="moderation_status" required>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= e($status) ?>" <?= $post['moderation_status'] === $status ? 'selected' : '' ?>><?= e($status === 'Verified' ? 'Published' : $status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Internal moderator note
                        <textarea name="admin_note" maxlength="5000"><?= e($post['admin_note'] ?? '') ?></textarea>
                    </label>
                    <div class="moderation-buttons">
                        <?php if ($post['moderation_status'] === 'Pending'): ?>
                            <button class="primary-btn small" type="submit" name="moderation_action" value="approve">Approve &amp; Publish</button>
                            <button class="moderator-reject-btn" type="submit" name="moderation_action" value="reject" onclick="return confirm('Reject this post and keep it out of the public feed?')">Reject</button>
                        <?php endif; ?>
                        <button class="moderator-save-btn" type="submit" name="moderation_action" value="save">Save changes</button>
                    </div>
                </form>

                <div class="moderator-manage-links">
                    <a href="edit_post.php?id=<?= (int)$post['post_id'] ?>&amp;return_status=<?= rawurlencode($filter) ?>">Edit post</a>
                    <a class="danger" href="delete_post.php?id=<?= (int)$post['post_id'] ?>&amp;return_to=moderator&amp;status=<?= rawurlencode($filter) ?>" onclick="return confirm('Delete this post and its comments permanently?')">Delete post</a>
                    <span><?= (int)$post['comment_count'] ?> comments</span>
                </div>

                <?php
                $commentStmt = $pdo->prepare('SELECT c.comment_id, c.comment_text, c.created_at, s.name AS student_name FROM lost_found_comments c JOIN students s ON s.student_id = c.student_id WHERE c.post_id = ? ORDER BY c.created_at ASC');
                $commentStmt->execute([(int)$post['post_id']]);
                $comments = $commentStmt->fetchAll();
                ?>
                <?php if ($comments): ?>
                    <div class="moderator-comments">
                        <?php foreach ($comments as $comment): ?>
                            <div class="moderator-comment">
                                <div><strong><?= e($comment['student_name']) ?></strong><p><?= nl2br(e($comment['comment_text'])) ?></p></div>
                                <form action="delete_comment.php" method="POST" onsubmit="return confirm('Delete this comment?')">
                                    <input type="hidden" name="comment_id" value="<?= (int)$comment['comment_id'] ?>">
                                    <input type="hidden" name="return_to" value="moderator">
                                    <input type="hidden" name="return_status" value="<?= e($filter) ?>">
                                    <button class="moderator-delete-comment" type="submit" aria-label="Delete comment">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </section>

        <aside class="rightbar">
            <div class="info-card orange">
                <h3>Review posts</h3>
                <p>Check the item details, location and date before approving.</p>
                <p>Approved posts are published to the Lost &amp; Found community feed.</p>
            </div>
            <div class="info-card">
                <h3>Moderator tools</h3>
                <p>Update status and internal notes, edit or remove posts, and delete inappropriate comments.</p>
            </div>
        </aside>
    </div>
</main>
</body>
</html>