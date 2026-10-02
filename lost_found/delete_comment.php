<?php
require_once __DIR__ . '/auth.php';
requireLogin();

if (!isLostFoundModerator()) {
    http_response_code(403);
    exit('Only Lost & Found moderators can delete comments.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$commentId = (int)($_POST['comment_id'] ?? 0);
if ($commentId > 0) {
    $stmt = $pdo->prepare('DELETE FROM lost_found_comments WHERE comment_id = ?');
    $stmt->execute([$commentId]);
}

$returnToModerator = ($_POST['return_to'] ?? '') === 'moderator';
$returnStatus = $_POST['return_status'] ?? 'Pending';
if (!in_array($returnStatus, ['Pending', 'Verified', 'Resolved', 'Rejected', 'All'], true)) {
    $returnStatus = 'Pending';
}
header('Location: ' . ($returnToModerator ? 'moderator.php?' . http_build_query(['status' => $returnStatus]) : 'index.php'));
exit;