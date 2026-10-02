<?php

require_once "../config/database.php";
require_once "../config/auth.php";

student_required();

$student_id = $_SESSION["student_id"];

$stmt = $conn->prepare(
    "SELECT *
     FROM reports
     WHERE student_id = ?
     ORDER BY created_at DESC"
);

$stmt->bind_param(
    "i",
    $student_id
);

$stmt->execute();

$reports =
    $stmt
    ->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>My Reports</title>

<link
rel="stylesheet"
href="../css/style.css">

</head>

<body>

<nav class="nav">

<a
class="brand"
href="dashboard.php">

🎓 UniFlow

</a>

<div class="nav-links">

<a href="dashboard.php">
Dashboard
</a>

<a href="report_issue.php">
New Report
</a>

<a href="logout.php">
Logout
</a>

</div>

</nav>


<main class="container">

<h2>
My Reports
</h2>

<div class="table-wrap">

<table>

<tr>

<th>Ticket</th>
<th>Category</th>
<th>Issue Type</th>
<th>Title</th>
<th>Status</th>
<th>Date</th>

</tr>


<?php while ($report = $reports->fetch_assoc()): ?>

<tr>

<td>
<?= e($report["ticket_id"]) ?>
</td>

<td>
<?= e(ucfirst($report["category"])) ?>
</td>

<td>
<?= e($report["issue_type"]) ?>
</td>

<td>
<?= e($report["title"]) ?>
</td>

<td>

<span class="badge">

<?= e($report["status"]) ?>

</span>

</td>

<td>

<?= e($report["created_at"]) ?>

</td>

</tr>

<?php endwhile; ?>

</table>

</div>

</main>

</body>
</html>