<?php
require_once __DIR__ . '/auth.php';
requireStudent();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
$text = trim($_POST['comment_text'] ?? '');

if ($postId > 0 && $text !== '' && mb_strlen($text) <= 500) {
    $check = $pdo->prepare("SELECT post_id FROM lost_found_posts WHERE post_id = ?");
    $check->execute([$postId]);

    if ($check->fetch()) {
        $stmt = $pdo->prepare("
            INSERT INTO lost_found_comments (post_id, student_id, comment_text)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$postId, currentStudentId(), $text]);
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
?>
