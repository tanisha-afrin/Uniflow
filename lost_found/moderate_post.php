<?php
require_once __DIR__ . '/auth.php';
requireLogin();

if (!isLostFoundModerator()) {
    http_response_code(403);
    exit('Only Lost & Found moderators can moderate posts.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
$action = $_POST['moderation_action'] ?? 'save';
$status = match ($action) {
    'approve' => 'Verified',
    'reject' => 'Rejected',
    default => $_POST['moderation_status'] ?? ''
};
$note = trim($_POST['admin_note'] ?? '');
$allowedStatuses = ['Pending', 'Verified', 'Resolved', 'Rejected'];
$returnStatus = $_POST['return_status'] ?? 'Pending';
if (!in_array($returnStatus, ['Pending', 'Verified', 'Resolved', 'Rejected', 'All'], true)) {
    $returnStatus = 'Pending';
}

if ($postId > 0 && in_array($status, $allowedStatuses, true) && mb_strlen($note) <= 5000) {
    $stmt = $pdo->prepare(
        'UPDATE lost_found_posts
         SET moderation_status = ?, admin_note = ?, moderated_by = ?, moderated_at = CURRENT_TIMESTAMP
         WHERE post_id = ?'
    );
    $stmt->execute([$status, $note !== '' ? $note : null, currentAdminId(), $postId]);
    $_SESSION['lf_moderation_flash'] = match ($action) {
        'approve' => 'Post approved and published.',
        'reject' => 'Post rejected and hidden from the public feed.',
        default => 'Moderation changes saved.'
    };
}

header('Location: moderator.php?' . http_build_query(['status' => $returnStatus]));
exit;