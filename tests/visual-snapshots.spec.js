import { test } from '@playwright/test';
import { mkdirSync } from 'fs';

const BASE_URL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';
const OUT = 'test-results/visual';
const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL ?? 'admin@example.com';
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD ?? '1234';

const SCREENSHOTS = [
    { vp: { width: 360, height: 740 }, pages: [
        { name: 'dashboard', url: '/dashboard' },
        { name: 'users-index', url: '/users' },
        { name: 'appointment-index', url: '/appointment' },
    ]},
    { vp: { width: 768, height: 1024 }, pages: [
        { name: 'dashboard', url: '/dashboard' },
        { name: 'appointment-calendar', url: '/appointment-calendar' },
        { name: 'study-technologist', url: '/study/technologist' },
    ]},
    { vp: { width: 1440, height: 900 }, pages: [
        { name: 'dashboard', url: '/dashboard' },
        { name: 'users-index', url: '/users' },
        { name: 'appointment-index', url: '/appointment' },
        { name: 'appointment-calendar', url: '/appointment-calendar' },
        { name: 'study-technologist', url: '/study/technologist' },
        { name: 'invoices', url: '/invoices' },
        { name: 'modality', url: '/modality' },
        { name: 'roles', url: '/roles' },
    ]},
];

mkdirSync(OUT, { recursive: true });

async function login(page) {
    await page.goto(BASE_URL + '/login', { waitUntil: 'load' });
    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle', { timeout: 15000 });
}

for (const group of SCREENSHOTS) {
    test.describe(`screenshots @ ${group.vp.width}x${group.vp.height}`, () => {
        test.use({ viewport: group.vp });
        for (const p of group.pages) {
            test(`snap ${p.name}`, async ({ page }) => {
                await login(page);
                await page.goto(BASE_URL + p.url, { waitUntil: 'networkidle', timeout: 20000 });
                await page.waitForTimeout(800);
                const file = `${OUT}/${p.name}-${group.vp.width}.png`;
                await page.screenshot({ path: file, fullPage: true });
            });
        }
    });
}
