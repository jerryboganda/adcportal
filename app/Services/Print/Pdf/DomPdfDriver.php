<?php

namespace App\Services\Print\Pdf;

use App\Support\Print\PaperProfile;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;

/**
 * DomPDF: the always-available engine.
 *
 * It receives a physical page box in points rather than a named format, which is
 * what makes a 80 mm thermal PDF genuinely narrow instead of an A4 sheet with a
 * small receipt in the corner. Roll paper has no page height, so the box is
 * sized from the document's own estimated line count plus the configured feed.
 */
final class DomPdfDriver implements PrintPdfDriver
{
    public function name(): string
    {
        return 'dompdf';
    }

    public function available(): bool
    {
        return class_exists(PdfFacade::class);
    }

    public function render(string $html, PaperProfile $paper, array $options = []): string
    {
        $lines = (int) ($options['estimatedLines'] ?? 0);

        $pdf = PdfFacade::loadHTML($html)
            ->setPaper($paper->dompdfPageBox($lines));

        // A document must never fetch anything at render time: the logo arrives
        // as a data URI, and a missing asset would otherwise become a hung
        // request inside a clinical workflow.
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        PrintFonts::register($pdf);

        return $pdf->output();
    }
}
