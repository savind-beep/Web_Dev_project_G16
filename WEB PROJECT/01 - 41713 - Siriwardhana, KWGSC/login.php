<?php
require_once "db.php";

// If already logged in, redirect based on role
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: login.html?error=" . urlencode("Please enter both email and password."));
        exit;
    }

    // Query user by email using prepared statement
    $stmt = mysqli_prepare($conn, "SELECT id, name, email, password, role, avatar, banned_until, ban_reason FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        // Verify password using password_verify (with fallback for demo accounts)
        if (password_verify($password, $user['password']) || $password === $user['password'] || md5($password) === $user['password']) {
            // Check if user is suspended
            if (!empty($user['banned_until']) && strtotime($user['banned_until']) > time()) {
                $until = date("M d, Y h:i A", strtotime($user['banned_until']));
                $reason = !empty($user['ban_reason']) ? " (Reason: " . $user['ban_reason'] . ")" : "";
                header("Location: login.html?error=" . urlencode("Your student account is temporarily suspended until {$until}.{$reason}"));
                exit;
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_avatar'] = $user['avatar'] ?? 'ironman';

            if ($user['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            header("Location: login.html?error=" . urlencode("Invalid password. Please try again."));
            exit;
        }
    } else {
        header("Location: login.html?error=" . urlencode("No account found with this email address."));
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    // If accessed via GET, forward any query parameters (like ?msg= or ?error=) to login.html
    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: login.html" . $qs);
    exit;
}
