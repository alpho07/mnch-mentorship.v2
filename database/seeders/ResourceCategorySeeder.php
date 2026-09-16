<?php

namespace Database\Seeders;

use App\Models\ResourceCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ResourceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // TOP LEVEL CATEGORIES - MNICH & DIABETES FOCUSED
            [
                'name' => 'Maternal Health',
                'slug' => 'maternal-health',
                'description' => 'Comprehensive maternal health resources covering pregnancy care, delivery, and postpartum support.',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Newborn Care',
                'slug' => 'newborn-care',
                'description' => 'Essential newborn care protocols, procedures, and guidelines for the first 28 days of life.',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Infant Health',
                'slug' => 'infant-health',
                'description' => 'Infant health and development resources for children aged 0-12 months.',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Child Health',
                'slug' => 'child-health',
                'description' => 'Comprehensive child health resources for children aged 1-18 years including growth monitoring and development.',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Diabetes Management',
                'slug' => 'diabetes-management',
                'description' => 'Diabetes prevention, management, and care resources for all age groups.',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Clinical Protocols',
                'slug' => 'clinical-protocols',
                'description' => 'Standardized clinical protocols and evidence-based treatment guidelines.',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Training & Education',
                'slug' => 'training-education',
                'description' => 'Healthcare provider training materials and continuing education resources.',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Quality Improvement',
                'slug' => 'quality-improvement',
                'description' => 'Quality assurance, improvement methodologies, and performance monitoring tools.',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        // Create top-level categories first
        $createdCategories = [];
        foreach ($categories as $category) {
            $createdCategory = ResourceCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
            $createdCategories[$category['slug']] = $createdCategory;
        }

        // SUB-CATEGORIES FOR MATERNAL HEALTH
        $maternalSubCategories = [
            [
                'name' => 'Antenatal Care',
                'slug' => 'antenatal-care',
                'description' => 'Pregnancy monitoring, antenatal visits, and prenatal care protocols.',
                'parent_id' => $createdCategories['maternal-health']->id,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Labour & Delivery',
                'slug' => 'labour-delivery',
                'description' => 'Labour management, delivery procedures, and birthing protocols.',
                'parent_id' => $createdCategories['maternal-health']->id,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Postpartum Care',
                'slug' => 'postpartum-care',
                'description' => 'Post-delivery maternal care, recovery monitoring, and support.',
                'parent_id' => $createdCategories['maternal-health']->id,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Family Planning',
                'slug' => 'family-planning',
                'description' => 'Contraception, birth spacing, and reproductive health services.',
                'parent_id' => $createdCategories['maternal-health']->id,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'High-Risk Pregnancies',
                'slug' => 'high-risk-pregnancies',
                'description' => 'Management of complicated pregnancies and high-risk maternal conditions.',
                'parent_id' => $createdCategories['maternal-health']->id,
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        // SUB-CATEGORIES FOR NEWBORN CARE
        $newbornSubCategories = [
            [
                'name' => 'Essential Newborn Care',
                'slug' => 'essential-newborn-care',
                'description' => 'Basic newborn care practices including cord care, thermal protection, and early feeding.',
                'parent_id' => $createdCategories['newborn-care']->id,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Breastfeeding Support',
                'slug' => 'breastfeeding-support',
                'description' => 'Breastfeeding initiation, support, and problem-solving resources.',
                'parent_id' => $createdCategories['newborn-care']->id,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Newborn Screening',
                'slug' => 'newborn-screening',
                'description' => 'Screening protocols for congenital conditions and early detection programs.',
                'parent_id' => $createdCategories['newborn-care']->id,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Sick Newborn Care',
                'slug' => 'sick-newborn-care',
                'description' => 'Management of newborn illnesses, infections, and emergency care.',
                'parent_id' => $createdCategories['newborn-care']->id,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Preterm & Low Birth Weight',
                'slug' => 'preterm-low-birth-weight',
                'description' => 'Specialized care for premature babies and low birth weight infants.',
                'parent_id' => $createdCategories['newborn-care']->id,
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        // SUB-CATEGORIES FOR INFANT HEALTH
        $infantSubCategories = [
            [
                'name' => 'Growth Monitoring',
                'slug' => 'growth-monitoring',
                'description' => 'Infant growth tracking, nutrition assessment, and development monitoring.',
                'parent_id' => $createdCategories['infant-health']->id,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Immunization',
                'slug' => 'immunization',
                'description' => 'Vaccination schedules, immunization protocols, and vaccine safety.',
                'parent_id' => $createdCategories['infant-health']->id,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Nutrition & Feeding',
                'slug' => 'nutrition-feeding',
                'description' => 'Infant feeding practices, complementary feeding, and nutritional support.',
                'parent_id' => $createdCategories['infant-health']->id,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Common Infant Illnesses',
                'slug' => 'common-infant-illnesses',
                'description' => 'Management of common infant conditions like diarrhea, pneumonia, and malnutrition.',
                'parent_id' => $createdCategories['infant-health']->id,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        // SUB-CATEGORIES FOR CHILD HEALTH
        $childSubCategories = [
            [
                'name' => 'Child Development',
                'slug' => 'child-development',
                'description' => 'Physical, cognitive, and social development monitoring and support.',
                'parent_id' => $createdCategories['child-health']->id,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Adolescent Health',
                'slug' => 'adolescent-health',
                'description' => 'Adolescent-specific health issues, puberty, and reproductive health.',
                'parent_id' => $createdCategories['child-health']->id,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Childhood Diseases',
                'slug' => 'childhood-diseases',
                'description' => 'Common childhood illnesses, infectious diseases, and treatment protocols.',
                'parent_id' => $createdCategories['child-health']->id,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Mental Health & Wellbeing',
                'slug' => 'mental-health-wellbeing',
                'description' => 'Child and adolescent mental health, psychosocial support, and counseling.',
                'parent_id' => $createdCategories['child-health']->id,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        // SUB-CATEGORIES FOR DIABETES MANAGEMENT
        $diabetesSubCategories = [
            [
                'name' => 'Type 1 Diabetes',
                'slug' => 'type-1-diabetes',
                'description' => 'Type 1 diabetes management in children and adults, insulin therapy protocols.',
                'parent_id' => $createdCategories['diabetes-management']->id,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Type 2 Diabetes',
                'slug' => 'type-2-diabetes',
                'description' => 'Type 2 diabetes prevention, early detection, and management strategies.',
                'parent_id' => $createdCategories['diabetes-management']->id,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Gestational Diabetes',
                'slug' => 'gestational-diabetes',
                'description' => 'Gestational diabetes screening, management, and maternal-fetal care.',
                'parent_id' => $createdCategories['diabetes-management']->id,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Diabetes Prevention',
                'slug' => 'diabetes-prevention',
                'description' => 'Risk assessment, lifestyle interventions, and prevention strategies.',
                'parent_id' => $createdCategories['diabetes-management']->id,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Complications Management',
                'slug' => 'complications-management',
                'description' => 'Diabetic complications prevention and management protocols.',
                'parent_id' => $createdCategories['diabetes-management']->id,
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        // SUB-CATEGORIES FOR CLINICAL PROTOCOLS
        $protocolSubCategories = [
            [
                'name' => 'Emergency Protocols',
                'slug' => 'emergency-protocols',
                'description' => 'Emergency obstetric care, newborn resuscitation, and critical care protocols.',
                'parent_id' => $createdCategories['clinical-protocols']->id,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Infection Prevention',
                'slug' => 'infection-prevention',
                'description' => 'Infection control measures, antimicrobial stewardship, and hygiene protocols.',
                'parent_id' => $createdCategories['clinical-protocols']->id,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Drug Protocols',
                'slug' => 'drug-protocols',
                'description' => 'Medication guidelines, dosing protocols, and pharmaceutical management.',
                'parent_id' => $createdCategories['clinical-protocols']->id,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        // SUB-CATEGORIES FOR TRAINING & EDUCATION
        $trainingSubCategories = [
            [
                'name' => 'Healthcare Provider Training',
                'slug' => 'healthcare-provider-training',
                'description' => 'Clinical skills training for doctors, nurses, and health workers.',
                'parent_id' => $createdCategories['training-education']->id,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Community Health Education',
                'slug' => 'community-health-education',
                'description' => 'Community outreach materials and health education resources.',
                'parent_id' => $createdCategories['training-education']->id,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Patient Education',
                'slug' => 'patient-education',
                'description' => 'Patient and family education materials for health promotion.',
                'parent_id' => $createdCategories['training-education']->id,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        // Create all sub-categories
        $allSubCategories = array_merge(
            $maternalSubCategories,
            $newbornSubCategories,
            $infantSubCategories,
            $childSubCategories,
            $diabetesSubCategories,
            $protocolSubCategories,
            $trainingSubCategories
        );

        foreach ($allSubCategories as $subCategory) {
            ResourceCategory::updateOrCreate(
                ['slug' => $subCategory['slug']],
                $subCategory
            );
        }

        $this->command->info('MNICH & Diabetes resource categories seeded successfully!');
    }
}
