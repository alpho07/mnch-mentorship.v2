<?php

namespace Tests\Fixtures;

use App\Services\Site\SiteDataSource;

/**
 * Content of mnchkenyamentorship.org (home, /resources, /categories) as captured on 26 Oct 2026.
 * Bind it to preview the pages or run the tests without a database:
 *   $this->app->bind(SiteDataSource::class, LiveSiteSnapshot::class);
 *
 * The live list pages only ever show 37 distinct resources (3 are repeated across pages — see README),
 * so this snapshot holds those 37. Category counts are the live ones and add up to 40.
 */
class LiveSiteSnapshot implements SiteDataSource
{
    public function homeStats(): array
    {
        return ['mentorships' => 94, 'counties' => 20, 'mentees' => 302, 'facilities' => 28, 'active_mentorships' => 42, 'active_classes' => 60, 'pipeline_30d' => 5, 'pipeline_counties' => 3];
    }

    public function liveMentorshipsByCounty(): array
    {
        $raw = ['Nairobi' => [['Mama Lucy Kibaki Hospital – Embakasi', 10], ['Mbagathi District Hospital', 4], ['Pumwani Maternity Hospital', 1]], 'Nyeri' => [['Karatina District Hospital', 7], ['Nyeri Provincial General Hospital', 4], ['Mwai Kibaki Teaching & Referral Hospital', 1]], 'Vihiga' => [['Vihiga County Referral Hospital', 9], ['Emuhaya Sub County Referral Hospital', 1]], "Murang'a" => [["Murang'a District Hospital", 10]], 'Machakos' => [['Machakos Level 5 Hospital', 8]], 'Uasin Gishu' => [['Mama Rachel Ruto Maternity Hospital', 5], ['Moi Teaching & Referral Hospital', 2], ['West Health Centre', 1]], 'Bomet' => [['Longisa County Referral Hospital', 6]], 'Elgeyo Marakwet' => [['Iten County Referral Hospital', 4]], 'Lamu' => [['Lamu County Hospital', 4]], 'Trans Nzoia' => [['Kitale County Referral Hospital', 3]], 'Nakuru' => [['Nakuru Provincial General Hospital', 3]], 'Kisumu' => [['Kombewa County Referral Hospital', 2], ['Chulaimbo County Hospital', 1]], 'Kakamega' => [['Kakamega Provincial General Hospital', 1]], 'Homa Bay' => [['Homa Bay County Teaching & Referral Hospital', 1]], 'Busia' => [['Busia County Referral Hospital', 1]], 'Bungoma' => [['Bungoma County Referral Hospital', 1]], 'Kitui' => [['Kitui District Hospital', 1]], 'West Pokot' => [['Kapenguria County Referral Hospital', 1]], 'Tharaka Nithi' => [['Chuka County Referral Hospital', 1]], 'Embu' => [['Embu Provincial General Hospital', 1]]];

        return array_map(fn ($rows) => array_map(fn ($r) => ['facility' => $r[0], 'count' => $r[1]], $rows), $raw);
    }

    public function mentorshipCalendar(\DateTimeInterface $today): array
    {
        $rows = [
            ['Newborn Care', 'Lamu County Hospital', 'Lamu', '2026-10-13', '2026-10-27'],
            ['Newborn Care', 'Mbagathi District Hospital', 'Nairobi', '2026-10-13', '2026-10-27'],
            ['Infant & Child Care', 'Mama Lucy Kibaki Hospital – Embakasi', 'Nairobi', '2026-10-21', '2026-10-28'],
            ['Newborn Care', 'Mama Rachel Ruto Maternity Hospital', 'Uasin Gishu', '2026-10-09', '2026-10-30'],
            ['Newborn Care', 'Mama Rachel Ruto Maternity Hospital', 'Uasin Gishu', '2026-10-09', '2026-10-30'],
            ['Newborn Care', "Murang'a District Hospital", "Murang'a", '2026-10-09', '2026-10-30'],
            ['Newborn Care', 'Vihiga County Referral Hospital', 'Vihiga', '2026-10-27', '2026-11-10'],
            ['Newborn Care', 'Vihiga County Referral Hospital', 'Vihiga', '2026-10-27', '2026-11-10'],
            ['Newborn Care', 'Vihiga County Referral Hospital', 'Vihiga', '2026-10-27', '2026-11-10'],
            ['Infant & Child Care', 'Chuka County Referral Hospital', 'Tharaka Nithi', '2026-10-05', '2026-10-26', true],
            ['Newborn Care', 'Longisa County Referral Hospital', 'Bomet', '2026-10-09', '2026-10-23', true],
            ['Infant & Child Care', 'Longisa County Referral Hospital', 'Bomet', '2026-10-08', '2026-10-22', true],
            ['Newborn Care', 'Longisa County Referral Hospital', 'Bomet', '2026-10-07', '2026-10-21', true],
        ];

        return array_map(fn ($r) => ['programme' => $r[0], 'facility' => $r[1], 'county' => $r[2], 'starts_on' => $r[3], 'ends_on' => $r[4], 'closed' => $r[5] ?? false], $rows);
    }

    public function resources(): array
    {
        // slug, title, format, subcategory, views, downloads, created_at, advanced, featured, thumbnail, excerpt
        $rows = [
            ['emonc-training-and-mentorship-package-mentee-manual-2026-third-edition', 'EmONC Training & Mentorship Package — Mentee Manual (3rd ed., 2026)', 'Manual', 'Maternal Health', 286, 0, '2026-08-26', false, false, null, null],
            ['emonc-training-and-mentorship-package-mentor-manual-2026-third-edition', 'EmONC Training & Mentorship Package — Mentor Manual (3rd ed., 2026)', 'Manual', 'Maternal Health', 476, 0, '2026-08-26', false, true, null, 'Third edition with the Labour Care Guide and immediate newborn resuscitation updates.'],
            ['older-child-module-slides', 'Older Child Module Slides', 'Slides', 'Healthcare Provider Training', 168, 273, '2026-05-26', false, false, null, null],
            ['diabetic-ketoacidosis-dka', 'Diabetic Ketoacidosis (DKA)', 'Slides', 'Type 1 Diabetes', 168, 216, '2026-05-26', false, false, null, null],
            ['introduction-to-diabetes', 'Introduction to Diabetes', 'Slides', 'Type 1 Diabetes', 177, 175, '2026-05-26', false, false, null, null],
            ['meningitis-and-malaria', 'Meningitis and Malaria', 'Slides', 'Infant Health', 134, 205, '2026-05-26', false, false, null, null],
            ['severe-acute-malnutrition-recognition-and-early-treatment', 'Severe Acute Malnutrition — Recognition and Early Treatment', 'Slides', 'Infant Health', 183, 212, '2026-05-26', false, false, null, null],
            ['fluid-therapy-in-dehydration-due-to-diarrhoea', 'Fluid Therapy in Dehydration Due to Diarrhoea', 'Slides', 'Infant Health', 148, 219, '2026-05-26', false, false, null, null],
            ['management-of-an-infant-child-with-respiratory-distress-child-with-tb', 'Respiratory Distress — Child with TB', 'Slides', 'Infant Health', 175, 217, '2026-05-26', false, false, null, null],
            ['management-of-an-infant-child-with-respiratory-distress-asthma', 'Respiratory Distress — Asthma', 'Slides', 'Infant Health', 131, 161, '2026-05-26', false, false, null, null],
            ['management-of-an-infant-child-with-respiratory-distress-pneumonia', 'Respiratory Distress — Pneumonia', 'Slides', 'Infant Health', 128, 0, '2026-05-26', false, false, null, null],
            ['oxygen-therapy-infant-child', 'Oxygen Therapy (Infant & Child)', 'Slides', 'Infant Health', 124, 50, '2026-05-26', false, false, null, null],
            ['assessment-management-of-a-sick-infant-child', 'Assessment & Management of a Sick Infant or Child', 'Slides', 'Infant Health', 112, 0, '2026-05-26', false, false, null, null],
            ['basic-life-support', 'Basic Life Support', 'Slides', 'Infant Health', 109, 138, '2026-05-26', false, false, null, null],
            ['triage', 'Triage', 'Slides', 'Infant Health', 82, 192, '2026-05-26', false, false, null, null],
            ['newborn-transport-and-referral-system', 'Newborn Transport and Referral System', 'Slides', 'Newborn Care', 105, 0, '2026-05-26', false, false, null, null],
            ['feeds-and-fluids', 'Feeds and Fluids', 'Slides', 'Nutrition & Feeding', 98, 0, '2026-05-26', false, false, null, null],
            ['neonatal-hypoglycaemia', 'Neonatal Hypoglycaemia', 'Slides', 'Newborn Care', 96, 31, '2026-05-26', false, false, null, null],
            ['neonatal-jaundice', 'Neonatal Jaundice', 'Slides', 'Newborn Care', 119, 42, '2026-05-26', false, false, null, null],
            ['kangaroo-mother-care', 'Kangaroo Mother Care', 'Slides', 'Preterm & Low Birth Weight', 80, 40, '2026-05-26', false, false, 'images/site/kangaroo-care.jpg', null],
            ['apnoea-of-prematurity', 'Apnoea of Prematurity', 'Slides', 'Preterm & Low Birth Weight', 78, 141, '2026-05-26', false, false, null, null],
            ['use-of-continuous-positive-airway-pressure-cpap', 'Use of Continuous Positive Airway Pressure (CPAP)', 'Slides', 'Preterm & Low Birth Weight', 94, 196, '2026-05-26', false, false, null, null],
            ['ballard-score', 'Ballard Score', 'Slides', 'Preterm & Low Birth Weight', 84, 175, '2026-05-26', false, false, null, null],
            ['introduction-to-care-of-preterms', 'Introduction to Care of Preterms', 'Slides', 'Preterm & Low Birth Weight', 80, 147, '2026-05-26', false, false, null, null],
            ['oxygen-therapy', 'Oxygen Therapy', 'Slides', 'Newborn Care', 190, 244, '2026-05-26', false, false, null, null],
            ['neonatal-thermoregulation', 'Neonatal Thermoregulation', 'Slides', 'Newborn Care', 105, 206, '2026-05-26', false, false, null, null],
            ['use-of-a-plastic-wraps-in-preterm-newborns', 'Use of Plastic Wraps in Preterm Newborns', 'Slides', 'Preterm & Low Birth Weight', 92, 196, '2026-05-26', false, false, null, null],
            ['use-of-a-radiant-warmer', 'Use of a Radiant Warmer', 'Slides', 'Preterm & Low Birth Weight', 84, 138, '2026-05-26', false, false, null, null],
            ['use-of-incubators', 'Use of Incubators', 'Slides', 'Preterm & Low Birth Weight', 100, 123, '2026-05-26', false, false, null, null],
            ['neonatal-resuscitation', 'Neonatal Resuscitation', 'Slides', 'Emergency Protocols', 98, 220, '2026-05-26', false, false, null, null],
            ['danger-signs-and-neonatal-sepsis', 'Danger Signs and Neonatal Sepsis', 'Slides', 'Sick Newborn Care', 86, 171, '2026-05-26', false, false, null, null],
            ['ipc', 'Infection Prevention and Control (IPC)', 'Slides', 'Infection Prevention', 194, 265, '2026-05-26', false, false, null, null],
            ['infant-child-mentorship-manual', 'Infant & Child Mentorship Manual', 'Online', 'Child Health', 858, 0, '2026-02-26', true, true, null, 'Building provider skills in evidence-based newborn and child care through structured, supportive mentorship.'],
            ['newborn-mentorship-mentorship-manual', 'Newborn Mentorship — Mentorship Manual', 'Online', 'Newborn Care', 501, 0, '2026-02-26', true, true, null, 'A guide for newborn mentors running facility-based mentorship.'],
            ['basic-paediatric-protocol', 'Basic Paediatric Protocol (5th edition)', 'PDF', 'Clinical Protocols', 621, 0, '2026-02-26', false, true, null, 'The 5th edition of the protocol for quality care of children under five in Kenya.'],
            ['norms-and-standards-for-infant-child', 'Norms and Standards for Infant & Child', 'Guideline', 'Paediatric Care', 264, 0, '2026-02-26', false, true, 'images/site/cover-infant-norms.jpg', 'National norms and standards for paediatric services, from infancy to childhood.'],
            ['norms-and-standards-for-newborn-care', 'Norms and Standards for Newborn Care', 'Guideline', 'Newborn Care', 310, 0, '2026-02-26', true, true, 'images/site/cover-newborn-norms.jpg', 'National norms and standards for newborn care services in Kenyan health facilities.'],
        ];

        return array_map(fn ($r) => ['slug' => $r[0], 'title' => $r[1], 'format' => $r[2], 'subcategory' => $r[3], 'views' => $r[4], 'downloads' => $r[5], 'created_at' => $r[6], 'advanced' => $r[7], 'featured' => $r[8], 'thumbnail' => $r[9], 'excerpt' => $r[10]], $rows);
    }

    public function categories(): array
    {
        $c = [
            ['Maternal Health', 'maternal-health', 2, 'Maternal health resources covering pregnancy care, delivery and postpartum support.', [['Antenatal Care', 0], ['Labour & Delivery', 0], ['Postpartum Care', 0], ['Family Planning', 0], ['High-Risk Pregnancies', 0]]],
            ['Newborn Care', 'newborn-care', 8, 'Essential newborn care protocols, procedures and guidelines for the first 28 days of life.', [['Essential Newborn Care', 1], ['Breastfeeding Support', 0], ['Newborn Screening', 0], ['Sick Newborn Care', 1], ['Preterm & Low Birth Weight', 8]]],
            ['Infant Health', 'infant-health', 10, 'Infant health and development resources for children aged 0–12 months.', [['Growth Monitoring', 0], ['Immunization', 0], ['Nutrition & Feeding', 1], ['Common Infant Illnesses', 0]]],
            ['Child Health', 'child-health', 1, 'Child health resources for ages 1–18, including growth monitoring and development.', [['Child Development', 0], ['Adolescent Health', 0], ['Childhood Diseases', 0], ['Mental Health & Wellbeing', 0]]],
            ['Diabetes Management', 'diabetes-management', 0, 'Diabetes prevention, management and care resources for all age groups.', [['Type 1 Diabetes', 2], ['Type 2 Diabetes', 0], ['Gestational Diabetes', 0], ['Diabetes Prevention', 0], ['Complications Management', 0]]],
            ['Clinical Protocols', 'clinical-protocols', 1, 'Standardised clinical protocols and evidence-based treatment guidelines.', [['Emergency Protocols', 1], ['Infection Prevention', 1], ['Drug Protocols', 0]]],
            ['Training & Education', 'training-education', 0, 'Healthcare provider training materials and continuing education resources.', [['Healthcare Provider Training', 2], ['Community Health Education', 0], ['Patient Education', 0], ['Paediatric Care', 1]]],
            ['Quality Improvement', 'quality-improvement', 0, 'Quality assurance, improvement methods and performance monitoring tools.', []],
        ];

        return array_map(fn ($x) => ['name' => $x[0], 'slug' => $x[1], 'direct' => $x[2], 'description' => $x[3],
            'subcategories' => array_map(fn ($s) => ['name' => $s[0], 'slug' => strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s[0])), 'count' => $s[1]], $x[4])], $c);
    }
}
