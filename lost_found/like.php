<?php
require_once __DIR__ . '/auth.php';
requireStudent();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);

$check = $pdo->prepare("SELECT like_id FROM lost_found_likes WHERE post_id = ? AND student_id = ?");
$check->execute([$postId, currentStudentId()]);
$existing = $check->fetch();

if ($existing) {
    $stmt = $pdo->prepare("DELETE FROM lost_found_likes WHERE like_id = ?");
    $stmt->execute([$existing['like_id']]);
} else {
    $stmt = $pdo->prepare("INSERT INTO lost_found_likes (post_id, student_id) VALUES (?, ?)");
    $stmt->execute([$postId, currentStudentId()]);
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
?>
