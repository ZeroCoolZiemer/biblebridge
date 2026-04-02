<?php
/**
 * Plan Day page — Standalone (API) version.
 * Instead of querying a local DB for verses, this page shows the reading
 * assignment and links to the reader for each passage.
 * The verse text is fetched via the BibleBridge API for inline display.
 */
require_once dirname(__DIR__) . '/config.php';

$slug    = preg_replace('/[^a-z0-9\-]/', '', $_GET['slug'] ?? '');
$dayNum  = max(1, (int)($_GET['day'] ?? 1));
$dataFile = __DIR__ . '/data/' . $slug . '.json';

if (!$slug || !file_exists($dataFile)) {
    header('Location: ' . $bbBaseUrl . '/plans');
    exit;
}

$plan = json_decode(file_get_contents($dataFile), true);
$totalDays = $plan['days'];
$dayNum    = min($dayNum, $totalDays);

// Find the entry for this day
$entry = null;
foreach ($plan['entries'] as $e) {
    if ($e['day'] === $dayNum) { $entry = $e; break; }
}
if (!$entry) { header('Location: ' . $bbBaseUrl . '/plans/' . $slug); exit; }

// -----------------------------------------------------------
// Parse label to extract reading references
// Labels look like: "Genesis 1-3 . Matthew 1" or "John 1" or "Psalms 1-5 . Proverbs 1"
// -----------------------------------------------------------
$sections = [];

// Split on common separators
$parts = preg_split('/\s*[·•]\s*/', $entry['label']);

foreach ($parts as $part) {
    $part = trim($part);
    if (empty($part)) continue;

    // Try to parse "Book Chapter" or "Book Chapter-Chapter"
    if (preg_match('/^(.+?)\s+(\d+)(?:\s*[-–]\s*(\d+))?$/', $part, $m)) {
        $bookName = trim($m[1]);
        $chStart = (int)$m[2];
        $chEnd = isset($m[3]) ? (int)$m[3] : $chStart;

        $bookId = slugToBookIdMulti(strtolower(str_replace(' ', '-', $bookName)), $books, $localized_books);
        if ($bookId === false) continue;

        $engBookName = $books[$bookId];

        for ($ch = $chStart; $ch <= $chEnd; $ch++) {
            $verses = bb_api_chapter($bookId, $ch, 'kjv');
            $sections[] = [
                'book'      => $bookId,
                'chapter'   => $ch,
                'book_name' => $engBookName,
                'verses'    => $verses,
            ];
        }
    }
}

$prevDay = $dayNum > 1          ? $dayNum - 1 : null;
$nextDay = $dayNum < $totalDays ? $dayNum + 1 : null;

$pageTitle    = 'Day ' . $dayNum . ' — ' . $plan['name'];
$canonicalUrl = $siteUrl . $bbBaseUrl . '/plans/' . $slug . '/day/' . $dayNum;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($plan['name']) ?> — Day <?= $dayNum ?>: <?= htmlspecialchars($entry['label']) ?>">
    <link rel="canonical" href="<?= $canonicalUrl ?>">
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
    <style>
        body { padding-bottom: 90px; }
        @media (max-width: 640px) {
            body { padding-bottom: 150px; }
            .day-complete-bar { bottom: calc(52px + env(safe-area-inset-bottom, 0px)); }
        }
        .day-main { max-width: 680px; margin: 0 auto; padding: calc(var(--header-height) + 2rem) 1.5rem 2rem; }
        .day-context { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 0.5rem; }
        .day-breadcrumb { font-family: 'Inter', sans-serif; font-size: 0.8rem; color: var(--text-muted); }
        .day-breadcrumb a { color: var(--text-muted); text-decoration: none; }
        .day-breadcrumb a:hover { color: var(--accent); }
        .day-breadcrumb span { margin: 0 0.35rem; }
        .day-nav-links { display: flex; gap: 0.5rem; }
        .day-nav-link { font-family: 'Inter', sans-serif; font-size: 0.8rem; color: var(--text-muted); text-decoration: none; padding: 0.3rem 0.65rem; border: 1px solid var(--border-light); border-radius: 5px; transition: border-color 0.12s, color 0.12s; }
        .day-nav-link:hover { border-color: var(--accent); color: var(--accent); }
        .day-header { margin-bottom: 2.5rem; }
        .day-number-label { font-family: 'Inter', sans-serif; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.35rem; }
        .day-reading-label { font-family: 'Lora', Georgia, serif; font-size: 1.6rem; font-weight: 500; color: var(--text-primary); line-height: 1.3; }
        .reading-section { margin-bottom: 3rem; }
        .reading-section + .reading-section { padding-top: 2rem; border-top: 1px solid var(--border-light); }
        .section-heading { font-family: 'Lora', Georgia, serif; font-size: 1.25rem; font-weight: 500; color: var(--text-primary); margin-bottom: 1.25rem; }
        .verses-block .verse { font-family: 'Lora', Georgia, serif; font-size: 1.1rem; line-height: 1.9; color: var(--text-primary); margin-bottom: 0; padding: 0.3rem 0; }
        .verses-block .verse .vnum { font-family: 'Inter', sans-serif; font-size: 0.65em; font-weight: 600; color: var(--text-muted); vertical-align: super; margin-right: 0.2em; user-select: none; }
        .day-complete-bar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 210; background: var(--bg-card); border-top: 1px solid var(--border-light); padding: 0.9rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 -4px 16px rgba(0,0,0,0.07); opacity: 0; transform: translateY(100%); transition: opacity 0.3s ease, transform 0.3s ease; pointer-events: none; }
        .day-complete-bar.shown { opacity: 1; transform: translateY(0); pointer-events: auto; }
        .day-complete-bar-left { font-family: 'Inter', sans-serif; font-size: 0.83rem; color: var(--text-secondary); }
        .day-complete-bar-left strong { color: var(--text-primary); }
        .day-complete-bar-right { display: flex; align-items: center; gap: 0.65rem; }
        .btn-mark { font-family: 'Inter', sans-serif; font-size: 0.875rem; font-weight: 600; background: var(--accent); color: #fff; border: none; border-radius: 6px; padding: 0.6rem 1.3rem; cursor: pointer; transition: background 0.12s; white-space: nowrap; }
        .btn-mark:hover { background: var(--accent-hover); }
        .btn-mark.done { background: transparent; color: var(--accent); border: 1px solid var(--accent); }
        .btn-mark.done:hover { background: var(--bg-hover); }
        .btn-day-next { font-family: 'Inter', sans-serif; font-size: 0.875rem; font-weight: 600; background: var(--accent); color: #fff; border: none; border-radius: 6px; padding: 0.6rem 1.3rem; cursor: pointer; text-decoration: none; display: none; white-space: nowrap; }
        .btn-day-next:hover { background: var(--accent-hover); }
        .btn-day-next.visible { display: inline-block; }
        .complete-check { display: none; align-items: center; gap: 0.4rem; font-family: 'Inter', sans-serif; font-size: 0.83rem; color: var(--accent); font-weight: 600; }
        .complete-check.visible { display: flex; }
        @media (max-width: 480px) {
            .day-complete-bar { flex-direction: column; align-items: stretch; gap: 0.6rem; }
            .day-complete-bar-right { justify-content: flex-end; }
            .day-reading-label { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<header class="reader-header">
    <div class="reader-header-left">
        <a href="<?= $bbBaseUrl ?>/read" class="reader-logo"><?= htmlspecialchars($siteName) ?></a>
        <nav class="reader-header-nav">
            <a href="<?= $bbBaseUrl ?>/read" class="reader-header-nav-link">Read</a>
            <a href="<?= $bbBaseUrl ?>/plans" class="reader-header-nav-link active">Plans</a>
            <a href="<?= $bbBaseUrl ?>/topics" class="reader-header-nav-link">Topics</a>
        </nav>
    </div>
    <div class="reader-header-center"></div>
    <div class="reader-header-right">
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <svg class="theme-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg class="theme-icon-sun"  width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>
    </div>
</header>

<main class="day-main">

    <div class="day-context">
        <div class="day-breadcrumb">
            <a href="<?= $bbBaseUrl ?>/plans">Plans</a>
            <span>&#x203A;</span>
            <a href="<?= $bbBaseUrl ?>/plans/<?= $slug ?>"><?= htmlspecialchars($plan['name']) ?></a>
            <span>&#x203A;</span>
            Day <?= $dayNum ?>
        </div>
        <div class="day-nav-links">
            <?php if ($prevDay): ?>
                <a class="day-nav-link" href="<?= $bbBaseUrl ?>/plans/<?= $slug ?>/day/<?= $prevDay ?>">&larr; Day <?= $prevDay ?></a>
            <?php endif; ?>
            <?php if ($nextDay): ?>
                <a class="day-nav-link" href="<?= $bbBaseUrl ?>/plans/<?= $slug ?>/day/<?= $nextDay ?>">Day <?= $nextDay ?> &rarr;</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="day-header">
        <div class="day-number-label"><?= htmlspecialchars($plan['name']) ?> &mdash; Day <?= $dayNum ?> of <?= $totalDays ?></div>
        <div class="day-reading-label"><?= htmlspecialchars($entry['label']) ?></div>
    </div>

    <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $section): ?>
        <div class="reading-section">
            <h2 class="section-heading">
                <a href="<?= $bbBaseUrl ?>/read/<?= bookToSlug($section['book_name']) ?>/<?= $section['chapter'] ?>" style="color:inherit;text-decoration:none;">
                    <?= htmlspecialchars($section['book_name']) ?>
                    <span style="color:var(--text-muted);font-weight:400;"><?= $section['chapter'] ?></span>
                </a>
            </h2>
            <?php if (!empty($section['verses'])): ?>
            <div class="verses-block">
                <?php foreach ($section['verses'] as $v): ?>
                <p class="verse">
                    <sup class="vnum"><?= (int)$v['verse'] ?></sup><?= htmlspecialchars($v['text']) ?>
                </p>
                <?php endforeach; ?>
            </div>
            <?php elseif (!empty($GLOBALS['bb_api_rate_limited'])): ?>
            <div class="reader-limit-notice" style="padding:1rem;">
                <p>Daily limit reached. <a href="<?= $bbBaseUrl ?>/read/<?= bookToSlug($section['book_name']) ?>/<?= $section['chapter'] ?>">Read in the reader &rarr;</a></p>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted);"><a href="<?= $bbBaseUrl ?>/read/<?= bookToSlug($section['book_name']) ?>/<?= $section['chapter'] ?>">Read <?= htmlspecialchars($section['book_name']) ?> <?= $section['chapter'] ?> &rarr;</a></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color:var(--text-muted);">Could not parse reading assignment. The reading for today is: <strong><?= htmlspecialchars($entry['label']) ?></strong></p>
    <?php endif; ?>

</main>

<?php $bottomNavActive = 'plans'; include dirname(__DIR__) . '/bottom-nav.php'; ?>
<!-- Sticky complete bar -->
<div class="day-complete-bar">
    <div class="day-complete-bar-left">
        <strong>Day <?= $dayNum ?></strong> of <?= $totalDays ?>
        <span id="completedNote" style="display:none;"> &mdash; you finished this one</span>
    </div>
    <div class="day-complete-bar-right">
        <div class="complete-check" id="completeCheck">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><circle cx="7.5" cy="7.5" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M4.5 7.5l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Completed
        </div>
        <button class="btn-mark" id="btnMark">Mark Complete</button>
        <?php if ($nextDay): ?>
        <a class="btn-day-next" id="btnNext" href="<?= $bbBaseUrl ?>/plans/<?= $slug ?>/day/<?= $nextDay ?>">Day <?= $nextDay ?> &rarr;</a>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var SLUG    = <?= json_encode($slug) ?>;
    var DAY     = <?= $dayNum ?>;
    var KEY     = 'bb_plan_' + SLUG;
    var HAS_NEXT = <?= $nextDay ? 'true' : 'false' ?>;

    var themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var html = document.documentElement;
            var dark = html.getAttribute('data-theme') === 'dark';
            html.setAttribute('data-theme', dark ? 'light' : 'dark');
            localStorage.setItem('bb_theme', dark ? 'light' : 'dark');
        });
    }

    function loadData() {
        try { return JSON.parse(localStorage.getItem(KEY)) || null; }
        catch(e) { return null; }
    }
    function saveData(d) { localStorage.setItem(KEY, JSON.stringify(d)); }

    function isCompleted() {
        var d = loadData();
        return d && d.completed && d.completed.indexOf(DAY) !== -1;
    }

    function markComplete() {
        var d = loadData() || { startDate: new Date().toISOString().slice(0,10), completed: [] };
        if (d.completed.indexOf(DAY) === -1) d.completed.push(DAY);
        saveData(d);
        renderBar(true);
    }

    function unmarkComplete() {
        var d = loadData();
        if (!d) return;
        d.completed = d.completed.filter(function(x) { return x !== DAY; });
        saveData(d);
        renderBar(false);
    }

    function renderBar(done) {
        var btnMark = document.getElementById('btnMark');
        var completeCheck = document.getElementById('completeCheck');
        var completedNote = document.getElementById('completedNote');
        var btnNext = document.getElementById('btnNext');

        if (done) {
            btnMark.textContent = 'Undo';
            btnMark.classList.add('done');
            completeCheck.classList.add('visible');
            completedNote.style.display = 'inline';
            if (btnNext && HAS_NEXT) btnNext.classList.add('visible');
        } else {
            btnMark.textContent = 'Mark Complete';
            btnMark.classList.remove('done');
            completeCheck.classList.remove('visible');
            completedNote.style.display = 'none';
            if (btnNext) btnNext.classList.remove('visible');
        }
    }

    var bar = document.querySelector('.day-complete-bar');
    var mainEl = document.querySelector('.day-main');

    function checkScroll() {
        var rect = mainEl.getBoundingClientRect();
        var threshold = rect.top + rect.height * 0.9;
        if (window.innerHeight >= threshold || isCompleted()) {
            bar.classList.add('shown');
        } else {
            bar.classList.remove('shown');
        }
    }

    renderBar(isCompleted());
    checkScroll();
    window.addEventListener('scroll', checkScroll, { passive: true });

    document.getElementById('btnMark').addEventListener('click', function () {
        if (isCompleted()) unmarkComplete();
        else markComplete();
    });
})();
</script>
</body>
</html>
