// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './specs',
    timeout: 30000,
    retries: 1,
    use: {
        baseURL: 'https://holybible.dev/test-reader',
        httpCredentials: { username: 'bbtest', password: 'TestReader2026!' },
        headless: true,
        ignoreHTTPSErrors: true,
    },
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'report' }]],
});
