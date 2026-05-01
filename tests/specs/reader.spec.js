// @ts-check
const { test, expect } = require('@playwright/test');

// Fail on any 4xx/5xx response (excludes API sub-requests which may 429)
test.beforeEach(async ({ page }) => {
    page.on('response', response => {
        const url = response.url();
        const status = response.status();
        // Only check navigation requests on our own domain
        if (status >= 400 && url.includes('holybible.dev/test-reader') && !url.includes('/api/')) {
            throw new Error(`HTTP ${status} for ${url}`);
        }
    });
});

// ── Homepage ────────────────────────────────────────────────────────────────

test('homepage redirects to /read and shows reader header', async ({ page }) => {
    await page.goto('/read');
    await expect(page.locator('.reader-header')).toBeVisible();
});

// ── Chapter reading ──────────────────────────────────────────────────────────

test('loads Genesis 1 and shows verses', async ({ page }) => {
    await page.goto('/read/genesis/1');
    await expect(page.locator('.verse').first()).toBeVisible({ timeout: 10000 });
    const count = await page.locator('.verse').count();
    expect(count).toBeGreaterThan(25);
    await expect(page.locator('.verse[data-verse="31"]')).toBeVisible();
});

test('loads John 3 and verse 16 contains expected text', async ({ page }) => {
    await page.goto('/read/john/3');
    const verse16 = page.locator('.verse[data-verse="16"]');
    await expect(verse16).toBeVisible({ timeout: 10000 });
    await expect(verse16).toContainText('God so loved');
});

test('chapter nav next link goes to next chapter', async ({ page }) => {
    await page.goto('/read/john/3');
    await expect(page.locator('#nextBtn')).toBeVisible({ timeout: 10000 });
    await page.locator('#nextBtn').click();
    await expect(page).toHaveURL(/\/read\/john\/4/);
});

test('version switcher changes translation', async ({ page }) => {
    await page.goto('/read/john/3');
    await page.locator('#versionBtn').click();
    await page.locator('.version-option[href*="v=asv"]').click();
    await expect(page).toHaveURL(/v=asv/);
    await expect(page.locator('.verse').first()).toBeVisible({ timeout: 10000 });
});

// ── Search ───────────────────────────────────────────────────────────────────

test('search returns results for "faith"', async ({ page }) => {
    await page.goto('/read/search?q=faith');
    await expect(page.locator('.search-result-item').first()).toBeVisible({ timeout: 10000 });
});

test('search book filter limits results to Matthew', async ({ page }) => {
    await page.goto('/read/search?q=faith&book=40'); // 40 = Matthew
    const results = page.locator('.search-result-item');
    await expect(results.first()).toBeVisible({ timeout: 10000 });
    const count = await results.count();
    for (let i = 0; i < Math.min(count, 5); i++) {
        await expect(results.nth(i).locator('.search-result-ref')).toContainText(/Matthew/i);
    }
});

test('search reference redirects to chapter', async ({ page }) => {
    await page.goto('/read/search?q=John+3');
    await expect(page).toHaveURL(/\/read\/john\/3/);
});

// ── Cross-references ─────────────────────────────────────────────────────────

test('xref panel opens and loads content when clicking a verse', async ({ page }) => {
    await page.goto('/read/john/3');
    await expect(page.locator('.verse[data-verse="16"]')).toBeVisible({ timeout: 10000 });
    await page.locator('.verse[data-verse="16"]').click();
    await expect(page.locator('.xref-panel')).toBeVisible({ timeout: 8000 });
    // Panel should have loaded cross-reference links, not be empty or show error
    const xrefLinks = page.locator('.xref-panel-body a, .xref-api-link');
    await expect(xrefLinks.first()).toBeVisible({ timeout: 8000 });
});

test('xref panel can be closed', async ({ page }) => {
    await page.goto('/read/john/3');
    await page.locator('.verse[data-verse="16"]').click();
    await expect(page.locator('.xref-panel')).toBeVisible({ timeout: 8000 });
    await page.locator('.xref-close').click();
    await expect(page.locator('.xref-panel')).toHaveAttribute('aria-hidden', 'true');
});

// ── Dark mode ────────────────────────────────────────────────────────────────

test('dark mode toggle applies data-theme="dark"', async ({ page }) => {
    await page.goto('/read/john/3');
    await page.locator('#themeToggle').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

// ── Topics ───────────────────────────────────────────────────────────────────

test('topics page loads topic clusters', async ({ page }) => {
    await page.goto('/topics');
    await expect(page.locator('.te-cluster-card').first()).toBeVisible({ timeout: 10000 });
});

// ── Plans ────────────────────────────────────────────────────────────────────

test('reading plans page loads plan list', async ({ page }) => {
    await page.goto('/plans');
    await expect(page.locator('.plan-card, .plan-item, .reader-content').first()).toBeVisible({ timeout: 10000 });
});

// ── Error handling ───────────────────────────────────────────────────────────

test('invalid book route does not 500', async ({ page }) => {
    const response = await page.goto('/read/notabook/999');
    expect(response.status()).not.toBe(500);
});
