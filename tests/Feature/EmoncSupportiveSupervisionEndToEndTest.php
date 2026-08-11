<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSectionScore;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\AssessmentPdfReportService;
use App\Services\DynamicFormBuilder;
use Database\Seeders\EmoncSupportiveSupervisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmoncSupportiveSupervisionEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_emonc_seeder_produces_a_working_assessment_end_to_end(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $type = AssessmentType::where('code', EmoncSupportiveSupervisionSeeder::TYPE_CODE)->firstOrFail();
        $this->assertSame(9, $type->sections()->count());
        $this->assertSame('EmONC', $type->category->name);

        $user = User::factory()->create(['name' => 'E2E Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_assessment', 'view_assessment']);
        $user->assignRole('assessor');
        $this->actingAs($user);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $user->id,
        ]);

        // Fill every real (non-completeness) question in Kit 1 with "Yes" —
        // exercises grouped rendering, saveResponses(), and the
        // group_completeness derivation together.
        $kitSection = $type->sections()->where('code', 'emonc_emergency_kits')->firstOrFail();
        $kit1Questions = $kitSection->questions()
            ->where('group', '1. Obstetric Hemorrhage Kit')
            ->where('question_type', '!=', 'group_completeness')
            ->get();

        $data = [];
        foreach ($kit1Questions as $question) {
            $data["question_response_{$question->id}"] = 'Yes';
        }

        DynamicFormBuilder::saveResponses($assessment->id, $kitSection->id, $data);

        $completenessQuestion = $kitSection->questions()->where('question_code', 'EMONC_E_K1_COMPLETE')->firstOrFail();
        $completenessResponse = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $completenessQuestion->id)
            ->first();

        $this->assertNotNull($completenessResponse);
        $this->assertSame('Yes', $completenessResponse->response_value);
        $this->assertEquals(1, $completenessResponse->score);

        $sectionScore = AssessmentSectionScore::where('assessment_id', $assessment->id)
            ->where('assessment_section_id', $kitSection->id)
            ->first();
        $this->assertNotNull($sectionScore);
        $this->assertGreaterThan(0, $sectionScore->total_score);

        // A second, unscored section (Facility Profile) must also save
        // cleanly through the same generic path.
        $facilitySection = $type->sections()->where('code', 'emonc_facility_context')->firstOrFail();
        $categoryQuestion = $facilitySection->questions()->where('question_code', 'EMONC_A_FACILITY_CATEGORY')->firstOrFail();
        DynamicFormBuilder::saveResponses($assessment->id, $facilitySection->id, [
            "question_response_{$categoryQuestion->id}" => 'CEMONC',
        ]);
        $savedCategoryResponse = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $categoryQuestion->id)
            ->first();
        $this->assertSame('CEMONC', $savedCategoryResponse->response_value);

        // Generic PDF export must not throw for this template.
        $pdf = app(AssessmentPdfReportService::class)->generateExecutiveReport($assessment->fresh());
        $this->assertNotNull($pdf);

        // Generic CSV export must not throw either.
        $csv = app(\App\Services\AssessmentExportService::class)->exportAssessmentToCSV($assessment->fresh());
        $this->assertIsString($csv);
        $this->assertNotSame('', $csv);

        // The read-only summary page must render without error for this template.
        $summaryUrl = \App\Filament\Resources\AssessmentResource::getUrl('summary', ['record' => $assessment->id]);
        $this->get($summaryUrl)->assertOk();
    }
}
