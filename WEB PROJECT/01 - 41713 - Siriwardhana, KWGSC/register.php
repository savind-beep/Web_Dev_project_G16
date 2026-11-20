<?php
require_once "db.php";

// If already logged in, redirect
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation checks
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        header("Location: register.html?error=" . urlencode("Please fill in all required fields."));
        exit;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.html?error=" . urlencode("Please enter a valid email address."));
        exit;
    } elseif (strlen($password) < 6) {
        header("Location: register.html?error=" . urlencode("Password must be at least 6 characters long."));
        exit;
    } elseif ($password !== $confirm_password) {
        header("Location: register.html?error=" . urlencode("Passwords do not match."));
        exit;
    }

    // Check if email already exists
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);

    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        mysqli_stmt_close($check_stmt);
        header("Location: register.html?error=" . urlencode("An account with this email address already exists."));
        exit;
    }
    mysqli_stmt_close($check_stmt);

    // Hash the password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = "student";

    // Avatar selection
    $avatar = trim($_POST['avatar'] ?? 'ironman');
    $allowed_avatars = array_keys(getAvatarList());
    if (!in_array($avatar, $allowed_avatars)) {
        $avatar = 'ironman';
    }

    // Insert new user
    $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (name, student_id, email, password, role, avatar) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($insert_stmt, "ssssss", $name, $student_id, $email, $hashed_password, $role, $avatar);

    if (mysqli_stmt_execute($insert_stmt)) {
        $_SESSION['user_id'] = mysqli_insert_id($conn);
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['role'] = $role;
        $_SESSION['user_avatar'] = $avatar;
        mysqli_stmt_close($insert_stmt);

        header("Location: index.php?msg=" . urlencode("Registration successful! Welcome to N Magazine."));
        exit;
    } else {
        $err = mysqli_error($conn);
        mysqli_stmt_close($insert_stmt);
        header("Location: register.html?error=" . urlencode("Failed to create account: " . $err));
        exit;
    }
} else {
    // If accessed via GET, forward any query parameters to register.html
    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: register.html" . $qs);
    exit;
}
