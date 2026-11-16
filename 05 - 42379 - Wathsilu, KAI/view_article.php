<?php
require_once "db.php";

$article_id = intval($_GET['id'] ?? 0);

if ($article_id <= 0) {
    header("Location: index.php");
    exit;
}

// Increment view count
mysqli_query($conn, "UPDATE articles SET views = views + 1 WHERE id = $article_id");

// Fetch article details
$stmt = mysqli_prepare($conn, "SELECT a.*, c.name AS category_name, c.slug AS category_slug, 
                               u.id AS author_user_id, u.name AS author_name, u.email AS author_email, 
                               u.student_id AS author_student_id, u.faculty AS author_faculty, 
                               u.bio AS author_bio, u.phone AS author_phone, u.avatar AS author_avatar,
                               u.social_facebook, u.social_twitter, u.social_linkedin, u.social_instagram
                               FROM articles a
                               JOIN categories c ON a.category_id = c.id
                               JOIN users u ON a.author_id = u.id
                               WHERE a.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $article_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$article = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$article) {
    header("Location: index.php?msg=Article+not+found");
    exit;
}

// Admin Quick Actions (Restrict / Unrestrict)
if (isAdmin() && isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['restrict_article', 'unrestrict_article'])) {
    $act = $_REQUEST['action'];
    $new_status = ($act === 'restrict_article') ? 'restricted' : 'approved';
    $u_stmt = mysqli_prepare($conn, "UPDATE articles SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($u_stmt, "si", $new_status, $article_id);
    mysqli_stmt_execute($u_stmt);
    mysqli_stmt_close($u_stmt);

    $msg = ($act === 'restrict_article') 
        ? "This article has been restricted and hidden from public feeds."
        : "Article restriction lifted. Story is now published live.";
    header("Location: view_article.php?id=" . $article_id . "&msg=" . urlencode($msg));
    exit;
}

// Access check for restricted/hidden articles
if ($article['status'] !== 'approved' && !isAdmin() && (!isLoggedIn() || $_SESSION['user_id'] != $article['author_id'])) {
    header("Location: index.php?msg=" . urlencode("This article has been restricted by an administrator."));
    exit;
}

$comment_error = "";
$comment_success = "";

// New comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    if (!isLoggedIn()) {
        header("Location: login.html?error=" . urlencode("Please log in to post comments."));
        exit;
    }

    if ($article['status'] === 'restricted') {
        $comment_error = "Commenting is disabled because this story has been restricted.";
    } else {
        $comment_text = trim($_POST['comment_text'] ?? '');
        if (empty($comment_text)) {
            $comment_error = "Comment cannot be empty.";
        } else {
        $user_id = $_SESSION['user_id'];
        $c_stmt = mysqli_prepare($conn, "INSERT INTO comments (article_id, user_id, comment_text) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($c_stmt, "iis", $article_id, $user_id, $comment_text);
        if (mysqli_stmt_execute($c_stmt)) {
            $comment_success = "Your comment was posted successfully!";
        } else {
            $comment_error = "Failed to post comment. Please try again.";
        }
        mysqli_stmt_close($c_stmt);
    }
    }
}

// Delete comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment_id'])) {
    $del_id = intval($_POST['delete_comment_id']);
    if (isAdmin()) {
        mysqli_query($conn, "DELETE FROM comments WHERE id = $del_id");
        $comment_success = "Comment removed by admin.";
    } elseif (isLoggedIn()) {
        $my_id = $_SESSION['user_id'];
        mysqli_query($conn, "DELETE FROM comments WHERE id = $del_id AND user_id = $my_id");
        $comment_success = "Comment removed.";
    }
}

// Article comments
$comments_query = mysqli_query($conn, "SELECT c.*, u.name AS user_name, u.role AS user_role, u.avatar AS user_avatar 
                                       FROM comments c 
                                       JOIN users u ON c.user_id = u.id 
                                       WHERE c.article_id = $article_id 
                                       ORDER BY c.created_at DESC");

// Bookmark status
$isBookmarked = false;
if (isLoggedIn()) {
    $uid = $_SESSION['user_id'];
    $check_bm = mysqli_query($conn, "SELECT id FROM bookmarks WHERE user_id = $uid AND article_id = $article_id");
    if ($check_bm && mysqli_num_rows($check_bm) > 0) {
        $isBookmarked = true;
    }
}

$pageTitle = $article['title'];
include __DIR__ . "/includes/header.php";
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="index.php?category=<?php echo urlencode($article['category_slug']); ?>" class="text-decoration-none"><?php echo safe($article['category_name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo safe(substr($article['title'], 0, 40)); ?>...</li>
        </ol>
    </nav>

    <?php if ($article['status'] === 'restricted'): ?>
        <div class="alert alert-warning border border-warning shadow-sm d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <i class="bi bi-slash-circle-fill fs-5 me-2 text-warning"></i>
                <strong>Restricted Article:</strong> This article has been restricted by an administrator and is hidden from public feeds.
            </div>
            <?php if (isAdmin()): ?>
                <div class="d-flex gap-2 mt-2 mt-sm-0">
                    <form method="POST" action="view_article.php?id=<?php echo $article['id']; ?>" class="m-0">
                        <input type="hidden" name="action" value="unrestrict_article">
                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-check-circle me-1"></i> Lift Restriction
                        </button>
                    </form>
                    <form method="POST" action="admin.php?tab=articles" class="m-0" onsubmit="return confirm('Permanently remove this article?');">
                        <input type="hidden" name="action" value="delete_article">
                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                        <input type="hidden" name="return_url" value="index.php">
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="bi bi-trash me-1"></i> Remove Article
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Column -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                <?php if (!empty($article['image_url'])): ?>
                    <img src="<?php echo safe($article['image_url']); ?>" class="img-fluid w-100 article-cover-img" alt="<?php echo safe($article['title']); ?>" onerror="this.style.display='none';">
                <?php endif; ?>

                <div class="card-body p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge badge-category badge-<?php echo safe($article['category_slug']); ?>">
                            <?php echo safe($article['category_name']); ?>
                        </span>

                        <div class="d-flex gap-2 align-items-center">
                            <?php if (isLoggedIn()): ?>
                                <form method="POST" action="bookmarks.php" class="m-0">
                                    <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                    <input type="hidden" name="return_url" value="<?php echo safe($_SERVER['REQUEST_URI']); ?>">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Bookmark Article">
                                        <i class="bi <?php echo $isBookmarked ? 'bi-bookmark-fill text-warning' : 'bi-bookmark'; ?> me-1"></i>
                                        <?php echo $isBookmarked ? 'Saved' : 'Bookmark'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (isAdmin() || (isLoggedIn() && $_SESSION['user_id'] == $article['author_id'])): ?>
                                <a href="edit_article.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Article">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </a>
                            <?php endif; ?>

                            <?php if (isLoggedIn() && $_SESSION['user_id'] == $article['author_id'] && !isAdmin()): ?>
                                <form method="POST" action="my_submissions.php" class="m-0" onsubmit="return confirm('Are you sure you want to permanently delete your article?');">
                                    <input type="hidden" name="action" value="delete_article">
                                    <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete My Article">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (isAdmin()): ?>
                                <?php if ($article['status'] !== 'restricted'): ?>
                                    <form method="POST" action="view_article.php?id=<?php echo $article['id']; ?>" class="m-0">
                                        <input type="hidden" name="action" value="restrict_article">
                                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Restrict Article (Hide from public)">
                                            <i class="bi bi-slash-circle me-1"></i> Restrict
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="view_article.php?id=<?php echo $article['id']; ?>" class="m-0">
                                        <input type="hidden" name="action" value="unrestrict_article">
                                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Lift Restriction (Publish Live)">
                                            <i class="bi bi-check-circle me-1"></i> Lift
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="admin.php?tab=articles" class="m-0" onsubmit="return confirm('Permanently remove this article?');">
                                    <input type="hidden" name="action" value="delete_article">
                                    <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                    <input type="hidden" name="return_url" value="index.php">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Article">
                                        <i class="bi bi-trash me-1"></i> Remove
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h1 class="fw-bold mb-3"><?php echo safe($article['title']); ?></h1>

                    <div class="news-meta d-flex flex-wrap align-items-center gap-3 pb-3 mb-4 border-bottom text-muted">
                        <span><i class="bi bi-person-circle me-1"></i> <?php echo safe($article['author_name']); ?></span>
                        <span><i class="bi bi-calendar3 me-1"></i> <?php echo date("F d, Y", strtotime($article['created_at'])); ?></span>
                        <span><i class="bi bi-eye me-1"></i> <?php echo $article['views']; ?> views</span>
                    </div>

                    <div class="article-body-text fs-6 lh-lg mb-4">
                        <?php echo nl2br(safe($article['content'])); ?>
                    </div>

                    <!-- Share / Return Buttons -->
                    <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Back to News
                        </a>
                        <a href="profile.php?id=<?php echo $article['author_user_id']; ?>" class="btn btn-outline-nsbm btn-sm">
                            <i class="bi bi-person me-1"></i> View Author Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Author Profile & Social Connectivity Box -->
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4 bg-white fade-in-up">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="profile-avatar-circle p-0 overflow-hidden shadow-sm" style="width: 56px; height: 56px; border: 2.5px solid #16a34a; background: transparent;">
                            <img src="<?php echo safe(getAvatarUrl($article['author_avatar'] ?? 'ironman')); ?>" alt="Author Avatar" class="w-100 h-100 object-fit-cover rounded-circle">
                        </div>
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Article Written By</span>
                            <h5 class="fw-bold mb-0">
                                <a href="profile.php?id=<?php echo $article['author_user_id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo safe($article['author_name']); ?>
                                </a>
                            </h5>
                            <span class="badge bg-light text-secondary border small">
                                <i class="bi bi-mortarboard me-1 text-success"></i>
                                <?php echo safe(!empty($article['author_faculty']) ? $article['author_faculty'] : 'NSBM Green University'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Author Social & Contact Icons -->
                    <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($article['author_email'])): ?>
                            <a href="mailto:<?php echo safe($article['author_email']); ?>" class="btn btn-sm btn-outline-secondary rounded-circle" title="Send Email" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-envelope-fill"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($article['author_facebook'])): ?>
                            <a href="<?php echo safe($article['author_facebook']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary rounded-circle" title="Facebook" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-facebook"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($article['author_twitter'])): ?>
                            <a href="<?php echo safe($article['author_twitter']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-dark rounded-circle" title="Twitter / X" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-twitter-x"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($article['author_linkedin'])): ?>
                            <a href="<?php echo safe($article['author_linkedin']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info rounded-circle" title="LinkedIn" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-linkedin"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($article['author_instagram'])): ?>
                            <a href="<?php echo safe($article['author_instagram']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-danger rounded-circle" title="Instagram" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-instagram"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($article['author_bio'])): ?>
                    <p class="text-secondary small mt-3 mb-0 pt-2 border-top">
                        <?php echo safe($article['author_bio']); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Comments Section -->
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4" id="comments">
                <h4 class="fw-bold mb-4">
                    <i class="bi bi-chat-dots me-2 text-success"></i> Comments 
                    <span class="badge bg-light text-dark fs-6"><?php echo mysqli_num_rows($comments_query); ?></span>
                </h4>

                <?php if (!empty($comment_error)): ?>
                    <div class="alert alert-danger py-2 small"><?php echo safe($comment_error); ?></div>
                <?php endif; ?>

                <?php if (!empty($comment_success)): ?>
                    <div class="alert alert-success py-2 small"><?php echo safe($comment_success); ?></div>
                <?php endif; ?>

                <!-- Add Comment Form -->
                <?php if ($article['status'] === 'restricted'): ?>
                    <div class="alert alert-warning py-3 mb-4 text-center border-warning-subtle">
                        <i class="bi bi-lock-fill me-1 text-warning"></i> Comments are locked because this story has been restricted.
                    </div>
                <?php elseif (isLoggedIn()): ?>
                    <form method="POST" action="view_article.php?id=<?php echo $article['id']; ?>#comments" class="mb-4">
                        <div class="mb-3">
                            <label for="comment_text" class="form-label small fw-semibold">Leave a comment as <strong><?php echo safe($_SESSION['user_name']); ?></strong>:</label>
                            <textarea class="form-control" id="comment_text" name="comment_text" rows="3" placeholder="Share your thoughts respectfully..." required></textarea>
                        </div>
                        <button type="submit" name="submit_comment" class="btn btn-nsbm btn-sm px-3">
                            <i class="bi bi-send me-1"></i> Post Comment
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-light border py-3 mb-4 text-center">
                        <p class="mb-1 text-muted">Want to join the campus discussion?</p>
                        <a href="login.html" class="btn btn-sm btn-nsbm px-3">Log in to comment</a>
                    </div>
                <?php endif; ?>

                <!-- List Comments -->
                <div class="comments-list">
                    <?php if (mysqli_num_rows($comments_query) > 0): ?>
                        <?php while ($comment = mysqli_fetch_assoc($comments_query)): ?>
                            <div class="comment-item">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo safe(getAvatarUrl($comment['user_avatar'] ?? 'ironman')); ?>" alt="Avatar" class="rounded-circle shadow-sm" style="width: 26px; height: 26px; object-fit: cover; border: 1.5px solid #16a34a;">
                                        <strong class="text-dark"><?php echo safe($comment['user_name']); ?></strong>
                                        <?php if ($comment['user_role'] === 'admin'): ?>
                                            <span class="badge bg-success-subtle text-success small">Admin</span>
                                        <?php endif; ?>
                                        <span class="text-muted small">&bull; <?php echo date("M d, Y, g:i a", strtotime($comment['created_at'])); ?></span>
                                    </div>
                                    
                                    <?php if (isAdmin() || (isLoggedIn() && $_SESSION['user_id'] == $comment['user_id'])): ?>
                                        <form method="POST" action="view_article.php?id=<?php echo $article['id']; ?>#comments" class="m-0">
                                            <input type="hidden" name="delete_comment_id" value="<?php echo $comment['id']; ?>">
                                            <button type="submit" class="btn btn-link text-danger p-0 confirm-delete" title="Delete Comment" data-message="Are you sure you want to delete this comment?">
                                                <i class="bi bi-trash small"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <p class="text-secondary mb-0 small comment-text"><?php echo safe($comment['comment_text']); ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted small">
                            <i class="bi bi-chat-square-quote display-6 d-block mb-2 text-secondary"></i>
                            No comments yet. Be the first to start the conversation!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Column -->
        <div class="col-lg-4">
            <!-- Category Selection Menu -->
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
                <h5 class="fw-bold mb-3 d-flex align-items-center">
                    <i class="bi bi-grid-fill text-success me-2"></i> Categories
                </h5>
                <div class="list-group list-group-flush side-category-menu">
                    <?php
                    $side_cats_q = mysqli_query($conn, "SELECT c.id, c.name, c.slug, 
                        (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id AND a.status = 'approved') AS total_count
                        FROM categories c 
                        ORDER BY FIELD(c.slug, 'events', 'academic', 'clubs', 'uni-life', 'sports', 'general'), c.name ASC");

                    $cat_icons = [
                        'events'   => 'bi-calendar-event text-warning',
                        'academic' => 'bi-mortarboard text-primary',
                        'clubs'    => 'bi-people-fill text-danger',
                        'uni-life' => 'bi-compass text-info',
                        'sports'   => 'bi-trophy text-success',
                        'general'  => 'bi-info-circle text-secondary'
                    ];

                    if ($side_cats_q && mysqli_num_rows($side_cats_q) > 0):
                        while ($scat = mysqli_fetch_assoc($side_cats_q)):
                            $isCurrent = ($article['category_slug'] === $scat['slug']);
                            $icon = $cat_icons[$scat['slug']] ?? 'bi-tag text-success';
                    ?>
                        <a href="index.php?category=<?php echo urlencode($scat['slug']); ?>" 
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0 py-2 border-bottom <?php echo $isCurrent ? 'fw-bold text-success' : 'text-dark'; ?>">
                            <span class="d-flex align-items-center">
                                <i class="bi <?php echo $icon; ?> me-2 fs-6"></i>
                                <?php echo safe($scat['name']); ?>
                            </span>
                            <span class="badge <?php echo $isCurrent ? 'bg-success text-white' : 'bg-light text-muted border'; ?> rounded-pill small">
                                <?php echo $scat['total_count']; ?>
                            </span>
                        </a>
                    <?php 
                        endwhile;
                    endif; 
                    ?>
                </div>
            </div>

            <!-- About Section Card -->
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-success me-2"></i>About This Story</h5>
                <ul class="list-unstyled small mb-0 lh-lg">
                    <li><strong>Category:</strong> <span class="badge badge-category badge-<?php echo safe($article['category_slug']); ?>"><?php echo safe($article['category_name']); ?></span></li>
                    <li><strong>Submitted By:</strong> <?php echo safe($article['author_name']); ?></li>
                    <li><strong>Published On:</strong> <?php echo date("M d, Y", strtotime($article['created_at'])); ?></li>
                    <li><strong>Total Views:</strong> <?php echo $article['views']; ?></li>
                </ul>
            </div>

            <!-- Related Articles Card -->
            <div class="card border-0 shadow-sm rounded-3 p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-lightning text-warning me-2"></i>Other Latest Stories</h5>
                <?php
                $related_query = mysqli_query($conn, "SELECT id, title, created_at FROM articles WHERE status = 'approved' AND id != $article_id ORDER BY created_at DESC LIMIT 4");
                if ($related_query && mysqli_num_rows($related_query) > 0):
                ?>
                    <ul class="list-unstyled mb-0">
                        <?php while ($rel = mysqli_fetch_assoc($related_query)): ?>
                            <li class="mb-3 pb-2 border-bottom">
                                <a href="view_article.php?id=<?php echo $rel['id']; ?>" class="text-decoration-none text-dark fw-semibold small d-block">
                                    <?php echo safe($rel['title']); ?>
                                </a>
                                <span class="text-muted related-date"><i class="bi bi-clock me-1"></i><?php echo date("M d, Y", strtotime($rel['created_at'])); ?></span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted small mb-0">No other articles available right now.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
