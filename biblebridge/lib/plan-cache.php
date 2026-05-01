<?php
/**
 * Caches the plan tier from the BibleBridge API.
 * Checks once per day to avoid unnecessary API calls.
 * Returns 'free', 'growth', 'scale', etc.
 */

function bb_get_cached_tier(): string
{
    global $bbInstall;

    $cacheFile = __DIR__ . '/../.plan-cache.json';
    $ttl = 86400; // 24 hours

    // Check cache
    if (file_exists($cacheFile)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if ($cached && isset($cached['tier'], $cached['ts']) && (time() - $cached['ts']) < $ttl) {
            return $cached['tier'];
        }
    }

    // Fetch from API
    $url = rtrim($bbInstall['api_url'] ?? 'https://holybible.dev/api', '/') . '/usage';
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "X-API-Key: {$bbInstall['api_key']}\r\nAccept: application/json\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return 'free'; // default on failure
    }

    $data = json_decode($body, true);
    $tier = $data['tier'] ?? 'free';

    // Write cache (best-effort)
    @file_put_contents($cacheFile, json_encode(['tier' => $tier, 'ts' => time()]));

    return $tier;
}
