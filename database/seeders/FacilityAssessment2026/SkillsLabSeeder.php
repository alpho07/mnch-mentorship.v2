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
        ['SKILLS_YES_POWER_BACKUP', 'Is there a power back up system?', 'yes_no'],
        ['SKILLS_YES_POWER_BACKUP_TYPE', 'Specify the power back up type', 'multi_select'],
        ['SKILLS_YES_POWER_BACKUP_TYPE_OTHER', 'Please specify the other power back up type', 'text'],
        ['SKILLS_YES_HANDWASH_SINK', 'Does the skills lab have at least 1 hand washing sink with clean running water and soap?', 'yes_no'],
        ['SKILLS_YES_VENTILATED', 'Is the space well ventilated?', 'yes_no'],
        ['SKILLS_YES_WELL_LIT', 'Is the space well lit?', 'yes_no'],
        ['SKILLS_YES_LOCKABLE_STORE', 'Does the skills lab have a lockable store or cabinet to safely maintain the skills lab essential supplies and equipment?', 'yes_no'],
        ['SKILLS_YES_FIRE_EXITS', 'Are there clearly marked fire exits?', 'yes_no'],
        ['SKILLS_YES_FIRE_EXTINGUISHERS', 'Are there clearly marked fire extinguishers?', 'yes_no'],
        ['SKILLS_YES_OFFICER_IN_CHARGE', 'Is there an officer in charge of the skills lab?', 'yes_no'],
        ['SKILLS_YES_BIOMED_MAINTENANCE', 'Is there a biomed assigned to do planned preventive maintainance and corrective maintainance?', 'yes_no'],
        ['SKILLS_YES_MONTHLY_REPORTS', 'Are there upto date monthly/quaterly reports showing activities/events held in the skills lab?', 'select'],
        ['SKILLS_YES_MANIKIN_CHILD', 'One child manikin with lungs that fill up when a BVM is used and feedback mechanism?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_INFANT', 'One infant manikin with lungs that fill up when a BVM is used?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_NEONATE', 'One neonate manikin with lungs that fill up when a BVM is used?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_PREMATURE', 'Premature mannikin - with open nose to aid in NGT insertion and is used to demonstrate use of plastic wraps and phototherapy?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_CPAP', 'One CPAP baby - has an open nose and mouth to aid in insertion of CPAP prongs and OGT?', 'yes_no'],
        ['SKILLS_YES_MANIKIN_BREAST', 'Breast model able to simulate breast milk expression(mama breast)?', 'yes_no'],
        ['SKILLS_YES_AIR_DEVICE', 'AIR device?', 'yes_no'],
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

        // $order also numbers each top-level question's text ("1. ", "2. ",
        // ...) in one continuous sequence across the gate, yes-branch,
        // Anne manikin, and no-branch questions — every updateOrCreate
        // below prepends the pre-increment $order to its own text before
        // passing $order++ as the 'order' column value.
        $order = 1;

        AssessmentQuestion::updateOrCreate(
            ['assessment_section_id' => $section->id, 'question_code' => 'SKILLS_HAS_LAB'],
            ['question_text' => "{$order}. Is there a functional skills lab?", 'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0], 'order' => $order++, 'is_active' => true]
        );

        $yesCondition = ['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'];
        $biomedCondition = ['question_code' => 'SKILLS_YES_BIOMED_MAINTENANCE', 'operator' => 'equals', 'value' => 'Yes'];

        foreach (self::YES_ITEMS as [$code, $text, $questionType]) {
            $attrs = [
                'question_text' => "{$order}. {$text}",
                'question_type' => $questionType,
                'is_scored' => true,
                'scoring_map' => $questionType === 'yes_no' ? ['Yes' => 1, 'No' => 0] : null,
                // The monthly/quarterly reports question only makes sense
                // once a biomed is assigned to maintain the lab — gated on
                // both the lab existing AND that assignment, not just the
                // lab gate every other yes-branch question uses. The power
                // back-up type dropdown only makes sense once the back-up
                // system itself is confirmed present — gated on that
                // question alone (it's already only reachable once the lab
                // gate is Yes, so re-checking the lab gate too is redundant).
                // The "specify other" box only makes sense once "Other" is
                // actually one of the picked back-up types — 'intersects'
                // because the dropdown is multi_select, not a single value.
                'display_conditions' => match ($code) {
                    'SKILLS_YES_MONTHLY_REPORTS' => ['operator' => 'and', 'conditions' => [$yesCondition, $biomedCondition]],
                    'SKILLS_YES_POWER_BACKUP_TYPE' => ['question_code' => 'SKILLS_YES_POWER_BACKUP', 'operator' => 'equals', 'value' => 'Yes'],
                    'SKILLS_YES_POWER_BACKUP_TYPE_OTHER' => ['question_code' => 'SKILLS_YES_POWER_BACKUP_TYPE', 'operator' => 'intersects', 'value' => ['Other']],
                    default => $yesCondition,
                },
                'order' => $order++,
                'is_active' => true,
            ];
            if (in_array($questionType, ['select', 'multi_select'], true)) {
                $attrs['options'] = $code === 'SKILLS_YES_POWER_BACKUP_TYPE' ? ['Generator', 'Solar', 'Other'] : ['Monthly', 'Quarterly', 'Both'];
            }
            if ($code === 'SKILLS_YES_LOCKABLE_STORE') {
                $attrs['checklist_id'] = $checklist?->id;
            }
            if ($code === 'SKILLS_YES_POWER_BACKUP') {
                $attrs['requires_explanation_on'] = ['No'];
                $attrs['explanation_label'] = 'Reason';
            }

            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $code],
                $attrs
            );
        }

        AssessmentQuestion::updateOrCreate(
            ['assessment_section_id' => $section->id, 'question_code' => 'SKILLS_YES_MANIKIN_ANNE'],
            [
                'question_text' => "{$order}. Newborn Anne Manikin that can be intubated and has an umbilicus for UVC insertion?",
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
                    'question_text' => "{$order}. {$text}",
                    'question_type' => 'yes_no',
                    'is_scored' => true,
                    'scoring_map' => ['Yes' => 1, 'No' => 0],
                    'display_conditions' => $noCondition,
                    'order' => $order++,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('  ✓ skills_lab: 24 questions (1 gate + 21 yes-branch + 2 no-branch).');
    }
}
