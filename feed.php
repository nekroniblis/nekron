<?php
// feed.php - tiny RSS fetcher for the dashboard. Put it next to clock7_3.html.
// Only the feeds listed below can be fetched, so this can't be used as an open proxy.
$feeds = [
    'planetary' => 'https://www.planetary.org/rss/articles',
    'nasa'      => 'https://www.nasa.gov/feeds/iotd-feed/',
];

$key = $_GET['src'] ?? '';
if (!isset($feeds[$key])) {
    http_response_code(400);
    exit('Unknown feed');
}

$url       = $feeds[$key];
$cacheFile = sys_get_temp_dir() . '/nekron_feed_' . md5($url) . '.xml';
$maxAge    = 900; // 15 minutes

header('Content-Type: application/xml; charset=utf-8');

// Serve fresh cache if we have it
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $maxAge) {
    readfile($cacheFile);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_ENCODING       => '',
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml;q=0.9, text/xml;q=0.8, */*;q=0.5'],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body !== false && $code === 200 && strpos($body, '<') !== false) {
    file_put_contents($cacheFile, $body);
    echo $body;
    exit;
}

// Upstream failed: fall back to a stale cache if there is one
if (is_file($cacheFile)) {
    readfile($cacheFile);
    exit;
}

http_response_code(502);
echo 'Upstream fetch failed (HTTP ' . $code . ')';
