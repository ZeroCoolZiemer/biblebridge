<?php
/**
 * Topic Explorer — local mode
 * Scripture-first topic detail and browse pages with direct DB access.
 * Included by topic-explorer.php.
 *
 * Phase 1a reframe (2026-04-09): description prose + directional flow UI stripped.
 * Topic rows keep `description` and `topic_type` in DB for admin/diagnostic use;
 * neither is rendered to the user. Relations/BFS/reading-path computation removed —
 * shared-verse adjacency will be added in Task 3.
 */

// $slug and config vars already set by topic-explorer.php

if (!empty($slug)) {
    // =========================================================
    // DETAIL MODE
    // =========================================================
    $db = _localDb();

    $stmt = $db->prepare("SELECT id, slug, name FROM topics WHERE slug = :s LIMIT 1");
    $stmt->execute([':s' => $slug]);
    $topicRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$topicRow) {
        http_response_code(404);
        $pageTitle = 'Topic Not Found — BibleBridge';
        include __DIR__ . '/404.php';
        exit;
    }

    $topicId   = (int)$topicRow['id'];
    $topicName = $topicRow['name'];

    $topic = [
        'slug' => $topicRow['slug'],
        'name' => $topicName,
    ];

    $pageTitle    = htmlspecialchars($topicName) . ' — BibleBridge Topic';
    $canonicalUrl = $siteUrl . '/topics/' . htmlspecialchars($slug);

    // --- Load books ---
    if (!isset($GLOBALS['_localBooks_en'])) {
        require_once '/var/www/html/book-arrays.php';
        $GLOBALS['_localBooks_en'] = $books_en;
    }
    $booksEn = $GLOBALS['_localBooks_en'];

    // --- Shared-verse adjacency (Task 3) ---
    require_once '/var/www/html/includes/topic-adjacency.php';
    $adjacency = bb_topic_adjacency($db, $topicId, 8);

    // --- Meta description helper (Task 6) ---
    require_once '/var/www/html/includes/topic-meta.php';

    // --- Anchor verses with OT/NT grouping ---
    $ancStmt = $db->prepare(
        "SELECT ta.verse_index, ta.score, v.text, v.book, v.chapter, v.verse
         FROM topic_anchors ta
         JOIN bible_verses_kjv v ON v.verse_index = ta.verse_index
         WHERE ta.topic_id = :tid
         ORDER BY ta.score DESC, v.book ASC, v.chapter ASC, v.verse ASC"
    );
    $ancStmt->execute([':tid' => $topicId]);
    $anchorRows = $ancStmt->fetchAll(PDO::FETCH_ASSOC);

    $otAnchors = [];
    $ntAnchors = [];
    foreach ($anchorRows as $ar) {
        $bid = (int)$ar['book'];
        $bookName = $booksEn[$bid] ?? '';
        $entry = [
            'ref'     => "$bookName {$ar['chapter']}:{$ar['verse']}",
            'slug'    => bookToSlug($bookName),
            'chapter' => (int)$ar['chapter'],
            'verse'   => (int)$ar['verse'],
            'text'    => $ar['text'],
        ];
        if ($bid <= 39) {
            $otAnchors[] = $entry;
        } else {
            $ntAnchors[] = $entry;
        }
    }
    $totalAnchors = count($anchorRows);

    // Build meta description from the top 3 anchor refs (already sorted by score DESC)
    $topRefStrs = [];
    foreach (array_slice($anchorRows, 0, 3) as $ar) {
        $bookName = $booksEn[(int)$ar['book']] ?? '';
        if ($bookName) {
            $topRefStrs[] = "$bookName {$ar['chapter']}:{$ar['verse']}";
        }
    }
    $metaDescription = bb_generate_topic_meta_description($topicName, $topRefStrs, $totalAnchors);

} else {
    // =========================================================
    // BROWSE MODE
    // =========================================================
    $db = _localDb();

    $stmt = $db->query(
        "SELECT t.id, t.slug, t.name, t.topic_type,
                COUNT(ta.verse_index) AS anchor_count
         FROM topics t
         LEFT JOIN topic_anchors ta ON ta.topic_id = t.id
         GROUP BY t.id
         ORDER BY t.name"
    );
    $allTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Top 2 anchor refs per topic for card preview (Task 7.5) ---
    if (!isset($GLOBALS['_localBooks_en'])) {
        require_once '/var/www/html/book-arrays.php';
        $GLOBALS['_localBooks_en'] = $books_en;
    }
    $booksEn = $GLOBALS['_localBooks_en'];

    $ancStmt = $db->query(
        "SELECT ta.topic_id, ta.score, v.book, v.chapter, v.verse
         FROM topic_anchors ta
         JOIN bible_verses_kjv v ON v.verse_index = ta.verse_index
         ORDER BY ta.topic_id, ta.score DESC, v.book ASC, v.chapter ASC, v.verse ASC"
    );
    $topAnchorsByTopic = [];
    foreach ($ancStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $tid = (int)$r['topic_id'];
        if (!isset($topAnchorsByTopic[$tid])) $topAnchorsByTopic[$tid] = [];
        if (count($topAnchorsByTopic[$tid]) >= 2) continue;
        $bookName = $booksEn[(int)$r['book']] ?? '';
        if ($bookName) {
            $topAnchorsByTopic[$tid][] = "$bookName {$r['chapter']}:{$r['verse']}";
        }
    }
    foreach ($allTopics as &$t) {
        $t['top_anchors'] = $topAnchorsByTopic[(int)$t['id']] ?? [];
    }
    unset($t);

    // --- Tier curation (Task 7.5c) ---
    $tiers = require '/var/www/html/includes/topic-tiers.php';
    $tierAOrder = array_flip($tiers['tier_a']);
    $tierBOrder = array_flip($tiers['tier_b']);
    $topicsByslug = [];
    foreach ($allTopics as $t) $topicsByslug[$t['slug']] = $t;

    // Tier A: render in topic-tiers.php order (teaching sequence, NOT alpha).
    // The shelf label "Start with doctrine" promises intent, so order matters.
    $tierATopics = [];
    foreach ($tiers['tier_a'] as $slug) {
        if (isset($topicsByslug[$slug])) $tierATopics[] = $topicsByslug[$slug];
    }
    // Tier B: alphabetical by display name. There's no canonical learning
    // sequence for "Anxiety vs Depression vs Doubt" — alpha is the calm default.
    $tierBTopics = [];
    foreach ($tiers['tier_b'] as $slug) {
        if (isset($topicsByslug[$slug])) $tierBTopics[] = $topicsByslug[$slug];
    }
    usort($tierBTopics, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    $tierALabel = $tiers['tier_a_label'] ?? 'Start with doctrine';
    $tierAIntro = $tiers['tier_a_intro'] ?? '';
    $tierBLabel = $tiers['tier_b_label'] ?? 'For pastoral care & life';
    $tierBIntro = $tiers['tier_b_intro'] ?? '';

    $topic = null;
    $pageTitle    = 'Scripture Topics — BibleBridge';
    $canonicalUrl = $siteUrl . '/topics';
    $metaDescription = 'Browse scripture by topic on BibleBridge — anchor verses curated for each theme, with cross-references and multiple translations.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= $siteUrl ?>/og/<?= !empty($topic) ? 'topic.php?slug=' . htmlspecialchars($topic['slug']) . '&v=20260409' : 'biblebridge.png' ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $pageTitle ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="twitter:image" content="<?= $siteUrl ?>/og/<?= !empty($topic) ? 'topic.php?slug=' . htmlspecialchars($topic['slug']) . '&v=20260409' : 'biblebridge.png' ?>">
    <?php if (!empty($topic)): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "BreadcrumbList",
                "itemListElement": [
                    {"@type": "ListItem", "position": 1, "name": "Home", "item": "<?= $siteUrl ?>"},
                    {"@type": "ListItem", "position": 2, "name": "Topics", "item": "<?= $siteUrl ?>/topics"},
                    {"@type": "ListItem", "position": 3, "name": "<?= htmlspecialchars($topic['name']) ?>"}
                ]
            },
            {
                "@type": "WebPage",
                "name": "<?= htmlspecialchars($topic['name']) ?>",
                "description": "<?= htmlspecialchars($metaDescription) ?>",
                "url": "<?= $canonicalUrl ?>",
                "isPartOf": {"@type": "WebSite", "name": "BibleBridge", "url": "<?= $siteUrl ?>"},
                "image": "<?= $siteUrl ?>/og/topic.php?slug=<?= htmlspecialchars($topic['slug']) ?>&v=20260409"
            }
        ]
    }
    </script>
    <?php endif; ?>
    <link rel="stylesheet" href="/reader/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/reader/assets/reader.min.css?v=20260409h">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="manifest" href="/reader/manifest.json">
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
        <a href="/" class="reader-logo">BibleBridge</a>
        <nav class="reader-header-nav">
            <a href="/read" class="reader-header-nav-link">Read</a>
            <a href="/plans" class="reader-header-nav-link">Plans</a>
            <a href="/topics" class="reader-header-nav-link active">Topics</a>
        </nav>
    </div>
    <div class="reader-header-center">
        <button class="mobile-search-toggle" id="mobileSearchToggle" aria-label="Open search">
            <svg width="18" height="18" viewBox="0 0 14 14" fill="none"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.5"/><path d="M10 10l2.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
        <button class="mobile-search-close" id="mobileSearchClose" aria-label="Close search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
        <form class="reader-search-form" action="/read/search" method="get">
            <input class="reader-search-input" type="search" name="q" placeholder="Search scripture…" autocomplete="off" aria-label="Search">
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

<?php if ($topic): ?>
<main class="te-main">

    <!-- 1. HEADER -->
    <div class="te-header">
        <div class="te-breadcrumb"><a href="/topics">Topics</a> / <?= htmlspecialchars($topicName) ?></div>
        <h1 class="te-title"><?= htmlspecialchars($topicName) ?></h1>
        <?php if (($bbInstall['mode'] ?? 'api') === 'local'): ?>
        <p class="te-map-link">
            <a href="/topics/map?focus=<?= htmlspecialchars(urlencode($slug)) ?>">See <?= htmlspecialchars($topicName) ?> on the topic map &rarr;</a>
        </p>
        <?php endif; ?>
    </div>

    <!-- 2. SHARED-VERSE ADJACENCY -->
    <?php if (!empty($adjacency)): ?>
    <section class="te-section te-adjacency">
        <h2 class="te-section-title">Topics that share scripture</h2>
        <p class="te-adjacency-intro">Other topics whose anchor verses also appear under <?= htmlspecialchars($topicName) ?>.</p>
        <ul class="te-adjacency-list">
            <?php foreach ($adjacency as $adj): ?>
            <li class="te-adjacency-item">
                <a href="/topics/<?= htmlspecialchars($adj['slug']) ?>" class="te-adjacency-link"><?= htmlspecialchars($adj['name']) ?></a>
                <span class="te-adjacency-count"><?= (int)$adj['shared'] ?> shared <?= $adj['shared'] === 1 ? 'verse' : 'verses' ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- 3. ANCHOR SCRIPTURES -->
    <?php if ($totalAnchors > 0): ?>
    <section class="te-section">
        <h2 class="te-section-title">Anchor Scriptures <span class="te-anchor-count"><?= $totalAnchors ?> verses</span>
            <button type="button" class="te-copy-all"
                    data-bb-copy-anchors
                    data-topic-name="<?= htmlspecialchars($topicName) ?>"
                    data-source-url="<?= htmlspecialchars($canonicalUrl) ?>">Copy all verses</button>
        </h2>
        <?php if (!empty($otAnchors)): ?>
        <div class="te-testament-label">Old Testament</div>
        <div class="te-anchors">
            <?php foreach ($otAnchors as $idx => $av): ?>
            <div class="te-anchor-item<?= $idx >= 8 ? ' te-anchor-hidden' : '' ?>">
                <a href="/read/<?= $av['slug'] ?>/<?= $av['chapter'] ?>/<?= $av['verse'] ?>"
                   class="te-anchor-ref"><?= htmlspecialchars($av['ref']) ?></a>
                <?php if ($av['text']): ?>
                <p class="te-anchor-text">&ldquo;<?= htmlspecialchars($av['text']) ?>&rdquo;</p>
                <?php endif; ?>
                <button type="button" class="te-anchor-expand" data-bb-context-ref="<?= htmlspecialchars($av['ref']) ?>">Expand context</button>
                <div class="te-anchor-utils">
                    <a href="/read/<?= $av['slug'] ?>/<?= $av['chapter'] ?>/<?= $av['verse'] ?>" class="te-anchor-context">Read in context</a>
                    <button type="button" class="te-anchor-xref" data-bb-xref-ref="<?= htmlspecialchars($av['ref']) ?>">Cross-references</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($ntAnchors)): ?>
        <div class="te-testament-label">New Testament</div>
        <div class="te-anchors">
            <?php foreach ($ntAnchors as $idx => $av):
                $globalIdx = count($otAnchors) + $idx;
            ?>
            <div class="te-anchor-item<?= $globalIdx >= 8 ? ' te-anchor-hidden' : '' ?>">
                <a href="/read/<?= $av['slug'] ?>/<?= $av['chapter'] ?>/<?= $av['verse'] ?>"
                   class="te-anchor-ref"><?= htmlspecialchars($av['ref']) ?></a>
                <?php if ($av['text']): ?>
                <p class="te-anchor-text">&ldquo;<?= htmlspecialchars($av['text']) ?>&rdquo;</p>
                <?php endif; ?>
                <button type="button" class="te-anchor-expand" data-bb-context-ref="<?= htmlspecialchars($av['ref']) ?>">Expand context</button>
                <div class="te-anchor-utils">
                    <a href="/read/<?= $av['slug'] ?>/<?= $av['chapter'] ?>/<?= $av['verse'] ?>" class="te-anchor-context">Read in context</a>
                    <button type="button" class="te-anchor-xref" data-bb-xref-ref="<?= htmlspecialchars($av['ref']) ?>">Cross-references</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($totalAnchors > 8): ?>
        <button class="te-show-all-btn" id="showAllAnchors" onclick="document.querySelectorAll('.te-anchor-hidden').forEach(function(e){e.classList.remove('te-anchor-hidden')});this.style.display='none'">
            Show all <?= $totalAnchors ?> verses
        </button>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- 3. BROWSE ENTRY POINTS -->
    <section class="te-section te-section--entry">
        <a href="/read/search?q=<?= urlencode($topicName) ?>" class="te-read-link">
            View all verses about <?= htmlspecialchars($topicName) ?> &rarr;
        </a>
        <a href="/topics" class="te-read-link te-read-link--muted">Browse all topics &rarr;</a>
    </section>

</main>

<?php else: ?>
<main class="te-browse-main">
    <div class="te-browse-hero">
        <h1 class="te-browse-title">Scripture by Topic</h1>
        <p class="te-browse-sub">Use this shelf for sermon prep, doctrine classes, or finding verses for life themes. Each topic gathers anchor scriptures pastors and study tools have connected across church history.</p>
    </div>

<!-- Topics — Tier A / Tier B intentional shelves (Phase 1a Task 7.5c) -->
    <?php if (!empty($tierATopics)): ?>
    <section class="te-tier">
        <h2 class="te-tier-label"><?= htmlspecialchars($tierALabel) ?></h2>
        <?php if ($tierAIntro): ?><p class="te-tier-intro"><?= htmlspecialchars($tierAIntro) ?></p><?php endif; ?>
        <div class="te-cluster-grid">
            <?php foreach ($tierATopics as $t): ?>
            <a href="/topics/<?= htmlspecialchars($t['slug']) ?>" class="te-cluster-card">
                <div class="te-cluster-card-body">
                    <div class="te-cluster-title"><?= htmlspecialchars($t['name']) ?></div>
                    <?php if (!empty($t['top_anchors'])): ?>
                    <div class="te-cluster-anchors"><?= htmlspecialchars(implode(' · ', $t['top_anchors'])) ?></div>
                    <?php endif; ?>
                </div>
                <div class="te-cluster-footer">
                    <span class="te-cluster-count"><?= (int)$t['anchor_count'] ?> verses</span>
                    <span class="te-cluster-arrow">&rarr;</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($tierBTopics)): ?>
    <section class="te-tier">
        <h2 class="te-tier-label"><?= htmlspecialchars($tierBLabel) ?></h2>
        <?php if ($tierBIntro): ?><p class="te-tier-intro"><?= htmlspecialchars($tierBIntro) ?></p><?php endif; ?>
        <div class="te-cluster-grid">
            <?php foreach ($tierBTopics as $t): ?>
            <a href="/topics/<?= htmlspecialchars($t['slug']) ?>" class="te-cluster-card">
                <div class="te-cluster-card-body">
                    <div class="te-cluster-title"><?= htmlspecialchars($t['name']) ?></div>
                    <?php if (!empty($t['top_anchors'])): ?>
                    <div class="te-cluster-anchors"><?= htmlspecialchars(implode(' · ', $t['top_anchors'])) ?></div>
                    <?php endif; ?>
                </div>
                <div class="te-cluster-footer">
                    <span class="te-cluster-count"><?= (int)$t['anchor_count'] ?> verses</span>
                    <span class="te-cluster-arrow">&rarr;</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <p class="te-browse-footer-search">
        Don't see what you need?
        <a href="/read/search">Search scripture directly &rarr;</a>
    </p>
</main>
<?php endif; ?>

<?php $bottomNavActive = 'topics'; include __DIR__ . '/bottom-nav.php'; ?>
<script src="/reader/assets/reader.min.js?v=20260409h"></script>
<?php if (!empty($topic)): ?>
<script>
    window.BB_XREF_WALKER_CONFIG = {
        endpoint: '/reader/xref.php?',
        version: 'kjv',
        baseUrl: ''
    };
</script>
<script src="/reader/assets/xref-walker.js?v=20260409h"></script>
<script src="/reader/assets/topic-tools.js?v=20260409h"></script>
<script>
    window.BB_CONTEXT_CONFIG = {
        endpoint: '/reader/context-proxy.php?',
        version: 'kjv',
        window: 2
    };
</script>
<script src="/reader/assets/topic-context.js?v=20260409i"></script>
<?php endif; ?>
</body>
</html>
