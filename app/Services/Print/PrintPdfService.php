<?php

namespace App\Services\Print;

use App\Models\PrintDocumentRecord;
use App\Services\Print\Pdf\ChromiumPdfDriver;
use App\Services\Print\Pdf\DomPdfDriver;
use App\Services\Print\Pdf\PrintPdfDriver;
use App\Support\Print\PrintDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * PDF orchestration: model → HTML → engine → stored artefact.
 *
 * The HTML always comes from the shared print layout (`resources/views/print`),
 * which consumes the shared token sheet, so a PDF cannot drift from the browser
 * document by having its own template. What varies between engines is only how
 * faithfully that HTML is painted — and which engine painted it is recorded on
 * the stored file rather than assumed.
 */
final class PrintPdfService
{
    public function documentFor(string $artifact, Model $model, ?string $paper = null, array $options = []): PrintDocument
    {
        $factory = new PrintDocumentFactory((int) $model->getAttribute('business_id'));

        return $factory->build($artifact, (string) $model->getKey(), $paper, null, $options);
    }

    public function html(PrintDocument $document): string
    {
        return view('print.document', ['document' => $document->toArray()])->render();
    }

    /**
     * Configured engine, or the best one this deployment can actually run.
     * `auto` prefers Chromium (pixel-true) and degrades to DomPDF.
     */
    public function driver(): PrintPdfDriver
    {
        $configured = (string) config('ris.print.pdf_driver', 'auto');

        $chromium = new ChromiumPdfDriver();

        if ($configured === 'dompdf') {
            return new DomPdfDriver();
        }

        if ($configured === 'chromium') {
            return $chromium->available() ? $chromium : new DomPdfDriver();
        }

        return $chromium->available() ? $chromium : new DomPdfDriver();
    }

    /**
     * @return array{bytes: string, driver: string, fallbackReason: string|null}
     */
    public function render(PrintDocument $document): array
    {
        $html = $this->html($document);
        $driver = $this->driver();
        $options = ['estimatedLines' => $document->lineCountEstimate()];

        try {
            return ['bytes' => $driver->render($html, $document->paper(), $options), 'driver' => $driver->name(), 'fallbackReason' => null];
        } catch (\Throwable $e) {
            if ($driver->name() === 'dompdf') {
                throw $e;
            }

            // A missing/broken browser must not cost a clinic its document.
            report($e);

            $fallback = new DomPdfDriver();

            return [
                'bytes' => $fallback->render($html, $document->paper(), $options),
                'driver' => $fallback->name(),
                'fallbackReason' => $e->getMessage(),
            ];
        }
    }

    /** Path convention for a stored document, stable across reprints. */
    public function pathFor(int $businessId, PrintDocument $document): string
    {
        return sprintf(
            'print/%d/%s/%s-%s.pdf',
            $businessId,
            $document->artifact(),
            $document->filenameStem(),
            $document->paper()->paper(),
        );
    }

    public function record(int $businessId, PrintDocument $document): ?PrintDocumentRecord
    {
        return PrintDocumentRecord::query()
            ->where('business_id', $businessId)
            ->where('artifact', $document->artifact())
            ->where('document_key', $document->documentKey())
            ->where('paper', $document->paper()->paper())
            ->first();
    }

    /**
     * Render and store the document.
     *
     * A finalized document is rendered ONCE: after that the stored file is the
     * document, so a later branding change cannot rewrite history. A draft is
     * re-rendered on request, because it is still being worked on.
     *
     * @param  array{finalized?: bool, force?: bool, path?: string, rendered?: array{bytes: string, driver: string}}  $options
     */
    public function store(int $businessId, PrintDocument $document, array $options = []): PrintDocumentRecord
    {
        $finalized = (bool) ($options['finalized'] ?? PrintDocumentRecord::isFinalizedArtifact($document->artifact(), true));
        $force = (bool) ($options['force'] ?? false);
        $disk = Storage::disk('public');

        $existing = $this->record($businessId, $document);

        if ($existing && ! $force && ($existing->finalized || ! config('ris.print.archive', true))) {
            if ($disk->exists((string) $existing->path)) {
                return $existing;
            }
        }

        // Callers that already painted the document (a report filing its own
        // PDF, for instance) pass the bytes in rather than paying for a second
        // render of the same page.
        $rendered = $options['rendered'] ?? $this->render($document);
        $path = (string) ($options['path'] ?? $this->pathFor($businessId, $document));

        $disk->put($path, $rendered['bytes']);

        return PrintDocumentRecord::updateOrCreate(
            [
                'business_id' => $businessId,
                'artifact' => $document->artifact(),
                'document_key' => $document->documentKey(),
                'paper' => $document->paper()->paper(),
            ],
            [
                'driver' => $rendered['driver'],
                'path' => $path,
                'bytes' => strlen($rendered['bytes']),
                'checksum' => hash('sha256', $rendered['bytes']),
                'finalized' => $finalized,
                'generated_by' => auth()->id(),
                'generated_at' => now(),
            ],
        );
    }
}
