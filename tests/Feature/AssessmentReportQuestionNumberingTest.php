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

class AssessmentReportQuestionNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_infrastructure_questions_are_numbered_sequentially(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'NUM1', 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $q1 = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'INFRA_Q1',
            'question_text' => 'Has power backup?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $q2 = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'INFRA_Q2',
            'question_text' => 'Has running water?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 2, 'is_active' => true,
        ]);
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $q1->id, 'response_value' => 'Yes']);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $q2->id, 'response_value' => 'No']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('1. Has power backup?', $html);
        $this->assertStringContainsString('2. Has running water?', $html);
    }

    public function test_indented_follow_up_question_is_visually_indented_and_still_numbered(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'NUM2', 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Information Systems', 'code' => 'information_systems',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $gate = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'INFOSYS_GATE',
            'question_text' => 'Uses an EMR?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 1, 'indent_level' => 0, 'is_active' => true,
        ]);
        $followUp = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'INFOSYS_FOLLOWUP',
            'question_text' => 'Does the EMR generate reports?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 2, 'indent_level' => 1,
            'display_conditions' => ['question_code' => 'INFOSYS_GATE', 'operator' => 'equals', 'value' => 'Yes'],
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $gate->id, 'response_value' => 'Yes']);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $followUp->id, 'response_value' => 'Yes']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('1. Uses an EMR?', $html);
        $this->assertStringContainsString('2. Does the EMR generate reports?', $html);
        $this->assertStringContainsString('padding-left: 28px;', $html);
    }

    public function test_stale_baked_in_numbering_in_question_text_does_not_double_up(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'NUM3', 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        // Simulates a legacy row seeded before numbering became a
        // report-rendering concern — question_text itself still starts
        // with "11. ".
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'INFRA_STALE',
            'question_text' => '11. Is there a triage area in the outpatient department?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $question->id, 'response_value' => 'Yes']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('1. Is there a triage area in the outpatient department?', $html);
        $this->assertStringNotContainsString('1. 11. Is there a triage area', $html);
        $this->assertStringNotContainsString('11. 11.', $html);
    }
}
