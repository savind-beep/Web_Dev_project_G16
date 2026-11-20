<?php
// Setup wizard
$db_config = require __DIR__ . '/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$install_success = false;
$install_error = '';
$queries_run = 0;

// Host & DB settings
$db_host = $db_config['host'];
$db_user = $db_config['user'];
$db_pass = $db_config['pass'];
$db_name = $db_config['name'];
$db_port = $db_config['port'];
$is_local = $db_config['is_local'];

// Disable mysqli exception throwing in PHP 8.1+
if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}

// Environment checks
$php_version_ok = version_compare(PHP_VERSION, '7.4.0', '>=');
$mysqli_installed = extension_loaded('mysqli');
$session_installed = extension_loaded('session');

// Step 1: Test Server Connection (without selecting DB)
$server_conn = null;
$server_error = '';
if ($mysqli_installed) {
    $server_conn = @mysqli_connect($db_host, $db_user, $db_pass, null, $db_port);
    if (!$server_conn) {
        $server_error = mysqli_connect_error();
    }
}

// Step 2: Test Database Selection
$db_exists = false;
$target_conn = null;
$db_error = '';
if ($server_conn) {
    $target_conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    if ($target_conn) {
        $db_exists = true;
    } else {
        $db_error = mysqli_connect_error();
    }
}

// Handle Database Installation Action
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET']) && $action === 'install') {
    if (!$server_conn) {
        $install_error = "Cannot connect to MySQL server ($db_host). Please check your MySQL credentials in config.php.";
    } else {
        // Try creating the database if it doesn't exist
        if (!$db_exists) {
            $create_db_res = @mysqli_query($server_conn, "CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($create_db_res) {
                $target_conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
                if ($target_conn) {
                    $db_exists = true;
                }
            } else {
                $install_error = "Could not create database `$db_name` automatically: " . mysqli_error($server_conn) . ". On live hosting, please create the database first in your hosting control panel.";
            }
        }

        // If target database is accessible, run schema
        if ($db_exists && $target_conn) {
            mysqli_set_charset($target_conn, 'utf8mb4');
            $sql_file = __DIR__ . '/database.sql';
            if (!file_exists($sql_file)) {
                $install_error = "database.sql file not found in project root!";
            } else {
                $sql_content = file_get_contents($sql_file);

                // Strip SQL comment lines
                $cleaned_sql = preg_replace('/^--.*$/m', '', $sql_content);
                $cleaned_sql = preg_replace('/^\/\*.*?\*\//ms', '', $cleaned_sql);

                // Split by semicolon
                $queries = explode(';', $cleaned_sql);
                $all_ok = true;

                foreach ($queries as $q) {
                    $q = trim($q);
                    if ($q === '') continue;

                    // Skip explicit CREATE DATABASE / USE queries if importing into existing database
                    if (stripos($q, 'CREATE DATABASE') === 0 || stripos($q, 'USE ') === 0) {
                        continue;
                    }

                    if (!mysqli_query($target_conn, $q)) {
                        $all_ok = false;
                        $install_error = "Query failed: " . mysqli_error($target_conn) . "<br>Query: <code>" . htmlspecialchars(substr($q, 0, 150)) . "...</code>";
                        break;
                    }
                    $queries_run++;
                }

                if ($all_ok) {
                    $install_success = true;
                }
            }
        }
    }
}

// Check existing tables and counts
$tables_status = [
    'users' => false,
    'categories' => false,
    'articles' => false,
    'comments' => false,
    'bookmarks' => false,
    'mindscape_thoughts' => false
];
$counts = ['users' => 0, 'articles' => 0, 'categories' => 0];

if ($db_exists && $target_conn) {
    foreach (array_keys($tables_status) as $table) {
        $check = mysqli_query($target_conn, "SHOW TABLES LIKE '$table'");
        if ($check && mysqli_num_rows($check) > 0) {
            $tables_status[$table] = true;
        }
    }
    if ($tables_status['users']) {
        $res = mysqli_query($target_conn, "SELECT COUNT(*) as c FROM users");
        if ($res && $r = mysqli_fetch_assoc($res)) $counts['users'] = $r['c'];
    }
    if ($tables_status['articles']) {
        $res = mysqli_query($target_conn, "SELECT COUNT(*) as c FROM articles");
        if ($res && $r = mysqli_fetch_assoc($res)) $counts['articles'] = $r['c'];
    }
    if ($tables_status['categories']) {
        $res = mysqli_query($target_conn, "SELECT COUNT(*) as c FROM categories");
        if ($res && $r = mysqli_fetch_assoc($res)) $counts['categories'] = $r['c'];
    }
}

$all_tables_installed = !in_array(false, $tables_status, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N Magazine - Web Setup & Diagnostics Wizard</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f4f8; font-family: system-ui, -apple-system, sans-serif; }
        .hero-card { border-radius: 16px; border: none; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .status-ok { background-color: #198754; }
        .status-fail { background-color: #dc3545; }
        .code-box { background: #1e293b; color: #f8fafc; border-radius: 8px; padding: 12px 16px; font-size: 0.9rem; }
    </style>
</head>
<body class="py-5">
<div class="container" style="max-width: 860px;">
    <!-- Header -->
    <div class="text-center mb-4">
        <div class="d-inline-flex p-3 bg-white rounded-circle shadow-sm mb-3">
            <i class="bi bi-mortarboard-fill text-success fs-1"></i>
        </div>
        <h2 class="fw-bold text-dark">N Magazine Setup & Health Check</h2>
        <p class="text-muted">Automated installer & diagnostics for Localhost and Cloud/Live Servers</p>
    </div>

    <?php if ($install_success): ?>
        <div class="alert alert-success shadow-sm rounded-3 d-flex align-items-center mb-4 p-3">
            <i class="bi bi-check-circle-fill fs-2 me-3 flex-shrink-0"></i>
            <div>
                <h5 class="fw-bold mb-1">Database Successfully Installed!</h5>
                <p class="mb-0 small">Created and seeded tables (<?php echo $queries_run; ?> SQL statements executed). Default admin and student accounts are ready!</p>
            </div>
        </div>
    <?php elseif (!empty($install_error)): ?>
        <div class="alert alert-danger shadow-sm rounded-3 d-flex align-items-center mb-4 p-3">
            <i class="bi bi-exclamation-triangle-fill fs-2 me-3 flex-shrink-0"></i>
            <div>
                <h5 class="fw-bold mb-1">Setup Encountered an Error</h5>
                <div class="small"><?php echo $install_error; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Card -->
    <div class="card shadow-sm hero-card mb-4">
        <div class="card-header bg-white border-bottom p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0">System & Database Status</h5>
                    <span class="small text-muted">Detected Environment: 
                        <strong class="<?php echo $is_local ? 'text-primary' : 'text-success'; ?>">
                            <?php echo $is_local ? '💻 Localhost (Development)' : '🌐 Live Server (Production)'; ?>
                        </strong>
                    </span>
                </div>
                <span class="badge <?php echo $all_tables_installed ? 'bg-success' : 'bg-warning text-dark'; ?> px-3 py-2 rounded-pill fw-bold">
                    <?php echo $all_tables_installed ? '<i class="bi bi-check2-circle me-1"></i> Ready to Use' : '<i class="bi bi-exclamation-circle me-1"></i> Setup Needed'; ?>
                </span>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- 1. Environment Checklist -->
            <h6 class="fw-bold text-uppercase text-secondary small mb-3">1. PHP Environment Checks</h6>
            <div class="list-group mb-4">
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="status-dot <?php echo $php_version_ok ? 'status-ok' : 'status-fail'; ?>"></span>
                        <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?>
                    </div>
                    <span class="badge <?php echo $php_version_ok ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                        <?php echo $php_version_ok ? 'OK (>= 7.4)' : 'Requires PHP 7.4+'; ?>
                    </span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="status-dot <?php echo $mysqli_installed ? 'status-ok' : 'status-fail'; ?>"></span>
                        <strong>MySQLi Extension:</strong>
                    </div>
                    <span class="badge <?php echo $mysqli_installed ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                        <?php echo $mysqli_installed ? 'Enabled' : 'Missing'; ?>
                    </span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="status-dot <?php echo $session_installed ? 'status-ok' : 'status-fail'; ?>"></span>
                        <strong>Session Support:</strong>
                    </div>
                    <span class="badge <?php echo $session_installed ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                        <?php echo $session_installed ? 'Enabled' : 'Missing'; ?>
                    </span>
                </div>
            </div>

            <!-- 2. Database Connection Checklist -->
            <h6 class="fw-bold text-uppercase text-secondary small mb-3">2. MySQL Connection & Database</h6>
            <div class="list-group mb-4">
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="status-dot <?php echo $server_conn ? 'status-ok' : 'status-fail'; ?>"></span>
                        <strong>MySQL Server (<code><?php echo htmlspecialchars($db_host . ':' . $db_port); ?></code>):</strong>
                        <?php if (!$server_conn && !empty($server_error)): ?>
                            <div class="small text-danger mt-1"><?php echo htmlspecialchars($server_error); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="badge <?php echo $server_conn ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                        <?php echo $server_conn ? 'Connected' : 'Offline / Failed'; ?>
                    </span>
                </div>

                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="status-dot <?php echo $db_exists ? 'status-ok' : 'status-fail'; ?>"></span>
                        <strong>Target Database (<code><?php echo htmlspecialchars($db_name); ?></code>):</strong>
                        <?php if (!$db_exists && !empty($db_error)): ?>
                            <div class="small text-danger mt-1"><?php echo htmlspecialchars($db_error); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="badge <?php echo $db_exists ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning border border-warning'; ?>">
                        <?php echo $db_exists ? 'Exists & Accessible' : 'Not Found'; ?>
                    </span>
                </div>

                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="status-dot <?php echo $all_tables_installed ? 'status-ok' : 'status-fail'; ?>"></span>
                        <strong>Database Tables:</strong>
                        <div class="small text-muted mt-1">
                            users (<?php echo $counts['users']; ?>), categories (<?php echo $counts['categories']; ?>), articles (<?php echo $counts['articles']; ?>)
                        </div>
                    </div>
                    <span class="badge <?php echo $all_tables_installed ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?>">
                        <?php echo $all_tables_installed ? 'All 6 Tables Present' : 'Incomplete'; ?>
                    </span>
                </div>
            </div>

            <!-- 3. Actions & Installer -->
            <h6 class="fw-bold text-uppercase text-secondary small mb-3">3. Database Action</h6>
            <?php if ($server_conn): ?>
                <div class="p-4 bg-light rounded-3 border mb-4">
                    <form method="POST" action="setup.php" onsubmit="return confirm('Install / refresh database tables from database.sql?');">
                        <input type="hidden" name="action" value="install">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <h6 class="fw-bold mb-1">
                                    <?php echo $all_tables_installed ? 'Re-install / Reset Database Tables' : '1-Click Install Database Tables'; ?>
                                </h6>
                                <p class="small text-muted mb-0">
                                    Executes <code>database.sql</code> to create all tables (users, categories, articles, comments, bookmarks) and seed sample data.
                                </p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="setup.php?action=install" class="btn btn-outline-primary px-3 fw-semibold" onclick="return confirm('Install / refresh database tables from database.sql?');">
                                    <i class="bi bi-shield-check me-1"></i> Bypass 403 (Direct Setup)
                                </a>
                                <button type="submit" class="btn <?php echo $all_tables_installed ? 'btn-outline-success' : 'btn-success'; ?> px-4 fw-bold">
                                    <i class="bi bi-box-arrow-in-down me-1"></i> <?php echo $all_tables_installed ? 'Re-install Database' : 'Install Database Now'; ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Cannot run installation:</strong> MySQL server connection is not established.
                    <?php if ($is_local): ?>
                        Please open your <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to MySQL.
                    <?php else: ?>
                        Please verify your server database host, user, and password in <code>config.php</code>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Default Test Logins Card -->
            <div class="card border-0 bg-light rounded-3 p-3 mb-4">
                <h6 class="fw-bold small text-dark mb-2"><i class="bi bi-key-fill text-warning me-1"></i> Default Demo Credentials</h6>
                <div class="row g-2 small">
                    <div class="col-md-6">
                        <div class="p-2 bg-white rounded border">
                            <strong>Admin Account:</strong><br>
                            Email: <code>admin@nsbm.ac.lk</code><br>
                            Password: <code>admin123</code>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 bg-white rounded border">
                            <strong>Student Account:</strong><br>
                            Email: <code>student@nsbm.ac.lk</code><br>
                            Password: <code>student123</code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="d-flex flex-wrap gap-2 justify-content-between pt-2 border-top">
                <a href="index.php" class="btn btn-primary fw-bold px-4">
                    <i class="bi bi-house-door me-1"></i> Open Website (index.php)
                </a>
                <div class="d-flex gap-2">
                    <a href="login.html" class="btn btn-outline-secondary">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login Page
                    </a>
                    <a href="admin.php" class="btn btn-outline-dark">
                        <i class="bi bi-speedometer2 me-1"></i> Admin Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Documentation Card -->
    <div class="card shadow-sm hero-card">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle me-1 text-primary"></i> Hosting Guide Reference</h6>
            <div class="accordion" id="setupAccordion">
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header" id="headingLocal">
                        <button class="accordion-button collapsed px-0 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLocal">
                            💻 Running on Localhost (XAMPP)
                        </button>
                    </h2>
                    <div id="collapseLocal" class="accordion-collapse collapse" data-bs-parent="#setupAccordion">
                        <div class="accordion-body px-0 text-muted small" style="line-height: 1.8;">
                            1. Open XAMPP Control Panel and start <strong>Apache</strong> and <strong>MySQL</strong>.<br>
                            2. Visit <code>http://localhost/webproject/setup.php</code> in your browser.<br>
                            3. Click <strong>"Install Database Now"</strong>. Once finished, click <strong>"Open Website"</strong>!
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0">
                    <h2 class="accordion-header" id="headingCloud">
                        <button class="accordion-button collapsed px-0 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCloud">
                            🌐 Hosting on a Live Server (InfinityFree, cPanel, Hostinger)
                        </button>
                    </h2>
                    <div id="collapseCloud" class="accordion-collapse collapse" data-bs-parent="#setupAccordion">
                        <div class="accordion-body px-0 text-muted small" style="line-height: 1.8;">
                            1. Upload all project files to your server's <code>htdocs</code> or <code>public_html</code> folder via FTP or File Manager.<br>
                            2. Create a MySQL database and user in your hosting control panel.<br>
                            3. Open <code>config.php</code> and fill in your database credentials under <code>LIVE SERVER CONFIGURATION</code>.<br>
                            4. Open your browser to <code>https://yourdomain.com/setup.php</code> and click <strong>"Install Database Now"</strong> (or import <code>database.sql</code> in phpMyAdmin).<br>
                            5. Done! Your site is live and fully functional.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
