<?php

namespace Tests\Unit;

use App\Support\ReportStructure;
use Tests\TestCase;

/**
 * Structured reporting is the schema a template declares plus the values a
 * radiologist enters. The rules that matter clinically:
 *
 *  - a report can only carry structure its template declared (no orphan keys),
 *  - an invalid choice/number is refused rather than silently stored,
 *  - the "Normal" quick control emits CURATED template text, never invented
 *    phrasing from the UI layer.
 */
class ReportStructureTest extends TestCase
{
    public function test_fields_without_a_label_are_dropped(): void
    {
        $fields = ReportStructure::sanitizeFields([
            ['label' => '  ', 'type' => 'text'],
            ['label' => 'Liver', 'type' => 'text'],
        ]);

        $this->assertCount(1, $fields);
        $this->assertSame('Liver', $fields[0]['label']);
        $this->assertSame('liver', $fields[0]['key']);
    }

    public function test_keys_are_derived_from_the_label_and_made_unique(): void
    {
        $fields = ReportStructure::sanitizeFields([
            ['label' => 'Kidney Size', 'type' => 'text'],
            ['label' => 'Kidney Size', 'type' => 'text'],
        ]);

        $this->assertSame('kidney_size', $fields[0]['key']);
        $this->assertSame('kidney_size_2', $fields[1]['key']);
    }

    public function test_an_unknown_control_type_degrades_to_text(): void
    {
        $fields = ReportStructure::sanitizeFields([
            ['label' => 'Impression', 'type' => 'signature-pad'],
        ]);

        $this->assertSame('text', $fields[0]['type']);
    }

    public function test_a_choice_control_without_options_degrades_to_text(): void
    {
        // A dropdown with nothing to choose from is a dead control.
        $fields = ReportStructure::sanitizeFields([
            ['label' => 'Severity', 'type' => 'select', 'options' => []],
        ]);

        $this->assertSame('text', $fields[0]['type']);
        $this->assertArrayNotHasKey('options', $fields[0]);
    }

    public function test_required_empty_field_is_reported_and_not_stored(): void
    {
        $schema = [['key' => 'cbd', 'label' => 'CBD', 'type' => 'measurement', 'unit' => 'mm', 'required' => true]];

        [$values, $errors] = ReportStructure::validateValues($schema, []);

        $this->assertSame([], $values);
        $this->assertSame('CBD is required.', $errors['cbd']);
    }

    public function test_non_numeric_measurement_is_rejected(): void
    {
        $schema = [['key' => 'cbd', 'label' => 'CBD', 'type' => 'number', 'unit' => 'mm']];

        [$values, $errors] = ReportStructure::validateValues($schema, ['cbd' => 'wide']);

        $this->assertSame([], $values);
        $this->assertArrayHasKey('cbd', $errors);
    }

    public function test_a_value_outside_the_declared_options_is_rejected(): void
    {
        $schema = [['key' => 'hydro', 'label' => 'Hydronephrosis', 'type' => 'select', 'options' => ['None', 'Mild']]];

        [$values, $errors] = ReportStructure::validateValues($schema, ['hydro' => 'Severe']);

        $this->assertSame([], $values);
        $this->assertArrayHasKey('hydro', $errors);
    }

    public function test_values_for_undeclared_keys_are_dropped(): void
    {
        $schema = [['key' => 'liver', 'label' => 'Liver', 'type' => 'text']];

        [$values] = ReportStructure::validateValues($schema, [
            'liver' => 'Normal',
            'smuggled' => 'liver: cirrhosis',
        ]);

        $this->assertSame(['liver' => 'Normal'], $values);
    }

    public function test_checkboxes_are_stored_as_booleans(): void
    {
        $schema = [['key' => 'ascites', 'label' => 'Ascites', 'type' => 'checkbox']];

        [$values] = ReportStructure::validateValues($schema, ['ascites' => '1']);
        $this->assertSame(['ascites' => true], $values);

        [$values] = ReportStructure::validateValues($schema, []);
        $this->assertSame(['ascites' => false], $values);
    }

    public function test_rendering_includes_units_and_only_filled_fields(): void
    {
        $schema = ReportStructure::sanitizeFields([
            ['key' => 'cbd', 'label' => 'CBD', 'type' => 'measurement', 'unit' => 'mm'],
            ['key' => 'liver', 'label' => 'Liver', 'type' => 'text'],
            ['key' => 'ascites', 'label' => 'Ascites', 'type' => 'checkbox'],
        ]);

        $text = ReportStructure::render($schema, [
            'cbd' => '4.1',
            'ascites' => false,
            'unused' => 'ignored',
        ]);

        $this->assertSame("CBD: 4.1 mm\nAscites: No", $text);
    }

    public function test_rendering_is_empty_when_nothing_was_recorded(): void
    {
        $schema = ReportStructure::sanitizeFields([['key' => 'liver', 'label' => 'Liver', 'type' => 'text']]);

        $this->assertSame('', ReportStructure::render($schema, []));
    }

    public function test_normal_marker_emits_the_curated_template_text(): void
    {
        $schema = ReportStructure::sanitizeFields([
            ['label' => 'Gallbladder', 'type' => 'radio', 'options' => ['Normal', 'Calculi'], 'normalText' => 'Gallbladder is thin walled and calculus free.'],
            ['key' => 'cbd', 'label' => 'CBD', 'type' => 'measurement', 'unit' => 'mm'],
        ]);

        $normal = ReportStructure::renderNormalText($schema, [
            'gallbladder' => 'normal',
            'cbd' => 'abnormal',
        ]);

        $this->assertSame('Gallbladder is thin walled and calculus free.', $normal);
    }

    public function test_a_field_without_curated_text_falls_back_to_a_label_statement(): void
    {
        $schema = ReportStructure::sanitizeFields([['label' => 'Spleen', 'type' => 'radio', 'options' => ['Normal']]]);

        $this->assertSame('Spleen: normal.', ReportStructure::renderNormalText($schema, ['spleen' => 'normal']));
    }
}
