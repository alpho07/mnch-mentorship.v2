<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class QualityOfCareSeeder extends Seeder
{
    private const QUESTIONS = [
        ['QOC_NEONATAL_AUDITS', 'Are audits conducted to review neonatal deaths (Verify using audit minutes)', 0],
        ['QOC_NEONATAL_MOH527', 'Are they documented on the Neonatal death review form MoH 527', 1],
        ['QOC_NEONATAL_KHIS_UPLOAD', 'Is the Neonatal death audit form uploaded to KHIS', 1],
        ['QOC_NEONATAL_ACTION_POINTS', 'Were the action points from the audit Implemented', 1],
        ['QOC_CHILD_AUDITS', 'Are audits conducted to review child deaths at least once a month (Verify using audit minutes)', 0],
        ['QOC_CHILD_REGISTER', 'Are they documented on the paediatric register', 1],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $section = AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'quality_of_care'],
            ['name' => 'Quality of Care', 'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 8, 'is_active' => true]
        );

        $section->update(['description' => 'Select agreed timelines: {{quality_of_care_timeline}}']);

        foreach (self::QUESTIONS as $order => [$code, $text, $indent]) {
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $code],
                [
                    'question_text' => $text,
                    'question_type' => 'yes_no',
                    'is_scored' => true,
                    'scoring_map' => ['Yes' => 1, 'No' => 0],
                    'requires_explanation_on' => ['No'],
                    'indent_level' => $indent,
                    'order' => $order + 1,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('  ✓ quality_of_care: 6 questions.');
    }
}
