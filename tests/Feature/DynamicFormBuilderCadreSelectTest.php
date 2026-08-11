<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Cadre;
use App\Services\DynamicFormBuilder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFormBuilderCadreSelectTest extends TestCase
{
    use RefreshDatabase;

    private function makeCadreSelectQuestion(): array
    {
        $type = AssessmentType::create(['name' => 'Cadre Select Test', 'code' => 'CADRE_SELECT_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Cadre Select Section',
            'code' => 'cadre_select_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'CADRE_SELECT_Q1',
            'question_text' => 'Cadre',
            'question_type' => 'cadre_select',
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);

        return [$section, $question];
    }

    public function test_cadre_select_renders_as_a_select_with_live_cadre_options(): void
    {
        Cadre::create(['name' => 'General Cadre', 'code' => 'general_cadre_test', 'category' => null, 'is_active' => true, 'order' => 1]);
        Cadre::create(['name' => 'EmONC Cadre', 'code' => 'emonc_cadre_select_test', 'category' => 'emonc', 'is_active' => true, 'order' => 2]);
        Cadre::create(['name' => 'Retired Cadre', 'code' => 'retired_cadre_test', 'category' => null, 'is_active' => false, 'order' => 3]);

        [$section] = $this->makeCadreSelectQuestion();

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Select::class, $fields[0]);

        $options = $fields[0]->getOptions();
        // Both the general (category=null) and emonc-category cadres are
        // offered -- the respondent answering the survey could be any
        // active cadre in the system, not just the 4 EmONC HR buckets.
        $this->assertContains('General Cadre', $options);
        $this->assertContains('EmONC Cadre', $options);
        $this->assertNotContains('Retired Cadre', $options);
    }

    public function test_cadre_select_picks_up_a_cadre_added_after_the_question_was_created(): void
    {
        [$section] = $this->makeCadreSelectQuestion();
        Cadre::create(['name' => 'Late Addition', 'code' => 'late_addition_test', 'category' => null, 'is_active' => true, 'order' => 1]);

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertContains('Late Addition', $fields[0]->getOptions());
    }
}
