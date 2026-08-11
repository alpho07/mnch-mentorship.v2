<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Services\DynamicFormBuilder;
use Filament\Forms\Components\Fieldset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFormBuilderGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSection(string $code): AssessmentSection
    {
        $type = AssessmentType::create(['name' => 'Grouping Test', 'code' => "GROUPING_TEST_{$code}", 'is_active' => true]);

        return AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Grouped Section',
            'code' => $code,
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);
    }

    private function makeYesNo(AssessmentSection $section, string $questionCode, int $order, ?string $group): AssessmentQuestion
    {
        return AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => $questionCode,
            'question_text' => "Question {$questionCode}",
            'question_type' => 'yes_no',
            'group' => $group,
            'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'order' => $order,
            'is_active' => true,
        ]);
    }

    public function test_questions_sharing_a_group_are_wrapped_in_one_fieldset(): void
    {
        $section = $this->makeSection('grouped_section_test');
        $this->makeYesNo($section, 'GRP_Q1', 1, 'Kit A');
        $this->makeYesNo($section, 'GRP_Q2', 2, 'Kit A');
        $this->makeYesNo($section, 'GRP_Q3', 3, null);

        $fields = DynamicFormBuilder::buildForSection($section->id);

        // GRP_Q1 + GRP_Q2 collapse into one Fieldset labeled "Kit A";
        // the ungrouped GRP_Q3 stays a separate top-level field.
        $this->assertCount(2, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        $this->assertSame('Kit A', $fields[0]->getLabel());
        $this->assertNotInstanceOf(Fieldset::class, $fields[1]);
    }

    public function test_ungrouped_questions_render_with_no_fieldset_wrapping_at_all(): void
    {
        $section = $this->makeSection('ungrouped_section_test');
        $this->makeYesNo($section, 'UNGRP_Q1', 1, null);
        $this->makeYesNo($section, 'UNGRP_Q2', 2, null);

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(2, $fields);
        foreach ($fields as $field) {
            $this->assertNotInstanceOf(Fieldset::class, $field);
        }
    }

    public function test_two_separate_groups_produce_two_separate_fieldsets(): void
    {
        $section = $this->makeSection('two_groups_section_test');
        $this->makeYesNo($section, 'TWOGRP_Q1', 1, 'Kit A');
        $this->makeYesNo($section, 'TWOGRP_Q2', 2, 'Kit B');

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(2, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        $this->assertSame('Kit A', $fields[0]->getLabel());
        $this->assertInstanceOf(Fieldset::class, $fields[1]);
        $this->assertSame('Kit B', $fields[1]->getLabel());
    }

    public function test_a_group_of_exactly_7_fields_still_lays_out_side_by_side(): void
    {
        // The upper edge of the small-group threshold — matches the
        // EmONC-trained-workers distribution row (total + 6 departments).
        $section = $this->makeSection('seven_field_section_test');
        for ($i = 1; $i <= 7; $i++) {
            $this->makeYesNo($section, "SEVEN_Q{$i}", $i, 'Distribution');
        }

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        $this->assertSame(7, $fields[0]->getColumns('default'));
    }

    public function test_a_small_group_lays_out_side_by_side_table_style(): void
    {
        $section = $this->makeSection('small_group_section_test');
        $this->makeYesNo($section, 'SMALL_Q1', 1, 'Respondent');
        $this->makeYesNo($section, 'SMALL_Q2', 2, 'Respondent');
        $this->makeYesNo($section, 'SMALL_Q3', 3, 'Respondent');

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        // 'default' must resolve to 3 (not just 'lg') — Filament's
        // plain-int columns(3) only sets the 'lg' breakpoint, which
        // silently collapses to a single column below 1024px or whenever
        // the container (not the raw viewport) is narrower than that,
        // e.g. behind this admin panel's sidebar.
        $this->assertSame(3, $fields[0]->getColumns('default'));
    }

    public function test_a_large_group_stays_a_single_readable_column(): void
    {
        // 9 — above the <=7 small-group threshold, matching the smallest
        // real "large" group (the Assisted Vacuum Delivery kit: 1 parent +
        // 7 items + 1 completeness = 9).
        $section = $this->makeSection('large_group_section_test');
        for ($i = 1; $i <= 9; $i++) {
            $this->makeYesNo($section, "LARGE_Q{$i}", $i, 'Big Kit');
        }

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        $this->assertSame(1, $fields[0]->getColumns('default'));
    }

    private function makeText(AssessmentSection $section, string $questionCode, int $order, ?string $group): AssessmentQuestion
    {
        return AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => $questionCode,
            'question_text' => "Question {$questionCode}",
            'question_type' => 'text',
            'group' => $group,
            'is_scored' => false,
            'order' => $order,
            'is_active' => true,
        ]);
    }

    public function test_text_fields_in_a_small_group_actually_sit_side_by_side(): void
    {
        // buildTextField() calls ->columnSpanFull() unconditionally (right
        // for a standalone question) — that must not survive once the
        // field is placed inside a table-style Fieldset, or every "column"
        // stacks full-width regardless of the Fieldset's own column count.
        $section = $this->makeSection('text_field_span_section_test');
        $this->makeText($section, 'SPAN_Q1', 1, 'Person In Charge');
        $this->makeText($section, 'SPAN_Q2', 2, 'Person In Charge');

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);

        foreach ($fields[0]->getChildComponents() as $child) {
            $this->assertSame(1, $child->getColumnSpan('default'));
        }
    }

    private function makeNumber(AssessmentSection $section, string $questionCode, int $order, string $group): AssessmentQuestion
    {
        return AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => $questionCode,
            'question_text' => "Question {$questionCode}",
            'question_type' => 'number',
            'group' => $group,
            'is_scored' => false,
            'order' => $order,
            'is_active' => true,
        ]);
    }

    public function test_consecutive_table_row_groups_merge_into_one_shared_header_table(): void
    {
        $section = $this->makeSection('table_merge_section_test');
        // 2 "rows" (Nurses, Doctors), 2 columns each — the table-row
        // convention: "{title}|{rowLabelHeader}|{rowLabel}".
        $this->makeNumber($section, 'TABLE_Q1', 1, 'Human Resources|Cadre|Nurses');
        $this->makeNumber($section, 'TABLE_Q2', 2, 'Human Resources|Cadre|Nurses');
        $this->makeNumber($section, 'TABLE_Q3', 3, 'Human Resources|Cadre|Doctors');
        $this->makeNumber($section, 'TABLE_Q4', 4, 'Human Resources|Cadre|Doctors');

        $fields = DynamicFormBuilder::buildForSection($section->id);

        // Both cadre rows collapse into ONE Fieldset, not two.
        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        $this->assertSame('Human Resources', $fields[0]->getLabel());

        // A genuine header row (1 row-label header + 2 column headers = 3
        // cells) followed by 2 full data rows (3 cells each) = 9 total.
        // Every row, including the first, is a real data row — none of
        // them also doubles as the header.
        $this->assertCount(9, $fields[0]->getChildComponents());
        $this->assertSame(3, $fields[0]->getColumns('default'));
    }

    public function test_table_row_group_has_a_dedicated_header_row_separate_from_every_data_row(): void
    {
        $section = $this->makeSection('table_header_separation_section_test');
        $this->makeNumber($section, 'HEADSEP_Q1', 1, 'HR|Cadre|Nurses');
        $this->makeNumber($section, 'HEADSEP_Q2', 2, 'HR|Cadre|Doctors');

        $fields = DynamicFormBuilder::buildForSection($section->id);
        $cells = $fields[0]->getChildComponents();

        // Header row: row-label header ("Cadre") + 1 column header
        // ("Question HEADSEP_Q1"), both tagged .aqs-header-cell, both with
        // their labels visible — then 2 data rows x 2 cells each = 6 total.
        $this->assertCount(6, $cells);
        $this->assertFalse($cells[0]->isLabelHidden());
        $this->assertSame('Cadre', $cells[0]->getLabel());
        $this->assertFalse($cells[1]->isLabelHidden());

        // Data row 1 (Nurses): its own row-label cell has its label
        // hidden (it's not "Cadre" again, just the bare value "Nurses"),
        // and — critically — its field's label is ALSO hidden, unlike the
        // old design where the first data row doubled as the header.
        $this->assertTrue($cells[2]->isLabelHidden());
        $this->assertSame('Nurses', $cells[2]->getContent());
        $this->assertTrue($cells[3]->isLabelHidden());

        // Data row 2 (Doctors) behaves identically to data row 1 — no
        // row is special-cased as "the header row" anymore.
        $this->assertTrue($cells[4]->isLabelHidden());
        $this->assertSame('Doctors', $cells[4]->getContent());
        $this->assertTrue($cells[5]->isLabelHidden());
    }

    public function test_table_rows_with_a_different_title_do_not_merge(): void
    {
        $section = $this->makeSection('table_no_merge_section_test');
        $this->makeNumber($section, 'NOMERGE_Q1', 1, 'Table A|Row|First');
        $this->makeNumber($section, 'NOMERGE_Q2', 2, 'Table B|Row|Second');

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(2, $fields);
        $this->assertSame('Table A', $fields[0]->getLabel());
        $this->assertSame('Table B', $fields[1]->getLabel());
    }
}
