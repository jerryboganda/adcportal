#!/usr/bin/env node
/**
 * Render a print document to PDF with headless Chromium.
 *
 * Used by App\Services\Print\Pdf\ChromiumPdfDriver, and directly by CI for the
 * print visual-regression job. It renders the SAME html/css the browser print
 * path uses, waits for fonts and images, and — for roll paper — measures the
 * document and sizes the page to it instead of forcing a sheet format.
 *
 * Usage:
 *   node scripts/print-pdf.mjs --html in.html --out out.pdf \
 *     --width-mm 80 [--height-mm 297 | --auto-height --feed-mm 8] [--prefer-css-page-size] \
 *     [--launch-args "--no-sandbox,--disable-dev-shm-usage"]
 */
import { chromium } from 'playwright';
import { readFileSync, statSync } from 'node:fs';

const args = process.argv.slice(2);

function flag(name) {
    const index = args.indexOf(`--${name}`);
    return index === -1 ? null : (args[index + 1] ?? null);
}

const htmlPath = flag('html');
const outPath = flag('out');
const widthMm = Number(flag('width-mm') ?? 210);
const heightMmRaw = flag('height-mm');
const autoHeight = args.includes('--auto-height');
const feedMm = Number(flag('feed-mm') ?? 0);
const preferCssPageSize = args.includes('--prefer-css-page-size');

if (!htmlPath || !outPath) {
    console.error('print-pdf: --html and --out are required');
    process.exit(2);
}

const mmToPx = (mm) => `${mm}mm`;

// Terminal hints are off for every render so the shapes the browser and the
// server disagree about most (hairline rules, 7 pt table text) are laid out by
// metrics rather than by a rasteriser setting.
//
// `--launch-args` exists for the deployment: a container runs Chromium without
// the namespaces its sandbox needs (`--no-sandbox`) and with a 64 MB /dev/shm
// (`--disable-dev-shm-usage`). Those flags are deployment policy, so they are
// passed in rather than compiled in — a workstation keeps its sandbox.
const launchArgs = (flag('launch-args') ?? '')
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean);

const browser = await chromium.launch({
    args: ['--font-render-hinting=none', ...launchArgs],
});

try {
    const page = await browser.newPage({ viewport: { width: 1240, height: 1754 } });

    // The document is self-contained (tokens, logo data URI, barcode geometry),
    // so nothing is fetched over the network and a render cannot hang on a
    // remote asset.
    await page.setContent(readFileSync(htmlPath, 'utf8'), { waitUntil: 'load' });

    await page.evaluate(async () => {
        if (document.fonts?.ready) {
            await document.fonts.ready;
        }
        await Promise.all(
            Array.from(document.images)
                .filter((img) => !img.complete)
                .map((img) => new Promise((resolve) => {
                    img.addEventListener('load', resolve, { once: true });
                    img.addEventListener('error', resolve, { once: true });
                }))
        );
    });

    let heightMm = heightMmRaw ? Number(heightMmRaw) : null;

    if (!heightMm && autoHeight) {
        // Measure the printable body, not the viewport: the page grows with the
        // receipt, and a receipt that is cut mid-total is a lost transaction.
        const bodyHeightPx = await page.evaluate(() => {
            const node = document.querySelector('.pd-body') ?? document.body;
            const rect = node.getBoundingClientRect();
            return Math.ceil(rect.height);
        });

        // 96 CSS px per inch → 1 px = 25.4/96 mm
        heightMm = Math.max(40, Math.ceil(bodyHeightPx * (25.4 / 96) + feedMm + 2));
    }

    const pdfOptions = {
        path: outPath,
        printBackground: true,
        margin: { top: '0', right: '0', bottom: '0', left: '0' },
        width: mmToPx(widthMm),
        preferCSSPageSize: preferCssPageSize,
    };

    if (heightMm) {
        pdfOptions.height = mmToPx(heightMm);
    } else {
        pdfOptions.format = 'A4';
    }

    await page.pdf(pdfOptions);

    const bytes = statSync(outPath).size;
    if (!bytes) {
        console.error('print-pdf: produced an empty file');
        process.exit(1);
    }
} catch (error) {
    console.error(`print-pdf: ${error?.stack ?? error}`);
    process.exit(1);
} finally {
    await browser.close();
}
