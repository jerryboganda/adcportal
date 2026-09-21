<?php

namespace App\Services\Print\Pdf;

use App\Support\Print\PaperProfile;

/**
 * A PDF engine.
 *
 * Two engines exist on purpose, and the difference is stated rather than
 * hidden:
 *
 *  - `dompdf`   — always available, pure PHP, CSS 2.1 only. It is the archival
 *                 fallback and it renders the SAME blade markup and the SAME
 *                 token sheet as everything else.
 *  - `chromium` — headless Chromium through the Playwright that CI already
 *                 installs. It is the pixel-true path: the identical CSS is fed
 *                 to the same engine the browser print dialog uses, so the PDF
 *                 and the browser print of one document are the same layout.
 *
 * The driver is resolved at render time (config + availability) so a deployment
 * without Chromium keeps working, and a deployment that installs it becomes
 * pixel-true without a code change.
 */
interface PrintPdfDriver
{
    public function name(): string;

    public function available(): bool;

    /**
     * @param  array{timeout?: int, baseUrl?: string|null}  $options
     * @return string raw PDF bytes
     */
    public function render(string $html, PaperProfile $paper, array $options = []): string;
}
