import { defineConfig, devices } from '@playwright/test';

/**
 * Browser matrix.
 *
 * Chromium is the default because it is what CI installs and what the journey
 * suite was written against. `E2E_BROWSERS=chromium,msedge` adds Microsoft Edge
 * — the same engine as Chrome but a different build, PDF pipeline and default
 * paper handling, which is precisely why RULE 11 requires printing to be tested
 * on both rather than assumed on one. Edge is a *channel* of Chromium, so it is
 * addressed as such instead of as a separate browserName (otherwise Playwright
 * looks for Firefox's binary).
 */
function projects() {
    const requested = (process.env.E2E_BROWSERS ?? 'chromium')
        .split(',')
        .map((name) => name.trim().toLowerCase())
        .filter(Boolean);

    return (requested.length > 0 ? requested : ['chromium']).map((name) => ({
        name,
        use:
            name === 'msedge'
                ? { ...devices['Desktop Chrome'], browserName: 'chromium' as const, channel: 'msedge' }
                : { browserName: 'chromium' as const },
    }));
}

export default defineConfig({
    testDir: './tests',
    testMatch: '**/*.spec.js',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['line'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: projects(),
});
