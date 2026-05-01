<?php
/**
 * BibleBridge — Full-page disk cache + IP throttling
 * ---------------------------------------------------
 * Protects standalone installs from bot-driven overload.
 * Default-on, no configuration needed.
 *
 * Cache: disk-based, 24h TTL for chapter pages.
 * Throttle: per-IP, filesystem counters (no APCu needed).
 *
 * Usage from read.php:
 *   require_once __DIR__ . '/lib/page-cache.php';
 *   $cache = bb_page_cache_check($book, $chapter, $version, $parallel);
 *   if ($cache) { echo $cache; exit; }
 *   ob_start();
 *   // ... normal render ...
 *   $html = ob_get_flush();
 *   bb_page_cache_write($book, $chapter, $version, $parallel, $html);
 */

// ---------------------------------------------------------------
// Config
// ---------------------------------------------------------------
define('BB_CACHE_DIR', __DIR__ . '/../cache');
define('BB_CACHE_TTL', 86400);         // 24 hours
define('BB_THROTTLE_MAX_PER_MIN', 30); // requests/minute before throttling
define('BB_THROTTLE_WINDOW', 60);      // seconds

// ---------------------------------------------------------------
// Ensure cache directories exist
// ---------------------------------------------------------------
function bb_cache_init(): void
{
    $dirs = [BB_CACHE_DIR . '/pages', BB_CACHE_DIR . '/throttle'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}

// ---------------------------------------------------------------
// Cache key — normalize inputs to prevent cache busting
// ---------------------------------------------------------------
function bb_cache_key(string $book, int $chapter, string $version, bool $parallel): string
{
    return sha1(json_encode([
        'route'    => 'chapter',
        'book'     => strtolower($book),
        'chapter'  => $chapter,
        'version'  => strtolower($version),
        'parallel' => $parallel ? 1 : 0,
        'tpl'      => BB_VERSION,
    ]));
}

function bb_cache_path(string $key): string
{
    return BB_CACHE_DIR . '/pages/' . $key . '.html';
}

// ---------------------------------------------------------------
// IP throttling — filesystem-based (works on any shared host)
// ---------------------------------------------------------------
function bb_is_throttled(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $minute = date('YmdHi');
    $file = BB_CACHE_DIR . '/throttle/' . md5($ip . $minute) . '.cnt';

    // Read current count
    $count = 0;
    if (file_exists($file)) {
        $count = (int) @file_get_contents($file);
    }

    // Increment
    @file_put_contents($file, $count + 1, LOCK_EX);

    return $count >= BB_THROTTLE_MAX_PER_MIN;
}

/**
 * Clean stale throttle files (older than 2 minutes).
 * Called probabilistically (1% of requests) to avoid overhead.
 */
function bb_throttle_cleanup(): void
{
    if (mt_rand(1, 100) > 1) return;

    $dir = BB_CACHE_DIR . '/throttle';
    if (!is_dir($dir)) return;

    $cutoff = time() - 120;
    $files = @scandir($dir);
    if (!$files) return;

    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . '/' . $f;
        if (is_file($path) && filemtime($path) < $cutoff) {
            @unlink($path);
        }
    }
}

// ---------------------------------------------------------------
// Cache check — returns cached HTML or false
// ---------------------------------------------------------------
function bb_page_cache_check(string $book, int $chapter, string $version, bool $parallel): string|false
{
    bb_cache_init();
    bb_throttle_cleanup();

    $key = bb_cache_key($book, $chapter, $version, $parallel);
    $path = bb_cache_path($key);

    // Check throttle
    $throttled = bb_is_throttled();

    // Cache hit?
    if (file_exists($path)) {
        $age = time() - filemtime($path);

        if ($age < BB_CACHE_TTL) {
            // Fresh cache — serve it
            bb_cache_log('cache_hit');
            return file_get_contents($path);
        }

        if ($throttled) {
            // Stale cache but client is throttled — serve stale (graceful degradation)
            bb_cache_log('throttled_served_stale');
            return file_get_contents($path);
        }

        // Stale and not throttled — fall through to re-render
    }

    // No cache and throttled — return a lightweight 429
    if ($throttled) {
        bb_cache_log('throttled_blocked');
        http_response_code(429);
        header('Retry-After: 60');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Please wait</title>'
           . '<style>body{font-family:system-ui,sans-serif;max-width:480px;margin:4rem auto;padding:0 1.5rem;color:#555;text-align:center}'
           . 'h1{font-size:1.2rem;color:#333;margin-bottom:0.5rem}</style></head>'
           . '<body><h1>Slow down</h1><p>You\'re requesting pages too quickly. Please wait a moment and try again.</p></body></html>';
        exit;
    }

    bb_cache_log('cache_miss');
    return false;
}

// ---------------------------------------------------------------
// Cache write — atomic disk write after render
// ---------------------------------------------------------------
function bb_page_cache_write(string $book, int $chapter, string $version, bool $parallel, string $html): void
{
    if (strlen($html) < 100) return; // don't cache error pages

    $key = bb_cache_key($book, $chapter, $version, $parallel);
    $path = bb_cache_path($key);

    // Atomic write — temp file + rename
    $tmp = $path . '.' . uniqid('', true) . '.tmp';
    if (@file_put_contents($tmp, $html, LOCK_EX) !== false) {
        @rename($tmp, $path);
    } else {
        @unlink($tmp);
    }

    bb_cache_log('rendered_and_cached');
}

// ---------------------------------------------------------------
// Simple outcome logging (daily log file)
// ---------------------------------------------------------------
function bb_cache_log(string $outcome): void
{
    static $logged = false;
    if ($logged) return; // one log per request
    $logged = true;

    $logDir = BB_CACHE_DIR . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
    $uri = $_SERVER['REQUEST_URI'] ?? '-';
    $line = date('H:i:s') . "\t" . $outcome . "\t" . $ip . "\t" . $uri . "\n";
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
