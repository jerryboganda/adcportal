@php
    /*
     * THE document stylesheet.
     *
     * Consumed twice from one physical file: the server PDF engines render this
     * blade directly, and the API ships the rendered CSS to the SPA inside the
     * print payload (`documentCss`), where the preview and the browser print
     * inject it verbatim. There is therefore no second stylesheet to drift.
     *
     * Rules of this sheet:
     *   - BARE CSS, no `<style>` wrapper. The HTML layout wraps it for the PDF
     *     engines; the print API ships it inside a payload the SPA injects with
     *     `style.textContent`. A wrapper here would be injected as TEXT — the
     *     CSS parser would drop the first rule (its selector becomes
     *     `<style> .pd-doc`) and the stray `</style>` would swallow `
     *     .pd-paper`, i.e. the paper geometry itself;
     *   - CSS 2.1 only (tables, inline-block, no flex/grid/transforms) because
     *     DomPDF must paint the same document;
     *   - every length is physical (mm/pt) — no vw/vh/px scaling, so the layout
     *     cannot reflow with a viewport;
     *   - no background-only meaning: an inverted block always has a border and
     *     text so it survives "background graphics" being switched off;
     *   - `pd-` namespace only: application CSS can never reach into a document.
     */
    $render = $document['render'];
    $paper = $document['paper'];
    $m = $render['margins'];
    $mm = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp
.pd-doc { margin: 0; padding: 0; background: #ffffff; }
.pd-doc table { border-collapse: collapse; }
.pd-paper { margin: 0 auto; background: #ffffff; }

/* ---- paper geometry ---------------------------------------------------- */
@if($paper === 'a4')
.pd-paper { width: {{ $mm($render['widthMm']) }}mm; min-height: {{ $mm($render['heightMm']) }}mm;
            padding: {{ $mm($m['top']) }}mm {{ $mm($m['right']) }}mm {{ $mm($m['bottom']) }}mm {{ $mm($m['left']) }}mm; }
@elseif($paper === 'label')
.pd-paper { width: {{ $mm($render['widthMm']) }}mm; height: {{ $mm($render['heightMm']) }}mm;
            padding: {{ $mm($m['top']) }}mm {{ $mm($m['right']) }}mm {{ $mm($m['bottom']) }}mm {{ $mm($m['left']) }}mm; }
@else
.pd-paper { width: {{ $mm($render['widthMm']) }}mm;
            padding: {{ $mm($m['top']) }}mm {{ $mm($m['right']) }}mm {{ $mm($m['bottom']) }}mm {{ $mm($m['left']) }}mm; }
@endif

/* The printable body is always the physical safe width: an 80 mm receipt that
   wraps mid-amount is a failed receipt. */
.pd-body { margin: 0 auto; }
@if($paper !== 'a4')
.pd-body { width: {{ $mm($render['safeWidthMm']) }}mm; }
@endif
.pd-c { text-align: center; }
.pd-r { text-align: right; }
.pd-b { font-weight: bold; }
.pd-upper { text-transform: uppercase; }
.pd-muted { color: #555555; }
.pd-nowrap { white-space: nowrap; }

/* ---- draft / reprint / void marks ------------------------------------- */
.pd-marks { margin-bottom: 2mm; }
.pd-mark { display: inline-block; border: 0.4mm solid #000000; padding: 0.6mm 2mm; font-weight: bold;
           text-transform: uppercase; letter-spacing: 0.04em; }
.pd-mark--critical { border-color: #b91c1c; color: #b91c1c; }
.pd-mark--warn { border-color: #b45309; color: #b45309; }
.pd-mark--draft { border-style: dashed; }

/* ---- letterhead ------------------------------------------------------- */
.pd-header { width: 100%; }
.pd-header td { vertical-align: top; padding: 0; }
.pd-logo { max-height: 16mm; max-width: 46mm; }
.pd-org { font-size: 1.35em; font-weight: bold; letter-spacing: -0.01em; }
.pd-org-tagline { font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.12em; color: #444444; }
.pd-org-lines { font-size: 0.85em; color: #444444; margin-top: 0.8mm; }
.pd-doctype { text-align: right; }
.pd-doctype-label { display: inline-block; border: 0.4mm solid #000000; padding: 0.8mm 2.4mm; font-weight: bold;
                    text-transform: uppercase; letter-spacing: 0.08em; }
.pd-doctype-status { margin-top: 1.2mm; font-weight: bold; }

.pd-hero { text-align: center; border: 0.5mm solid #000000; padding: 2mm; margin-top: 3mm; }
.pd-hero-label { font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.1em; }
.pd-hero-value { font-size: 2.4em; font-weight: bold; letter-spacing: 0.06em; }
.pd-hero-room { font-size: 0.95em; font-weight: bold; }

/* ---- key/value blocks ------------------------------------------------- */
.pd-meta { width: 100%; }
.pd-meta-row td { padding: 0.3mm 0; vertical-align: top; }
.pd-meta-label { color: #555555; width: 32%; }
.pd-meta-value { font-weight: bold; }

.pd-parties { width: 100%; }
.pd-parties td { width: 50%; vertical-align: top; padding: 0 2mm 0 0; }
.pd-party { border: 0.25mm solid #999999; padding: 1.6mm; }
.pd-party-title { font-size: 0.82em; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em;
                  border-bottom: 0.25mm solid #cccccc; padding-bottom: 0.6mm; margin-bottom: 0.8mm; }
.pd-party-row td { padding: 0.2mm 0; vertical-align: top; }
.pd-party-label { color: #555555; width: 38%; }
.pd-party-value { font-weight: bold; }

/* ---- notices ---------------------------------------------------------- */
.pd-notice { border: 0.3mm solid #999999; padding: 1.2mm 1.8mm; margin-top: 2mm; }
.pd-notice--critical { border-color: #b91c1c; color: #b91c1c; font-weight: bold; }
.pd-notice--muted { color: #333333; }

/* ---- tables ----------------------------------------------------------- */
.pd-table { width: 100%; }
.pd-th { border-bottom: 0.4mm solid #000000; padding: 1.2mm; text-align: left; text-transform: uppercase;
         letter-spacing: 0.06em; font-size: 0.85em; }
.pd-td { border-bottom: 0.2mm solid #cccccc; padding: 1.2mm; vertical-align: top; }
.pd-items-sub { font-size: 0.82em; color: #555555; }
.pd-group-row td { padding: 1.6mm 0 0.6mm; font-weight: bold; text-transform: uppercase;
                   letter-spacing: 0.08em; border-bottom: 0.3mm solid #000000; }
.pd-row-empty { color: #555555; font-style: italic; }

/* ---- structured observations ----------------------------------------- */
.pd-obs-row td { padding: 0.4mm 0; vertical-align: top; }
.pd-obs-label { color: #444444; width: 46%; }

/* ---- narrative -------------------------------------------------------- */
.pd-section-title { font-size: 0.85em; font-weight: bold; text-transform: uppercase; letter-spacing: 0.12em;
                    border-bottom: 0.25mm solid #cccccc; padding-bottom: 0.4mm; }
.pd-section-group { font-size: 0.9em; font-weight: bold; text-transform: uppercase; letter-spacing: 0.12em;
                    border-bottom: 0.3mm solid #000000; padding-bottom: 0.6mm; }
.pd-section-body { white-space: pre-wrap; margin-top: 1mm; }
.pd-section--emphasis .pd-section-body { font-weight: bold; }
.pd-narrative { white-space: pre-wrap; }

/* ---- totals / payments ------------------------------------------------ */
.pd-totals { width: 100%; margin-top: 2mm; }
.pd-total-row td { padding: 0.7mm 0; vertical-align: top; }
.pd-total-label { color: #333333; }
.pd-total-value { text-align: right; font-weight: bold; white-space: nowrap; }
.pd-total-row--emphasis td { border-top: 0.4mm solid #000000; font-size: 1.08em; }
.pd-tone-success { color: #15803d; }
.pd-tone-warn { color: #b45309; }
.pd-tone-critical { color: #b91c1c; }
.pd-tone-accent { color: #0e7490; }
.pd-tone-muted { color: #555555; }

/* ---- signature -------------------------------------------------------- */
.pd-signature { width: 100%; margin-top: 6mm; }
.pd-signature td { vertical-align: bottom; padding: 0; }
.pd-signature-line { border-top: 0.3mm solid #000000; margin-bottom: 1mm; }
.pd-signature-name { font-weight: bold; }
.pd-signature-statement { font-size: 0.85em; color: #444444; }
.pd-signature-ref { font-size: 0.8em; color: #555555; }

/* ---- codes (barcode / QR) -------------------------------------------- */
.pd-codes { margin-top: 2.5mm; }
.pd-code { text-align: center; }
.pd-code svg, .pd-code img { display: block; margin: 0 auto; }
.pd-code-value { font-size: 0.8em; letter-spacing: 0.14em; margin-top: 0.6mm; }

/* ---- footer ---------------------------------------------------------- */
.pd-footer { margin-top: 3mm; border-top: 0.25mm solid #999999; padding-top: 1.4mm; font-size: 0.8em; color: #444444; }
.pd-footer-note { margin-top: 0.6mm; }

/* ---- repeating page footer (print only) ------------------------------ */
.pd-pagefoot { display: none; }
@media print {
    .pd-pagefoot { display: block; position: fixed; bottom: 0; left: 0; right: 0; font-size: 0.75em;
                   color: #444444; border-top: 0.25mm solid #999999; padding-top: 0.8mm; }
    .pd-pagefoot-cell { display: inline-block; }
    .pd-pagefoot-left { width: 40%; text-align: left; }
    .pd-pagefoot-center { width: 30%; text-align: center; }
    .pd-pagefoot-right { width: 28%; text-align: right; }
}

/*
 * Pagination contract.
 *
 * A long Findings section MUST be allowed to break: `break-inside: avoid` on a
 * section that is taller than a page either clips it or produces a blank sheet.
 * Only genuinely indivisible blocks are kept whole, and a heading is never left
 * stranded at the foot of a page away from its body.
 */
.pd-keep { break-inside: avoid; page-break-inside: avoid; }
.pd-keep-next { break-after: avoid; page-break-after: avoid; }
.pd-body p { orphans: 3; widows: 3; }
.pd-table thead { display: table-header-group; }
.pd-table tr { break-inside: avoid; page-break-inside: avoid; }

/* ---- thermal tuning -------------------------------------------------- */
@if($paper !== 'a4')
.pd-rule-dashed { border-top: 0.25mm dashed #000000; margin: 1.6mm 0; }
.pd-label-value { font-weight: bold; }
@endif

/*
 * Print geometry.
 *
 * On screen the paper box carries the margins so the preview shows the physical
 * page. On paper the box stops pretending to be a sheet and the page margins come
 * from `@page`, so the browser can paginate natively — the same content width in
 * both cases, which is why the preview and the printed page line up.
 */
@media print {
    html, body { margin: 0 !important; padding: 0 !important; background: #ffffff !important; }
    .pd-paper { width: auto !important; min-height: 0 !important; height: auto !important;
                padding: 0 !important; margin: 0 !important; box-shadow: none !important; border: 0 !important; }
    .pd-body { width: 100% !important; }
@if($paper !== 'a4')
    .pd-body { width: {{ $mm($render['safeWidthMm']) }}mm !important; margin: 0 auto !important; }
@endif
}
