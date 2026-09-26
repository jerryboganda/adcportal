@php
    /**
     * Invoice PDF — the document of record.
     *
     * Same contract as the report PDF: this is the stable entry point, not a
     * second design. The canonical print document model carries the tenant's
     * currency presentation, its date format, its letterhead and the invoice's
     * PERSISTED totals, so the PDF and the printed page cannot show different
     * money.
     */
    use App\Services\Print\PrintPdfService;

    $document = $document ?? app(PrintPdfService::class)->documentFor('invoice', $invoice, $paper ?? null);
@endphp
@include('print.document', ['document' => $document->toArray()])
