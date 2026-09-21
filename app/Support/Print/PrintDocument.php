<?php

namespace App\Support\Print;

/**
 * The print document model — one normalised payload that every renderer draws.
 *
 * Application data → PrintDocument → (browser renderer | DomPDF | Chromium).
 *
 * The point of this object is that the *data* is finished before a renderer sees
 * it: money is already formatted, dates are already in the tenant's format and
 * timezone, missing fields are already '—', and the barcode already exists as
 * vector geometry. A renderer that has to make a formatting decision is a
 * renderer that can disagree with the other one.
 *
 * It is deliberately a plain, versionable array with a documented shape rather
 * than a deep class hierarchy: documents are read-only snapshots, and adding a
 * field must never break an archived record.
 */
final class PrintDocument
{
    public const VERSION = 1;

    /**
     * @param  array<string, mixed>  $data  the document body
     */
    public function __construct(
        private readonly string $artifact,
        private readonly PaperProfile $paper,
        private readonly array $data,
    ) {
    }

    public function artifact(): string
    {
        return $this->artifact;
    }

    public function paper(): PaperProfile
    {
        return $this->paper;
    }

    /** Identity of the underlying record: invoice number, report id, token, date. */
    public function documentKey(): string
    {
        return (string) ($this->data['documentKey'] ?? '');
    }

    /** A filename stem that is safe on every operating system. */
    public function filenameStem(): string
    {
        $raw = $this->artifact.'-'.$this->documentKey();
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $raw) ?? $this->artifact;

        return trim($clean, '-') ?: $this->artifact;
    }

    public function lineCountEstimate(): int
    {
        return (int) ($this->data['lineCountEstimate'] ?? 0);
    }

    /**
     * The wire format.
     *
     * `render` carries the resolved paper geometry so the SPA can lay out the
     * exact same page without re-deriving it, and `pageCss` carries the `@page`
     * rule the browser must honour for this document (per-tenant margins).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $render = $this->paper->toArray();
        $paper = $this->paper->paper();

        return array_merge([
            'version' => self::VERSION,
            'artifact' => $this->artifact,
            'artifactLabel' => PrintArtifactRegistry::for($this->artifact)['label'],
            'documentKey' => $this->documentKey(),
            'paper' => $paper,
            'render' => $render,
            'pageCss' => $this->paper->pageRule(),
            'tokensCss' => PrintTokens::css(
                $paper,
                $this->paper->fontScale(),
                $this->paper->safeWidthMm(),
            ),
            // The document stylesheet travels WITH the document, rendered from
            // the same blade partial the PDF engines use. The SPA injects it
            // verbatim, which is why there is exactly one copy of it in the
            // codebase and no way for preview and paper to disagree.
            'documentCss' => $this->documentCss($render, $paper),
        ], $this->data);
    }

    private function documentCss(array $render, string $paper): string
    {
        return view('print.partials.styles', [
            'document' => array_merge($this->data, [
                'paper' => $paper,
                'render' => $render,
            ]),
        ])->render();
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }
}
