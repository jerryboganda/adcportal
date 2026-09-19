<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Clinically meaningful age bands for template matching.
 *
 * These are deliberate clinical cohorts, not string substitutions: a neonatal
 * head ultrasound and an adult abdominal ultrasound are different templates.
 * When only an integer age is known (legacy/imported patients) month-precision
 * bands are NOT invented — `neonatal` and `infant` collapse to the safest band
 * the data supports.
 */
final class AgeGroup
{
    public const NEONATAL = 'neonatal';
    public const INFANT = 'infant';
    public const PEDIATRIC = 'pediatric';
    public const ADOLESCENT = 'adolescent';
    public const ADULT = 'adult';
    public const OLDER_ADULT = 'older_adult';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::NEONATAL, self::INFANT, self::PEDIATRIC,
            self::ADOLESCENT, self::ADULT, self::OLDER_ADULT,
        ];
    }

    /** @return array<string,string> value => human label */
    public static function labels(): array
    {
        return [
            self::NEONATAL => 'Neonatal (0–28 days)',
            self::INFANT => 'Infant (1–23 months)',
            self::PEDIATRIC => 'Pediatric (2–12 years)',
            self::ADOLESCENT => 'Adolescent (13–17 years)',
            self::ADULT => 'Adult (18–64 years)',
            self::OLDER_ADULT => 'Older adult (65+)',
        ];
    }

    public static function isValid(?string $group): bool
    {
        return $group !== null && in_array($group, self::all(), true);
    }

    /**
     * Derive the band from a date of birth (preferred: month precision) or,
     * failing that, from whole years.
     */
    public static function for(?string $dob, ?int $ageYears): ?string
    {
        if (! empty($dob)) {
            try {
                $months = Carbon::parse($dob)->diffInMonths(now());
                $years = Carbon::parse($dob)->age;

                if ($months < 1) {
                    return self::NEONATAL;
                }
                if ($months < 24) {
                    return self::INFANT;
                }
            } catch (\Throwable) {
                // Unparsable dob falls through to the years-based rule.
            }
        }

        if ($ageYears === null) {
            return null;
        }

        // With only whole years, a 0- or 1-year-old may be a neonate or an
        // infant; claim the safer (less clinically specific) band.
        return match (true) {
            $ageYears <= 1 => self::INFANT,
            $ageYears <= 12 => self::PEDIATRIC,
            $ageYears <= 17 => self::ADOLESCENT,
            $ageYears <= 64 => self::ADULT,
            default => self::OLDER_ADULT,
        };
    }

    public static function label(?string $group): string
    {
        return self::labels()[$group] ?? 'Any age';
    }
}
