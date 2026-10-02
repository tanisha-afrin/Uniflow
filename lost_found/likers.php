<?php
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

$postId = (int)($_GET['post_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.name
    FROM lost_found_likes l
    JOIN students s ON s.student_id = l.student_id
    WHERE l.post_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$postId]);

echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
?>
