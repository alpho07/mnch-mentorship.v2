<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Pages\CreateAssessment;
use App\Filament\Resources\AssessmentTypeResource;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentSectionScore;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\ConditionalLogicEvaluator;
use App\Services\DynamicFormBuilder;
use App\Services\DynamicScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Test Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        foreach (['view_any_assessment', 'view_any_assessment::type', 'update_assessment::type', 'create_assessment::type', 'view_any_assessment::question'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_assessment', 'view_any_assessment::type', 'update_assessment::type', 'create_assessment::type', 'view_any_assessment::question']);
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    private function makeTemplateWithQuestionGroupSection(): array
    {
        $type = AssessmentType::create([
            'name' => 'Skillslab Status Assessment (2025 Aug)',
            'code' => 'SKILLSLAB_STATUS_2025_AUG',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Skills Lab',
            'code' => 'skills_lab_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);

        $master = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'TEST_MASTER',
            'question_text' => 'Does the facility have a skills lab?',
            'question_type' => 'yes_no',
            'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'order' => 1,
            'is_active' => true,
        ]);

        $follower = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'TEST_FOLLOWER',
            'question_text' => 'Is there a fallback room?',
            'question_type' => 'yes_no',
            'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'display_conditions' => [
                'question_code' => 'TEST_MASTER',
                'operator' => 'equals',
                'value' => 'No',
            ],
            'order' => 2,
            'is_active' => true,
        ]);

        return [$type, $section, $master, $follower];
    }

    // ── ConditionalLogicEvaluator (unit) ──────────────────────────────────

    public function test_evaluator_single_condition(): void
    {
        $resolver = fn (string $code) => $code === 'PARENT' ? 'Yes' : null;

        $this->assertTrue(ConditionalLogicEvaluator::isVisible(
            ['question_code' => 'PARENT', 'operator' => 'equals', 'value' => 'Yes'],
            $resolver
        ));
        $this->assertFalse(ConditionalLogicEvaluator::isVisible(
            ['question_code' => 'PARENT', 'operator' => 'equals', 'value' => 'No'],
            $resolver
        ));
    }

    public function test_evaluator_single_condition_hidden_when_parent_unanswered(): void
    {
        $resolver = fn (string $code) => null;

        $this->assertFalse(ConditionalLogicEvaluator::isVisible(
            ['question_code' => 'PARENT', 'operator' => 'equals', 'value' => 'Yes'],
            $resolver
        ));
    }

    public function test_evaluator_or_condition(): void
    {
        $resolver = fn (string $code) => match ($code) {
            'A' => 'No',
            'B' => 'Yes',
            default => null,
        };

        $this->assertTrue(ConditionalLogicEvaluator::isVisible([
            'operator' => 'or',
            'conditions' => [
                ['question_code' => 'A', 'operator' => 'equals', 'value' => 'Yes'],
                ['question_code' => 'B', 'operator' => 'equals', 'value' => 'Yes'],
            ],
        ], $resolver));
    }

    public function test_evaluator_and_condition(): void
    {
        $resolver = fn (string $code) => match ($code) {
            'A' => 'Yes',
            'B' => 'No',
            default => null,
        };

        $this->assertFalse(ConditionalLogicEvaluator::isVisible([
            'operator' => 'and',
            'conditions' => [
                ['question_code' => 'A', 'operator' => 'equals', 'value' => 'Yes'],
                ['question_code' => 'B', 'operator' => 'equals', 'value' => 'Yes'],
            ],
        ], $resolver));
    }

    public function test_evaluator_legacy_show_if(): void
    {
        $resolver = fn (string $code) => 'Yes';

        $this->assertTrue(ConditionalLogicEvaluator::isVisible(
            ['show_if' => ['question_code' => 'PARENT', 'value' => 'Yes']],
            $resolver
        ));
    }

    public function test_evaluator_no_conditions_is_always_visible(): void
    {
        $this->assertTrue(ConditionalLogicEvaluator::isVisible([], fn () => null));
    }

    // ── DynamicScoringService: general conditional exclusion ─────────────

    public function test_conditionally_hidden_question_is_excluded_from_section_scoring(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section, $master, $follower] = $this->makeTemplateWithQuestionGroupSection();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        // Master = "Yes" -> follower's condition (Master = "No") is false,
        // so the follower should be excluded entirely from max_score.
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id,
            'assessment_question_id' => $master->id,
            'response_value' => 'Yes',
            'score' => 1,
        ]);

        DynamicScoringService::recalculateSectionScore($assessment->id, $section->id);

        $score = AssessmentSectionScore::where('assessment_id', $assessment->id)
            ->where('assessment_section_id', $section->id)
            ->first();

        $this->assertEquals(1, $score->max_score); // only TEST_MASTER counts
        $this->assertEquals(1, $score->total_score);
        $this->assertSame(100.0, (float) $score->percentage);
    }

    public function test_question_becomes_scoreable_once_its_condition_is_met(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section, $master, $follower] = $this->makeTemplateWithQuestionGroupSection();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        // Master = "No" -> follower's condition IS met, both count.
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id,
            'assessment_question_id' => $master->id,
            'response_value' => 'No',
            'score' => 0,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id,
            'assessment_question_id' => $follower->id,
            'response_value' => 'Yes',
            'score' => 1,
        ]);

        DynamicScoringService::recalculateSectionScore($assessment->id, $section->id);

        $score = AssessmentSectionScore::where('assessment_id', $assessment->id)
            ->where('assessment_section_id', $section->id)
            ->first();

        $this->assertEquals(2, $score->max_score);
    }

    // ── Mortality 3-month field ────────────────────────────────────────

    public function test_mortality_field_saves_json_and_is_never_scored(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Test Type', 'code' => 'TEST_TYPE_MORT', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Info Systems',
            'code' => 'info_systems_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'TEST_MORTALITY',
            'question_text' => 'Mortality register',
            'question_type' => 'mortality_three_month',
            'is_scored' => true,
            'scoring_map' => ['No' => 0, 'Yes' => 1],
            'order' => 1,
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        $fieldName = "question_response_{$question->id}";
        $monthKeys = array_values((new \ReflectionMethod(DynamicFormBuilder::class, 'mortalityMonthKeys'))->invoke(null));

        DynamicFormBuilder::saveResponses($assessment->id, $section->id, [
            "{$fieldName}_{$monthKeys[0]}" => 2,
            "{$fieldName}_{$monthKeys[1]}" => 1,
            "{$fieldName}_{$monthKeys[2]}" => 4,
        ]);

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $question->id)
            ->first();

        $this->assertSame(
            [$monthKeys[0] => 2, $monthKeys[1] => 1, $monthKeys[2] => 4],
            json_decode($response->response_value, true)
        );
        $this->assertNull($response->score);

        // The only question in this section is the unscored mortality
        // type, so recalculateSectionScore() has nothing to score at all
        // (0 scoreable questions) and doesn't write a score row —
        // consistent with how an all-informational section already behaves.
        $score = AssessmentSectionScore::where('assessment_id', $assessment->id)
            ->where('assessment_section_id', $section->id)
            ->first();
        $this->assertNull($score);
    }

    // ── AssessmentType (template) admin resource ──────────────────────

    public function test_non_privileged_user_cannot_access_assessment_type_resource(): void
    {
        $user = User::factory()->create(['name' => 'No Access']);
        $this->actingAs($user);

        $this->assertFalse(AssessmentTypeResource::canAccess());
    }

    public function test_assessment_type_page_loads_and_lists_templates(): void
    {
        $this->makeAssessor();
        AssessmentType::create(['name' => 'Skillslab Status Assessment (2025 Aug)', 'code' => 'SKILLSLAB_2025', 'is_active' => true]);

        $response = $this->get(AssessmentTypeResource::getUrl());

        $response->assertOk();
        $response->assertSee('Skillslab Status Assessment (2025 Aug)');
    }

    public function test_sections_relation_manager_blocks_a_second_human_resources_section_on_standard_template(): void
    {
        $this->makeAssessor();

        // Mirrors the backfilled "Standard Facility Assessment" shape:
        // an informational section sharing the same section_type value as
        // the real human_resources section.
        $type = AssessmentType::create(['name' => 'Standard-like', 'code' => 'STANDARD_LIKE', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Facility Profile',
            'code' => 'facility_profile',
            'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES, // 'structured_data'
            'order' => 1,
            'is_active' => true,
        ]);
        $existingHr = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Human Resources',
            'code' => 'human_resources',
            'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES,
            'order' => 2,
            'is_active' => true,
        ]);

        // The validation rule's own "does a duplicate already exist" check
        // (exercised directly, matching the closure body in
        // SectionsRelationManager::form()) must NOT be tripped by the
        // informational facility_profile row sharing section_type with the
        // real human_resources section — only editing the human_resources
        // section itself should be allowed.
        $existsWhenEditingItself = $type->sections()
            ->where('section_type', AssessmentSection::KIND_HUMAN_RESOURCES)
            ->whereNotIn('code', AssessmentSection::INFORMATIONAL_CODES)
            ->whereKeyNot($existingHr->id)
            ->exists();

        $this->assertFalse($existsWhenEditingItself, 'Editing the existing Human Resources section should not be blocked by the informational facility_profile row.');

        // A genuine second human_resources-kind section IS a real duplicate.
        $secondHr = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Human Resources 2',
            'code' => 'human_resources_2',
            'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES,
            'order' => 3,
            'is_active' => true,
        ]);

        $existsWhenCreatingADuplicate = $type->sections()
            ->where('section_type', AssessmentSection::KIND_HUMAN_RESOURCES)
            ->whereNotIn('code', AssessmentSection::INFORMATIONAL_CODES)
            ->whereKeyNot($secondHr->id)
            ->exists();

        $this->assertTrue($existsWhenCreatingADuplicate, 'A second real Human Resources section should be flagged as a duplicate.');
    }

    // ── CreateAssessment: template picker + duplicate check ────────────

    public function test_create_assessment_uses_template_picker_and_builds_dynamic_section_progress(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section] = $this->makeTemplateWithQuestionGroupSection();
        $facility = Facility::factory()->create();

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $assessment = Assessment::where('facility_id', $facility->id)->where('assessment_type_id', $type->id)->first();

        $this->assertNotNull($assessment);
        $this->assertArrayHasKey('facility_assessor', $assessment->section_progress);
        $this->assertArrayHasKey($section->code, $assessment->section_progress);
        $this->assertTrue($assessment->section_progress['facility_assessor']);
        $this->assertFalse($assessment->section_progress[$section->code]);
    }

    public function test_create_assessment_blocks_a_duplicate_for_the_same_template_and_facility(): void
    {
        $assessor = $this->makeAssessor();
        [$type] = $this->makeTemplateWithQuestionGroupSection();
        $facility = Facility::factory()->create();

        Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
            ])
            ->call('create');

        $this->assertSame(1, Assessment::where('facility_id', $facility->id)->where('assessment_type_id', $type->id)->count());
    }

    // ── EditSection: scoping + real render ─────────────────────────────

    public function test_edit_section_page_renders_for_the_assessments_owner(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section] = $this->makeTemplateWithQuestionGroupSection();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $section->code]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee($section->name);
    }

    public function test_edit_section_page_404s_for_a_different_assessor(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section] = $this->makeTemplateWithQuestionGroupSection();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        $otherAssessor = User::factory()->create(['name' => 'Other Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $otherAssessor->givePermissionTo('view_any_assessment');
        $otherAssessor->assignRole('assessor');
        $this->actingAs($otherAssessor);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $section->code]);
        $response = $this->get($url);

        $response->assertNotFound();
    }

    public function test_edit_section_404s_for_a_human_resources_kind_section(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'HR Only', 'code' => 'HR_ONLY_TEST', 'is_active' => true]);
        $hrSection = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Human Resources',
            'code' => 'human_resources',
            'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES,
            'order' => 1,
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        // A human_resources-kind section must not be reachable through the
        // generic question-group EditSection page.
        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $hrSection->code]);
        $response = $this->get($url);

        $response->assertNotFound();
    }

    public function test_edit_human_resources_resolves_the_correct_section_not_an_informational_one(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard-like 2', 'code' => 'STANDARD_LIKE_2', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Facility Profile',
            'code' => 'facility_profile',
            'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES,
            'order' => 1,
            'is_active' => true,
        ]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Human Resources',
            'code' => 'human_resources',
            'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES,
            'order' => 2,
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        $page = new \App\Filament\Resources\AssessmentResource\Pages\EditHumanResources;
        $page->record = $assessment;
        $page->mount($assessment->id);

        $this->assertSame('human_resources', $page->section->code);
    }
}
