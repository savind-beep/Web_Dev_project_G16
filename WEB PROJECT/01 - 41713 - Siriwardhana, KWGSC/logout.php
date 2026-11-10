<?php
// ========================================================
// N Magazine - Logout Script
// ========================================================

require_once "db.php";

// Clear session array
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session on server
session_destroy();

// Redirect to login page with notice
header("Location: login.php?msg=You+have+been+successfully+logged+out.");
exit;
