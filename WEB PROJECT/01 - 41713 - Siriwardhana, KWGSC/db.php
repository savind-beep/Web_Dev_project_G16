<?php
// Session init
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Config
$db_config = require_once __DIR__ . '/config.php';

$db_host = $db_config['host'];
$db_user = $db_config['user'];
$db_pass = $db_config['pass'];
$db_name = $db_config['name'];
$db_port = $db_config['port'];
$is_local = $db_config['is_local'];

if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}

// DB connection
$conn = null;
$db_error_code = 0;
$db_error_msg = '';

try {
    $conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    if (!$conn) {
        $db_error_code = mysqli_connect_errno();
        $db_error_msg  = mysqli_connect_error();
    }
} catch (Throwable $e) {
    $conn = false;
    $db_error_code = $e->getCode();
    $db_error_msg  = $e->getMessage();
}

// Connection error fallback
if (!$conn) {
    $server_alive = false;
    if ($db_error_code === 1049) {
        $test_conn = @mysqli_connect($db_host, $db_user, $db_pass, null, $db_port);
        if ($test_conn) {
            $server_alive = true;
            @mysqli_close($test_conn);
        }
    }

    $isLocalXampp = $is_local;

    die("
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Database Setup | N Magazine</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' rel='stylesheet'>
        <style>
            body { background: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
            .setup-card { max-width: 640px; margin: 40px auto; border-radius: 12px; }
        </style>
    </head>
    <body>
    <div class='container p-3'>
        <div class='card shadow-sm setup-card border-0'>
            <div class='card-header bg-white border-bottom p-4 text-center'>
                <div class='text-danger mb-2'>
                    <i class='bi bi-database-exclamation' style='font-size: 2.5rem;'></i>
                </div>
                <h4 class='fw-bold mb-1 text-dark'>Database Connection Failed</h4>
                <p class='text-muted small mb-0'>Unable to connect to the MySQL database server.</p>
            </div>
            <div class='card-body p-4'>
                <div class='alert alert-danger py-2 small mb-3'>
                    <strong>Error (" . htmlspecialchars((string)$db_error_code) . "):</strong> " . htmlspecialchars($db_error_msg) . "
                </div>

                <div class='bg-light p-3 rounded mb-3 small'>
                    <div><strong>Host:</strong> " . htmlspecialchars($db_host) . ":" . htmlspecialchars((string)$db_port) . "</div>
                    <div><strong>Database:</strong> " . htmlspecialchars($db_name) . "</div>
                    <div><strong>User:</strong> " . htmlspecialchars($db_user) . "</div>
                </div>

                <div class='d-flex gap-2 justify-content-between pt-2'>
                    <a href='' onclick='location.reload(); return false;' class='btn btn-success btn-sm px-3'>
                        <i class='bi bi-arrow-clockwise me-1'></i> Retry
                    </a>
                    <a href='setup.php' class='btn btn-outline-secondary btn-sm px-3'>
                        <i class='bi bi-gear me-1'></i> Database Setup Wizard
                    </a>
                </div>
            </div>
        </div>
    </div>
    </body>
    </html>
    ");
}

mysqli_set_charset($conn, "utf8mb4");

// Schema verification
if ($conn) {
    // Users extra fields
    $check_avatar = @mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'avatar'");
    if ($check_avatar && mysqli_num_rows($check_avatar) === 0) {
        @mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(50) DEFAULT 'ironman' AFTER `role`");
    }

    $check_bio = @mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'bio'");
    if ($check_bio && mysqli_num_rows($check_bio) === 0) {
        @mysqli_query($conn, "ALTER TABLE `users` 
            ADD COLUMN `bio` TEXT DEFAULT NULL,
            ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL,
            ADD COLUMN `faculty` VARCHAR(100) DEFAULT NULL,
            ADD COLUMN `social_facebook` VARCHAR(255) DEFAULT NULL,
            ADD COLUMN `social_twitter` VARCHAR(255) DEFAULT NULL,
            ADD COLUMN `social_linkedin` VARCHAR(255) DEFAULT NULL,
            ADD COLUMN `social_instagram` VARCHAR(255) DEFAULT NULL,
            ADD COLUMN `is_reported` TINYINT(1) DEFAULT 0,
            ADD COLUMN `report_reason` TEXT DEFAULT NULL");
    }

    $check_ban = @mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'banned_until'");
    if ($check_ban && mysqli_num_rows($check_ban) === 0) {
        @mysqli_query($conn, "ALTER TABLE `users` 
            ADD COLUMN `banned_until` DATETIME DEFAULT NULL,
            ADD COLUMN `ban_reason` VARCHAR(255) DEFAULT NULL");
    }

    // Ensure categories exist
    $check_cats = @mysqli_query($conn, "SELECT slug FROM `categories` WHERE slug IN ('clubs', 'uni-life')");
    $existing_slugs = [];
    if ($check_cats) {
        while ($r = mysqli_fetch_assoc($check_cats)) {
            $existing_slugs[] = $r['slug'];
        }
    }
    if (!in_array('clubs', $existing_slugs)) {
        @mysqli_query($conn, "INSERT INTO `categories` (name, slug, description) VALUES ('Clubs', 'clubs', 'Student clubs, societies, and community activities')");
    }
    if (!in_array('uni-life', $existing_slugs)) {
        @mysqli_query($conn, "INSERT INTO `categories` (name, slug, description) VALUES ('Uni Life', 'uni-life', 'Campus life, student culture, and university living')");
    }

    // Mindscape table
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `mindscape_thoughts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT DEFAULT NULL,
        `author_name` VARCHAR(100) NOT NULL,
        `student_id` VARCHAR(50) DEFAULT NULL,
        `role` VARCHAR(50) DEFAULT 'student',
        `avatar_emoji` VARCHAR(30) DEFAULT '🌱',
        `avatar_bg` VARCHAR(60) DEFAULT 'linear-gradient(135deg, #16a34a, #059669)',
        `thought` TEXT NOT NULL,
        `gif_url` VARCHAR(500) DEFAULT NULL,
        `brick_color` VARCHAR(50) DEFAULT 'brick-emerald',
        `brick_size` VARCHAR(20) DEFAULT 'standard',
        `likes` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $check_gif = @mysqli_query($conn, "SHOW COLUMNS FROM `mindscape_thoughts` LIKE 'gif_url'");
    if ($check_gif && mysqli_num_rows($check_gif) === 0) {
        @mysqli_query($conn, "ALTER TABLE `mindscape_thoughts` ADD COLUMN `gif_url` VARCHAR(500) DEFAULT NULL AFTER `thought`");
    }

    // Seed thoughts if table empty
    $check_thoughts = @mysqli_query($conn, "SELECT COUNT(*) AS total FROM `mindscape_thoughts`");
    if ($check_thoughts && ($t_row = mysqli_fetch_assoc($check_thoughts)) && intval($t_row['total']) === 0) {
        @mysqli_query($conn, "INSERT INTO `mindscape_thoughts` 
            (`author_name`, `student_id`, `role`, `avatar_emoji`, `avatar_bg`, `thought`, `brick_color`, `brick_size`, `likes`, `created_at`) VALUES
            ('Kasun Perera', 'NSBM/SOC/2024/1042', 'student', '💻', 'linear-gradient(135deg, #0284c7, #0369a1)', 'HackNSBM 2026 is going to be great! Looking for teammates.', 'brick-emerald', 'wide', 14, DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
            ('Amaya Silva', 'NSBM/FOB/2024/0821', 'student', '📸', 'linear-gradient(135deg, #ec4899, #be185d)', 'The sunset over the Green Oval today looked beautiful!', 'brick-gold', 'standard', 28, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
            ('N Magazine Admin', 'NSBM/ADMIN/01', 'admin', '🏛️', 'linear-gradient(135deg, #15803d, #16a34a)', 'Welcome to Mindscape! Post your campus thoughts here.', 'brick-emerald', 'accent', 42, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
            ('Dinuka Fernando', 'NSBM/FOE/2023/0411', 'student', '⚡', 'linear-gradient(135deg, #f59e0b, #d97706)', 'Robotics project testing completed. Ready for the expo!', 'brick-slate', 'standard', 9, DATE_SUB(NOW(), INTERVAL 8 HOUR)),
            ('Sithmi Jayawardena', 'NSBM/FOB/2025/1190', 'student', '🌿', 'linear-gradient(135deg, #10b981, #047857)', 'The electric shuttle service is very convenient.', 'brick-mint', 'wide', 19, DATE_SUB(NOW(), INTERVAL 1 DAY))");
    }
}

// User ban check
function checkActiveBan($user_id) {
    global $conn;
    if (!$conn || $user_id <= 0) return null;
    $stmt = mysqli_prepare($conn, "SELECT banned_until, ban_reason FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($u = mysqli_fetch_assoc($res)) {
            if (!empty($u['banned_until']) && strtotime($u['banned_until']) > time()) {
                mysqli_stmt_close($stmt);
                return $u;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return null;
}

// Auth helpers
function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    $active_ban = checkActiveBan($_SESSION['user_id']);
    if ($active_ban) {
        $until = date("M d, Y h:i A", strtotime($active_ban['banned_until']));
        $reason = !empty($active_ban['ban_reason']) ? " (Reason: " . $active_ban['ban_reason'] . ")" : "";
        session_unset();
        session_destroy();
        header("Location: login.php?error=" . urlencode("Your account has been suspended until {$until}.{$reason}"));
        exit;
    }
    return true;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php?msg=Please+log+in+to+access+this+page");
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header("Location: login.php?msg=Access+denied.+Admin+privileges+required");
        exit;
    }
}

// Output sanitize
function safe($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Avatars
function getAvatarList() {
    return [
        'ironman'   => ['file' => 'assets/images/avatars/avatar_ironman.svg'],
        'batman'    => ['file' => 'assets/images/avatars/avatar_batman.svg'],
        'spiderman' => ['file' => 'assets/images/avatars/avatar_spiderman.svg'],
        'superman'  => ['file' => 'assets/images/avatars/avatar_superman.svg'],
        'thor'      => ['file' => 'assets/images/avatars/avatar_thor.svg'],
        'panther'   => ['file' => 'assets/images/avatars/avatar_panther.svg'],
        'jedi'      => ['file' => 'assets/images/avatars/avatar_jedi.svg'],
        'potter'    => ['file' => 'assets/images/avatars/avatar_potter.svg'],
        'neo'       => ['file' => 'assets/images/avatars/avatar_neo.svg'],
        'wolverine' => ['file' => 'assets/images/avatars/avatar_wolverine.svg']
    ];
}

function getSuperheroAvatars() {
    return getAvatarList();
}

function getAvatarUrl($key) {
    $avatars = getAvatarList();
    $key = strtolower(trim($key ?? ''));
    return $avatars[$key]['file'] ?? 'assets/images/avatars/avatar_ironman.svg';
}

// Image upload handler for articles (supports file upload or URL)
function handleArticleImageUpload($fileInputName = 'image_file', $urlInputName = 'image_url', $existingUrl = '') {
    if (isset($_FILES[$fileInputName]) && is_array($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$fileInputName];
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed_exts)) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            $filename = 'art_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                return 'uploads/' . $filename;
            }
        }
    }
    
    $url = trim($_POST[$urlInputName] ?? '');
    if (!empty($url)) {
        return $url;
    }
    
    if (!empty($existingUrl)) {
        return $existingUrl;
    }
    
    return "https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=800&auto=format&fit=crop&q=80";
}

