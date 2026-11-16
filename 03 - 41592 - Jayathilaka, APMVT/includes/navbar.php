<?php
require_once __DIR__ . "/../db.php";

$current_page = basename($_SERVER['PHP_SELF']);

// Moderation count (restricted stories)
$mod_count = 0;
if (isAdmin()) {
    $mod_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM articles WHERE status = 'restricted'");
    if ($mod_res && $row = mysqli_fetch_assoc($mod_res)) {
        $mod_count = intval($row['total']);
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-nsbm sticky-top">
    <div class="container">

        <!-- Brand -->
        <a class="navbar-brand d-inline-flex align-items-center p-0 me-4" href="index.php">
            <img src="assets/images/logo_nav.png" alt="N Magazine" class="navbar-logo" height="34"
                 style="height:34px !important;width:auto !important;max-height:34px !important;max-width:150px !important;object-fit:contain;display:block;">
        </a>

        <!-- Hamburger toggler (mobile) -->
        <button class="navbar-toggler border-0 shadow-none px-2" type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#navSideDrawer"
                aria-controls="navSideDrawer"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Desktop collapse (lg+) -->
        <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarDesktop">
            <ul class="navbar-nav me-auto mb-0 align-items-center">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                </li>

                <!-- Categories -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-grid"></i> Categories
                    </a>
                    <ul class="dropdown-menu shadow-sm">
                        <li><a class="dropdown-item" href="index.php?category=events"><i class="bi bi-calendar-event text-warning me-2"></i>Events</a></li>
                        <li><a class="dropdown-item" href="index.php?category=academic"><i class="bi bi-mortarboard text-primary me-2"></i>Academic</a></li>
                        <li><a class="dropdown-item" href="index.php?category=clubs"><i class="bi bi-people-fill text-danger me-2"></i>Clubs</a></li>
                        <li><a class="dropdown-item" href="index.php?category=uni-life"><i class="bi bi-compass text-info me-2"></i>Uni Life</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?category=sports"><i class="bi bi-trophy text-success me-2"></i>Sports</a></li>
                        <li><a class="dropdown-item" href="index.php?category=general"><i class="bi bi-info-circle text-secondary me-2"></i>General</a></li>
                    </ul>
                </li>

                <!-- Mindscape -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'mindscape.php') ? 'active' : ''; ?>" href="mindscape.php">
                        <i class="bi bi-bricks text-success"></i> Mindscape
                    </a>
                </li>

                <?php if (isLoggedIn()): ?>
                    <?php if (!isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'submit_article.php') ? 'active' : ''; ?>" href="submit_article.php">
                                <i class="bi bi-pencil-square"></i> Submit News
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'bookmarks.php') ? 'active' : ''; ?>" href="bookmarks.php">
                                <i class="bi bi-bookmark-fill"></i> Saved
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'my_submissions.php') ? 'active' : ''; ?>" href="my_submissions.php">
                                <i class="bi bi-journal-text"></i> My Submissions
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Admin Management Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-1 <?php echo ($current_page == 'admin.php') ? 'active fw-semibold' : ''; ?>"
                               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-shield-lock-fill text-success"></i> Admin Panel
                                <?php if ($mod_count > 0): ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-1 ms-1"><?php echo $mod_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu shadow">
                                <li><a class="dropdown-item" href="admin.php?tab=dashboard"><i class="bi bi-speedometer2 text-success me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="admin.php?tab=articles"><i class="bi bi-journals text-primary me-2"></i>Articles</a></li>
                                <li>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center" href="admin.php?tab=restricted">
                                        <span><i class="bi bi-slash-circle text-warning me-2"></i>Restricted Stories</span>
                                        <?php if ($mod_count > 0): ?>
                                            <span class="badge bg-warning text-dark rounded-pill"><?php echo $mod_count; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="admin.php?tab=categories"><i class="bi bi-tags me-2"></i>Categories</a></li>
                                <li><a class="dropdown-item" href="admin.php?tab=comments"><i class="bi bi-chat-left-dots me-2"></i>Comments</a></li>
                                <li><a class="dropdown-item" href="admin.php?tab=users"><i class="bi bi-people me-2"></i>Accounts</a></li>
                                <li><a class="dropdown-item" href="admin.php?tab=mindscape"><i class="bi bi-bricks me-2"></i>Mindscape Moderation</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-success fw-semibold" href="admin.php?tab=new"><i class="bi bi-plus-circle me-2"></i>Write Story</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- Auth / profile (desktop) -->
            <!-- Dark Mode Toggle -->
            <button id="darkModeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
                <i class="bi bi-moon-stars-fill" id="darkModeIcon"></i>
            </button>
            <ul class="navbar-nav ms-2 align-items-center">
                <?php if (isLoggedIn()):
                    $nav_user_avatar = $_SESSION['user_avatar'] ?? null;
                    if (!$nav_user_avatar && isset($_SESSION['user_id'])) {
                        $av_q = @mysqli_query($conn, "SELECT avatar FROM users WHERE id = " . intval($_SESSION['user_id']) . " LIMIT 1");
                        if ($av_q && $av_r = mysqli_fetch_assoc($av_q)) {
                            $nav_user_avatar = $av_r['avatar'];
                            $_SESSION['user_avatar'] = $nav_user_avatar;
                        }
                    }
                    $nav_avatar_path = getAvatarUrl($nav_user_avatar ?? 'ironman');
                ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo safe($nav_avatar_path); ?>" alt="Avatar" class="rounded-circle shadow-sm"
                                 style="width:28px;height:28px;object-fit:cover;border:1.5px solid #16a34a;">
                            <span class="fw-medium"><?php echo safe($_SESSION['user_name'] ?? 'User'); ?></span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase navbar-role-badge">
                                <?php echo safe($_SESSION['role']); ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li class="px-3 py-2 text-muted small border-bottom">
                                Signed in as <strong><?php echo safe($_SESSION['user_email']); ?></strong>
                            </li>
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                            <?php if (isAdmin()): ?>
                                <li><a class="dropdown-item" href="admin.php?tab=dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="admin.php?tab=articles"><i class="bi bi-journals me-2"></i>Articles</a></li>
                                <li><a class="dropdown-item" href="admin.php?tab=new"><i class="bi bi-pencil-square me-2"></i>Write Story</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="submit_article.php"><i class="bi bi-pencil-square me-2"></i>Submit News</a></li>
                                <li><a class="dropdown-item" href="bookmarks.php"><i class="bi bi-bookmark me-2"></i>Saved Bookmarks</a></li>
                                <li><a class="dropdown-item" href="my_submissions.php"><i class="bi bi-journal-text me-2"></i>My Submissions</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'login.html') ? 'active' : ''; ?>" href="login.html">
                            <i class="bi bi-box-arrow-in-right"></i> Log In
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-nsbm btn-sm text-white fw-bold px-3 shadow-sm" href="register.html">
                            <i class="bi bi-person-plus"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div><!-- /.collapse (desktop) -->

    </div><!-- /.container -->
</nav>

<!-- ============================================================
     MOBILE OFFCANVAS SIDE DRAWER (outside <nav>, at body level)
     ============================================================ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="navSideDrawer" aria-labelledby="navSideDrawerLabel">
    <div class="offcanvas-header border-bottom py-3">
        <a class="d-inline-flex align-items-center" href="index.php">
            <img src="assets/images/logo_nav.png" alt="N Magazine" height="30" style="height:30px;width:auto;">
        </a>
        <button type="button" class="btn-close shadow-none ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body px-0 py-0">
        <!-- Main nav links -->
        <ul class="list-unstyled mb-0">
            <li>
                <a class="offcanvas-nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
                    <i class="bi bi-house-door"></i> Home
                </a>
            </li>
            <li>
                <a class="offcanvas-nav-link" href="index.php?category=events">
                    <i class="bi bi-calendar-event text-warning"></i> Events
                </a>
            </li>
            <li>
                <a class="offcanvas-nav-link" href="index.php?category=academic">
                    <i class="bi bi-mortarboard text-primary"></i> Academic
                </a>
            </li>
            <li>
                <a class="offcanvas-nav-link" href="index.php?category=clubs">
                    <i class="bi bi-people-fill text-danger"></i> Clubs
                </a>
            </li>
            <li>
                <a class="offcanvas-nav-link" href="index.php?category=uni-life">
                    <i class="bi bi-compass text-info"></i> Uni Life
                </a>
            </li>
            <li>
                <a class="offcanvas-nav-link" href="index.php?category=sports">
                    <i class="bi bi-trophy text-success"></i> Sports
                </a>
            </li>
            <li>
                <a class="offcanvas-nav-link <?php echo ($current_page == 'mindscape.php') ? 'active' : ''; ?>" href="mindscape.php">
                    <i class="bi bi-bricks text-success"></i> Mindscape
                </a>
            </li>

            <?php if (isLoggedIn()): ?>
                <li><hr class="my-1 mx-3"></li>
                <?php if (!isAdmin()): ?>
                    <li>
                        <a class="offcanvas-nav-link <?php echo ($current_page == 'submit_article.php') ? 'active' : ''; ?>" href="submit_article.php">
                            <i class="bi bi-pencil-square"></i> Submit News
                        </a>
                    </li>
                    <li>
                        <a class="offcanvas-nav-link <?php echo ($current_page == 'bookmarks.php') ? 'active' : ''; ?>" href="bookmarks.php">
                            <i class="bi bi-bookmark-fill"></i> Saved
                        </a>
                    </li>
                    <li>
                        <a class="offcanvas-nav-link <?php echo ($current_page == 'my_submissions.php') ? 'active' : ''; ?>" href="my_submissions.php">
                            <i class="bi bi-journal-text"></i> My Submissions
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a class="offcanvas-nav-link <?php echo ($current_page == 'admin.php') ? 'active' : ''; ?>" href="admin.php?tab=dashboard">
                            <i class="bi bi-speedometer2 text-success"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a class="offcanvas-nav-link" href="admin.php?tab=articles">
                            <i class="bi bi-journals text-primary"></i> Articles
                        </a>
                    </li>
                    <li>
                        <a class="offcanvas-nav-link" href="admin.php?tab=restricted">
                            <i class="bi bi-slash-circle text-warning"></i> Restricted Stories
                            <?php if ($mod_count > 0): ?>
                                <span class="badge bg-warning text-dark rounded-pill ms-auto"><?php echo $mod_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a class="offcanvas-nav-link" href="admin.php?tab=users">
                            <i class="bi bi-people"></i> Accounts
                        </a>
                    </li>
                    <li>
                        <a class="offcanvas-nav-link" href="admin.php?tab=mindscape">
                            <i class="bi bi-bricks"></i> Mindscape
                        </a>
                    </li>
                    <li>
                        <a class="offcanvas-nav-link text-success fw-semibold" href="admin.php?tab=new">
                            <i class="bi bi-plus-circle"></i> Write Story
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>

        <!-- Auth section at bottom -->
        <div class="offcanvas-auth-section mt-auto border-top pt-3 px-3 pb-4">
            <?php if (isLoggedIn()):
                $nav_user_avatar2 = $_SESSION['user_avatar'] ?? null;
                $nav_avatar_path2 = getAvatarUrl($nav_user_avatar2 ?? 'ironman');
            ?>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="<?php echo safe($nav_avatar_path2); ?>" alt="Avatar" class="rounded-circle shadow-sm"
                         style="width:38px;height:38px;object-fit:cover;border:2px solid #16a34a;">
                    <div>
                        <div class="fw-semibold small"><?php echo safe($_SESSION['user_name'] ?? 'User'); ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?php echo safe($_SESSION['user_email']); ?></div>
                    </div>
                </div>
                <a href="profile.php" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                    <i class="bi bi-person me-1"></i> My Profile
                </a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm w-100">
                    <i class="bi bi-box-arrow-right me-1"></i> Log Out
                </a>
            <?php else: ?>
                <a href="login.html" class="btn btn-outline-success btn-sm w-100 mb-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Log In
                </a>
                <a href="register.html" class="btn btn-nsbm btn-sm text-white w-100">
                    <i class="bi bi-person-plus me-1"></i> Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
