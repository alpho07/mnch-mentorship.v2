<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Indicators\IndicatorReportType;
use App\Models\Indicators\IndicatorFrequency;
use App\Models\Indicators\IndicatorGroup;
use App\Models\Indicators\Indicator;

/**
 * Seeds all indicator configuration from:
 * MOH Newborn and Paed Modules and Indicators.docx
 *
 * Structure:
 *   IndicatorReportType  → Newborn / Paediatric
 *   IndicatorFrequency   → Monthly / Quarterly / Annually
 *   IndicatorGroup       → Per module (Module 1: IPC, Module 2: IFCDC, ...)
 *   Indicator            → Each row in the M&E tables
 */
class IndicatorsSeeder extends Seeder {

    public function run(): void {
        $this->seedFrequencies();
        $this->seedReportTypes();
        $this->seedNewbornIndicators();
        $this->seedPaediatricIndicators();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Frequencies
    // ──────────────────────────────────────────────────────────────────────────

    private function seedFrequencies(): void {
        $frequencies = [
            ['code' => 'monthly', 'name' => 'Monthly', 'dhis2_period_type' => 'Monthly', 'sort_order' => 1],
            ['code' => 'quarterly', 'name' => 'Quarterly', 'dhis2_period_type' => 'Quarterly', 'sort_order' => 2],
            ['code' => 'annually', 'name' => 'Annually', 'dhis2_period_type' => 'Yearly', 'sort_order' => 3],
        ];

        foreach ($frequencies as $data) {
            IndicatorFrequency::updateOrCreate(['code' => $data['code']], $data);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Report Types
    // ──────────────────────────────────────────────────────────────────────────

    private function seedReportTypes(): void {
        $types = [
            [
                'code' => 'newborn',
                'name' => 'Newborn Indicators',
                'description' => 'Monitoring & Evaluation indicators for the Newborn Unit (NBU/KMC). Covers IPC, IFCDC, Essential Newborn Care, Oxygen Therapy, Thermoregulation, Resuscitation, Danger Signs & Sepsis, Care of Small & Sick Newborns, Neonatal Jaundice, Hypoglycemia, Feeding, and Documentation.',
                'color' => 'blue',
                'icon' => 'heroicon-o-heart',
                'sort_order' => 1,
            ],
            [
                'code' => 'paediatric',
                'name' => 'Paediatric Indicators',
                'description' => 'Monitoring & Evaluation indicators for Paediatric Inpatient and Outpatient services. Covers Triage, BLS, Oxygen Therapy, Respiratory Distress, Dehydration, Nutrition/SAM, Altered Consciousness, Diabetes, and Documentation.',
                'color' => 'green',
                'icon' => 'heroicon-o-user-group',
                'sort_order' => 2,
            ],
        ];

        foreach ($types as $typeData) {
            $type = IndicatorReportType::updateOrCreate(['code' => $typeData['code']], $typeData);

            // Both types use monthly + quarterly reporting
            $monthly = IndicatorFrequency::where('code', 'monthly')->first();
            $quarterly = IndicatorFrequency::where('code', 'quarterly')->first();
            $type->frequencies()->syncWithoutDetaching([$monthly->id, $quarterly->id]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // NEWBORN INDICATORS
    // ──────────────────────────────────────────────────────────────────────────

    private function seedNewbornIndicators(): void {
        $reportType = IndicatorReportType::where('code', 'newborn')->firstOrFail();

        $groups = [
            // ── MODULE 1 ──────────────────────────────────────────────────────
            [
                'code' => 'nb_module_1',
                'name' => 'Module 1: IPC',
                'description' => '5 moments of hand hygiene and hand hygiene techniques',
                'sort_order' => 1,
                'indicators' => [
                // No specific M&E indicators listed in doc for Modules 1 & 2 —
                // process indicators tracked via mentorship session records.
                // Placeholder for configurable future indicators:
                ],
            ],
            // ── MODULE 2 ──────────────────────────────────────────────────────
            [
                'code' => 'nb_module_2',
                'name' => 'Module 2: IFCDC',
                'description' => 'Swaddling, nesting, pain management, sensory environment, family involvement',
                'sort_order' => 2,
                'indicators' => [],
            ],
            // ── MODULE 3: ENC ─────────────────────────────────────────────────
            [
                'code' => 'nb_module_3',
                'name' => 'Module 3: Essential Newborn Care',
                'description' => 'Immediate and subsequent ENC',
                'sort_order' => 3,
                'indicators' => [
                    [
                        'code' => '1',
                        'name' => 'Proportion of mothers with preterms below 34 weeks gestation, admitted in the NBU, who received at least one dose of antenatal corticosteroids',
                        'short_name' => 'Antenatal corticosteroids (<34 wks)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of mothers with preterms, admitted in the NBU below 34 weeks gestation who received at least one dose of antenatal corticosteroids',
                        'denominator_label' => 'Total number of mothers with preterms below 34 weeks admitted to the newborn unit',
                        'source_document' => 'Newborn transfer form; In-patient neonatal register; Newborn Admission Record',
                        'source_document_code' => 'NBU_TRANSFER,NAR,INPATIENT_NEO',
                        'display_hint' => 'Count mothers of preterm babies <34 weeks. Check antenatal corticosteroid administration records.',
                        'sort_order' => 10,
                    ],
                ],
            ],
            // ── MODULE 4: OXYGEN ─────────────────────────────────────────────
            [
                'code' => 'nb_module_4',
                'name' => 'Module 4: Oxygen Therapy',
                'description' => 'Identify hypoxemia, use of pulse oximetry, O2 delivery devices for the newborn, oxygen blenders',
                'sort_order' => 4,
                'indicators' => [
                    [
                        'code' => '2',
                        'name' => 'Proportion of newborns <2000g weight who were initiated on KMC within 2 hours of birth admitted to the KMC/NBU',
                        'short_name' => 'KMC initiation within 2hrs (<2000g)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of newborns <2000g weight who were initiated on KMC within 2 hours of birth',
                        'denominator_label' => 'Total number of newborns <2000g weight admitted to the KMC/NBU',
                        'source_document' => 'KMC register',
                        'source_document_code' => 'KMC_REG',
                        'display_hint' => 'Check KMC register for time of KMC initiation relative to birth time.',
                        'sort_order' => 10,
                    ],
                    [
                        'code' => '3',
                        'name' => 'Proportion of newborns <2000g weight who were on KMC in the NBU/KMC',
                        'short_name' => 'Newborns on KMC (<2000g)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of newborns <2000g weight who were on KMC',
                        'denominator_label' => 'Total number of newborns <2000g weight admitted in the newborn unit',
                        'source_document' => 'In-patient neonatal register; In-patient neonatal register',
                        'source_document_code' => 'INPATIENT_NEO',
                        'display_hint' => 'Count all newborns <2000g who were placed on KMC at any point during the reporting period.',
                        'sort_order' => 20,
                    ],
                ],
            ],
            // ── MODULE 5: THERMOREGULATION ───────────────────────────────────
            [
                'code' => 'nb_module_5',
                'name' => 'Module 5: Thermoregulation',
                'description' => 'Risk factors, ways of losing heat, how to minimize heat loss, use of radiant warmer, use of the incubator',
                'sort_order' => 5,
                'indicators' => [
                    [
                        'code' => '4a',
                        'name' => 'Proportion of preterms <32 weeks gestation who were initiated on CPAP in the newborn unit',
                        'short_name' => 'CPAP initiation (<32 wks)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of preterms <32 weeks gestation who were initiated on CPAP in the newborn unit',
                        'denominator_label' => 'Total number of preterms <32 weeks gestation admitted in the newborn unit',
                        'source_document' => 'In-patient neonatal register',
                        'source_document_code' => 'INPATIENT_NEO',
                        'sort_order' => 10,
                    ],
                    [
                        'code' => '4b',
                        'name' => 'Proportion of neonates initiated on CPAP with continuous oxygen saturation monitoring in the newborn unit',
                        'short_name' => 'CPAP with SpO2 monitoring',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates initiated on CPAP with continuous oxygen saturation monitoring',
                        'denominator_label' => 'Total number of neonates initiated on CPAP in the newborn unit',
                        'source_document' => 'Comprehensive newborn monitoring chart; Health facility assessment',
                        'source_document_code' => 'NEO_MONITORING',
                        'sort_order' => 20,
                    ],
                    [
                        'code' => '4c',
                        'name' => 'Proportion of neonates on CPAP who were successfully weaned off in the newborn unit',
                        'short_name' => 'CPAP successful weaning',
                        'indicator_type' => 'proportion',
                        'category' => 'outcome',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates on CPAP who were successfully weaned off',
                        'denominator_label' => 'Total number of neonates initiated on CPAP in the newborn unit',
                        'source_document' => 'In-patient file; In-patient neonatal register',
                        'source_document_code' => 'INPATIENT_NEO',
                        'display_hint' => 'Facility to collect. Check in-patient file for weaning documentation.',
                        'sort_order' => 30,
                    ],
                ],
            ],
            // ── MODULE 6: RESUSCITATION ──────────────────────────────────────
            [
                'code' => 'nb_module_6',
                'name' => 'Module 6: Newborn Resuscitation',
                'description' => 'Preparation (including radiant warmer and suction machine), initial stabilization, ABC management, post resuscitation care',
                'sort_order' => 6,
                'indicators' => [
                    [
                        'code' => '5a',
                        'name' => 'Proportion of neonates <34 weeks gestation who received prophylactic caffeine citrate in the newborn unit',
                        'short_name' => 'Prophylactic caffeine citrate (<34 wks)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates <34 weeks gestation who received prophylactic caffeine citrate',
                        'denominator_label' => 'Total number of neonates <34 weeks gestation in the newborn unit',
                        'source_document' => 'In-patient neonatal register; NAR',
                        'source_document_code' => 'INPATIENT_NEO,NAR',
                        'sort_order' => 10,
                    ],
                    [
                        'code' => '5b',
                        'name' => 'Proportion of neonates <34 weeks gestation who received complete dose caffeine citrate in the newborn unit',
                        'short_name' => 'Complete caffeine citrate dose (<34 wks)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates <34 weeks gestation who received complete dose of caffeine citrate',
                        'denominator_label' => 'Total number of neonates <34 weeks who received caffeine citrate in the newborn unit',
                        'source_document' => 'In-patient file; In-patient neonatal register',
                        'source_document_code' => 'INPATIENT_NEO',
                        'display_hint' => 'Facility to collect. Check in-patient file for complete dosing records.',
                        'sort_order' => 20,
                    ],
                ],
            ],
            // ── MODULE 7: DANGER SIGNS & SEPSIS ─────────────────────────────
            [
                'code' => 'nb_module_7',
                'name' => 'Module 7: Danger Signs and Sepsis',
                'description' => 'Identification of danger signs, diagnosis and management of sepsis',
                'sort_order' => 7,
                'indicators' => [
                    [
                        'code' => '6',
                        'name' => 'Proportion of neonates admitted to the newborn unit who died (crude mortality)',
                        'short_name' => 'NBU crude mortality rate',
                        'indicator_type' => 'proportion',
                        'category' => 'outcome',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of admitted neonates in the NBU who died',
                        'denominator_label' => 'Total number of neonatal admissions in the newborn unit',
                        'source_document' => 'In-patient neonatal register',
                        'source_document_code' => 'INPATIENT_NEO',
                        'display_hint' => 'This is the overall/crude NBU mortality. Separate weight-band and gestational-age breakdowns are captured below.',
                        'sort_order' => 10,
                    ],
                    // ── 7a: Mortality by weight band (sub-banded) ─────────────
                    // parent indicator first, children added via parent_indicator_id in afterSeed()
                    [
                        'code' => '7a',
                        'name' => 'Proportion of neonates who died in the newborn unit as per the different weight bands',
                        'short_name' => 'Mortality by weight band',
                        'indicator_type' => 'proportion',
                        'category' => 'outcome',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates who died in the newborn unit as per respective weight band',
                        'denominator_label' => 'Total number of neonates admitted to newborn unit as per respective weight band',
                        'source_document' => 'In-patient neonatal register',
                        'source_document_code' => 'INPATIENT_NEO',
                        'display_hint' => 'Report separately for each weight band using the sub-indicators below.',
                        'sort_order' => 20,
                        'children' => [
                            ['code' => '7a_lt1000', 'name' => 'Mortality by weight band: <1000g', 'short_name' => 'Mortality <1000g', 'sort_order' => 1],
                            ['code' => '7a_1000_1499', 'name' => 'Mortality by weight band: 1000–1499g', 'short_name' => 'Mortality 1000–1499g', 'sort_order' => 2],
                            ['code' => '7a_1500_1999', 'name' => 'Mortality by weight band: 1500–1999g', 'short_name' => 'Mortality 1500–1999g', 'sort_order' => 3],
                            ['code' => '7a_2000_2499', 'name' => 'Mortality by weight band: 2000–2499g', 'short_name' => 'Mortality 2000–2499g', 'sort_order' => 4],
                            ['code' => '7a_gt2500', 'name' => 'Mortality by weight band: >2500g', 'short_name' => 'Mortality >2500g', 'sort_order' => 5],
                        ],
                    ],
                    // ── 7b: Mortality by gestational age ──────────────────────
                    [
                        'code' => '7b',
                        'name' => 'Proportion of neonates who died in the newborn unit as per the different gestational age',
                        'short_name' => 'Mortality by gestational age',
                        'indicator_type' => 'proportion',
                        'category' => 'outcome',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates who died in the newborn unit as per respective gestational age band',
                        'denominator_label' => 'Total number of neonates admitted to the newborn unit as per respective gestational age band',
                        'source_document' => 'In-patient neonatal register',
                        'source_document_code' => 'INPATIENT_NEO',
                        'sort_order' => 30,
                        'children' => [
                            ['code' => '7b_lt28', 'name' => 'Mortality by gestational age: <28 weeks', 'short_name' => 'Mortality <28 wks', 'sort_order' => 1],
                            ['code' => '7b_28_32', 'name' => 'Mortality by gestational age: ≥28–≤32 weeks', 'short_name' => 'Mortality 28–32 wks', 'sort_order' => 2],
                            ['code' => '7b_32_34', 'name' => 'Mortality by gestational age: ≥32–≤34 weeks', 'short_name' => 'Mortality 32–34 wks', 'sort_order' => 3],
                            ['code' => '7b_34_37', 'name' => 'Mortality by gestational age: ≥34–≤37 weeks', 'short_name' => 'Mortality 34–37 wks', 'sort_order' => 4],
                            ['code' => '7b_gt37', 'name' => 'Mortality by gestational age: >37 weeks', 'short_name' => 'Mortality >37 wks', 'sort_order' => 5],
                        ],
                    ],
                    [
                        'code' => '7c',
                        'name' => 'Proportion of neonates who died in the newborn unit and were referrals in',
                        'short_name' => 'Deaths among referral-in neonates',
                        'indicator_type' => 'proportion',
                        'category' => 'outcome',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates who died in the newborn unit and were referrals in',
                        'denominator_label' => 'Total number of neonates admitted in the unit as referral in',
                        'source_document' => 'In-patient neonatal register; NAR',
                        'source_document_code' => 'INPATIENT_NEO,NAR',
                        'sort_order' => 40,
                    ],
                    [
                        'code' => '7d',
                        'name' => 'Number of monthly mortality audit meetings conducted in the newborn unit',
                        'short_name' => 'Mortality audit meetings (monthly)',
                        'indicator_type' => 'count',
                        'category' => 'process',
                        'has_numerator' => false,
                        'has_denominator' => false,
                        'source_document' => 'Minutes of the Audit meeting',
                        'source_document_code' => 'AUDIT_MINUTES',
                        'display_hint' => 'Enter the number of audit meetings held this month. Attach minutes as supporting documentation.',
                        'sort_order' => 50,
                    ],
                ],
            ],
            // ── MODULE 8: CARE OF SMALL & SICK NEWBORNS ──────────────────────
            [
                'code' => 'nb_module_8',
                'name' => 'Module 8: Care of the Small and Sick Newborns',
                'description' => 'Use of a plastic wrap, use of CPAP, management of AOP, KMC',
                'sort_order' => 8,
                'indicators' => [
                    [
                        'code' => '8a',
                        'name' => 'Proportion of neonates admitted to newborn unit with an admission temperature of <36.5°C',
                        'short_name' => 'Admission hypothermia (<36.5°C)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates admitted to newborn unit with an admission temperature of <36.5°C',
                        'denominator_label' => 'Total number of neonates admitted to newborn unit',
                        'source_document' => 'NAR form',
                        'source_document_code' => 'NAR',
                        'display_hint' => 'Count neonates whose first recorded temperature was <36.5°C.',
                        'sort_order' => 10,
                    ],
                    [
                        'code' => '8b',
                        'name' => 'Proportion of neonates admitted to newborn unit with temperatures <36.5°C who were referrals in',
                        'short_name' => 'Hypothermic referral-in neonates',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates admitted to newborn unit with temperatures <36.5°C who were referrals in',
                        'denominator_label' => 'Total number of neonates admitted to newborn unit',
                        'source_document' => 'NAR form',
                        'source_document_code' => 'NAR',
                        'sort_order' => 20,
                    ],
                    [
                        'code' => '9',
                        'name' => 'Proportion of newborns with suspected sepsis admitted in the NBU with a blood culture done',
                        'short_name' => 'Blood culture in suspected sepsis',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates admitted in the NBU with suspected sepsis with a blood culture done',
                        'denominator_label' => 'Total number of neonates with suspected neonatal sepsis admitted in the NBU',
                        'source_document' => 'NAR form; In-patient neonatal register',
                        'source_document_code' => 'NAR,INPATIENT_NEO',
                        'sort_order' => 30,
                    ],
                    [
                        'code' => '10',
                        'name' => 'Proportion of neonates admitted to the newborn unit with a diagnosis of birth asphyxia',
                        'short_name' => 'Birth asphyxia admissions',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of neonates admitted to the newborn unit with a diagnosis of birth asphyxia',
                        'denominator_label' => 'Total number of neonates admitted to the newborn unit',
                        'source_document' => 'In-patient neonatal register; NAR form',
                        'source_document_code' => 'INPATIENT_NEO,NAR',
                        'sort_order' => 40,
                    ],
                ],
            ],
            // ── MODULE 9: NEONATAL JAUNDICE ──────────────────────────────────
            [
                'code' => 'nb_module_9',
                'name' => 'Module 9: Neonatal Jaundice',
                'description' => 'Identification, use of nomograms, initiation of phototherapy with the appropriate irradiance',
                'sort_order' => 9,
                'indicators' => [],
            ],
            // ── MODULE 10: NEONATAL HYPOGLYCEMIA ─────────────────────────────
            [
                'code' => 'nb_module_10',
                'name' => 'Module 10: Neonatal Hypoglycemia',
                'description' => 'Management, performing a heel prick, administration of buccal dextrose',
                'sort_order' => 10,
                'indicators' => [],
            ],
            // ── MODULE 11: NEWBORN FEEDING ───────────────────────────────────
            [
                'code' => 'nb_module_11',
                'name' => 'Module 11: Newborn Feeding',
                'description' => 'Breastfeeding techniques, expression of breast milk, cup feeding, NGT/OGT insertion and feeding, safe administration of parenteral feeds',
                'sort_order' => 11,
                'indicators' => [],
            ],
            // ── MODULE 12: SUPPORTIVE TOPICS ─────────────────────────────────
            [
                'code' => 'nb_module_12',
                'name' => 'Module 12: Supportive Topics (Documentation & Referrals)',
                'description' => 'Documentation; Referrals',
                'sort_order' => 12,
                'indicators' => [],
            ],
        ];

        $this->createGroupsAndIndicators($reportType, $groups);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PAEDIATRIC INDICATORS
    // ──────────────────────────────────────────────────────────────────────────

    private function seedPaediatricIndicators(): void {
        $reportType = IndicatorReportType::where('code', 'paediatric')->firstOrFail();

        $groups = [
            // ── MODULE 1: TRIAGE ─────────────────────────────────────────────
            [
                'code' => 'paed_module_1',
                'name' => 'Module 1: Triage',
                'description' => 'Identifying and acting on the ABCD for emergency signs. Identifying and acting on 3Ts, 3Ps, 3Rs and MOB for priority signs.',
                'sort_order' => 1,
                'indicators' => [],
            ],
            // ── MODULE 2: BLS ─────────────────────────────────────────────────
            [
                'code' => 'paed_module_2',
                'name' => 'Module 2: Basic Life Support for Infant/Child',
                'description' => 'Structured approach to Basic Life Support (BLS) for both infants and children.',
                'sort_order' => 2,
                'indicators' => [],
            ],
            // ── MODULE 3: FLUID MANAGEMENT ───────────────────────────────────
            [
                'code' => 'paed_module_3',
                'name' => 'Module 3: Fluid Management',
                'description' => 'Fluid resuscitation and management for paediatric patients',
                'sort_order' => 3,
                'indicators' => [],
            ],
            // ── MODULE 4: OXYGEN THERAPY ─────────────────────────────────────
            [
                'code' => 'paed_module_4',
                'name' => 'Module 4: Oxygen Therapy',
                'description' => 'Identification of hypoxaemia, oxygen delivery devices, pulse oximetry',
                'sort_order' => 4,
                'indicators' => [
                    [
                        'code' => '1a',
                        'name' => 'Proportion of children under 5 years with hypoxaemia (SpO2 <90%) started on oxygen',
                        'short_name' => 'Hypoxaemia (<5 yrs) started on O2',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 years with hypoxaemia (SpO2 <90%) started on oxygen',
                        'denominator_label' => 'Total number of children under 5 years with hypoxaemia (SpO2 <90%)',
                        'source_document' => 'Paediatric Inpatient register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'display_hint' => 'Only count children where SpO2 was measured and found <90%. Source: MOH 377.',
                        'sort_order' => 10,
                    ],
                ],
            ],
            // ── MODULE 5: CHILD WITH RESPIRATORY DISTRESS ────────────────────
            [
                'code' => 'paed_module_5',
                'name' => 'Module 5: Child with Respiratory Distress',
                'description' => 'Correct classification and management of pneumonia and respiratory distress',
                'sort_order' => 5,
                'indicators' => [
                    [
                        'code' => '2a',
                        'name' => 'Proportion of children under 5 years with severe pneumonia started on oxygen',
                        'short_name' => 'Severe pneumonia + O2 (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 years with severe pneumonia started on oxygen',
                        'denominator_label' => 'Total number of children under 5 years with severe pneumonia',
                        'source_document' => 'Paediatric Inpatient register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'sort_order' => 10,
                    ],
                    [
                        'code' => '2b',
                        'name' => 'Proportion of children under 5 years with pneumonia started on high dose Amoxicillin',
                        'short_name' => 'Pneumonia + high-dose Amoxicillin (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 years with pneumonia started on high dose Amoxicillin',
                        'denominator_label' => 'Total number of children under 5 years with pneumonia',
                        'source_document' => 'Paediatric Outpatient register (MOH 204A)',
                        'source_document_code' => 'MOH_204A',
                        'sort_order' => 20,
                    ],
                    [
                        'code' => '2c',
                        'name' => 'Proportion of children under 5 years with severe pneumonia started on Benzyl Penicillin and Gentamycin',
                        'short_name' => 'Severe pneumonia + BnzylPen/Gent (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 years with severe pneumonia started on Benzyl Penicillin and Gentamycin',
                        'denominator_label' => 'Total number of children under 5 years with severe pneumonia',
                        'source_document' => 'Paediatric Inpatient register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'sort_order' => 30,
                    ],
                    [
                        'code' => '2d',
                        'name' => 'Proportion of children under 5 years with severe pneumonia who died',
                        'short_name' => 'Severe pneumonia case fatality (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'outcome',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 years with severe pneumonia who died',
                        'denominator_label' => 'Total number of children under 5 years diagnosed with severe pneumonia',
                        'source_document' => 'Paediatric Inpatient register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'sort_order' => 40,
                    ],
                ],
            ],
            // ── MODULE 6: DEHYDRATION / DIARRHOEA ───────────────────────────
            [
                'code' => 'paed_module_6',
                'name' => 'Module 6: Child with Dehydration due to Diarrhea/Vomiting',
                'description' => 'Correct management of dehydration due to diarrhoea including ORS, zinc, isotonic fluids',
                'sort_order' => 6,
                'indicators' => [
                    [
                        'code' => '3a',
                        'name' => 'Proportion of children under 5 years with diarrhea treated with ORS and zinc co-pack',
                        'short_name' => 'Diarrhoea + ORS & zinc co-pack (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 years with diarrhea treated with ORS and zinc co-pack',
                        'denominator_label' => 'Total number of children under 5 years with diarrhea',
                        'source_document' => 'Paediatric Outpatient Register (MOH 204A); ORT corner register (MOH 283)',
                        'source_document_code' => 'MOH_204A,MOH_283',
                        'sort_order' => 10,
                    ],
                    [
                        'code' => '3b',
                        'name' => 'Proportion of children under 5 years with hypovolaemic shock due to diarrhea treated with the correct volume of isotonic fluid',
                        'short_name' => 'Hypovolaemic shock + correct isotonic fluid (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 years with hypovolaemic shock due to diarrhea treated with the correct volume of isotonic fluid',
                        'denominator_label' => 'Total number of children under 5 years with hypovolaemic shock due to diarrhea',
                        'source_document' => 'Paediatric Inpatient Register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'sort_order' => 20,
                    ],
                    [
                        'code' => '3c',
                        'name' => 'Proportion of children under 5 years with severe dehydration due to diarrhea treated with the correct volume of isotonic fluid',
                        'short_name' => 'Severe dehydration + correct isotonic fluid (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 years with severe dehydration due to diarrhea treated with the correct volume of isotonic fluid',
                        'denominator_label' => 'Total number of children under 5 years with severe dehydration due to diarrhea',
                        'source_document' => 'Paediatric Inpatient Register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'sort_order' => 30,
                    ],
                ],
            ],
            // ── MODULE 7: NUTRITION / SAM ────────────────────────────────────
            [
                'code' => 'paed_module_7',
                'name' => 'Module 7: Management of SAM in Infants/Children aged 6–59 months',
                'description' => 'Screening for malnutrition, management of Severe Acute Malnutrition',
                'sort_order' => 7,
                'indicators' => [
                    [
                        'code' => '4a',
                        'name' => 'Proportion of children under 5 with SAM who died',
                        'short_name' => 'SAM case fatality (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'outcome',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 with SAM who died',
                        'denominator_label' => 'Total number of children under 5 with SAM',
                        'source_document' => 'Paediatric inpatient register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'sort_order' => 10,
                    ],
                    [
                        'code' => '4b',
                        'name' => 'Proportion of children under 5 screened for malnutrition (MUAC/WHZ/nutritional oedema) in the inpatient department',
                        'short_name' => 'Malnutrition screening (IPD, <5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'process',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 screened for malnutrition (MUAC/WHZ/nutritional oedema) in the inpatient department',
                        'denominator_label' => 'Total number of children under 5 admitted in the inpatient department',
                        'source_document' => 'Paediatric inpatient register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'display_hint' => 'MUAC = Mid-Upper Arm Circumference. WHZ = Weight-for-Height Z-score. Include all three screening methods.',
                        'sort_order' => 20,
                    ],
                    [
                        'code' => '4c',
                        'name' => 'Proportion of children under 5 screened for acute malnutrition (WHZ/MUAC/nutritional oedema) in the outpatient department',
                        'short_name' => 'Malnutrition screening (OPD, <5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'process',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 screened for acute malnutrition (WHZ/MUAC/nutritional oedema) in the outpatient department',
                        'denominator_label' => 'Total number of children under 5 seen in the outpatient department',
                        'source_document' => 'Paediatric Outpatient (MOH 204A); Child welfare clinic register (MOH 511)',
                        'source_document_code' => 'MOH_204A,MOH_511',
                        'sort_order' => 30,
                    ],
                    [
                        'code' => '4d',
                        'name' => 'Proportion of children under 5 with nutritional oedema in the outpatient department',
                        'short_name' => 'Nutritional oedema (OPD, <5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 with nutritional oedema in the outpatient department',
                        'denominator_label' => 'Total number of children under 5 seen in the outpatient department',
                        'source_document' => 'Paediatric Outpatient register (MOH 511)',
                        'source_document_code' => 'MOH_511',
                        'sort_order' => 40,
                    ],
                ],
            ],
            // ── MODULE 8: ALTERED CONSCIOUSNESS ─────────────────────────────
            [
                'code' => 'paed_module_8',
                'name' => 'Module 8: Child with Altered Consciousness',
                'description' => 'Assessment and management of altered consciousness including malaria, meningitis, hypoglycaemia',
                'sort_order' => 8,
                'indicators' => [
                    [
                        'code' => '5a',
                        'name' => 'Proportion of sick children under 5 years admitted with an RBS measurement',
                        'short_name' => 'Admitted with RBS measurement (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'process',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of sick children under 5 years admitted with an RBS measurement',
                        'denominator_label' => 'Total number of sick children under 5 years admitted',
                        'source_document' => 'Paediatric inpatient register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'display_hint' => 'RBS = Random Blood Sugar. Record if a blood glucose level was measured at admission.',
                        'sort_order' => 10,
                    ],
                    [
                        'code' => '5b',
                        'name' => 'Proportion of children under 5 years with severe malaria who died',
                        'short_name' => 'Severe malaria case fatality (<5 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'outcome',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children under 5 years with severe malaria who died',
                        'denominator_label' => 'Total number of children under 5 years diagnosed with severe malaria',
                        'source_document' => 'Paediatric Inpatient register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'sort_order' => 20,
                    ],
                ],
            ],
            // ── MODULE 9: DIABETES ────────────────────────────────────────────
            [
                'code' => 'paed_module_9',
                'name' => 'Module 9: Diabetes',
                'description' => 'Assessment, diagnosis, classification and management of DKA; routine care of children and adolescents with type 1 diabetes',
                'sort_order' => 9,
                'indicators' => [
                    [
                        'code' => '6a',
                        'name' => 'Proportion of patients aged 0–18 years with type 1 DM on basal bolus regimen',
                        'short_name' => 'Type 1 DM on basal bolus regimen (0–18 yrs)',
                        'indicator_type' => 'proportion',
                        'category' => 'output',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of patients aged 0–18 years with type 1 DM on basal bolus regimen',
                        'denominator_label' => 'Total number of patients aged 0–18 years with type 1 DM',
                        'source_document' => 'Diabetes and hypertension comprehensive care register',
                        'source_document_code' => 'DM_HT_REG',
                        'sort_order' => 10,
                    ],
                    [
                        'code' => '6b',
                        'name' => 'Proportion of children admitted with DKA who died',
                        'short_name' => 'DKA case fatality',
                        'indicator_type' => 'proportion',
                        'category' => 'outcome',
                        'has_numerator' => true,
                        'has_denominator' => true,
                        'numerator_label' => 'Number of children admitted with DKA who died',
                        'denominator_label' => 'Total number of children admitted with DKA',
                        'source_document' => 'Paediatric Inpatient register (MOH 377)',
                        'source_document_code' => 'MOH_377',
                        'display_hint' => 'DKA = Diabetic Ketoacidosis. Record all DKA admissions and outcomes.',
                        'sort_order' => 20,
                    ],
                ],
            ],
            // ── MODULE 10: DOCUMENTATION ──────────────────────────────────────
            [
                'code' => 'paed_module_10',
                'name' => 'Module 10: Documentation using the Paediatric Inpatient File',
                'description' => 'Paediatric admission record (PAR), inpatient treatment sheet, nursing cardex, discharge summary, referral form',
                'sort_order' => 10,
                'indicators' => [
                    [
                        'code' => 'doc_audit',
                        'name' => 'Number of monthly mortality audit meetings conducted in the paediatric unit',
                        'short_name' => 'Paed mortality audit meetings (monthly)',
                        'indicator_type' => 'count',
                        'category' => 'process',
                        'has_numerator' => false,
                        'has_denominator' => false,
                        'source_document' => 'Minutes of the Audit meeting',
                        'source_document_code' => 'AUDIT_MINUTES',
                        'display_hint' => 'Enter the number of paediatric audit meetings held this month.',
                        'sort_order' => 10,
                    ],
                ],
            ],
        ];

        $this->createGroupsAndIndicators($reportType, $groups);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shared Helper
    // ──────────────────────────────────────────────────────────────────────────

    private function createGroupsAndIndicators(IndicatorReportType $reportType, array $groups): void {
        foreach ($groups as $groupData) {
            $indicators = $groupData['indicators'] ?? [];
            unset($groupData['indicators']);

            /** @var IndicatorGroup $group */
            $group = IndicatorGroup::updateOrCreate(
                    ['report_type_id' => $reportType->id, 'code' => $groupData['code']],
                    array_merge($groupData, ['report_type_id' => $reportType->id])
            );

            foreach ($indicators as $indicatorData) {
                $children = $indicatorData['children'] ?? [];
                unset($indicatorData['children']);

                $indicatorData['group_id'] = $group->id;

                /** @var Indicator $parent */
                $parent = Indicator::updateOrCreate(
                        ['group_id' => $group->id, 'code' => $indicatorData['code']],
                        $indicatorData
                );

                // Create sub-band children (inherit parent's type/category/source)
                foreach ($children as $idx => $childData) {
                    Indicator::updateOrCreate(
                            ['group_id' => $group->id, 'code' => $childData['code']],
                            array_merge([
                        'group_id' => $group->id,
                        'parent_indicator_id' => $parent->id,
                        'indicator_type' => $parent->indicator_type,
                        'category' => $parent->category,
                        'has_numerator' => $parent->has_numerator,
                        'has_denominator' => $parent->has_denominator,
                        'numerator_label' => $parent->numerator_label,
                        'denominator_label' => $parent->denominator_label,
                        'source_document' => $parent->source_document,
                        'source_document_code' => $parent->source_document_code,
                        'is_active' => true,
                                    ], $childData)
                    );
                }
            }
        }
    }
}
