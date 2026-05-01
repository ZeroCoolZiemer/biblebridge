<?php
/**
 * BibleBridge Standalone — Preflight Check
 * Detects missing requirements and shows users plain-language explanations.
 * Include this early (e.g. from setup.php) — it exits with a diagnostic page
 * only if problems are found.
 */

function bb_preflight(): array {
    $checks = [];

    // 1. PHP version
    $checks[] = [
        'name'   => 'PHP 8.0 or newer',
        'passed' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'value'  => PHP_VERSION,
        'fix'    => 'Ask your hosting provider to switch your site to PHP 8.0 or newer. Most hosts offer this in cPanel → "Select PHP Version" or "MultiPHP Manager".',
    ];

    // 2. allow_url_fopen (needed for API calls via file_get_contents)
    $checks[] = [
        'name'   => 'allow_url_fopen enabled',
        'passed' => (bool) ini_get('allow_url_fopen'),
        'value'  => ini_get('allow_url_fopen') ? 'On' : 'Off',
        'fix'    => 'BibleBridge fetches scripture from its API using file_get_contents(). Your host has disabled this. Ask them to enable allow_url_fopen, or add <code>allow_url_fopen = On</code> to a php.ini file in your site root.',
    ];

    // 3. openssl (needed for HTTPS API calls)
    $checks[] = [
        'name'   => 'OpenSSL extension',
        'passed' => extension_loaded('openssl'),
        'value'  => extension_loaded('openssl') ? 'Loaded' : 'Missing',
        'fix'    => 'The openssl extension is needed to connect securely to the BibleBridge API. Ask your host to enable the PHP openssl extension.',
    ];

    // 4. json extension
    $checks[] = [
        'name'   => 'JSON extension',
        'passed' => extension_loaded('json'),
        'value'  => extension_loaded('json') ? 'Loaded' : 'Missing',
        'fix'    => 'The json extension is required. Ask your host to enable the PHP json extension. (It is included by default on most servers.)',
    ];

    // 5. mbstring extension (optional — only needed for non-English translations)
    $checks[] = [
        'name'   => 'Multibyte String (mbstring)',
        'passed' => true, // never block setup
        'warning' => !extension_loaded('mbstring'),
        'value'  => extension_loaded('mbstring') ? 'Loaded' : 'Not loaded (optional)',
        'fix'    => 'The mbstring extension improves support for non-English Bible translations. English works fine without it. Ask your host to enable the PHP mbstring extension if you plan to use other languages.',
    ];

    // 6. Writable directory (for config.local.php)
    $dir = __DIR__;
    $checks[] = [
        'name'   => 'Writable directory',
        'passed' => is_writable($dir),
        'value'  => is_writable($dir) ? 'Writable' : 'Not writable',
        'fix'    => 'BibleBridge needs to write one config file during setup. In your FTP client or file manager, right-click the <code>biblebridge/</code> folder → Permissions → set to <strong>755</strong> (or 775 if 755 doesn\'t work).',
    ];

    // 7. mod_rewrite / .htaccess support (heuristic)
    $htaccessExists = file_exists(__DIR__ . '/.htaccess');
    // We can't detect mod_rewrite directly from PHP on all hosts,
    // but we can check if Apache modules are listed
    $modRewrite = true; // assume ok
    $modRewriteValue = 'Assumed OK';
    if (function_exists('apache_get_modules')) {
        $modules = apache_get_modules();
        $modRewrite = in_array('mod_rewrite', $modules);
        $modRewriteValue = $modRewrite ? 'Enabled' : 'Disabled';
    } elseif (!$htaccessExists) {
        $modRewrite = false;
        $modRewriteValue = '.htaccess file missing';
    }
    $checks[] = [
        'name'   => 'URL rewriting (.htaccess)',
        'passed' => $modRewrite,
        'value'  => $modRewriteValue,
        'fix'    => 'BibleBridge uses clean URLs (like /read/john/3) which require Apache mod_rewrite. Ask your host to enable mod_rewrite and make sure AllowOverride is set to All for your directory. If you\'re on Nginx, you\'ll need equivalent rewrite rules.',
    ];

    return $checks;
}

function bb_preflight_render(array $checks): void {
    $failures = array_filter($checks, fn($c) => !$c['passed']);
    if (empty($failures)) return; // all good

    $passCount = count($checks) - count($failures);
    $failCount = count($failures);

    http_response_code(200); // Don't trigger error pages
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Check — BibleBridge Reader</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #faf8f4;
            --bg-card: #ffffff;
            --text: #2c2416;
            --text-muted: #8a7e6b;
            --accent: #b8860b;
            --border: #e8e0d4;
            --green: #16a34a;
            --green-bg: #f0fdf4;
            --green-border: #bbf7d0;
            --red: #dc2626;
            --red-bg: #fef2f2;
            --red-border: #fecaca;
            --amber: #d97706;
            --amber-bg: #fffbeb;
            --amber-border: #fde68a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .preflight-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 580px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        .preflight-logo {
            font-family: 'Lora', Georgia, serif;
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--accent);
            margin-bottom: 0.25rem;
        }
        .preflight-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .preflight-subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .check-list { list-style: none; margin-bottom: 1.5rem; }
        .check-item {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        .check-item:last-child { border-bottom: none; }
        .check-icon {
            flex-shrink: 0;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 1px;
        }
        .check-pass .check-icon { background: var(--green-bg); color: var(--green); border: 1.5px solid var(--green-border); }
        .check-fail .check-icon { background: var(--red-bg); color: var(--red); border: 1.5px solid var(--red-border); }
        .check-warn .check-icon { background: var(--amber-bg); color: var(--amber); border: 1.5px solid var(--amber-border); }
        .check-body { flex: 1; min-width: 0; }
        .check-name {
            font-size: 0.88rem;
            font-weight: 500;
        }
        .check-value {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 0.1rem;
        }
        .check-fix {
            font-size: 0.82rem;
            color: var(--red);
            background: var(--red-bg);
            border-radius: 6px;
            padding: 0.6rem 0.8rem;
            margin-top: 0.5rem;
            line-height: 1.5;
        }
        .check-fix code {
            background: #fff;
            padding: 0.1rem 0.35rem;
            border-radius: 3px;
            font-size: 0.78rem;
        }
        .summary {
            font-size: 0.85rem;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .summary-fail {
            background: var(--red-bg);
            color: #991b1b;
            border: 1px solid var(--red-border);
        }
        .retry-btn {
            display: block;
            width: 100%;
            padding: 0.75rem;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background 0.15s;
        }
        .retry-btn:hover { background: #9a7209; }
        .preflight-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="preflight-card">
        <div class="preflight-logo">BibleBridge</div>
        <div class="preflight-title">Server Compatibility Check</div>
        <p class="preflight-subtitle">
            Your server is missing <?= $failCount ?> requirement<?= $failCount !== 1 ? 's' : '' ?> needed to run BibleBridge.
            Each issue below includes what to ask your hosting provider.
        </p>

        <div class="summary summary-fail">
            <?= $passCount ?> of <?= count($checks) ?> checks passed &mdash; <?= $failCount ?> need<?= $failCount === 1 ? 's' : '' ?> attention
        </div>

        <ul class="check-list">
            <?php foreach ($checks as $c):
                $isWarning = !empty($c['warning']);
                $cssClass = !$c['passed'] ? 'check-fail' : ($isWarning ? 'check-warn' : 'check-pass');
                $icon = !$c['passed'] ? '&#10007;' : ($isWarning ? '!' : '&#10003;');
            ?>
            <li class="check-item <?= $cssClass ?>">
                <span class="check-icon"><?= $icon ?></span>
                <div class="check-body">
                    <div class="check-name"><?= htmlspecialchars($c['name']) ?></div>
                    <div class="check-value"><?= htmlspecialchars($c['value']) ?></div>
                    <?php if (!$c['passed'] || $isWarning): ?>
                    <div class="check-fix" <?php if ($isWarning): ?>style="color: var(--amber); background: var(--amber-bg);"<?php endif; ?>><?= $c['fix'] /* contains safe HTML (code tags) */ ?></div>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <a href="" class="retry-btn">Re-run Check</a>

        <div class="preflight-footer">
            Fix the issues above, then click Re-run Check.<br>
            Once everything passes, setup will continue automatically.
        </div>
    </div>
</body>
</html><?php
    exit;
}

// Run automatically when included
$bbPreflightResults = bb_preflight();
bb_preflight_render($bbPreflightResults);
