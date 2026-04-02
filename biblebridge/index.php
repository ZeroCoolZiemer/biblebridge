<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Read the Bible — ' . htmlspecialchars($siteName);
$canonicalUrl = $siteUrl . $bbBaseUrl . '/read';

$otBooks = array_filter($books, fn($id) => $id <= 39, ARRAY_FILTER_USE_KEY);
$ntBooks = array_filter($books, fn($id) => $id >= 40, ARRAY_FILTER_USE_KEY);

// Verse of the day — from API (falls back to static default)
$votdData = bb_api_votd();
if ($votdData && ($votdData['status'] ?? '') === 'success' && !empty($votdData['data'])) {
    $votd = ['ref' => $votdData['data']['reference'] ?? 'John 3:16', 'text' => $votdData['data']['text'] ?? ''];
} else {
    $votd = ['ref' => 'John 3:16', 'text' => 'For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.'];
}

// Parse ref to build a read URL
preg_match('/^(.+?)\s+(\d+)/', $votd['ref'], $rm);
$votd_url = $bbBaseUrl . '/read/' . bookToSlug($rm[1] ?? 'john') . '/' . ($rm[2] ?? '1');

// Popular books with descriptions
$popular = [
    43 => ['name' => 'John',    'desc' => 'Start here — Life of Jesus'],
    19 => ['name' => 'Psalms',  'desc' => 'Read — Prayer & emotion'],
    45 => ['name' => 'Romans',  'desc' => 'Study — Salvation explained'],
    1  => ['name' => 'Genesis', 'desc' => 'Begin — Where it all starts'],
    20 => ['name' => 'Proverbs','desc' => 'Try — Daily wisdom'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="Read the Bible free — all 66 books, reading plans, cross-references, and powerful search.">
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:type" content="website">
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
<body class="reader-index-page">

<header class="reader-header">
    <div class="reader-header-left">
        <a href="<?= $bbBaseUrl ?>/read" class="reader-logo"><?= htmlspecialchars($siteName) ?></a>
        <nav class="reader-header-nav">
            <a href="<?= $bbBaseUrl ?>/read" class="reader-header-nav-link active">Read</a>
            <a href="<?= $bbBaseUrl ?>/plans" class="reader-header-nav-link">Plans</a>
            <a href="<?= $bbBaseUrl ?>/topics" class="reader-header-nav-link">Topics</a>
        </nav>
    </div>
    <div class="reader-header-center">
        <button class="mobile-search-toggle" id="mobileSearchToggle" aria-label="Open search">
            <svg width="18" height="18" viewBox="0 0 14 14" fill="none"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.5"/><path d="M10 10l2.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
        <button class="mobile-search-close" id="mobileSearchClose" aria-label="Close search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
        <form class="reader-search-form" action="<?= $bbBaseUrl ?>/read/search" method="get">
            <input class="reader-search-input" type="search" name="q" placeholder="Search or go to..." autocomplete="off" aria-label="Search scripture">
            <button class="reader-search-btn" type="submit" aria-label="Search">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.5"/><path d="M10 10l2.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </form>
    </div>
    <div class="reader-header-right">
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <svg class="theme-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg class="theme-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>
    </div>
</header>

<main class="reader-index-main">

    <!-- A. PRIMARY: Verse of the Day + CTA -->
    <section class="index-hero-section">
        <div class="votd-block">
            <div class="votd-label">Verse of the day</div>
            <blockquote class="votd-text"><?= htmlspecialchars($votd['text']) ?></blockquote>
            <div class="votd-footer">
                <cite class="votd-ref">
                    <a href="<?= $votd_url ?>"><?= htmlspecialchars($votd['ref']) ?></a>
                </cite>
                <a href="<?= $votd_url ?>" class="votd-read-btn">Read this chapter →</a>
            </div>
        </div>
        <div class="hero-cta-block" id="heroCta">
            <!-- Injected by JS: Continue reading or Start here -->
            <a href="<?= $bbBaseUrl ?>/read/john/1" class="hero-cta-btn">Start reading →</a>
            <span class="hero-cta-hint">New here? Begin with the Gospel of John.</span>
        </div>
    </section>

    <!-- B. SECONDARY: Entry points -->
    <section class="index-section">
        <div class="entry-grid">
            <a href="<?= $bbBaseUrl ?>/plans" class="entry-tile">
                <span class="entry-tile-icon">&#128214;</span>
                <span class="entry-tile-name">Reading Plans</span>
                <span class="entry-tile-desc">Guided paths through Scripture</span>
            </a>
            <a href="<?= $bbBaseUrl ?>/read/search" class="entry-tile">
                <span class="entry-tile-icon">&#128269;</span>
                <span class="entry-tile-name">Search Scripture</span>
                <span class="entry-tile-desc">Find any verse or passage</span>
            </a>
            <a href="<?= $bbBaseUrl ?>/topics" class="entry-tile">
                <span class="entry-tile-icon">&#128330;&#65039;</span>
                <span class="entry-tile-name">Explore Topics</span>
                <span class="entry-tile-desc">Follow themes across the Bible</span>
            </a>
        </div>
    </section>

    <!-- C. POPULAR BOOKS -->
    <section class="index-section">
        <h2 class="index-section-heading">Popular</h2>
        <div class="popular-grid">
            <?php foreach ($popular as $id => $info): ?>
            <a href="<?= $bbBaseUrl ?>/read/<?= bookToSlug($books[$id]) ?>/1" class="popular-card">
                <span class="popular-card-name"><?= htmlspecialchars($info['name']) ?></span>
                <span class="popular-card-desc"><?= htmlspecialchars($info['desc']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- D. BROWSE ALL BOOKS -->
    <section class="index-section index-section-library">
        <div class="library-heading">
            <h2>Browse all 66 books</h2>
            <p>Or jump directly into any book of the Bible</p>
        </div>

        <details class="testament-details">
            <summary class="testament-summary">Old Testament <span class="testament-count">39 books</span></summary>
            <div class="book-grid">
                <?php foreach ($otBooks as $id => $name): ?>
                <a href="<?= $bbBaseUrl ?>/read/<?= bookToSlug($name) ?>/1" class="book-card">
                    <span class="book-card-name"><?= htmlspecialchars($name) ?></span>
                    <span class="book-card-chapters"><?= $max_chapters[$id] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </details>

        <details class="testament-details">
            <summary class="testament-summary">New Testament <span class="testament-count">27 books</span></summary>
            <div class="book-grid">
                <?php foreach ($ntBooks as $id => $name): ?>
                <a href="<?= $bbBaseUrl ?>/read/<?= bookToSlug($name) ?>/1" class="book-card">
                    <span class="book-card-name"><?= htmlspecialchars($name) ?></span>
                    <span class="book-card-chapters"><?= $max_chapters[$id] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </details>
    </section>

</main>

<?php $bottomNavActive = 'read'; include __DIR__ . '/bottom-nav.php'; ?>
<script src="<?= $bbBaseUrl ?>/assets/reader.min.js?v=20260401"></script>
<script>
(function () {
    var BASE = <?= json_encode($bbBaseUrl) ?>;
    // Override hero CTA for returning visitors
    try {
        var lr = JSON.parse(localStorage.getItem('bb_last_read') || 'null');
        if (lr && lr.url && lr.label) {
            var cta = document.getElementById('heroCta');
            if (cta) {
                cta.innerHTML =
                    '<a href="' + lr.url.replace(/"/g, '&quot;') + '" class="hero-cta-btn">' +
                        'Continue reading →' +
                    '</a>' +
                    '<span class="hero-cta-hint">' + lr.label.replace(/</g, '&lt;') + '</span>';
            }
        }
    } catch (e) {}

    // Plan progress badges
    var planDays = {
        'bible-in-a-year': 365, 'nt-in-a-year': 365,
        'nt-in-90-days': 90, 'psalms-and-proverbs': 31, 'gospel-of-john': 21
    };
    try {
        Object.keys(planDays).forEach(function (id) {
            var raw = localStorage.getItem('bb_plan_' + id);
            if (!raw) return;
            var data = JSON.parse(raw);
            var completed = Array.isArray(data.completed) ? data.completed.length : 0;
            if (completed === 0) return;
            var total = planDays[id];
            var pct = Math.round((completed / total) * 100);
            var el = document.getElementById('progress-' + id);
            if (!el) return;
            el.style.display = 'flex';
            el.querySelector('.plan-card-progress-fill').style.width = pct + '%';
            el.querySelector('.plan-card-progress-label').textContent = 'Day ' + completed + ' of ' + total;
        });
    } catch (e) {}
})();
</script>
</body>
</html>
