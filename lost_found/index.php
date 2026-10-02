<?php

require_once __DIR__ . '/auth.php';


$search = trim($_GET['search'] ?? '');

$type = $_GET['type'] ?? 'All';
$isModerator = isLostFoundModerator();
$submissionMessage = (string)($_SESSION['lf_flash'] ?? '');
unset($_SESSION['lf_flash']);
$pendingCount = 0;

if ($isModerator) {
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM lost_found_posts WHERE moderation_status = 'Pending'")->fetchColumn();
}


$sql = "
    SELECT
        p.*,
        COALESCE(s.name, a.name, 'UniFlow Admin') AS author_name,

        (
            SELECT COUNT(*)
            FROM lost_found_likes l
            WHERE l.post_id = p.post_id
        ) AS like_count,

        (
            SELECT COUNT(*)
            FROM lost_found_comments c
            WHERE c.post_id = p.post_id
        ) AS comment_count

    FROM lost_found_posts p

    LEFT JOIN students s
        ON s.student_id = p.student_id

    LEFT JOIN admins a
        ON a.admin_id = p.admin_id

    WHERE p.moderation_status IN ('Verified', 'Resolved')
";


$params = [];


if ($search !== '') {

    $sql .= "
        AND (
            p.item_name LIKE ?
            OR p.location LIKE ?
            OR p.description LIKE ?
        )
    ";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}


if (
    in_array(
        $type,
        ['Lost', 'Found'],
        true
    )
) {

    $sql .= "
        AND p.post_type = ?
    ";

    $params[] = $type;
}


$sql .= "
    ORDER BY p.created_at DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$posts = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Find which posts current student liked
|--------------------------------------------------------------------------
*/

$likedPostIds = [];


if (
    isStudentLoggedIn() &&
    $posts
) {

    $ids = array_column(
        $posts,
        'post_id'
    );


    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($ids),
                '?'
            )
        );


    $likeStmt = $pdo->prepare("
        SELECT post_id

        FROM lost_found_likes

        WHERE student_id = ?

        AND post_id IN ($placeholders)
    ");


    $likeStmt->execute(
        array_merge(
            [currentStudentId()],
            $ids
        )
    );


    $likedPostIds =
        array_map(
            'intval',
            array_column(
                $likeStmt->fetchAll(),
                'post_id'
            )
        );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        UniFlow | Lost &amp; Found
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

<link rel="stylesheet" href="../css/buttons.css">
</head>


<body class="lost-found-home">


<!-- =====================================================
     TOP BAR
===================================================== -->

<header class="topbar">


    <a
        class="brand"
        href="../index.php"
    >

        <span class="brand-logo">
            UF
        </span>


        <span>

            <strong>
                UniFlow
            </strong>

            <small>
                SMART CAMPUS PLATFORM
            </small>

        </span>

    </a>


    <nav>


        <a href="../index.php">
            Home
        </a>


        <a href="../index.php#services">
            Services
        </a>


        <a
            class="active"
            href="index.php"
        >
            Lost &amp; Found
        </a>

        <?php if ($isModerator): ?>
            <a class="nav-login" href="moderator.php">
                Moderation<?= $pendingCount > 0 ? ' (' . $pendingCount . ')' : '' ?>
            </a>
        <?php endif; ?>


        <?php if (isLoggedIn()): ?>


            <span class="user-chip">
                Hi, <?= e(currentUserName()) ?>
                <?php if (isAdminLoggedIn()): ?>
                    · <?= e(ucfirst(currentAdminRole())) ?> Admin
                <?php else: ?>
                    · Student
                <?php endif; ?>
            </span>


            <a
                class="nav-login"
                href="logout.php"
            >
                Logout
            </a>


        <?php else: ?>


            <a
                class="nav-login"
                href="login_redirect.php"
            >
                Login
            </a>


        <?php endif; ?>


    </nav>

</header>



<main class="page">


<!-- =====================================================
     HERO
===================================================== -->

<section class="hero">
    <div class="hero-copy">

        <span class="eyebrow">
            CAMPUS COMMUNITY
        </span>


        <h1>

            Lost
            <span>&amp;</span>
            Found

        </h1>


        <p>
            Find what you lost.
            Return what you found.
            One community feed for the UniFlow campus.
        </p>

    </div>


    <?php if (canCreatePost()): ?>


        <a
            class="primary-btn"
            href="create_post.php"
        >
            Create Post
        </a>


    <?php else: ?>


        <a
            class="primary-btn"
            href="login_redirect.php"
        >
            Login to Post
        </a>


    <?php endif; ?>


</section>


<?php if ($submissionMessage !== ''): ?>
    <div class="submission-notice" role="status"><?= e($submissionMessage) ?></div>
<?php endif; ?>



<!-- =====================================================
     SEARCH
===================================================== -->

<section class="toolbar">


    <form
        method="GET"
        class="search-form"
    >


        <input
            type="text"
            name="search"
            value="<?= e($search) ?>"
            placeholder="Search wallet, bag, ID card, phone, location..."
        >


        <select name="type">


            <option
                value="All"
                <?= $type === 'All'
                    ? 'selected'
                    : '' ?>
            >
                All Posts
            </option>


            <option
                value="Lost"
                <?= $type === 'Lost'
                    ? 'selected'
                    : '' ?>
            >
                Lost Items
            </option>


            <option
                value="Found"
                <?= $type === 'Found'
                    ? 'selected'
                    : '' ?>
            >
                Found Items
            </option>


        </select>


        <button
            class="primary-btn small"
            type="submit"
        >
            Search
        </button>


        <?php if (
            $search !== '' ||
            $type !== 'All'
        ): ?>


            <a
                class="clear-btn"
                href="index.php"
            >
                Clear
            </a>


        <?php endif; ?>


    </form>

</section>



<!-- =====================================================
     THREE COLUMN AREA
===================================================== -->

<div class="layout">



<!-- =====================================================
     LEFT SIDEBAR
===================================================== -->

<aside class="sidebar">


    <div class="side-card">


        <h3>
            Community
        </h3>


        <a
            class="side-link active"
            href="index.php"
        >
            All Posts
        </a>


        <a
            class="side-link"
            href="?type=Lost"
        >
            Lost Items
        </a>


        <a
            class="side-link"
            href="?type=Found"
        >
            Found Items
        </a>


    </div>



    <div class="side-card">


        <h3>
            How it works
        </h3>


        <p>
            Search for an item.
        </p>


        <p>
            Create a Lost or Found post.
        </p>


        <p>
            Comment if you have useful information.
        </p>


        <p>
            Like posts to show support.
        </p>


    </div>


</aside>



<!-- =====================================================
     FEED
===================================================== -->

<section class="feed">


<?php if (!$posts): ?>


    <div class="empty-card">


        <h2>
            No posts found
        </h2>


        <p>
            Try another search or create the first post.
        </p>


        <?php if (canCreatePost()): ?>


            <a
                class="primary-btn"
                href="create_post.php"
            >
                Create Post
            </a>


        <?php elseif (!isLoggedIn()): ?>


            <a
                class="primary-btn"
                href="login_redirect.php"
            >
                Login to Post
            </a>


        <?php endif; ?>


    </div>


<?php endif; ?>



<?php foreach ($posts as $post): ?>


    <?php


        $canManagePost = canManagePost($post);


        $isLiked =
            in_array(
                (int)$post['post_id'],
                $likedPostIds,
                true
            );


        $badgeClass =
            $post['post_type'] === 'Lost'
            ? 'lost'
            : 'found';


    ?>


    <!-- =================================================
         POST
    ================================================= -->

    <article class="post-card">


        <!-- POST HEADER -->

        <div class="post-head">


            <div class="avatar">

                <?= e(
                    strtoupper(
                        substr(
                            $post['author_name'],
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <div class="author">


                <strong>
                    <?= e(
                        $post['author_name']
                    ) ?>
                </strong>


                <small>

                    <?= date(
                        'M d, Y • h:i A',
                        strtotime(
                            $post['created_at']
                        )
                    ) ?>

                </small>


            </div>


            <div class="post-actions">


                <span
                    class="type-badge <?= $badgeClass ?>"
                >

                    <?= e(
                        $post['post_type']
                    ) ?>

                </span>

                <span class="moderation-status <?= strtolower(e($post['moderation_status'] ?? 'Pending')) ?>">
                    <?= e($post['moderation_status'] ?? 'Pending') ?>
                </span>



                <?php if ($canManagePost): ?>


                    <button
                        class="dots-btn"
                        type="button"
                        onclick="
                            toggleMenu(
                                <?= (int)$post['post_id'] ?>
                            )
                        "
                    >
                        ⋮
                    </button>


                    <div
                        class="post-menu"
                        id="menu-<?= (int)$post['post_id'] ?>"
                    >


                        <a
                            href="edit_post.php?id=<?= (int)$post['post_id'] ?>"
                        >
                            Edit Post
                        </a>


                        <a
                            class="danger"
                            href="delete_post.php?id=<?= (int)$post['post_id'] ?>"
                            onclick="
                                return confirm(
                                    'Delete this post permanently?'
                                )
                            "
                        >
                            Delete Post
                        </a>


                    </div>


                <?php endif; ?>


            </div>


        </div>



        <!-- POST CONTENT -->

        <div class="post-content">


            <h2>
                <?= e(
                    $post['item_name']
                ) ?>
            </h2>


            <p>

                <?= nl2br(
                    e(
                        $post['description']
                    )
                ) ?>

            </p>


            <div class="meta-row">


                <span>

                    Location:
                    <?= e(
                        $post['location']
                    ) ?>

                </span>


                <span>

                    Date:
                    <?= date(
                        'M d, Y',
                        strtotime(
                            $post['event_date']
                        )
                    ) ?>

                </span>


            </div>



            <?php if (
                !empty(
                    $post['photo']
                )
            ): ?>


                <img
                    class="post-photo"
                    src="uploads/<?= e(
                        $post['photo']
                    ) ?>"
                    alt="Lost or found item"
                >


            <?php else: ?>


                <div class="no-photo">
                    No photo added
                </div>


            <?php endif; ?>


        </div>



        <!-- STATS -->

        <div class="stats">


            <span>

                <?= (int)$post['like_count'] ?>

                likes

            </span>


            <span>

                <?= (int)$post['comment_count'] ?>

                comments

            </span>


        </div>



        <!-- ACTIONS -->

        <div class="post-buttons">


            <?php if (
                isStudentLoggedIn()
            ): ?>


                <form
                    action="like.php"
                    method="POST"
                >


                    <input
                        type="hidden"
                        name="post_id"
                        value="<?= (int)$post['post_id'] ?>"
                    >


                    <button
                        class="action-btn <?= $isLiked
                            ? 'liked'
                            : '' ?>"
                        type="submit"
                    >

                        <?= $isLiked
                            ? 'Liked'
                            : 'Like'
                        ?>

                    </button>


                </form>


            <?php elseif (
                isAdminLoggedIn()
            ): ?>


                <span
                    class="action-btn"
                    style="
                        opacity:.55;
                        cursor:default;
                    "
                >
                    Like
                </span>


            <?php else: ?>


                <a
                    class="action-btn"
                    href="login_redirect.php"
                >
                    Like
                </a>


            <?php endif; ?>



            <button
                class="action-btn"
                type="button"
                onclick="
                    focusComment(
                        <?= (int)$post['post_id'] ?>
                    )
                "
            >
                Comment
            </button>



            <button
                class="action-btn"
                type="button"
                onclick="
                    showLikers(
                        <?= (int)$post['post_id'] ?>
                    )
                "
            >
                Who liked?
            </button>


        </div>



        <!-- LIKERS -->

        <div
            class="liker-list"
            id="likers-<?= (int)$post['post_id'] ?>"
        ></div>



        <!-- COMMENTS -->

        <div class="comments">


            <?php


                $commentStmt =
                    $pdo->prepare("
                        SELECT
                            c.*,
                            s.name AS student_name

                        FROM lost_found_comments c

                        JOIN students s
                            ON s.student_id =
                               c.student_id

                        WHERE c.post_id = ?

                        ORDER BY
                            c.created_at ASC
                    ");


                $commentStmt->execute([
                    (int)$post['post_id']
                ]);


                $comments =
                    $commentStmt->fetchAll();


            ?>


            <?php foreach (
                $comments
                as $comment
            ): ?>


                <div class="comment">


                    <div class="comment-avatar">

                        <?= e(
                            strtoupper(
                                substr(
                                    $comment['student_name'],
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <div class="comment-body">


                        <strong>

                            <?= e(
                                $comment['student_name']
                            ) ?>

                        </strong>


                        <p>

                            <?= nl2br(
                                e(
                                    $comment['comment_text']
                                )
                            ) ?>

                        </p>


                        <small>

                            <?= date(
                                'M d, Y • h:i A',
                                strtotime(
                                    $comment['created_at']
                                )
                            ) ?>

                        </small>


                    </div>


                </div>

                <?php if (isLostFoundModerator()): ?>
                    <form action="delete_comment.php" method="POST" class="moderator-comment-action">
                        <input type="hidden" name="comment_id" value="<?= (int)$comment['comment_id'] ?>">
                        <button type="submit" class="action-btn" onclick="return confirm('Delete this comment?')">Delete comment</button>
                    </form>
                <?php endif; ?>


            <?php endforeach; ?>



            <?php if (
                isStudentLoggedIn()
            ): ?>


                <form
                    class="comment-form"
                    id="comment-form-<?= (int)$post['post_id'] ?>"
                    action="comment.php"
                    method="POST"
                >


                    <input
                        type="hidden"
                        name="post_id"
                        value="<?= (int)$post['post_id'] ?>"
                    >


                    <input
                        type="text"
                        name="comment_text"
                        maxlength="500"
                        placeholder="Write a helpful comment..."
                        required
                    >


                    <button
                        class="primary-btn small"
                        type="submit"
                    >
                        Post
                    </button>


                </form>


            <?php else: ?>


                <div class="login-comment">


                    <?php if (isAdminLoggedIn()): ?>


                        Admins can create posts. Only students can comment.


                    <?php else: ?>


                        <a
                            href="login_redirect.php"
                        >
                            Login
                        </a>

                        to like or comment on posts.


                    <?php endif; ?>


                </div>


            <?php endif; ?>


        </div>


    </article>


<?php endforeach; ?>


</section>



<!-- =====================================================
     RIGHT SIDEBAR
===================================================== -->

<aside class="rightbar">


    <div class="info-card">


        <h3>
            About this community
        </h3>


        <p>

            UniFlow Lost &amp; Found is a public
            campus community where students can
            share lost and found item information.

        </p>


    </div>



    <div class="info-card orange">


        <h3>
            Need to post?
        </h3>


        <p>

            Lost something or found an item
            on campus?

        </p>


        <?php if (canCreatePost()): ?>


            <a
                class="primary-btn full"
                href="create_post.php"
            >
                Create Post
            </a>


        <?php elseif (
            !isLoggedIn()
        ): ?>


            <a
                class="primary-btn full"
                href="login_redirect.php"
            >
                Login to Post
            </a>


        <?php endif; ?>


    </div>


</aside>


</div>

</main>



<script>


function toggleMenu(id){

    document
        .querySelectorAll('.post-menu')
        .forEach(function(menu){

            if(
                menu.id !==
                'menu-' + id
            ){

                menu.classList.remove(
                    'show'
                );

            }

        });


    const menu =
        document.getElementById(
            'menu-' + id
        );


    if(menu){

        menu.classList.toggle(
            'show'
        );

    }

}



document.addEventListener(
    'click',
    function(event){

        if(
            !event.target.closest(
                '.post-actions'
            )
        ){

            document
                .querySelectorAll(
                    '.post-menu'
                )
                .forEach(
                    function(menu){

                        menu.classList.remove(
                            'show'
                        );

                    }
                );

        }

    }
);



function focusComment(id){

    const form =
        document.getElementById(
            'comment-form-' + id
        );


    if(form){

        form.scrollIntoView({
            behavior:'smooth',
            block:'center'
        });


        const input =
            form.querySelector(
                'input[name="comment_text"]'
            );


        if(input){

            input.focus();

        }


        return;

    }


    <?php if (isAdminLoggedIn()): ?>

        alert(
            'Only students can comment on Lost & Found posts.'
        );

    <?php else: ?>

        window.location.href =
            'login_redirect.php';

    <?php endif; ?>

}



async function showLikers(postId){

    const box =
        document.getElementById(
            'likers-' + postId
        );


    if(!box){
        return;
    }


    if(
        box.classList.contains(
            'open'
        )
    ){

        box.classList.remove(
            'open'
        );

        return;

    }


    box.innerHTML =
        'Loading...';


    box.classList.add(
        'open'
    );


    try{

        const response =
            await fetch(
                'likers.php?post_id=' +
                postId
            );


        const data =
            await response.json();


        if(!data.length){

            box.innerHTML =
                'No likes yet.';

            return;

        }


        box.innerHTML =
            '<strong>Liked by:</strong> ' +

            data
                .map(
                    function(user){

                        return (
                            '<span class="liker">' +
                            escapeHtml(user.name) +
                            '</span>'
                        );

                    }
                )
                .join('');


    }catch(error){

        box.innerHTML =
            'Could not load names.';

    }

}



function escapeHtml(text){

    const div =
        document.createElement(
            'div'
        );


    div.textContent =
        text;


    return div.innerHTML;

}

</script>



</body>

</html>
