# Printing subsystem

Every printable document in the platform — A4 invoice, report of record, 80 mm
receipt, queue token, wristband label, daily manifest, fee schedule, doctor
settlement, shift closing — is produced by **one pipeline**:

```
                        PERSISTED DATA
                              │
                              ▼
          PrintDocumentFactory (app/Services/Print)
           · authoritative models only, no recomputation
           · every string finished: money, dates, '—' for missing
           · vector barcode/QR geometry from one encoder
                              │
                              ▼
                 PrintDocument model (one payload)
           version · artifact · paper · render geometry
           pageCss · tokensCss · documentCss   ← the whole design
                              │
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
   /api/v1/print/…       print.document         print.document
   (JSON payload)        → headless Chromium    → DomPDF (fallback)
        │                (pixel-true)           (guaranteed)
        ▼
   PrintDocumentView.tsx  ← SPA renders the SAME payload
   (preview, browser print, /print/{artifact}/{id})
```

## 1. One document, three renderings

| Rendering | What paints it | Stylesheet |
| --- | --- | --- |
| In-app preview | `src/print/PrintDocumentView.tsx` | `documentCss` from the payload |
| Browser print dialog | the same React tree, portalled to `<body>` | same |
| `/print/{artifact}/{id}` route | the same React tree | same |
| PDF of record | `resources/views/print/document.blade.php` | same |

The stylesheet is **bare CSS** (no `<style>` wrapper) rendered by
`resources/views/print/partials/styles.blade.php` and shipped inside the payload;
`useDocumentStyles` injects it with `style.textContent`. There is one copy of it
in the codebase. `PrintDocumentTest` asserts the payload never carries a wrapper
(a wrapper injected as text loses its first rule and its stray `</style>` eats the
next one).

Design tokens live in `resources/print/print-tokens.json` — the single source for
paper geometry, type scale, ink and rules. `PrintTokens::css()` emits literal CSS
from it (DomPDF has no custom properties); the SPA receives that same CSS.
**One key per paper** (`a4`, `thermal80`, `label`): `PrintDesignSystemTest` asserts
every paper resolves its own scale, because a key mismatch (`thermal` vs
`thermal80`) once made receipts inherit the A4 proportional, grey-ink scale.

## 2. Paper profiles (physical)

| Profile | Paper | Printable (safe) width | Page box |
| --- | --- | --- | --- |
| `a4` | 210 × 297 mm | 210 mm minus tenant margins (10/14/18 mm presets) | `@page { size: A4 portrait; margin: … }` |
| `thermal80` | 80 mm roll, height grows | tenant default 72 mm, per-workstation 48–80 mm | `@page { size: 80mm auto; margin: 0 }` |
| `label` | 63.5 × 25.4 mm (2.5″ × 1″) | 59.5 mm | `@page { size: 63.5mm 25.4mm; margin: 0 }` |

Thermal is not a scaled-down A4: monospace type, pure black ink, dashed rules,
stacked line items, no table columns. A report can never be printed on a receipt
roll (`PrintArtifactRegistry::allowsPaper`), and a label can never be printed on
A4.

## 3. PDF engines

* **Chromium (pixel-true, preferred)** — `ChromiumPdfDriver` shells out to
  `scripts/print-pdf.mjs` (Playwright). Same engine as the operator's print
  dialog, same HTML, same CSS, explicit `width`/`height` in mm for roll paper
  (`--auto-height` measures the rendered body and adds the configured feed), and
  `preferCSSPageSize` for A4 so the tenant's margins come from `@page`.
  The production image ships node + Playwright + Chromium (Dockerfile `pdf`
  stage) and verifies it at deploy time.
* **DomPDF (fallback)** — always available, given an explicit page box in points,
  no remote assets (`isRemoteEnabled=false`). Line breaking can differ from the
  browser; the driver that painted every stored PDF is recorded in
  `print_document_records.driver`.
* **A fallback is never silent** — it is logged, reported in the
  `X-Print-Fallback` response header, and shown by `php artisan print:doctor`.
  The print visual job fails if a documented `chromium` deployment falls back.

`php artisan print:doctor [--render]` prints the resolved engine, why Chromium is
or is not usable, every paper profile, every registered artifact, and (with
`--render`) renders one page per paper profile and verifies its physical width.

## 4. Artifact registry

`app/Support/Print/PrintArtifactRegistry.php` is the only list of what can be
printed, on what paper, behind which permission. The controller authorises from
it, the tenant settings screen renders from it, the SPA resolves default paper
from it, and both test suites iterate it — adding a printable document without a
paper profile, a permission or a fixture fails the suite.

| Artifact | Default paper | Allowed | Permission (any of) |
| --- | --- | --- | --- |
| invoice | a4 | a4, thermal80 | `invoice print`, `invoice manage` |
| receipt | thermal80 | thermal80, a4 | `receipt print`, `invoice manage` |
| report | a4 | a4 | `report print`, `report manage` |
| token | thermal80 | thermal80 | `receipt print`, `study checkin`, `appointment manage` |
| label | label | label, thermal80 | `label print`, `study acquire`, `study checkin`, `appointment manage` |
| manifest | a4 | a4 | `receipt print`, `appointment manage` |
| fee-schedule | a4 | a4 | `invoice print`, `catalog view` |
| doctor-settlement | a4 | a4 | `invoice print`, `doctors view` |
| shift-closing | a4 | a4 | `invoice print`, `invoice payment` |

## 5. API

| Route | Purpose |
| --- | --- |
| `GET /api/v1/print/registry` | artifacts, settings, paper profiles, currency/date options |
| `GET /api/v1/print/{artifact}/{id}` | the canonical document payload |
| `GET /api/v1/print/{artifact}/{id}/pdf` | the PDF of the same document |
| `POST /api/v1/print/{artifact}/{id}/events` | audit event for a browser print |
| `GET/PUT /api/v1/settings/printing` | tenant print settings + per-workstation calibration |

* **Printing is a read.** No endpoint creates or mutates an invoice, payment,
  study or report; the only write is the archive row for a rendered PDF. Double
  pressing Print cannot duplicate anything (`PrintDocumentTest`).
* **Tenant isolation.** A document that does not belong to the active tenant is a
  404, indistinguishable from one that does not exist.
* **Reprints** reproduce the persisted document (`?reprint=1` adds a `REPRINT`
  mark) and finalized documents are archived once — a later branding change cannot
  rewrite a filed document, and a reprint is byte-identical.
* **Audit**: `document_printed`, `document_reprinted`, `document_pdf_downloaded`
  with user, tenant, artifact, document key, paper, device and engine — metadata
  only, never clinical content.

## 6. Browser printing

`PrintHost` is mounted once at the root and renders the active document into
`<body>` (outside the application layout, where nothing can clip it). It:

1. injects the document's stylesheet and `@page` rule into `<head>`;
2. waits for fonts (`document.fonts.ready` plus the exact weights used), for every
   image to decode, and for two painted frames — each wait bounded, so a CDN
   outage delays a print instead of blocking it;
3. publishes `data-print-ready="true"` on the host and `<body>`;
4. only then lets `window.print()` run, and removes the document afterwards.

Under `@media print` everything except the host (or `#root` on the standalone
route) is hidden, so no toolbar, badge or sidebar can reach paper. The old
architecture hid the application and printed a single overlay that eight of nine
call sites never rendered into — every print was blank.

## 7. Thermal calibration

The safe width and the feed belong to **the printer in front of the operator**,
not to the platform. The preview's **Calibrate** panel writes `safeWidthMm`
(48–80 mm), `feedMm` (0–30 mm) and `fontScale` (0.85–1.25) against a workstation
id kept in `localStorage`, so a 58 mm roll is fixed at that desk without a support
ticket and without touching a neighbour's receipts. Values are clamped
server-side; the page stays 80 mm (that is the physical roll) and only the
printable body narrows.

## 8. Testing

| Layer | What it proves |
| --- | --- |
| `tests/Unit/PrintDesignSystemTest.php` | geometry clamps, page rules, per-paper type scales, money/date formatting, Code128 patterns |
| `tests/Feature/PrintDocumentTest.php` | every artifact produces a complete payload; printing writes nothing; tenant isolation; RBAC; calibration scoping; verbatim clinical text |
| `tests/Feature/PrintPdfTest.php` | every artifact's PDF page box in points (A4 vs 80 mm vs label), calibration effect, empty-PDF guard |
| `tests/print/visual.spec.js` | real browser: paper geometry, print-media CSS, readiness gate, worst-case overflow, PDF↔browser parity, rasterised PDF QA, calibration, "printing is a read", anonymous refusal |
| CI `print-visual` job | the above on **Chromium and Edge**, with `EXPECT_PRINT_DRIVER=chromium` so a silent engine fallback fails the build; plus `print:doctor --render` |
| Deploy smoke | the deployed image reports `chromium available yes` and renders every paper profile at its physical size |

## 9. Deliberate limitations

* The browser still owns a few things no web application can control: the user's
  paper choice, "scale to fit", the browser's own headers/footers, and the
  printer's hardware margins. The preview says so, and the recommended settings
  are: 100 % scale, headers/footers off, "Background graphics" on.
* `@page` margins are honoured by Chromium and by the browser print path. DomPDF
  receives an explicit page box instead (it cannot use `size: … auto`), which is
  why roll paper is estimated from the document's own line count plus feed.
* Chromium rendering is CPU-heavy; it runs off the web tier's critical path
  (queued job for finalized documents, on-demand for downloads) and is time-boxed
  at 45 s, after which DomPDF renders the document so a clinic is never blocked.
* Fonts are the browser's/OS's: documents name `Plus Jakarta Sans` and
  `JetBrains Mono` with monospace/sans fallbacks, so a machine without them
  prints slightly different metrics but the same layout hierarchy.
