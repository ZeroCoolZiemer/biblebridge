<?php
require_once __DIR__ . '/config.php';

$query      = trim($_GET['q'] ?? '');
$version    = strtolower(trim($_GET['v'] ?? 'kjv'));
$page       = max(1, (int)($_GET['p'] ?? 1));
$perPage    = 25;
$bookFilter = (int)($_GET['book'] ?? 0);
if ($bookFilter < 1 || $bookFilter > 66) $bookFilter = 0;
if (!isset($version_names[$version])) $version = 'kjv';

// -----------------------------------------------------------
// Reference redirect — try to resolve as a scripture reference
// -----------------------------------------------------------
$contextBook = trim($_GET['book'] ?? '');

if ($query !== '') {
    // Context-aware: bare number -> chapter in current book
    if ($contextBook && preg_match('/^(\d+)(?::(\d+))?$/', $query, $numMatch)) {
        $ctxBookId = slugToBookIdMulti($contextBook, $books, $localized_books);
        if ($ctxBookId !== false) {
            $ch = (int)$numMatch[1];
            if ($ch >= 1 && $ch <= ($max_chapters[$ctxBookId] ?? 999)) {
                $url = $bbBaseUrl . '/read/' . bookToSlug($books[$ctxBookId]) . '/' . $ch;
                if (!empty($numMatch[2])) $url .= '/' . $numMatch[2];
                if ($version !== 'kjv') $url .= '?v=' . $version;
                header('Location: ' . $url);
                exit;
            }
        }
    }

    $bookId = slugToBookIdMulti($query, $books, $localized_books);
    if ($bookId !== false) {
        header('Location: ' . $bbBaseUrl . '/read/' . bookToSlug($books[$bookId]) . '/1');
        exit;
    }
    // Try "Book Chapter" or "Book Chapter:Verse"
    if (preg_match('/^(.+?)\s+(\d+)(?::(\d+))?$/', $query, $m)) {
        $refBookId = slugToBookIdMulti(trim($m[1]), $books, $localized_books);
        if ($refBookId !== false) {
            $url = $bbBaseUrl . '/read/' . bookToSlug($books[$refBookId]) . '/' . $m[2];
            if (!empty($m[3])) $url .= '/' . $m[3];
            if ($version !== 'kjv') $url .= '?v=' . $version;
            header('Location: ' . $url);
            exit;
        }
    }
    // Passage nickname lookup (e.g. "beatitudes" -> Matthew 5:3-12)
    global $PASSAGE_NICKNAMES;
    $qLower = mb_strtolower(trim($query));
    if (isset($PASSAGE_NICKNAMES[$qLower])) {
        $p = $PASSAGE_NICKNAMES[$qLower];
        $url = $bbBaseUrl . '/read/' . bookToSlug($books[$p['book_id']]) . '/' . $p['chapter'];
        if ($p['verse_start'] > 1) $url .= '/' . $p['verse_start'];
        if ($version !== 'kjv') $url .= '?v=' . $version;
        header('Location: ' . $url);
        exit;
    }
    // Fallback: resolve via passage API (handles john3:16, gen1:1, abbreviations, etc.)
    $passageData = bb_api_get('/passage', ['ref' => $query, 'version' => $version]);
    if ($passageData && ($passageData['status'] ?? '') === 'success' && !empty($passageData['data'])) {
        $first = $passageData['data'][0];
        $bookSlug = strtolower(str_replace(' ', '-', $first['book']));
        $url = $bbBaseUrl . '/read/' . $bookSlug . '/' . $first['chapter'];
        if (($first['verse'] ?? 0) > 0) $url .= '/' . $first['verse'];
        if ($version !== 'kjv') $url .= '?v=' . $version;
        header('Location: ' . $url);
        exit;
    }
}

// -----------------------------------------------------------
// Fulltext search via API
// -----------------------------------------------------------
$results        = [];
$totalCount     = 0;
$totalPages     = 1;
$error          = null;
$booksWithHits  = [];

if ($query !== '' && mb_strlen($query) >= 3) {
    $apiData = bb_api_search($query, $version, $page, $perPage, $bookFilter);
    if (!empty($GLOBALS['bb_api_rate_limited'])) {
        $error = '__rate_limited__';
    } elseif ($apiData && ($apiData['status'] ?? '') === 'success') {
        $totalCount    = $apiData['total_count'] ?? ($apiData['results_count'] ?? 0);
        $totalPages    = max(1, (int)ceil($totalCount / $perPage));
        $booksWithHits = $apiData['book_facets'] ?? [];
        usort($booksWithHits, fn($a, $b) => (int)$a['book_id'] <=> (int)$b['book_id']);
        foreach (($apiData['data'] ?? []) as $r) {
            $results[] = [
                'book_id' => $r['book']['id'] ?? 0,
                'chapter' => $r['chapter'] ?? 0,
                'verse'   => $r['verse'] ?? 0,
                'text'    => $r['text'] ?? '',
            ];
        }
    } else {
        $error = 'Search failed. Please try again.';
    }
} elseif ($query !== '') {
    $error = 'Query too short — enter at least 3 characters.';
}

// -----------------------------------------------------------
// Highlight matches in text
// -----------------------------------------------------------
function highlightMatches(string $text, string $query): string {
    $words = array_filter(preg_split('/\s+/', preg_quote($query, '/')));
    if (empty($words)) return htmlspecialchars($text);
    $pattern = '/(' . implode('|', $words) . ')/i';
    return preg_replace($pattern, '<em>$1</em>', htmlspecialchars($text));
}

$pageTitle = $query ? 'Search: ' . htmlspecialchars($query) . ' — ' . htmlspecialchars($siteName) : 'Search — ' . htmlspecialchars($siteName);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="robots" content="noindex">
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
            <input class="reader-search-input" type="search" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Search or go to..." autocomplete="off" autofocus aria-label="Search scripture">
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

<main class="search-results-main">
    <a href="<?= $bbBaseUrl ?>/read" class="search-back">&larr; Back to Bible</a>

    <?php if ($query === ''): ?>
        <h1 class="search-results-heading">Search the Bible</h1>
        <p class="search-hint">Enter a word, phrase, or reference like "John 3:16" or "God so loved the world".</p>
        <div class="search-suggestions">
            <span class="search-suggestions-label">Try:</span>
            <a href="<?= $bbBaseUrl ?>/read/search?q=love" class="search-suggestion-chip">love</a>
            <a href="<?= $bbBaseUrl ?>/read/search?q=faith" class="search-suggestion-chip">faith</a>
            <a href="<?= $bbBaseUrl ?>/read/search?q=grace" class="search-suggestion-chip">grace</a>
            <a href="<?= $bbBaseUrl ?>/read/search?q=peace" class="search-suggestion-chip">peace</a>
            <a href="<?= $bbBaseUrl ?>/read/search?q=forgiveness" class="search-suggestion-chip">forgiveness</a>
        </div>

    <?php elseif ($error === '__rate_limited__'): ?>
        <h1 class="search-results-heading">Search: <span><?= htmlspecialchars($query) ?></span></h1>
        <div class="reader-limit-notice">
            <p>This Bible reader is popular right now and has reached its daily limit.</p>
            <p>Please try again in a few minutes, or come back tomorrow.</p>
        </div>

    <?php elseif ($error): ?>
        <h1 class="search-results-heading">Search: <span><?= htmlspecialchars($query) ?></span></h1>
        <p class="search-empty"><?= htmlspecialchars($error) ?></p>

    <?php elseif (empty($results)): ?>
        <h1 class="search-results-heading">Search: <span><?= htmlspecialchars($query) ?></span></h1>
        <p class="search-empty">No results found in <?= strtoupper($version) ?>.</p>

    <?php else:
        $rangeFrom = ($page - 1) * $perPage + 1;
        $rangeTo   = min($page * $perPage, $totalCount);
        $baseUrl   = $bbBaseUrl . '/read/search?q=' . urlencode($query) . ($version !== 'kjv' ? '&v=' . $version : '');
        $searchDisplayBooks = $localized_books[$version] ?? $books;
    ?>
        <h1 class="search-results-heading">Search: <span><?= htmlspecialchars($query) ?></span></h1>
        <div class="search-toolbar">
            <span class="search-count">
                <?= number_format($totalCount) ?> result<?= $totalCount !== 1 ? 's' : '' ?>
                <?php if ($bookFilter > 0 && isset($books[$bookFilter])): ?>
                    in <?= htmlspecialchars($searchDisplayBooks[$bookFilter] ?? $books[$bookFilter]) ?>
                <?php endif; ?>
                (<?= strtoupper($version) ?>)
                <?php if ($totalPages > 1): ?> &mdash; <?= $rangeFrom ?>&ndash;<?= $rangeTo ?><?php endif; ?>
            </span>
            <?php if (!empty($booksWithHits)): ?>
            <form class="search-filter-bar" action="<?= $bbBaseUrl ?>/read/search" method="get">
                <input type="hidden" name="v" value="<?= htmlspecialchars($version) ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($query) ?>">
                <select class="search-filter-select" id="bookFilter" name="book" onchange="this.form.submit()" aria-label="Filter by book">
                    <option value="">All books</option>
                    <?php foreach ($booksWithHits as $facet):
                        $fBookId = (int)$facet['book_id'];
                        $fCount  = (int)$facet['cnt'];
                        $fName   = ($localized_books[$version][$fBookId] ?? null) ?: ($books[$fBookId] ?? '');
                        if (!$fName) continue;
                    ?>
                    <option value="<?= $fBookId ?>"<?= $fBookId === $bookFilter ? ' selected' : '' ?>><?= htmlspecialchars($fName) ?> (<?= $fCount ?>)</option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>

        <?php
        foreach ($results as $row):
            $bookId      = (int)$row['book_id'];
            $bookNameEn  = $books[$bookId] ?? 'Unknown';
            $bookDisplay = $searchDisplayBooks[$bookId] ?? $bookNameEn;
            $chapter     = (int)$row['chapter'];
            $verse       = (int)$row['verse'];
            $url = $bbBaseUrl . '/read/' . bookToSlug($bookNameEn) . '/' . $chapter . '/' . $verse . ($version !== 'kjv' ? '?v=' . $version : '');
        ?>
        <div class="search-result-item">
            <a href="<?= htmlspecialchars($url) ?>" class="search-result-ref"><?= htmlspecialchars("{$bookDisplay} {$chapter}:{$verse}") ?></a>
            <p class="search-result-text"><?= highlightMatches($row['text'], $query) ?></p>
        </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1):
            $pageBaseUrl = $baseUrl . ($bookFilter > 0 ? '&book=' . $bookFilter : '');
        ?>
        <nav class="search-pagination">
            <?php if ($page > 1): ?>
                <a href="<?= $pageBaseUrl ?>&p=<?= $page - 1 ?>" class="search-page-btn">&larr; Prev</a>
            <?php else: ?>
                <span class="search-page-btn disabled">&larr; Prev</span>
            <?php endif; ?>

            <span class="search-page-info">Page <?= $page ?> of <?= $totalPages ?></span>

            <?php if ($page < $totalPages): ?>
                <a href="<?= $pageBaseUrl ?>&p=<?= $page + 1 ?>" class="search-page-btn">Next &rarr;</a>
            <?php else: ?>
                <span class="search-page-btn disabled">Next &rarr;</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php $bottomNavActive = 'search'; include __DIR__ . '/bottom-nav.php'; ?>
<script src="<?= $bbBaseUrl ?>/assets/reader.min.js?v=20260401"></script>
</body>
</html>
