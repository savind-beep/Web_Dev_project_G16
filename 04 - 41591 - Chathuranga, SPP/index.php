<?php
require_once "db.php";

// Fetch search, filter, and pagination parameters
$search_query = trim($_GET['search'] ?? '');
$category_slug = trim($_GET['category'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 6; // Display 6 articles per page
$offset = ($page - 1) * $per_page;

// 1. Fetch Trending Articles (Top 3 most-viewed approved articles)
$trending_sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name,
                (SELECT COUNT(*) FROM comments cm WHERE cm.article_id = a.id) AS comment_count
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                JOIN users u ON a.author_id = u.id
                WHERE a.status = 'approved'
                ORDER BY a.views DESC, a.created_at DESC
                LIMIT 3";
$trending_result = mysqli_query($conn, $trending_sql);

// Fetch latest thought bricks for THE WALL preview on home page
$wall_preview_res = mysqli_query($conn, "SELECT * FROM mindscape_thoughts ORDER BY id DESC LIMIT 3");
$wall_total_count = 0;
$c_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM mindscape_thoughts");
if ($c_res && $c_row = mysqli_fetch_assoc($c_res)) {
    $wall_total_count = intval($c_row['cnt']);
}

// 2. Count Total Matching Articles for Pagination
$count_sql = "SELECT COUNT(*) AS total
              FROM articles a
              JOIN categories c ON a.category_id = c.id
              JOIN users u ON a.author_id = u.id
              WHERE a.status = 'approved'";

$count_params = [];
$count_types = "";

if (!empty($category_slug)) {
    $count_sql .= " AND c.slug = ?";
    $count_params[] = $category_slug;
    $count_types .= "s";
}

if (!empty($search_query)) {
    $count_sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
    $like_term = "%" . $search_query . "%";
    $count_params[] = $like_term;
    $count_params[] = $like_term;
    $count_types .= "ss";
}

$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($count_params)) {
    mysqli_stmt_bind_param($count_stmt, $count_types, ...$count_params);
}
mysqli_stmt_execute($count_stmt);
$count_res = mysqli_stmt_get_result($count_stmt);
$total_articles = 0;
if ($row = mysqli_fetch_assoc($count_res)) {
    $total_articles = intval($row['total']);
}
mysqli_stmt_close($count_stmt);

$total_pages = max(1, ceil($total_articles / $per_page));

// 3. Main Query for Paginated Articles
$sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name,
        (SELECT COUNT(*) FROM comments cm WHERE cm.article_id = a.id) AS comment_count
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        JOIN users u ON a.author_id = u.id
        WHERE a.status = 'approved'";

$params = [];
$types = "";

// Filter by category
if (!empty($category_slug)) {
    $sql .= " AND c.slug = ?";
    $params[] = $category_slug;
    $types .= "s";
}

// Search filter
if (!empty($search_query)) {
    $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
    $like_term = "%" . $search_query . "%";
    $params[] = $like_term;
    $params[] = $like_term;
    $types .= "ss";
}

$sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$articles_result = mysqli_stmt_get_result($stmt);

// Fetch all categories for filter buttons
$categories_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");

// Fetch user's bookmarks if logged in
$user_bookmarks = [];
if (isLoggedIn()) {
    $uid = $_SESSION['user_id'];
    $bm_res = mysqli_query($conn, "SELECT article_id FROM bookmarks WHERE user_id = $uid");
    if ($bm_res) {
        while ($bm = mysqli_fetch_assoc($bm_res)) {
            $user_bookmarks[] = $bm['article_id'];
        }
    }
}

$pageTitle = "Home - Campus News & Updates";
include __DIR__ . "/includes/header.php";
?>

<!-- Hero Banner with Entry Animation & Non-stop Background Video -->
<div class="hero-banner fade-in-up">
    <!-- Non-stop Ambient Video Background (Zero Controls, Seamless Infinite Loop) -->
    <video id="heroVideo" class="hero-video-bg" autoplay muted loop playsinline webkit-playsinline preload="auto" poster="assets/images/NSBM/12.webp">
        <source src="assets/campus_hero.mp4" type="video/mp4">
    </video>
    <div class="hero-video-overlay"></div>

    <div class="container text-center py-3 position-relative" style="z-index: 3;">
        <h1 class="display-5 fw-bold mb-2 text-white">Welcome to N Magazine</h1>
        <p class="lead mb-4 text-white text-opacity-75 col-md-8 mx-auto">
            Stay informed with the latest academic announcements, campus life highlights, student club achievements, and upcoming university events.
        </p>

        <!-- Search Form -->
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <form method="GET" action="index.php" class="d-flex gap-2 bg-white p-2 rounded-3 shadow">
                    <?php if (!empty($category_slug)): ?>
                        <input type="hidden" name="category" value="<?php echo safe($category_slug); ?>">
                    <?php endif; ?>
                    <input type="text" name="search" class="form-control border-0" placeholder="Search news headlines or keywords..." value="<?php echo safe($search_query); ?>">
                    <button type="submit" class="btn btn-nsbm px-4">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if (!empty($search_query) || !empty($category_slug)): ?>
                        <a href="index.php" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <!-- Notice Alert if message passed in URL -->
    <?php if (isset($_GET['msg'])): ?>
        <?php 
        $msg_text = $_GET['msg']; 
        $is_warning = (stripos($msg_text, 'restrict') !== false || stripos($msg_text, 'denied') !== false || stripos($msg_text, 'not found') !== false);
        $alert_cls = $is_warning ? 'alert-warning border-warning' : 'alert-success border-success';
        $icon_cls = $is_warning ? 'bi-exclamation-triangle-fill text-warning' : 'bi-check-circle-fill text-success';
        ?>
        <div class="alert <?php echo $alert_cls; ?> alert-dismissible fade show alert-auto-dismiss mb-4 shadow-sm" role="alert">
            <i class="bi <?php echo $icon_cls; ?> me-2"></i> <?php echo safe($msg_text); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Trending Stories -->
    <?php if (empty($search_query) && empty($category_slug) && $page === 1 && $trending_result && mysqli_num_rows($trending_result) > 0): ?>
        <div class="mb-5 fade-in-up anim-delay-1">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 border-bottom pb-2 gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="trending-badge">
                        <i class="bi bi-fire"></i> Trending
                    </span>
                    <h4 class="fw-bold mb-0 text-dark fs-5 fs-sm-4">Most Viewed on Campus</h4>
                </div>
                <span class="text-muted small d-none d-sm-inline">Updated in real-time</span>
            </div>

            <div class="row g-4">
                <?php 
                $rank = 1;
                while ($t_article = mysqli_fetch_assoc($trending_result)): 
                    $t_image = !empty($t_article['image_url']) ? $t_article['image_url'] : 'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&auto=format&fit=crop&q=80';
                ?>
                    <div class="col-md-4 trending-col">
                        <div class="trending-card h-100 d-flex flex-column">
                            <div class="position-relative">
                                <a href="view_article.php?id=<?php echo $t_article['id']; ?>">
                                    <img src="<?php echo safe($t_image); ?>" class="news-card-img" alt="<?php echo safe($t_article['title']); ?>" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&auto=format&fit=crop&q=80';">
                                </a>
                                <div class="position-absolute top-0 start-0 m-2">
                                    <span class="badge bg-danger shadow-sm">
                                        <i class="bi bi-trophy-fill me-1"></i> #<?php echo $rank++; ?>
                                    </span>
                                </div>
                                <div class="position-absolute bottom-0 end-0 m-2">
                                    <span class="badge bg-dark bg-opacity-75 text-white">
                                        <i class="bi bi-eye-fill me-1 text-success"></i> <?php echo number_format($t_article['views']); ?> views
                                    </span>
                                </div>
                            </div>
                            <div class="p-3 d-flex flex-column flex-grow-1">
                                <span class="badge badge-category badge-<?php echo strtolower($t_article['category_slug']); ?> align-self-start mb-2">
                                    <?php echo safe($t_article['category_name']); ?>
                                </span>
                                <h6 class="fw-bold mb-2">
                                    <a href="view_article.php?id=<?php echo $t_article['id']; ?>" class="text-decoration-none text-dark">
                                        <?php echo safe($t_article['title']); ?>
                                    </a>
                                </h6>
                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center text-muted small">
                                    <span><i class="bi bi-person me-1"></i> <?php echo safe($t_article['author_name']); ?></span>
                                    <span><i class="bi bi-chat-text me-1"></i> <?php echo $t_article['comment_count']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Category Filter Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 pb-2 border-bottom fade-in-up anim-delay-1">
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php<?php echo !empty($search_query) ? '?search=' . urlencode($search_query) : ''; ?>" 
               class="btn btn-sm <?php echo empty($category_slug) ? 'btn-nsbm' : 'btn-outline-secondary'; ?> rounded-pill px-3">
                All Stories
            </a>
            <?php 
            if ($categories_res) {
                mysqli_data_seek($categories_res, 0);
                while ($cat = mysqli_fetch_assoc($categories_res)): 
                    $isActive = ($category_slug === $cat['slug']);
            ?>
                <a href="index.php?category=<?php echo urlencode($cat['slug']); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="btn btn-sm <?php echo $isActive ? 'btn-nsbm' : 'btn-outline-secondary'; ?> rounded-pill px-3">
                    <?php echo safe($cat['name']); ?>
                </a>
            <?php 
                endwhile; 
            }
            ?>
        </div>

        <?php if (isLoggedIn() && !isAdmin()): ?>
            <div>
                <a href="submit_article.php" class="btn btn-sm btn-outline-nsbm rounded-pill">
                    <i class="bi bi-plus-circle me-1"></i> Submit Article
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Active Search / Filter Tag -->
    <?php if (!empty($search_query) || !empty($category_slug)): ?>
        <div class="mb-4 text-muted small fade-in-up">
            Showing results for: 
            <?php if (!empty($category_slug)): ?>
                <span class="badge bg-secondary me-2">Category: <?php echo safe(ucfirst($category_slug)); ?></span>
            <?php endif; ?>
            <?php if (!empty($search_query)): ?>
                <span class="badge bg-secondary me-2">Search: "<?php echo safe($search_query); ?>"</span>
            <?php endif; ?>
            <span class="badge bg-light text-dark border me-2"><?php echo $total_articles; ?> story(s) found</span>
            <a href="index.php" class="text-decoration-none text-danger ms-2"><i class="bi bi-x-circle"></i> Clear filters</a>
        </div>
    <?php endif; ?>

    <!-- Articles Grid with Subtle Entry Animation -->
    <div class="row g-4">
        <?php if ($articles_result && mysqli_num_rows($articles_result) > 0): ?>
            <?php 
            $card_index = 0;
            while ($article = mysqli_fetch_assoc($articles_result)): 
                $card_index++;
                $delay_class = 'anim-delay-' . (($card_index % 3) + 1);
                $isBookmarked = in_array($article['id'], $user_bookmarks);
                $badgeClass = 'badge-' . strtolower($article['category_slug']);
                $image = !empty($article['image_url']) ? $article['image_url'] : 'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&auto=format&fit=crop&q=80';
            ?>
                <div class="col-md-6 col-lg-4 fade-in-up <?php echo $delay_class; ?>">
                    <div class="news-card">
                        <a href="view_article.php?id=<?php echo $article['id']; ?>">
                            <img src="<?php echo safe($image); ?>" class="news-card-img" alt="<?php echo safe($article['title']); ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&auto=format&fit=crop&q=80';">
                        </a>
                        <div class="news-card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge badge-category <?php echo $badgeClass; ?>">
                                    <?php echo safe($article['category_name']); ?>
                                </span>
                                
                                <?php if (isLoggedIn()): ?>
                                    <form method="POST" action="bookmarks.php" class="m-0">
                                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                        <input type="hidden" name="return_url" value="<?php echo safe($_SERVER['REQUEST_URI']); ?>">
                                        <button type="submit" class="btn btn-sm btn-link p-0 text-decoration-none" title="<?php echo $isBookmarked ? 'Remove Bookmark' : 'Save Bookmark'; ?>">
                                            <i class="bi <?php echo $isBookmarked ? 'bi-bookmark-fill text-warning' : 'bi-bookmark text-muted'; ?> fs-5"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <h5 class="news-card-title">
                                <a href="view_article.php?id=<?php echo $article['id']; ?>">
                                    <?php echo safe($article['title']); ?>
                                </a>
                            </h5>

                            <p class="news-card-text">
                                <?php 
                                $preview = strip_tags($article['content']);
                                echo safe(strlen($preview) > 120 ? substr($preview, 0, 120) . "..." : $preview); 
                                ?>
                            </p>

                            <div class="news-meta mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-person me-1"></i> <?php echo safe($article['author_name']); ?><br>
                                    <i class="bi bi-clock me-1"></i> <?php echo date("M d, Y", strtotime($article['created_at'])); ?>
                                </div>
                                <div class="text-end">
                                    <span class="me-2"><i class="bi bi-eye"></i> <?php echo $article['views']; ?></span>
                                    <span><i class="bi bi-chat-text"></i> <?php echo $article['comment_count']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 py-5 text-center fade-in-up">
                <div class="text-muted mb-3">
                    <i class="bi bi-journal-x display-3"></i>
                </div>
                <h4 class="fw-semibold">No News Articles Found</h4>
                <p class="text-muted">No published articles match your current search or category filter.</p>
                <a href="index.php" class="btn btn-nsbm">View All News</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ======================================================== -->
    <!-- PAGINATION CONTROLS -->
    <!-- ======================================================== -->
    <?php if ($total_pages > 1): ?>
        <?php
        // Helper to construct pagination query string preserving filters
        function paginationUrl($p, $category_slug, $search_query) {
            $query_params = ['page' => $p];
            if (!empty($category_slug)) $query_params['category'] = $category_slug;
            if (!empty($search_query)) $query_params['search'] = $search_query;
            return 'index.php?' . http_build_query($query_params);
        }
        ?>
        <nav aria-label="Article navigation" class="mt-5 fade-in-up">
            <ul class="pagination justify-content-center">
                <!-- Previous Button -->
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo paginationUrl($page - 1, $category_slug, $search_query); ?>" aria-label="Previous">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                </li>

                <!-- Page Number Links -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                        <a class="page-link <?php echo ($i === $page) ? 'bg-success border-success text-white' : 'text-dark'; ?>" href="<?php echo paginationUrl($i, $category_slug, $search_query); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <!-- Next Button -->
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo paginationUrl($page + 1, $category_slug, $search_query); ?>" aria-label="Next">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
            <div class="text-center text-muted small mt-2">
                Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong> (<?php echo $total_articles; ?> total articles)
            </div>
        </nav>
    <?php endif; ?>

    <!-- Mindscape Preview (The Wall) -->
    <?php if (empty($search_query) && empty($category_slug) && $page === 1 && $wall_preview_res && mysqli_num_rows($wall_preview_res) > 0): ?>
        <div class="mt-5 mb-5 fade-in-up anim-delay-2" id="theWallPreview">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 border-bottom pb-2 gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold mb-0 text-dark fs-5 fs-sm-4"><i class="bi bi-bricks text-success me-2"></i>THE WALL</h4>
                    <span class="badge bg-light text-secondary border rounded-pill d-none d-sm-inline"><?php echo $wall_total_count; ?> bricks</span>
                </div>
                <div class="d-flex align-items-center gap-2 w-100 w-sm-auto justify-content-between justify-content-sm-end mt-1 mt-sm-0">
                    <a href="mindscape.php#composer" class="btn btn-sm btn-nsbm rounded-pill px-3 flex-fill flex-sm-grow-0 text-center text-nowrap">
                        <i class="bi bi-plus-lg me-1"></i> Drop a Brick
                    </a>
                    <a href="mindscape.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 flex-fill flex-sm-grow-0 text-center text-nowrap">
                        View Full Wall <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Preview of Bricks as a Mini Wall -->
            <div class="row g-3">
                <?php while ($brick = mysqli_fetch_assoc($wall_preview_res)): ?>
                    <div class="col-md-4">
                        <div class="mindscape-brick <?php echo safe($brick['brick_color']); ?> h-100 mb-0 shadow-sm">
                            <div class="brick-header mb-2">
                                <div class="brick-author-info d-flex align-items-center gap-2">
                                    <div class="brick-avatar-circle" style="background: <?php echo safe($brick['avatar_bg'] ?? '#16a34a'); ?>; width: 34px; height: 34px; font-size: 1.05rem; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                        <?php echo safe($brick['avatar_emoji'] ?? '💭'); ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="brick-author-name text-truncate fw-semibold small text-dark" style="max-width: 140px;">
                                            <?php echo safe($brick['author_name']); ?>
                                        </div>
                                        <span class="text-muted text-xs d-block" style="font-size: 0.72rem;">
                                            <?php echo date("M d, g:i A", strtotime($brick['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="badge bg-white text-secondary border text-xs" style="font-size: 0.7rem;">
                                    #Brick-<?php echo $brick['id']; ?>
                                </span>
                            </div>

                            <div class="brick-body small text-dark flex-grow-1 mb-2" style="font-size: 0.88rem; line-height: 1.45;">
                                <?php 
                                $txt = safe($brick['thought']);
                                echo nl2br(strlen($txt) > 130 ? substr($txt, 0, 130) . '...' : $txt); 
                                ?>
                                <?php if (!empty($brick['gif_url'])): ?>
                                    <div class="brick-gif-wrapper mt-2 mb-1" style="max-height: 130px;">
                                        <img src="<?php echo safe($brick['gif_url']); ?>" alt="GIF" class="brick-gif-img" style="max-height: 130px;" loading="lazy" onerror="this.closest('.brick-gif-wrapper').style.display='none';">
                                        <span class="brick-gif-badge"><i class="bi bi-filetype-gif me-1"></i>GIF</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="brick-footer pt-2 border-top d-flex justify-content-between align-items-center text-xs text-muted mt-auto" style="font-size: 0.75rem;">
                                <span class="badge bg-light text-dark border text-capitalize">
                                    <?php echo safe($brick['brick_color'] ? str_replace('brick-', '', $brick['brick_color']) : 'emerald'); ?>
                                </span>
                                <span class="text-danger fw-semibold">
                                    ❤️ <?php echo intval($brick['likes']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ======================================================== -->
    <!-- BIG IDEA CALLOUT & STACKED CAMPUS IMAGES SECTION -->
    <!-- ======================================================== -->
    <section class="home-idea-cta-section fade-in-up">
        <div class="row align-items-center g-4 g-lg-5">
            <!-- Left Column: Big Text & Action Callout -->
            <div class="col-lg-7">
                <h2 class="big-idea-heading">
                    BIG CHANGE STARTS WITH <span class="highlight-green">SMALL IDEAS</span>, SO WRITE YOUR IDEA NOW
                </h2>

                <p class="lead text-muted mb-4" style="font-size: 1.05rem; max-width: 600px;">
                    Whether it's an exciting project breakthrough, campus reflection, event announcement, or creative thought, your voice empowers the entire NSBM community.
                </p>

                <div class="d-flex flex-wrap gap-3">
                    <a href="mindscape.php" class="btn btn-nsbm px-4 py-2.5 rounded-pill shadow-sm fw-bold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-bricks"></i> Post on Mindscape Wall
                    </a>
                    <a href="submit_article.php" class="btn btn-outline-success px-4 py-2.5 rounded-pill fw-semibold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-pencil-square"></i> Submit News Article
                    </a>
                </div>
            </div>

            <!-- Right Column: Stacked Campus Photography Deck -->
            <div class="col-lg-5">
                <div class="campus-photo-grid">
                    <div class="campus-grid-item" title="NSBM Globe Plaza">
                        <img src="assets/images/NSBM/12.webp" alt="NSBM Campus" loading="lazy">
                    </div>
                    <div class="campus-grid-item" title="NSBM Swimming Complex">
                        <img src="assets/images/NSBM/13.webp" alt="NSBM Campus" loading="lazy">
                    </div>
                    <div class="campus-grid-item" title="Golden Sunset Way">
                        <img src="assets/images/NSBM/14.webp" alt="NSBM Campus" loading="lazy">
                    </div>
                    <div class="campus-grid-item" title="Barista Campus Cafe">
                        <img src="assets/images/NSBM/15.webp" alt="NSBM Campus" loading="lazy">
                    </div>
                    <div class="campus-grid-item" title="Faculty Quadrangle">
                        <img src="assets/images/NSBM/16.webp" alt="NSBM Campus" loading="lazy">
                    </div>
                    <div class="campus-grid-item" title="Civil Engineering Complex">
                        <img src="assets/images/NSBM/17.webp" alt="NSBM Campus" loading="lazy">
                    </div>
                    <div class="campus-grid-item" title="Fountain Courtyard">
                        <img src="assets/images/NSBM/18.webp" alt="NSBM Campus" loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
