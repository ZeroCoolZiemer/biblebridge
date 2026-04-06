<?php
require_once dirname(__DIR__) . '/config.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', $_GET['slug'] ?? '');
$dataFile = __DIR__ . '/data/' . $slug . '.json';

if (!$slug || !file_exists($dataFile)) {
    header('Location: ' . $bbBaseUrl . '/plans');
    exit;
}

$plan = json_decode(file_get_contents($dataFile), true);
$totalDays = $plan['days'];

$pageTitle    = $plan['name'] . ' — Reading Plans';
$canonicalUrl = $siteUrl . $bbBaseUrl . '/plans/' . $slug;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($plan['description']) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
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
        .plan-page-main { max-width: 860px; margin: 0 auto; padding: calc(var(--header-height) + 2rem) 1.5rem 5rem; }
        .plan-breadcrumb { font-family: 'Inter', sans-serif; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        .plan-breadcrumb a { color: var(--text-muted); text-decoration: none; }
        .plan-breadcrumb a:hover { color: var(--accent); }
        .plan-breadcrumb span { margin: 0 0.4rem; }
        .plan-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.5rem; margin-bottom: 2rem; }
        .plan-header-right { flex-shrink: 0; }
        .plan-header-left h1 { font-family: 'Lora', Georgia, serif; font-size: 1.75rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.35rem; }
        .plan-header-left p { font-family: 'Inter', sans-serif; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6; max-width: 520px; }
        .plan-progress-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; padding: 1rem 1.25rem; background: var(--bg-card); border: 1px solid var(--border-light); border-radius: 8px; }
        .plan-progress-row.hidden { display: none; }
        .plan-progress-bar-wrap { flex: 1; }
        .plan-progress-bar-bg { height: 6px; background: var(--bg-subtle); border-radius: 3px; overflow: hidden; }
        .plan-progress-bar-fill { height: 100%; background: var(--accent); border-radius: 3px; transition: width 0.4s ease; width: 0%; }
        .plan-progress-text { font-family: 'Inter', sans-serif; font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; }
        .plan-progress-pct { font-family: 'Inter', sans-serif; font-size: 1.1rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; }
        .btn-continue { font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 600; background: var(--accent); color: #fff; border: none; border-radius: 6px; padding: 0.55rem 1.1rem; cursor: pointer; white-space: nowrap; text-decoration: none; display: inline-block; }
        .btn-continue:hover { background: var(--accent-hover); }
        .plan-start-cta { background: var(--bg-card); border: 1px solid var(--border-light); border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .plan-start-cta.hidden { display: none; }
        .plan-start-cta p { font-family: 'Inter', sans-serif; font-size: 0.875rem; color: var(--text-secondary); flex: 1; min-width: 200px; }
        .plan-start-cta p strong { color: var(--text-primary); }
        .plan-start-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .start-date-label { font-family: 'Inter', sans-serif; font-size: 0.78rem; color: var(--text-muted); margin-right: 0.25rem; }
        input[type="date"].start-date-input { font-family: 'Inter', sans-serif; font-size: 0.85rem; background: var(--bg-subtle); border: 1px solid var(--border-light); border-radius: 5px; color: var(--text-primary); padding: 0.45rem 0.7rem; cursor: pointer; }
        input[type="date"].start-date-input:focus { outline: none; border-color: var(--accent); }
        .btn-start { font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 600; background: var(--accent); color: #fff; border: none; border-radius: 6px; padding: 0.55rem 1.2rem; cursor: pointer; }
        .btn-start:hover { background: var(--accent-hover); }
        .btn-reset { font-family: 'Inter', sans-serif; font-size: 0.78rem; color: var(--text-muted); background: none; border: none; cursor: pointer; padding: 0; text-decoration: underline; text-underline-offset: 2px; }
        .btn-reset:hover { color: var(--text-primary); }
        .calendar-section { margin-top: 0.5rem; }
        .calendar-section-title { font-family: 'Inter', sans-serif; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1rem; }
        .calendar-week-header { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 4px; }
        .calendar-week-header span { font-family: 'Inter', sans-serif; font-size: 0.65rem; font-weight: 600; color: var(--text-muted); text-align: center; padding: 0 0 2px; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .cal-day { aspect-ratio: 1; border-radius: 5px; background: var(--bg-subtle); border: 1px solid transparent; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; font-size: 0.72rem; font-weight: 500; color: var(--text-muted); cursor: pointer; text-decoration: none; transition: background 0.12s, border-color 0.12s, color 0.12s; position: relative; user-select: none; }
        .cal-day:hover { border-color: var(--accent); color: var(--accent); }
        .cal-day.completed { background: var(--accent); color: #fff; border-color: transparent; }
        .cal-day.completed:hover { background: var(--accent-hover); border-color: transparent; color: #fff; }
        .cal-day.today { border-color: var(--accent); color: var(--accent); font-weight: 700; background: var(--bg-hover); }
        .cal-day.today.completed { background: var(--accent); color: #fff; }
        .cal-day.not-started-today { border-color: var(--accent); color: var(--accent); font-weight: 700; }
        .cal-day.future { cursor: pointer; opacity: 0.38; }
        .cal-day.future:hover { opacity: 0.65; border-color: var(--border-light); }
        .calendar-legend { display: flex; gap: 1.25rem; margin-top: 1rem; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 0.4rem; font-family: 'Inter', sans-serif; font-size: 0.75rem; color: var(--text-muted); }
        .legend-dot { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
        .legend-dot.completed { background: var(--accent); }
        .legend-dot.today { background: var(--bg-hover); border: 2px solid var(--accent); }
        .legend-dot.missed { background: var(--bg-subtle); }
        .legend-dot.future { background: var(--bg-subtle); opacity: 0.4; }
        @media (max-width: 540px) {
            .plan-header { flex-direction: column; }
            .plan-progress-row { flex-wrap: wrap; }
            .cal-day { font-size: 0.62rem; border-radius: 4px; }
            .calendar-grid { gap: 3px; }
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

<main class="plan-page-main">
    <div class="plan-breadcrumb">
        <a href="<?= $bbBaseUrl ?>/plans">Reading Plans</a>
        <span>&#x203A;</span>
        <?= htmlspecialchars($plan['name']) ?>
    </div>

    <div class="plan-header">
        <div class="plan-header-left">
            <h1><?= htmlspecialchars($plan['name']) ?></h1>
            <p><?= htmlspecialchars($plan['description']) ?></p>
        </div>
    </div>

    <div class="plan-progress-row hidden" id="progressRow">
        <div class="plan-progress-bar-wrap">
            <div class="plan-progress-bar-bg">
                <div class="plan-progress-bar-fill" id="progressFill"></div>
            </div>
            <div class="plan-progress-text" id="progressText"></div>
        </div>
        <div class="plan-progress-pct" id="progressPct"></div>
        <a class="btn-continue" id="continueBtn" href="#">Continue Reading</a>
    </div>

    <div class="plan-start-cta" id="startCta">
        <p><strong>Ready to start?</strong> Pick a start date and we'll track your progress — no account needed.</p>
        <div class="plan-start-actions">
            <span class="start-date-label">Start date</span>
            <input type="date" class="start-date-input" id="startDateInput">
            <button class="btn-start" id="btnStart">Start Plan</button>
        </div>
    </div>

    <div class="calendar-section">
        <div class="calendar-section-title"><?= $totalDays ?> days</div>
        <div class="calendar-week-header">
            <span>Su</span><span>Mo</span><span>Tu</span><span>We</span>
            <span>Th</span><span>Fr</span><span>Sa</span>
        </div>
        <div class="calendar-grid" id="calendarGrid">
            <?php for ($d = 1; $d <= $totalDays; $d++): ?>
                <a class="cal-day" href="<?= $bbBaseUrl ?>/plans/<?= $slug ?>/day/<?= $d ?>" data-day="<?= $d ?>"><?= $d ?></a>
            <?php endfor; ?>
            <?php
            $remainder = $totalDays % 7;
            if ($remainder > 0):
                for ($p = 0; $p < (7 - $remainder); $p++):
            ?>
                <div class="cal-day" style="visibility:hidden;pointer-events:none;"></div>
            <?php endfor; endif; ?>
        </div>

        <div class="calendar-legend">
            <div class="legend-item"><div class="legend-dot completed"></div> Completed</div>
            <div class="legend-item"><div class="legend-dot today"></div> Today</div>
            <div class="legend-item"><div class="legend-dot missed"></div> Missed</div>
            <div class="legend-item"><div class="legend-dot future"></div> Upcoming</div>
        </div>
    </div>

    <div id="resetWrap" style="margin-top:1.5rem;display:none;">
        <button class="btn-reset" id="btnReset">Reset plan progress</button>
    </div>
</main>

<?php $bottomNavActive = 'plans'; include dirname(__DIR__) . '/bottom-nav.php'; ?>
<script>
(function () {
    var SLUG      = <?= json_encode($slug) ?>;
    var TOTAL     = <?= $totalDays ?>;
    var STORE_KEY = 'bb_plan_' + SLUG;
    var BASE      = <?= json_encode($bbBaseUrl) ?>;

    function loadData() {
        try { return JSON.parse(localStorage.getItem(STORE_KEY)) || null; }
        catch (e) { return null; }
    }
    function saveData(d) { localStorage.setItem(STORE_KEY, JSON.stringify(d)); }
    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }
    function daysBetween(a, b) {
        var da = new Date(a + 'T00:00:00');
        var db = new Date(b + 'T00:00:00');
        return Math.round((db - da) / 86400000);
    }
    function currentDay(startDate) {
        var diff = daysBetween(startDate, todayStr());
        return Math.min(Math.max(diff + 1, 1), TOTAL);
    }

    var themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var html = document.documentElement;
            var isDark = html.getAttribute('data-theme') === 'dark';
            html.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('bb_theme', isDark ? 'light' : 'dark');
        });
    }

    var dateInput = document.getElementById('startDateInput');
    dateInput.value = todayStr();

    document.getElementById('btnStart').addEventListener('click', function () {
        var d = dateInput.value || todayStr();
        saveData({ startDate: d, completed: [] });
        render();
    });

    document.getElementById('btnReset').addEventListener('click', function () {
        if (!confirm('Reset all progress for this plan?')) return;
        localStorage.removeItem(STORE_KEY);
        render();
    });

    function render() {
        var data = loadData();
        var cells = document.querySelectorAll('#calendarGrid .cal-day[data-day]');
        var startCta = document.getElementById('startCta');
        var progressRow = document.getElementById('progressRow');
        var resetWrap = document.getElementById('resetWrap');

        if (!data) {
            startCta.classList.remove('hidden');
            progressRow.classList.add('hidden');
            resetWrap.style.display = 'none';
            cells.forEach(function (cell) { cell.className = 'cal-day'; });
            return;
        }

        startCta.classList.add('hidden');
        progressRow.classList.remove('hidden');
        resetWrap.style.display = 'block';

        var completed = data.completed || [];
        var startDate = data.startDate;
        var todayDay = currentDay(startDate);
        var doneCount = completed.length;
        var pct = Math.round(doneCount / TOTAL * 100);

        document.getElementById('progressFill').style.width = pct + '%';
        document.getElementById('progressPct').textContent = pct + '%';
        document.getElementById('progressText').textContent = doneCount + ' of ' + TOTAL + ' days complete';

        var nextDay = todayDay;
        for (var i = 1; i <= todayDay; i++) {
            if (completed.indexOf(i) === -1) { nextDay = i; break; }
        }
        document.getElementById('continueBtn').href = BASE + '/plans/' + SLUG + '/day/' + nextDay;

        cells.forEach(function (cell) {
            var day = parseInt(cell.getAttribute('data-day'), 10);
            var done = completed.indexOf(day) !== -1;
            var isToday = day === todayDay;
            var isFuture = day > todayDay;
            cell.className = 'cal-day';
            if (done) cell.classList.add('completed');
            if (isToday) cell.classList.add('today');
            if (isFuture && !done) cell.classList.add('future');
        });
    }

    render();
})();
</script>
</body>
</html>
