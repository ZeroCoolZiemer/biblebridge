<?php
/**
 * BibleBridge Standalone — Setup Page
 * Shows on first visit to configure the reader.
 */

// Pre-flight: check server requirements before anything else
require __DIR__ . '/preflight.php';

// If already configured, redirect to read page
if (file_exists(__DIR__ . '/config.local.php')) {
    $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    header('Location: ' . $baseUrl . '/read');
    exit;
}

// If .installed sentinel exists but config is missing, block re-provision
if (file_exists(__DIR__ . '/.installed')) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup Locked</title>'
       . '<style>body{font-family:system-ui,sans-serif;max-width:520px;margin:4rem auto;padding:0 1.5rem;color:#333}'
       . 'h1{color:#b91c1c;font-size:1.3rem}code{background:#f3f4f6;padding:2px 6px;border-radius:3px;font-size:0.9em}</style></head>'
       . '<body><h1>Setup Locked</h1>'
       . '<p>BibleBridge was previously installed. To re-run setup, restore <code>config.local.php</code> from a backup, '
       . 'or remove the <code>.installed</code> file and visit this page again.</p>'
       . '</body></html>';
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim($_POST['site_name'] ?? '');
    if ($siteName === '') {
        $error = 'Please enter a site name.';
    } else {
        // Provision API key directly
        $siteUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $apiKey = 'bb_free_demo'; // fallback

        $postData = json_encode([
            'site_url'  => $siteUrl,
            'site_name' => $siteName,
            'source'    => 'standalone',
        ]);
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $postData,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents('https://holybible.dev/api/provision', false, $ctx);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (!empty($data['api_key'])) {
                $apiKey = $data['api_key'];
            }
        }

        $siteDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $adminToken = bin2hex(random_bytes(24));

        $config = [
            'mode'        => 'api',
            'api_url'     => 'https://holybible.dev/api',
            'api_key'     => $apiKey,
            'admin_token' => $adminToken,
            'site_name'   => $siteName,
            'site_domain' => $siteDomain,
            'versions'    => [
                'kjv' => 'KJV', 'asv' => 'ASV', 'web' => 'WEB', 'ylt' => 'YLT',
                'lsg' => 'LSG (French)', 'lut' => 'Luther (German)',
                'ara' => 'ARA (Portuguese)', 'cuv' => 'CUV (Chinese)',
                'krv' => 'KRV (Korean)', 'rvr' => 'RVR (Spanish)', 'adb' => 'ADB (Tagalog)',
            ],
        ];

        $configFile = __DIR__ . '/config.local.php';
        $export = var_export($config, true);
        $written = @file_put_contents($configFile, "<?php\nreturn " . $export . ";\n");

        if ($written !== false) {
            // Write sentinel to prevent re-provisioning if config is later lost
            @file_put_contents(__DIR__ . '/.installed', date('c') . "\n");
        }

        if (file_exists(__DIR__ . '/config.local.php')) {
            $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

            // Auto-patch .htaccess RewriteBase for subdirectory installs
            if ($baseUrl !== '') {
                $htaccess = __DIR__ . '/.htaccess';
                if (is_writable($htaccess)) {
                    $contents = file_get_contents($htaccess);
                    $contents = preg_replace(
                        '/^RewriteBase\s+\/\s*$/m',
                        'RewriteBase ' . $baseUrl . '/',
                        $contents
                    );
                    file_put_contents($htaccess, $contents);
                }
            }

            $adminToken = $config['admin_token'] ?? '';
            header('Location: ' . $baseUrl . '/settings?token=' . urlencode($adminToken));
            exit;
        } else {
            $error = 'Could not write config file. Please make sure the biblebridge/ folder is writable by PHP (chmod 755 or 775).';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — BibleBridge Reader</title>
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
            --accent-hover: #9a7209;
            --border: #e8e0d4;
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
        .setup-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 3rem 2.5rem;
            max-width: 460px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        .setup-logo {
            font-family: 'Lora', Georgia, serif;
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
        .setup-subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0.4rem;
            color: var(--text);
        }
        input[type="text"] {
            width: 100%;
            padding: 0.7rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
            margin-bottom: 0.4rem;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: var(--accent);
        }
        .input-hint {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
        .error {
            background: #fef2f2;
            color: #b91c1c;
            padding: 0.7rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }
        button {
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
            transition: background 0.15s;
        }
        button:hover {
            background: var(--accent-hover);
        }
        .setup-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.78rem;
            color: var(--text-muted);
        }
        .setup-footer a {
            color: var(--accent);
            text-decoration: none;
        }
        .setup-footer a:hover { text-decoration: underline; }
        .success-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
        .success-box p { font-size: 0.9rem; color: #166534; margin-bottom: 0.5rem; line-height: 1.5; }
        .success-box p:last-child { margin-bottom: 0; }
        .api-key-display { font-family: 'Courier New', monospace; font-size: 0.78rem; background: #fff; border: 1px solid var(--border); border-radius: 5px; padding: 0.6rem 0.85rem; word-break: break-all; color: var(--text); margin: 0.5rem 0; display: block; user-select: all; }
        .tier-info { font-size: 0.82rem; color: var(--text-muted); margin-top: 0.5rem; }
        .btn-primary { display: block; width: 100%; padding: 0.75rem; font-size: 0.95rem; font-weight: 600; font-family: inherit; background: var(--accent); color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: background 0.15s; text-align: center; text-decoration: none; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-secondary { display: block; width: 100%; padding: 0.65rem; font-size: 0.85rem; font-weight: 500; font-family: inherit; background: transparent; color: var(--accent); border: 1px solid var(--accent); border-radius: 6px; cursor: pointer; transition: background 0.15s, color 0.15s; text-align: center; text-decoration: none; }
        .btn-secondary:hover { background: var(--accent); color: #fff; }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="setup-logo">BibleBridge</div>

        <?php if ($success): ?>

        <p class="setup-subtitle">Your reader is ready.</p>

        <div class="success-box">
            <p>Your API key has been provisioned and saved.</p>
            <div class="tier-info">Free tier &mdash; 250 requests/day.</div>
        </div>

        <?php $adminToken = $config['admin_token'] ?? ''; ?>
        <?php if ($adminToken): ?>
        <div class="success-box" style="background:#eff6ff; border-color:#bfdbfe;">
            <p style="color:#1e40af;">Bookmark this link to manage your reader settings:</p>
            <a href="<?= $baseUrl ?>/settings?token=<?= urlencode($adminToken) ?>" class="api-key-display" style="display:block; text-decoration:none; color:#1e40af;"><?= htmlspecialchars($baseUrl) ?>/settings?token=<?= htmlspecialchars($adminToken) ?></a>
            <div class="tier-info">This is your private admin link. Do not share it publicly.</div>
        </div>
        <?php endif; ?>

        <a href="<?= $baseUrl ?>/read" class="btn-primary">Open Your Reader &rarr;</a>

        <button type="button" class="btn-secondary" onclick="claimRedirect('signup')" style="width:100%;cursor:pointer;margin-top:0.5rem;">Upgrade for Unlimited Access</button>

        <div class="setup-footer">
            Manage your key at <a href="#" onclick="claimRedirect('signup');return false;">holybible.dev</a>
        </div>

        <script>
        function claimRedirect(page) {
            var apiUrl = <?= json_encode($config['api_url'] ?? 'https://holybible.dev/api') ?>;
            var apiKey = <?= json_encode($apiKey) ?>;
            fetch(apiUrl + '/claim-token', {
                method: 'POST',
                headers: { 'X-API-Key': apiKey }
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.status === 'success') {
                    window.open('https://holybible.dev/' + page + '?claim=' + d.claim_token, '_blank');
                } else {
                    window.open('https://holybible.dev/' + page, '_blank');
                }
            })
            .catch(function() {
                window.open('https://holybible.dev/' + page, '_blank');
            });
        }
        </script>

        <?php else: ?>

        <p class="setup-subtitle">
            Set up your free Bible reader. This takes about 10 seconds.
        </p>

        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:0.7rem 1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.6rem;">
            <span style="color:#16a34a;font-size:1.1rem;line-height:1;">&#10003;</span>
            <span style="font-size:0.82rem;color:#166534;">Server check passed &mdash; PHP <?= PHP_VERSION ?>, all requirements met.</span>
        </div>

        <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="site_name">Site Name</label>
            <input type="text" id="site_name" name="site_name"
                   value="<?= htmlspecialchars($_POST['site_name'] ?? '') ?>"
                   placeholder="My Church Bible Reader" required>
            <div class="input-hint">This appears in the header of your reader.</div>

            <button type="submit">Set Up Reader</button>
        </form>

        <div class="setup-footer">
            Powered by <a href="https://holybible.dev" target="_blank" rel="noopener">BibleBridge</a>
            &mdash; Free API key (250 requests/day)
        </div>

        <?php endif; ?>
    </div>
</body>
</html>
