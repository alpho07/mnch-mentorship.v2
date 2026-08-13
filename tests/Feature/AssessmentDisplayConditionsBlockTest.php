<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Facility;
use App\Models\User;
use App\Services\CommodityScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentDisplayConditionsBlockTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Block Skip Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    public function test_commodity_category_is_hidden_from_health_products_page_when_its_condition_is_false(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'NICU Skip Test', 'code' => 'NICU_SKIP_TEST', 'is_active' => true]);

        $infraSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_nicu',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $hasNicuQuestion = AssessmentQuestion::create([
            'assessment_section_id' => $infraSection->id, 'question_code' => 'HAS_NICU',
            'question_text' => 'Do you have a NICU?', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products_nicu',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 2, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-nicu-skip', 'is_active' => true, 'order' => 1]);
        $nicuCategory = CommodityCategory::create([
            'assessment_type_id' => $type->id, 'name' => 'NICU/PICU', 'slug' => 'nicu-picu-skip', 'order' => 1,
            'display_conditions' => ['question_code' => 'HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'],
        ]);
        $commodity = Commodity::create(['commodity_category_id' => $nicuCategory->id, 'name' => 'Surfactant', 'order' => 1, 'is_active' => true]);
        $commodity->applicableDepartments()->attach($dept->id);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $hasNicuQuestion->id, 'response_value' => 'No',
        ]);

        $url = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);
        $response->assertOk();
        $response->assertDontSee('NICU/PICU');
        $response->assertDontSee('Surfactant');

        // Scoring must also exclude the hidden category from the denominator.
        app(CommodityScoringService::class)->recalculateDepartmentScore($assessment->id, $dept->id);
        $score = \App\Models\AssessmentDepartmentScore::where('assessment_id', $assessment->id)
            ->where('assessment_department_id', $dept->id)
            ->where('commodity_category_id', $nicuCategory->id)
            ->first();
        $this->assertNull($score, 'A hidden category should not produce a department score row.');
    }

    public function test_commodity_category_is_shown_when_its_condition_is_true(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'NICU Show Test', 'code' => 'NICU_SHOW_TEST', 'is_active' => true]);

        $infraSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_nicu2',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $hasNicuQuestion = AssessmentQuestion::create([
            'assessment_section_id' => $infraSection->id, 'question_code' => 'HAS_NICU',
            'question_text' => 'Do you have a NICU?', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products_nicu2',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 2, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-nicu-show', 'is_active' => true, 'order' => 1]);
        $nicuCategory = CommodityCategory::create([
            'assessment_type_id' => $type->id, 'name' => 'NICU/PICU', 'slug' => 'nicu-picu-show', 'order' => 1,
            'display_conditions' => ['question_code' => 'HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'],
        ]);
        $commodity = Commodity::create(['commodity_category_id' => $nicuCategory->id, 'name' => 'Surfactant', 'order' => 1, 'is_active' => true]);
        $commodity->applicableDepartments()->attach($dept->id);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $hasNicuQuestion->id, 'response_value' => 'Yes',
        ]);

        $url = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);
        $response->assertOk();
        $response->assertSee('NICU/PICU');
        $response->assertSee('Surfactant');
    }

    public function test_section_with_a_false_condition_is_excluded_from_section_navigation(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Section Skip Test', 'code' => 'SECTION_SKIP_TEST', 'is_active' => true]);

        $gateSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Gate', 'code' => 'gate_section',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $gateQuestion = AssessmentQuestion::create([
            'assessment_section_id' => $gateSection->id, 'question_code' => 'GATE_Q',
            'question_text' => 'Enable extra section?', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        $hiddenSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Extra', 'code' => 'extra_section',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 2, 'is_active' => true,
            'display_conditions' => ['question_code' => 'GATE_Q', 'operator' => 'equals', 'value' => 'Yes'],
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $hiddenSection->id, 'question_code' => 'EXTRA_Q',
            'question_text' => 'Extra question', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $gateQuestion->id, 'response_value' => 'No',
        ]);

        // Hit the (still-visible) gate_section page — its section-nav strip
        // is built from HasSectionNavigation::getAllSections(), so the
        // presence/absence of each section's own edit URL in the rendered
        // HTML proves whether it was included, without needing a route
        // context to instantiate EditSection directly (its mount() requires
        // a real routed sectionCode param).
        $gateUrl = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $gateSection->code]);
        $extraUrl = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $hiddenSection->code]);

        $response = $this->get($gateUrl);
        $response->assertOk();
        $response->assertSee($gateUrl, false);
        $response->assertDontSee($extraUrl, false);
    }
}
