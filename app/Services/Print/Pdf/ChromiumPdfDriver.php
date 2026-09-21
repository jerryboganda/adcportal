<?php

namespace App\Services\Print\Pdf;

use App\Support\Print\PaperProfile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * Headless Chromium — the pixel-true path.
 *
 * The PDF of record and the browser print dialog are the same engine here, fed
 * the same markup and the same token sheet, so "preview ≈ print ≈ PDF" stops
 * being an aspiration for A4 and 80 mm documents alike.
 *
 * It is deliberately OFF the web tier's critical path: the render is a queued
 * job (`RenderPrintDocumentPdf`) or an explicitly requested download, it runs a
 * separate process, it is time-boxed, and every failure falls back to DomPDF
 * while recording why. A clinic mid-shift must never get a blank page because a
 * browser binary was not installed.
 */
final class ChromiumPdfDriver implements PrintPdfDriver
{
    private const AVAILABILITY_CACHE_KEY = 'print:chromium:available';

    public function name(): string
    {
        return 'chromium';
    }

    public function available(): bool
    {
        return $this->diagnose()['available'];
    }

    /**
     * Why this deployment can (or cannot) render pixel-true PDFs.
     *
     * Returned as a reason rather than a bare boolean because the failure mode is
     * invisible: a driver that silently degrades produces a *plausible* PDF in a
     * different engine, and nobody notices until a receipt wraps differently than
     * it did in the print dialog. `php artisan print:doctor` prints this.
     *
     * @param  bool  $fresh  bypass the probe cache (an operator asking "is this
     *                       deployment pixel-true?" wants the truth, not a
     *                       ten-minute-old answer)
     * @return array{available: bool, reason: string, node: string, script: string, args: string}
     */
    public function diagnose(bool $fresh = false): array
    {
        $config = config('ris.print.chromium', []);
        $node = (string) ($config['node'] ?? 'node');
        $script = $this->script();
        $fail = static fn (string $reason): array => [
            'available' => false,
            'reason' => $reason,
            'node' => $node,
            'script' => $script,
            'args' => (string) ($config['args'] ?? ''),
        ];

        if (! ($config['enabled'] ?? false)) {
            return $fail('disabled (RIS_PRINT_CHROMIUM)');
        }

        if (! is_file($script)) {
            return $fail("renderer script missing: {$script}");
        }

        // The cache is a convenience, never an authority: a flushed or
        // unreachable cache store must not be able to decide how a document is
        // painted. (It used to: a missing cache table made every PDF fall back to
        // DomPDF, silently.)
        try {
            $cached = $fresh ? null : Cache::get(self::AVAILABILITY_CACHE_KEY);
        } catch (\Throwable) {
            $cached = null;
        }

        if (is_bool($cached)) {
            return [
                'available' => $cached,
                'reason' => $cached ? 'probe cache: available' : 'probe cache: a failed probe is retried every minute',
                'node' => $node,
                'script' => $script,
                'args' => (string) ($config['args'] ?? ''),
            ];
        }

        $result = $this->probe($node);

        if (! $fresh) {
            try {
                Cache::put(
                    self::AVAILABILITY_CACHE_KEY,
                    $result['available'],
                    $result['available'] ? now()->addMinutes(10) : now()->addMinute(),
                );
            } catch (\Throwable) {
                // A cache store that cannot be written is not a printing problem.
            }
        }

        return [
            'available' => $result['available'],
            'reason' => $result['reason'],
            'node' => $node,
            'script' => $script,
            'args' => (string) ($config['args'] ?? ''),
        ];
    }

    /** @return array{available: bool, reason: string} */
    private function probe(string $node): array
    {
        try {
            $version = new Process([$node, '--version'], null, $this->environment());
            $version->setTimeout(5);
            $version->run();

            if (! $version->isSuccessful()) {
                return ['available' => false, 'reason' => 'node is not runnable: '.trim($version->getErrorOutput())];
            }

            // Playwright itself is the real dependency: node alone cannot drive a
            // browser.
            $probe = new Process(
                [
                    $node,
                    '-e',
                    "import('playwright').then(()=>process.exit(0)).catch(()=>process.exit(1))",
                ],
                base_path(),
                $this->environment(),
            );
            $probe->setTimeout(10);
            $probe->run();

            return $probe->isSuccessful()
                ? ['available' => true, 'reason' => 'node '.trim($version->getOutput()).' + playwright']
                : ['available' => false, 'reason' => 'playwright is not installed for node (npm i playwright)'];
        } catch (\Throwable $e) {
            return ['available' => false, 'reason' => $e->getMessage()];
        }
    }

    public function render(string $html, PaperProfile $paper, array $options = []): string
    {
        $config = config('ris.print.chromium', []);
        $workdir = storage_path('app/print-tmp');

        if (! is_dir($workdir)) {
            @mkdir($workdir, 0775, true);
        }

        $token = bin2hex(random_bytes(8));
        $htmlFile = $workdir.'/'.$token.'.html';
        $pdfFile = $workdir.'/'.$token.'.pdf';

        try {
            file_put_contents($htmlFile, $html);

            $command = [
                (string) ($config['node'] ?? 'node'),
                $this->script(),
                '--html', $htmlFile,
                '--out', $pdfFile,
                '--width-mm', (string) $paper->widthMm(),
            ];

            $height = $paper->heightMm();
            if ($height !== null) {
                $command[] = '--height-mm';
                $command[] = (string) $height;
            } else {
                // Roll paper: measure the rendered document and size the page to
                // it, so a receipt PDF is a receipt and not an A4 sheet.
                $command[] = '--auto-height';
                $command[] = '--feed-mm';
                $command[] = (string) $paper->feedMm();
            }

            if ($paper->paper() === PaperProfile::A4) {
                $command[] = '--prefer-css-page-size';
            }

            // Deployment-supplied launch flags (container sandbox/shared-memory
            // policy). Passed as one argument so a stray flag cannot become a
            // second CLI option.
            $launchArgs = trim((string) ($config['args'] ?? ''));
            if ($launchArgs !== '') {
                $command[] = '--launch-args';
                $command[] = $launchArgs;
            }

            $process = new Process($command, base_path(), $this->environment());
            $process->setTimeout((float) ($options['timeout'] ?? ($config['timeout'] ?? 45)));
            $process->run();

            if (! $process->isSuccessful() || ! is_file($pdfFile)) {
                throw new \RuntimeException(
                    'Chromium PDF render failed: '.trim($process->getErrorOutput() ?: $process->getOutput())
                );
            }

            $bytes = (string) file_get_contents($pdfFile);
            if (! str_starts_with($bytes, '%PDF')) {
                throw new \RuntimeException('Chromium did not produce a PDF document.');
            }

            return $bytes;
        } finally {
            @unlink($htmlFile);
            @unlink($pdfFile);
        }
    }

    private function script(): string
    {
        return base_path((string) (config('ris.print.chromium.script') ?? 'scripts/print-pdf.mjs'));
    }

    /**
     * The environment the renderer child process runs in.
     *
     * Symfony's Process, on Windows, REPLACES the environment with the variables
     * it can find in `$_SERVER`/`$_ENV` rather than inheriting the real one — and
     * inside a web request those hold CGI variables, not PATH/SystemRoot/TEMP.
     * Node then fails to start its cryptographic RNG and aborts:
     *
     *     Assertion failed: ncrypto::CSPRNG(nullptr, 0)
     *
     * which the driver correctly reported as "Chromium failed" and the platform
     * correctly survived by falling back to DomPDF — i.e. every PDF silently lost
     * pixel parity with the print dialog, with nothing but a log line to say so.
     *
     * `getenv()` with no argument reads the process's REAL environment, so the
     * child gets what a maintainer would expect: exactly what PHP itself runs
     * with. An empty answer falls back to Symfony's default inheritance.
     *
     * @return array<string, string>|null
     */
    private function environment(): ?array
    {
        $env = getenv();

        if (! is_array($env) || $env === []) {
            return null;
        }

        return array_filter(
            array_map(static fn ($value) => (string) $value, $env),
            static fn (string $value, string $key) => $key !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
