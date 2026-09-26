import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/**
 * Print parity & visual regression — against the REAL stack.
 *
 * The failure this suite exists for is not "the PDF is missing": it is a
 * document that looked right on screen and came out of the printer different —
 * reflowed, clipped, 80 mm content on an A4 sheet, or a blank page because the
 * print stylesheet hid everything. So every assertion here is about GEOMETRY and
 * CONTENT AGREEMENT between three renderings of the same payload:
 *
 *   1. the browser document (the `/print/{artifact}/{id}` route),
 *   2. the same payload rendered inside the application's print host,
 *   3. the server-rendered PDF of that document.
 *
 * A structural/geometric gate is used rather than a committed pixel baseline
 * because these documents contain live patient data: a golden PNG would break on
 * every seeded fixture change and tell us nothing about the geometry that
 * actually regressed. Where pixels are compared (the rasterised PDF), the
 * comparison is against the browser's own measured paper, not a stored image.
 */

const EMAIL = process.env.E2E_EMAIL ?? 'admin@polytronx-e2e.test';
const PASSWORD = process.env.E2E_PASSWORD ?? 'E2eDemo#2026';

/**
 * Requests are made FROM THE PAGE, not from the test runner.
 *
 * The API is behind Sanctum's stateful (cookie) guard, which only treats a
 * request as authenticated when it arrives like the application's own calls do
 * (same origin, cookie, XSRF token on writes). A request made from the runner
 * process has to reproduce all of that by hand, and getting it subtly wrong
 * reads as "the route is broken" — an afternoon spent debugging the test rather
 * than the product. Driving `fetch` inside the page is what the SPA does, so it
 * is the most faithful client available and cannot drift from the app.
 */
/** GET JSON through the page's own session. */
async function jsonFromPage(page, path) {
    const result = await page.evaluate(async (target) => {
        const response = await fetch(target, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        return { status: response.status, body: await response.text() };
    }, path);

    expect(result.status, `${path} answered ${result.status} — the session or the route is broken`).toBe(200);

    return JSON.parse(result.body).data;
}

/** GET binary content (a PDF) through the page's own session. */
async function binaryFromPage(page, path) {
    const result = await page.evaluate(async (target) => {
        const response = await fetch(target, { credentials: 'same-origin' });
        const bytes = new Uint8Array(await response.arrayBuffer());
        let binary = '';

        // A PDF has to cross the evaluate boundary as text; the head of the file
        // and its MediaBox are ASCII, so latin1 is lossless for what is asserted.
        for (let index = 0; index < bytes.length; index += 1) {
            binary += String.fromCharCode(bytes[index]);
        }

        return {
            status: response.status,
            headers: Object.fromEntries(response.headers.entries()),
            content: binary,
        };
    }, path);

    return { ...result, buffer: Buffer.from(result.content, 'latin1') };
}

/** 1 mm in CSS pixels (96 dpi) and in PDF points. */
const MM_TO_PX = 96 / 25.4;
const MM_TO_PT = 2.8346456693;

// The day the SEEDED studies fall on. The seeder stamps them with
// `now()->format('Y-m-d')` in the APP timezone (Asia/Karachi), so deriving this
// from `toISOString()` (UTC) picks yesterday between 00:00 and 05:00 PKT — the
// manifest and shift-closing documents then render an EMPTY day and still pass
// every structural assertion below, validating nothing.
const TODAY = (() => {
    const now = new Date();
    const month = `${now.getMonth() + 1}`.padStart(2, '0');
    const day = `${now.getDate()}`.padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
})();

async function login(page) {
    await page.goto('/');
    await page.getByPlaceholder('Work email').fill(EMAIL);
    await page.getByPlaceholder('Password').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    await expect(page.getByText('Total Studies Today')).toBeVisible({ timeout: 20000 });
}

/** GET a JSON API path with the session cookie, failing with the status. */
async function api(page, path) {
    return jsonFromPage(page, path);
}

/**
 * A mutating request as the SPA itself makes it.
 *
 * Sanctum's stateful guard validates CSRF on writes, and the token lives in the
 * `XSRF-TOKEN` cookie that axios reads in the browser. Driving the write from
 * inside the page therefore reproduces the real thing (`419` otherwise) rather
 * than reaching around the application's own protection.
 */
async function mutate(page, method, path, body) {
    return page.evaluate(
        async ([verb, target, payload]) => {
            const token = document.cookie
                .split('; ')
                .find((entry) => entry.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length);

            const response = await fetch(target, {
                method: verb,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
                },
                body: JSON.stringify(payload),
            });

            return { status: response.status, text: await response.text() };
        },
        [method, path, body],
    );
}

/** The newest seeded invoice plus the study it bills, as the API shapes them. */
async function invoiceFixture(page) {
    const { invoices } = await api(page, '/api/v1/invoices');
    expect(invoices.length, 'the seeded tenant must have at least one invoice to print').toBeGreaterThan(0);

    const invoice = invoices[0];
    expect(invoice.appointmentId, 'the invoice must be tied to a study so token/label can be printed').toBeTruthy();

    return invoice;
}

/**
 * One document per artifact, at the paper that artifact genuinely belongs on.
 * A receipt is asked for at 80 mm and an invoice at A4 because that is what the
 * application defaults to — the suite must test the shipped configuration, not a
 * convenient one.
 */
function fixtures(invoice) {
    const studyId = invoice.appointmentId;

    return [
        { artifact: 'invoice', id: invoice.id, paper: 'a4', widthMm: 210 },
        { artifact: 'receipt', id: invoice.id, paper: 'thermal80', widthMm: 80 },
        { artifact: 'token', id: studyId, paper: 'thermal80', widthMm: 80 },
        { artifact: 'label', id: studyId, paper: 'label', widthMm: 63.5 },
        { artifact: 'manifest', id: TODAY, paper: 'a4', widthMm: 210 },
        { artifact: 'fee-schedule', id: 'current', paper: 'a4', widthMm: 210 },
        { artifact: 'shift-closing', id: TODAY, paper: 'a4', widthMm: 210 },
    ];
}

async function documentPayload(page, fixture, device = 'e2e-desk-1') {
    return (
        await api(page, `/api/v1/print/${fixture.artifact}/${fixture.id}?paper=${fixture.paper}&device=${device}`)
    ).document;
}

/** Open the standalone print route and wait for the readiness gate. */
async function openDocumentRoute(page, fixture) {
    await page.goto(`/print/${fixture.artifact}/${fixture.id}?paper=${fixture.paper}`);
    await expect(page.locator('body[data-print-ready="true"]')).toBeAttached({ timeout: 20000 });
    await expect(page.locator('.pd-paper')).toBeVisible();

    // The paper box, not the wrapper: it carries the physical width in mm and is
    // therefore the element that can disagree with the PDF's page box.
    return page.locator('.pd-paper');
}

/** Measured size of a locator, in CSS pixels. */
async function boxPx(locator) {
    const box = await locator.boundingBox();
    expect(box, 'the document has no layout box').not.toBeNull();

    return box;
}

/** The MediaBox of a PDF, in points — the PDF's own physical page size. */
function mediaBox(buffer) {
    const text = Buffer.from(buffer).toString('latin1');
    expect(text.startsWith('%PDF'), 'the engine returned something that is not a PDF').toBe(true);
    expect(text, 'the PDF is truncated').toContain('%%EOF');

    const match = /MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/.exec(text);
    expect(match, 'the PDF carries no MediaBox — its paper size cannot be verified').not.toBeNull();

    return { width: Number(match[3]), height: Number(match[4]), text };
}

/** PNG dimensions from the IHDR chunk. No image library, no decoding. */
function pngSize(path) {
    const bytes = readFileSync(path);
    expect(bytes.subarray(1, 4).toString('ascii'), 'not a PNG').toBe('PNG');

    return { width: bytes.readUInt32BE(16), height: bytes.readUInt32BE(20) };
}

/** Rasterise a PDF, or null when poppler is unavailable (local Windows dev). */
function rasterise(pdfBuffer, name) {
    let directory;

    try {
        directory = mkdtempSync(join(tmpdir(), 'print-qa-'));
    } catch {
        return null;
    }

    const pdfPath = join(directory, `${name}.pdf`);
    const pngPath = join(directory, `${name}-1.png`);

    try {
        writeFileSync(pdfPath, pdfBuffer);
        // 96 dpi makes one rasterised pixel equal one CSS pixel, which is what
        // lets the PDF be compared to the browser's own measurement.
        execFileSync('pdftoppm', ['-png', '-f', '1', '-l', '1', '-r', '96', pdfPath, join(directory, name)], {
            stdio: 'ignore',
        });
    } catch {
        return null;
    }

    return existsSync(pngPath) ? pngSize(pngPath) : null;
}

test.describe('print subsystem', () => {
    test('the registry is the single list of everything that can be printed', async ({ page }) => {
        await login(page);

        const registry = await api(page, '/api/v1/print/registry');
        const artifacts = registry.artifacts.map((entry) => entry.artifact);

        // The core clinical and financial documents must all be present; a new
        // printable screen that forgets the registry fails here rather than in
        // production.
        for (const artifact of ['invoice', 'receipt', 'report', 'token', 'label', 'manifest']) {
            expect(artifacts, `${artifact} is not a registered printable artifact`).toContain(artifact);
        }

        for (const entry of registry.artifacts) {
            expect(entry.allowedPapers.length, `${entry.artifact} allows no paper`).toBeGreaterThan(0);
            expect(entry.permission.length, `${entry.artifact} is gated by no permission`).toBeGreaterThan(0);
        }

        // The paper profiles the whole subsystem resolves geometry from.
        const profiles = Object.fromEntries(registry.paperProfiles.map((profile) => [profile.paper, profile]));
        expect(profiles.a4.widthMm).toBeCloseTo(210, 1);
        expect(profiles.a4.heightMm).toBeCloseTo(297, 1);
        expect(profiles.thermal80.widthMm).toBeCloseTo(80, 1);
        expect(profiles.thermal80.safeWidthMm).toBeLessThanOrEqual(80);

        // A report is never a receipt: paper follows document semantics.
        expect(registry.settings.defaultPapers.report).toBe('a4');
        expect(registry.settings.defaultPapers.receipt).toBe('thermal80');
    });

    test('every document renders on its own paper, with no unformatted value', async ({ page }) => {
        await login(page);
        const invoice = await invoiceFixture(page);
        const scales = {};

        for (const fixture of fixtures(invoice)) {
            const document = await documentPayload(page, fixture);

            expect(document.artifact).toBe(fixture.artifact);
            expect(document.paper, `${fixture.artifact} chose the wrong paper`).toBe(fixture.paper);
            expect(document.render.widthMm, `${fixture.artifact} paper width`).toBeCloseTo(fixture.widthMm, 1);
            expect(document.branding.name, `${fixture.artifact} printed without tenant branding`).not.toBe('');

            // The @page rule the browser must honour, and the one stylesheet
            // every engine shares.
            expect(document.pageCss).toContain('@page');
            // A4 is named in the rule (so the browser uses the user's real A4
            // paper); every other profile states its physical size in mm.
            if (fixture.paper === 'a4') {
                expect(document.pageCss).toContain('A4');
            } else {
                expect(document.pageCss).toContain(`${document.render.widthMm}mm`);
            }
            expect(document.documentCss).toContain('.pd-doc');
            expect(document.documentCss).not.toContain('<style');
            expect(document.tokensCss).toContain('.pd-doc');

            // A4, 80 mm roll and label tag are DIFFERENT PHYSICAL DESIGNS, not
            // one scaled into another: a monochrome 203 dpi head needs a
            // monospace face and pure black ink, while a label is read by eye at
            // arm's length on a 63.5 mm tag.
            scales[fixture.paper] = document.tokensCss;

            if (fixture.paper === 'thermal80') {
                expect(document.tokensCss).toContain('monospace');
                expect(document.tokensCss).toContain('color: #000000');
                expect(document.tokensCss).not.toContain('#0f172a');
            }

            if (fixture.paper !== 'a4') {
                expect(document.render.safeWidthMm).toBeLessThanOrEqual(fixture.widthMm);
                expect(document.tokensCss).toContain(`.pd-body { width: ${document.render.safeWidthMm}mm; }`);
            }

            // Nothing that reaches paper may be an unformatted placeholder.
            const strings = [
                ...document.meta.flatMap((row) => [row.label, row.value]),
                ...document.parties.flatMap((party) => party.rows.flatMap((row) => [row.label, row.value])),
                ...document.items.flatMap((item) => [item.description, item.quantity, item.lineTotal]),
                ...document.totals.flatMap((total) => [total.label, total.value]),
                ...document.sections.flatMap((section) => [section.label, section.body]),
                ...document.observations.flatMap((row) => [row.label, row.value]),
                document.documentKey,
                document.title,
            ].join(' ');

            for (const forbidden of ['undefined', 'NaN', '[object Object]']) {
                expect(strings, `${fixture.artifact} printed [${forbidden}]`).not.toContain(forbidden);
            }
        }

        // Three papers, three scales — asserted as a set, because the failure
        // this catches (a receipt inheriting the A4 scale) leaves each document
        // individually plausible.
        expect(new Set(Object.values(scales)).size, 'each paper must own its own type scale').toBe(3);
    });

    test('the browser lays the document out at its physical size', async ({ page }) => {
        await login(page);
        const invoice = await invoiceFixture(page);
        const cases = [
            { artifact: 'invoice', id: invoice.id, paper: 'a4', widthMm: 210 },
            { artifact: 'receipt', id: invoice.id, paper: 'thermal80', widthMm: 80 },
            { artifact: 'label', id: invoice.appointmentId, paper: 'label', widthMm: 63.5 },
        ];

        for (const fixture of cases) {
            const document = await documentPayload(page, fixture);
            const root = await openDocumentRoute(page, fixture);
            const box = await boxPx(root);

            // The document's own box is the physical paper, within a pixel: this
            // is the check that catches an 80 mm receipt rendered on A4.
            expect(
                Math.abs(box.width / MM_TO_PX - fixture.widthMm),
                `${fixture.artifact} rendered ${(box.width / MM_TO_PX).toFixed(1)} mm wide instead of ${fixture.widthMm} mm`,
            ).toBeLessThan(0.6);

            // And the printable body inside it is the safe width, so the margins
            // of a thermal printer are honoured rather than guessed.
            const body = page.locator('.pd-body');
            const bodyBox = await boxPx(body);

            if (fixture.paper !== 'a4') {
                expect(
                    Math.abs(bodyBox.width / MM_TO_PX - document.render.safeWidthMm),
                    `${fixture.artifact} printable body width`,
                ).toBeLessThan(0.6);
            }

            expect(bodyBox.width).toBeLessThanOrEqual(box.width + 0.5);

            // A paper with a FIXED height has to hold its own content. The label
            // used to compose the shared letterhead and the shared key/value
            // table — ~51 mm of content on a 25.4 mm tag — and came out of the
            // printer as three tags per patient with the barcode on the second.
            // No MediaBox assertion could have caught it: the first page still
            // measured exactly 25.4 mm while two more followed it.
            if (document.render.heightMm !== null) {
                expect(
                    bodyBox.height / MM_TO_PX,
                    `${fixture.artifact} content is ${(bodyBox.height / MM_TO_PX).toFixed(1)} mm on a ${document.render.heightMm} mm sheet`,
                ).toBeLessThanOrEqual(document.render.heightMm + 0.6);
            }
        }
    });

    test('the standalone print route is the document and nothing else', async ({ page }) => {
        await login(page);
        const invoice = await invoiceFixture(page);
        const fixture = { artifact: 'invoice', id: invoice.id, paper: 'a4' };

        await openDocumentRoute(page, fixture);

        // On screen the toolbar is present (preview parity)…
        await expect(page.locator('.pd-shell-bar')).toBeVisible();

        // …and under the print media it is gone: no toolbar, no padding, no
        // application chrome on the sheet.
        await page.emulateMedia({ media: 'print' });
        const printed = await page.evaluate(() => {
            const style = (selector) => {
                const node = document.querySelector(selector);
                return node ? getComputedStyle(node) : null;
            };

            return {
                bar: style('.pd-shell-bar')?.display,
                barClass: style('.pd-no-print')?.display,
                routePaper: style('.pd-route-paper'),
                bodyBackground: getComputedStyle(document.body).backgroundColor,
                bodyMargin: getComputedStyle(document.body).margin,
            };
        });

        expect(printed.bar).toBe('none');
        expect(printed.barClass).toBe('none');
        expect(printed.routePaper.padding).toBe('0px');
        expect(printed.bodyBackground).toBe('rgb(255, 255, 255)');
        expect(printed.bodyMargin).toBe('0px');

        await page.emulateMedia({ media: 'screen' });
    });

    test('printing from the application puts exactly one document on paper', async ({ page }) => {
        await login(page);

        // The regression this guards: the print stylesheet used to hide
        // everything except one overlay that eight of nine call sites never
        // rendered into — every print was a blank page.
        await page.getByRole('button', { name: 'Billing & POS' }).click();
        await expect(page.getByText('Clinical Billing & Point of Sale (POS)')).toBeVisible({ timeout: 15000 });

        await page.locator('button[title="Print / preview the tax invoice"]').first().click();

        // The document is portalled to <body> — outside the application's layout,
        // where no ancestor can clip or reposition it — and it announces its own
        // readiness, so the print dialog cannot open on a half-painted page.
        const host = page.locator('body > .pd-print-host');
        await expect(host).toBeVisible({ timeout: 20000 });
        await expect(host).toHaveAttribute('data-print-ready', 'true');
        await expect(host).toHaveAttribute('data-print-paper', 'a4');
        await expect(page.locator('.pd-doc')).toBeVisible();
        await expect(page.locator('.pd-doc')).toContainText(/tax invoice/i);

        // Under print media the application is hidden and the document is not.
        await page.emulateMedia({ media: 'print' });
        const mediaState = await page.evaluate(() => ({
            root: getComputedStyle(document.getElementById('root')).display,
            host: getComputedStyle(document.querySelector('body > .pd-print-host')).display,
            toolbar: getComputedStyle(document.querySelector('.pd-shell')).display,
        }));

        expect(mediaState.root, 'the application shell must not be printed').toBe('none');
        expect(mediaState.host).not.toBe('none');
        expect(mediaState.toolbar).toBe('none');
        await page.emulateMedia({ media: 'screen' });

        // Switching format re-renders the SAME document on the other paper.
        await page.locator('.pd-shell-select select').selectOption('thermal80');
        const receiptHost = page.locator('body > .pd-print-host');
        await expect(receiptHost).toHaveAttribute('data-print-paper', 'thermal80', { timeout: 20000 });
        const receiptPaper = await boxPx(page.locator('.pd-paper'));
        expect(Math.abs(receiptPaper.width / MM_TO_PX - 80)).toBeLessThan(0.6);

        // Closing removes the document from the DOM entirely.
        await page.locator('button[aria-label="Close print preview"]').click();
        await expect(page.locator('body > .pd-print-host')).toHaveCount(0);
    });

    test('fonts, images and codes are loaded before the document is published', async ({ page }) => {
        await login(page);
        const invoice = await invoiceFixture(page);

        await openDocumentRoute(page, { artifact: 'label', id: invoice.appointmentId, paper: 'label' });

        const readiness = await page.evaluate(async () => {
            await document.fonts.ready;

            return {
                fonts: document.fonts.status,
                images: Array.from(document.images).map((image) => ({
                    src: image.currentSrc || image.src,
                    complete: image.complete,
                    width: image.naturalWidth,
                })),
                codes: Array.from(document.querySelectorAll('.pd-code svg')).map((svg) => svg.childElementCount),
            };
        });

        expect(readiness.fonts).toBe('loaded');

        // A logo that has not decoded is a distorted or missing letterhead.
        for (const image of readiness.images) {
            expect(image.complete, `image not loaded: ${image.src}`).toBe(true);
            expect(image.width, `image decoded with no pixels: ${image.src}`).toBeGreaterThan(0);
        }

        // Codes are VECTOR (rules/rects), never a rasterised bitmap: this is what
        // keeps a barcode scannable after the browser or DomPDF scales it.
        expect(readiness.codes.length, 'the label carries no barcode').toBeGreaterThan(0);
        for (const children of readiness.codes) {
            expect(children, 'a barcode was flattened into a single shape').toBeGreaterThan(10);
        }
    });

    test('worst-case content wraps inside the safe width instead of being clipped', async ({ page }) => {
        await login(page);
        const invoice = await invoiceFixture(page);

        const worstCaseLongProcedure =
            'MRI Whole Spine Screening with Contrast — Sagittal T1, T2 and STIR sequences plus post-gadolinium axial imaging';
        const worstCaseName = 'Muhammad Abdul Rehman Siddiqui Qureshi (Referred by Dr. Syed Muhammad Hassan Ali)';
        const worstCaseAddress = 'House 214-B, Block C, Satellite Town, Near District Headquarters Hospital, Rawalpindi';

        for (const fixture of [
            { artifact: 'receipt', id: invoice.id, paper: 'thermal80' },
            { artifact: 'invoice', id: invoice.id, paper: 'a4' },
        ]) {
            await openDocumentRoute(page, fixture);

            // Real reports and real patient names are long. The worst case is
            // injected into the LIVE document (the CSS is under test, not the
            // seed data) and then measured exactly as the printer will lay it out.
            const overflow = await page.evaluate(
                ([procedure, name, address]) => {
                    const paper = document.querySelector('.pd-paper');
                    const paperRight = paper.getBoundingClientRect().right;

                    const setAll = (selector, value) => {
                        document.querySelectorAll(selector).forEach((node) => {
                            node.textContent = value;
                        });
                    };

                    setAll('.pd-label-value', procedure);
                    setAll('.pd-td .pd-b', procedure);
                    setAll('.pd-meta-value', name);
                    setAll('.pd-party-value', name);
                    setAll('.pd-org-lines', address);

                    const widest = Array.from(document.querySelectorAll('.pd-doc *')).reduce((worst, node) => {
                        const right = node.getBoundingClientRect().right - paperRight;
                        return right > worst ? right : worst;
                    }, 0);

                    const body = document.querySelector('.pd-body');

                    return {
                        overflowRight: Math.round(widest * 10) / 10,
                        bodyScroll: body.scrollWidth - body.clientWidth,
                        // The paper box itself: the route deliberately shows the sheet
                        // on a wider backdrop, so the viewport is not the reference —
                        // the sheet is.
                        paperScroll: paper.scrollWidth - paper.clientWidth,
                        paperWidth: paper.getBoundingClientRect().width,
                    };
                },
                [worstCaseLongProcedure, worstCaseName, worstCaseAddress],
            );

            // Nothing may cross the edge of the paper, and no element may need to
            // scroll horizontally: clipped content on a receipt is a lost
            // transaction.
            expect(
                overflow.overflowRight,
                `${fixture.artifact}: content overflows the paper by ${overflow.overflowRight}px`,
            ).toBeLessThanOrEqual(0.5);
            expect(overflow.bodyScroll, `${fixture.artifact}: the printable body overflows`).toBeLessThanOrEqual(1);
            expect(overflow.paperScroll, `${fixture.artifact}: the paper needs horizontal scrolling`).toBeLessThanOrEqual(1);
        }
    });

    test('the PDF is the same physical page the browser showed', async ({ page }) => {
        await login(page);
        const invoice = await invoiceFixture(page);
        const drivers = [];
        const rasterNote = [];

        const cases = [
            { artifact: 'invoice', id: invoice.id, paper: 'a4', widthMm: 210, heightMm: 297 },
            { artifact: 'receipt', id: invoice.id, paper: 'thermal80', widthMm: 80, heightMm: null },
            { artifact: 'label', id: invoice.appointmentId, paper: 'label', widthMm: 63.5, heightMm: 25.4 },
        ];

        for (const fixture of cases) {
            const response = await binaryFromPage(
                page,
                `/api/v1/print/${fixture.artifact}/${fixture.id}/pdf?paper=${fixture.paper}&download=1`,
            );

            expect(response.status, `${fixture.artifact} PDF status`).toBe(200);
            expect(response.headers['content-type']).toContain('application/pdf');
            expect(response.headers['x-print-paper']).toBe(fixture.paper);

            const driver = response.headers['x-print-driver'];
            expect(driver, `${fixture.artifact} did not report its engine`).toBeTruthy();
            const fallback = response.headers['x-print-fallback'];
            drivers.push(`${fixture.artifact}:${driver}${fallback ? ` (fell back: ${fallback})` : ''}`);

            const buffer = response.buffer;
            const box = mediaBox(buffer);

            // The PDF's own page box is the physical paper — a receipt cannot be
            // an A4 sheet, and a label cannot be a letter page with a small tag
            // in the corner.
            expect(
                Math.abs(box.width / MM_TO_PT - fixture.widthMm),
                `${fixture.artifact} PDF page is ${(box.width / MM_TO_PT).toFixed(1)} mm wide, expected ${fixture.widthMm} mm`,
            ).toBeLessThan(0.6);

            if (fixture.heightMm !== null) {
                expect(
                    Math.abs(box.height / MM_TO_PT - fixture.heightMm),
                    `${fixture.artifact} PDF page height`,
                ).toBeLessThan(0.6);
            } else {
                // Roll paper: taller than it is wide, and never a sheet.
                expect(box.height).toBeGreaterThan(120);
                expect(box.height).toBeLessThan(297 * MM_TO_PT);
            }

            // ---- PDF ↔ browser parity -------------------------------------
            const root = await openDocumentRoute(page, fixture);
            const browserBox = await boxPx(root);
            const browserMm = browserBox.width / MM_TO_PX;

            expect(
                Math.abs(browserMm - box.width / MM_TO_PT),
                `${fixture.artifact}: browser paper ${browserMm.toFixed(2)} mm vs PDF ${(box.width / MM_TO_PT).toFixed(2)} mm`,
            ).toBeLessThan(1);

            // ---- rasterised PDF QA ---------------------------------------
            // A file that exists is not proof of a correct layout, so where
            // poppler is available the PDF is rendered back to pixels and its
            // paper is compared with the browser's own raster.
            const raster = rasterise(buffer, `${fixture.artifact}-${fixture.paper}`);

            if (raster) {
                expect(
                    Math.abs(raster.width - browserBox.width),
                    `${fixture.artifact}: rasterised PDF is ${raster.width}px wide, browser paper is ${browserBox.width.toFixed(0)}px`,
                ).toBeLessThanOrEqual(3);
                rasterNote.push(`${fixture.artifact}=${raster.width}x${raster.height}px@96dpi`);
            }
        }

        // Both engines are legitimate (DomPDF is the guaranteed fallback), but a
        // deployment that ships Chromium must say so: this is the assertion that
        // catches a production image where the pixel-true driver silently
        // degraded.
        if (process.env.EXPECT_PRINT_DRIVER) {
            for (const entry of drivers) {
                expect(entry, `engines used: ${drivers.join(' | ')}`).toContain(process.env.EXPECT_PRINT_DRIVER);
                expect(entry, 'a silent engine fallback is exactly the drift this guards').not.toContain('fell back');
            }
        }

        test.info().annotations.push({
            type: 'print-engine',
            description: `drivers=${[...new Set(drivers)].join(',')}${rasterNote.length ? ` raster=${rasterNote.join(' ')}` : ''}`,
        });
    });

    test('a calibrated workstation changes its own receipt, and only its own', async ({ page }) => {
        await login(page);
        const invoice = await invoiceFixture(page);

        const before = await documentPayload(page, { artifact: 'receipt', id: invoice.id, paper: 'thermal80' });

        // Pin THIS browser's workstation id: a calibration is stored against the
        // workstation that performed it, and the print route can only reach a
        // calibrated profile if the browser and the payload agree on the id.
        const device = await page.evaluate(() => {
            window.localStorage.setItem('polytronx.print.device', 'e2e-counter-1');

            return window.localStorage.getItem('polytronx.print.device');
        });
        expect(device).toBe('e2e-counter-1');

        const saved = await mutate(page, 'PUT', '/api/v1/settings/printing', {
            device,
            calibration: {
                [device]: { label: 'E2E counter', safeWidthMm: 58, feedMm: 14, fontScale: 0.9 },
            },
        });
        expect(saved.status, 'a tenant admin must be able to calibrate a printer').toBe(200);
        expect(saved.text).toContain('calibration');

        // The calibrated desk gets its dimensions…
        const calibrated = await documentPayload(page, { artifact: 'receipt', id: invoice.id, paper: 'thermal80' }, device);
        expect(calibrated.render.safeWidthMm).toBeCloseTo(58, 1);
        expect(calibrated.render.feedMm).toBeCloseTo(14, 1);
        expect(calibrated.tokensCss).toContain('58mm');

        // …and the neighbouring desk does not inherit them.
        const neighbour = await documentPayload(
            page,
            { artifact: 'receipt', id: invoice.id, paper: 'thermal80' },
            'e2e-desk-2',
        );
        expect(neighbour.render.safeWidthMm).toBeCloseTo(before.render.safeWidthMm, 1);

        // The page stays 80 mm — that is the roll, not the printable area.
        expect(calibrated.render.widthMm).toBeCloseTo(80, 1);

        // The calibrated document actually renders narrower on screen, which is
        // the whole point of calibrating: a 58 mm printer must not clip.
        const root = await openDocumentRoute(page, {
            artifact: 'receipt',
            id: invoice.id,
            paper: 'thermal80',
        });
        expect(root).not.toBeNull();
        const body = await boxPx(page.locator('.pd-body'));
        expect(body.width / MM_TO_PX).toBeGreaterThan(57.4);
        expect(body.width / MM_TO_PX).toBeLessThan(58.6);
    });

    test('printing is a read: it creates no invoice, payment, study or report', async ({ page }) => {
        await login(page);
        const invoice = await invoiceFixture(page);

        const count = async () => {
            const { invoices } = await api(page, '/api/v1/invoices');
            const payments = invoices.reduce((total, row) => total + (row.payments?.length ?? 0), 0);

            return { invoices: invoices.length, payments, newest: invoices[0]?.invoiceNumber };
        };

        const before = await count();

        // Print, reprint, download the PDF of record and file the audit events —
        // twice, because a double-press on Print is exactly the accident this
        // guards against.
        for (let round = 0; round < 2; round += 1) {
            await api(page, `/api/v1/print/invoice/${invoice.id}`);
            await api(page, `/api/v1/print/invoice/${invoice.id}?reprint=1`);
            await binaryFromPage(page, `/api/v1/print/invoice/${invoice.id}/pdf?paper=a4`);

            const event = await mutate(page, 'POST', `/api/v1/print/invoice/${invoice.id}/events`, {
                event: round === 0 ? 'printed' : 'reprinted',
                paper: 'a4',
                device: 'e2e',
            });
            expect(event.status, `the audit event was refused: ${event.text}`).toBe(200);
        }

        const after = await count();

        expect(after, 'printing must never create a financial or clinical record').toEqual(before);

        // A reprint is marked as such on the document itself.
        const reprint = await api(page, `/api/v1/print/invoice/${invoice.id}?reprint=1`);
        expect(reprint.document.marks.map((mark) => mark.code)).toContain('REPRINT');
    });

    test('an anonymous browser can neither read nor render a print document', async ({ page, browser }) => {
        await login(page);
        const invoice = await invoiceFixture(page);

        // A real second browser with no session at all: the print payload, the
        // PDF of record, the registry and the settings endpoint must all be
        // closed, and a document must not be reachable by guessing an id.
        const anonymous = await browser.newContext();

        try {
            const stranger = await anonymous.newPage();
            await stranger.goto('/');

            for (const path of [
                '/api/v1/print/registry',
                '/api/v1/settings/printing',
                `/api/v1/print/invoice/${invoice.id}`,
                `/api/v1/print/invoice/${invoice.id}/pdf`,
                '/api/v1/print/report/1',
            ]) {
                const status = await stranger.evaluate(
                    async (target) => (await fetch(target, { credentials: 'same-origin' })).status,
                    path,
                );

                expect(status, `${path} must be closed to an anonymous browser`).toBe(401);
            }
        } finally {
            await anonymous.close();
        }
    });
});
