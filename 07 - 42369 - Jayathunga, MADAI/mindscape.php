<?php
require_once "db.php";

$msg = trim($_GET['msg'] ?? '');
$error = trim($_GET['error'] ?? '');
$filter = trim($_GET['filter'] ?? 'all');
$search = trim($_GET['q'] ?? '');

// Current logged in user info
$logged_user = null;
if (isLoggedIn()) {
    $u_stmt = mysqli_prepare($conn, "SELECT id, name, student_id, role, faculty FROM users WHERE id = ? LIMIT 1");
    if ($u_stmt) {
        mysqli_stmt_bind_param($u_stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($u_stmt);
        $logged_user = mysqli_fetch_assoc(mysqli_stmt_get_result($u_stmt));
        mysqli_stmt_close($u_stmt);
    }
}

// Post handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (!empty($_GET['action']) && in_array($_GET['action'], ['admin_delete_brick', 'delete_mindscape', 'delete_brick']))) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || isset($_POST['ajax']);

    // 1. Drop a Brick (New Thought)
    if ($action === 'drop_brick') {
        // Check ban status if logged in
        if ($logged_user) {
            $active_ban = checkActiveBan($logged_user['id']);
            if ($active_ban) {
                $until = date("M d, Y h:i A", strtotime($active_ban['banned_until']));
                $err_msg = "Your account is currently suspended until $until. You cannot drop bricks on the wall.";
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $err_msg]);
                    exit;
                }
                header("Location: mindscape.php?error=" . urlencode($err_msg));
                exit;
            }
        }

        $thought = trim($_POST['thought'] ?? '');
        $brick_color = trim($_POST['brick_color'] ?? 'brick-emerald');
        $brick_size = trim($_POST['brick_size'] ?? 'standard');
        $avatar_emoji = trim($_POST['avatar_emoji'] ?? '🌱');

        // Allowed lists
        $allowed_colors = ['brick-emerald', 'brick-mint', 'brick-gold', 'brick-cyan', 'brick-slate', 'brick-sunset', 'brick-purple'];
        if (!in_array($brick_color, $allowed_colors)) $brick_color = 'brick-emerald';

        $allowed_sizes = ['standard', 'wide', 'accent'];
        if (!in_array($brick_size, $allowed_sizes)) $brick_size = 'standard';

        if (empty($thought)) {
            $err_msg = "Please write a thought before dropping a brick onto the wall.";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $err_msg]);
                exit;
            }
            header("Location: mindscape.php?error=" . urlencode($err_msg));
            exit;
        }

        if (mb_strlen($thought) > 350) {
            $err_msg = "Thoughts cannot exceed 350 characters.";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $err_msg]);
                exit;
            }
            header("Location: mindscape.php?error=" . urlencode($err_msg));
            exit;
        }

        // Determine author info
        if ($logged_user) {
            $user_id = $logged_user['id'];
            $author_name = $logged_user['name'];
            $student_id = $logged_user['student_id'] ?? '';
            $role = $logged_user['role'] ?? 'student';
            $avatar_bg = ($role === 'admin') ? 'linear-gradient(135deg, #15803d, #16a34a)' : 'linear-gradient(135deg, #0284c7, #0369a1)';
        } else {
            $user_id = null;
            $author_name = trim($_POST['author_name'] ?? '');
            if (empty($author_name)) {
                $author_name = "Campus Explorer";
            }
            $student_id = null;
            $role = "guest";
            $avatar_bg = 'linear-gradient(135deg, #64748b, #475569)';
        }

        $gif_url = trim($_POST['gif_url'] ?? '');
        if (!empty($gif_url)) {
            if (!filter_var($gif_url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $gif_url)) {
                $gif_url = null;
            }
        } else {
            $gif_url = null;
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO mindscape_thoughts (user_id, author_name, student_id, role, avatar_emoji, avatar_bg, thought, gif_url, brick_color, brick_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isssssssss", $user_id, $author_name, $student_id, $role, $avatar_emoji, $avatar_bg, $thought, $gif_url, $brick_color, $brick_size);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => $new_id, 'msg' => 'Your thought brick dropped smoothly onto the wall!']);
                    exit;
                }
                header("Location: mindscape.php?msg=" . urlencode("Brick placed on the Mindscape wall! 🧱") . "#brick-" . $new_id);
                exit;
            } else {
                $err_msg = "Database error: " . mysqli_error($conn);
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $err_msg]);
                    exit;
                }
                header("Location: mindscape.php?error=" . urlencode($err_msg));
                exit;
            }
        }
    }

    // 2. React / Like Brick
    elseif ($action === 'like_brick') {
        $brick_id = intval($_POST['brick_id'] ?? 0);
        if ($brick_id > 0) {
            mysqli_query($conn, "UPDATE mindscape_thoughts SET likes = likes + 1 WHERE id = $brick_id");
            $res = mysqli_query($conn, "SELECT likes FROM mindscape_thoughts WHERE id = $brick_id LIMIT 1");
            $new_likes = ($r = mysqli_fetch_assoc($res)) ? intval($r['likes']) : 0;
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'likes' => $new_likes]);
                exit;
            }
        }
        header("Location: mindscape.php#brick-" . $brick_id);
        exit;
    }

    // 3. Admin Delete Brick from Wall
    elseif ($action === 'admin_delete_brick' || $action === 'delete_mindscape' || $action === 'delete_brick') {
        if (!isAdmin()) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Permission denied.']);
                exit;
            }
            header("Location: mindscape.php?error=Permission+denied");
            exit;
        }
        $del_id = intval($_POST['brick_id'] ?? $_GET['brick_id'] ?? $_POST['thought_id'] ?? $_GET['thought_id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
        if ($del_id > 0) {
            mysqli_query($conn, "DELETE FROM mindscape_thoughts WHERE id = $del_id");
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'deleted_id' => $del_id]);
                exit;
            }
            header("Location: mindscape.php?msg=" . urlencode("Thought brick #$del_id removed from wall."));
            exit;
        }
    }
}

// ==========================================
// WALL STATS & BRICKS QUERY
// ==========================================
$total_bricks = 0;
$total_reactions = 0;
$st_res = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(likes) AS reactions FROM mindscape_thoughts");
if ($st_res && $row = mysqli_fetch_assoc($st_res)) {
    $total_bricks = intval($row['total'] ?? 0);
    $total_reactions = intval($row['reactions'] ?? 0);
}
// Approximate wall height / levels
$wall_level = max(1, (int)ceil($total_bricks / 3));

// Build main wall query
$wall_sql = "SELECT mt.*, u.faculty 
             FROM mindscape_thoughts mt 
             LEFT JOIN users u ON mt.user_id = u.id 
             WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $wall_sql .= " AND (mt.thought LIKE ? OR mt.author_name LIKE ? OR mt.student_id LIKE ?)";
    $like = "%" . $search . "%";
    $params = [$like, $like, $like];
    $types = "sss";
}

if ($filter === 'trending') {
    $wall_sql .= " ORDER BY mt.likes DESC, mt.created_at DESC";
} elseif ($filter === 'me' && $logged_user) {
    $wall_sql .= " AND mt.user_id = " . intval($logged_user['id']) . " ORDER BY mt.created_at DESC";
} else {
    // Default newest first (top of the wall!)
    $wall_sql .= " ORDER BY mt.created_at DESC";
}

$stmt = mysqli_prepare($conn, $wall_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$bricks_list = mysqli_stmt_get_result($stmt);

$pageTitle = "Mindscape - Universal Campus Thought Wall";
include __DIR__ . "/includes/header.php";
?>

<div class="container py-4">
    <!-- Notice Messages -->
    <?php if (!empty($msg)): ?>
        <div class="alert alert-success alert-dismissible fade show alert-auto-dismiss mb-4 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> <?php echo safe($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> <?php echo safe($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Mindscape Hero Header -->
    <div class="mindscape-hero fade-in-up">
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-8">
                <h1 class="display-6 fw-bold mb-2">Mindscape Thought Wall</h1>
                <p class="lead mb-0 text-white-50" style="font-size: 1.08rem; max-width: 650px;">
                    A living canvas where everyone can drop thoughts, campus shouts, inspirations, and ideas. 
                    Like an interactive Tetris tower, our wall grows taller with every brick added!
                </p>
            </div>
            <div class="col-lg-4 mt-3 mt-lg-0">
                <div class="d-flex gap-2 justify-content-lg-end w-100">
                    <div class="bg-black bg-opacity-25 backdrop-blur rounded-4 p-2 p-sm-3 border border-white border-opacity-10 text-center flex-fill">
                        <div class="fs-4 fs-sm-3 fw-bold text-white"><?php echo number_format($total_bricks); ?></div>
                        <div class="text-xs text-white-50 text-uppercase fw-semibold" style="font-size: 0.68rem;">Bricks</div>
                    </div>
                    <div class="bg-black bg-opacity-25 backdrop-blur rounded-4 p-2 p-sm-3 border border-white border-opacity-10 text-center flex-fill">
                        <div class="fs-4 fs-sm-3 fw-bold text-warning">Lv. <?php echo $wall_level; ?></div>
                        <div class="text-xs text-white-50 text-uppercase fw-semibold" style="font-size: 0.68rem;">Height</div>
                    </div>
                    <div class="bg-black bg-opacity-25 backdrop-blur rounded-4 p-2 p-sm-3 border border-white border-opacity-10 text-center flex-fill">
                        <div class="fs-4 fs-sm-3 fw-bold text-danger">❤️ <?php echo number_format($total_reactions); ?></div>
                        <div class="text-xs text-white-50 text-uppercase fw-semibold" style="font-size: 0.68rem;">Reactions</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Drop a Brick Composer Card -->
    <div class="mindscape-composer-card fade-in-up">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-plus-square-dotted text-success me-2"></i>Drop a Brick on the Wall
            </h5>
            <?php if ($logged_user): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 small">
                    <i class="bi bi-person-check-fill me-1"></i> Posting as <?php echo safe($logged_user['name']); ?>
                </span>
            <?php else: ?>
                <span class="badge bg-light text-secondary border rounded-pill px-3 py-1 small">
                    <a href="login.php" class="text-success text-decoration-none fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i>Log In</a>
                </span>
            <?php endif; ?>
        </div>

        <form id="dropBrickForm" method="POST" action="mindscape.php">
            <input type="hidden" name="action" value="drop_brick">

            <div class="row g-3">
                <!-- If Guest: Name input & mood picker -->
                <?php if (!$logged_user): ?>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small text-secondary">Your Name or Alias</label>
                        <input type="text" name="author_name" class="form-control rounded-3" placeholder="e.g. Kasun from Computing, or Anon Student..." maxlength="60">
                    </div>
                <?php endif; ?>

                <!-- Emoji Face Icon Picker -->
                <div class="<?php echo $logged_user ? 'col-12' : 'col-md-7'; ?>">
                    <label class="form-label fw-semibold small text-secondary">Choose Your Brick Face Icon</label>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php 
                        $emojis = ['💭', '💡', '🌱', '🚀', '🎓', '⚡', '☕', '🎨', '🔥', '🐾', '🌟', '🎧', '💻', '✨'];
                        $default_emoji = $logged_user ? ($logged_user['role'] === 'admin' ? '🏛️' : '🎓') : '💭';
                        foreach ($emojis as $idx => $em): 
                        ?>
                            <label class="btn btn-sm btn-outline-secondary rounded-circle p-0 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-size: 1.15rem; cursor: pointer;">
                                <input type="radio" name="avatar_emoji" value="<?php echo $em; ?>" class="d-none" <?php echo ($idx === 0) ? 'checked' : ''; ?>>
                                <span><?php echo $em; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Thought Text Area -->
                <div class="col-12">
                    <label class="form-label fw-semibold small text-secondary">Your Thought, Spark or Shoutout</label>
                    <textarea name="thought" id="brickThoughtInput" rows="3" class="form-control rounded-3" placeholder="What's inspiring you today? Share a campus reflection, funny moment, club update, or motivation..." maxlength="350" required></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="text-xs text-muted">Keep it respectful &amp; inspiring for all NSBM members.</span>
                        <span id="charCount" class="text-xs text-muted fw-semibold">0 / 350</span>
                    </div>
                </div>

                <!-- GIF Attachment Feature -->
                <div class="col-12">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <label class="form-label fw-semibold small text-secondary mb-0">
                            <i class="bi bi-filetype-gif text-success me-1"></i> Add an Animated GIF <span class="text-muted fw-normal text-xs">(optional)</span>
                        </label>
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 text-xs fw-semibold" id="toggleGifDrawerBtn">
                            <i class="bi bi-plus-circle me-1"></i> Choose / Paste GIF
                        </button>
                    </div>

                    <!-- Hidden input to submit with the form -->
                    <input type="hidden" name="gif_url" id="brickGifInput" value="">

                    <!-- Selected GIF Live Preview (Hidden until selected) -->
                    <div id="gifPreviewContainer" class="d-none mb-3">
                        <div class="gif-preview-card">
                            <img id="gifPreviewImg" src="" alt="Selected GIF preview">
                            <button type="button" class="gif-preview-remove-btn" id="btnRemoveGif" title="Remove GIF">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="text-xs text-success fw-semibold mt-1">
                            <i class="bi bi-check-circle-fill me-1"></i> GIF attached! Will animate on your brick.
                        </div>
                    </div>

                    <!-- Collapsible Global GIF Picker (Tenor / WhatsApp Style) -->
                    <div id="gifAttacherDrawer" class="gif-attacher-panel d-none mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold small text-dark">
                                <i class="bi bi-images text-success me-1"></i> Choose a GIF
                            </span>
                            <span class="badge bg-light text-success border border-success-subtle fw-semibold text-xs">
                                <i class="bi bi-broadcast me-1"></i> Live Tenor Engine
                            </span>
                        </div>

                        <!-- Live Search Bar -->
                        <div class="gif-search-wrap">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="gifSearchInput" class="form-control form-control-sm" placeholder="Search millions of Tenor GIFs (e.g. celebrate, study, funny cat, laugh, dance)..." autocomplete="off">
                            <button type="button" class="clear-btn d-none" id="gifClearSearchBtn" title="Clear search">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                        </div>

                        <!-- Category Pills Bar (Horizontal Scroll) -->
                        <div class="gif-categories-bar" id="gifCategoryPills">
                            <button type="button" class="gif-cat-chip active" data-query="trending">🔥 Trending</button>
                            <button type="button" class="gif-cat-chip" data-query="campus study">🎓 Campus &amp; Study</button>
                            <button type="button" class="gif-cat-chip" data-query="reactions funny">😂 Reactions</button>
                            <button type="button" class="gif-cat-chip" data-query="celebrate">🎉 Celebrate</button>
                            <button type="button" class="gif-cat-chip" data-query="coding programmer">💻 Coding</button>
                            <button type="button" class="gif-cat-chip" data-query="coffee morning">☕ Coffee</button>
                            <button type="button" class="gif-cat-chip" data-query="applause claps">👏 Claps</button>
                            <button type="button" class="gif-cat-chip" data-query="dance party">💃 Dance</button>
                            <button type="button" class="gif-cat-chip" data-query="cute cat">🐱 Cute Animals</button>
                            <button type="button" class="gif-cat-chip" data-query="fire cool">🔥 Lit / Fire</button>
                            <button type="button" class="gif-cat-chip" data-query="mind blown wow">🤯 Mind Blown</button>
                            <button type="button" class="gif-cat-chip" data-query="thumbs up agree">👍 Thumbs Up</button>
                        </div>

                        <!-- Results Container with Grid -->
                        <div class="gif-results-container" id="gifResultsContainer">
                            <div class="text-center py-4 text-muted small" id="gifLoader">
                                <div class="spinner-border spinner-border-sm text-success me-1" role="status"></div> Loading GIFs...
                            </div>
                            <div class="gif-results-grid d-none" id="gifResultsGrid"></div>
                            <div class="text-center py-4 text-muted small d-none" id="gifEmptyState">
                                <i class="bi bi-emoji-neutral d-block fs-4 text-secondary mb-1"></i>
                                No matching GIFs found. Try another search term!
                            </div>
                        </div>

                        <!-- Direct Link Option at bottom -->
                        <div class="pt-2 mt-2 border-top">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label text-muted text-xs mb-0">Or enter any external GIF URL directly:</label>
                            </div>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                <input type="url" id="customGifUrlInput" class="form-control" placeholder="https://media.giphy.com/... or https://tenor.com/...">
                                <button type="button" class="btn btn-outline-success" id="btnApplyCustomGif">
                                    <i class="bi bi-check-lg me-1"></i> Attach
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Brick Palette (Color) Selection -->
                <div class="col-md-8">
                    <label class="form-label fw-semibold small text-secondary">Brick Color Theme</label>
                    <div class="color-picker-group">
                        <input type="radio" name="brick_color" id="c_emerald" value="brick-emerald" class="d-none color-option-input" checked>
                        <label for="c_emerald" class="color-option-label" style="background: #dcfce7; color: #15803d;">🌿 Emerald</label>

                        <input type="radio" name="brick_color" id="c_mint" value="brick-mint" class="d-none color-option-input">
                        <label for="c_mint" class="color-option-label" style="background: #ccfbf1; color: #0f766e;">🍃 Fresh Mint</label>

                        <input type="radio" name="brick_color" id="c_gold" value="brick-gold" class="d-none color-option-input">
                        <label for="c_gold" class="color-option-label" style="background: #fef08a; color: #854d0e;">✨ Gold Spark</label>

                        <input type="radio" name="brick_color" id="c_cyan" value="brick-cyan" class="d-none color-option-input">
                        <label for="c_cyan" class="color-option-label" style="background: #e0f2fe; color: #0369a1;">🌊 Sky Ocean</label>

                        <input type="radio" name="brick_color" id="c_sunset" value="brick-sunset" class="d-none color-option-input">
                        <label for="c_sunset" class="color-option-label" style="background: #ffedd5; color: #c2410c;">🌅 Sunset</label>

                        <input type="radio" name="brick_color" id="c_purple" value="brick-purple" class="d-none color-option-input">
                        <label for="c_purple" class="color-option-label" style="background: #f3e8ff; color: #7e22ce;">🔮 Creative</label>

                        <input type="radio" name="brick_color" id="c_slate" value="brick-slate" class="d-none color-option-input">
                        <label for="c_slate" class="color-option-label" style="background: #f1f5f9; color: #334155;">🌑 Deep Slate</label>
                    </div>
                </div>

                <!-- Brick Size Modifier -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-secondary">Brick Style</label>
                    <select name="brick_size" class="form-select form-select-sm rounded-3">
                        <option value="standard" selected>Standard Brick</option>
                        <option value="wide">Wide Brick (Spans across)</option>
                        <option value="accent">Highlighted Accent Brick</option>
                    </select>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top">
                    <button type="submit" id="btnDropBrick" class="btn btn-nsbm px-4 py-2 rounded-pill shadow-sm fw-semibold">
                        <i class="bi bi-bricks me-1"></i> Drop Brick on Wall
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Wall Controls & Filters Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 gap-sm-3 mb-3" id="wall">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-dark fs-5"><i class="bi bi-grid-3x3-gap-fill text-success me-1"></i> THE WALL</span>
            <span class="badge bg-light text-secondary border rounded-pill"><?php echo mysqli_num_rows($bricks_list); ?> bricks</span>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 w-100 w-sm-auto justify-content-between justify-content-sm-end">
            <!-- Filter Pills -->
            <div class="btn-group btn-group-sm shadow-sm" role="group">
                <a href="mindscape.php?filter=all<?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?>#wall" class="btn <?php echo ($filter === 'all') ? 'btn-nsbm' : 'btn-outline-secondary'; ?>">
                    All Bricks
                </a>
                <a href="mindscape.php?filter=trending<?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?>#wall" class="btn <?php echo ($filter === 'trending') ? 'btn-nsbm' : 'btn-outline-secondary'; ?>">
                    <i class="bi bi-fire text-danger me-1"></i> Trending
                </a>
                <?php if ($logged_user): ?>
                    <a href="mindscape.php?filter=me<?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?>#wall" class="btn <?php echo ($filter === 'me') ? 'btn-nsbm' : 'btn-outline-secondary'; ?>">
                        My Bricks
                    </a>
                <?php endif; ?>
            </div>

            <!-- Search Form -->
            <form method="GET" action="mindscape.php#wall" class="d-flex gap-1 flex-grow-1 flex-sm-grow-0" style="min-width: 140px;">
                <input type="hidden" name="filter" value="<?php echo safe($filter); ?>">
                <input type="text" name="q" class="form-control form-control-sm rounded-pill w-100" placeholder="Search wall..." value="<?php echo safe($search); ?>" style="max-width: 160px;">
                <button type="submit" class="btn btn-outline-secondary btn-sm rounded-circle flex-shrink-0"><i class="bi bi-search"></i></button>
                <?php if (!empty($search)): ?>
                    <a href="mindscape.php?filter=<?php echo safe($filter); ?>#wall" class="btn btn-outline-danger btn-sm rounded-circle flex-shrink-0" title="Clear"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- The Brick Masonry Wall Grid -->
    <div class="mindscape-wall" id="mindscapeWallGrid">
        <?php if ($bricks_list && mysqli_num_rows($bricks_list) > 0): ?>
            <?php 
            $brick_num = mysqli_num_rows($bricks_list);
            while ($b = mysqli_fetch_assoc($bricks_list)): 
                $brick_id = $b['id'];
                $emoji = !empty($b['avatar_emoji']) ? $b['avatar_emoji'] : '🌱';
                $bg = !empty($b['avatar_bg']) ? $b['avatar_bg'] : 'linear-gradient(135deg, #16a34a, #059669)';
                $color_class = safe($b['brick_color'] ?? 'brick-emerald');
                $size_class = safe($b['brick_size'] ?? 'standard');
                $time_str = date("M d, g:i A", strtotime($b['created_at']));
                $likes_count = intval($b['likes'] ?? 0);
            ?>
                <div class="mindscape-brick <?php echo $color_class; ?> <?php echo ($size_class === 'accent') ? 'brick-accent' : ''; ?>" id="brick-<?php echo $brick_id; ?>">
                    <!-- Brick Header -->
                    <div class="brick-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="brick-avatar" style="background: <?php echo safe($bg); ?>; color: #fff;">
                                <span><?php echo safe($emoji); ?></span>
                            </div>
                            <div>
                                <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                                    <?php if (!empty($b['user_id'])): ?>
                                        <a href="profile.php?id=<?php echo $b['user_id']; ?>" class="text-decoration-none text-dark hover-green">
                                            <?php echo safe($b['author_name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span><?php echo safe($b['author_name']); ?></span>
                                    <?php endif; ?>

                                    <?php if ($b['role'] === 'admin'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle text-xxs">Admin</span>
                                    <?php elseif ($b['role'] === 'student'): ?>
                                        <span class="badge bg-light text-muted border text-xxs">Student</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border text-xxs">Explorer</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted text-xxs">
                                    <?php if (!empty($b['student_id'])): ?>
                                        <span class="font-monospace me-1"><?php echo safe($b['student_id']); ?> &bull;</span>
                                    <?php endif; ?>
                                    <span><?php echo $time_str; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Direct Remove Button -->
                        <?php if (isAdmin()): ?>
                            <form method="POST" action="mindscape.php" class="m-0 d-inline" onsubmit="return confirm('Remove this thought brick from the Mindscape wall?');">
                                <input type="hidden" name="action" value="admin_delete_brick">
                                <input type="hidden" name="brick_id" value="<?php echo $brick_id; ?>">
                                <input type="hidden" name="thought_id" value="<?php echo $brick_id; ?>">
                                <button type="submit" class="brick-admin-del" title="Admin Remove Brick">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Thought Body -->
                    <div class="brick-content">
                        <?php echo nl2br(safe($b['thought'])); ?>
                    </div>

                    <!-- Attached GIF if present -->
                    <?php if (!empty($b['gif_url'])): ?>
                        <div class="brick-gif-wrapper">
                            <img src="<?php echo safe($b['gif_url']); ?>" alt="Attached GIF" class="brick-gif-img" loading="lazy" onerror="this.closest('.brick-gif-wrapper').style.display='none';">
                            <span class="brick-gif-badge"><i class="bi bi-filetype-gif me-1"></i>GIF</span>
                        </div>
                    <?php endif; ?>

                    <!-- Brick Footer -->
                    <div class="brick-footer">
                        <span class="text-muted text-xxs font-monospace">
                            #Brick-<?php echo $brick_id; ?>
                        </span>

                        <!-- Like Reaction Button -->
                        <button type="button" class="brick-like-btn" onclick="likeBrick(<?php echo $brick_id; ?>, this);" data-brick-id="<?php echo $brick_id; ?>">
                            <i class="bi bi-heart-fill"></i>
                            <span class="like-counter"><?php echo $likes_count; ?></span>
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="w-100 py-5 text-center bg-white rounded-4 border p-5">
                <i class="bi bi-bricks display-3 text-secondary opacity-50 mb-3 d-block"></i>
                <h5 class="fw-bold text-dark">No Bricks on this Layer Yet</h5>
                <p class="text-muted small mb-3">Be the first to drop an inspiring thought brick onto the wall!</p>
                <a href="#dropBrickForm" class="btn btn-nsbm btn-sm rounded-pill px-4" onclick="document.getElementById('brickThoughtInput').focus();">
                    <i class="bi bi-plus-circle me-1"></i> Write a Thought
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Character countdown
const thoughtInput = document.getElementById('brickThoughtInput');
const charCount = document.getElementById('charCount');
if (thoughtInput && charCount) {
    thoughtInput.addEventListener('input', function() {
        charCount.textContent = this.value.length + ' / 350';
    });
}

// Emoji selection active styling
document.querySelectorAll('input[name="avatar_emoji"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('input[name="avatar_emoji"]').forEach(r => {
            r.closest('.btn').classList.remove('btn-success', 'text-white');
            r.closest('.btn').classList.add('btn-outline-secondary');
        });
        if (this.checked) {
            this.closest('.btn').classList.remove('btn-outline-secondary');
            this.closest('.btn').classList.add('btn-success', 'text-white');
        }
    });
    if (radio.checked) {
        radio.closest('.btn').classList.remove('btn-outline-secondary');
        radio.closest('.btn').classList.add('btn-success', 'text-white');
    }
});

// Like Brick AJAX handler
function likeBrick(brickId, btn) {
    if (btn.classList.contains('liked')) {
        return; // Already liked in this session
    }
    btn.classList.add('liked');
    const counter = btn.querySelector('.like-counter');
    const current = parseInt(counter.textContent) || 0;
    counter.textContent = current + 1;

    // Send async reaction
    const formData = new FormData();
    formData.append('action', 'like_brick');
    formData.append('brick_id', brickId);
    formData.append('ajax', '1');

    fetch('mindscape.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    }).then(res => res.json()).then(data => {
        if (data && data.success && data.likes) {
            counter.textContent = data.likes;
        }
    }).catch(err => {
        console.log('Reaction registered locally');
    });
}

// ==========================================
// Global GIF Picker Engine (WhatsApp / Insta Style)
// ==========================================
const toggleGifBtn = document.getElementById('toggleGifDrawerBtn');
const gifDrawer = document.getElementById('gifAttacherDrawer');
const brickGifInput = document.getElementById('brickGifInput');
const gifPreviewContainer = document.getElementById('gifPreviewContainer');
const gifPreviewImg = document.getElementById('gifPreviewImg');
const btnRemoveGif = document.getElementById('btnRemoveGif');
const customGifUrlInput = document.getElementById('customGifUrlInput');
const btnApplyCustomGif = document.getElementById('btnApplyCustomGif');

const gifSearchInput = document.getElementById('gifSearchInput');
const gifClearSearchBtn = document.getElementById('gifClearSearchBtn');
const gifCategoryPills = document.getElementById('gifCategoryPills');
const gifResultsGrid = document.getElementById('gifResultsGrid');
const gifLoader = document.getElementById('gifLoader');
const gifEmptyState = document.getElementById('gifEmptyState');

// Resilient curated GIF database for offline or instant fallback
const curatedLibrary = [
    { q: "trending", url: "https://media.giphy.com/media/26u4cqiYI30juCOGY/giphy.gif", title: "Celebration" },
    { q: "trending", url: "https://media.giphy.com/media/g9582DNuQppxC/giphy.gif", title: "Cheers Party" },
    { q: "trending", url: "https://media.giphy.com/media/mi6DsSSNKDbUY/giphy.gif", title: "Rocket Launch" },
    { q: "trending", url: "https://media.giphy.com/media/nbvFVPiEiJH6JOGIok/giphy.gif", title: "Standing Ovation" },
    { q: "trending", url: "https://media.giphy.com/media/blSTtZehjAZ8I/giphy.gif", title: "Dance Party" },
    { q: "trending", url: "https://media.giphy.com/media/xT0xezQGU5xCDJuCPe/giphy.gif", title: "Mind Blown" },
    { q: "campus study", url: "https://media.giphy.com/media/26u4cqiYI30juCOGY/giphy.gif", title: "Graduation" },
    { q: "campus study", url: "https://media.giphy.com/media/3o7TKMt1VVNkHV2PaE/giphy.gif", title: "Green Campus" },
    { q: "campus study", url: "https://media.giphy.com/media/l2YWgBNdssBwnnd5e/giphy.gif", title: "Studying Hard" },
    { q: "campus study", url: "https://media.giphy.com/media/l0HlHFRbmaZtBRhXG/giphy.gif", title: "Library Focus" },
    { q: "coding programmer", url: "https://media.giphy.com/media/3oKIPnAiaMCws8nOsE/giphy.gif", title: "Hackathon Coding" },
    { q: "coding programmer", url: "https://media.giphy.com/media/ule4akeXnUSWs/giphy.gif", title: "Matrix Terminal" },
    { q: "coding programmer", url: "https://media.giphy.com/media/13HgwGsXF0aiGY/giphy.gif", title: "Bug Fixed" },
    { q: "coding programmer", url: "https://media.giphy.com/media/LmN8OYiY4m0X85K0Zz/giphy.gif", title: "Developer Flow" },
    { q: "coffee morning", url: "https://media.giphy.com/media/hPTZgtzfRIB5Nfb5rL/giphy.gif", title: "Morning Fuel" },
    { q: "coffee morning", url: "https://media.giphy.com/media/oZEBLugoTdzxK/giphy.gif", title: "Fresh Espresso" },
    { q: "applause claps", url: "https://media.giphy.com/media/nbvFVPiEiJH6JOGIok/giphy.gif", title: "Clapping Hands" },
    { q: "applause claps", url: "https://media.giphy.com/media/7rj2ZgttvgomY/giphy.gif", title: "Bravo Claps" },
    { q: "reactions funny", url: "https://media.giphy.com/media/10JhviFuU2gWD6/giphy.gif", title: "Laughing Out Loud" },
    { q: "reactions funny", url: "https://media.giphy.com/media/dC9DTdqPmRnlS/giphy.gif", title: "Haha Yes" },
    { q: "reactions funny", url: "https://media.giphy.com/media/3ohzdQ1IynzclJldUQ/giphy.gif", title: "Shocked Face" },
    { q: "celebrate", url: "https://media.giphy.com/media/26u4cqiYI30juCOGY/giphy.gif", title: "Graduate" },
    { q: "celebrate", url: "https://media.giphy.com/media/g9582DNuQppxC/giphy.gif", title: "Celebrate!" },
    { q: "celebrate", url: "https://media.giphy.com/media/artj92V8o75VPL7AeQ/giphy.gif", title: "Confetti Pop" },
    { q: "dance party", url: "https://media.giphy.com/media/blSTtZehjAZ8I/giphy.gif", title: "Dancing Moves" },
    { q: "dance party", url: "https://media.giphy.com/media/l0MYt5jPR6QX5pnqM/giphy.gif", title: "Weekend Vibe" },
    { q: "cute cat", url: "https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif", title: "Campus Cat" },
    { q: "cute cat", url: "https://media.giphy.com/media/mlvseq9yvZhba/giphy.gif", title: "Cute Kitten" },
    { q: "fire cool", url: "https://media.giphy.com/media/3o72FfM5HJydzafgUE/giphy.gif", title: "Fire Flame" },
    { q: "fire cool", url: "https://media.giphy.com/media/26BRv0ThflsHCqDrG/giphy.gif", title: "Lit" },
    { q: "mind blown wow", url: "https://media.giphy.com/media/xT0xezQGU5xCDJuCPe/giphy.gif", title: "Mind Blown" },
    { q: "thumbs up agree", url: "https://media.giphy.com/media/111ebonMs90YLu/giphy.gif", title: "Approved Thumbs Up" }
];

let gifSearchTimer = null;
let currentGifQuery = 'trending';
let hasLoadedGifs = false;

function setGif(url) {
    if (!url) {
        brickGifInput.value = '';
        gifPreviewContainer.classList.add('d-none');
        gifPreviewImg.src = '';
        document.querySelectorAll('.gif-item-card').forEach(t => t.classList.remove('selected'));
        return;
    }
    brickGifInput.value = url;
    gifPreviewImg.src = url;
    gifPreviewContainer.classList.remove('d-none');
    gifDrawer.classList.add('d-none');
}

function renderGifs(items) {
    gifLoader.classList.add('d-none');
    gifResultsGrid.innerHTML = '';

    if (!items || items.length === 0) {
        gifResultsGrid.classList.add('d-none');
        gifEmptyState.classList.remove('d-none');
        return;
    }

    gifEmptyState.classList.add('d-none');
    gifResultsGrid.classList.remove('d-none');

    items.forEach(item => {
        const card = document.createElement('div');
        card.className = 'gif-item-card';
        if (brickGifInput.value === item.url) {
            card.classList.add('selected');
        }
        card.innerHTML = `
            <img src="${item.preview || item.url}" alt="${item.title || 'GIF'}" loading="lazy">
            <div class="gif-item-title">${item.title || 'GIF'}</div>
        `;
        card.addEventListener('click', function() {
            document.querySelectorAll('.gif-item-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            setGif(item.url);
        });
        gifResultsGrid.appendChild(card);
    });
}

// Fetch directly from live Tenor backend endpoint
async function fetchGlobalGifs(query) {
    gifLoader.classList.remove('d-none');
    gifResultsGrid.classList.add('d-none');
    gifEmptyState.classList.add('d-none');

    const cleanQ = (query || 'trending').trim().toLowerCase();

    try {
        const res = await fetch('api_gif_search.php?q=' + encodeURIComponent(cleanQ));
        if (res.ok) {
            const data = await res.json();
            if (data && data.success && data.results && data.results.length > 0) {
                renderGifs(data.results);
                return;
            }
        }
    } catch (e) {
        // Fallback to local curated library if offline
    }

    // Curated Library Fallback
    const filtered = curatedLibrary.filter(item => {
        if (cleanQ === 'trending') return true;
        return item.q.toLowerCase().includes(cleanQ) || item.title.toLowerCase().includes(cleanQ) || cleanQ.includes(item.q.toLowerCase());
    });

    renderGifs(filtered.length > 0 ? filtered : curatedLibrary);
}

// Drawer open toggle
if (toggleGifBtn && gifDrawer) {
    toggleGifBtn.addEventListener('click', function() {
        gifDrawer.classList.toggle('d-none');
        if (!gifDrawer.classList.contains('d-none') && !hasLoadedGifs) {
            hasLoadedGifs = true;
            fetchGlobalGifs('trending');
        }
    });
}

// Category Pills click
if (gifCategoryPills) {
    gifCategoryPills.addEventListener('click', function(e) {
        const btn = e.target.closest('.gif-cat-chip');
        if (!btn) return;
        gifCategoryPills.querySelectorAll('.gif-cat-chip').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const q = btn.getAttribute('data-query');
        currentGifQuery = q;
        if (gifSearchInput) {
            gifSearchInput.value = '';
            gifClearSearchBtn.classList.add('d-none');
        }
        fetchGlobalGifs(q);
    });
}

// Live Search with Debounce
if (gifSearchInput) {
    gifSearchInput.addEventListener('input', function() {
        const q = this.value.trim();
        if (q.length > 0) {
            gifClearSearchBtn.classList.remove('d-none');
        } else {
            gifClearSearchBtn.classList.add('d-none');
        }

        clearTimeout(gifSearchTimer);
        gifSearchTimer = setTimeout(() => {
            if (q.length === 0) {
                // Revert to active category pill
                const activePill = gifCategoryPills.querySelector('.gif-cat-chip.active');
                fetchGlobalGifs(activePill ? activePill.getAttribute('data-query') : 'trending');
            } else {
                fetchGlobalGifs(q);
            }
        }, 350);
    });
}

// Clear Search button
if (gifClearSearchBtn && gifSearchInput) {
    gifClearSearchBtn.addEventListener('click', function() {
        gifSearchInput.value = '';
        gifClearSearchBtn.classList.add('d-none');
        const activePill = gifCategoryPills.querySelector('.gif-cat-chip.active');
        fetchGlobalGifs(activePill ? activePill.getAttribute('data-query') : 'trending');
        gifSearchInput.focus();
    });
}

// Custom direct GIF URL apply
if (btnApplyCustomGif && customGifUrlInput) {
    btnApplyCustomGif.addEventListener('click', function() {
        const val = customGifUrlInput.value.trim();
        if (val) {
            setGif(val);
            customGifUrlInput.value = '';
        }
    });
    customGifUrlInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            btnApplyCustomGif.click();
        }
    });
}

// Remove selected GIF
if (btnRemoveGif) {
    btnRemoveGif.addEventListener('click', function() {
        setGif('');
    });
}
</script>

<?php include __DIR__ . "/includes/footer.php"; ?>
