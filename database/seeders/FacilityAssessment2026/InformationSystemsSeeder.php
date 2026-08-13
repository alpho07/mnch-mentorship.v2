<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class InformationSystemsSeeder extends Seeder
{
    private const MOH_FORMS = [
        ['MOH_204A', 'MoH 204 A: Out- Patient register'],
        ['NCD_REGISTER', 'NCD Register'],
        ['MOH_661_NEONATAL', 'MoH 661 Neonatal death notification'],
        ['MOH_670', 'MoH 670 Child and Adolescent death notification'],
        ['NEONATAL_CHILD_DEATH_REGISTER', 'Neonatal Child and Adolescent death register'],
        ['MOH_282', 'MoH 282 ORT Corner register'],
        ['MOH_671', 'MoH 671 child and adolescent death review forms'],
        ['MORTALITY_LINE_LIST', 'Neonatal Child and Adolescent mortality line list'],
        ['MOH_511', 'MoH 511 CWC register'],
        ['MOH_333', 'MoH 333: Maternity Register'],
        ['KMC_REGISTER', 'KMC Register'],
        ['THEATRE_MATERNITY_REGISTER', 'Theatre Maternity register'],
        ['MOH_373', 'MoH 373: Neonatal Inpatient register'],
        ['MOH_301', 'MoH 301: In-Patient admission Register'],
        ['MOH_378', 'MoH 378 Neonatal admission file'],
        ['MOH_379', 'MoH 379 Paediatric admision file'],
        ['MOH_377', 'MoH 377: Paediatric Admission register'],
        ['MOH_711', 'MoH 711 Integrated summary Tool'],
        ['MOH_661_DEATH_NOTIFICATION', 'MoH 661: Neonatal death notification form'],
        ['D1_DEATH_NOTIFICATION', 'D1 -Death Notification'],
        ['B1_BIRTH_NOTIFICATION', 'B1 -Birth Notification'],
        ['MORTALITY_REGISTER', 'Mortality registers/record for mortalities (Death register)'],
        ['MONTHLY_SUMMARY_NEWBORNS', 'Montlhy summary forms for Newborns'],
        ['MONTHLY_SUMMARY_PAEDIATRICS', 'Monthly summary forms for paediatrics'],
    ];

    private const EMR_REPORTS = [
        ['INFOSYS_EMR_REPORT_711', 'MoH 711 Integrated summary Tool'],
        ['INFOSYS_EMR_REPORT_NEONATAL_MORTALITY', 'Neonatal Mortality Summary'],
        ['INFOSYS_EMR_REPORT_MORTALITY_LINE_LIST', 'Neonatal Child and Adolescent mortality line list'],
        ['INFOSYS_EMR_REPORT_B1', 'B1 -Birth Notification'],
        ['INFOSYS_EMR_REPORT_D1', 'D1 -Death Notification'],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $section = AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'information_systems'],
            ['name' => 'Information System and Record Keeping For Monitoring', 'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 7, 'is_active' => true]
        );

        $order = 0;
        $create = function (array $attrs) use ($section, &$order) {
            $order++;
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $attrs['question_code']],
                array_merge(['question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0], 'requires_explanation_on' => ['No'], 'order' => $order, 'is_active' => true], $attrs)
            );
        };

        $create(['question_code' => 'INFOSYS_DOC_TYPE', 'question_text' => 'What type of documentation is the facility using', 'question_type' => 'select', 'options' => ['Paper based', 'EMR', 'Hybrid'], 'scoring_map' => null]);
        $create(['question_code' => 'INFOSYS_PAPER_AVAIL_COMPLETE', 'question_text' => 'If paper based/Hybrid ask about the availability and completeness of standardized data collection and summary tools', 'display_conditions' => ['question_code' => 'INFOSYS_DOC_TYPE', 'operator' => 'in', 'value' => ['Paper based', 'Hybrid']]]);

        foreach (self::MOH_FORMS as [$codePrefix, $formName]) {
            $group = "Data Collection Tools & Registers|Form|{$formName}";
            $create(['question_code' => "{$codePrefix}_AVAILABLE", 'question_text' => 'Available', 'group' => $group]);
            $create(['question_code' => "{$codePrefix}_COMPLETE", 'question_text' => 'Complete', 'group' => $group]);
        }

        $create(['question_code' => 'INFOSYS_KHIS_UPLOAD', 'question_text' => 'Is data uploaded to KHIS']);
        $create(['question_code' => 'INFOSYS_KHIS_RESPONSIBLE', 'question_text' => 'Is there a person responsible for neonatal data entry into the KHIS Tracker?']);
        $create(['question_code' => 'INFOSYS_USES_EMR', 'question_text' => 'If the facility is using EMR: If Yes']);

        $emrCondition = ['question_code' => 'INFOSYS_USES_EMR', 'operator' => 'equals', 'value' => 'Yes'];
        foreach (self::EMR_REPORTS as [$code, $reportName]) {
            $create(['question_code' => $code, 'question_text' => "Does the EMR generate the following Reports: {$reportName}", 'display_conditions' => $emrCondition]);
        }
        $create(['question_code' => 'INFOSYS_EMR_ACCESS', 'question_text' => 'Does the EMR allow access to the patient records to verify Information', 'display_conditions' => $emrCondition]);
        $create(['question_code' => 'INFOSYS_EMR_KHIS_UPLOAD', 'question_text' => 'Is data uploaded to KHIS', 'display_conditions' => $emrCondition]);

        $create(['question_code' => 'INFOSYS_ATTENDANCE_REGISTER', 'question_text' => 'Is there an upto date attendance register showing the date, time, mentees name & contact, and skills to be taught? (check)', 'help_text' => 'Does Not appear in baseline']);
        $create(['question_code' => 'INFOSYS_ASSESSMENT_RECORDS', 'question_text' => 'Is there an upto date record of all assessments done - which mentees, which area of assessment, by whom, recommendations after assessments (check)', 'help_text' => 'Does Not appear in baseline']);
        $create(['question_code' => 'INFOSYS_FEEDBACK_MECHANISM', 'question_text' => 'Is there a mechanism to collect feedback on the mentorship program(feedback forms,compliment / complaint register)']);
        $create(['question_code' => 'INFOSYS_MENTORSHIP_DATA_ENTRY', 'question_text' => 'Is there a person responsible for mentorship data entry into the electronic platform?']);
        $create(['question_code' => 'INFOSYS_INTERNET', 'question_text' => 'Is there internet Availability?']);

        $this->command->info("  ✓ information_systems: {$order} questions (incl. 24 MoH-form Available/Complete pairs).");
    }
}
