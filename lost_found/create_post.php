<?php
require_once __DIR__ . '/auth.php';
requirePostPermission();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postType = $_POST['post_type'] ?? '';
    $itemName = trim($_POST['item_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $eventDate = $_POST['event_date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if (!in_array($postType, ['Lost', 'Found'], true)) {
        $error = 'Please select Lost or Found.';
    } elseif ($itemName === '' || $location === '' || $eventDate === '' || $description === '') {
        $error = 'Please fill in all required fields.';
    }

    $photoName = null;

    if ($error === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Photo upload failed.';
        } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            $error = 'Photo must be 5 MB or smaller.';
        } else {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = mime_content_type($_FILES['photo']['tmp_name']);

            if (!isset($allowed[$mime])) {
                $error = 'Only JPG, PNG or WEBP images are allowed.';
            } else {
                $photoName = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                $destination = __DIR__ . '/uploads/' . $photoName;
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                    $error = 'Could not save the photo.';
                }
            }
        }
    }

    if ($error === '') {
        $stmt = $pdo->prepare("
            INSERT INTO lost_found_posts
            (student_id, admin_id, post_type, item_name, photo, location, event_date, description, moderation_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([
            isStudentLoggedIn() ? currentStudentId() : null,
            isAdminLoggedIn() ? currentAdminId() : null,
            $postType, $itemName, $photoName,
            $location, $eventDate, $description
        ]);

        $_SESSION['lf_flash'] = 'Your post was sent to the moderator for review.';
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Post | UniFlow Lost &amp; Found</title>
<link rel="stylesheet" href="style.css">

<style>
:root{
    --orange:#ff6900;
    --orange2:#ff914d;
    --orangeDark:#dc4f00;
    --ink:#2c211b;
    --muted:#806f64;
    --border:#f0d9c5;
    --cream:#fff8f1;
    --soft:#fff0e4;
}

*{box-sizing:border-box}

html{scroll-behavior:smooth}

body.form-page{
    margin:0;
    min-height:100vh;
    overflow-x:hidden;
    font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
    color:var(--ink);
    background:
        radial-gradient(circle at 86% 13%,rgba(255,105,0,.16),transparent 24%),
        radial-gradient(circle at 8% 83%,rgba(255,145,77,.12),transparent 22%),
        linear-gradient(135deg,#fff9f4,#fff 52%,#fff7ef);
}

body.form-page:before{
    content:"";
    position:fixed;
    width:260px;height:260px;
    right:-105px;top:155px;
    border-radius:52px;
    background:linear-gradient(145deg,rgba(255,145,77,.28),rgba(255,105,0,.07));
    transform:rotate(29deg);
    box-shadow:24px 25px 0 rgba(255,105,0,.06);
    pointer-events:none;
}

body.form-page:after{
    content:"";
    position:fixed;
    width:175px;height:175px;
    left:-90px;bottom:55px;
    border:2px solid rgba(255,105,0,.12);
    border-radius:50%;
    box-shadow:inset 0 0 0 18px rgba(255,255,255,.45);
    pointer-events:none;
}

/* NAV */
.form-page .topbar{
    position:sticky!important;
    top:0;
    z-index:50;
    min-height:76px!important;
    padding:12px clamp(18px,5vw,70px)!important;
    display:flex!important;
    align-items:center!important;
    justify-content:space-between!important;
    background:rgba(255,255,255,.86)!important;
    border-bottom:1px solid var(--border)!important;
    box-shadow:0 10px 32px rgba(60,35,20,.07)!important;
    backdrop-filter:blur(18px);
}

.form-page .brand{
    display:flex!important;
    align-items:center!important;
    gap:12px!important;
    text-decoration:none!important;
    color:var(--ink)!important;
}

.form-page .brand-logo{
    width:48px!important;height:48px!important;
    display:grid!important;place-items:center!important;
    border-radius:15px!important;
    background:linear-gradient(145deg,var(--orange2),var(--orange))!important;
    color:#fff!important;
    font-weight:950!important;
    box-shadow:0 7px 0 var(--orangeDark),0 13px 25px rgba(255,105,0,.22)!important;
}

.form-page .brand strong{
    display:block;
    font-size:22px;
    line-height:1;
}

.form-page .brand small{
    display:block;
    margin-top:4px;
    color:var(--orange);
    font-size:9px;
    font-weight:900;
    letter-spacing:2px;
}

.form-page .topbar nav{
    display:flex!important;
    align-items:center!important;
    gap:9px!important;
}

.form-page .topbar nav a{
    text-decoration:none!important;
    color:#66574e!important;
    padding:10px 14px!important;
    border-radius:11px!important;
    font-weight:800!important;
    transition:.2s ease!important;
}

.form-page .topbar nav a:hover{
    color:var(--orange)!important;
    background:var(--soft)!important;
    transform:translateY(-2px);
}

.form-page .topbar nav .nav-login{
    color:#fff!important;
    background:linear-gradient(145deg,var(--orange2),var(--orange))!important;
    box-shadow:0 4px 0 var(--orangeDark)!important;
}

.form-page .topbar nav .nav-login:hover{
    color:#fff!important;
}

/* LAYOUT */
.create-layout{
    position:relative;
    z-index:2;
    width:min(1450px,92%);
    margin:0 auto;
    padding:42px 0 80px!important;
    display:grid!important;
    grid-template-columns:235px minmax(0,1fr) 235px;
    gap:24px!important;
    align-items:start!important;
}

.tips{
    display:flex;
    flex-direction:column;
    gap:18px;
    position:sticky;
    top:100px;
}

.side-card{
    padding:21px!important;
    border:1px solid var(--border)!important;
    border-radius:21px!important;
    background:rgba(255,255,255,.9)!important;
    box-shadow:0 16px 38px rgba(60,35,20,.07)!important;
    transition:.22s ease!important;
}

.side-card:hover{
    transform:translateY(-5px);
    box-shadow:0 23px 45px rgba(60,35,20,.1)!important;
}

.side-card h3{
    margin:0 0 14px!important;
    color:#33261f!important;
    font-size:16px!important;
}

.side-card p{
    margin:10px 0!important;
    color:var(--muted)!important;
    font-size:12px!important;
    line-height:1.65!important;
}

.side-card.orange-bg{
    background:linear-gradient(145deg,#fff0e3,#fff)!important;
    border-color:#ffd0ad!important;
}

/* MAIN CARD */
.form-card{
    position:relative;
    overflow:hidden;
    padding:42px clamp(24px,4vw,48px)!important;
    border:1px solid rgba(255,255,255,.95)!important;
    border-radius:30px!important;
    background:rgba(255,255,255,.96)!important;
    box-shadow:
        0 28px 70px rgba(76,43,23,.11),
        0 8px 0 rgba(255,105,0,.05)!important;
}

.form-card:before{
    content:"";
    position:absolute;
    width:160px;height:160px;
    right:-70px;top:-70px;
    border-radius:42px;
    background:linear-gradient(145deg,#ff9b58,#ff6900);
    opacity:.13;
    transform:rotate(25deg);
}

.form-card:after{
    content:"UF";
    position:absolute;
    right:30px;top:27px;
    width:70px;height:70px;
    display:grid;place-items:center;
    border:2px solid rgba(255,105,0,.1);
    border-radius:50%;
    color:rgba(255,105,0,.12);
    font-weight:950;
    transform:rotate(-9deg);
}

.form-title{
    position:relative;
    z-index:2;
    margin-bottom:30px;
}

.eyebrow{
    display:inline-block;
    color:var(--orange)!important;
    font-size:11px!important;
    font-weight:950!important;
    letter-spacing:4px!important;
}

.form-title h1{
    margin:9px 0 9px!important;
    font-size:clamp(30px,4vw,47px)!important;
    line-height:1.08!important;
    letter-spacing:-1.8px;
    color:#2f241e!important;
}

.form-title h1 span{color:var(--orange)!important}

.form-title p{
    margin:0!important;
    color:#8a786d!important;
    font-size:14px!important;
    line-height:1.7!important;
}

/* FORM */
.form-card form>label{
    display:block;
    margin:21px 0 8px!important;
    color:#43342c!important;
    font-size:13px!important;
    font-weight:900!important;
}

.optional{
    color:#a39186;
    font-weight:650;
}

.type-choice{
    display:grid!important;
    grid-template-columns:1fr 1fr;
    gap:12px!important;
    margin-bottom:7px;
}

.type-option{
    position:relative;
    display:block!important;
    margin:0!important;
    cursor:pointer;
}

.type-option input{
    position:absolute;
    opacity:0;
    pointer-events:none;
}

.type-option span{
    min-height:92px;
    display:grid;
    grid-template-columns:38px 1fr;
    grid-template-rows:auto auto;
    column-gap:10px;
    align-items:center;
    padding:16px!important;
    border:1.5px solid var(--border)!important;
    border-radius:17px!important;
    background:linear-gradient(145deg,#fff,#fffaf6)!important;
    box-shadow:0 7px 0 #f7e8dc,0 12px 25px rgba(70,40,20,.04)!important;
    transition:.2s ease!important;
}

.type-option span:first-letter{
    font-size:22px;
}

.type-option span b{
    display:block;
    color:#3a2c24;
    font-size:14px;
}

.type-option span small{
    grid-column:2;
    color:#96847a;
    font-size:11px;
    margin-top:2px;
}

.type-option input:checked + span{
    border-color:var(--orange)!important;
    background:#fff4ea!important;
    box-shadow:0 5px 0 #ffd3b3,0 15px 28px rgba(255,105,0,.12)!important;
    transform:translateY(-3px);
}

.form-card input[type="text"],
.form-card input[type="date"],
.form-card input[type="file"]{
    width:100%!important;
    min-height:49px!important;
    padding:12px 14px!important;
    border:1px solid #e9d6c7!important;
    border-radius:13px!important;
    background:#fff!important;
    color:#403129!important;
    font:inherit!important;
    outline:none!important;
    box-shadow:inset 0 1px 2px rgba(60,35,20,.025)!important;
}

.form-card input[type="file"]{
    padding:10px!important;
    background:#fffaf6!important;
    cursor:pointer;
}

.form-card input:focus,
.form-card textarea:focus{
    border-color:var(--orange)!important;
    box-shadow:0 0 0 4px rgba(255,105,0,.1)!important;
}

.two-col{
    display:grid!important;
    grid-template-columns:1fr 1fr;
    gap:15px!important;
}

.two-col>div label{
    display:block;
    margin:21px 0 8px!important;
    color:#43342c!important;
    font-size:13px!important;
    font-weight:900!important;
}

.form-card textarea{
    width:100%!important;
    min-height:155px!important;
    resize:vertical!important;
    padding:14px!important;
    border:1px solid #e9d6c7!important;
    border-radius:15px!important;
    background:#fff!important;
    color:#403129!important;
    font:inherit!important;
    line-height:1.6!important;
    outline:none!important;
}

.error-box{
    position:relative;
    z-index:3;
    margin-bottom:18px!important;
    padding:14px 16px!important;
    border:1px solid #ffc8a5!important;
    border-radius:13px!important;
    background:#fff0e6!important;
    color:#c34e12!important;
    font-size:13px!important;
    font-weight:800!important;
}

.form-actions{
    display:flex!important;
    align-items:center!important;
    justify-content:flex-end!important;
    gap:11px!important;
    margin-top:27px!important;
    padding-top:20px;
    border-top:1px solid #f4e6dc;
}

.cancel-btn{
    min-height:48px;
    display:inline-flex!important;
    align-items:center;
    justify-content:center;
    padding:11px 20px!important;
    border:1px solid #e6d5c8!important;
    border-radius:13px!important;
    color:#66574e!important;
    background:#fff!important;
    text-decoration:none!important;
    font-weight:850!important;
    transition:.2s ease!important;
}

.cancel-btn:hover{
    background:#fff8f2!important;
    transform:translateY(-2px);
}

.form-card .primary-btn{
    min-height:48px!important;
    padding:12px 22px!important;
    border:0!important;
    border-radius:13px!important;
    color:#fff!important;
    background:linear-gradient(145deg,var(--orange2),var(--orange))!important;
    box-shadow:0 5px 0 var(--orangeDark),0 14px 25px rgba(255,105,0,.2)!important;
    font-weight:900!important;
    cursor:pointer;
    transition:.2s ease!important;
}

.form-card .primary-btn:hover{
    transform:translateY(-4px);
    box-shadow:0 8px 0 var(--orangeDark),0 19px 32px rgba(255,105,0,.23)!important;
}

@media(max-width:1100px){
    .create-layout{grid-template-columns:190px minmax(0,1fr)!important}
    .create-layout>.tips:last-child{display:none}
}

@media(max-width:780px){
    .form-page .topbar{
        align-items:flex-start!important;
        flex-direction:column!important;
    }
    .form-page .topbar nav{width:100%}
    .create-layout{
        width:94%;
        grid-template-columns:1fr!important;
        padding-top:24px!important;
    }
    .tips{
        position:static;
        display:grid;
        grid-template-columns:1fr 1fr;
    }
}

@media(max-width:560px){
    .form-page .brand small{display:none}
    .form-page .topbar nav a{font-size:12px;padding:8px 10px!important}
    .form-card{padding:29px 19px!important;border-radius:23px!important}
    .form-title h1{font-size:31px!important}
    .type-choice,
    .two-col,
    .tips{grid-template-columns:1fr!important}
    .type-option span{min-height:82px}
    .form-actions{flex-direction:column-reverse}
    .form-actions>*{width:100%!important}
}
</style>
</head>
<body class="form-page">

<header class="topbar">
    <a class="brand" href="../index.php">
        <span class="brand-logo">UF</span>
        <span><strong>UniFlow</strong><small>SMART CAMPUS PLATFORM</small></span>
    </a>
    <nav>
        <a href="index.php">Lost &amp; Found</a>
        <a class="nav-login" href="logout.php">Logout</a>
    </nav>
</header>

<main class="create-layout">
    <aside class="tips">
        <div class="side-card">
            <h3>📌 Create a Post</h3>
            <p>🔸 Lost something on campus?</p>
            <p>🔸 Found someone's belongings?</p>
            <p>🔸 Add the location to help others.</p>
            <p>🔸 A clear photo can make identification easier.</p>
        </div>
        <div class="side-card">
            <h3>🔐 Privacy</h3>
            <p>Your UniFlow name will be shown on the post so other students know who shared it.</p>
        </div>
    </aside>

    <section class="form-card">
        <div class="form-title">
            <span class="eyebrow">CAMPUS COMMUNITY</span>
            <h1>Create a <span>Lost &amp; Found</span> Post</h1>
            <p>Share a lost or found item with the UniFlow campus community.</p>
        </div>

        <?php if ($error): ?>
            <div class="error-box"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <label>What happened?</label>
            <div class="type-choice">
                <label class="type-option">
                    <input type="radio" name="post_type" value="Lost" required
                        <?= (($_POST['post_type'] ?? '') === 'Lost') ? 'checked' : '' ?>>
                    <span>🔶<b>I Lost Something</b><small>Report something you lost.</small></span>
                </label>
                <label class="type-option">
                    <input type="radio" name="post_type" value="Found"
                        <?= (($_POST['post_type'] ?? '') === 'Found') ? 'checked' : '' ?>>
                    <span>🟢<b>I Found Something</b><small>Help return an item.</small></span>
                </label>
            </div>

            <label for="item_name">Item Name</label>
            <input id="item_name" name="item_name" maxlength="150" required
                   value="<?= e($_POST['item_name'] ?? '') ?>"
                   placeholder="Example: Black Wallet">

            <label for="photo">Item Photo <span class="optional">(optional)</span></label>
            <input id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">

            <div class="two-col">
                <div>
                    <label for="location">📍 Location</label>
                    <input id="location" name="location" required
                           value="<?= e($_POST['location'] ?? '') ?>"
                           placeholder="Example: UIU Library">
                </div>
                <div>
                    <label for="event_date">📅 Date</label>
                    <input id="event_date" type="date" name="event_date" required
                           value="<?= e($_POST['event_date'] ?? date('Y-m-d')) ?>">
                </div>
            </div>

            <label for="description">Description</label>
            <textarea id="description" name="description" maxlength="2000" required
                      placeholder="Describe the item, its color, identifying features, and what happened..."><?= e($_POST['description'] ?? '') ?></textarea>

            <div class="form-actions">
                <a class="cancel-btn" href="index.php">Cancel</a>
                <button class="primary-btn" type="submit">🚀 Publish Post</button>
            </div>
        </form>
    </section>

    <aside class="tips">
        <div class="side-card">
            <h3>💡 Tips for a Good Post</h3>
            <p><b>1.</b> Give the item a clear name.</p>
            <p><b>2.</b> Mention where you lost or found it.</p>
            <p><b>3.</b> Add useful identifying details.</p>
            <p><b>4.</b> Upload a clear photo when possible.</p>
        </div>
        <div class="side-card orange-bg">
            <h3>🤝 Community</h3>
            <p>Your post can help another student quickly find something important.</p>
        </div>
    </aside>
</main>
</body>
</html>
