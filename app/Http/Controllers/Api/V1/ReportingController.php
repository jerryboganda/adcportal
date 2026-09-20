<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudyState;
use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\CriticalFindingLog;
use App\Models\Customer;
use App\Models\RadiologyReport;
use App\Models\ReportMacro;
use App\Models\ReportTemplate;
use App\Models\Service;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\Dictation\TranscriptionException;
use App\Services\Dictation\TranscriptionService;
use App\Services\EntitlementService;
use App\Services\StudyTokenAllocator;
use App\Support\AgeGroup;
use App\Support\ReportStructure;
use App\Support\ReportTemplateResolver;
use App\Services\TenantAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Radiologist reporting module.
 *
 * The reading worklist is served HERE, server-side, paginated and filtered in
 * SQL — a hospital's study table is not a browser collection. Everything on
 * this controller is tenant-scoped and permission-checked; the SPA never
 * receives another clinic's study, template or macro.
 */
class ReportingController extends BaseApiController
{
    /**
     * Study states that belong on a READING worklist. A study is reportable
     * once it has been acquired; booked/checked-in studies are reception's or
     * the technologist's queue, not the radiologist's.
     */
    private const REPORTABLE_STATES = ['acquired', 'reading', 'reported', 'delivered'];

    private const TABS = [
        'assigned', 'unreported', 'in_progress', 'priority',
        'drafts', 'preliminary', 'finalized', 'addenda', 'recent', 'all',
    ];

    public function __construct(private readonly TranscriptionService $transcription)
    {
    }

    // ==================== reading worklist ====================

    public function worklist(Request $request): JsonResponse
    {
        $this->denyUnless('report manage');

        $v = $request->validate([
            'tab' => ['sometimes', 'in:'.implode(',', self::TABS)],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'priority' => ['sometimes', 'in:all,routine,urgent,stat'],
            'modalityId' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', 'in:'.implode(',', self::REPORTABLE_STATES)],
            'reportStatus' => ['sometimes', 'nullable', 'in:not_started,draft,preliminary,final,addendum'],
            'assignee' => ['sometimes', 'nullable', 'string', 'max:40'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'sort' => ['sometimes', 'in:priority,oldest,newest'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $tab = $v['tab'] ?? 'unreported';
        $userId = (int) Auth::id();
        $perPage = (int) ($v['perPage'] ?? 25);
        $page = (int) ($v['page'] ?? 1);

        // Counts intentionally ignore the tab and the free-text search: a
        // tab badge that changes while you type a patient name is noise.
        $countBase = $this->baseQuery($v, includeSearch: false);

        $base = $this->baseQuery($v, includeSearch: true);
        $this->applyTab($base, $tab, $userId);
        $this->applySort($base, $v['sort'] ?? 'priority');

        $total = (clone $base)->count();
        $rows = $base->forPage($page, $perPage)->get();

        return response()->json([
            'data' => [
                'studies' => $rows->map(fn (Appointment $a) => ApiShape::worklistStudy($a))->all(),
                'counts' => $this->counts($countBase, $userId),
                'tab' => $tab,
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'hasMore' => ($page * $perPage) < $total,
            ],
        ]);
    }

    /**
     * A single study, rendered in worklist shape.
     *
     * The dashboard's "Manage" button, global search and the notification
     * centre all hand a study over to the reporting module, but the worklist is
     * paginated and filtered — the row the caller wants is not reliably on the
     * page the SPA happens to hold. Resolving it here also means tenant scope
     * is enforced by the database, never inferred from the caller's id.
     */
    public function study(Appointment $appointment): JsonResponse
    {
        $this->denyUnless('report manage');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        if (! in_array($appointment->workflow_state, self::REPORTABLE_STATES, true)) {
            abort(409, 'That study is not on a reading worklist.');
        }

        return $this->ok([
            'study' => ApiShape::worklistStudy($appointment->fresh([
                'CustomerData', 'ServiceData.modality', 'referrer',
                'assignedRadiologist', 'radiologyReports.author',
            ])),
        ]);
    }

    // ==================== dictation ====================

    /**
     * What dictation can actually do in THIS clinic right now.
     *
     * The editor asks before it records anything: browser speech recognition is
     * always available in a supporting browser but may send audio to a vendor,
     * while the clinic's own self-hosted engine keeps it inside the network.
     * Which one is in use is a clinical/privacy decision, so the server states
     * the facts rather than the frontend guessing.
     */
    public function dictation(): JsonResponse
    {
        $this->denyUnless('report manage');

        $integration = $this->transcription->resolve($this->tenantId());

        return $this->ok([
            'serverProvider' => [
                'available' => $integration !== null,
                'label' => $integration?->name,
                'reason' => $integration === null
                    ? 'No active self-hosted dictation service is configured for this clinic.'
                    : null,
            ],
        ]);
    }

    /**
     * Transcribe one recorded chunk through the clinic's own STT service.
     *
     * The audio is never stored: it lives in memory for this request only, and
     * the response carries the text back to the editor, which inserts it into
     * the field the radiologist is editing. Nothing is signed, saved or
     * finalised here — dictation still has to be reviewed like any typed text.
     */
    public function transcribe(Request $request): JsonResponse
    {
        $this->denyUnlessAny(['report create', 'report edit'], 'report edit');

        $request->validate([
            'audio' => ['required', 'file', 'max:'.(int) (TranscriptionService::MAX_AUDIO_BYTES / 1024)],
            'language' => ['sometimes', 'nullable', 'string', 'max:12'],
            // The study being dictated into. Optional (the editor may not have
            // one open yet) but recorded when present, so the audit trail says
            // WHICH study a dictation session belonged to.
            'appointmentId' => ['sometimes', 'nullable', 'integer'],
        ]);

        $integration = $this->transcription->resolve($this->tenantId());

        if ($integration === null) {
            // 409, not 403: the radiologist is allowed to dictate, this clinic
            // simply has no self-hosted engine — the editor falls back to the
            // browser (or to typing) with an explanation.
            abort(response()->json([
                'message' => 'Self-hosted dictation is not configured for this clinic. Use browser dictation or type the report.',
                'error' => 'dictation.unconfigured',
            ], 409));
        }

        $file = $request->file('audio');

        /*
         * Prefer the container the browser DECLARED over a sniffed one.
         *
         * The declared type is the most specific description available: Safari
         * reports audio/mp4, Chromium audio/webm. Sniffing the bytes instead is
         * actively worse here, because an MP4 audio file sniffs as video/mp4 and
         * engines that route on content type reject it — presenting as "the
         * engine will not accept valid audio". The value only ever reaches the
         * clinic's own engine, as a Content-Type header.
         */
        $declared = (string) ($file->getClientMimeType() ?: '');
        $mime = str_starts_with($declared, 'audio/')
            ? $declared
            : (string) ($file->getMimeType() ?: 'audio/webm');

        try {
            $result = $this->transcription->transcribe(
                $integration,
                (string) file_get_contents($file->getRealPath()),
                (string) ($file->getClientOriginalName() ?: 'dictation.webm'),
                $mime,
                $request->input('language'),
            );
        } catch (TranscriptionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'dictation.failed',
            ], 502);
        }

        // The study, when one was named — resolved inside the tenant, so an id
        // from another clinic simply does not resolve rather than attaching a
        // dictation event to someone else's record.
        $study = $request->filled('appointmentId')
            ? Appointment::where('business_id', $this->tenantId())->find($request->input('appointmentId'))
            : null;

        // Audited without content: that dictation happened, for which study,
        // through which engine, and how long it took. The audio and its
        // transcript are not recorded — the saved draft is the clinical record.
        $this->audit('report_dictation', $study ?? $integration, [
            'summary' => 'Dictation transcribed through '.$integration->name,
            'appointmentId' => $study?->id,
            'latencyMs' => $result['latencyMs'],
            'characters' => mb_strlen($result['text']),
        ]);

        return $this->ok([
            'text' => $result['text'],
            'provider' => $result['provider'],
            'latencyMs' => $result['latencyMs'],
        ]);
    }

    /** @param array<string,mixed> $filters */
    private function baseQuery(array $filters, bool $includeSearch = true)
    {
        $query = Appointment::query()
            ->where('appointments.business_id', $this->tenantId())
            ->whereIn('appointments.workflow_state', self::REPORTABLE_STATES)
            ->with([
                'CustomerData',
                'ServiceData.modality',
                'referrer',
                'assignedRadiologist',
                'radiologyReports.author',
            ]);

        if (! empty($filters['modalityId'])) {
            $query->whereHas('ServiceData', fn ($q) => $q->where('modality_id', (int) $filters['modalityId']));
        }

        if (! empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('appointments.priority', $filters['priority']);
        }

        if (! empty($filters['status'])) {
            $query->where('appointments.workflow_state', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->where('appointments.date_sort', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('appointments.date_sort', '<=', $filters['to']);
        }

        if (! empty($filters['assignee'])) {
            match ($filters['assignee']) {
                'me' => $query->where('appointments.assigned_radiologist_id', Auth::id()),
                'unassigned' => $query->whereNull('appointments.assigned_radiologist_id'),
                default => ctype_digit((string) $filters['assignee'])
                    ? $query->where('appointments.assigned_radiologist_id', (int) $filters['assignee'])
                    : null,
            };
        }

        if (! empty($filters['reportStatus'])) {
            $this->applyReportStatusFilter($query, $filters['reportStatus']);
        }

        if ($includeSearch && ! empty($filters['q'])) {
            $this->applySearch($query, (string) $filters['q']);
        }

        return $query;
    }

    /**
     * Portable LIKE escaping.
     *
     * A patient literally named "100%" must not turn into a wildcard. PostgreSQL
     * happens to default `LIKE`'s escape character to a backslash, SQLite has no
     * default at all — so the escape character is declared EXPLICITLY in every
     * pattern, which is the only spelling both engines agree on.
     */
    private static function escapedLike(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }

    /**
     * `LOWER(column) LIKE ? ESCAPE '\'` — portable AND case-insensitive.
     *
     * PostgreSQL's `LIKE` is case-SENSITIVE (only `ILIKE` folds case) while
     * SQLite's `LIKE` is not, so a radiologist typing "bilal" would find
     * nothing in production while the SQLite test suite reported success.
     * Folding both sides explicitly behaves identically on both engines.
     */
    private function likeRaw($query, string $column, string $like, string $boolean = 'and'): void
    {
        $query->whereRaw('LOWER('.$column.") LIKE ? ESCAPE '\\'", [mb_strtolower($like)], $boolean);
    }

    /**
     * Also note the explicit `CAST(... AS TEXT)`: PostgreSQL has no
     * `integer LIKE text`, so a patient searching for a token number needs the
     * cast on both engines.
     */
    private function applySearch($query, string $term): void
    {
        $like = self::escapedLike($term);

        $query->where(function ($outer) use ($like, $term) {
            $this->likeRaw($outer, 'appointments.name', $like);

            $outer->orWhereHas('CustomerData', function ($c) use ($like) {
                $c->where(function ($x) use ($like) {
                    $this->likeRaw($x, 'name', $like);
                    $this->likeRaw($x, 'mrn', $like, 'or');
                });
            });

            $outer->orWhereHas('ServiceData', function ($s) use ($like) {
                $this->likeRaw($s, 'name', $like);
            });

            if (ctype_digit($term)) {
                $this->likeRaw($outer, 'CAST(appointments.token_number AS TEXT)', $like, 'or');
            }
        });
    }

    private function applyReportStatusFilter($query, string $status): void
    {
        match ($status) {
            'not_started' => $query->whereDoesntHave('radiologyReports'),
            'draft' => $query->whereHas('radiologyReports', fn ($r) => $r->whereNull('locked_at')),
            'preliminary' => $query->whereHas('radiologyReports', fn ($r) => $r->where('type', 'preliminary')->whereNotNull('locked_at')),
            'final' => $query->whereHas('radiologyReports', fn ($r) => $r->whereNotNull('locked_at')->whereIn('type', ['final'])),
            'addendum' => $query->whereHas('radiologyReports', fn ($r) => $r->where('type', 'addendum')),
            default => null,
        };
    }

    private function applyTab($query, string $tab, int $userId): void
    {
        match ($tab) {
            'assigned' => $query
                ->where('appointments.assigned_radiologist_id', $userId)
                ->whereIn('appointments.workflow_state', ['acquired', 'reading']),
            'unreported' => $query->whereIn('appointments.workflow_state', ['acquired', 'reading']),
            'in_progress' => $query->where('appointments.workflow_state', 'reading'),
            'priority' => $query
                ->whereIn('appointments.priority', ['stat', 'urgent'])
                ->whereIn('appointments.workflow_state', ['acquired', 'reading']),
            'drafts' => $query->whereHas(
                'radiologyReports',
                fn ($r) => $r->whereNull('locked_at')->where('authored_by', $userId)
            ),
            'preliminary' => $query->whereHas(
                'radiologyReports',
                fn ($r) => $r->where('type', 'preliminary')->whereNotNull('locked_at')
            ),
            'finalized' => $query->whereIn('appointments.workflow_state', ['reported', 'delivered']),
            'addenda' => $query->whereHas('radiologyReports', fn ($r) => $r->where('type', 'addendum')),
            'recent' => $query->whereHas('radiologyReports'),
            default => null, // 'all'
        };
    }

    private function applySort($query, string $sort): void
    {
        // `acquired_at IS NULL` first: a study with a real acquisition stamp
        // always outranks one without, on both PostgreSQL and SQLite.
        $query->orderByRaw("CASE appointments.priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END");

        if ($sort === 'newest') {
            $query->orderByDesc('appointments.acquired_at')->orderByDesc('appointments.id');

            return;
        }

        $query->orderByRaw('appointments.acquired_at IS NULL ASC')
            ->orderBy('appointments.acquired_at')
            ->orderBy('appointments.id');
    }

    /** @return array<string,int> */
    private function counts($base, int $userId): array
    {
        $stateTally = (clone $base)
            ->reorder()
            ->selectRaw('appointments.workflow_state as state, COUNT(*) as total')
            ->groupBy('appointments.workflow_state')
            ->pluck('total', 'state');

        $unreported = (int) (($stateTally['acquired'] ?? 0) + ($stateTally['reading'] ?? 0));
        $finalized = (int) (($stateTally['reported'] ?? 0) + ($stateTally['delivered'] ?? 0));

        return [
            'assigned' => (clone $base)->reorder()->where('appointments.assigned_radiologist_id', $userId)
                ->whereIn('appointments.workflow_state', ['acquired', 'reading'])->count(),
            'unreported' => $unreported,
            'in_progress' => (int) ($stateTally['reading'] ?? 0),
            'priority' => (clone $base)->reorder()->whereIn('appointments.priority', ['stat', 'urgent'])
                ->whereIn('appointments.workflow_state', ['acquired', 'reading'])->count(),
            'drafts' => (clone $base)->reorder()->whereHas(
                'radiologyReports',
                fn ($r) => $r->whereNull('locked_at')->where('authored_by', $userId)
            )->count(),
            'preliminary' => (clone $base)->reorder()->whereHas(
                'radiologyReports',
                fn ($r) => $r->where('type', 'preliminary')->whereNotNull('locked_at')
            )->count(),
            'finalized' => $finalized,
            'addenda' => (clone $base)->reorder()
                ->whereHas('radiologyReports', fn ($r) => $r->where('type', 'addendum'))->count(),
            'recent' => (clone $base)->reorder()->whereHas('radiologyReports')->count(),
            'all' => $unreported + $finalized,
            'stat' => (clone $base)->reorder()->where('appointments.priority', 'stat')
                ->whereIn('appointments.workflow_state', ['acquired', 'reading'])->count(),
        ];
    }

    // ==================== template resolution & library ====================

    public function templates(Request $request): JsonResponse
    {
        $this->denyUnless('report manage');

        $v = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'modalityId' => ['sometimes', 'nullable', 'integer'],
            'serviceId' => ['sometimes', 'nullable', 'integer'],
            'includeArchived' => ['sometimes', 'boolean'],
            'mine' => ['sometimes', 'boolean'],
        ]);

        $query = ReportTemplate::forClinic($this->tenantId())
            ->usableBy((int) Auth::id())
            ->with(['modality', 'serviceData', 'author'])
            ->orderBy('name');

        if (empty($v['includeArchived'])) {
            $query->active();
        }

        if (! empty($v['modalityId'])) {
            $query->where('modality_id', (int) $v['modalityId']);
        }

        if (! empty($v['serviceId'])) {
            $query->where(fn ($q) => $q->where('service_id', (int) $v['serviceId'])->orWhereNull('service_id'));
        }

        if (! empty($v['mine'])) {
            $query->where('created_by', Auth::id());
        }

        if (! empty($v['q'])) {
            $like = self::escapedLike((string) $v['q']);
            $query->where(function ($q) use ($like) {
                $this->likeRaw($q, 'name', $like);
                $this->likeRaw($q, 'code', $like, 'or');
                $this->likeRaw($q, 'body_region', $like, 'or');
            });
        }

        return $this->ok([
            'templates' => $query->limit(300)->get()->map(fn ($t) => ApiShape::reportTemplate($t))->all(),
            'ageGroups' => AgeGroup::labels(),
        ]);
    }

    /**
     * Explain which template a study resolves to (and why), without saving
     * anything. The SPA calls this when a study is opened.
     */
    public function resolveTemplate(Request $request): JsonResponse
    {
        $this->denyUnless('report manage');

        $v = $request->validate([
            'appointmentId' => ['sometimes', 'nullable', 'integer'],
            'serviceId' => ['sometimes', 'nullable', 'integer'],
            'modalityId' => ['sometimes', 'nullable', 'integer'],
            'bodyRegion' => ['sometimes', 'nullable', 'string', 'max:60'],
            'ageGroup' => ['sometimes', 'nullable', 'string', 'max:20'],
            'sex' => ['sometimes', 'nullable', 'in:male,female'],
            'contrast' => ['sometimes', 'nullable', 'in:with,without,both'],
        ]);

        if (! empty($v['appointmentId'])) {
            $appointment = Appointment::where('business_id', $this->tenantId())
                ->with(['ServiceData.modality', 'CustomerData', 'doseLog'])
                ->findOrFail($v['appointmentId']);

            $match = ReportTemplateResolver::forAppointment($appointment, (int) Auth::id());
        } else {
            $service = ! empty($v['serviceId'])
                ? Service::forClinic($this->tenantId())->findOrFail($v['serviceId'])
                : null;

            $match = ReportTemplateResolver::resolve(
                tenantId: $this->tenantId(),
                serviceId: $service?->id,
                modalityId: $service?->modality_id ?? ($v['modalityId'] ?? null),
                bodyRegion: $v['bodyRegion'] ?? $service?->body_region,
                ageGroup: $v['ageGroup'] ?? null,
                sex: $v['sex'] ?? null,
                contrast: $v['contrast'] ?? ($service && $service->contrast_type !== 'none' ? 'with' : null),
                userId: (int) Auth::id(),
            );
        }

        return $this->ok([
            'matched' => $match['template'] ? ApiShape::reportTemplate($match['template']) : null,
            'tier' => $match['tier'],
            'ageGroup' => $match['ageGroup'],
            'ageGroupLabel' => AgeGroup::label($match['ageGroup']),
            'candidates' => $match['candidates'],
            'explanation' => $match['explanation'],
        ]);
    }

    // ==================== macros (snippet library) ====================

    public function macros(Request $request): JsonResponse
    {
        $this->denyUnless('report manage');

        $v = $request->validate([
            'modalityId' => ['sometimes', 'nullable', 'integer'],
            'serviceId' => ['sometimes', 'nullable', 'integer'],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $query = ReportMacro::forClinic($this->tenantId())
            ->usableBy((int) Auth::id())
            ->active()
            ->with('author')
            ->orderByDesc('usage_count')
            ->orderBy('name');

        if (! empty($v['modalityId'])) {
            $query->where(fn ($q) => $q->whereNull('modality_id')->orWhere('modality_id', (int) $v['modalityId']));
        }

        if (! empty($v['serviceId'])) {
            $query->where(fn ($q) => $q->whereNull('service_id')->orWhere('service_id', (int) $v['serviceId']));
        }

        if (! empty($v['q'])) {
            $like = self::escapedLike((string) $v['q']);
            $query->where(function ($q) use ($like) {
                $this->likeRaw($q, 'name', $like);
                $this->likeRaw($q, 'shortcut', $like, 'or');
                $this->likeRaw($q, 'findings', $like, 'or');
                $this->likeRaw($q, 'impression', $like, 'or');
            });
        }

        return $this->ok(['macros' => $query->limit(200)->get()->map(fn ($m) => ApiShape::reportMacro($m))->all()]);
    }

    public function storeMacro(Request $request): JsonResponse
    {
        $validated = $this->validatedMacro($request);

        // A macro is clinical content: editing the tenant's shared library
        // needs the same authority as editing a report template.
        if (($validated['scope'] ?? 'personal') === 'tenant') {
            $this->denyUnless('report template edit');
        } else {
            $this->denyUnless('report edit');
        }

        $macro = ReportMacro::create([
            ...collect($validated)->only([
                'name', 'shortcut', 'findings', 'impression', 'recommendations', 'scope',
            ])->all(),
            'modality_id' => $validated['modalityId'] ?? null,
            'service_id' => $validated['serviceId'] ?? null,
            'business_id' => $this->tenantId(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->audit('report_macro_saved', $macro, ['summary' => "Saved reporting macro \"{$macro->name}\""]);

        return response()->json(['data' => ['macro' => ApiShape::reportMacro($macro)]], 201);
    }

    public function updateMacro(Request $request, ReportMacro $macro): JsonResponse
    {
        $this->guardMacro($macro);

        $validated = $this->validatedMacro($request);

        $macro->update([
            ...collect($validated)->only([
                'name', 'shortcut', 'findings', 'impression', 'recommendations', 'scope',
            ])->all(),
            'modality_id' => $validated['modalityId'] ?? null,
            'service_id' => $validated['serviceId'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        return $this->ok(['macro' => ApiShape::reportMacro($macro->fresh())]);
    }

    /** Archive rather than hard-delete: reports already drafted from a macro keep their provenance. */
    public function destroyMacro(ReportMacro $macro): JsonResponse
    {
        $this->guardMacro($macro);

        $macro->forceFill(['is_archived' => true, 'updated_by' => Auth::id()])->save();

        $this->audit('report_macro_archived', $macro, ['summary' => "Archived reporting macro \"{$macro->name}\""]);

        return $this->ok(['archived' => true]);
    }

    public function useMacro(ReportMacro $macro): JsonResponse
    {
        // USING a snippet is ordinary reporting; curating the library is not.
        $this->denyUnless('report manage');
        $this->guardMacroTenant($macro);

        $macro->increment('usage_count');

        return $this->ok(['usageCount' => (int) $macro->fresh()->usage_count]);
    }

    private function guardMacroTenant(ReportMacro $macro): void
    {
        if ($macro->business_id !== $this->tenantId()) {
            abort(404);
        }
    }

    private function guardMacro(ReportMacro $macro): void
    {
        $this->guardMacroTenant($macro);

        $mine = (int) $macro->created_by === (int) Auth::id();

        // A shared macro belongs to the clinic; a personal one to its author.
        $this->denyUnlessAny(
            $mine ? ['report edit', 'report template edit'] : ['report template edit'],
            'report macro manage',
        );
    }

    private function validatedMacro(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'shortcut' => ['nullable', 'string', 'max:40'],
            'modalityId' => ['nullable', 'integer'],
            'serviceId' => ['nullable', 'integer'],
            'findings' => ['nullable', 'string', 'max:8000'],
            'impression' => ['nullable', 'string', 'max:8000'],
            'recommendations' => ['nullable', 'string', 'max:4000'],
            'scope' => ['sometimes', 'in:tenant,personal'],
        ]);
    }

    // ==================== report search & history ====================

    public function reportSearch(Request $request): JsonResponse
    {
        $this->denyUnless('report manage');

        $v = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'modalityId' => ['sometimes', 'nullable', 'integer'],
            'radiologistId' => ['sometimes', 'nullable', 'integer'],
            'patientId' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', 'in:draft,preliminary,final,addendum'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($v['perPage'] ?? 20);
        $page = (int) ($v['page'] ?? 1);

        $query = RadiologyReport::query()
            ->where('radiology_reports.business_id', $this->tenantId())
            ->join('appointments', 'appointments.id', '=', 'radiology_reports.appointment_id')
            ->where('appointments.business_id', $this->tenantId())
            ->select('radiology_reports.*')
            ->with([
                'author',
                'appointment.CustomerData',
                'appointment.ServiceData.modality',
                'appointment.assignedRadiologist',
            ])
            ->orderByDesc('radiology_reports.signed_at')
            ->orderByDesc('radiology_reports.id');

        if (! empty($v['status'])) {
            $query->where('radiology_reports.type', $v['status']);
        }

        if (! empty($v['radiologistId'])) {
            $query->where(fn ($q) => $q->where('radiology_reports.authored_by', (int) $v['radiologistId'])
                ->orWhere('radiology_reports.signed_by', (int) $v['radiologistId']));
        }

        if (! empty($v['patientId'])) {
            $query->where('appointments.customer_id', (int) $v['patientId']);
        }

        if (! empty($v['modalityId'])) {
            $query->whereHas('appointment.ServiceData', fn ($q) => $q->where('modality_id', (int) $v['modalityId']));
        }

        if (! empty($v['from'])) {
            $query->where('appointments.date_sort', '>=', $v['from']);
        }

        if (! empty($v['to'])) {
            $query->where('appointments.date_sort', '<=', $v['to']);
        }

        if (! empty($v['q'])) {
            $term = (string) $v['q'];
            $like = self::escapedLike($term);

            $query->where(function ($outer) use ($like, $term) {
                $this->likeRaw($outer, 'radiology_reports.impression', $like);
                $this->likeRaw($outer, 'radiology_reports.findings', $like, 'or');
                $this->likeRaw($outer, 'radiology_reports.clinical_history', $like, 'or');

                $outer->orWhereHas('appointment.CustomerData', function ($c) use ($like) {
                    $c->where(function ($x) use ($like) {
                        $this->likeRaw($x, 'name', $like);
                        $this->likeRaw($x, 'mrn', $like, 'or');
                    });
                });

                if (ctype_digit($term)) {
                    $this->likeRaw($outer, 'CAST(appointments.token_number AS TEXT)', $like, 'or');
                }
            });
        }

        $total = (clone $query)->count();

        return $this->ok([
            'reports' => $query->forPage($page, $perPage)->get()
                ->map(fn (RadiologyReport $r) => ApiShape::reportHistoryRow($r, $r->appointment))
                ->all(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'hasMore' => ($page * $perPage) < $total,
        ]);
    }

    /** Prior examinations + their reports for the same patient (longitudinal record). */
    public function priors(Appointment $appointment): JsonResponse
    {
        $this->denyUnless('report manage');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        if (empty($appointment->customer_id)) {
            return $this->ok(['priors' => []]);
        }

        $priors = Appointment::query()
            ->where('business_id', $this->tenantId())
            ->where('customer_id', $appointment->customer_id)
            ->where('id', '!=', $appointment->id)
            ->whereNull('deleted_at')
            ->with(['ServiceData.modality', 'CustomerData', 'radiologyReports.author', 'radiologyReports.signer'])
            ->orderByDesc('date_sort')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return $this->ok([
            'priors' => $priors->map(function (Appointment $prior) {
                $latest = $prior->radiologyReports->first();

                return [
                    'study' => ApiShape::worklistStudy($prior),
                    'report' => $latest ? [
                        'id' => ApiShape::id($latest->id),
                        'type' => $latest->type,
                        'statusLabel' => $latest->statusLabel(),
                        'signedAt' => $latest->signed_at ? ApiShape::dateTime($latest->signed_at) : null,
                        'signedBy' => optional($latest->signer)->name,
                        'impression' => (string) ($latest->impression ?? ''),
                        'findings' => (string) ($latest->findings ?? ''),
                        'comparison' => (string) ($latest->comparison ?? ''),
                    ] : null,
                ];
            })->all(),
        ]);
    }

    // ==================== critical-result communication ====================

    public function criticalFindings(Appointment $appointment): JsonResponse
    {
        $this->denyUnless('report manage');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        $logs = CriticalFindingLog::forClinic($this->tenantId())
            ->where('appointment_id', $appointment->id)
            ->with('communicator')
            ->orderByDesc('communicated_at')
            ->get();

        return $this->ok(['logs' => $logs->map(fn ($l) => ApiShape::criticalFindingLog($l))->all()]);
    }

    public function storeCriticalFinding(Request $request, Appointment $appointment): JsonResponse
    {
        // Recording that you telephoned a clinician about a life-threatening
        // finding is a signing radiologist's act.
        $this->denyUnless('report sign');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        $v = $request->validate([
            'summary' => ['required', 'string', 'max:2000'],
            'notifiedTo' => ['required', 'string', 'max:160'],
            'notifiedRole' => ['nullable', 'string', 'max:80'],
            'contact' => ['nullable', 'string', 'max:80'],
            'method' => ['required', 'in:phone,in_person,sms,email,portal'],
            'readBackVerified' => ['sometimes', 'boolean'],
            'adviceGiven' => ['nullable', 'string', 'max:2000'],
            'reportId' => ['sometimes', 'nullable', 'integer'],
        ]);

        $reportId = null;
        if (! empty($v['reportId'])) {
            $report = RadiologyReport::where('business_id', $this->tenantId())->findOrFail($v['reportId']);
            if ((int) $report->appointment_id !== (int) $appointment->id) {
                abort(422, 'That report belongs to a different study.');
            }
            $reportId = $report->id;
        }

        $log = CriticalFindingLog::create([
            'appointment_id' => $appointment->id,
            'report_id' => $reportId,
            'summary' => $v['summary'],
            'notified_to' => $v['notifiedTo'],
            'notified_role' => $v['notifiedRole'] ?? null,
            'contact' => $v['contact'] ?? null,
            'method' => $v['method'],
            'read_back_verified' => (bool) ($v['readBackVerified'] ?? false),
            'advice_given' => $v['adviceGiven'] ?? null,
            'communicated_at' => now(),
            'communicated_by' => Auth::id(),
            'business_id' => $this->tenantId(),
            'created_by' => Auth::id(),
        ]);

        if ($reportId) {
            RadiologyReport::whereKey($reportId)->update([
                'critical_acked_at' => now(),
                'critical_acked_by' => Auth::id(),
            ]);
        }

        $this->notify([
            'title' => '🚨 CRITICAL RESULT COMMUNICATED',
            'message' => "Critical finding for {$appointment->patientDisplayName()} (#{$appointment->token_number}) communicated to {$log->notified_to} by ".strtoupper($log->method).'.',
            'category' => 'stat',
            'priority' => 'critical',
            'appointment_id' => $appointment->id,
            'token_number' => $appointment->token_number,
            'patient_name' => $appointment->patientDisplayName(),
            'target_tab' => 'doctors',
            'action_label' => 'View Critical Log',
        ]);

        $this->audit('critical_finding_communicated', $appointment, [
            'summary' => "Critical result communicated to {$log->notified_to} (read-back: ".($log->read_back_verified ? 'yes' : 'no').')',
            'notified_to' => $log->notified_to,
            'method' => $log->method,
        ]);

        return response()->json(['data' => ['log' => ApiShape::criticalFindingLog($log->load('communicator'))]], 201);
    }

    // ==================== assignment ====================

    public function assign(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        $v = $request->validate([
            'radiologistId' => ['present', 'nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $target = $v['radiologistId'] !== null ? (int) $v['radiologistId'] : null;
        $me = (int) Auth::id();
        $currentAssignee = $appointment->assigned_radiologist_id !== null ? (int) $appointment->assigned_radiologist_id : null;

        // A radiologist may take a study and release their own claim; handing
        // work to a colleague (or off someone else's list) is an administrative
        // act and needs the study-assignment permission.
        $selfService = $target === $me || ($target === null && $currentAssignee === $me);

        if ($selfService) {
            $this->denyUnlessAny(['study assign', 'report edit'], 'report assign');
        } else {
            $this->denyUnless('study assign');
        }

        if (! in_array($appointment->workflow_state, ['acquired', 'reading'], true)) {
            abort(422, 'Only studies awaiting interpretation can be assigned.');
        }

        if ($target !== null) {
            $assignee = User::where('business_id', $this->tenantId())->find($target);

            if (! $assignee || (int) $assignee->active_status !== 1) {
                throw ValidationException::withMessages(['radiologistId' => 'That radiologist is not an active member of this clinic.']);
            }
            if (! TenantAuthorizer::allows($assignee, 'report sign', $this->tenantId())) {
                throw ValidationException::withMessages(['radiologistId' => 'That staff member cannot sign reports.']);
            }
        }

        $previous = $appointment->assigned_radiologist_id;
        $appointment->forceFill(['assigned_radiologist_id' => $target])->save();

        $this->audit('radiologist_assigned', $appointment, [
            'summary' => $target
                ? "Assigned {$appointment->patientDisplayName()} (#{$appointment->token_number}) to ".optional(User::find($target))->name
                : "Unassigned {$appointment->patientDisplayName()} (#{$appointment->token_number})",
            'from' => $previous,
            'to' => $target,
        ]);

        if ($target !== null && $target !== $me) {
            $this->notify([
                'title' => 'Study Assigned for Reporting',
                'message' => "{$appointment->patientDisplayName()} (#{$appointment->token_number}) has been assigned to you for reporting.",
                'category' => 'workflow',
                'priority' => 'normal',
                'appointment_id' => $appointment->id,
                'token_number' => $appointment->token_number,
                'patient_name' => $appointment->patientDisplayName(),
                'target_tab' => 'reporting',
                'target_user_id' => $target,
                'action_label' => 'Open Reporting',
            ]);
        }

        return $this->ok([
            'study' => ApiShape::worklistStudy(
                $appointment->fresh(['CustomerData', 'ServiceData.modality', 'referrer', 'assignedRadiologist', 'radiologyReports'])
            ),
        ]);
    }

    /** Radiologists who may receive a reading assignment (no `user manage` needed). */
    public function roster(): JsonResponse
    {
        $this->denyUnless('report manage');

        $tenantId = $this->tenantId();

        $staff = User::where('business_id', $tenantId)
            ->where('type', '!=', 'customer')
            ->where('active_status', 1)
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->filter(fn (User $u) => TenantAuthorizer::allows($u, 'report sign', $tenantId))
            ->map(fn (User $u) => [
                'id' => ApiShape::id($u->id),
                'name' => $u->name,
                'role' => $u->portalRole(),
                'department' => (string) ($u->department ?? ''),
            ])
            ->values();

        return $this->ok(['radiologists' => $staff->all()]);
    }

    // ==================== manual / external report creation ====================

    /**
     * "Create New Report" — for external, imported or offline studies that
     * never went through scheduling.
     *
     * The report is NOT attached to a fake appointment: a real, explicitly
     * marked study record (`origin = manual`, state `acquired`) is created in
     * the same transaction, so DICOM identifiers, the patient's longitudinal
     * record and the token sequence all stay coherent.
     */
    public function storeManualReport(Request $request): JsonResponse
    {
        $this->denyUnless('report create');

        if ($business = Business::find($this->tenantId())) {
            EntitlementService::enforce($business, 'studies', 'monthly study volume');
        }

        $validated = $request->validate([
            'patientId' => ['nullable', 'integer'],
            'newPatient' => ['nullable', 'array'],
            'newPatient.name' => ['required_with:newPatient', 'string', 'max:255'],
            'newPatient.phone' => ['nullable', 'string', 'max:40'],
            'newPatient.email' => ['nullable', 'email', 'max:255'],
            'newPatient.age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'newPatient.gender' => ['nullable', 'in:male,female,other'],
            'newPatient.bloodGroup' => ['nullable', 'string', 'max:8'],

            'serviceId' => ['required', 'integer'],
            'referrerId' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'priority' => ['required', 'in:routine,urgent,stat'],
            // Date the external examination was performed, when it differs
            // from the date it is being reported (defaults to `date`).
            'studyDate' => ['nullable', 'date_format:Y-m-d'],
            'indication' => ['nullable', 'string', 'max:2000'],

            'templateId' => ['nullable', 'integer'],
            'technique' => ['nullable', 'string', 'max:5000'],
            'comparison' => ['nullable', 'string', 'max:2000'],
            'findings' => ['nullable', 'string'],
            'impression' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string', 'max:3000'],
            'criticalFlag' => ['sometimes', 'boolean'],
            'structuredValues' => ['sometimes', 'array'],
            'signNow' => ['sometimes', 'boolean'],
            'signAs' => ['sometimes', 'in:final,preliminary'],
        ]);

        if (empty($validated['patientId']) && empty($validated['newPatient'])) {
            throw ValidationException::withMessages([
                'patientId' => 'Select an existing patient or supply the demographics for a new one.',
            ]);
        }

        if (! empty($validated['signNow'])) {
            $this->denyUnless('report sign');

            if (empty(trim((string) ($validated['impression'] ?? '')))) {
                throw ValidationException::withMessages(['impression' => 'An impression is required to finalize a report.']);
            }
        }

        $service = Service::forClinic($this->tenantId())->with('modality')->findOrFail($validated['serviceId']);

        $template = null;
        if (! empty($validated['templateId'])) {
            $template = ReportTemplate::forClinic($this->tenantId())->findOrFail($validated['templateId']);
        }

        [$structuredValues, $structureErrors] = ReportStructure::validateValues(
            $template?->structured_fields,
            $validated['structuredValues'] ?? null,
        );

        if ($structureErrors !== []) {
            throw ValidationException::withMessages(
                collect($structureErrors)->mapWithKeys(fn ($msg, $key) => ["structuredValues.{$key}" => $msg])->all()
            );
        }

        [$appointment, $report] = DB::transaction(function () use ($validated, $service, $template, $structuredValues) {
            // Patient identity goes through the SAME registration path as
            // booking/reception — never a reporting-only mini registry.
            $customer = ! empty($validated['patientId'])
                ? Customer::where('business_id', $this->tenantId())->findOrFail($validated['patientId'])
                : Customer::register($validated['newPatient'], $this->tenantId(), Auth::id());

            $studyDate = $validated['studyDate'] ?? $validated['date'];

            $appointment = Appointment::create([
                'customer_id' => $customer->user_id,
                'name' => $customer->name,
                'email' => $customer->email,
                'contact' => $customer->phone,
                'service_id' => $service->id,
                'referrer_id' => $validated['referrerId'] ?? null,
                'date' => $studyDate,
                'time' => now()->format('H:i:s'),
                'priority' => $validated['priority'],
                // The examination already happened elsewhere; it enters the
                // pipeline directly at "acquired" (awaiting interpretation).
                'workflow_state' => StudyState::Acquired->value,
                'acquired_at' => now(),
                'origin' => 'manual',
                'screening_required' => false,
                'screening_cleared' => true,
                'notes' => $validated['indication'] ?? null,
                'business_id' => $this->tenantId(),
                'created_by' => Auth::id(),
            ]);

            StudyTokenAllocator::assignTo($appointment, $studyDate);

            $appointment->forceFill([
                'assigned_radiologist_id' => Auth::id(),
            ])->save();

            $report = RadiologyReport::create([
                'appointment_id' => $appointment->id,
                'version' => 1,
                'type' => 'draft',
                'clinical_history' => $validated['indication'] ?? null,
                'technique' => $validated['technique'] ?? null,
                'comparison' => $validated['comparison'] ?? null,
                'findings' => $validated['findings'] ?? null,
                'impression' => $validated['impression'] ?? null,
                'recommendations' => $validated['recommendations'] ?? null,
                'critical_flag' => (bool) ($validated['criticalFlag'] ?? false),
                'template_id' => $template?->id,
                'template_version' => $template?->version,
                'structured_values' => $structuredValues ?: null,
                'authored_by' => Auth::id(),
                'business_id' => $this->tenantId(),
                'created_by' => Auth::id(),
            ]);

            // Sign-off belongs to the SAME unit of work: a manual report can
            // never be left half-created because signing failed afterwards.
            if (! empty($validated['signNow'])) {
                app(ReportController::class)->signReport($report, $validated['signAs'] ?? 'final');
            }

            UsageCounter::add($this->tenantId(), 'studies');

            return [$appointment, $report];
        });

        $this->audit('report_created_manual', $appointment, [
            'summary' => "Created a manual report for {$appointment->patientDisplayName()} ({$service->name})",
            'report_id' => $report->id,
            'service_id' => $service->id,
        ]);

        return response()->json([
            'data' => [
                'study' => ApiShape::appointment($appointment->fresh(StudyController::eager())),
                'report' => ApiShape::radiologyReport($report->fresh(['author', 'releases'])),
            ],
        ], 201);
    }
}
