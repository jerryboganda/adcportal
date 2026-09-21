<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Services\Print\PrintDocumentFactory;
use App\Services\Print\PrintPdfService;
use App\Support\Print\PaperProfile;
use App\Support\Print\PrintArtifactRegistry;
use App\Support\Print\PrintDocument;
use App\Support\Print\PrintFormat;
use App\Support\Print\PrintSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The print API — one endpoint pair for every printable document.
 *
 * `GET /print/{artifact}/{id}`            the canonical print document model
 * `GET /print/{artifact}/{id}/pdf`        the rendered PDF of the same document
 * `POST /print/{artifact}/{id}/events`    an audit event for a browser print
 *
 * Authorization comes from the artifact registry (the same list the tenant
 * settings screen and the test suite iterate), so a new printable document
 * cannot be added without stating who may print it. Tenant ownership is
 * enforced by the factory: a document that does not belong to the active
 * tenant is a 404, never another clinic's paper.
 *
 * PRINTING IS A READ. Nothing here creates, mutates or duplicates a business
 * record — a pressed Print button must never produce a second invoice, payment
 * or report. The only write is the archive row for a rendered PDF.
 */
final class PrintController extends BaseApiController
{
    public function registry(): JsonResponse
    {
        $tenantId = $this->tenantId();

        return $this->ok([
            'artifacts' => PrintArtifactRegistry::toArray($tenantId),
            'settings' => PrintSettings::for($tenantId),
            'paperProfiles' => array_map(
                fn (string $paper) => (new PaperProfile($paper))->toArray(),
                PaperProfile::papers(),
            ),
            'currencyStyles' => collect(PrintFormat::CURRENCY_STYLES)
                ->map(fn (string $prefix, string $style) => ['style' => $style, 'prefix' => $prefix])
                ->values()
                ->all(),
            'dateFormats' => PrintFormat::DATE_FORMATS,
            'marginPresets' => \App\Support\Print\PrintTokens::marginPresetNames(),
        ]);
    }

    public function show(Request $request, string $artifact, string $id): JsonResponse
    {
        $this->authorizeArtifact($artifact);

        $document = $this->build($request, $artifact, $id, false);

        return $this->ok(['document' => $document->toArray()]);
    }

    public function pdf(Request $request, string $artifact, string $id): Response
    {
        $this->authorizeArtifact($artifact);

        $document = $this->build($request, $artifact, $id, true);
        $pdf = app(PrintPdfService::class);

        $finalized = $artifact === 'report' && ($document->data()['status']['code'] ?? '') === 'signed';
        $shouldArchive = $request->boolean('archive')
            || ($finalized && config('ris.print.archive', true))
            || $artifact === 'report';

        $fallback = null;

        if ($shouldArchive) {
            $record = $pdf->store($this->tenantId(), $document, [
                'finalized' => $finalized,
                'force' => $request->boolean('force'),
            ]);

            $bytes = \Illuminate\Support\Facades\Storage::disk('public')->get((string) $record->path);
            $driver = (string) $record->driver;
        } else {
            $rendered = $pdf->render($document);
            $bytes = $rendered['bytes'];
            $driver = $rendered['driver'];
            $fallback = $rendered['fallbackReason'];
        }

        $this->auditDownload($document, $driver, $request);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')
                .'; filename="'.$document->filenameStem().'.pdf"',
            'X-Print-Driver' => $driver,
            'X-Print-Paper' => $document->paper()->paper(),
            'Cache-Control' => 'private, no-store',
        ];

        // A fallback produces a valid PDF from a different engine, which is the
        // hardest printing failure to notice and the easiest to report. The
        // reason travels with the response (and into the log) instead of living
        // only in a log file nobody reads.
        if ($fallback !== null) {
            $headers['X-Print-Fallback'] = substr(preg_replace('/[^\x20-\x7E]/', ' ', $fallback) ?? '', 0, 180);
            \Illuminate\Support\Facades\Log::warning('Print PDF fell back to another engine', [
                'artifact' => $document->artifact(),
                'paper' => $document->paper()->paper(),
                'reason' => $fallback,
            ]);
        }

        return response($bytes, 200, $headers);
    }

    /**
     * Browser printing cannot be observed by the server, so the SPA reports it.
     * This is an audit signal only: it is fire-and-forget and never blocks or
     * fails a print, and it carries no clinical content.
     */
    public function event(Request $request, string $artifact, string $id): JsonResponse
    {
        $this->authorizeArtifact($artifact);

        $validated = $request->validate([
            'event' => ['required', 'string', 'in:printed,reprinted,pdf'],
            'paper' => ['nullable', 'string'],
            'device' => ['nullable', 'string', 'max:64'],
        ]);

        $action = match ($validated['event']) {
            'reprinted' => 'document_reprinted',
            'pdf' => 'document_pdf_downloaded',
            default => 'document_printed',
        };

        AuditLog::record($action, auth()->user(), [
            'artifact' => $artifact,
            'document' => $id,
            'paper' => $validated['paper'] ?? null,
            'device' => $validated['device'] ?? null,
        ], $this->tenantId() ?: null);

        return $this->ok(['recorded' => true]);
    }

    private function build(Request $request, string $artifact, string $id, bool $forPdf): PrintDocument
    {
        $validated = $request->validate([
            'paper' => ['nullable', 'string', 'in:a4,thermal80,label'],
            'device' => ['nullable', 'string', 'max:64'],
            'reprint' => ['nullable', 'boolean'],
            'draft' => ['nullable', 'boolean'],
            'period' => ['nullable', 'string', 'max:7'],
            'countedCash' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'supervisor' => ['nullable', 'string', 'max:120'],
            'cashier' => ['nullable', 'string', 'max:120'],
            'shiftName' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            return (new PrintDocumentFactory($this->tenantId()))->build(
                $artifact,
                $id,
                $validated['paper'] ?? null,
                $validated['device'] ?? null,
                [
                    'reprint' => (bool) ($validated['reprint'] ?? false),
                    'draft' => (bool) ($validated['draft'] ?? false),
                    'period' => $validated['period'] ?? null,
                    'countedCash' => $validated['countedCash'] ?? null,
                    'supervisor' => $validated['supervisor'] ?? null,
                    'cashier' => $validated['cashier'] ?? null,
                    'shiftName' => $validated['shiftName'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'requestedBy' => auth()->user()?->name,
                ],
            );
        } catch (InvalidArgumentException $e) {
            // A document outside this tenant is indistinguishable from one that
            // does not exist — the boundary is not a discovery oracle.
            abort(response()->json([
                'message' => 'Document not found.',
                'error' => 'print.document_not_found',
            ], 404));
        }
    }

    private function authorizeArtifact(string $artifact): void
    {
        if (! PrintArtifactRegistry::exists($artifact)) {
            abort(response()->json([
                'message' => 'Unknown printable document.',
                'error' => 'print.unknown_artifact',
            ], 404));
        }

        $this->denyUnlessAny(PrintArtifactRegistry::permissions($artifact), 'print '.$artifact);
    }

    private function auditDownload(PrintDocument $document, string $driver, Request $request): void
    {
        // Metadata only — an audit trail must never become a second copy of a
        // patient's document.
        $this->audit('document_pdf_downloaded', auth()->user(), [
            'artifact' => $document->artifact(),
            'document' => $document->documentKey(),
            'paper' => $document->paper()->paper(),
            'driver' => $driver,
            'reprint' => $request->boolean('reprint'),
        ]);
    }
}
