<?php
/**
 * Topic Explorer — API-only mode (standalone build)
 * Uses the BibleBridge API instead of direct DB access.
 * Included by topic-explorer.php.
 */

// $slug and config vars already set by topic-explorer.php

$RELATION_LABELS = [
    'leads-to'   => ['out' => 'leads to',     'in' => 'preceded by'],
    'results-in' => ['out' => 'produces',    'in' => 'preceded by'],
    'depends-on' => ['out' => 'depends on',   'in' => 'relied on by'],
    'reverses'   => ['out' => 'reverses',    'in' => 'reversed by'],
    'determines' => ['out' => 'shapes',      'in' => 'shaped by'],
    'related'    => ['out' => 'related to',  'in' => 'related to'],
];

if (!empty($slug)) {
    // =========================================================
    // DETAIL MODE — fetch single topic from API
    // =========================================================
    $apiData = bb_api_topics($slug);

    if (!$apiData || ($apiData['status'] ?? '') !== 'success' || empty($apiData['topic'])) {
        http_response_code(404);
        $pageTitle = 'Topic Not Found — ' . htmlspecialchars($siteName);
        include __DIR__ . '/404.php';
        exit;
    }

    $topic = $apiData['topic'];
    $topicName = $topic['name'];
    $topicType = $topic['type'] ?? 'topic';
    $topicDesc = $topic['description'] ?? '';

    $pageTitle    = htmlspecialchars($topicName) . ' — ' . htmlspecialchars($siteName) . ' Topics';
    $canonicalUrl = $siteUrl . $bbBaseUrl . '/topics/' . htmlspecialchars($slug);

    // Parse related topics
    $related = $apiData['related'] ?? [];
    $outgoing = [];
    $incoming = [];
    foreach ($related as $r) {
        $rt = $r['relation'] ?? 'related';
        $dir = $r['direction'] ?? 'outgoing';
        $entry = ['slug' => $r['slug'], 'name' => $r['name']];
        if ($dir === 'outgoing') {
            $outgoing[$rt][] = $entry;
        } else {
            $incoming[$rt][] = $entry;
        }
    }

    // Flow data from API (computed by TopicFlowBuilder on server)
    $flow = $apiData['flow'] ?? [];
    $beforeChains      = $flow['before_chains'] ?? [];
    $consequenceChains = $flow['consequence_chains'] ?? [];
    $redemptionChains  = $flow['redemption_chains'] ?? [];
    $beforeLabel       = $flow['before_label'] ?? ('Leads into ' . $topicName);
    $hasGroups         = $flow['has_groups'] ?? false;
    $flowIntro         = $flow['flow_sentence'] ?? '';
    $readingPath       = $flow['reading_path'] ?? [];

    // Determine if there are any spine chains at all
    $hasSpineChains = !empty($beforeChains) || !empty($consequenceChains) || !empty($redemptionChains);
    // "Ungrouped" spine chains: when !hasGroups but there are forward chains
    $ungroupedChains = [];
    if (!$hasGroups) {
        $ungroupedChains = array_merge($consequenceChains, $redemptionChains);
    }

    // Anchors from API
    $anchors = $apiData['anchors'] ?? [];
    $otAnchors = [];
    $ntAnchors = [];
    foreach ($anchors as $a) {
        // Parse book from reference to determine OT/NT
        // verse_id format: book*1000000 + chapter*1000 + verse
        $vid = $a['verse_id'] ?? 0;
        $bookId = intdiv($vid, 1000000);
        $ref = $a['reference'] ?? '';
        $text = $a['text'] ?? '';
        // Build slug from reference
        $refParts = explode(' ', $ref);
        $bookName = '';
        $ch = 0;
        $vn = 0;
        if (preg_match('/^(.+?)\s+(\d+):(\d+)$/', $ref, $m)) {
            $bookName = $m[1];
            $ch = (int)$m[2];
            $vn = (int)$m[3];
        }
        $bookSlug = strtolower(str_replace(' ', '-', $bookName));

        $entry = [
            'ref'     => $ref,
            'slug'    => $bookSlug,
            'chapter' => $ch,
            'verse'   => $vn,
            'text'    => $text,
        ];
        if ($bookId <= 39) {
            $otAnchors[] = $entry;
        } else {
            $ntAnchors[] = $entry;
        }
    }
    $totalAnchors = count($anchors);

} else {
    // =========================================================
    // BROWSE MODE — fetch all topics from API
    // =========================================================
    $apiData = bb_api_topics();

    $allTopics = [];
    if ($apiData && ($apiData['status'] ?? '') === 'success') {
        $allTopics = $apiData['topics'] ?? [];
    }

    // Build slug→name map for explore chains
    $slugToName = [];
    foreach ($allTopics as $t) $slugToName[$t['slug']] = $t['name'];

    $exploreChains = [
        ['god', 'creation', 'sin', 'repentance', 'salvation'],
        ['faith', 'grace', 'redemption', 'sanctification'],
        ['prayer', 'worship', 'church', 'discipleship'],
    ];

    $topic = null;
    $pageTitle    = 'Theology Topics — ' . htmlspecialchars($siteName);
    $canonicalUrl = $siteUrl . $bbBaseUrl . '/topics';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?php if (!empty($topic)): ?>
    <meta name="description" content="Explore how <?= htmlspecialchars($topic['name']) ?> is structured, expressed, and connected in Scripture.">
    <?php endif; ?>
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
</head>
<body class="reader-index-page">

<header class="reader-header">
    <div class="reader-header-left">
        <a href="<?= $bbBaseUrl ?>/read" class="reader-logo"><?= htmlspecialchars($siteName) ?></a>
        <nav class="reader-header-nav">
            <a href="<?= $bbBaseUrl ?>/read" class="reader-header-nav-link">Read</a>
            <a href="<?= $bbBaseUrl ?>/plans" class="reader-header-nav-link">Plans</a>
            <a href="<?= $bbBaseUrl ?>/topics" class="reader-header-nav-link active">Topics</a>
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
            <input class="reader-search-input" type="search" name="q" placeholder="Search scripture..." autocomplete="off" aria-label="Search">
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
        <div class="te-breadcrumb"><a href="<?= $bbBaseUrl ?>/topics">Topics</a> / <?= htmlspecialchars($topicName) ?></div>
        <h1 class="te-title"><?= htmlspecialchars($topicName) ?></h1>
        <span class="te-type-badge te-type-<?= htmlspecialchars($topicType) ?>"><?= htmlspecialchars($topicType) ?></span>
        <?php if ($topicDesc): ?>
        <p class="te-description"><?= htmlspecialchars($topicDesc) ?></p>
        <?php endif; ?>
        <p class="te-why-block">Not just a list of verses — a map of how ideas in Scripture connect and lead to each other.</p>
    </div>

    <!-- 2. FOLLOW THE FLOW (spine) -->
    <?php if ($hasSpineChains): ?>
    <section class="te-section te-section--flow">
        <h2 class="te-section-title">Follow the flow</h2>
        <?php if ($hasGroups && $flowIntro): ?>
        <p class="te-flow-intro"><?= htmlspecialchars($topicName) ?> connects to different paths in Scripture:</p>
        <p class="te-flow-connecting"><?= htmlspecialchars($flowIntro) ?></p>
        <?php endif; ?>

        <?php if (!empty($beforeChains)): ?>
        <div class="te-flow-group te-flow-group--before">
            <span class="te-flow-group-label"><?= htmlspecialchars($beforeLabel) ?></span>
            <div class="te-spine-list">
            <?php foreach (array_slice($beforeChains, 0, 3) as $chain): ?>
            <div class="te-spine-chain">
                <?php foreach ($chain as $i => $node): ?>
                <?php if ($i > 0): ?><span class="te-spine-arrow">&rarr;</span><?php endif; ?>
                <a href="<?= $bbBaseUrl ?>/topics/<?= htmlspecialchars($node['slug']) ?>"
                   class="te-spine-node<?= !empty($node['is_current']) ? ' te-spine-node--current' : '' ?>">
                    <?= htmlspecialchars($node['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($consequenceChains) && $hasGroups): ?>
        <div class="te-flow-group te-flow-group--consequence">
            <span class="te-flow-group-label">Consequence</span>
            <div class="te-spine-list">
            <?php foreach (array_slice($consequenceChains, 0, 4) as $chain): ?>
            <div class="te-spine-chain">
                <?php foreach ($chain as $i => $node): ?>
                <?php if ($i > 0): ?><span class="te-spine-arrow">&rarr;</span><?php endif; ?>
                <a href="<?= $bbBaseUrl ?>/topics/<?= htmlspecialchars($node['slug']) ?>"
                   class="te-spine-node<?= !empty($node['is_current']) ? ' te-spine-node--current' : '' ?>">
                    <?= htmlspecialchars($node['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($redemptionChains) && $hasGroups): ?>
        <div class="te-flow-group te-flow-group--redemption">
            <span class="te-flow-group-label">Redemption</span>
            <div class="te-spine-list">
            <?php foreach (array_slice($redemptionChains, 0, 4) as $chain): ?>
            <div class="te-spine-chain">
                <?php foreach ($chain as $i => $node): ?>
                <?php if ($i > 0): ?><span class="te-spine-arrow">&rarr;</span><?php endif; ?>
                <a href="<?= $bbBaseUrl ?>/topics/<?= htmlspecialchars($node['slug']) ?>"
                   class="te-spine-node<?= !empty($node['is_current']) ? ' te-spine-node--current' : '' ?>">
                    <?= htmlspecialchars($node['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$hasGroups && !empty($ungroupedChains)): ?>
        <div class="te-spine-list">
        <?php foreach (array_slice($ungroupedChains, 0, 5) as $chain): ?>
        <div class="te-spine-chain">
            <?php foreach ($chain as $i => $node): ?>
            <?php if ($i > 0): ?><span class="te-spine-arrow">&rarr;</span><?php endif; ?>
            <a href="<?= $bbBaseUrl ?>/topics/<?= htmlspecialchars($node['slug']) ?>"
               class="te-spine-node<?= !empty($node['is_current']) ? ' te-spine-node--current' : '' ?>">
                <?= htmlspecialchars($node['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- 2b. READING PATH -->
    <?php if (!empty($readingPath)): ?>
    <section class="te-section te-section--reading-path">
        <h2 class="te-section-title">Reading Path</h2>
        <p class="te-rp-intro">Follow this theme through Scripture — one verse per step, in theological order.</p>
        <div class="te-rp-steps">
            <?php foreach ($readingPath as $i => $step): ?>
            <div class="te-rp-step">
                <div class="te-rp-step-marker">
                    <span class="te-rp-step-num"><?= $i + 1 ?></span>
                    <?php if ($i < count($readingPath) - 1): ?><span class="te-rp-step-line"></span><?php endif; ?>
                </div>
                <div class="te-rp-step-body">
                    <a href="<?= $bbBaseUrl ?>/topics/<?= htmlspecialchars($step['slug']) ?>" class="te-rp-topic-name"><?= htmlspecialchars($step['name']) ?></a>
                    <?php if (!empty($step['text'])): ?>
                    <?php
                        $stepBookSlug = $step['book_slug'] ?? strtolower(str_replace(' ', '-', explode(' ', $step['ref'] ?? '')[0] ?? ''));
                        $stepCh = $step['chapter'] ?? 0;
                        $stepVn = $step['verse'] ?? 0;
                    ?>
                    <blockquote class="te-rp-verse-text">&ldquo;<?= htmlspecialchars($step['text']) ?>&rdquo;</blockquote>
                    <a href="<?= $bbBaseUrl ?>/read/<?= $stepBookSlug ?>/<?= $stepCh ?>/<?= $stepVn ?>" class="te-rp-verse-ref"><?= htmlspecialchars($step['ref'] ?? '') ?> &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 3. RELATIONSHIPS -->
    <?php if (!empty($outgoing) || !empty($incoming)): ?>
    <section class="te-section">
        <h2 class="te-section-title">Relationships</h2>
        <div class="te-rel-table">
            <?php foreach ($outgoing as $rt => $nodes): ?>
            <div class="te-rel-row">
                <span class="te-rel-dir te-rel-out">&rarr;</span>
                <span class="te-rel-label"><?= htmlspecialchars($RELATION_LABELS[$rt]['out'] ?? $rt) ?></span>
                <span class="te-rel-nodes">
                    <?php foreach ($nodes as $n): ?>
                    <a href="<?= $bbBaseUrl ?>/topics/<?= htmlspecialchars($n['slug']) ?>" class="te-rel-node"><?= htmlspecialchars($n['name']) ?></a>
                    <?php endforeach; ?>
                </span>
            </div>
            <?php endforeach; ?>

            <?php foreach ($incoming as $rt => $nodes): ?>
            <div class="te-rel-row">
                <span class="te-rel-dir te-rel-in">&larr;</span>
                <span class="te-rel-label"><?= htmlspecialchars($RELATION_LABELS[$rt]['in'] ?? $rt) ?></span>
                <span class="te-rel-nodes">
                    <?php foreach ($nodes as $n): ?>
                    <a href="<?= $bbBaseUrl ?>/topics/<?= htmlspecialchars($n['slug']) ?>" class="te-rel-node"><?= htmlspecialchars($n['name']) ?></a>
                    <?php endforeach; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 4. ANCHOR SCRIPTURES -->
    <?php if ($totalAnchors > 0): ?>
    <section class="te-section">
        <h2 class="te-section-title">Anchor Scriptures <span class="te-anchor-count"><?= $totalAnchors ?> verses</span></h2>
        <?php if (!empty($otAnchors)): ?>
        <div class="te-testament-label">Old Testament</div>
        <div class="te-anchors">
            <?php foreach ($otAnchors as $idx => $av): ?>
            <div class="te-anchor-item<?= $idx >= 8 ? ' te-anchor-hidden' : '' ?>">
                <a href="<?= $bbBaseUrl ?>/read/<?= $av['slug'] ?>/<?= $av['chapter'] ?>/<?= $av['verse'] ?>"
                   class="te-anchor-ref"><?= htmlspecialchars($av['ref']) ?></a>
                <?php if ($av['text']): ?>
                <p class="te-anchor-text">&ldquo;<?= htmlspecialchars($av['text']) ?>&rdquo;</p>
                <?php endif; ?>
                <a href="<?= $bbBaseUrl ?>/read/<?= $av['slug'] ?>/<?= $av['chapter'] ?>/<?= $av['verse'] ?>" class="te-anchor-context">Read in context &rarr;</a>
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
                <a href="<?= $bbBaseUrl ?>/read/<?= $av['slug'] ?>/<?= $av['chapter'] ?>/<?= $av['verse'] ?>"
                   class="te-anchor-ref"><?= htmlspecialchars($av['ref']) ?></a>
                <?php if ($av['text']): ?>
                <p class="te-anchor-text">&ldquo;<?= htmlspecialchars($av['text']) ?>&rdquo;</p>
                <?php endif; ?>
                <a href="<?= $bbBaseUrl ?>/read/<?= $av['slug'] ?>/<?= $av['chapter'] ?>/<?= $av['verse'] ?>" class="te-anchor-context">Read in context &rarr;</a>
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

    <!-- 5. BROWSE ENTRY POINTS -->
    <section class="te-section te-section--entry">
        <a href="<?= $bbBaseUrl ?>/read/search?q=<?= urlencode($topicName) ?>" class="te-read-link">
            View all verses about <?= htmlspecialchars($topicName) ?> &rarr;
        </a>
        <a href="<?= $bbBaseUrl ?>/topics" class="te-read-link te-read-link--muted">Browse all topics &rarr;</a>
    </section>

</main>

<?php else: ?>
<main class="te-browse-main">
    <div class="te-browse-hero">
        <h1 class="te-browse-title">Theology Topics</h1>
        <p class="te-browse-sub">Follow how ideas in Scripture connect — not just what they mean, but where they lead.</p>
    </div>

    <!-- Start Exploring — curated entry chains -->
    <?php if (!empty($exploreChains)): ?>
    <section class="te-browse-group te-browse-group--explore">
        <div class="te-browse-type">Start Exploring</div>
        <div class="te-cluster-grid">
            <?php foreach ($exploreChains as $chain):
                $chainNames = [];
                $firstSlug = $chain[0];
                foreach ($chain as $s) {
                    if (isset($slugToName[$s])) $chainNames[] = $slugToName[$s];
                }
                if (empty($chainNames)) continue;
            ?>
            <a href="<?= $bbBaseUrl ?>/topics/<?= htmlspecialchars($firstSlug) ?>" class="te-cluster-card">
                <div class="te-cluster-card-body">
                    <div class="te-cluster-title"><?= implode(' &rarr; ', array_map('htmlspecialchars', $chainNames)) ?></div>
                    <div class="te-cluster-desc">Explore &rarr;</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Topics grouped by type -->
    <?php if (!empty($allTopics)):
        $grouped = [];
        foreach ($allTopics as $t) {
            $type = $t['type'] ?: 'other';
            $grouped[$type][] = $t;
        }
        $typeOrder = ['doctrine', 'virtue', 'practice', 'experience'];
        $typeLabels = [
            'doctrine'   => 'Doctrines',
            'virtue'     => 'Virtues',
            'practice'   => 'Practices',
            'experience' => 'Experiences',
        ];
    ?>
        <?php foreach ($typeOrder as $type):
            if (empty($grouped[$type])) continue;
            $topics = $grouped[$type];
        ?>
        <section class="te-browse-group">
            <div class="te-browse-type"><?= $typeLabels[$type] ?? ucfirst($type) ?></div>
            <div class="te-cluster-grid">
                <?php foreach ($topics as $t):
                    $edge = $t['primary_edge'] ?? null;
                ?>
                <a href="<?= $bbBaseUrl ?>/topics/<?= htmlspecialchars($t['slug']) ?>" class="te-cluster-card">
                    <div class="te-cluster-card-body">
                        <div class="te-cluster-title"><?= htmlspecialchars($t['name']) ?></div>
                        <?php if (!empty($t['description'])): ?>
                        <div class="te-cluster-desc"><?= htmlspecialchars(mb_strimwidth($t['description'], 0, 100, '…')) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="te-cluster-footer">
                        <?php if ($edge): ?>
                        <span class="te-cluster-direction"><?= htmlspecialchars($edge['label']) ?> &rarr; <?= htmlspecialchars($edge['name']) ?></span>
                        <?php else: ?>
                        <span class="te-cluster-count"><?= (int)($t['anchor_count'] ?? 0) ?> verses</span>
                        <?php endif; ?>
                        <span class="te-cluster-arrow">&rarr;</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    <?php else: ?>
    <p style="text-align:center; color:var(--text-muted); padding:2rem;">Could not load topics. Please try again later.</p>
    <?php endif; ?>
</main>
<?php endif; ?>

<?php $bottomNavActive = 'topics'; include __DIR__ . '/bottom-nav.php'; ?>
<script src="<?= $bbBaseUrl ?>/assets/reader.min.js?v=20260401"></script>
</body>
</html>
