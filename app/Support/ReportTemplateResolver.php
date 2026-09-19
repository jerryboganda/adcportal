<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\ReportTemplate;

/**
 * Deterministic radiology report template resolution.
 *
 * The chain is explicit, monotonically less specific, and every step is
 * TENANT-SCOPED (a template belonging to another clinic is never a candidate,
 * whatever the client asks for):
 *
 *   1. procedure + age band              (exact procedure, exact cohort)
 *   2. procedure                         (exact procedure, age-agnostic)
 *   3. modality + body region + band
 *   4. modality + body region            (age-agnostic variant of that region)
 *   5. modality + age band               (region-AGNOSTIC template only)
 *   6. modality                          (region-agnostic, age-agnostic)
 *   7. clinic default template
 *   8. none  → blank structured report
 *
 * Three deliberate safety rules:
 *
 *  - An age band NEVER falls back to a DIFFERENT band. A neonatal head
 *    ultrasound with no neonatal template resolves to the age-agnostic or
 *    region-agnostic template — never to the "adult" variant, which would
 *    present adult findings as a drafting aid for a neonate.
 *  - A template authored for a DIFFERENT body region is never served. Steps
 *    5/6 only consider templates whose `body_region` is null (explicitly
 *    modality-wide), so a chest template can never arrive on a brain study.
 *  - A sex-specific template is never served to a patient of another (or
 *    unknown) sex.
 *
 * A resolved baseline is a DRAFTING AID. Nothing here finalizes anything.
 */
final class ReportTemplateResolver
{
    /** @return array{template: ?ReportTemplate, tier: string, ageGroup: ?string, candidates: int, explanation: string} */
    public static function forAppointment(Appointment $appointment, ?int $userId = null): array
    {
        $service = $appointment->relationLoaded('ServiceData') ? $appointment->ServiceData : $appointment->ServiceData()->first();
        $patient = $appointment->relationLoaded('CustomerData') ? $appointment->CustomerData : $appointment->CustomerData()->first();
        $doseLog = $appointment->relationLoaded('doseLog') ? $appointment->doseLog : $appointment->doseLog()->first();

        $sex = in_array($patient?->gender, ['male', 'female'], true) ? $patient->gender : null;
        $ageGroup = AgeGroup::for($patient?->dob, $patient?->age);

        $contrast = null;
        if ($service?->contrast_type === 'none') {
            $contrast = 'without';
        } elseif (! empty($service?->contrast_type)) {
            $contrast = 'with';
        } elseif (! empty($doseLog?->contrast_agent)) {
            $contrast = 'with';
        }

        return self::resolve(
            tenantId: (int) $appointment->business_id,
            serviceId: $appointment->service_id ? (int) $appointment->service_id : null,
            modalityId: $service?->modality_id ? (int) $service->modality_id : null,
            bodyRegion: $service?->body_region,
            ageGroup: $ageGroup,
            sex: $sex,
            contrast: $contrast,
            userId: $userId,
        );
    }

    /** @return array{template: ?ReportTemplate, tier: string, ageGroup: ?string, candidates: int, explanation: string} */
    public static function resolve(
        int $tenantId,
        ?int $serviceId,
        ?int $modalityId,
        ?string $bodyRegion,
        ?string $ageGroup,
        ?string $sex,
        ?string $contrast,
        ?int $userId = null,
    ): array {
        $ageGroup = AgeGroup::isValid($ageGroup) ? $ageGroup : null;
        $bodyRegion = trim((string) $bodyRegion) !== '' ? trim((string) $bodyRegion) : null;

        // [column, operator, value]. `null` (IS NULL) is an explicit operator
        // rather than a sentinel value, so "region is unknown → skip this
        // step" cannot be confused with "template must be region-agnostic".
        $attempts = [
            ['tier' => 'procedure_age', 'constraints' => [['service_id', 'eq', $serviceId], ['age_group', 'eq', $ageGroup]]],
            ['tier' => 'procedure', 'constraints' => [['service_id', 'eq', $serviceId], ['age_group', 'isnull', null]]],
            ['tier' => 'region_age', 'constraints' => [['modality_id', 'eq', $modalityId], ['body_region', 'eq', $bodyRegion], ['age_group', 'eq', $ageGroup]]],
            ['tier' => 'region', 'constraints' => [['modality_id', 'eq', $modalityId], ['body_region', 'eq', $bodyRegion], ['age_group', 'isnull', null]]],
            ['tier' => 'modality_age', 'constraints' => [['modality_id', 'eq', $modalityId], ['body_region', 'isnull', null], ['age_group', 'eq', $ageGroup]]],
            ['tier' => 'modality', 'constraints' => [['modality_id', 'eq', $modalityId], ['body_region', 'isnull', null], ['age_group', 'isnull', null]]],
            ['tier' => 'tenant_default', 'constraints' => [['is_default', 'eq', true]]],
        ];

        $totalCandidates = 0;

        foreach ($attempts as $attempt) {
            $constraints = [];
            $skip = false;

            foreach ($attempt['constraints'] as [$column, $operator, $value]) {
                if ($operator === 'eq' && ($value === null || $value === false)) {
                    // This clinical dimension is unknown for this study, so the
                    // step does not apply at all.
                    $skip = true;
                    break;
                }

                $constraints[] = [$column, $operator, $value];
            }

            if ($skip || $constraints === []) {
                continue;
            }

            $candidates = self::candidates($tenantId, $constraints, $sex, $contrast, $userId);
            $totalCandidates += $candidates->count();

            if ($candidates->isEmpty()) {
                continue;
            }

            /** @var ReportTemplate $winner */
            $winner = $candidates->first();

            return [
                'template' => $winner,
                'tier' => $attempt['tier'],
                'ageGroup' => $ageGroup,
                'candidates' => $candidates->count(),
                'explanation' => self::explain($winner, $attempt['tier'], $ageGroup),
            ];
        }

        return [
            'template' => null,
            'tier' => 'none',
            'ageGroup' => $ageGroup,
            'candidates' => $totalCandidates,
            'explanation' => 'No matching template in this clinic — a blank structured report will be used.',
        ];
    }

    /**
     * @param  list<array{0:string,1:string,2:mixed}>  $constraints
     * @return \Illuminate\Support\Collection<int,ReportTemplate>
     */
    private static function candidates(int $tenantId, array $constraints, ?string $sex, ?string $contrast, ?int $userId)
    {
        $query = ReportTemplate::query()
            ->where('business_id', $tenantId)
            ->where('is_archived', false)
            ->where(fn ($q) => $q->where('scope', 'tenant')->orWhere('created_by', $userId ?? 0));

        foreach ($constraints as [$column, $operator, $value]) {
            if ($operator === 'isnull') {
                $query->whereNull($column);
                continue;
            }

            $query->where($column, $value);
        }

        if ($sex === null) {
            // Unknown/other sex: sex-specific clinical content must not be
            // auto-served.
            $query->whereNull('sex');
        } else {
            $query->where(fn ($q) => $q->whereNull('sex')->orWhere('sex', $sex));
        }

        return $query->orderByDesc('version')->orderBy('id')->get()
            ->map(function (ReportTemplate $t) use ($sex, $contrast, $userId) {
                $score = 0;

                if ($sex !== null && $t->sex === $sex) {
                    $score += 4;
                }

                if ($contrast !== null) {
                    if ($t->contrast === $contrast) {
                        $score += 4;
                    } elseif ($t->contrast === 'both' || $t->contrast === null) {
                        $score += 1;
                    }
                }

                if ($userId !== null && (int) $t->created_by === $userId) {
                    $score += 2; // the radiologist's own template wins ties
                }

                $t->setAttribute('_match_score', $score);

                return $t;
            })
            ->sortBy([['_match_score', 'desc'], ['version', 'desc'], ['id', 'asc']])
            ->values();
    }

    private static function explain(ReportTemplate $template, string $tier, ?string $ageGroup): string
    {
        $tierLabel = match ($tier) {
            'procedure_age' => 'the exact procedure and age group',
            'procedure' => 'the exact procedure',
            'region_age' => 'the modality, body region and age group',
            'region' => 'the modality and body region',
            'modality_age' => 'the modality and age group',
            'modality' => 'the modality',
            'tenant_default' => 'this clinic\'s default template',
            default => 'no match',
        };

        $age = $ageGroup ? ' for a '.strtolower(AgeGroup::label($ageGroup)).' patient' : '';

        return "Matched \"{$template->name}\" on {$tierLabel}{$age}. Baseline text only — review and edit before finalizing.";
    }
}
