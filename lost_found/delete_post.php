<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT photo, student_id, admin_id FROM lost_found_posts WHERE post_id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post || !canManagePost($post)) {
    http_response_code(403);
    die('You do not have permission to delete this post.');
}

$delete = $pdo->prepare("DELETE FROM lost_found_posts WHERE post_id = ?");
$delete->execute([$id]);

if (!empty($post['photo']) && is_file(__DIR__ . '/uploads/' . $post['photo'])) {
    @unlink(__DIR__ . '/uploads/' . $post['photo']);
}

$returnToModerator = isLostFoundModerator() && ($_GET['return_to'] ?? '') === 'moderator';
$returnStatus = $_GET['status'] ?? 'Pending';
if (!in_array($returnStatus, ['Pending', 'Verified', 'Resolved', 'Rejected', 'All'], true)) {
    $returnStatus = 'Pending';
}
header('Location: ' . ($returnToModerator ? 'moderator.php?' . http_build_query(['status' => $returnStatus]) : 'index.php'));
exit;
?>
