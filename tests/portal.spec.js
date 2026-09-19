import { test, expect } from '@playwright/test';

/**
 * Critical user journey against the REAL stack: Laravel API + seeded
 * database + compiled SPA, all served from one origin (Laravel).
 * Journey: login → dashboard (server data) → reception desk → tech worklist →
 * billing → notification center → sign out → login gate returns.
 */

const EMAIL = process.env.E2E_EMAIL ?? 'admin@polytronx-e2e.test';
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
    await expect(page.locator('body')).toContainText('Bilal Ahmed Sheikh');
});

test('reception desk lists today\u2019s seeded studies', async ({ page }) => {
    await login(page);

    await page.getByRole('button', { name: 'Reception Desk' }).click();
    await expect(page.getByText('Reception & Patient Check-In Desk')).toBeVisible({ timeout: 15000 });

    // Seeded studies appear in the server-backed worklist.
    // (Tokens are integer sequences now; anchor on seeded patients.)
    await expect(page.locator('body')).toContainText('Bilal Ahmed Sheikh');
    await expect(page.locator('body')).toContainText('Capt. (R) Asadullah Khan');
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

    // Open the profile menu via its accessible label (a11y-hardened trigger).
    await page.getByRole('button', { name: 'Open profile menu' }).click();
    await page.getByText('Sign Out').first().click();

    // Session destroyed → the API gate is shown again.
    await expect(page.getByPlaceholder('Work email')).toBeVisible({ timeout: 15000 });

    // The API really is closed for this browser session now.
    await page.goto('/');
    await expect(page.getByPlaceholder('Work email')).toBeVisible();
});

const RADIOLOGIST_EMAIL = 'dr.shahzad@amaddiagnosticcentre.com.pk';

/**
 * Fill the walk-in form up to a selected, price-loaded procedure.
 * Returns once the modal shows the tenant-configured price as payable.
 */
async function selectCtProcedure(page, patientName) {
    await page.getByRole('button', { name: 'Book Study' }).click();
    await expect(page.getByText('Book Diagnostic Imaging Study')).toBeVisible({ timeout: 15000 });

    await page.getByRole('button', { name: '+ New Walk-In' }).click();
    await page.getByPlaceholder('e.g. Tariq Mehmood').fill(patientName);
    await page.getByLabel('Phone Number').fill('0300-1234567');
    await page.getByLabel('Patient age').fill('34');

    // The imaging-suite dropdown is honest when nothing is configured.
    await expect(page.getByText(/No imaging suites are configured for this modality/)).toBeVisible();

    // Tenant-configured procedure (server-seeded CT catalog) at Rs. 6,500.
    await page.getByLabel('Modality', { exact: true }).selectOption({ label: 'Computed Tomography (CT)' });
    await page.getByLabel('Procedure Service').selectOption({ label: 'CT Brain Non-Contrast (NCCT) - Rs. 6,500' });

    await expect(page.getByTestId('booking-base-price')).toContainText('6,500');
    await expect(page.getByTestId('booking-payable')).toContainText('6,500');
}

test('reception booking captures full payment and lands paid in billing', async ({ page }) => {
    await login(page);
    await selectCtProcedure(page, 'E2E Paid Walkin');

    // Settle in full, in cash — from the tenant-configured method list.
    await page.getByLabel('Payment Status').selectOption('paid');
    // Full collection IS the final payable: the amount is derived, never typed.
    await expect(page.getByTestId('booking-amount-received')).toContainText('6,500');
    await expect(page.getByTestId('booking-outstanding')).toContainText('0');
    await page.getByLabel('Payment Method').selectOption({ label: 'Cash (Counter Drawer)' });

    await page.getByRole('button', { name: 'Confirm & Generate Token' }).click();
    await expect(page.getByText('Book Diagnostic Imaging Study')).toBeHidden({ timeout: 15000 });

    // The confirmation states the server-minted token and the settled amount.
    await expect(page.getByRole('status')).toContainText('Token #');

    // The paid invoice is real: persisted server-side and visible in Billing.
    await page.getByRole('button', { name: 'Billing & POS' }).click();
    await expect(page.getByText('Clinical Billing & Point of Sale (POS)')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('body')).toContainText('E2E Paid Walkin');
    await expect(page.locator('body')).toContainText('PAID');
});

test('walk-in discount recalculates the payable instantly and books the net amount', async ({ page }) => {
    await login(page);
    await selectCtProcedure(page, 'E2E Discount Walkin');

    // Type a discount: 6,500 − 3,500 = 3,000, with no save, reload or API trip.
    await page.getByTestId('booking-discount').fill('3500');
    await expect(page.getByTestId('booking-payable')).toContainText('3,000');

    await page.getByLabel('Payment Status').selectOption('paid');
    await expect(page.getByTestId('booking-amount-received')).toContainText('3,000');
    await expect(page.getByTestId('booking-outstanding')).toContainText('0');
    await page.getByLabel('Payment Method').selectOption({ label: 'Cash (Counter Drawer)' });

    await page.getByRole('button', { name: 'Confirm & Generate Token' }).click();
    await expect(page.getByText('Book Diagnostic Imaging Study')).toBeHidden({ timeout: 15000 });
    await expect(page.getByRole('status')).toContainText('Token #');

    // The discounted invoice is what the server persisted — 3,000, not 6,500.
    await page.getByRole('button', { name: 'Billing & POS' }).click();
    await expect(page.getByText('Clinical Billing & Point of Sale (POS)')).toBeVisible({ timeout: 15000 });
    const row = page.locator('tr', { hasText: 'E2E Discount Walkin' });
    await expect(row).toContainText('3,000');
    await expect(row).toContainText('PAID');
});

test('an over-discount is refused inline and books nothing', async ({ page }) => {
    await login(page);
    await selectCtProcedure(page, 'E2E Overdiscount Walkin');

    await page.getByTestId('booking-discount').fill('7000');
    await expect(page.getByTestId('booking-payable')).toContainText('0');
    // An over-discount is NOT a waiver: it is an invalid entry.
    await expect(page.getByTestId('booking-fully-discounted')).toHaveCount(0);

    await page.getByRole('button', { name: 'Confirm & Generate Token' }).click();

    // Still open, with a precise reason — and no booking created.
    await expect(page.getByText('Book Diagnostic Imaging Study')).toBeVisible();
    await expect(page.getByTestId('booking-form-error')).toContainText('Discount cannot exceed the study price');
});

test('100% discount books a zero-payable study and generates a token', async ({ page }) => {
    await login(page);
    await selectCtProcedure(page, 'E2E Waived Walkin');

    await page.getByTestId('booking-discount').fill('6500');
    await expect(page.getByTestId('booking-payable')).toContainText('0');
    await expect(page.getByTestId('booking-fully-discounted')).toBeVisible();
    // Nothing to collect, so no payment method is demanded.
    await expect(page.getByLabel('Payment Method')).toHaveCount(0);

    await page.getByRole('button', { name: 'Confirm & Generate Token' }).click();
    await expect(page.getByText('Book Diagnostic Imaging Study')).toBeHidden({ timeout: 15000 });

    // The zero-payable booking succeeded and still received its token.
    await expect(page.getByRole('status')).toContainText('Token #');
    await expect(page.getByRole('status')).toContainText('settled');
});

test('radiologist receives no booking privileges anywhere in the SPA', async ({ page }) => {
    await page.goto('/');
    await page.getByPlaceholder('Work email').fill(RADIOLOGIST_EMAIL);
    await page.getByPlaceholder('Password').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    await expect(page.getByText('Total Studies Today')).toBeVisible({ timeout: 20000 });

    // No "Book Diagnostic Study" module on the radiologist dashboard.
    await expect(page.getByRole('button', { name: 'Book Study' })).toHaveCount(0);

    // The global search offers no "+ New Booking" affordance either.
    await page.getByPlaceholder('Search by Patient Name, MRN, Token (DX-01), ID...').click();
    await page.getByPlaceholder('Search by Patient Name, MRN, Token (DX-01), ID...').fill('zzz-no-match-zzz');
    await expect(page.getByText('No matching records found')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('+ Book New Patient & Study')).toHaveCount(0);
    await expect(page.getByText('+ New Booking')).toHaveCount(0);

    // Reception Desk and Billing tabs are not part of the radiologist surface.
    await expect(page.getByRole('button', { name: 'Reception Desk' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Billing & POS' })).toHaveCount(0);
});

test('tenant admin RBAC console lists roles, matrix and access preview', async ({ page }) => {
    await login(page);

    await page.getByRole('button', { name: 'Settings', exact: true }).click();
    await expect(page.getByText('System Settings & Governance')).toBeVisible({ timeout: 15000 });

    // The server-backed Roles & Permissions control center mounts in the
    // default Users & RBAC section and lists the provisioned system roles.
    await expect(page.getByText('Roles & Permissions')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('body')).toContainText('SYSTEM');
    await expect(page.locator('body')).toContainText('Radiologist');

    // The permission matrix renders catalog groups against all roles.
    await page.getByRole('button', { name: 'Permission Matrix', exact: true }).click();
    await expect(page.locator('body')).toContainText('Modules');

    // Preview-as-role: a safe simulation of what the role will see.
    await page.getByRole('button', { name: 'Roles', exact: true }).click();
    await page.getByRole('button', { name: 'Preview as role' }).first().click();
    await expect(page.getByText(/What .* sees/)).toBeVisible({ timeout: 10000 });
});

async function bookWalkin(page, name) {
    await page.getByRole('button', { name: 'Book Study' }).click();
    await expect(page.getByText('Book Diagnostic Imaging Study')).toBeVisible({ timeout: 15000 });
    await page.getByRole('button', { name: '+ New Walk-In' }).click();
    await page.getByPlaceholder('e.g. Tariq Mehmood').fill(name);
    await page.getByLabel('Phone Number').fill('0300-7788990');
    await page.getByLabel('Patient age').fill('41');
    await page.getByLabel('Modality', { exact: true }).selectOption({ label: 'Computed Tomography (CT)' });
    await page.getByLabel('Procedure Service').selectOption({ label: 'CT Brain Non-Contrast (NCCT) - Rs. 6,500' });
    await page.getByLabel('Payment Status').selectOption('unpaid');
    await page.getByRole('button', { name: 'Confirm & Generate Token' }).click();
    await expect(page.getByText('Book Diagnostic Imaging Study')).toBeHidden({ timeout: 15000 });
}

test('live queue console moves a called patient into now-serving', async ({ page }) => {
    await login(page);
    await bookWalkin(page, 'E2E Queue Walkin');

    await page.getByRole('button', { name: 'Live Queue TV' }).click();
    await expect(page.getByText('Live Queue Console')).toBeVisible({ timeout: 15000 });

    // The new walk-in waits in "Next in Line" within one poll interval.
    const row = page.locator('div.bg-white.border', { hasText: 'E2E Queue Walkin' }).first();
    await expect(row).toBeVisible({ timeout: 15000 });

    // Call → server-stamped → the entry LEAVES the waiting list and appears
    // as "Called" in Now Serving (polled, not local-only).
    await row.getByRole('button', { name: 'Call', exact: true }).click();
    await expect(
        page.locator('div.border-sky-300', { hasText: 'E2E Queue Walkin' })
    ).toBeVisible({ timeout: 15000 });
});

test('waiting-room TV kiosk renders the live queue with no login', async ({ page }) => {
    await login(page);
    await bookWalkin(page, 'E2E TV Walkin');

    await page.getByRole('button', { name: 'Live Queue TV' }).click();
    await expect(page.getByText('Live Queue Console')).toBeVisible({ timeout: 15000 });

    // Call the patient: the TV shows names on now-serving cards only.
    const row = page.locator('div.bg-white.border', { hasText: 'E2E TV Walkin' }).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    await row.getByRole('button', { name: 'Call', exact: true }).click();
    await expect(
        page.locator('div.border-sky-300', { hasText: 'E2E TV Walkin' })
    ).toBeVisible({ timeout: 15000 });

    // Admin copies the private display link from Display Setup.
    await page.getByRole('button', { name: 'Display Setup' }).click();
    const linkInput = page.locator('input[readonly]');
    await expect(linkInput).toHaveValue(/\/tv\?key=/, { timeout: 15000 });
    const tvLink = await linkInput.inputValue();

    // The kiosk renders outside the app shell: no login gate, live zones.
    await page.goto(tvLink);
    await expect(page.getByText(/Patient Calling System/i)).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('E2E TV Walkin')).toBeVisible({ timeout: 15000 });
    await expect(page.getByPlaceholder('Work email')).toHaveCount(0);

    // An invalid key degrades to a setup hint — never a login screen.
    await page.goto('/tv?key=bogus-key');
    await expect(page.getByText('Display link is no longer valid')).toBeVisible({ timeout: 15000 });
    await expect(page.getByPlaceholder('Work email')).toHaveCount(0);
});
