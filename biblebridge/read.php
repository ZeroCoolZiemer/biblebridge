<?php
require_once __DIR__ . '/config.php';

// -----------------------------------------------------------
// Input
// -----------------------------------------------------------
$bookSlug = strtolower(trim($_GET['book'] ?? ''));
$chapter  = max(1, (int)($_GET['chapter'] ?? 1));
$version  = strtolower(trim($_GET['v'] ?? 'kjv'));
$highlight = (int)($_GET['verse'] ?? 0);
$parallel  = !empty($_GET['parallel']) && isset($version_names['web']);

if (!isset($version_names[$version])) $version = 'kjv';
if ($parallel) $version = 'kjv';

// -----------------------------------------------------------
// Resolve book
// -----------------------------------------------------------
$bookId = slugToBookIdMulti($bookSlug, $books, $localized_books);

if ($bookId === false) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$bookName      = $books[$bookId];
$canonicalSlug = bookToSlug($bookName);
if ($bookSlug !== $canonicalSlug) {
    $redir = $bbBaseUrl . '/read/' . $canonicalSlug . '/' . $chapter;
    if ($highlight) $redir .= '/' . $highlight;
    $qs = [];
    if ($version !== 'kjv') $qs[] = 'v=' . urlencode($version);
    if ($parallel) $qs[] = 'parallel=1';
    if ($qs) $redir .= '?' . implode('&', $qs);
    header('Location: ' . $redir, true, 301);
    exit;
}
$displayBooks  = $localized_books[$version] ?? $books;
$displayName   = $displayBooks[$bookId] ?? $bookName;
$maxChapter    = $max_chapters[$bookId];
$chapter       = min($chapter, $maxChapter);

// -----------------------------------------------------------
// Full-page cache + IP throttling (protects shared hosts from bots)
// -----------------------------------------------------------
require_once __DIR__ . '/lib/page-cache.php';
$_bbCachedHtml = bb_page_cache_check($canonicalSlug, $chapter, $version, $parallel);
if ($_bbCachedHtml !== false) {
    echo $_bbCachedHtml;
    exit;
}
ob_start(); // capture output for caching

// -----------------------------------------------------------
// Fetch verses from API
// -----------------------------------------------------------
$verses = bb_api_chapter($bookId, $chapter, $version);

$web_by_verse = [];
if ($parallel) {
    $webVerses = bb_api_chapter($bookId, $chapter, 'web');
    foreach ($webVerses as $wv) {
        $web_by_verse[(int)$wv['verse']] = $wv['text'];
    }
}

// -----------------------------------------------------------
// Nav helpers
// -----------------------------------------------------------
$prevUrl = $nextUrl = null;
$qs = $parallel ? '?parallel=1' : ($version !== 'kjv' ? '?v=' . $version : '');

if ($chapter > 1) {
    $prevUrl = $bbBaseUrl . '/read/' . bookToSlug($bookName) . '/' . ($chapter - 1) . $qs;
} elseif ($bookId > 1) {
    $prevBook    = $books[$bookId - 1];
    $prevChapter = $max_chapters[$bookId - 1];
    $prevUrl     = $bbBaseUrl . '/read/' . bookToSlug($prevBook) . '/' . $prevChapter . $qs;
}

if ($chapter < $maxChapter) {
    $nextUrl = $bbBaseUrl . '/read/' . bookToSlug($bookName) . '/' . ($chapter + 1) . $qs;
} elseif ($bookId < 66) {
    $nextBook = $books[$bookId + 1];
    $nextUrl  = $bbBaseUrl . '/read/' . bookToSlug($nextBook) . '/1' . $qs;
}

// -----------------------------------------------------------
// SEO
// -----------------------------------------------------------
$firstVerse = $verses[0]['text'] ?? '';
$descSnippet = substr(strip_tags($firstVerse), 0, 155);
$pageTitle   = $parallel
    ? "{$displayName} {$chapter} — KJV / WEB Parallel | " . htmlspecialchars($siteName)
    : "{$displayName} {$chapter} — " . strtoupper($version) . " | " . htmlspecialchars($siteName);
$canonicalUrl = $siteUrl . $bbBaseUrl . '/read/' . bookToSlug($bookName) . '/' . $chapter;

// -----------------------------------------------------------
// Sidebar / switcher
// -----------------------------------------------------------
$baseReadUrl = $bbBaseUrl . '/read/' . bookToSlug($bookName) . '/' . $chapter;
$sidebarBooks = $displayBooks;
$otBooks = array_filter($sidebarBooks, fn($id) => $id <= 39, ARRAY_FILTER_USE_KEY);
$ntBooks = array_filter($sidebarBooks, fn($id) => $id >= 40, ARRAY_FILTER_USE_KEY);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($descSnippet) ?>">
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($descSnippet) ?>">
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
            if (localStorage.getItem('bb_sidebar_closed') === '1') {
                document.documentElement.setAttribute('data-sidebar', 'closed');
            }
        })();
    </script>
</head>
<body data-version="<?= htmlspecialchars($version) ?>" data-book="<?= htmlspecialchars(bookToSlug($bookName)) ?>" data-chapter="<?= $chapter ?>">

<!-- ===================== TOP NAV ===================== -->
<header class="reader-header">
    <div class="reader-header-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </button>
        <a href="<?= $bbBaseUrl ?>/read" class="reader-logo"><?= htmlspecialchars($siteName) ?></a>
        <nav class="reader-header-nav">
            <a href="<?= $bbBaseUrl ?>/read" class="reader-header-nav-link">Read</a>
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
            <input type="hidden" name="v" value="<?= htmlspecialchars($version) ?>">
            <input type="hidden" name="book" value="<?= htmlspecialchars(bookToSlug($bookName)) ?>">
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
        <div class="version-picker">
            <button class="version-btn" id="versionBtn" aria-haspopup="true" aria-expanded="false">
                <?= strtoupper(htmlspecialchars($version)) ?> <svg width="10" height="6" viewBox="0 0 10 6"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
            </button>
            <div class="version-dropdown" id="versionDropdown" role="menu">
                <?php foreach ($version_names as $vKey => $vLabel): ?>
                    <a href="<?= $baseReadUrl ?>?v=<?= $vKey ?>" class="version-option<?= $vKey === $version ? ' active' : '' ?>" role="menuitem">
                        <span class="version-code"><?= strtoupper($vKey) ?></span>
                        <span class="version-label"><?= htmlspecialchars($vLabel) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (isset($version_names['web'])): ?>
        <button class="parallel-toggle-btn<?= $parallel ? ' active' : '' ?>" id="parallelToggleBtn" title="Toggle parallel view (KJV / WEB)">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><rect x="1" y="2" width="5" height="10" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="8" y="2" width="5" height="10" rx="1" stroke="currentColor" stroke-width="1.4"/></svg>
            Compare
        </button>
        <?php endif; ?>
        <div class="font-controls">
            <button class="font-btn" id="fontDown" aria-label="Decrease font size">A<sup>-</sup></button>
            <button class="font-btn" id="fontUp" aria-label="Increase font size">A<sup>+</sup></button>
        </div>
    </div>
</header>

<!-- ===================== LAYOUT ===================== -->
<div class="reader-layout">

    <!-- SIDEBAR -->
    <nav class="reader-sidebar" id="readerSidebar" aria-label="Bible navigation">
        <div class="sidebar-inner">
            <div class="sidebar-section">
                <button class="sidebar-testament-btn<?= $bookId <= 39 ? ' open' : '' ?>" data-target="ot-list">
                    Old Testament
                    <svg width="10" height="6" viewBox="0 0 10 6"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
                </button>
                <ul class="sidebar-book-list<?= $bookId <= 39 ? ' open' : '' ?>" id="ot-list">
                    <?php foreach ($otBooks as $id => $name): ?>
                    <li>
                        <button class="sidebar-book-btn<?= $id === $bookId ? ' active' : '' ?>" data-book-id="<?= $id ?>" data-book="<?= htmlspecialchars(bookToSlug($books[$id])) ?>">
                            <?= htmlspecialchars($name) ?>
                        </button>
                        <?php if ($id === $bookId): ?>
                        <ul class="sidebar-chapter-list" id="chapter-list">
                            <?php for ($c = 1; $c <= $maxChapter; $c++): ?>
                            <li>
                                <a href="<?= $bbBaseUrl ?>/read/<?= bookToSlug($bookName) ?>/<?= $c ?><?= $qs ?>" class="sidebar-chapter<?= $c === $chapter ? ' active' : '' ?>"><?= $c ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="sidebar-section">
                <button class="sidebar-testament-btn<?= $bookId >= 40 ? ' open' : '' ?>" data-target="nt-list">
                    New Testament
                    <svg width="10" height="6" viewBox="0 0 10 6"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
                </button>
                <ul class="sidebar-book-list<?= $bookId >= 40 ? ' open' : '' ?>" id="nt-list">
                    <?php foreach ($ntBooks as $id => $name): ?>
                    <li>
                        <button class="sidebar-book-btn<?= $id === $bookId ? ' active' : '' ?>" data-book-id="<?= $id ?>" data-book="<?= htmlspecialchars(bookToSlug($books[$id])) ?>">
                            <?= htmlspecialchars($name) ?>
                        </button>
                        <?php if ($id === $bookId): ?>
                        <ul class="sidebar-chapter-list" id="chapter-list">
                            <?php for ($c = 1; $c <= $maxChapter; $c++): ?>
                            <li>
                                <a href="<?= $bbBaseUrl ?>/read/<?= bookToSlug($bookName) ?>/<?= $c ?><?= $qs ?>" class="sidebar-chapter<?= $c === $chapter ? ' active' : '' ?>"><?= $c ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="sidebar-footer-nav">
            <a href="<?= $bbBaseUrl ?>/plans" class="sidebar-footer-link">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Reading Plans
            </a>
            <a href="<?= $bbBaseUrl ?>/topics" class="sidebar-footer-link">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/></svg>
                Topic Explorer
            </a>
        </div>
    </nav>

    <!-- READING PANE -->
    <main class="reader-main" id="readerMain">
        <div class="reading-progress-bar" id="readingProgressBar"></div>
        <div class="reader-content" id="readerContent">

            <div class="chapter-nav chapter-nav-top">
                <?php if ($prevUrl): ?>
                <a href="<?= htmlspecialchars($prevUrl) ?>" class="chapter-nav-btn" id="prevBtn">&larr; Prev</a>
                <?php else: ?>
                <span class="chapter-nav-btn disabled">&larr; Prev</span>
                <?php endif; ?>
                <span class="chapter-nav-label"><?= htmlspecialchars($displayName) ?> <?= $chapter ?> <span class="of-total">of <?= $maxChapter ?></span></span>
                <?php if ($nextUrl): ?>
                <a href="<?= htmlspecialchars($nextUrl) ?>" class="chapter-nav-btn" id="nextBtn">Next &rarr;</a>
                <?php else: ?>
                <span class="chapter-nav-btn disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>

            <h1 class="chapter-heading"><?= htmlspecialchars($displayName) ?> <span class="chapter-number"><?= $chapter ?></span></h1>

            <?php if (empty($verses) && !empty($GLOBALS['bb_api_rate_limited'])): ?>
            <div class="reader-limit-notice">
                <p>This Bible reader is popular right now and has reached its daily limit.</p>
                <p>Please try again in a few minutes, or come back tomorrow.</p>
            </div>
            <?php elseif (empty($verses)): ?>
            <p class="reader-error">This chapter could not be loaded. Please try again.</p>
            <?php elseif ($parallel): ?>
            <div class="parallel-grid" id="versesBlock">
                <div class="parallel-header-row" aria-hidden="true">
                    <div class="parallel-col-label">KJV <span class="parallel-col-sublabel">Classic</span></div>
                    <div class="parallel-col-label">WEB <span class="parallel-col-sublabel">Modern</span></div>
                </div>
                <?php foreach ($verses as $v):
                    $vn = (int)$v['verse'];
                    $webText = $web_by_verse[$vn] ?? '';
                ?>
                <div class="verse<?= $highlight && $vn === $highlight ? ' verse-highlight' : '' ?>" id="v<?= $vn ?>" data-verse="<?= $vn ?>">
                    <sup class="vnum"><?= $vn ?></sup>
                    <span class="parallel-kjv"><?= htmlspecialchars($v['text']) ?></span>
                    <span class="parallel-web"><?= htmlspecialchars($webText) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="verses-block" id="versesBlock">
                <?php foreach ($verses as $v): ?>
                <p class="verse<?= $highlight && (int)$v['verse'] === $highlight ? ' verse-highlight' : '' ?>" id="v<?= (int)$v['verse'] ?>" data-verse="<?= (int)$v['verse'] ?>">
                    <sup class="vnum"><?= (int)$v['verse'] ?></sup><?= htmlspecialchars($v['text']) ?>
                </p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="chapter-nav chapter-nav-bottom">
                <?php if ($prevUrl): ?>
                <a href="<?= htmlspecialchars($prevUrl) ?>" class="chapter-nav-btn">&larr; Prev</a>
                <?php else: ?>
                <span class="chapter-nav-btn disabled">&larr; Prev</span>
                <?php endif; ?>
                <span class="chapter-nav-label"><?= htmlspecialchars($displayName) ?> <?= $chapter ?></span>
                <?php if ($nextUrl): ?>
                <a href="<?= htmlspecialchars($nextUrl) ?>" class="chapter-nav-btn">Next &rarr;</a>
                <?php else: ?>
                <span class="chapter-nav-btn disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>

            <div class="reader-permission">Public Domain</div>

        </div>

        <!-- SIDE PANEL: Cross-References -->
        <aside class="xref-panel" id="xrefPanel" aria-hidden="true">
            <div class="xref-panel-header">
                <div class="panel-verse-label" id="panelVerseLabel"></div>
                <button class="xref-close" id="xrefClose" aria-label="Close">&times;</button>
            </div>
            <div class="xref-panel-body" id="xrefBody">
                <p class="xref-hint">Click a verse to see cross-references.</p>
            </div>
            <div class="xref-panel-footer">
                <a href="<?= $bbBaseUrl ?>/read" class="xref-api-link"><?= htmlspecialchars($siteDomain) ?></a>
            </div>
        </aside>
    </main>

</div>

<!-- SIDEBAR OVERLAY (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<!-- PANEL BACKDROP (mobile bottom sheet) -->
<div class="panel-backdrop" id="panelBackdrop"></div>

<script>
    var READER_VERSION      = <?= json_encode($version) ?>;
    var READER_BOOK         = <?= json_encode(bookToSlug($bookName)) ?>;
    var READER_BOOK_DISPLAY = <?= json_encode($displayName) ?>;
    var READER_CHAPTER      = <?= $chapter ?>;
    var HIGHLIGHT_VERSE     = <?= $highlight ?>;
    var BASE_READ_URL       = <?= json_encode($baseReadUrl) ?>;
    var READER_PARALLEL     = <?= $parallel ? 'true' : 'false' ?>;
    var SITE_DOMAIN         = <?= json_encode($siteDomain) ?>;
    var BB_BASE_URL         = <?= json_encode($bbBaseUrl) ?>;
<?php
$slugMap = [];
foreach ($displayBooks as $id => $name) {
    $slugMap[bookToSlug($books[$id])] = $name;
}
?>
    var BOOK_NAMES          = <?= json_encode($slugMap, JSON_UNESCAPED_UNICODE) ?>;

    (function () {
        var btn = document.getElementById('parallelToggleBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var url = new URL(window.location.href);
            if (url.searchParams.has('parallel')) {
                url.searchParams.delete('parallel');
            } else {
                url.searchParams.set('parallel', '1');
                url.searchParams.delete('v');
            }
            window.location.href = url.toString();
        });
    })();
</script>
<?php $bottomNavActive = 'read'; include __DIR__ . '/bottom-nav.php'; ?>
<?php
// Signed beacon — no raw API key exposed in page source
$_bbSlot = floor(time() / 600); // 10-minute window
$_bbChapterKey = $canonicalSlug . '-' . $chapter;
$_bbPayload = $bbInstall['site_domain'] . '|' . $_bbChapterKey . '|' . $_bbSlot;
$_bbSig = hash_hmac('sha256', $_bbPayload, $bbInstall['api_key']);
?>
<script>
(function(){
    if (typeof navigator.sendBeacon !== 'function') return;
    var sid = sessionStorage.getItem('bb_sid');
    if (!sid) { sid = Math.random().toString(36).substr(2,12) + Date.now().toString(36); sessionStorage.setItem('bb_sid', sid); }
    navigator.sendBeacon(<?= json_encode(rtrim($bbInstall['api_url'], '/') . '/reader-session') ?>,
        JSON.stringify({site:<?= json_encode($bbInstall['site_domain']) ?>,chapter:<?= json_encode($_bbChapterKey) ?>,translation:<?= json_encode($version) ?>,slot:<?= $_bbSlot ?>,sig:<?= json_encode($_bbSig) ?>,session:sid})
    );
})();
</script>
<script src="<?= $bbBaseUrl ?>/assets/reader.min.js?v=20260401"></script>
</body>
</html>
<?php
// Write rendered page to disk cache (only if we got verses — don't cache errors)
$_bbRenderedHtml = ob_get_flush();
if (!empty($verses)) {
    bb_page_cache_write($canonicalSlug, $chapter, $version, $parallel, $_bbRenderedHtml);
}
?>
