@php
    /**
     * Report PDF — the document of record.
     *
     * This view is kept as the stable entry point (`view('reports.pdf', [...])`,
     * `RadiologyReport::storePdf()`), but it no longer owns a layout: it builds
     * the canonical print document model and renders the SAME layout, tokens and
     * partials as the on-screen preview and the browser print. The previous
     * implementation was a second, independently designed document — which is
     * exactly how a preview and its PDF drifted apart.
     */
    use App\Services\Print\PrintPdfService;

    $document = $document ?? app(PrintPdfService::class)->documentFor('report', $report, $paper ?? null);
@endphp
@include('print.document', ['document' => $document->toArray()])
