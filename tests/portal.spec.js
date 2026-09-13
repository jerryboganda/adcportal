import { test, expect } from '@playwright/test';

/**
 * Critical user journey against the REAL stack: Laravel API + seeded
 * database + compiled SPA, all served from one origin (Laravel).
 * Journey: login → dashboard (server data) → reception desk → tech worklist →
 * billing → notification center → sign out → login gate returns.
 */

const EMAIL = process.env.E2E_EMAIL ?? 'admin@adc-e2e.test';
const PASSWORD = process.env.E2E_PASSWORD ?? 'E2eDemo#2026';

// window.confirm() guards the sign-out action — accept it.
test.beforeEach(async ({ page }) => {
    page.on('dialog', dialog => dialog.accept());
});

async function login(page) {
    await page.goto('/');
    await page.getByPlaceholder('Work email').fill(EMAIL);
    await page.getByPlaceholder('Password').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    await expect(page.getByText('Total Studies Today')).toBeVisible({ timeout: 20000 });
}

test('staff sign-in lands on the live dashboard', async ({ page }) => {
    await page.goto('/');

    // Unauthenticated users are gated by the real login screen.
    await expect(page.getByPlaceholder('Work email')).toBeVisible();
    await expect(page.getByPlaceholder('Password')).toBeVisible();

    await login(page);

    // Dashboard renders data hydrated from the API bootstrap.
    await expect(page.getByText('Reception Desk')).toBeVisible();

    // A seeded demo study is present (server-persisted, not mock data).
    await expect(page.locator('body')).toContainText('MR-01');
});

test('reception desk lists today\u2019s seeded studies', async ({ page }) => {
    await login(page);

    await page.getByRole('button', { name: 'Reception Desk' }).click();
    await expect(page.getByText('Reception & Patient Check-In Desk')).toBeVisible({ timeout: 15000 });

    // Seeded studies appear in the server-backed worklist.
    await expect(page.locator('body')).toContainText('DX-01');
    await expect(page.locator('body')).toContainText('CT-01');
});

test('technologist worklist and billing render live data', async ({ page }) => {
    await login(page);

    await page.getByRole('button', { name: 'Tech Worklist' }).click();
    await expect(page.getByText('Technologist Worklist & PACS Suite')).toBeVisible({ timeout: 15000 });

    await page.getByRole('button', { name: 'Billing & POS' }).click();
    await expect(page.getByText('Clinical Billing & Point of Sale (POS)')).toBeVisible({ timeout: 15000 });

    // Seeded invoices with real invoice numbers from the database.
    await expect(page.locator('body')).toContainText('INV-');
});

test('sign-out destroys the session and the login gate returns', async ({ page }) => {
    await login(page);

    // Open the profile menu (trigger is the last button in the header actions group).
    await page.locator('header .flex.items-center.space-x-2 > button').last().click();
    await page.getByText('Sign Out').first().click();

    // Session destroyed → the API gate is shown again.
    await expect(page.getByPlaceholder('Work email')).toBeVisible({ timeout: 15000 });

    // The API really is closed for this browser session now.
    await page.goto('/');
    await expect(page.getByPlaceholder('Work email')).toBeVisible();
});
