<?php
// Prevent undefined variable warning
$pageTitle = isset($pageTitle) ? $pageTitle . " | N Magazine" : "N Magazine - Campus News & Lifestyle Magazine";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Anti-flash: apply saved theme before render -->
    <script>try{document.documentElement.setAttribute('data-theme',localStorage.getItem('nsbm-theme')||'light')}catch(e){}</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="manifest" href="site.webmanifest">
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom N Magazine Styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>">
</head>
<body>
<?php include_once __DIR__ . "/preloader.php"; ?>
<?php include_once __DIR__ . "/navbar.php"; ?>
<main>
