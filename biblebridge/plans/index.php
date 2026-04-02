<?php
require_once dirname(__DIR__) . '/config.php';

$pageTitle    = 'Reading Plans — ' . htmlspecialchars($siteName);
$canonicalUrl = $siteUrl . $bbBaseUrl . '/plans';

$plans = [
    [
        'id'          => 'bible-in-a-year',
        'name'        => 'Bible in a Year',
        'days'        => 365,
        'description' => 'Read through the entire Bible in one year. Each day pairs an Old Testament passage with a New Testament passage — the rhythm most churches follow.',
        'icon'        => 'book-open',
        'tag'         => 'Most Popular',
    ],
    [
        'id'          => 'nt-in-a-year',
        'name'        => 'New Testament in a Year',
        'days'        => 365,
        'description' => 'One chapter per day through the entire New Testament, then return to the Gospels and Romans. A gentle pace perfect for building a daily habit.',
        'icon'        => 'scroll',
        'tag'         => 'Great for Starters',
    ],
    [
        'id'          => 'nt-in-90-days',
        'name'        => 'New Testament in 90 Days',
        'days'        => 90,
        'description' => 'An intensive 90-day immersion in the New Testament. About 3 chapters per day — ideal for a focused season of study or Lent.',
        'icon'        => 'flame',
        'tag'         => 'Intensive',
    ],
    [
        'id'          => 'psalms-and-proverbs',
        'name'        => 'Psalms & Proverbs',
        'days'        => 31,
        'description' => '5 Psalms and 1 chapter of Proverbs every day. Completes in a month — repeat it every month of the year for deep wisdom and worship.',
        'icon'        => 'music',
        'tag'         => 'Monthly Devotional',
    ],
    [
        'id'          => 'gospel-of-john',
        'name'        => 'Gospel of John',
        'days'        => 21,
        'description' => 'One chapter per day through the Gospel of John. The best introduction to Jesus for new readers — and a rewarding revisit for anyone.',
        'icon'        => 'cross',
        'tag'         => 'New Readers',
    ],
];

$icons = [
    'book-open' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
    'scroll'    => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>',
    'flame'     => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0011 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 3z"/></svg>',
    'music'     => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
    'cross'     => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="22"/><line x1="6" y1="7" x2="18" y2="7"/></svg>',
];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="Free Bible reading plans — Bible in a Year, New Testament, Psalms & Proverbs, and more. Track your progress, miss a day and pick right back up.">
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
        .plans-main { max-width: 760px; margin: 0 auto; padding: calc(var(--header-height) + 2.5rem) 1.5rem 5rem; }
        .plans-hero { margin-bottom: 2.5rem; }
        .plans-hero h1 { font-family: 'Lora', Georgia, serif; font-size: 2rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.5rem; }
        .plans-hero p { font-family: 'Inter', sans-serif; font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6; }
        .plans-grid { display: flex; flex-direction: column; gap: 1rem; }
        .plan-card { background: var(--bg-card); border: 1px solid var(--border-light); border-radius: 8px; padding: 1.4rem 1.5rem; display: grid; grid-template-columns: 48px 1fr auto; gap: 0 1.2rem; align-items: center; text-decoration: none; color: inherit; transition: border-color 0.15s, box-shadow 0.15s; }
        .plan-card:hover { border-color: var(--accent); box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .plan-card-icon { width: 48px; height: 48px; border-radius: 10px; background: var(--bg-subtle); display: flex; align-items: center; justify-content: center; color: var(--accent); flex-shrink: 0; }
        .plan-card-body { min-width: 0; }
        .plan-card-top { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.25rem; }
        .plan-card-name { font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: 600; color: var(--text-primary); }
        .plan-card-tag { font-family: 'Inter', sans-serif; font-size: 0.65rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--accent); background: var(--bg-subtle); padding: 0.15em 0.55em; border-radius: 3px; white-space: nowrap; }
        .plan-card-desc { font-family: 'Inter', sans-serif; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.55; }
        .plan-card-meta { font-family: 'Inter', sans-serif; font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; text-align: right; padding-left: 1rem; }
        .plan-card-days { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); display: block; }
        .plan-card-progress { margin-top: 0.7rem; display: none; }
        .plan-card-progress-bar { height: 3px; background: var(--bg-subtle); border-radius: 2px; overflow: hidden; }
        .plan-card-progress-fill { height: 100%; background: var(--accent); border-radius: 2px; transition: width 0.3s; }
        .plan-card-progress-label { font-family: 'Inter', sans-serif; font-size: 0.72rem; color: var(--text-muted); margin-top: 0.3rem; }
        @media (max-width: 540px) {
            .plan-card { grid-template-columns: 40px 1fr; grid-template-rows: auto auto; }
            .plan-card-icon { width: 40px; height: 40px; grid-row: 1; }
            .plan-card-body { grid-column: 2; grid-row: 1; }
            .plan-card-meta { grid-column: 1 / -1; text-align: left; padding-left: 0; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
            .plan-card-days { font-size: 0.9rem; display: inline; }
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
            <svg class="theme-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>
    </div>
</header>

<main class="plans-main">
    <div class="plans-hero">
        <h1>Reading Plans</h1>
        <p>Pick a plan and read at your own pace. Miss a day? Just come back — every day stays open.</p>
    </div>

    <div class="plans-grid" id="plansGrid">
        <?php foreach ($plans as $plan): ?>
        <a class="plan-card" href="<?= $bbBaseUrl ?>/plans/<?= $plan['id'] ?>" data-plan-id="<?= $plan['id'] ?>">
            <div class="plan-card-icon">
                <?= $icons[$plan['icon']] ?>
            </div>
            <div class="plan-card-body">
                <div class="plan-card-top">
                    <span class="plan-card-name"><?= htmlspecialchars($plan['name']) ?></span>
                    <span class="plan-card-tag"><?= htmlspecialchars($plan['tag']) ?></span>
                </div>
                <div class="plan-card-desc"><?= htmlspecialchars($plan['description']) ?></div>
                <div class="plan-card-progress" id="progress-<?= $plan['id'] ?>">
                    <div class="plan-card-progress-bar">
                        <div class="plan-card-progress-fill" style="width:0%"></div>
                    </div>
                    <div class="plan-card-progress-label"></div>
                </div>
            </div>
            <div class="plan-card-meta">
                <span class="plan-card-days"><?= $plan['days'] ?></span>
                days
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</main>

<?php $bottomNavActive = 'plans'; include dirname(__DIR__) . '/bottom-nav.php'; ?>
<script>
(function () {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var html = document.documentElement;
        var isDark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', isDark ? 'light' : 'dark');
        localStorage.setItem('bb_theme', isDark ? 'light' : 'dark');
    });
})();
(function () {
    var planDays = <?= json_encode(array_column($plans, 'days', 'id')) ?>;
    Object.keys(planDays).forEach(function (id) {
        var key = 'bb_plan_' + id;
        var stored = localStorage.getItem(key);
        if (!stored) return;
        try {
            var data = JSON.parse(stored);
            var completed = (data.completed || []).length;
            var total = planDays[id];
            var pct = Math.round(completed / total * 100);
            var wrap = document.getElementById('progress-' + id);
            if (!wrap) return;
            wrap.style.display = 'block';
            wrap.querySelector('.plan-card-progress-fill').style.width = pct + '%';
            wrap.querySelector('.plan-card-progress-label').textContent =
                completed + ' of ' + total + ' days complete (' + pct + '%)';
        } catch (e) {}
    });
})();
</script>
</body>
</html>
