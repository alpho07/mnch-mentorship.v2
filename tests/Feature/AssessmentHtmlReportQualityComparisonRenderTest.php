<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentHtmlReportQualityComparisonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_quality_of_care_section_renders_one_column_per_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'QREND1', 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Quality of Care', 'code' => 'quality_of_care',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'QOC_AUDIT_TEST',
            'question_text' => 'Mortality audits conducted?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $facility = Facility::factory()->create();

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonth(), 'assessor_name' => 'Test Assessor',
        ]);
        $endline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'endline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $baseline->id, 'assessment_question_id' => $question->id, 'response_value' => 'No']);
        AssessmentQuestionResponse::create(['assessment_id' => $endline->id, 'assessment_question_id' => $question->id, 'response_value' => 'Yes']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($endline);

        $this->assertStringContainsString('Mortality audits conducted?', $html);
        $this->assertStringContainsString('Baseline', $html);
        $this->assertStringContainsString('Endline', $html);
    }
}
