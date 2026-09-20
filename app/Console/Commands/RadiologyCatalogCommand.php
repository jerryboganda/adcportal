<?php

namespace App\Console\Commands;

use App\Models\RadiologyCatalogRelease;
use App\Services\RadiologyCatalog\CatalogImporter;
use App\Services\RadiologyCatalog\CatalogNormalizer;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

class RadiologyCatalogCommand extends Command
{
    protected $signature = 'ris:catalog
        {action=validate : validate, dry-run, import, or report}
        {artifact? : Path to a normalized JSON artifact}
        {--sha256= : Reviewed normalized artifact SHA-256, required for import}
        {--release= : Stored release key for report}';

    protected $description = 'Validate, preview, or stage a radiology catalog in CI; never publish or reset clinical data';

    public function handle(CatalogNormalizer $normalizer, CatalogImporter $importer): int
    {
        if (! app()->environment('testing') && getenv('GITHUB_ACTIONS') !== 'true') {
            $this->error('Catalog processing must run in GitHub Actions, not on a local or serving production host.');

            return self::FAILURE;
        }
        $action = (string) $this->argument('action');
        if (! in_array($action, ['validate', 'dry-run', 'import', 'report'], true)) {
            $this->error('Unsupported action. Publication, activation and reset are not available through this command.');

            return self::FAILURE;
        }
        if ($action === 'report') {
            $release = RadiologyCatalogRelease::where('release_key', (string) $this->option('release'))->first();
            if (! $release) {
                $this->error('The requested catalog release was not found.');

                return self::FAILURE;
            }
            $this->line(json_encode([
                'releaseKey' => $release->release_key,
                'status' => $release->status,
                'artifactSha256' => $release->artifact_sha256,
                'counts' => $release->counts,
                'fixture' => (bool) ($release->manifest['fixture'] ?? true),
                'publishedAt' => $release->published_at?->toIso8601String(),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $path = realpath((string) $this->argument('artifact'));
        if ($path === false || ! is_file($path) || ! is_readable($path) || filesize($path) > 128 * 1024 * 1024) {
            $this->error('Provide a readable local JSON artifact no larger than 128 MiB.');

            return self::FAILURE;
        }
        if ($action === 'import' && ! preg_match('/\A[a-f0-9]{64}\z/D', (string) $this->option('sha256'))) {
            $this->error('Import requires --sha256 from a reviewed validate or dry-run result.');

            return self::FAILURE;
        }

        try {
            $input = json_decode(file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
            if (! is_array($input) || array_is_list($input)) {
                throw ValidationException::withMessages(['artifact' => 'The artifact must be a JSON object.']);
            }
            if ($action === 'validate') {
                $artifact = $normalizer->normalize($input);
                if ($artifact['fixture'] && ! app()->environment('testing')) {
                    throw ValidationException::withMessages(['fixture' => 'Synthetic catalog fixtures are restricted to tests.']);
                }
                $result = [
                    'releaseKey' => $artifact['release_key'],
                    'artifactSha256' => hash('sha256', CatalogNormalizer::canonicalJson($artifact)),
                    'counts' => $normalizer->counts($artifact),
                    'fixture' => $artifact['fixture'],
                    'validation' => 'structural_only',
                    'activatesTenants' => false,
                ];
            } elseif ($action === 'dry-run') {
                $result = $importer->preview($input);
            } else {
                $release = $importer->stage($input, (string) $this->option('sha256'));
                $result = [
                    'releaseKey' => $release->release_key,
                    'artifactSha256' => $release->artifact_sha256,
                    'status' => $release->status,
                    'counts' => $release->counts,
                    'activatesTenants' => false,
                ];
            }
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return empty($result['conflicts']) ? self::SUCCESS : self::FAILURE;
        } catch (ValidationException $exception) {
            foreach (array_slice(\Illuminate\Support\Arr::flatten($exception->errors()), 0, 20) as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        } catch (JsonException $exception) {
            $this->error('Invalid catalog JSON: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}