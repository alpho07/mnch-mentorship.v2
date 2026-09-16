<?php

namespace Database\Seeders;

use App\Models\AccessGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccessGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accessGroups = [
            // ROLE-BASED ACCESS GROUPS
            [
                'name' => 'All Healthcare Staff',
                'description' => 'General access group for all authenticated healthcare personnel. Includes basic clinical protocols and general health information.',
                'is_active' => true,
            ],
            [
                'name' => 'Clinical Leadership',
                'description' => 'Senior clinical staff and department heads with access to advanced protocols, quality improvement materials, and management resources.',
                'is_active' => true,
            ],
            [
                'name' => 'Program Coordinators',
                'description' => 'MNICH and diabetes program coordinators with access to program-specific materials, training resources, and coordination tools.',
                'is_active' => true,
            ],
            [
                'name' => 'New Healthcare Staff',
                'description' => 'Recently hired healthcare workers with access to orientation materials, basic clinical protocols, and foundational training content.',
                'is_active' => true,
            ],
            [
                'name' => 'Trainers & Mentors',
                'description' => 'Clinical trainers and mentors with access to educational resources, training materials, and instructional content for capacity building.',
                'is_active' => true,
            ],

            // CLINICAL SPECIALTY GROUPS
            [
                'name' => 'Maternal Health Specialists',
                'description' => 'Obstetricians, midwives, and maternal health specialists with access to pregnancy care protocols, delivery procedures, and maternal health guidelines.',
                'is_active' => true,
            ],
            [
                'name' => 'Newborn Care Specialists',
                'description' => 'Neonatologists, pediatric nurses, and newborn care specialists with access to essential newborn care protocols and neonatal emergency procedures.',
                'is_active' => true,
            ],
            [
                'name' => 'Child Health Providers',
                'description' => 'Pediatricians, child health nurses, and providers specializing in infant and child health with access to IMCI protocols and child development resources.',
                'is_active' => true,
            ],
            [
                'name' => 'Diabetes Care Team',
                'description' => 'Endocrinologists, diabetes educators, and specialists focused on diabetes prevention, management, and patient education across all age groups.',
                'is_active' => true,
            ],

            // NURSING SPECIALIZATIONS
            [
                'name' => 'Midwives',
                'description' => 'Professional midwives with access to maternal care protocols, delivery procedures, family planning resources, and emergency obstetric care guidelines.',
                'is_active' => true,
            ],
            [
                'name' => 'Pediatric Nurses',
                'description' => 'Nurses specializing in pediatric care with access to child health protocols, growth monitoring procedures, and pediatric emergency care.',
                'is_active' => true,
            ],
            [
                'name' => 'Community Health Nurses',
                'description' => 'Public health and community nurses with access to community-based interventions, health education materials, and outreach resources.',
                'is_active' => true,
            ],
            [
                'name' => 'ICU/Critical Care Nurses',
                'description' => 'Intensive care nurses with access to critical care protocols, emergency procedures, and advanced life support resources.',
                'is_active' => true,
            ],

            // COMMUNITY HEALTH WORKERS
            [
                'name' => 'Community Health Workers',
                'description' => 'Community health volunteers and workers with access to community-level health promotion materials, basic care protocols, and referral guidelines.',
                'is_active' => true,
            ],
            [
                'name' => 'Traditional Birth Attendants',
                'description' => 'Trained traditional birth attendants with access to safe delivery practices, complication recognition, and referral protocols.',
                'is_active' => true,
            ],
            [
                'name' => 'Peer Educators',
                'description' => 'Community peer educators with access to health education materials, behavior change communication resources, and community mobilization tools.',
                'is_active' => true,
            ],

            // SUPPORT SERVICES
            [
                'name' => 'Laboratory Personnel',
                'description' => 'Laboratory technicians and staff with access to diagnostic procedures, testing protocols, quality control measures, and equipment maintenance.',
                'is_active' => true,
            ],
            [
                'name' => 'Pharmacy Staff',
                'description' => 'Pharmacists and pharmacy technicians with access to drug protocols, medication management, insulin storage guidelines, and pharmaceutical care.',
                'is_active' => true,
            ],
            [
                'name' => 'Nutritionists',
                'description' => 'Nutrition specialists with access to feeding guidelines, malnutrition management protocols, diabetes nutrition education, and growth monitoring resources.',
                'is_active' => true,
            ],
            [
                'name' => 'Data Management Staff',
                'description' => 'Health information officers and data managers with access to data collection tools, reporting formats, and quality assurance procedures.',
                'is_active' => true,
            ],

            // PROGRAM-SPECIFIC GROUPS
            [
                'name' => 'PMTCT Program Staff',
                'description' => 'Prevention of Mother-to-Child Transmission program staff with access to HIV testing protocols, antiretroviral therapy guidelines, and counseling resources.',
                'is_active' => true,
            ],
            [
                'name' => 'Immunization Team',
                'description' => 'EPI (Expanded Program on Immunization) staff with access to vaccination schedules, cold chain management, adverse event protocols, and coverage monitoring.',
                'is_active' => true,
            ],
            [
                'name' => 'Nutrition Program Staff',
                'description' => 'Nutrition program coordinators and staff with access to malnutrition screening tools, therapeutic feeding protocols, and nutrition education materials.',
                'is_active' => true,
            ],
            [
                'name' => 'Emergency Response Team',
                'description' => 'Emergency obstetric and newborn care teams with access to emergency protocols, resuscitation procedures, and critical care guidelines.',
                'is_active' => true,
            ],

            // FACILITY LEVEL GROUPS
            [
                'name' => 'Primary Health Centers',
                'description' => 'Staff at primary healthcare facilities with access to basic clinical protocols, referral guidelines, and community health interventions.',
                'is_active' => true,
            ],
            [
                'name' => 'District Hospitals',
                'description' => 'District hospital staff with access to comprehensive emergency obstetric care, neonatal intensive care, and specialized treatment protocols.',
                'is_active' => true,
            ],
            [
                'name' => 'Referral Hospitals',
                'description' => 'Tertiary care staff with access to advanced procedures, specialized protocols, and complex case management guidelines.',
                'is_active' => true,
            ],
            [
                'name' => 'Maternity Units',
                'description' => 'Maternity ward staff with access to delivery protocols, postpartum care procedures, and newborn care guidelines.',
                'is_active' => true,
            ],

            // QUALITY & MONITORING GROUPS
            [
                'name' => 'Quality Improvement Teams',
                'description' => 'QI coordinators and teams with access to quality improvement methodologies, audit tools, performance indicators, and improvement strategies.',
                'is_active' => true,
            ],
            [
                'name' => 'Infection Prevention Teams',
                'description' => 'Infection prevention and control staff with access to hygiene protocols, antimicrobial stewardship guidelines, and outbreak response procedures.',
                'is_active' => true,
            ],
            [
                'name' => 'Research Teams',
                'description' => 'Research coordinators and staff with access to research protocols, data collection tools, ethics guidelines, and study procedures.',
                'is_active' => true,
            ],
            [
                'name' => 'Monitoring & Evaluation',
                'description' => 'M&E officers with access to indicator definitions, data collection tools, reporting templates, and evaluation frameworks.',
                'is_active' => true,
            ],

            // ADMINISTRATIVE GROUPS
            [
                'name' => 'Health Records Officers',
                'description' => 'Medical records and health information management staff with access to documentation standards, filing procedures, and data management protocols.',
                'is_active' => true,
            ],
            [
                'name' => 'Supply Chain Management',
                'description' => 'Logistics and supply chain staff with access to commodity management protocols, inventory procedures, and distribution guidelines.',
                'is_active' => true,
            ],
            [
                'name' => 'Finance & Administration',
                'description' => 'Administrative staff with access to financial procedures, procurement guidelines, and administrative protocols.',
                'is_active' => true,
            ],

            // EDUCATION & TRAINING GROUPS
            [
                'name' => 'Medical Students',
                'description' => 'Medical students and interns with access to educational materials, clinical guidelines, and supervised learning resources.',
                'is_active' => true,
            ],
            [
                'name' => 'Nursing Students',
                'slug' => 'nursing-students',
                'description' => 'Nursing students and trainees with access to basic clinical protocols, learning materials, and practical guidance.',
                'is_active' => true,
            ],
            [
                'name' => 'Continuing Education Participants',
                'description' => 'Healthcare providers enrolled in continuing education programs with access to advanced training materials and certification resources.',
                'is_active' => true,
            ],

            // EXTERNAL PARTNERS
            [
                'name' => 'NGO Partners',
                'description' => 'Non-governmental organization staff working in MNICH and diabetes programs with access to relevant implementation guidelines and coordination materials.',
                'is_active' => true,
            ],
            [
                'name' => 'Development Partners',
                'description' => 'International development partners and donors with access to program documentation, progress reports, and strategic planning materials.',
                'is_active' => true,
            ],
            [
                'name' => 'Technical Advisors',
                'description' => 'External technical advisors and consultants with access to specialized protocols, evaluation tools, and technical guidance documents.',
                'is_active' => true,
            ],

            // TEMPORARY/PROJECT-BASED GROUPS
            [
                'name' => 'Training Participants',
                'description' => 'Temporary group for healthcare providers participating in specific training programs with access to training materials and resources.',
                'is_active' => true,
            ],
            [
                'name' => 'Pilot Program Sites',
                'description' => 'Staff at facilities implementing pilot programs with access to pilot-specific protocols, implementation guides, and feedback tools.',
                'is_active' => true,
            ],

            [
                'name' => 'Compliance Officers',
                'description' => 'Regulatory and compliance staff with access to regulatory documents, audit materials, compliance checklists, and legal guidelines.',
                'is_active' => true,
            ],
            [
                'name' => 'Guest Lecturers',
                'description' => 'External trainers and guest speakers with limited access to presentation materials and relevant training resources.',
                'is_active' => true,
            ],
            [
                'name' => 'Contractors',
                'description' => 'External contractors and vendors with restricted access to relevant procedures and safety requirements for their specific work areas.',
                'is_active' => true,
            ],

            // TEMPORARY ACCESS GROUPS
            [
                'name' => 'Orientation Group',
                'description' => 'Temporary group for new staff during orientation period with access to onboarding materials and initial training resources.',
                'is_active' => true,
            ],
            [
                'name' => 'Project Team',
                'description' => 'Temporary access for specific project participants with access to project-related documentation and collaboration materials.',
                'is_active' => true,
            ],
        ];

        foreach ($accessGroups as $group) {
            AccessGroup::updateOrCreate(
                ['name' => $group['name']],
                $group
            );
        }

        $this->command->info('Access groups seeded successfully!');
    }
}
