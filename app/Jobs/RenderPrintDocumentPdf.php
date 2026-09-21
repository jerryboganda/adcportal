<?php

namespace App\Jobs;

use App\Models\PrintDocumentRecord;
use App\Services\Print\PrintDocumentFactory;
use App\Services\Print\PrintPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Render a document PDF in the background.
 *
 * Pixel-true rendering means running a browser, which is not something a web
 * request in a clinic's busy hour should wait on. Queued rendering also gives
 * the archive a place to be built ahead of the first reprint request.
 */
class RenderPrintDocumentPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    /**
     * @param  array{finalized?: bool, force?: bool, paper?: string, deviceId?: string, options?: array<string, mixed>}  $payload
     */
    public function __construct(
        public readonly int $businessId,
        public readonly string $artifact,
        public readonly string $documentKey,
        public readonly array $payload = [],
    ) {
    }

    public function handle(PrintPdfService $pdf): void
    {
        $factory = new PrintDocumentFactory($this->businessId);

        $document = $factory->build(
            $this->artifact,
            $this->documentKey,
            $this->payload['paper'] ?? null,
            $this->payload['deviceId'] ?? null,
            $this->payload['options'] ?? [],
        );

        $pdf->store($this->businessId, $document, [
            'finalized' => $this->payload['finalized'] ?? false,
            'force' => $this->payload['force'] ?? false,
        ]);
    }

    /** Documents a tenant already has on disk are skipped by the archive sweep. */
    public function record(): ?PrintDocumentRecord
    {
        return PrintDocumentRecord::query()
            ->where('business_id', $this->businessId)
            ->where('artifact', $this->artifact)
            ->where('document_key', $this->documentKey)
            ->first();
    }
}
