<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudyState;
use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Modality;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Live Queue TV feed: one purpose-built aggregate for both surfaces —
 * the waiting-room TV display (public, key-gated kiosk) and the staff
 * queue console (session + `queue view`, enforced here server-side for
 * the first time).
 *
 * Polling-only by architecture (see docs/saas/SAAS_GAP_MATRIX.md): every
 * terminal converges within one poll interval without websockets.
 *
 * PHI budget: token number, patient display name, modality, room and queue
 * timestamps ONLY. This feed never emits MRN, contact details, DOB or
 * financials, because it is designed to run on an unattended public screen.
 */
class QueueDisplayController extends BaseApiController
{
    /** Per-tenant settings KV keys (settings table — no dedicated migration). */
    public const KEY_SETTING = 'queue_display_key';
    public const ANNOUNCEMENT_SETTING = 'queue_display_announcement';

    private const UP_NEXT_LIMIT = 10;
    private const RECENT_CALLS_LIMIT = 8;

    // ==================== staff console feed ====================

    public function show(): JsonResponse
    {
        $this->denyUnless('queue view');

        $business = Business::findOrFail($this->tenantId());

        return $this->ok($this->payload($business, $this->announcementFor($business->id)));
    }

    // ==================== public kiosk feed ====================

    public function publicShow(Request $request): JsonResponse
    {
        $key = (string) $request->query('key', '');
        abort_if($key === '' || strlen($key) > 64, 404, 'Display link is invalid.');

        // The key IS the credential: resolve the owning tenant with a
        // timing-safe comparison, never trusting anything client-side.
        $row = Setting::where('key', self::KEY_SETTING)->where('value', $key)->first();
        if (! $row || ! hash_equals((string) $row->value, $key)) {
            abort(404, 'Display link is invalid.');
        }

        $business = Business::find((int) $row->business);
        // A suspended/expired/offboarded tenant's TV goes dark, mirroring
        // EnsureTenantActive on the staff plane.
        if (! $business || ! $business->isSubscribable()) {
            abort(404, 'Display link is invalid.');
        }

        return $this->ok($this->payload($business, $this->announcementFor($business->id)));
    }

    // ==================== display settings (admin) ====================

    public function settings(): JsonResponse
    {
        $this->denyUnless('setting manage');

        $tenantId = $this->tenantId();

        // Auto-provision on first view so the admin always has a copyable
        // link immediately (setting manage holders only; PUT regenerates).
        $key = company_setting(self::KEY_SETTING);
        if (empty($key)) {
            $key = 'tv_'.Str::random(24);
            $this->storeSetting(self::KEY_SETTING, $key, $tenantId);
            comapnySettingCacheForget($tenantId);
        }

        return $this->ok([
            'displayKey' => (string) $key,
            'announcement' => $this->announcementFor($tenantId),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $this->denyUnless('setting manage');

        $tenantId = $this->tenantId();

        $validated = $request->validate([
            'announcement' => ['nullable', 'string', 'max:500'],
            'regenerateKey' => ['sometimes', 'boolean'],
        ]);

        $key = company_setting(self::KEY_SETTING);
        if (empty($key) || ! empty($validated['regenerateKey'])) {
            // Lazily provisioned on first admin view; regenerating kills the
            // old link everywhere instantly (TVs fall back to "invalid link").
            $key = 'tv_'.Str::random(24);
            $this->storeSetting(self::KEY_SETTING, $key, $tenantId);
        }

        if (array_key_exists('announcement', $validated)) {
            $this->storeSetting(self::ANNOUNCEMENT_SETTING, trim((string) $validated['announcement']), $tenantId);
        }

        comapnySettingCacheForget($tenantId);

        return $this->ok([
            'displayKey' => (string) $key,
            'announcement' => $this->announcementFor($tenantId),
        ]);
    }

    // ==================== payload assembly ====================

    /**
     * Today's queue only — a waiting-room board is inherently a "today"
     * surface, so stale check-ins from previous days can never linger.
     */
    private function payload(Business $business, string $announcement): array
    {
        $today = now()->toDateString();
        $tenantId = (int) $business->id;

        $active = Appointment::forClinic($tenantId)
            ->with(['CustomerData', 'ServiceData.modality'])
            ->where('date_sort', $today)
            ->whereIn('workflow_state', [
                StudyState::Booked->value,
                StudyState::CheckedIn->value,
                StudyState::Preparing->value,
                StudyState::InProgress->value,
            ])
            ->orderByRaw("CASE priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
            // Arrived (checked-in) patients precede not-yet-arrived bookings
            // within the same priority band.
            ->orderByRaw("checked_in_at IS NULL ASC")
            ->orderBy('checked_in_at')
            ->orderBy('time')
            ->get();

        // Serving = summoned (called_at) or already inside a suite. A called
        // patient therefore LEAVES "next in line" the moment reception calls
        // them — the display flaw this module rewrite fixes.
        $isServing = fn (Appointment $a) => $a->called_at !== null
            || in_array($a->workflow_state, [StudyState::Preparing->value, StudyState::InProgress->value], true);

        $nowServing = $active->filter($isServing)->values();
        $upNext = $active->reject($isServing)->take(self::UP_NEXT_LIMIT)->values();

        $recentCalls = Appointment::forClinic($tenantId)
            ->with(['CustomerData', 'ServiceData.modality'])
            ->where('date_sort', $today)
            ->whereNotNull('called_at')
            ->whereNotIn('workflow_state', [StudyState::Cancelled->value])
            ->orderByDesc('called_at')
            ->limit(self::RECENT_CALLS_LIMIT)
            ->get();

        $stats = Appointment::forClinic($tenantId)
            ->where('date_sort', $today)
            ->selectRaw("COUNT(CASE WHEN workflow_state IN ('booked','checked_in') AND called_at IS NULL THEN 1 END) AS waiting")
            ->selectRaw("COUNT(CASE WHEN called_at IS NOT NULL OR workflow_state IN ('preparing','in_progress') THEN 1 END) AS serving")
            ->selectRaw("COUNT(CASE WHEN workflow_state IN ('acquired','reading','reported','delivered') THEN 1 END) AS completed")
            ->selectRaw("COUNT(CASE WHEN workflow_state = 'no_show' THEN 1 END) AS no_show")
            ->first();

        return [
            'serverTime' => now()->toIso8601String(),
            'businessName' => $this->displayNameFor($business),
            'announcement' => $announcement,
            'zones' => $this->zones($tenantId, $active),
            'nowServing' => $nowServing->map(fn ($a) => ApiShape::queueEntry($a))->all(),
            'upNext' => $upNext->map(fn ($a) => ApiShape::queueEntry($a))->all(),
            'recentCalls' => $recentCalls->map(fn ($a) => ApiShape::queueEntry($a))->all(),
            'stats' => [
                'waiting' => (int) ($stats->waiting ?? 0),
                'serving' => (int) ($stats->serving ?? 0),
                'completed' => (int) ($stats->completed ?? 0),
                'noShow' => (int) ($stats->no_show ?? 0),
            ],
        ];
    }

    /**
     * Waiting-room zones derive from the tenant's own modality master —
     * never a hardcoded modality list (modalities are an open set).
     */
    private function zones(int $tenantId, $activeStudies): array
    {
        return Modality::forClinic($tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Modality $modality) use ($activeStudies) {
                $mine = $activeStudies->filter(
                    fn (Appointment $a) => (int) ($a->ServiceData?->modality_id ?? 0) === (int) $modality->id
                );

                return [
                    'id' => (int) $modality->id,
                    'code' => (string) $modality->code,
                    'name' => (string) $modality->name,
                    'color' => $modality->color ?: '#0080b6',
                    'waiting' => $mine->filter(
                        fn (Appointment $a) => in_array($a->workflow_state, [StudyState::Booked->value, StudyState::CheckedIn->value], true)
                            && $a->called_at === null
                    )->count(),
                    'serving' => $mine->filter(
                        fn (Appointment $a) => $a->called_at !== null
                            || in_array($a->workflow_state, [StudyState::Preparing->value, StudyState::InProgress->value], true)
                    )->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function announcementFor(int $tenantId): string
    {
        $settings = getCompanyAllSetting(null, $tenantId);

        return trim((string) ($settings[self::ANNOUNCEMENT_SETTING] ?? ''));
    }

    /**
     * The name painted on the TV follows the SAME chain the staff topbar
     * uses (custom white-label brand → clinic profile name → platform
     * default), so the lounge screen can never disagree with the app.
     */
    private function displayNameFor(Business $business): string
    {
        $branding = \App\Services\TenantBrandingService::forTenant($business);
        $appName = trim((string) ($branding['appName'] ?? ''));
        $platformDefault = trim((string) config('ris.platform_brand.app_name'));

        if ($appName !== '' && $appName !== $platformDefault) {
            return $appName;
        }

        $clinicName = trim((string) $business->name);

        return $clinicName !== '' ? $clinicName : $appName;
    }

    private function storeSetting(string $key, string $value, int $businessId): void
    {
        Setting::updateOrCreate(
            ['key' => $key, 'business' => $businessId],
            ['value' => $value, 'created_by' => auth()->id()],
        );
    }
}
