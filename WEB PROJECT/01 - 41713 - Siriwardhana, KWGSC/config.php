<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// PHP polyfills
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}

// Load .env if present
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (getenv($name) === false) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Local override config
if (file_exists(__DIR__ . '/config.local.php')) {
    $local_override = include __DIR__ . '/config.local.php';
    if (is_array($local_override)) {
        return $local_override;
    }
}

// Environment detection
$http_host = $_SERVER['HTTP_HOST'] ?? '';
$server_name = $_SERVER['SERVER_NAME'] ?? '';
$server_addr = $_SERVER['SERVER_ADDR'] ?? '';

$is_local = (
    php_sapi_name() === 'cli' || 
    php_sapi_name() === 'cli-server' || 
    str_contains($http_host, 'localhost') ||
    str_contains($http_host, '127.0.0.1') ||
    str_contains($http_host, '[::1]') ||
    str_ends_with($http_host, '.local') ||
    str_ends_with($http_host, '.test') ||
    $server_name === 'localhost'
);

$app_env = getenv('APP_ENV') ?: ($is_local ? 'local' : 'production');

if ($app_env === 'local') {
    // Localhost settings
    $config = [
        'host'     => getenv('DB_HOST') ?: 'localhost',
        'user'     => getenv('DB_USER') ?: 'root',
        'pass'     => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        'name'     => getenv('DB_NAME') ?: 'nsbm_newsdesk',
        'port'     => getenv('DB_PORT') ? intval(getenv('DB_PORT')) : 3306,
        'app_env'  => 'local',
        'is_local' => true
    ];
} else {
    // Production settings
    $config = [
        'host'     => getenv('DB_HOST') ?: 'sql305.infinityfree.com',
        'user'     => getenv('DB_USER') ?: 'if0_43242151',
        'pass'     => getenv('DB_PASS') ?: 'VYYm5rqjL3U0dDe',
        'name'     => getenv('DB_NAME') ?: 'if0_43242151_nsbm',
        'port'     => getenv('DB_PORT') ? intval(getenv('DB_PORT')) : 3306,
        'app_env'  => 'production',
        'is_local' => false
    ];
}

return $config;
