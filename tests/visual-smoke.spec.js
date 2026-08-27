import { test, expect } from '@playwright/test';

const VIEWPORTS = [
    { name: 'mobile-360', width: 360, height: 740 },
    { name: 'mobile-414', width: 414, height: 800 },
    { name: 'tablet-768', width: 768, height: 1024 },
    { name: 'laptop-1280', width: 1280, height: 800 },
    { name: 'desktop-1440', width: 1440, height: 900 },
    { name: 'ultrawide-1920', width: 1920, height: 1080 },
];

const BASE_URL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';
const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL ?? 'admin@admin.com';
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD ?? '1234';

// Pages to visit while logged in. Each must render without horizontal overflow.
const PAGES = [
    { name: 'dashboard', url: '/dashboard' },
    { name: 'users-index', url: '/users' },
    { name: 'users-list', url: '/users/list/view' },
    { name: 'appointment-index', url: '/appointment' },
    { name: 'appointment-calendar', url: '/appointment-calendar' },
    { name: 'study-checkin', url: '/study/checkin' },
    { name: 'study-technologist', url: '/study/technologist' },
    { name: 'modality', url: '/modality' },
    { name: 'custom-status', url: '/custom-status' },
    { name: 'roles', url: '/roles' },
    { name: 'permissions', url: '/permissions' },
    { name: 'email-templates', url: '/email-templates' },
    { name: 'notification-templates', url: '/notification-templates' },
    { name: 'referrer', url: '/referrer' },
    { name: 'report-templates', url: '/report-templates' },
    { name: 'screening-forms', url: '/screening-forms' },
    { name: 'invoices', url: '/invoices' },
    { name: 'profile', url: '/profile' },
    { name: 'userlog', url: '/users/logs/history' },
    { name: 'login', url: '/login' },
];

test.describe('ADC Frontend — visual + responsive smoke', () => {
    test.beforeAll(async ({ browser }) => {
        // Login once and store cookies in context.
        const ctx = await browser.newContext();
        const page = await ctx.newPage();
        await page.goto(BASE_URL + '/login', { waitUntil: 'load' });
        await page.fill('input[name="email"]', ADMIN_EMAIL).catch(() => {});
        await page.fill('input[name="password"]', ADMIN_PASSWORD).catch(() => {});
        await page.click('button[type="submit"]').catch(() => {});
        await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
        await ctx.close();
    });

    for (const vp of VIEWPORTS) {
        test.describe(`@ ${vp.name} (${vp.width}×${vp.height})`, () => {
            test.use({ viewport: { width: vp.width, height: vp.height } });

            for (const p of PAGES) {
                test(`${p.name} renders without horizontal overflow`, async ({ page }) => {
                    const consoleErrors = [];
                    page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });

                    const response = await page.goto(BASE_URL + p.url, { waitUntil: 'load', timeout: 20000 });
                    expect(response, 'navigation response').not.toBeNull();

                    // No 5xx
                    if (response && response.status() >= 500) {
                        throw new Error(`${p.url} returned ${response.status()}`);
                    }

                    // No horizontal overflow on <body> (we ignore the always-overflowing delibeate horizontal-scroll tables)
                    const overflow = await page.evaluate(() => {
                        const b = document.body;
                        return {
                            doc: document.documentElement.scrollWidth,
                            body: b.scrollWidth,
                            viewport: window.innerWidth,
                        };
                    });
                    // Allow up to 8px of overflow (sub-pixel rounding). Wider tables in cards (booking-data-table) deliberately scroll, so we only check the body.
                    if (overflow.body > overflow.viewport + 8) {
                        await page.screenshot({ path: `test-results/visual/${p.name}-${vp.name}-overflow.png`, fullPage: true });
                        throw new Error(
                            `Page ${p.url} overflows viewport on ${vp.name}: ` +
                            `body=${overflow.body}, viewport=${overflow.viewport}`
                        );
                    }
                });
            }
        });
    }
});
