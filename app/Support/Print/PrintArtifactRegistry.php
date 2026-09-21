<?php

namespace App\Support\Print;

/**
 * The print artifact registry — the single list of everything the platform can
 * print, what paper it belongs on, and which permission gates it.
 *
 * This is what stops printing from being "whatever a component happens to do":
 * the controller authorises from here, the tenant settings screen renders from
 * here, the SPA resolves its default paper from here, and the test suite
 * iterates it so a newly added document cannot ship untested.
 */
final class PrintArtifactRegistry
{
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_COLLECTION = 'collection';

    /**
     * @return array<string, array{
     *     label: string,
     *     type: string,
     *     defaultPaper: string,
     *     allowedPapers: list<string>,
     *     permission: list<string>,
     *     finalOnly: bool,
     *     description: string
     * }>
     */
    public static function all(): array
    {
        return [
            'invoice' => [
                'label' => 'Tax invoice',
                'type' => self::TYPE_DOCUMENT,
                'defaultPaper' => PaperProfile::A4,
                'allowedPapers' => [PaperProfile::A4, PaperProfile::THERMAL80],
                'permission' => ['invoice print', 'invoice manage'],
                'finalOnly' => false,
                'description' => 'Itemised billing document with totals, payments and tax.',
            ],
            'receipt' => [
                'label' => 'Payment receipt',
                'type' => self::TYPE_DOCUMENT,
                'defaultPaper' => PaperProfile::THERMAL80,
                'allowedPapers' => [PaperProfile::THERMAL80, PaperProfile::A4],
                'permission' => ['receipt print', 'invoice manage'],
                'finalOnly' => false,
                'description' => '80 mm counter slip for money taken at the desk.',
            ],
            'report' => [
                'label' => 'Radiology report',
                'type' => self::TYPE_DOCUMENT,
                'defaultPaper' => PaperProfile::A4,
                'allowedPapers' => [PaperProfile::A4],
                'permission' => ['report print', 'report manage'],
                'finalOnly' => false,
                'description' => 'Signed report of record, A4 only.',
            ],
            'token' => [
                'label' => 'Queue / booking token slip',
                'type' => self::TYPE_DOCUMENT,
                'defaultPaper' => PaperProfile::THERMAL80,
                'allowedPapers' => [PaperProfile::THERMAL80],
                'permission' => ['receipt print', 'study checkin', 'appointment manage'],
                'finalOnly' => false,
                'description' => 'Reception queue slip with preparation instructions.',
            ],
            'label' => [
                'label' => 'Patient wristband / film label',
                'type' => self::TYPE_DOCUMENT,
                'defaultPaper' => PaperProfile::LABEL,
                'allowedPapers' => [PaperProfile::LABEL, PaperProfile::THERMAL80],
                'permission' => ['label print', 'study acquire', 'study checkin', 'appointment manage'],
                'finalOnly' => false,
                'description' => 'Barcode tag for the wristband and the film envelope.',
            ],
            'manifest' => [
                'label' => 'Daily reception manifest',
                'type' => self::TYPE_COLLECTION,
                'defaultPaper' => PaperProfile::A4,
                'allowedPapers' => [PaperProfile::A4],
                'permission' => ['receipt print', 'appointment manage'],
                'finalOnly' => false,
                'description' => 'Front-desk run sheet for one day, with a sign-off column.',
            ],
            'fee-schedule' => [
                'label' => 'Official fee schedule',
                'type' => self::TYPE_COLLECTION,
                'defaultPaper' => PaperProfile::A4,
                'allowedPapers' => [PaperProfile::A4],
                'permission' => ['invoice print', 'catalog view'],
                'finalOnly' => false,
                'description' => 'Master procedure catalogue handed to patients.',
            ],
            'doctor-settlement' => [
                'label' => 'Referral settlement sheet',
                'type' => self::TYPE_DOCUMENT,
                'defaultPaper' => PaperProfile::A4,
                'allowedPapers' => [PaperProfile::A4],
                'permission' => ['invoice print', 'doctors view'],
                'finalOnly' => false,
                'description' => 'Monthly referrer commission statement.',
            ],
            'shift-closing' => [
                'label' => 'Shift cash closing statement',
                'type' => self::TYPE_DOCUMENT,
                'defaultPaper' => PaperProfile::A4,
                'allowedPapers' => [PaperProfile::A4],
                'permission' => ['invoice print', 'invoice payment'],
                'finalOnly' => false,
                'description' => 'Register reconciliation with drawer audit and sign-off.',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $artifact): bool
    {
        return array_key_exists($artifact, self::all());
    }

    /** @return array<string, mixed> */
    public static function for(string $artifact): array
    {
        return self::all()[$artifact] ?? [
            'label' => 'Document',
            'type' => self::TYPE_DOCUMENT,
            'defaultPaper' => PaperProfile::A4,
            'allowedPapers' => [PaperProfile::A4],
            'permission' => [],
            'finalOnly' => false,
            'description' => '',
        ];
    }

    public static function allowsPaper(string $artifact, string $paper): bool
    {
        return in_array($paper, self::for($artifact)['allowedPapers'], true);
    }

    /** @return list<string> */
    public static function permissions(string $artifact): array
    {
        return self::for($artifact)['permission'];
    }

    /** The client-facing registry: what the SPA needs, with no PHP internals. */
    public static function toArray(int $businessId): array
    {
        $settings = PrintSettings::for($businessId);
        $out = [];

        foreach (self::all() as $key => $meta) {
            $out[] = [
                'artifact' => $key,
                'label' => $meta['label'],
                'type' => $meta['type'],
                'description' => $meta['description'],
                'allowedPapers' => $meta['allowedPapers'],
                'defaultPaper' => $settings['defaultPapers'][$key] ?? $meta['defaultPaper'],
                'permission' => $meta['permission'],
            ];
        }

        return $out;
    }
}
