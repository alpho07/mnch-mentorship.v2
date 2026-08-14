<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class IndicatorsSeeder extends Seeder
{
    private const EMR_GATE = ['question_code' => 'INFOSYS_EMR_ACCESS', 'operator' => 'equals', 'value' => 'Yes'];

    private const NEWBORN = [
        ['IND_NEWBORN_HEADING', 'Newborn Inpatient Files (sample 30 newborn inpatient files) (Newborns admitted in the newborn unit)', 0],
        ['IND_NEWBORN_ADMISSIONS', 'Total number of newborn admissions for the last complete month', 0],
        ['IND_NEWBORN_HYPOTHERMIA', 'Total number of newborns with hypothermia at admission (temp <36.5)', 0],
        ['IND_NEWBORN_O2SAT_TAKEN', 'Total number of newborns who had their oxygen saturation taken at admission (sample 30 newborn files)', 1],
        ['IND_NEWBORN_RBS_TAKEN', 'Total number of admitted newborns who had their RBS taken at admission (sample 30 newborn files)', 0],
        ['IND_NEWBORN_HEADTOTOE', 'Total number of newborns who had a head to toe exam done and recorded in the newborn file(sample 30 newborn files)', 1],
        ['IND_NEWBORN_BIRTH_ASPHYXIA', 'Total number of newborns who had a diagnosis of birth asphyxia (Total admissions for the month)', 0],
        ['IND_NEWBORN_LT34_ADMISSIONS', 'Total number of newborns <34 weeks admitted in the last complete month?', 0],
        ['IND_NEWBORN_LT34_CAFFEINE', 'Total number of newborns <34 weeks initiated on caffeine citrate last complete month?', 0],
        ['IND_NEWBORN_ANTENATAL_CORTICOSTEROIDS', "Total number of mothers with preterm newborns <34 weeks gestation who received at least one dose of antenatal corticosteroids (Baby's admission notes)", 0],
        ['IND_NEWBORN_LT32_ADMISSIONS', 'Total number of newborns <32 weeks admitted in the last complete month', 0],
        ['IND_NEWBORN_LT32_CPAP', 'Total number of newborns <32 weeks initiated on CPAP last complete month (inpatient newborn register)', 0],
        ['IND_NEWBORN_LT2500G_KMC', 'Total number of newborns < 2500g initiated on KMC last complete month (KMC register)', 0],
        ['IND_NEWBORN_KMC_WITHIN_2HRS', 'Total number of newborns initiated on KMC within 2 hours after birth last complete month (KMC register)', 0],
        ['IND_NEWBORN_KMC_DURING_STAY', 'Total number of newborns initiated on KMC during their hospital stay during the last complete month (KMC register)', 0],
    ];

    private const PAEDIATRIC = [
        ['IND_PAED_HEADING', 'Paediatric Inpatient Files (sample 30 paediatric inpatient files) (Children admitted in the paediatric ward)', 0],
        ['IND_PAED_ADMISSIONS', 'Total number of paediatric admissions for the last complete month) (Paediatric admission file)', 0],
        ['IND_PAED_O2SAT_TAKEN', 'How many had an oxygen saturation taken at admission?', 0],
        ['IND_PAED_HYPOXEMIA', 'Of these, how many had an oxygen saturation less than <90%?', 1],
        ['IND_PAED_HYPOXEMIA_OXYGEN_STARTED', 'Of the ones with SPO2 <90%, how many were started on oxygen?', 1],
        ['IND_PAED_SEVERE_PNEUMONIA_OXYGEN', 'Total Number of children under 5 years with severe pneumonia initiated on oxygen', 0],
        ['IND_PAED_SEVERE_PNEUMONIA_ANTIBIOTICS', 'Total Number of children under 5 years with severe pneumonia initiated on Benzyl Penicillin and Gentamicin', 0],
        ['IND_PAED_PNEUMONIA_AMOXICILLIN', 'Total Number of children under 5 years with pneumonia initiated on Amoxicillin DT', 0],
        ['IND_PAED_SEVERE_PNEUMONIA_DEATHS', 'Total Number of children under 5 years with severe pneumonia who died', 0],
        ['IND_PAED_DIARRHOEA_ORS', 'Total Number of children under 5 years with diarrhoea treated with ORS/Zinc co-pack( MoH 204A)', 0],
        ['IND_PAED_HYPOVOLEMIC_SHOCK', 'Total Number of children under 5 years with hypovolemic shock due to diarrhoea treated with correct volume of isotonic fluid (Paediatric admission file)', 0],
        ['IND_PAED_RBS', 'Total Number of children under 5 years admitted with an RBS measurement (Paediatric admission file)', 0],
        ['IND_PAED_MALNUTRITION_INPATIENT', 'Total Number of children under 5 years screened for malnutrition (MUAC/WHZ/nutritional oedema) in the inpatient department (Paediatric admission file)', 0],
        ['IND_PAED_MALNUTRITION_OUTPATIENT', 'Total Number of children under 5 years screened for malnutrition (MUAC/WHZ/nutritional oedema) in the outpatient department (MoH 511 CWC)', 0],
        ['IND_PAED_T1DM_BASAL_BOLUS', 'Total Number of patients aged 0-18 years with type 1 DM on basal bolus regimen (NCD register)', 0],
        ['IND_PAED_DKA_DEATHS', 'Total Number of childen aged 0-18 years admitted with DKA who died (Paediatric admission file) and Inpatient admission file', 0],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        $section = AssessmentSection::updateOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'newborn_paediatric_indicators'],
            ['name' => 'Newborn & Paediatric Indicators', 'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => false, 'order' => 9, 'is_active' => true]
        );

        $order = 0;
        $groups = [
            'Newborn Indicators' => self::NEWBORN,
            'Paediatric Indicators' => self::PAEDIATRIC,
        ];

        $headingCodes = ['IND_NEWBORN_HEADING', 'IND_PAED_HEADING'];

        foreach ($groups as $groupLabel => $questions) {
            foreach ($questions as [$code, $text, $indent]) {
                $order++;
                $isHeading = in_array($code, $headingCodes, true);
                AssessmentQuestion::updateOrCreate(
                    ['assessment_section_id' => $section->id, 'question_code' => $code],
                    [
                        'question_text' => $text,
                        // A pure descriptive title placed before its
                        // group's indicator questions — no input, never
                        // produces a response (see
                        // QuestionFieldBuilder::buildHeadingField()).
                        'question_type' => $isHeading ? 'heading' : 'number',
                        'is_scored' => false,
                        'indent_level' => $indent,
                        // Every question in one half shares this group, so
                        // they render as one collapsible "Newborn
                        // Indicators"/"Paediatric Indicators" section
                        // (buildGroupFieldset()'s >7-fields path) instead
                        // of one long mixed list — the indent-level-1 items
                        // within each half are never consecutive to each
                        // other, so LineItemGrouper still letters none of
                        // them, same as before this change.
                        'group' => $groupLabel,
                        'display_conditions' => in_array($code, ['IND_NEWBORN_O2SAT_TAKEN', 'IND_NEWBORN_HEADTOTOE'], true) ? self::EMR_GATE : null,
                        'order' => $order,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info("  ✓ newborn_paediatric_indicators: {$order} questions (unscored).");
    }
}
