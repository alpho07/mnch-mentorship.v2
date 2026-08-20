<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentComparisonService;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentHtmlReportIndicatorsComparisonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_newborn_indicators_section_renders_one_column_per_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'INDREND1', 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Newborn & Paediatric Indicators', 'code' => 'newborn_paediatric_indicators',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => false, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_NEWBORN_ADMISSIONS',
            'question_text' => 'Number of newborn admissions', 'question_type' => 'number', 'group' => 'Newborn Indicators',
            'is_scored' => false, 'order' => 1, 'is_active' => true,
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

        AssessmentQuestionResponse::create(['assessment_id' => $baseline->id, 'assessment_question_id' => $question->id, 'response_value' => '10']);
        AssessmentQuestionResponse::create(['assessment_id' => $midline->id, 'assessment_question_id' => $question->id, 'response_value' => '25']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($midline);

        $this->assertStringContainsString('Number of newborn admissions', $html);
        $this->assertStringContainsString('Baseline', $html);
        $this->assertStringContainsString('Midline', $html);
    }

    public function test_comparison_data_includes_indicator_keys(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'INDREND2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonth(), 'assessor_name' => 'Test Assessor',
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($midline);

        $this->assertArrayHasKey('indicatorsNewborn', $result);
        $this->assertArrayHasKey('indicatorsPaediatric', $result);
        $this->assertArrayHasKey('indicatorsNewbornProportions', $result);
        $this->assertArrayHasKey('indicatorsPaediatricProportions', $result);
    }

    public function test_raw_count_indicator_delta_compares_the_two_most_recent_rounds(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'INDDELTA1', 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Newborn & Paediatric Indicators', 'code' => 'newborn_paediatric_indicators',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => false, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_NEWBORN_ADMISSIONS',
            'question_text' => 'Number of newborn admissions', 'question_type' => 'number', 'group' => 'Newborn Indicators',
            'is_scored' => false, 'order' => 1, 'is_active' => true,
        ]);
        $facility = Facility::factory()->create();

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonths(2), 'assessor_name' => 'Test Assessor',
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now()->subMonth(), 'assessor_name' => 'Test Assessor',
        ]);
        $endline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'endline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentQuestionResponse::create(['assessment_id' => $baseline->id, 'assessment_question_id' => $question->id, 'response_value' => '10']);
        AssessmentQuestionResponse::create(['assessment_id' => $midline->id, 'assessment_question_id' => $question->id, 'response_value' => '20']);
        AssessmentQuestionResponse::create(['assessment_id' => $endline->id, 'assessment_question_id' => $question->id, 'response_value' => '25']);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($endline);

        $row = collect($result['indicatorsNewborn'])->firstWhere('label', 'Number of newborn admissions');

        // Compares the two most recent rounds (midline -> endline: 20 -> 25),
        // not baseline -> endline, even though all three rounds are shown.
        $this->assertSame(5.0, $row['delta']['diff']);
        $this->assertSame('up', $row['delta']['direction']);
        $this->assertSame(25.0, $row['delta']['percent_change']);
        $this->assertFalse($row['delta']['is_proportion']);
    }

    public function test_proportion_indicator_delta_is_percentage_points_not_percent_change(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'INDDELTA2', 'version' => '1.0', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Newborn & Paediatric Indicators', 'code' => 'newborn_paediatric_indicators',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => false, 'order' => 1, 'is_active' => true,
        ]);
        $admissionsQ = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_NEWBORN_ADMISSIONS',
            'question_text' => 'Number of newborn admissions', 'question_type' => 'number', 'group' => 'Newborn Indicators',
            'is_scored' => false, 'order' => 1, 'is_active' => true,
        ]);
        $o2satQ = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_NEWBORN_O2SAT_TAKEN',
            'question_text' => 'Newborns with oxygen saturation taken at admission', 'question_type' => 'number', 'group' => 'Newborn Indicators',
            'is_scored' => false, 'order' => 2, 'is_active' => true,
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

        // Baseline: 5/10 = 50.0%. Midline: 18/20 = 90.0%.
        AssessmentQuestionResponse::create(['assessment_id' => $baseline->id, 'assessment_question_id' => $admissionsQ->id, 'response_value' => '10']);
        AssessmentQuestionResponse::create(['assessment_id' => $baseline->id, 'assessment_question_id' => $o2satQ->id, 'response_value' => '5']);
        AssessmentQuestionResponse::create(['assessment_id' => $midline->id, 'assessment_question_id' => $admissionsQ->id, 'response_value' => '20']);
        AssessmentQuestionResponse::create(['assessment_id' => $midline->id, 'assessment_question_id' => $o2satQ->id, 'response_value' => '18']);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($midline);

        $row = collect($result['indicatorsNewbornProportions'])
            ->firstWhere('label', 'Proportion of newborns who had their oxygen saturation taken at admission');

        // 90.0% - 50.0% = 40.0 percentage points, not a "percent change of a percentage".
        $this->assertSame(40.0, $row['delta']['diff']);
        $this->assertSame('up', $row['delta']['direction']);
        $this->assertNull($row['delta']['percent_change']);
        $this->assertTrue($row['delta']['is_proportion']);
    }
}
