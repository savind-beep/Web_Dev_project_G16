<?php
header('Content-Type: application/json; charset=utf-8');

$query = isset($_GET['q']) ? trim($_GET['q']) : 'trending';
if ($query === '') {
    $query = 'trending';
}

$cacheDir = __DIR__ . '/uploads/cache_gifs';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

$cacheKey = md5(strtolower($query));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';

// Return from cache if less than 2 hours old
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 7200)) {
    $cached = @file_get_contents($cacheFile);
    if ($cached) {
        echo $cached;
        exit;
    }
}

if (!function_exists('fetchTenorGifs')) {
function fetchTenorGifs($q) {
    $clean = strtolower($q);
    $clean = preg_replace('/[^a-z0-9]+/', '-', $clean);
    $clean = trim($clean, '-');

    if ($clean === '' || $clean === 'trending') {
        $targetUrl = "https://tenor.com";
    } else {
        $targetUrl = "https://tenor.com/search/" . $clean . "-gifs";
    }

    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$html) {
        return [];
    }

    preg_match_all('/https:\/\/media\.tenor\.com\/[a-zA-Z0-9_\-\/]+\.gif/i', $html, $matches);
    if (empty($matches[0])) {
        return [];
    }

    $uniqueGifs = array_values(array_unique($matches[0]));
    $results = [];

    foreach ($uniqueGifs as $url) {
        // Derive clean readable title from filename
        $title = 'GIF';
        if (preg_match('/\/([^\/]+)\.gif$/', $url, $m)) {
            $rawTitle = preg_replace('/[_\-]+/', ' ', $m[1]);
            $title = ucwords(trim($rawTitle));
        }

        // Generate a lighter preview URL if standard AAAAM
        $previewUrl = $url;
        if (strpos($url, 'AAAAM/') !== false) {
            $previewUrl = str_replace('AAAAM/', 'AAAAC/', $url);
        }

        $results[] = [
            'url' => $url,
            'preview' => $previewUrl,
            'title' => $title
        ];
    }

    return array_slice($results, 0, 40);
}
}

$results = fetchTenorGifs($query);

// Fallback curated collection if network offline or query has no web results
if (empty($results)) {
    $curated = [
        ["url" => "https://media.giphy.com/media/26u4cqiYI30juCOGY/giphy.gif", "title" => "Graduation Celebrate"],
        ["url" => "https://media.giphy.com/media/g9582DNuQppxC/giphy.gif", "title" => "Cheers Party"],
        ["url" => "https://media.giphy.com/media/mi6DsSSNKDbUY/giphy.gif", "title" => "Rocket Launch"],
        ["url" => "https://media.giphy.com/media/nbvFVPiEiJH6JOGIok/giphy.gif", "title" => "Applause Clap"],
        ["url" => "https://media.giphy.com/media/blSTtZehjAZ8I/giphy.gif", "title" => "Dance Party"],
        ["url" => "https://media.giphy.com/media/xT0xezQGU5xCDJuCPe/giphy.gif", "title" => "Mind Blown"],
        ["url" => "https://media.giphy.com/media/3oKIPnAiaMCws8nOsE/giphy.gif", "title" => "Coding Developer"],
        ["url" => "https://media.giphy.com/media/hPTZgtzfRIB5Nfb5rL/giphy.gif", "title" => "Coffee Fuel"],
        ["url" => "https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif", "title" => "Cute Cat"],
        ["url" => "https://media.giphy.com/media/3o7TKMt1VVNkHV2PaE/giphy.gif", "title" => "Green Campus"],
        ["url" => "https://media.giphy.com/media/111ebonMs90YLu/giphy.gif", "title" => "Thumbs Up"],
        ["url" => "https://media.giphy.com/media/l2YWgBNdssBwnnd5e/giphy.gif", "title" => "Study Time"]
    ];

    $cleanQuery = strtolower($query);
    $matched = array_values(array_filter($curated, function($item) use ($cleanQuery) {
        return stripos($item['title'], $cleanQuery) !== false || $cleanQuery === 'trending';
    }));

    $results = !empty($matched) ? $matched : $curated;
    foreach ($results as &$r) {
        $r['preview'] = $r['url'];
    }
}

$payload = json_encode([
    'success' => true,
    'source' => 'Tenor',
    'query' => $query,
    'count' => count($results),
    'results' => $results
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Write to cache if fetched successfully
if (!empty($results) && is_dir($cacheDir)) {
    @file_put_contents($cacheFile, $payload);
}

echo $payload;
