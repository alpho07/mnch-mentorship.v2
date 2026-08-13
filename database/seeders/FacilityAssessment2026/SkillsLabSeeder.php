<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class SkillsLabSeeder extends Seeder
{
    private const YES_ITEMS = [
        ['SKILLS_YES_POWER_OUTLETS', 'Does the Skills lab have at least 4 power outlets?', 'yes_no'],
        ['SKILLS_YES_POWER_BACKUP', 'Is there a power back up system? If yes Specify', 'select'],
        ['SKILLS_YES_HANDWASH_SINK', 'Does the skills lab have at least 1 hand washing sink with running water and soap', 'yes_no'],
        ['SKILLS_YES_VENTILATED', 'Is the space well ventilated?', 'yes_no'],
        ['SKILLS_YES_WELL_LIT', 'Is the space well lit?', 'yes_no'],
        ['SKILLS_YES_LOCKABLE_STORE', 'Does the skills lab have a lockable store or cabinet to safely maintain the skills lab essential supplies and equipment?', 'yes_no'],
        ['SKILLS_YES_FIRE_EXITS', 'Are there clearly marked fire exits and fire extinguishers?', 'yes_no'],
        ['SKILLS_YES_OFFICER_IN_CHARGE', 'Is there an officer in charge of the skills lab?', 'yes_no'],
        ['SKILLS_YES_BIOMED_MAINTENANCE', 'Is there a biomed assigned to do planned preventive maintainance and corrective maintainance?', 'yes_no'],
        ['SKILLS_YES_MONTHLY_REPORTS', 'Are there upto date monthly/quaterly reports showing activities/events held in the skills lab?', 'select'],
        ['SKILLS_YES_MANIKIN_CHILD', 'One child manikin with lungs that fill up when a BVM is used and feedback mechanism', 'yes_no'],
        ['SKILLS_YES_MANIKIN_INFANT', 'One infant manikin with lungs that fill up when a BVM is used?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_NEONATE', 'One neonate manikin with lungs that fill up when a BVM is used?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_PREMATURE', 'Premature mannikin - with open nose to aid in NGT insertion and is used to demonstrate use of plastic wraps and phototherapy', 'yes_no'],
        ['SKILLS_YES_MANIKIN_CPAP', 'One CPAP baby - has an open nose and mouth to aid in insertion of CPAP prongs and OGT?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_BREAST', 'Breast model able to simulate breast milk expression(mama breast)', 'yes_no'],
        ['SKILLS_YES_AIR_DEVICE', 'AIR device', 'yes_no'],
        // SKILLS_YES_MANIKIN_ANNE handled separately below (extra NICU condition).
    ];

    private const NO_ITEMS = [
        ['SKILLS_NO_ROOM_SPACE', 'Is there a room/space used for skills teaching and simulation?'],
        ['SKILLS_NO_LOCKABLE_STORAGE', 'Is there a lockable storage area for the equipment to be used in skills teaching and simulation?'],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $section = AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'skills_lab'],
            ['name' => 'Skills Lab', 'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 4, 'is_active' => true]
        );
        $checklist = AssessmentChecklist::where('assessment_type_id', $type->id)->where('title', 'Skills Lab Checklist Requirements')->first();

        $order = 1;

        AssessmentQuestion::updateOrCreate(
            ['assessment_section_id' => $section->id, 'question_code' => 'SKILLS_HAS_LAB'],
            ['question_text' => 'Is there a functional skills lab?', 'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0], 'order' => $order++, 'is_active' => true]
        );

        $yesCondition = ['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'];

        foreach (self::YES_ITEMS as [$code, $text, $questionType]) {
            $attrs = [
                'question_text' => $text,
                'question_type' => $questionType,
                'is_scored' => true,
                'scoring_map' => $questionType === 'yes_no' ? ['Yes' => 1, 'No' => 0] : null,
                'display_conditions' => $yesCondition,
                'order' => $order++,
                'is_active' => true,
            ];
            if ($questionType === 'select') {
                $attrs['options'] = $code === 'SKILLS_YES_POWER_BACKUP' ? ['Generator', 'Solar', 'Other'] : ['Monthly', 'Quarterly', 'Both'];
            }
            if ($code === 'SKILLS_YES_LOCKABLE_STORE') {
                $attrs['checklist_id'] = $checklist?->id;
            }

            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $code],
                $attrs
            );
        }

        AssessmentQuestion::updateOrCreate(
            ['assessment_section_id' => $section->id, 'question_code' => 'SKILLS_YES_MANIKIN_ANNE'],
            [
                'question_text' => 'Newborn Anne Manikin that can be intubated and has an umbilicus for UVC insertion',
                'question_type' => 'yes_no',
                'is_scored' => true,
                'scoring_map' => ['Yes' => 1, 'No' => 0],
                'display_conditions' => [
                    'operator' => 'and',
                    'conditions' => [
                        $yesCondition,
                        ['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'],
                    ],
                ],
                'order' => $order++,
                'is_active' => true,
            ]
        );

        $noCondition = ['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'No'];

        foreach (self::NO_ITEMS as [$code, $text]) {
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $code],
                [
                    'question_text' => $text,
                    'question_type' => 'yes_no',
                    'is_scored' => true,
                    'scoring_map' => ['Yes' => 1, 'No' => 0],
                    'display_conditions' => $noCondition,
                    'order' => $order++,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('  ✓ skills_lab: 21 questions (1 gate + 18 yes-branch + 2 no-branch).');
    }
}
