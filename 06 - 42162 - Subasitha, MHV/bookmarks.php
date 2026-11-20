<?php
require_once "db.php";

requireLogin();

$user_id = $_SESSION['user_id'];

// Handle Bookmark Toggle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $article_id = intval($_POST['article_id'] ?? 0);
    $return_url = $_POST['return_url'] ?? 'bookmarks.php';

    if ($article_id > 0) {
        $check_bm = mysqli_query($conn, "SELECT id FROM bookmarks WHERE user_id = $user_id AND article_id = $article_id");
        if (mysqli_num_rows($check_bm) > 0) {
            mysqli_query($conn, "DELETE FROM bookmarks WHERE user_id = $user_id AND article_id = $article_id");
        } else {
            mysqli_query($conn, "INSERT INTO bookmarks (user_id, article_id) VALUES ($user_id, $article_id)");
        }
    }
    header("Location: " . $return_url);
    exit;
}

// Fetch all bookmarked articles for this user
$query = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name, b.created_at AS bookmarked_at
          FROM bookmarks b
          JOIN articles a ON b.article_id = a.id
          JOIN categories c ON a.category_id = c.id
          JOIN users u ON a.author_id = u.id
          WHERE b.user_id = $user_id AND a.status = 'approved'
          ORDER BY b.created_at DESC";
$result = mysqli_query($conn, $query);

$pageTitle = "My Saved Bookmarks";
include __DIR__ . "/includes/header.php";
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-bookmark-star text-warning me-2"></i>My Saved Bookmarks</h2>
            <p class="text-muted small mb-0">Articles and announcements you have saved for quick reading.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Browse More News
        </a>
    </div>

    <div class="row g-4">
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while ($article = mysqli_fetch_assoc($result)): 
                $badgeClass = 'badge-' . strtolower($article['category_slug']);
                $image = !empty($article['image_url']) ? $article['image_url'] : 'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&auto=format&fit=crop&q=80';
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="news-card">
                        <a href="view_article.php?id=<?php echo $article['id']; ?>">
                            <img src="<?php echo safe($image); ?>" class="news-card-img" alt="<?php echo safe($article['title']); ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&auto=format&fit=crop&q=80';">
                        </a>
                        <div class="news-card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge badge-category <?php echo $badgeClass; ?>">
                                    <?php echo safe($article['category_name']); ?>
                                </span>
                                
                                <form method="POST" action="bookmarks.php" class="m-0">
                                    <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                    <input type="hidden" name="return_url" value="bookmarks.php">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" title="Remove Bookmark">
                                        <i class="bi bi-bookmark-x-fill fs-5"></i>
                                    </button>
                                </form>
                            </div>

                            <h5 class="news-card-title">
                                <a href="view_article.php?id=<?php echo $article['id']; ?>">
                                    <?php echo safe($article['title']); ?>
                                </a>
                            </h5>

                            <p class="news-card-text">
                                <?php 
                                $preview = strip_tags($article['content']);
                                echo safe(strlen($preview) > 110 ? substr($preview, 0, 110) . "..." : $preview); 
                                ?>
                            </p>

                            <div class="news-meta mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-calendar3 me-1"></i> <?php echo date("M d, Y", strtotime($article['created_at'])); ?>
                                </div>
                                <a href="view_article.php?id=<?php echo $article['id']; ?>" class="btn btn-nsbm btn-sm px-3">Read</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 py-5 text-center">
                <div class="text-muted mb-3">
                    <i class="bi bi-bookmark-dash display-3"></i>
                </div>
                <h4 class="fw-semibold">No Bookmarks Saved Yet</h4>
                <p class="text-muted">Click the bookmark icon on any news card or article page to save it here for later.</p>
                <a href="index.php" class="btn btn-nsbm">Explore Latest News</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
