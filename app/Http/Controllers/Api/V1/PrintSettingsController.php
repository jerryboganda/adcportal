<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Print\PaperProfile;
use App\Support\Print\PrintArtifactRegistry;
use App\Support\Print\PrintSettings;
use App\Support\Print\PrintTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant printing & documents settings.
 *
 * Everything a clinic legitimately needs to change about its own paper:
 * default paper per artifact, A4 margins, the calibrated thermal width and feed,
 * logo/barcode/signature visibility, currency presentation, date format and the
 * footer text. Everything is structured and clamped server-side — there is no
 * path for a tenant to inject markup, CSS or a 900 mm receipt.
 */
final class PrintSettingsController extends BaseApiController
{
    public function show(Request $request): JsonResponse
    {
        $this->denyUnless('clinic manage');

        $tenantId = $this->tenantId();

        return $this->ok([
            'settings' => PrintSettings::for($tenantId),
            'artifacts' => PrintArtifactRegistry::toArray($tenantId),
            'paperProfiles' => array_map(
                fn (string $paper) => (new PaperProfile($paper))->toArray(),
                PaperProfile::papers(),
            ),
            'marginPresets' => PrintTokens::marginPresetNames(),
            'calibration' => PrintSettings::device($tenantId, $request->query('device')),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->denyUnlessAny(['print settings manage', 'setting manage'], 'print settings manage');

        $input = $request->all();
        $saved = PrintSettings::save($this->tenantId(), $this->mergeDeviceScope($request, $input));

        $this->audit('print_settings_updated', auth()->user(), [
            'summary' => 'Updated printing & document settings for the clinic.',
            'artifacts' => array_keys($saved['defaultPapers']),
            'thermalSafeWidthMm' => $saved['thermalSafeWidthMm'],
            'a4MarginPreset' => $saved['a4MarginPreset'],
        ]);

        return $this->ok([
            'settings' => $saved,
            'artifacts' => PrintArtifactRegistry::toArray($this->tenantId()),
        ]);
    }

    /**
     * Apply a workstation-scoped save.
     *
     * A request that names a `device` is a DESK speaking about the printer in
     * front of it: the thermal dimensions in it belong to that device's
     * calibration entry and never become the tenant default — otherwise one
     * counter whose roll feeds 58 mm would silently shrink every other desk's
     * receipts. The tenant-wide defaults are changed by an administrator save
     * that names no device.
     *
     * Only the named device's entry can be written; entries for other desks are
     * carried through untouched, so two counters saving their own calibration
     * can never overwrite each other.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function mergeDeviceScope(Request $request, array $input): array
    {
        $device = $request->input('device');
        if (! is_string($device) || $device === '') {
            return $input;
        }

        $calibration = PrintSettings::for($this->tenantId())['calibration'];
        $entry = is_array($calibration[$device] ?? null) ? $calibration[$device] : [];

        if (is_array($request->input('calibration'))) {
            foreach ($request->input('calibration') as $id => $submitted) {
                if ((string) $id === $device && is_array($submitted)) {
                    $entry = array_merge($entry, $submitted);
                }
            }
        }

        // The tenant's field names, translated to this device's calibration keys.
        foreach (['thermalSafeWidthMm' => 'safeWidthMm', 'thermalFeedMm' => 'feedMm', 'thermalFontScale' => 'fontScale'] as $field => $key) {
            if ($request->has($field)) {
                $entry[$key] = $request->input($field);
            }
        }

        // Everything else in the request (defaults per artifact, margins, footer
        // text, currency, date format, visibility flags) is genuinely tenant-wide
        // and is saved as such. Only the thermal geometry is device property.
        unset($input['thermalSafeWidthMm'], $input['thermalFeedMm'], $input['thermalFontScale']);

        $input['calibration'] = array_merge($calibration, [$device => $entry]);

        return $input;
    }
}
