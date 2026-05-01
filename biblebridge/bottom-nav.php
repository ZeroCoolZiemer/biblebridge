<?php
/**
 * Mobile bottom navigation bar — shown below 640px.
 * Include this before </body> on all reader/plans pages.
 * Set $bottomNavActive to 'read', 'search', 'plans', or 'topics' before including.
 */
$bottomNavActive = $bottomNavActive ?? '';
?>
<nav class="mobile-bottom-nav" aria-label="Mobile navigation">
    <div class="mobile-bottom-nav-inner">
        <a href="<?= $bbBaseUrl ?>/read" class="mobile-bottom-nav-link<?= $bottomNavActive === 'read' ? ' active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            Read
        </a>
        <a href="<?= $bbBaseUrl ?>/read/search" class="mobile-bottom-nav-link<?= $bottomNavActive === 'search' ? ' active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            Search
        </a>
        <a href="<?= $bbBaseUrl ?>/plans" class="mobile-bottom-nav-link<?= $bottomNavActive === 'plans' ? ' active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Plans
        </a>
        <a href="<?= $bbBaseUrl ?>/topics" class="mobile-bottom-nav-link<?= $bottomNavActive === 'topics' ? ' active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/></svg>
            Topics
        </a>
    </div>
</nav>
<?php
require_once __DIR__ . '/lib/plan-cache.php';
$_bbTier = bb_get_cached_tier();
if ($_bbTier === 'free'):
?>
<div class="bb-powered-by">
    Powered by <a href="https://holybible.dev" target="_blank" rel="noopener">BibleBridge</a>
</div>
<style>
.bb-powered-by {
    text-align: center; padding: 1rem;
    font-family: 'Inter', sans-serif; font-size: 0.72rem;
    color: var(--text-muted); letter-spacing: 0.02em;
}
.bb-powered-by a { color: var(--accent); text-decoration: none; font-weight: 600; }
.bb-powered-by a:hover { text-decoration: underline; }
</style>
<?php endif; ?>
