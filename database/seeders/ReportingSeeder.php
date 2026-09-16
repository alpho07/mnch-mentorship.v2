<?php
// ReportingSeeder.php
namespace Database\Seeders;

use App\Models\ReportTemplate;
use App\Models\Indicator;
use Illuminate\Database\Seeder;

class ReportingSeeder extends Seeder
{
    public function run(): void
    {
        // Create Newborn Report Template
        $newbornTemplate = ReportTemplate::create([
            'name' => 'Newborn Care Monthly Report',
            'code' => 'NEWBORN_MONTHLY',
            'description' => 'Monthly reporting for newborn care indicators',
            'report_type' => 'newborn',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // Create Pediatric Report Template
        $pediatricTemplate = ReportTemplate::create([
            'name' => 'Pediatric Care Monthly Report',
            'code' => 'PEDIATRIC_MONTHLY',
            'description' => 'Monthly reporting for pediatric care indicators',
            'report_type' => 'pediatric',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // Newborn Indicators
        $newbornIndicators = [
            [
                'name' => 'Antenatal Corticosteroids Coverage',
                'code' => 'NB_ANTENATAL_STEROIDS',
                'description' => 'Proportion of mothers with preterms below 34 weeks gestation who received antenatal corticosteroids',
                'numerator_description' => 'Number of mothers with preterms below 34 weeks gestation who received at least one dose of antenatal corticosteroids',
                'denominator_description' => 'Total number of mothers with preterms below 34 weeks admitted to the newborn unit',
                'calculation_type' => 'percentage',
                'source_document' => 'Newborn transfer form, In-patient neonatal register, Newborn Admission Record',
                'target_value' => 80.00,
            ],
            [
                'name' => 'Early KMC Initiation',
                'code' => 'NB_EARLY_KMC',
                'description' => 'Proportion of newborns <2000g weight initiated on KMC within 2 hours',
                'numerator_description' => 'Number of newborns <2000g weight who were initiated on KMC within 2 hours of birth',
                'denominator_description' => 'Total number of newborns <2000g weight admitted to the KMC/NBU',
                'calculation_type' => 'percentage',
                'source_document' => 'KMC register',
                'target_value' => 90.00,
            ],
            [
                'name' => 'KMC Coverage',
                'code' => 'NB_KMC_COVERAGE',
                'description' => 'Proportion of neonates <2000g weight who were on KMC',
                'numerator_description' => 'Number of neonates <2000g weight who were on KMC',
                'denominator_description' => 'Total number of neonates <2000g weight admitted in the newborn unit',
                'calculation_type' => 'percentage',
                'source_document' => 'KMC register, In-patient neonatal register',
                'target_value' => 85.00,
            ],
            [
                'name' => 'CPAP Initiation - Preterms <32 weeks',
                'code' => 'NB_CPAP_PRETERM',
                'description' => 'Proportion of preterms <32 weeks gestation initiated on CPAP',
                'numerator_description' => 'Number of preterms <32 weeks gestation who were initiated on CPAP in the newborn unit',
                'denominator_description' => 'Total number of preterms <32 weeks gestation admitted in the newborn unit',
                'calculation_type' => 'percentage',
                'source_document' => 'In-patient neonatal register',
                'target_value' => 75.00,
            ],
            [
                'name' => 'CPAP with Oxygen Saturation Monitoring',
                'code' => 'NB_CPAP_MONITORING',
                'description' => 'Proportion of neonates on CPAP with continuous oxygen saturation monitoring',
                'numerator_description' => 'Number of neonates initiated on CPAP with continuous oxygen saturation monitoring in the newborn unit',
                'denominator_description' => 'Total number of neonates initiated on CPAP in the newborn unit',
                'calculation_type' => 'percentage',
                'source_document' => 'Comprehensive newborn monitoring chart, Health facility assessment',
                'target_value' => 95.00,
            ],
            [
                'name' => 'Caffeine Citrate Prophylaxis',
                'code' => 'NB_CAFFEINE_PROPHYLAXIS',
                'description' => 'Proportion of neonates <34 weeks gestation who received prophylactic caffeine citrate',
                'numerator_description' => 'Number of neonates <34 weeks gestation who received prophylactic caffeine citrate in the newborn unit',
                'denominator_description' => 'Total number of neonates <34 weeks gestation in the newborn unit',
                'calculation_type' => 'percentage',
                'source_document' => 'In-patient neonatal register, NAR',
                'target_value' => 80.00,
            ],
            [
                'name' => 'Newborn Unit Mortality Rate',
                'code' => 'NB_MORTALITY_RATE',
                'description' => 'Proportion of neonates admitted to the newborn unit who died (crude mortality)',
                'numerator_description' => 'Number of admitted neonates NBU who died',
                'denominator_description' => 'Total number of neonatal admissions in the newborn unit',
                'calculation_type' => 'percentage',
                'source_document' => 'In-patient neonatal register',
                'target_value' => 15.00,
            ],
            [
                'name' => 'Hypothermia on Admission',
                'code' => 'NB_HYPOTHERMIA_ADMISSION',
                'description' => 'Proportion of neonates admitted with temperature <36.5°C',
                'numerator_description' => 'Number of neonates admitted to newborn unit with an admission temperature of <36.5°C',
                'denominator_description' => 'Total number of neonates admitted to newborn unit',
                'calculation_type' => 'percentage',
                'source_document' => 'NAR form',
                'target_value' => 20.00,
            ],
            [
                'name' => 'Sepsis Blood Culture Coverage',
                'code' => 'NB_SEPSIS_BLOOD_CULTURE',
                'description' => 'Proportion of newborns with suspected sepsis who had blood culture done',
                'numerator_description' => 'Number of newborns admitted in the NBU with suspected sepsis with a blood culture done',
                'denominator_description' => 'Total number of neonates with suspected neonatal sepsis admitted in the NBU',
                'calculation_type' => 'percentage',
                'source_document' => 'NAR form, In-patient neonatal register',
                'target_value' => 90.00,
            ],
            [
                'name' => 'Birth Asphyxia Cases',
                'code' => 'NB_BIRTH_ASPHYXIA',
                'description' => 'Proportion of neonates admitted with a diagnosis of birth asphyxia',
                'numerator_description' => 'Number of neonates admitted to the newborn unit with a diagnosis of birth asphyxia',
                'denominator_description' => 'Total number of neonates admitted to the newborn unit',
                'calculation_type' => 'percentage',
                'source_document' => 'In-patient neonatal register, NAR form',
                'target_value' => 25.00,
            ],
        ];

        // Pediatric Indicators
        $pediatricIndicators = [
            [
                'name' => 'Hypoxemia Oxygen Therapy',
                'code' => 'PED_HYPOXEMIA_O2',
                'description' => 'Proportion of children under 5 with hypoxemia (SpO2 <90%) started on oxygen',
                'numerator_description' => 'Number of children under 5 years with hypoxemia (SpO2 <90%) started on oxygen',
                'denominator_description' => 'Total number of children under 5 years with hypoxemia (SpO2 <90%)',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric Inpatient register (MOH 377)',
                'target_value' => 95.00,
            ],
            [
                'name' => 'Severe Pneumonia Oxygen Therapy',
                'code' => 'PED_PNEUMONIA_O2',
                'description' => 'Proportion of children under 5 with severe pneumonia started on oxygen',
                'numerator_description' => 'Number of children under 5 years with severe pneumonia started on oxygen',
                'denominator_description' => 'Total number of children under 5 years with severe pneumonia',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric Inpatient register (MOH 377)',
                'target_value' => 90.00,
            ],
            [
                'name' => 'Pneumonia High-dose Amoxicillin',
                'code' => 'PED_PNEUMONIA_AMOXICILLIN',
                'description' => 'Proportion of children under 5 with pneumonia started on high dose Amoxicillin',
                'numerator_description' => 'Number of children under 5 years with pneumonia started on high dose Amoxicillin',
                'denominator_description' => 'Total number of children under 5 years with pneumonia',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric Outpatient register (MOH 204A)',
                'target_value' => 85.00,
            ],
            [
                'name' => 'Severe Pneumonia Antibiotic Treatment',
                'code' => 'PED_PNEUMONIA_ANTIBIOTICS',
                'description' => 'Proportion of children under 5 with severe pneumonia started on Benzyl Penicillin and Gentamycin',
                'numerator_description' => 'Number of children under 5 years with severe pneumonia started on Benzyl Penicillin and Gentamycin',
                'denominator_description' => 'Total number of children under 5 years with severe pneumonia',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric Inpatient register (MOH 377)',
                'target_value' => 90.00,
            ],
            [
                'name' => 'Severe Pneumonia Mortality',
                'code' => 'PED_PNEUMONIA_MORTALITY',
                'description' => 'Proportion of children under 5 with severe pneumonia who died',
                'numerator_description' => 'Number of children under 5 years with severe pneumonia who died',
                'denominator_description' => 'Total number of children under 5 years diagnosed with severe pneumonia',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric Inpatient register (MOH 377)',
                'target_value' => 5.00,
            ],
            [
                'name' => 'Diarrhea ORS and Zinc Treatment',
                'code' => 'PED_DIARRHEA_ORS_ZINC',
                'description' => 'Proportion of children under 5 with diarrhea treated with ORS and zinc co-pack',
                'numerator_description' => 'Number of children under 5 years with diarrhea treated with ORS and zinc co-pack',
                'denominator_description' => 'Total number of children under 5 years with diarrhea',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric Outpatient Register (MOH 204A), ORT corner register (MOH 283)',
                'target_value' => 80.00,
            ],
            [
                'name' => 'SAM Mortality Rate',
                'code' => 'PED_SAM_MORTALITY',
                'description' => 'Proportion of children under 5 with SAM who died',
                'numerator_description' => 'Number of children under 5 with SAM who died',
                'denominator_description' => 'Total number of children under 5 with SAM',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric inpatient register (MOH 377)',
                'target_value' => 10.00,
            ],
            [
                'name' => 'Inpatient Malnutrition Screening',
                'code' => 'PED_INPATIENT_MALNUTRITION_SCREENING',
                'description' => 'Proportion of children under 5 screened for malnutrition in inpatient department',
                'numerator_description' => 'Number of children under 5 screened for malnutrition (MUAC/WHZ/nutritional oedema) in the inpatient department',
                'denominator_description' => 'Total number of children under 5 admitted in the inpatient department',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric inpatient register (MOH 377)',
                'target_value' => 95.00,
            ],
            [
                'name' => 'Sick Children RBS Measurement',
                'code' => 'PED_RBS_MEASUREMENT',
                'description' => 'Proportion of sick children under 5 admitted with RBS measurement',
                'numerator_description' => 'Number of sick children under 5 years admitted with an RBS measurement',
                'denominator_description' => 'Total number of sick children under 5 years admitted',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric inpatient register (MOH 377)',
                'target_value' => 80.00,
            ],
            [
                'name' => 'Severe Malaria Mortality',
                'code' => 'PED_MALARIA_MORTALITY',
                'description' => 'Proportion of children under 5 with severe malaria who died',
                'numerator_description' => 'Number of children under 5 years with severe malaria who died',
                'denominator_description' => 'Total number of children under 5 years diagnosed with severe malaria',
                'calculation_type' => 'percentage',
                'source_document' => 'Paediatric Inpatient register (MOH 377)',
                'target_value' => 8.00,
            ],
        ];

        // Create and attach newborn indicators
        foreach ($newbornIndicators as $index => $indicatorData) {
            $indicator = Indicator::create($indicatorData);
            $newbornTemplate->indicators()->attach($indicator->id, [
                'sort_order' => $index + 1,
                'is_required' => true,
            ]);
        }

        // Create and attach pediatric indicators
        foreach ($pediatricIndicators as $index => $indicatorData) {
            $indicator = Indicator::create($indicatorData);
            $pediatricTemplate->indicators()->attach($indicator->id, [
                'sort_order' => $index + 1,
                'is_required' => true,
            ]);
        }
    }
}
