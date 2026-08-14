<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class QualityOfCareSeeder extends Seeder
{
    private const NEONATAL_AUDITS_YES = ['question_code' => 'QOC_NEONATAL_AUDITS', 'operator' => 'equals', 'value' => 'Yes'];

    private const CHILD_AUDITS_YES = ['question_code' => 'QOC_CHILD_AUDITS', 'operator' => 'equals', 'value' => 'Yes'];

    private const ACTION_POINTS_NO = ['question_code' => 'QOC_NEONATAL_ACTION_POINTS', 'operator' => 'equals', 'value' => 'No'];

    // [code, text, indent, question_type, display_conditions]
    private const QUESTIONS = [
        ['QOC_NEONATAL_AUDITS', 'Are audits conducted to review neonatal deaths (Verify using audit minutes)', 0, 'yes_no', null],
        ['QOC_NEONATAL_MOH527', 'Are they documented on the Neonatal death review form MoH 527', 1, 'yes_no', self::NEONATAL_AUDITS_YES],
        ['QOC_NEONATAL_KHIS_UPLOAD', 'Is the Neonatal death audit form uploaded to KHIS', 1, 'yes_no', self::NEONATAL_AUDITS_YES],
        ['QOC_NEONATAL_ACTION_POINTS', 'Were the action points from the audit Implemented', 1, 'yes_no', self::NEONATAL_AUDITS_YES],
        ['QOC_NEONATAL_ACTION_REASONS', 'If not implemented give reasons', 1, 'text', self::ACTION_POINTS_NO],
        ['QOC_CHILD_AUDITS', 'Are audits conducted to review child deaths at least once a month (Verify using audit minutes)', 0, 'yes_no', null],
        ['QOC_CHILD_REGISTER', 'Are they documented on the paediatric register', 1, 'yes_no', self::CHILD_AUDITS_YES],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $section = AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'quality_of_care'],
            ['name' => 'Quality of Care', 'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 8, 'is_active' => true]
        );

        $section->update(['description' => 'Select agreed timelines: {{quality_of_care_timeline}}']);

        foreach (self::QUESTIONS as $order => [$code, $text, $indent, $questionType, $displayConditions]) {
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $code],
                [
                    'question_text' => $text,
                    'question_type' => $questionType,
                    'is_scored' => $questionType === 'yes_no',
                    'scoring_map' => $questionType === 'yes_no' ? ['Yes' => 1, 'No' => 0] : null,
                    'requires_explanation_on' => $questionType === 'yes_no' ? ['No'] : null,
                    'display_conditions' => $displayConditions,
                    'indent_level' => $indent,
                    'order' => $order + 1,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('  ✓ quality_of_care: 7 questions.');
    }
}
