<?php

namespace App\Support;

/**
 * Body-region vocabulary for template matching.
 *
 * One shared vocabulary matters: `report_templates.body_region` is compared
 * against `services.body_region`, so two spellings of "Chest" would silently
 * disable the modality + region tier of template resolution.
 */
final class BodyRegion
{
    public const BRAIN = 'Brain';
    public const HEAD_NECK = 'Head & Neck';
    public const CHEST = 'Chest';
    public const ABDOMEN = 'Abdomen';
    public const PELVIS = 'Pelvis';
    public const SPINE = 'Spine';
    public const BREAST = 'Breast';
    public const MSK = 'Musculoskeletal';
    public const VASCULAR = 'Vascular';
    public const OTHER = 'Other';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::BRAIN, self::HEAD_NECK, self::CHEST, self::ABDOMEN, self::PELVIS,
            self::SPINE, self::BREAST, self::MSK, self::VASCULAR, self::OTHER,
        ];
    }

    public static function isValid(?string $region): bool
    {
        return $region !== null && in_array($region, self::all(), true);
    }

    /**
     * Best-effort classification of a procedure code/name.
     *
     * Used to backfill region data for catalogs created before regions existed.
     * It is intentionally conservative: an unrecognised study is left
     * `Other`, which still matches the modality tiers of template resolution
     * rather than pretending to know the anatomy.
     */
    public static function classify(?string $code, ?string $name = null): ?string
    {
        $haystack = strtoupper(trim(($code ?? '').' '.($name ?? '')));

        if ($haystack === '') {
            return null;
        }

        $rules = [
            self::VASCULAR => ['CAROTID', 'DOPPLER', 'ANGIO', 'VENOUS', 'ARTERIAL', 'DVT'],
            self::BREAST => ['MAMMO', 'BREAST', 'MG-'],
            self::SPINE => ['SPINE', 'LUMBAR', 'CERVICAL', 'THORACIC', 'LS-', 'SI JOINT'],
            self::BRAIN => ['BRAIN', 'CRANIAL', 'SKULL', 'HEAD CT', 'NCCT', 'STROKE'],
            self::HEAD_NECK => ['NECK', 'THYROID', 'SINUS', 'ORBIT', 'TEMPORAL', 'FACIAL', 'PNS'],
            self::CHEST => ['CHEST', 'THORAX', 'HRCT', 'PULMONARY', 'LUNG'],
            self::ABDOMEN => ['ABDOMEN', 'ABD', 'LIVER', 'KUB', 'RENAL', 'GALLBLADDER', 'PANCREA'],
            self::PELVIS => ['PELVIS', 'PELVIC', 'PROSTATE', 'UTERUS', 'OVARY', 'TRANSVAGINAL', 'TVS'],
            self::MSK => ['KNEE', 'SHOULDER', 'ANKLE', 'WRIST', 'ELBOW', 'HIP', 'JOINT', 'MSK', 'BONE', 'FEMUR', 'TIBIA'],
        ];

        foreach ($rules as $region => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $region;
                }
            }
        }

        return self::OTHER;
    }
}
