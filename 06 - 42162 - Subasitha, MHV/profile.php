<?php
require_once "db.php";

// Determine which user's profile to display
$target_user_id = intval($_GET['id'] ?? ($_SESSION['user_id'] ?? 0));

if ($target_user_id <= 0) {
    header("Location: login.php?msg=Please+log+in+to+view+your+profile");
    exit;
}

// Fetch user data
$u_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($u_stmt, "i", $target_user_id);
mysqli_stmt_execute($u_stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($u_stmt));
mysqli_stmt_close($u_stmt);

if (!$user_data) {
    header("Location: index.php?msg=User+profile+not+found");
    exit;
}

$is_own_profile = isLoggedIn() && ($_SESSION['user_id'] == $target_user_id || isAdmin());

$msg = trim($_GET['msg'] ?? '');
$error = trim($_GET['error'] ?? '');
$active_tab = trim($_GET['tab'] ?? 'overview');

// Profile update handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_own_profile) {
    $action = $_POST['action'] ?? '';

    // 1. Update Profile Info & Socials
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $student_id = trim($_POST['student_id'] ?? '');
        $faculty = trim($_POST['faculty'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $social_facebook = trim($_POST['social_facebook'] ?? '');
        $social_twitter = trim($_POST['social_twitter'] ?? '');
        $avatar = trim($_POST['avatar'] ?? ($user_data['avatar'] ?? 'ironman'));
        $allowed_avatars = array_keys(getAvatarList());
        if (!in_array($avatar, $allowed_avatars)) {
            $avatar = 'ironman';
        }

        if (empty($name)) {
            $error = "Full Name cannot be empty.";
            $active_tab = 'edit';
        } else {
            $up_stmt = mysqli_prepare($conn, "UPDATE users SET 
                name = ?, student_id = ?, faculty = ?, phone = ?, bio = ?, avatar = ?, 
                social_facebook = ?, social_twitter = ?, social_linkedin = ?, social_instagram = ? 
                WHERE id = ?");
            mysqli_stmt_bind_param($up_stmt, "ssssssssssi", 
                $name, $student_id, $faculty, $phone, $bio, $avatar, 
                $social_facebook, $social_twitter, $social_linkedin, $social_instagram, 
                $target_user_id);

            if (mysqli_stmt_execute($up_stmt)) {
                if ($_SESSION['user_id'] == $target_user_id) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_avatar'] = $avatar;
                }
                mysqli_stmt_close($up_stmt);
                header("Location: profile.php?id=$target_user_id&msg=" . urlencode("Profile updated successfully!"));
                exit;
            } else {
                $error = "Failed to update profile: " . mysqli_error($conn);
                $active_tab = 'edit';
            }
        }
    }

    // 2. Change Password
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in all password fields.";
            $active_tab = 'password';
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters.";
            $active_tab = 'password';
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
            $active_tab = 'password';
        } else {
            // Verify current password
            if (password_verify($current_password, $user_data['password']) || $current_password === $user_data['password'] || md5($current_password) === $user_data['password']) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $pw_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($pw_stmt, "si", $new_hash, $target_user_id);
                if (mysqli_stmt_execute($pw_stmt)) {
                    mysqli_stmt_close($pw_stmt);
                    header("Location: profile.php?id=$target_user_id&msg=" . urlencode("Password changed successfully!"));
                    exit;
                } else {
                    $error = "Failed to update password.";
                    $active_tab = 'password';
                }
            } else {
                $error = "Incorrect current password.";
                $active_tab = 'password';
            }
        }
    }
}

// Fetch articles by this author
if ($is_own_profile) {
    // If own profile or admin, show all statuses
    $art_stmt = mysqli_prepare($conn, "SELECT a.*, c.name AS category_name, c.slug AS category_slug 
                                       FROM articles a 
                                       JOIN categories c ON a.category_id = c.id 
                                       WHERE a.author_id = ? 
                                       ORDER BY a.created_at DESC");
} else {
    // Public view only shows approved articles
    $art_stmt = mysqli_prepare($conn, "SELECT a.*, c.name AS category_name, c.slug AS category_slug 
                                       FROM articles a 
                                       JOIN categories c ON a.category_id = c.id 
                                       WHERE a.author_id = ? AND a.status = 'approved' 
                                       ORDER BY a.created_at DESC");
}
mysqli_stmt_bind_param($art_stmt, "i", $target_user_id);
mysqli_stmt_execute($art_stmt);
$user_articles = mysqli_stmt_get_result($art_stmt);
$article_count = mysqli_num_rows($user_articles);

// Calculate total views for this author
$total_views = 0;
if ($user_articles && $article_count > 0) {
    while ($art = mysqli_fetch_assoc($user_articles)) {
        $total_views += intval($art['views'] ?? 0);
    }
    mysqli_data_seek($user_articles, 0);
}

$pageTitle = safe($user_data['name']) . " - User Profile";
include __DIR__ . "/includes/header.php";

$user_avatar = $user_data['avatar'] ?? 'ironman';
$avatar_list = getAvatarList();
?>

<div class="container py-4">
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

    <div class="profile-card-modern fade-in-up">
        <div class="profile-cover-modern"></div>

        <div class="profile-body-modern">
            <div class="profile-header-row">
                <div class="d-flex align-items-start gap-3 flex-wrap flex-sm-nowrap">
                    <div class="profile-avatar-box position-relative p-0 overflow-hidden" style="background: transparent; border: 3px solid rgba(22, 163, 74, 0.4); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);">
                        <img src="<?php echo safe(getAvatarUrl($user_avatar)); ?>" alt="Avatar" class="w-100 h-100 object-fit-cover rounded-circle">
                    </div>
                    <div class="profile-info-content">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h3 class="fw-bold mb-0 text-dark"><?php echo safe($user_data['name']); ?></h3>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill text-uppercase px-3 py-1 small fw-bold">
                                <i class="bi bi-patch-check-fill me-1"></i><?php echo safe($user_data['role']); ?>
                            </span>
                            <?php if (!empty($user_data['is_reported']) && $user_data['is_reported'] == 1): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2">Reported</span>
                            <?php endif; ?>
                        </div>

                        <!-- Academic Chips -->
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                            <?php if (!empty($user_data['faculty'])): ?>
                                <span class="profile-chip">
                                    <i class="bi bi-mortarboard-fill"></i> <?php echo safe($user_data['faculty']); ?>
                                </span>
                            <?php else: ?>
                                <span class="profile-chip">
                                    <i class="bi bi-mortarboard-fill"></i> NSBM Green University
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($user_data['student_id'])): ?>
                                <span class="profile-chip">
                                    <i class="bi bi-card-heading"></i> <?php echo safe($user_data['student_id']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Actions -->
                <div class="d-flex align-items-center gap-2 profile-actions-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm" onclick="navigator.clipboard.writeText(window.location.href); alert('Profile link copied to clipboard!');" title="Copy profile link">
                        <i class="bi bi-share me-1"></i> Share
                    </button>
                    <?php if ($is_own_profile): ?>
                        <?php if ($active_tab !== 'edit'): ?>
                            <a href="profile.php?id=<?php echo $target_user_id; ?>&tab=edit" class="btn btn-nsbm btn-sm rounded-pill px-3 shadow-sm">
                                <i class="bi bi-pencil me-1"></i> Edit Profile
                            </a>
                        <?php else: ?>
                            <a href="profile.php?id=<?php echo $target_user_id; ?>&tab=overview" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                <i class="bi bi-arrow-left me-1"></i> View Profile
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bio Text -->
            <?php if (!empty($user_data['bio'])): ?>
                <div class="profile-bio-box mb-3 text-secondary">
                    <i class="bi bi-quote fs-4 text-success opacity-50 me-1"></i>
                    <?php echo nl2br(safe($user_data['bio'])); ?>
                </div>
            <?php elseif ($is_own_profile): ?>
                <div class="profile-bio-box profile-bio-empty mb-3">
                    <span class="text-muted small"><i class="bi bi-pencil-square me-1"></i> You haven't added a bio yet. <a href="profile.php?id=<?php echo $target_user_id; ?>&tab=edit" class="text-success fw-semibold text-decoration-none">Add a bio</a> to introduce yourself to readers and peers.</span>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="profile-stats-grid">
                <div class="profile-stat-item">
                    <div class="profile-stat-icon">
                        <i class="bi bi-journal-richtext"></i>
                    </div>
                    <div>
                        <div class="profile-stat-number"><?php echo number_format($article_count); ?></div>
                        <div class="profile-stat-label">Published Stories</div>
                    </div>
                </div>

                <div class="profile-stat-item">
                    <div class="profile-stat-icon">
                        <i class="bi bi-eye"></i>
                    </div>
                    <div>
                        <div class="profile-stat-number"><?php echo number_format($total_views); ?></div>
                        <div class="profile-stat-label">Total Readers</div>
                    </div>
                </div>

                <div class="profile-stat-item">
                    <div class="profile-stat-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div>
                        <div class="profile-stat-number text-capitalize"><?php echo safe($user_data['role']); ?></div>
                        <div class="profile-stat-label">Portal Account</div>
                    </div>
                </div>

                <div class="profile-stat-item">
                    <div class="profile-stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div>
                        <div class="profile-stat-number"><?php echo date("M Y", strtotime($user_data['created_at'])); ?></div>
                        <div class="profile-stat-label">Member Since</div>
                    </div>
                </div>
            </div>

            <!-- Contact & Social Connectivity Bar -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-2">
                <div class="d-flex flex-wrap align-items-center gap-3 text-secondary small">
                    <span class="d-inline-flex align-items-center gap-1">
                        <i class="bi bi-envelope-at text-success"></i> <?php echo safe($user_data['email']); ?>
                    </span>
                    <?php if (!empty($user_data['phone'])): ?>
                        <span class="d-inline-flex align-items-center gap-1">
                            <i class="bi bi-telephone text-success"></i> <?php echo safe($user_data['phone']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Social Icons -->
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($user_data['social_facebook'])): ?>
                        <a href="<?php echo safe($user_data['social_facebook']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-btn" title="Facebook">
                            <i class="bi bi-facebook"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($user_data['social_twitter'])): ?>
                        <a href="<?php echo safe($user_data['social_twitter']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-btn" title="Twitter / X">
                            <i class="bi bi-twitter-x"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($user_data['social_linkedin'])): ?>
                        <a href="<?php echo safe($user_data['social_linkedin']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-btn" title="LinkedIn">
                            <i class="bi bi-linkedin"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($user_data['social_instagram'])): ?>
                        <a href="<?php echo safe($user_data['social_instagram']); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-btn" title="Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern Segment Navigation Tabs -->
    <div class="profile-nav-pills fade-in-up">
        <a class="nav-link <?php echo ($active_tab === 'overview') ? 'active' : ''; ?>" href="profile.php?id=<?php echo $target_user_id; ?>&tab=overview">
            <i class="bi bi-grid-fill"></i> Stories & Articles (<?php echo $article_count; ?>)
        </a>
        <?php if ($is_own_profile): ?>
            <a class="nav-link <?php echo ($active_tab === 'edit') ? 'active' : ''; ?>" href="profile.php?id=<?php echo $target_user_id; ?>&tab=edit">
                <i class="bi bi-sliders"></i> Edit Profile
            </a>
            <a class="nav-link <?php echo ($active_tab === 'password') ? 'active' : ''; ?>" href="profile.php?id=<?php echo $target_user_id; ?>&tab=password">
                <i class="bi bi-shield-lock"></i> Security & Password
            </a>
        <?php endif; ?>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: ARTICLES OVERVIEW -->
    <!-- ========================================== -->
    <?php if ($active_tab === 'overview'): ?>
        <div class="row g-4 fade-in-up">
            <?php if ($user_articles && $article_count > 0): ?>
                <?php while ($article = mysqli_fetch_assoc($user_articles)): 
                    $image = !empty($article['image_url']) ? $article['image_url'] : 'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&auto=format&fit=crop&q=80';
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="news-card h-100 d-flex flex-column">
                            <a href="view_article.php?id=<?php echo $article['id']; ?>">
                                <img src="<?php echo safe($image); ?>" class="news-card-img" alt="<?php echo safe($article['title']); ?>" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&auto=format&fit=crop&q=80';">
                            </a>
                            <div class="news-card-body d-flex flex-column flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge badge-category badge-<?php echo strtolower($article['category_slug']); ?>">
                                        <?php echo safe($article['category_name']); ?>
                                    </span>
                                    <?php if ($is_own_profile): ?>
                                        <span class="badge badge-<?php echo strtolower($article['status']); ?>">
                                            <?php echo ucfirst($article['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h6 class="news-card-title mb-2">
                                    <a href="view_article.php?id=<?php echo $article['id']; ?>" class="text-decoration-none text-dark">
                                        <?php echo safe($article['title']); ?>
                                    </a>
                                </h6>

                                <div class="news-meta mt-auto pt-3 border-top d-flex justify-content-between align-items-center text-muted small">
                                    <div>
                                        <i class="bi bi-clock me-1"></i> <?php echo date("M d, Y", strtotime($article['created_at'])); ?>
                                    </div>
                                    <div class="text-end">
                                        <i class="bi bi-eye me-1"></i> <?php echo number_format($article['views']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <div class="p-5 bg-white rounded-4 border border-light-subtle shadow-sm mx-auto" style="max-width: 450px;">
                        <i class="bi bi-journal-x display-3 text-secondary opacity-50 mb-3 d-block"></i>
                        <h5 class="fw-bold text-dark">No Published Stories Yet</h5>
                        <p class="text-muted small mb-4">This profile does not have any published articles available yet.</p>
                        <?php if ($is_own_profile && !isAdmin()): ?>
                            <a href="submit_article.php" class="btn btn-nsbm btn-sm rounded-pill px-4">
                                <i class="bi bi-pencil-square me-1"></i> Submit a News Story
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <!-- ========================================== -->
    <!-- TAB 2: EDIT PROFILE & SOCIALS -->
    <!-- ========================================== -->
    <?php elseif ($active_tab === 'edit' && $is_own_profile): ?>
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden fade-in-up">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-sliders me-2 text-success"></i>Edit Profile Details</h5>
                <span class="badge bg-light text-secondary border">Account Settings</span>
            </div>
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="profile.php?id=<?php echo $target_user_id; ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="avatar-picker-section mb-4">
                        <label class="form-label fw-bold text-dark small mb-3 d-flex align-items-center">
                            <i class="bi bi-person-circle text-success me-2 fs-6"></i> Select a PFP icon
                        </label>
                        <div class="avatar-grid-icons-only">
                            <?php foreach ($avatar_list as $a_key => $a_data): 
                                $is_selected = ($user_avatar === $a_key);
                            ?>
                                <label class="avatar-icon-btn" title="Select a PFP icon">
                                    <input type="radio" name="avatar" value="<?php echo safe($a_key); ?>" <?php echo $is_selected ? 'checked' : ''; ?>>
                                    <div class="avatar-icon-circle">
                                        <img src="<?php echo safe($a_data['file']); ?>" alt="PFP Icon">
                                        <span class="avatar-icon-check"><i class="bi bi-check"></i></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-person-badge text-success me-1"></i> Personal &amp; Campus Details
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Full Name *</label>
                            <input type="text" name="name" class="form-control rounded-3" value="<?php echo safe($user_data['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Address</label>
                            <input type="email" class="form-control rounded-3 bg-light" value="<?php echo safe($user_data['email']); ?>" disabled readonly>
                            <div class="form-text small">Registered university email cannot be changed.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Student / Staff ID</label>
                            <input type="text" name="student_id" class="form-control rounded-3" placeholder="e.g. NSBM/SOC/2024/1042" value="<?php echo safe($user_data['student_id'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Faculty / Department</label>
                            <select name="faculty" class="form-select rounded-3">
                                <option value="">-- Select Faculty --</option>
                                <option value="Faculty of Computing" <?php echo ($user_data['faculty'] ?? '') === 'Faculty of Computing' ? 'selected' : ''; ?>>Faculty of Computing</option>
                                <option value="Faculty of Business" <?php echo ($user_data['faculty'] ?? '') === 'Faculty of Business' ? 'selected' : ''; ?>>Faculty of Business</option>
                                <option value="Faculty of Engineering" <?php echo ($user_data['faculty'] ?? '') === 'Faculty of Engineering' ? 'selected' : ''; ?>>Faculty of Engineering</option>
                                <option value="Faculty of Science" <?php echo ($user_data['faculty'] ?? '') === 'Faculty of Science' ? 'selected' : ''; ?>>Faculty of Science</option>
                                <option value="Administration & Staff" <?php echo ($user_data['faculty'] ?? '') === 'Administration & Staff' ? 'selected' : ''; ?>>Administration & Staff</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Contact Phone Number</label>
                            <input type="tel" name="phone" class="form-control rounded-3" placeholder="e.g. +94 77 123 4567" value="<?php echo safe($user_data['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">About Me (Bio)</label>
                            <textarea name="bio" rows="3" class="form-control rounded-3" placeholder="Write a brief introduction about your academic role, interests, or student activities..."><?php echo safe($user_data['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-globe text-success me-1"></i> Social Media &amp; Profiles
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small"><i class="bi bi-facebook text-primary me-1"></i> Facebook Profile URL</label>
                            <input type="url" name="social_facebook" class="form-control rounded-3" placeholder="https://facebook.com/username" value="<?php echo safe($user_data['social_facebook'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small"><i class="bi bi-twitter-x me-1"></i> Twitter / X Profile URL</label>
                            <input type="url" name="social_twitter" class="form-control rounded-3" placeholder="https://x.com/username" value="<?php echo safe($user_data['social_twitter'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small"><i class="bi bi-linkedin text-info me-1"></i> LinkedIn Profile URL</label>
                            <input type="url" name="social_linkedin" class="form-control rounded-3" placeholder="https://linkedin.com/in/username" value="<?php echo safe($user_data['social_linkedin'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small"><i class="bi bi-instagram text-danger me-1"></i> Instagram Profile URL</label>
                            <input type="url" name="social_instagram" class="form-control rounded-3" placeholder="https://instagram.com/username" value="<?php echo safe($user_data['social_instagram'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="profile.php?id=<?php echo $target_user_id; ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-nsbm rounded-pill px-4">
                            <i class="bi bi-check-lg me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <!-- ========================================== -->
    <!-- TAB 3: CHANGE PASSWORD -->
    <!-- ========================================== -->
    <?php elseif ($active_tab === 'password' && $is_own_profile): ?>
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden fade-in-up" style="max-width: 580px; margin: 0 auto;">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-lock-fill me-2 text-success"></i>Change Account Password</h5>
            </div>
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="profile.php?id=<?php echo $target_user_id; ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Current Password *</label>
                        <input type="password" name="current_password" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">New Password *</label>
                        <input type="password" name="new_password" class="form-control rounded-3" minlength="6" placeholder="At least 6 characters" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-control rounded-3" minlength="6" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <a href="profile.php?id=<?php echo $target_user_id; ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-nsbm rounded-pill px-4">
                            <i class="bi bi-shield-check me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
