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

class AssessmentHtmlReportComparisonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_infrastructure_section_renders_one_column_per_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'REND1', 'version' => '1.0', 'is_active' => true]);
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

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonth(), 'assessor_name' => 'Test Assessor',
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $baseline->id, 'assessment_question_id' => $question->id, 'response_value' => 'No']);
        AssessmentQuestionResponse::create(['assessment_id' => $midline->id, 'assessment_question_id' => $question->id, 'response_value' => 'Yes']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($midline);

        $this->assertStringContainsString('Has power backup?', $html);
        $this->assertStringContainsString('Baseline', $html);
        $this->assertStringContainsString('Midline', $html);
    }
}
