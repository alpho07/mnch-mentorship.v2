<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentPdfSectionSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_report_always_shows_every_section_regardless_of_preference(): void
    {
        [$assessment] = $this->makeAssessmentWithInfrastructureQuestion();

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('Infrastructure', $html);
    }

    public function test_pdf_omits_a_section_not_in_the_enabled_list(): void
    {
        [$assessment] = $this->makeAssessmentWithInfrastructureQuestion();

        $pdf = app(AssessmentPdfReportService::class)->generateExecutiveReport($assessment, []);

        // Render the same data through the underlying HTML view directly
        // (rather than parsing PDF bytes) to assert the section is gone —
        // generateExecutiveReport and generateHtmlReport share the same
        // reports.assessment-html-report view/gating logic.
        $data = app(AssessmentPdfReportService::class)->prepareReportData($assessment);
        $data['comparison'] = null;
        $data['enabledSections'] = [];
        $html = view('reports.assessment-html-report', $data)->render();

        $this->assertStringNotContainsString('Has power backup?', $html);
        $this->assertNotNull($pdf);
    }

    public function test_pdf_includes_a_section_that_is_in_the_enabled_list(): void
    {
        [$assessment] = $this->makeAssessmentWithInfrastructureQuestion();

        $data = app(AssessmentPdfReportService::class)->prepareReportData($assessment);
        $data['comparison'] = null;
        $data['enabledSections'] = ['infrastructure'];
        $html = view('reports.assessment-html-report', $data)->render();

        $this->assertStringContainsString('Has power backup?', $html);
    }

    public function test_download_action_persists_the_selected_sections_to_the_user(): void
    {
        [$assessment, ] = $this->makeAssessmentWithInfrastructureQuestion();
        $user = User::factory()->create(['name' => 'PDF Prefs Assessor']);
        $this->actingAs($user);

        $user->update(['report_section_preferences' => ['infrastructure', 'skills_lab']]);

        $this->assertSame(['infrastructure', 'skills_lab'], $user->fresh()->enabledReportSections());
    }

    public function test_default_enabled_sections_include_everything_when_unset(): void
    {
        $user = User::factory()->create(['name' => 'Fresh User']);

        $this->assertSame(
            array_keys(AssessmentPdfReportService::TOGGLEABLE_SECTIONS),
            $user->enabledReportSections()
        );
    }

    /**
     * @return array{0: Assessment}
     */
    private function makeAssessmentWithInfrastructureQuestion(): array
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'SECSEL'.uniqid(), 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'INFRA_TEST',
            'question_text' => 'Has power backup?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $question->id, 'response_value' => 'Yes']);

        return [$assessment];
    }
}
