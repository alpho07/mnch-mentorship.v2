<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentSectionScore;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\DynamicFormBuilder;
use App\Services\DynamicScoringService;
use Filament\Forms\Components\Placeholder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GroupCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Completeness Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    private function makeKitTemplate(): array
    {
        $type = AssessmentType::create(['name' => 'Kit Completeness Test', 'code' => 'KIT_COMPLETENESS_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Kit Section',
            'code' => 'kit_completeness_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);
        $item1 = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'KIT_ITEM_1',
            'question_text' => 'Item 1',
            'question_type' => 'yes_no',
            'group' => 'Kit A',
            'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'order' => 1,
            'is_active' => true,
        ]);
        $item2 = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'KIT_ITEM_2',
            'question_text' => 'Item 2',
            'question_type' => 'yes_no',
            'group' => 'Kit A',
            'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'order' => 2,
            'is_active' => true,
        ]);
        $completeness = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'KIT_COMPLETE',
            'question_text' => 'Kit A Completeness',
            'question_type' => 'group_completeness',
            'group' => 'Kit A',
            'is_scored' => true,
            'order' => 3,
            'is_active' => true,
        ]);

        return [$type, $section, $item1, $item2, $completeness];
    }

    public function test_group_completeness_renders_as_a_disabled_placeholder(): void
    {
        [, $section] = $this->makeKitTemplate();

        $fields = DynamicFormBuilder::buildForSection($section->id);

        // All 3 questions share group "Kit A" -> one Fieldset.
        $this->assertCount(1, $fields);
        $fieldset = $fields[0];
        $children = $fieldset->getChildComponents();
        $this->assertInstanceOf(Placeholder::class, end($children));
    }

    public function test_completeness_scores_one_when_every_sibling_is_at_max(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section, $item1, $item2, $completeness] = $this->makeKitTemplate();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item1->id, 'response_value' => 'Yes', 'score' => 1]);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item2->id, 'response_value' => 'Yes', 'score' => 1]);

        DynamicScoringService::recalculateSectionScore($assessment->id, $section->id);

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $completeness->id)
            ->first();

        $this->assertNotNull($response);
        $this->assertSame('Yes', $response->response_value);
        $this->assertEquals(1, $response->score);
    }

    public function test_completeness_scores_zero_when_a_sibling_is_missing_or_no(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section, $item1, $item2, $completeness] = $this->makeKitTemplate();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item1->id, 'response_value' => 'Yes', 'score' => 1]);
        // item2 left unanswered entirely.

        DynamicScoringService::recalculateSectionScore($assessment->id, $section->id);

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $completeness->id)
            ->first();

        $this->assertNotNull($response);
        $this->assertSame('No', $response->response_value);
        $this->assertEquals(0, $response->score);
    }

    public function test_completeness_score_contributes_to_the_section_total(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section, $item1, $item2, $completeness] = $this->makeKitTemplate();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item1->id, 'response_value' => 'Yes', 'score' => 1]);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $item2->id, 'response_value' => 'Yes', 'score' => 1]);

        DynamicScoringService::recalculateSectionScore($assessment->id, $section->id);

        $score = AssessmentSectionScore::where('assessment_id', $assessment->id)
            ->where('assessment_section_id', $section->id)
            ->first();

        // 3 scored questions total (item1, item2, completeness), all at max -> 3/3.
        $this->assertEquals(3, $score->max_score);
        $this->assertEquals(3, $score->total_score);
        $this->assertSame(100.0, (float) $score->percentage);
    }
}
