<?php
http_response_code(404);
if (!isset($bbBaseUrl)) {
    require_once __DIR__ . '/config.php';
}
$pageTitle = $pageTitle ?? 'Page Not Found — ' . htmlspecialchars($siteName);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $bbBaseUrl ?>/assets/fonts/fonts.css">
    <link rel="stylesheet" href="<?= $bbBaseUrl ?>/assets/reader.min.css?v=20260401">
    <link rel="icon" type="image/svg+xml" href="<?= $bbBaseUrl ?>/favicon.svg">
    <script>
        (function () {
            var t = localStorage.getItem('bb_theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<header class="reader-header">
    <div class="reader-header-left">
        <a href="<?= $bbBaseUrl ?>/read" class="reader-logo"><?= htmlspecialchars($siteName) ?></a>
        <nav class="reader-header-nav">
            <a href="<?= $bbBaseUrl ?>/read" class="reader-header-nav-link">Read</a>
            <a href="<?= $bbBaseUrl ?>/plans" class="reader-header-nav-link">Plans</a>
            <a href="<?= $bbBaseUrl ?>/topics" class="reader-header-nav-link">Topics</a>
        </nav>
    </div>
    <div class="reader-header-center"></div>
    <div class="reader-header-right">
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <svg class="theme-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg class="theme-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>
    </div>
</header>

<main class="reader-index-main" style="text-align:center; padding: 4rem 1.5rem;">
    <h1 style="font-size:2rem; margin-bottom:0.5rem;">Page Not Found</h1>
    <p style="color:var(--muted); margin-bottom:2rem;">The page you're looking for doesn't exist or may have moved.</p>
    <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
        <a href="<?= $bbBaseUrl ?>/read" class="hero-cta-btn">Browse the Bible</a>
        <a href="<?= $bbBaseUrl ?>/read/search" class="hero-cta-btn" style="background:var(--surface-alt, #f0ebe3); color:var(--text);">Search Scripture</a>
    </div>
</main>

<?php $bottomNavActive = ''; include __DIR__ . '/bottom-nav.php'; ?>
<script src="<?= $bbBaseUrl ?>/assets/reader.min.js?v=20260401"></script>
</body>
</html>
