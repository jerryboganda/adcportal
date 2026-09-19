<?php

namespace App\Support;

/**
 * Structured reporting: the schema a template declares and the rendering of
 * the values a radiologist enters.
 *
 * Field schema (stored on `report_templates.structured_fields`):
 *
 *   {
 *     "key": "hydronephrosis",          // stable id, unique per template
 *     "label": "Hydronephrosis",
 *     "type": "text|number|select|radio|checkbox|date|measurement",
 *     "options": ["None","Mild"],       // select|radio only
 *     "unit": "mm",                     // number|measurement
 *     "placeholder": "3.8",
 *     "normalText": "No hydronephrosis.",   // approved normal phrasing
 *     "required": false
 *   }
 *
 * `normalText` is what makes the "Normal" quick control safe: it inserts
 * CURATED template text (clinically reviewed, tenant-managed data) instead of
 * the UI inventing phrasing. It is a drafting aid — the radiologist still
 * reviews and explicitly finalizes.
 */
final class ReportStructure
{
    public const TYPES = ['text', 'number', 'measurement', 'select', 'radio', 'checkbox', 'date'];

    /** Sanitise a client-supplied field list into the canonical schema. */
    public static function sanitizeFields(?array $fields): array
    {
        $clean = [];
        $seen = [];

        foreach ($fields ?? [] as $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            $key = $key !== '' ? $key : \Illuminate\Support\Str::slug($label, '_');
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key)) ?: 'field';

            // Keys must be unique within a template — a duplicate would make
            // the collected values ambiguous.
            $base = $key;
            $n = 2;
            while (isset($seen[$key])) {
                $key = $base.'_'.$n++;
            }
            $seen[$key] = true;

            $type = strtolower((string) ($field['type'] ?? 'text'));
            $type = in_array($type, self::TYPES, true) ? $type : 'text';

            $row = [
                'key' => $key,
                'label' => mb_substr($label, 0, 160),
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
            ];

            $unit = trim((string) ($field['unit'] ?? ''));
            if ($unit !== '') {
                $row['unit'] = mb_substr($unit, 0, 24);
            }

            $placeholder = trim((string) ($field['placeholder'] ?? ''));
            if ($placeholder !== '') {
                $row['placeholder'] = mb_substr($placeholder, 0, 80);
            }

            $normal = trim((string) ($field['normalText'] ?? ''));
            if ($normal !== '') {
                $row['normalText'] = mb_substr($normal, 0, 500);
            }

            if (in_array($type, ['select', 'radio'], true)) {
                $options = collect($field['options'] ?? [])
                    ->map(fn ($o) => trim((string) (is_array($o) ? ($o['label'] ?? $o['value'] ?? '') : $o)))
                    ->filter()
                    ->unique()
                    ->take(40)
                    ->values()
                    ->all();

                // An option-less choice control would be unusable; degrade it
                // to free text instead of shipping a dead dropdown.
                if ($options === []) {
                    $row['type'] = 'text';
                } else {
                    $row['options'] = $options;
                }
            }

            $clean[] = $row;
        }

        return array_slice($clean, 0, 60);
    }

    /**
     * Validate entered values against a template schema.
     *
     * @return array{0: array<string,mixed>, 1: array<string,string>} [values, errors keyed by field]
     */
    public static function validateValues(?array $fields, ?array $values): array
    {
        $schema = self::sanitizeFields($fields);
        $clean = [];
        $errors = [];

        foreach ($schema as $field) {
            $key = $field['key'];
            $raw = $values[$key] ?? null;
            $type = $field['type'];

            if ($type === 'checkbox') {
                $clean[$key] = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                continue;
            }

            $value = is_scalar($raw) ? trim((string) $raw) : '';

            if ($value === '') {
                if (($field['required'] ?? false) === true) {
                    $errors[$key] = "{$field['label']} is required.";
                }
                continue;
            }

            if (in_array($type, ['number', 'measurement'], true) && ! is_numeric($value)) {
                $errors[$key] = "{$field['label']} must be a number.";
                continue;
            }

            if (in_array($type, ['select', 'radio'], true) && ! in_array($value, $field['options'] ?? [], true)) {
                $errors[$key] = "{$field['label']} has an invalid option.";
                continue;
            }

            $clean[$key] = mb_substr($value, 0, 2000);
        }

        // Values for keys that are not in the schema are dropped: a report can
        // only carry structure the template declared.
        return [$clean, $errors];
    }

    /**
     * Render collected values as clinical text appended to the report body.
     * Only labels the radiologist actually filled in are emitted.
     */
    public static function render(?array $fields, ?array $values): string
    {
        $schema = self::sanitizeFields($fields);
        $values = $values ?? [];
        $lines = [];

        foreach ($schema as $field) {
            $key = $field['key'];
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];

            if ($field['type'] === 'checkbox') {
                $lines[] = $field['label'].': '.($value ? 'Yes' : 'No');
                continue;
            }

            $text = is_scalar($value) ? trim((string) $value) : '';
            if ($text === '') {
                continue;
            }

            $unit = $field['unit'] ?? '';
            $lines[] = $field['label'].': '.$text.($unit !== '' ? ' '.$unit : '');
        }

        return implode("\n", $lines);
    }

    /**
     * The curated "Normal" phrasing for the fields currently marked normal.
     *
     * @param  array<string,bool|string>  $marks  key => normal marker ('normal'|'abnormal'|'not_visualized'|'na')
     */
    public static function renderNormalText(?array $fields, array $marks): string
    {
        $lines = [];

        foreach (self::sanitizeFields($fields) as $field) {
            $mark = $marks[$field['key']] ?? null;
            if ($mark !== 'normal') {
                continue;
            }

            $text = $field['normalText'] ?? "{$field['label']}: normal.";
            $lines[] = $text;
        }

        return implode("\n", $lines);
    }
}
