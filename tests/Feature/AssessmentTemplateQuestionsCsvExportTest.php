<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Services\AssessmentExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTemplateQuestionsCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_one_row_per_question_ordered_by_section_and_question_order(): void
    {
        $type = AssessmentType::create(['name' => 'CSV Export Template', 'code' => 'CSVEXPORT1', 'version' => '1.0', 'is_active' => true]);

        $infra = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        $skills = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Skills Lab', 'code' => 'skills_lab',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 2, 'is_active' => true,
        ]);

        AssessmentQuestion::create([
            'assessment_section_id' => $infra->id, 'question_code' => 'INFRA_1',
            'question_text' => 'Has power backup?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $skills->id, 'question_code' => 'SKILLS_1',
            'question_text' => 'Has a skills lab?', 'question_type' => 'yes_no',
            'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);

        $csv = app(AssessmentExportService::class)->exportTemplateQuestionsToCSV($type);

        $this->assertStringContainsString('Section,"Question Code","Question Text",Type,Order', $csv);
        $this->assertStringContainsString('Infrastructure,INFRA_1,"Has power backup?",yes_no,1', $csv);
        $this->assertStringContainsString('"Skills Lab",SKILLS_1,"Has a skills lab?",yes_no,1', $csv);

        $infraPos = strpos($csv, 'INFRA_1');
        $skillsPos = strpos($csv, 'SKILLS_1');
        $this->assertLessThan($skillsPos, $infraPos, 'Infrastructure (section order 1) should appear before Skills Lab (order 2)');
    }
}
