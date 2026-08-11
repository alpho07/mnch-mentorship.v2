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

    public function test_a_small_group_lays_out_side_by_side_table_style(): void
    {
        $section = $this->makeSection('small_group_section_test');
        $this->makeYesNo($section, 'SMALL_Q1', 1, 'Respondent');
        $this->makeYesNo($section, 'SMALL_Q2', 2, 'Respondent');
        $this->makeYesNo($section, 'SMALL_Q3', 3, 'Respondent');

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        $this->assertSame(['lg' => 3], $fields[0]->getColumns());
    }

    public function test_a_large_group_stays_a_single_readable_column(): void
    {
        $section = $this->makeSection('large_group_section_test');
        for ($i = 1; $i <= 7; $i++) {
            $this->makeYesNo($section, "LARGE_Q{$i}", $i, 'Big Kit');
        }

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Fieldset::class, $fields[0]);
        $this->assertSame(['lg' => 1], $fields[0]->getColumns());
    }
}
