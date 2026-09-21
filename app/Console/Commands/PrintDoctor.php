<?php

namespace App\Console\Commands;

use App\Services\Print\Pdf\ChromiumPdfDriver;
use App\Services\Print\Pdf\DomPdfDriver;
use App\Services\Print\PrintPdfService;
use App\Support\Print\PaperProfile;
use App\Support\Print\PrintArtifactRegistry;
use App\Support\Print\PrintTokens;
use Illuminate\Console\Command;

/**
 * Is this deployment printing the way it was designed to?
 *
 * The print subsystem degrades by falling back, and a fallback is quiet: DomPDF
 * produces a real, plausible PDF that simply does not break lines the way the
 * browser did. Without a check, "the receipts look a bit different" is the first
 * signal anyone gets, and it arrives from a clinic.
 *
 * This command answers the question directly on the box it is run on: which
 * engine will be used, why, and does a real document of each paper profile
 * actually render at the right physical size.
 */
final class PrintDoctor extends Command
{
    protected $signature = 'print:doctor {--render : render a test document for every paper profile}';

    protected $description = 'Report the print PDF engine, its availability and (optionally) probe a render';

    public function handle(): int
    {
        $chromium = new ChromiumPdfDriver();
        $dompdf = new DomPdfDriver();
        $diagnosis = $chromium->diagnose(true);

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Print subsystem</>');
        $this->newLine();

        $this->table(['Setting', 'Value'], [
            ['configured driver', (string) config('ris.print.pdf_driver')],
            ['resolved driver', app(PrintPdfService::class)->driver()->name()],
            ['document archive', config('ris.print.archive') ? 'on' : 'off'],
            ['chromium available', $diagnosis['available'] ? '<fg=green>yes</>' : '<fg=yellow>no</>'],
            ['chromium reason', $diagnosis['reason']],
            ['node binary', $diagnosis['node']],
            ['renderer script', is_file($diagnosis['script']) ? $diagnosis['script'] : $diagnosis['script'].' (missing)'],
            ['launch args', $diagnosis['args'] !== '' ? $diagnosis['args'] : '(none)'],
            ['dompdf available', $dompdf->available() ? 'yes' : 'no'],
        ]);

        if (! $diagnosis['available']) {
            $this->warn('  PDFs will be rendered by DomPDF: correct content, but line breaking can differ from the browser.');
            $this->line('  The production image ships Chromium; a workstation needs node + playwright installed.');
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Paper profiles</>');
        $this->table(
            ['Paper', 'Width mm', 'Safe width mm', 'Page rule', 'Type family'],
            array_map(function (string $paper): array {
                $profile = new PaperProfile($paper);

                return [
                    $paper,
                    (string) $profile->widthMm(),
                    (string) $profile->safeWidthMm(),
                    $profile->pageRule(),
                    strtok((string) (PrintTokens::typeScale($paper)['fontFamily'] ?? ''), ',') ?: '-',
                ];
            }, PaperProfile::papers()),
        );

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Printable artifacts</>');
        $this->table(
            ['Artifact', 'Default paper', 'Allowed paper', 'Permission'],
            array_map(static fn (string $artifact, array $meta): array => [
                $artifact,
                $meta['defaultPaper'],
                implode(' / ', $meta['allowedPapers']),
                implode(' / ', $meta['permission']),
            ], array_keys(PrintArtifactRegistry::all()), array_values(PrintArtifactRegistry::all())),
        );

        if (! $this->option('render')) {
            $this->newLine();
            $this->line('  Add <fg=white>--render</> to prove the engine end to end with one test page per paper profile.');

            return self::SUCCESS;
        }

        return $this->probeRenders();
    }

    /** Render a synthetic document on every paper and verify its physical page. */
    private function probeRenders(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Test render</>');

        $service = app(PrintPdfService::class);
        $rows = [];
        $failed = false;

        foreach (PaperProfile::papers() as $paper) {
            $profile = new PaperProfile($paper);
            $document = new \App\Support\Print\PrintDocument('token', $profile, [
                'documentKey' => 'DOCTOR-'.$paper,
                'title' => 'Print doctor',
                'kindLabel' => 'PRINT ENGINE TEST',
                'title2' => null,
                'status' => null,
                'marks' => [],
                'branding' => [
                    'name' => 'Print doctor',
                    'shortName' => 'Print doctor',
                    'tagline' => '',
                    'branch' => '',
                    'addressLine' => '',
                    'contactLine' => '',
                    'registrations' => [],
                    'logoUrl' => null,
                    'logoDataUri' => null,
                    'reportHeader' => '',
                    'reportFooter' => '',
                    'currency' => 'Rs.',
                ],
                'generatedAt' => now()->toDateTimeString(),
                'meta' => [['label' => 'Paper', 'value' => $paper]],
                'parties' => [],
                'items' => [],
                'totals' => [],
                'payments' => [],
                'sections' => [['label' => 'Renderer', 'body' => 'If you can read this, the engine rendered a page.']],
                'observations' => [],
                'criticalLogs' => [],
                'codes' => [],
                'rows' => [],
                'columns' => [],
                'notices' => [],
                'footerNotes' => ['print:doctor'],
                'signature' => null,
                'pageFoot' => ['left' => 'print:doctor', 'center' => '', 'right' => $paper],
                'tokenNumber' => 'A-01',
                'lineCountEstimate' => 30,
                'options' => ['showLogo' => false, 'showBarcode' => false, 'showSignature' => false, 'currency' => 'Rs.'],
            ]);

            try {
                $rendered = $service->render($document);
                $box = $this->pageBox($rendered['bytes']);
                $expected = $profile->widthMm();
                $actual = $box === null ? null : round($box['width'] / 2.8346456693, 2);
                $ok = $actual !== null && abs($actual - $expected) < 0.6;

                $failed = $failed || ! $ok;

                // A fallback is the point of this command: the bytes are valid,
                // the engine is not the one that was asked for, and the only
                // place the reason exists is here.
                $note = $rendered['fallbackReason'] !== null
                    ? ' <fg=yellow>(fell back: '.substr($rendered['fallbackReason'], 0, 60).')</>'
                    : '';

                $rows[] = [
                    $paper,
                    $rendered['driver'],
                    number_format(strlen($rendered['bytes']) / 1024, 1).' KB',
                    $actual === null ? 'unknown' : $actual.' mm',
                    ($ok ? '<fg=green>ok</>' : '<fg=red>wrong paper</>').$note,
                ];

                if ($rendered['fallbackReason'] !== null) {
                    $failed = true;
                }
            } catch (\Throwable $e) {
                $failed = true;
                $rows[] = [$paper, '—', '—', '—', '<fg=red>'.substr($e->getMessage(), 0, 60).'</>'];
            }
        }

        $this->table(['Paper', 'Engine', 'Size', 'Rendered width', 'Result'], $rows);

        if ($failed) {
            $this->error('  A paper profile did not render at its physical size.');

            return self::FAILURE;
        }

        $this->info('  Every paper profile rendered at its physical size.');

        return self::SUCCESS;
    }

    /** @return array{width: float, height: float}|null */
    private function pageBox(string $pdf): ?array
    {
        if (! preg_match('/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $matches)) {
            return null;
        }

        return ['width' => (float) $matches[3], 'height' => (float) $matches[4]];
    }
}
