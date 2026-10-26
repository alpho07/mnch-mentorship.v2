<?php

namespace Tests\Fixtures;

use App\Services\Analytics\DashboardFilters;
use App\Services\Analytics\MetricsSource;

/**
 * The live numbers shown on mnchkenyamentorship.org/analytics/dashboard on 26 Oct 2026,
 * captured from the page, its Chart.js data and its /geojson endpoint.
 *
 * Use it for tests and for previewing the screens without a database:
 *   $this->app->bind(MetricsSource::class, LiveSnapshotMetrics::class);
 *
 * Filters are ignored except where noted (programme on trainings, county/cadre/level on the mentor matrix,
 * county on assessed facilities), so the screens look exactly like the design.
 */
class LiveSnapshotMetrics implements MetricsSource
{
    private const MONTHS = ['2025-11', '2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'];

    private static function months(array $v): array
    {
        return array_combine(self::MONTHS, $v);
    }

    public function filterOptions(DashboardFilters $f): array
    {
        $programmes = match ($f->mode) {
            'training' => ['etat' => 'ETAT trained HCWs', 't1dm' => 'T1DM trained HCWs', 'cnc' => 'CNC trained HCWs', 'oxygen' => 'Oxygen & pulse oximetry trained HCWs'],
            'mentor' => ['Infant and Child Care' => 'Infant and Child Care', 'Maternal Health (EmONC)' => 'Maternal Health (EmONC)', 'Newborn Care' => 'Newborn Care'],
            'emonc' => [],
            default => ['Newborn Care' => 'Newborn Care', 'Infant and Child Care' => 'Infant and Child Care'],
        };
        $years = match ($f->mode) {
            'assessment' => [2026, 2025],
            'training' => [2024, 2023, 2022, 2018],
            'emonc' => [2026],
            default => [2026, 2024, 2023, 2022, 2018],
        };
        $cadres = array_values(array_unique(array_filter(array_column(self::MATRIX, 2), fn ($c) => $c !== '—')));
        sort($cadres);

        return ['programmes' => $programmes, 'years' => $years, 'subcounties' => [], 'facilities' => [], 'cadres' => $cadres];
    }

    public function registeredFacilities(DashboardFilters $f): int
    {
        return 10703;
    }

    public function countyReach(string $kind, DashboardFilters $f): array
    {
        $src = $kind === 'training' ? self::TRAINING_COUNTIES : self::MENTORSHIP_COUNTIES;
        $out = [];
        foreach ($src as $name => [$p, $people, $reached, $total]) {
            $out[$name] = ['programmes' => $p, 'people' => $people, 'facilities_reached' => $reached, 'facilities_total' => $total];
        }

        return $out;
    }

    public function facilityTypeReach(string $kind, DashboardFilters $f): array
    {
        $totals = ['COUNTY REFERRAL HOSPITAL' => 34, 'SUB COUNTY HOSPITAL' => 380, 'MISSION HOSPITAL' => 31, 'COUNTY HEALTH MANAGEMENT TEAM (CHMT)' => 0, 'TEACHING & REFERRAL HOSPITAL' => 6, 'FAITH BASED ORGANIZATION' => 86, 'HEALTH CENTRE' => 1215, 'ENUMERATOR' => 0, 'COUNTY HEADQUARTERS' => 0, 'NATIONAL REFERRAL HOSPITAL' => 1, 'DIABETES CENTRE' => 0, 'KENYA MEDICAL TRAINING COLLEGE(KMTC)' => 4, 'DISPENSARY' => 4617, 'KEMRI' => 6, 'MOH' => 2, 'NOT CLASSIFIED' => 4323];
        $reached = $kind === 'training'
            ? [34, 357, 2, 0, 6, 1, 315, 0, 0, 1, 0, 1, 17, 0, 1, 1]
            : [11, 8, 0, 0, 3, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        $out = [];
        foreach (array_keys($totals) as $i => $type) {
            $out[] = ['type' => $type, 'reached' => $reached[$i], 'total' => $totals[$type]];
        }

        return $out;
    }

    public function classLifecycle(DashboardFilters $f): array
    {
        return ['draft' => 16, 'active' => 53, 'completed' => 18];
    }

    /* Newborn & Child */

    public function mentorshipSummary(DashboardFilters $f): array
    {
        return ['mentorships' => 76, 'completed' => 58, 'active' => 18, 'unique_mentees' => 285, 'facilities' => 23, 'module_attendance_pct' => 43.0];
    }

    public function mentorshipsStartedByMonth(DashboardFilters $f): array
    {
        return self::months([0, 0, 0, 0, 0, 7, 6, 10, 11, 9, 6, 27]);
    }

    public function menteesByDepartment(DashboardFilters $f, int $limit = 10): array
    {
        return array_slice(['NBU' => 72, '0PD/MCH' => 21, 'MATERNITY' => 17, 'PAEDIATRIC WARD' => 17, 'PAEDS' => 15, 'ALL DEPTS' => 13, 'NEW BORN UNIT' => 12, 'ANC, FP, CWC, MAT' => 12, 'PEAD WARD' => 11, 'ALL' => 11], 0, $limit, true);
    }

    public function menteesByCadre(DashboardFilters $f, int $limit = 8): array
    {
        return array_slice(['General Nurses NBU' => 63, 'General Nurses - Paediatric' => 32, 'Clinical Officer' => 30, 'Midwives' => 28, 'Clinical Officer Paediatric' => 16, 'Maternity Theater Nurses' => 16, 'Clinical officer' => 14, 'Paediatric Nurses' => 13], 0, $limit, true);
    }

    public function topMentorshipProgrammes(DashboardFilters $f, int $limit = 5): array
    {
        return array_slice(['Newborn Care – Emuhaya Sub County Referral Hospital' => 26, 'Newborn Care – Bungoma County Referral Hospital' => 16, 'Newborn Care – Mama Lucy Kibaki Hospital' => 12, 'Newborn Care – Lamu County Hospital' => 12, 'Newborn Care – Kakamega Provincial General Hospital' => 11], 0, $limit, true);
    }

    public function menteeEnrolmentStatus(DashboardFilters $f): array
    {
        return ['enrolled' => 396, 'completed' => 262];
    }

    public function moduleCompletionBuckets(DashboardFilters $f): array
    {
        return ['0-25' => 243, '26-50' => 26, '51-75' => 13, '76-99' => 3, '100' => 118];
    }

    /* EmONC */

    public function emoncSummary(DashboardFilters $f): array
    {
        return ['active_mentees' => 0, 'active_classes' => 0, 'completed_classes' => 0, 'modules_done' => 0, 'pending_video_reviews' => 0, 'awaiting_mentor_approval' => 0, 'pending_drmh' => 0, 'certificates_issued' => 0];
    }

    public function emoncClassesByMonth(DashboardFilters $f): array
    {
        return self::months(array_fill(0, 12, 0));
    }

    public function emoncMenteeMatrix(DashboardFilters $f): array
    {
        return [];
    }

    /* Mentors */

    public function mentorSummary(DashboardFilters $f): array
    {
        return ['mentors' => 58, 'active_classes' => 53, 'completed_classes' => 18, 'draft_classes' => 16, 'modules_facilitated' => 110, 'mentees_supported' => 210, 'cpd_points' => 145, 'inactive_mentors' => 40, 'dropped_mentees' => 0, 'avg_assessment_score' => null, 'avg_practical_score' => null, 'levels' => ['AP' => 1, 'P' => 6, 'F' => 51]];
    }

    public function facilityExceptions(DashboardFilters $f): array
    {
        $rows = [['Nakuru Provincial General Hospital (PGH)', 2, 0, 83], ['Iten County Referral Hospital', 2, 0, 87], ['Busia County Referral Hospital', 1, 0, 0], ['Chuka County Referral Hospital', 1, 0, 100], ['Mama Rachel Ruto Maternity Hospital', 2, 0, 64], ['Moi Teaching & Referral Hospital', 1, 0, 14], ['Mbagathi District Hospital', 2, 14, 44], ['Kitale County Referral Hospital', 3, 25, 35], ['Mama Lucy Kibaki Hospital – Embakasi', 5, 38, 205], ['Lamu County Hospital', 2, 50, 0], ['Homa Bay County Teaching & Referral Hospital', 1, 100, 0], ['Kitui District Hospital', 1, 100, 0], ['Nyeri Provincial General Hospital (PGH)', 1, 100, 0]];

        return array_map(fn ($r) => ['facility' => $r[0], 'classes' => $r[1], 'completion_pct' => (float) $r[2], 'attendance_pct' => (float) $r[3]], $rows);
    }

    public function inactiveMentors(DashboardFilters $f): array
    {
        $rows = [['Phanice Khaindi Wanyonyi', 191], ['Samuel Ranogwa', 188], ['Samuel Ow Ochieng', 188], ['Penina Kanini Musyoka', 172], ['Zainab Kioni', 161], ['Stella Kangethe', 158], ['Jacinta Sungu Juma', 153], ['Lucky Kiguongo', 132], ['Jacinta Mbithe Mutuku', 131], ['Edwin Wandurwa', 130], ['Stacy Letitia Wangechi', 129], ['Wangari Lawreen T. Ndwiga', 122], ['Emily Ngio Ndumbu', 122], ['Anne Muthoni Ndungu', 118], ['Mariko Kipyator Yego', 117], ['Judy Wanjiru Mwaura', 96], ['Winfred Ndinguri', 96], ['Victor Maiyo', 96], ['Fatma Bunu Said', 96], ['Melan Asila Simid', 96], ['Mwangi Virginiah Nyambura', 95], ['Carolyne Adongo Obuya', 89], ['Brian Demesi', 89], ['Kenneth Kipkebut Yatich', 82], ['Daisy Aluso', 80], ['Dr Virginia Muviku', 59], ['Phyllis Wambui Maina', 55], ['Ann Waikwa', 48], ['Josephine Mumbua Kivuva', 41], ['Cherotich Beatrice', 40], ['Timina Wasilwa', 33], ['Peter Munyiri Ndirangu', 26], ['Mary Beatrice Kiende', 21], ['Dr. Winnie Mueni Saumu', 21], ['Grace Wanjiru Wachira', 20], ['Catherine Chepchirchir Langat', 20], ['Viola Kitur Rono', 19], ['Evangeline Nkatha Joseph', 19], ['Mercy Koech', 19], ['Viola Chebet', 19]];

        return array_map(fn ($r) => ['name' => $r[0], 'days' => $r[1]], $rows);
    }

    public function zeroCpdMentors(DashboardFilters $f): array
    {
        return [['name' => 'Esther Maina Wangeshi', 'facility' => 'Moi Teaching & Referral Hospital', 'classes' => 1]];
    }

    public function cpdLeaders(DashboardFilters $f, int $limit = 10): array
    {
        return array_slice(['Kevin Asunda Jumba' => 27, 'Dr. Winnie Mueni Saumu' => 9, 'Emily Ngio Ndumbu' => 8, 'Victor Maiyo' => 7, 'Stella Kangethe' => 5, 'Grace Wanjiru Wachira' => 5, 'Phyllis Wambui Maina' => 4, 'Brian Demesi' => 3, 'Daisy Aluso' => 3, 'Sharon Sunguti' => 3], 0, $limit, true);
    }

    public function reachLeaders(DashboardFilters $f, int $limit = 10): array
    {
        return array_slice(['Kevin Asunda Jumba' => 38, 'Daisy Aluso' => 16, 'Penina Kanini Musyoka' => 13, 'Sharon Sunguti' => 12, 'Catherine Chepchirchir Langat' => 11, 'Grace Wanjiru Wachira' => 9, 'Samuel Ranogwa' => 8, 'Zainab Kioni' => 8, 'Winfred Ndinguri' => 8, 'Victor Maiyo' => 7], 0, $limit, true);
    }

    public function classesStartedByMonth(DashboardFilters $f): array
    {
        return self::months([0, 0, 0, 0, 0, 8, 5, 13, 15, 11, 9, 26]);
    }

    public function mentorsByFacility(DashboardFilters $f, int $limit = 10): array
    {
        return array_slice(["Murang'a District Hospital" => 7, 'Mama Lucy Kibaki Hospital' => 5, 'Machakos Level 5 Hospital' => 4, 'Vihiga County Referral Hospital' => 4, 'Mbagathi District Hospital' => 4, 'Lamu County Hospital' => 4, 'Longisa County Referral Hospital' => 4, 'Kitale County Referral Hospital' => 3, 'Nakuru Provincial General Hospital' => 3, 'Iten County Referral Hospital' => 3], 0, $limit, true);
    }

    public function mentorsByDepartment(DashboardFilters $f, int $limit = 10): array
    {
        return array_slice(['NBU' => 15, '0PD/MCH' => 6, 'NEW BORN UNIT' => 5, 'NBU/IPD' => 4, 'ALL' => 4, 'PAEDIATRIC WARD' => 3, 'INPATIENT PAEDS WARD' => 3, 'ALL DEPTS' => 3, 'PEAD WARD' => 2, 'ADMINISTRATION' => 2], 0, $limit, true);
    }

    public function mentorsByModule(DashboardFilters $f, int $limit = 12): array
    {
        return array_slice(['Module 1: Infection Prevention and Control (IPC)' => 16, 'Module 6: Newborn Resuscitation' => 7, 'Module 11: Newborn Feeding' => 7, 'Module 4: Basic Life Support for Infant/Child (BLS)' => 7, 'Module 2: Infant and Family Centred Developmental Care (IFCDC)' => 6, 'Module 3: Triage' => 6, 'Module 5: Neonatal Thermoregulation' => 5, 'Module 4: Oxygen Therapy' => 4, 'Module 10: Neonatal Hypoglycaemia' => 4, 'Module 9: Management of Severe Acute Malnutrition' => 4, 'Module 8: Management of Dehydration' => 4, 'Module 3: Essential Newborn Care (ENC)' => 3], 0, $limit, true);
    }

    public function mentorClassMatrix(DashboardFilters $f): array
    {
        $rows = array_map(fn ($r) => ['mentor' => $r[0], 'county' => $r[1], 'cadre' => $r[2], 'department' => $r[3], 'facility' => $r[4], 'class' => $r[5], 'modules_done' => $r[6], 'modules_total' => $r[7], 'mentees' => $r[8], 'cpd' => $r[9], 'level' => $r[10]], self::MATRIX);

        return array_values(array_filter($rows, fn ($r) => (! $f->county || $r['county'] === $f->county)
            && (! $f->cadre || $r['cadre'] === $f->cadre)
            && (! $f->level || $r['level'] === $f->level)
            && (! $f->search || stripos($r['mentor'], $f->search) !== false)));
    }

    /* Assessments */

    public function assessmentSummary(DashboardFilters $f): array
    {
        return ['completed' => 39, 'in_progress' => 2, 'draft' => 0, 'yoy_pct' => 620.0, 'avg_score' => 64.6];
    }

    public function assessmentsByMonth(DashboardFilters $f): array
    {
        return array_map(fn ($b) => ['baseline' => $b, 'midline' => 0, 'endline' => 0], self::months([0, 5, 8, 6, 10, 0, 0, 0, 4, 3, 3, 2]));
    }

    public function gradeDistribution(DashboardFilters $f): array
    {
        return ['good' => 1, 'fair' => 18, 'poor' => 20];
    }

    public function sectionScores(DashboardFilters $f): array
    {
        $rows = [['Quality of Care', 83.1], ['Infrastructure', 79.0], ['Information Systems & Record keeping for Monitoring', 75.7], ['Infrastructure', 70.8], ['Skills Lab', 58.3], ['Information System and Record Keeping For Monitoring', 53.5], ['Quality of Care', 47.5], ['Skills Lab', 23.8]];

        return array_map(fn ($r) => ['section' => $r[0], 'score' => $r[1]], $rows);
    }

    public function assessedFacilities(DashboardFilters $f): array
    {
        $rows = array_map(fn ($r) => ['assessment_id' => null, 'facility' => $r[0], 'mfl' => $r[1], 'subcounty' => $r[2], 'county' => $r[3], 'date' => $r[4], 'type' => 'Baseline', 'assessor' => $r[5], 'skills_lab' => $r[6], 'room' => $r[7], 'feedback' => $r[8], 'eligibility' => $r[9], 'trained' => $r[10], 'mentorships' => $r[11]], self::FACILITIES);

        return array_values(array_filter($rows, fn ($r) => ! $f->county || $r['county'] === $f->county));
    }

    /* Trainings */

    public function trainingProgrammes(DashboardFilters $f): array
    {
        return [
            ['id' => 'etat', 'name' => 'ETAT trained HCWs', 'participants' => 1977, 'facilities' => 648, 'year' => 2018, 'status' => 'Completed'],
            ['id' => 't1dm', 'name' => 'T1DM trained HCWs', 'participants' => 1050, 'facilities' => 378, 'year' => 2023, 'status' => 'Completed'],
            ['id' => 'cnc', 'name' => 'CNC trained HCWs', 'participants' => 885, 'facilities' => 80, 'year' => 2024, 'status' => 'Completed'],
            ['id' => 'oxygen', 'name' => 'Oxygen & pulse oximetry trained HCWs', 'participants' => 791, 'facilities' => 234, 'year' => 2022, 'status' => 'Completed'],
        ];
    }

    public function trainingSummary(DashboardFilters $f): array
    {
        return ['unique_participants' => 3897, 'enrolments' => 4703, 'facilities_reached' => 736, 'last_12_months_enrolments' => 0];
    }

    public function participantsByDepartment(DashboardFilters $f, int $limit = 10): array
    {
        return array_slice(['PAEDIATRIC WARD' => 1159, 'NURSING OFFICER' => 481, 'LABOUR WARD' => 437, 'OUTPATIENT/MATERNAL CHILD HEALTH' => 427, 'INPATIENT PAEDS WARD' => 277, 'NEW BORN UNIT' => 269, 'CLINICAL OFFICER' => 154, 'BIOMEDICAL ENGINEER' => 115, 'COUNTY HEALTH MANAGEMENT TEAM (CHMT)' => 73, 'NUTRITION' => 72], 0, $limit, true);
    }

    public function participantsByCadre(DashboardFilters $f, int $limit = 8): array
    {
        return array_slice(['Clinical Officer Paediatric' => 1259, 'Neonatal Nurses' => 1120, 'Maternity Theater Nurses' => 310, 'Maternity Theater Anaesthetists' => 107, 'Medical Officer Intern' => 66, 'Clinical Officer' => 51, 'Midwives' => 39, 'Occupational Therapist' => 25], 0, $limit, true);
    }

    /* ───────────── data ───────────── */

    /** name => [programmes, unique mentees, facilities reached, facilities total] */
    private const MENTORSHIP_COUNTIES = ['Baringo' => [0, 0, 0, 228], 'Bomet' => [5, 5, 1, 156], 'Bungoma' => [1, 16, 1, 218], 'Busia' => [1, 1, 1, 127], 'Elgeyo Marakwet' => [4, 18, 1, 132], 'Embu' => [1, 1, 1, 185], 'Garissa' => [0, 0, 0, 125], 'Homa Bay' => [1, 1, 1, 283], 'Isiolo' => [0, 0, 0, 54], 'Kajiado' => [0, 0, 0, 293], 'Kakamega' => [1, 11, 1, 302], 'Kericho' => [0, 0, 0, 220], 'Kiambu' => [0, 0, 0, 526], 'Kilifi' => [0, 0, 0, 285], 'Kirinyaga' => [0, 0, 0, 267], 'Kisii' => [0, 0, 0, 172], 'Kisumu' => [1, 2, 1, 216], 'Kitui' => [1, 5, 1, 427], 'Kwale' => [0, 0, 0, 149], 'Laikipia' => [0, 0, 0, 138], 'Lamu' => [4, 14, 1, 55], 'Machakos' => [8, 50, 1, 372], 'Makueni' => [0, 0, 0, 310], 'Mandera' => [0, 0, 0, 88], 'Marsabit' => [0, 0, 0, 98], 'Meru' => [0, 0, 0, 461], 'Migori' => [0, 0, 0, 240], 'Mombasa' => [0, 0, 0, 343], "Murang'a" => [10, 9, 1, 326], 'Nairobi' => [11, 37, 2, 750], 'Nakuru' => [3, 7, 1, 492], 'Nandi' => [0, 0, 0, 190], 'Narok' => [0, 0, 0, 180], 'Nyamira' => [0, 0, 0, 151], 'Nyandarua' => [0, 0, 0, 159], 'Nyeri' => [8, 18, 2, 420], 'Samburu' => [0, 0, 0, 91], 'Siaya' => [0, 0, 0, 222], 'Taita Taveta' => [0, 0, 0, 94], 'Tana River' => [0, 0, 0, 69], 'Tharaka Nithi' => [1, 2, 1, 143], 'Trans Nzoia' => [3, 17, 1, 144], 'Turkana' => [0, 0, 0, 238], 'Uasin Gishu' => [5, 16, 2, 209], 'Vihiga' => [7, 55, 2, 107], 'Wajir' => [0, 0, 0, 125], 'West Pokot' => [0, 0, 0, 123]];

    /** name => [programmes, participants, facilities reached, facilities total] */
    private const TRAINING_COUNTIES = ['Baringo' => [4, 88, 12, 228], 'Bomet' => [3, 54, 9, 156], 'Bungoma' => [3, 64, 15, 218], 'Busia' => [3, 39, 11, 127], 'Elgeyo Marakwet' => [3, 50, 13, 132], 'Embu' => [4, 79, 16, 185], 'Garissa' => [2, 30, 10, 125], 'Homa Bay' => [3, 55, 16, 283], 'Isiolo' => [4, 60, 12, 54], 'Kajiado' => [4, 167, 26, 293], 'Kakamega' => [3, 41, 15, 302], 'Kericho' => [3, 72, 12, 220], 'Kiambu' => [4, 179, 32, 526], 'Kilifi' => [4, 99, 14, 285], 'Kirinyaga' => [3, 56, 14, 267], 'Kisii' => [4, 145, 27, 172], 'Kisumu' => [4, 159, 27, 216], 'Kitui' => [3, 77, 17, 427], 'Kwale' => [4, 69, 12, 149], 'Laikipia' => [3, 57, 9, 138], 'Lamu' => [3, 45, 7, 55], 'Machakos' => [4, 138, 18, 372], 'Makueni' => [4, 114, 14, 310], 'Mandera' => [4, 52, 5, 88], 'Marsabit' => [3, 39, 7, 98], 'Meru' => [4, 121, 21, 461], 'Migori' => [3, 62, 19, 240], 'Mombasa' => [4, 92, 14, 343], "Murang'a" => [4, 75, 17, 326], 'Nairobi' => [4, 216, 46, 750], 'Nakuru' => [4, 186, 40, 492], 'Nandi' => [4, 81, 10, 190], 'Narok' => [3, 65, 17, 180], 'Nyamira' => [4, 64, 25, 151], 'Nyandarua' => [3, 48, 10, 159], 'Nyeri' => [4, 90, 18, 420], 'Samburu' => [3, 50, 12, 91], 'Siaya' => [3, 58, 11, 222], 'Taita Taveta' => [4, 55, 12, 94], 'Tana River' => [3, 53, 10, 69], 'Tharaka Nithi' => [3, 66, 17, 143], 'Trans Nzoia' => [3, 43, 9, 144], 'Turkana' => [3, 43, 13, 238], 'Uasin Gishu' => [3, 72, 16, 209], 'Vihiga' => [3, 41, 11, 107], 'Wajir' => [3, 30, 11, 125], 'West Pokot' => [3, 34, 7, 123]];

    private const MATRIX = [['Kevin Asunda Jumba', 'Vihiga', 'Clinical Officer Paediatric', 'MCH, PAEDS', 'Emuhaya Sub County Referral Hospital', 'emuhaya subcounty hospital cohort', 12, 12, 26, 27, 'AP'], ['Kevin Asunda Jumba', 'Vihiga', 'Clinical Officer Paediatric', 'MCH, PAEDS', 'Vihiga County Referral Hosptial', 'VTRH PEDIATRIC DEPARTMENT', 0, 2, 2, 27, 'AP'], ['Kevin Asunda Jumba', 'Vihiga', 'Clinical Officer Paediatric', 'MCH, PAEDS', 'Vihiga County Referral Hosptial', 'VTRH PEDIATRICS-NEW STAFFS AND INTERNS', 13, 13, 10, 27, 'AP'], ['DR. WINNIE MUENI SAUMU', 'Tharaka Nithi', 'Paediatrician', 'IPD/PAEDS', 'Chuka County Refferal Hospital', 'Chuka paed cohort 1', 0, 1, 2, 9, 'P'], ['Emily Ngio Ndumbu', 'Machakos', 'Clinical Officer Paediatric', '0PD/MCH', 'Machakos Level 5 Hospital', 'Mch class', 8, 8, 3, 8, 'P'], ['Victor Maiyo', 'Elgeyo Marakwet', 'Others', 'ALL DEPTS', 'Iten County Referral Hospital', 'ITEN CRH NBU IFCC 2026', 0, 1, 7, 7, 'P'], ['Stella Kangethe', "Murang'a", 'Paediatrician', 'NBU', "Murang'a District Hospital", "Tr. Stella's class(cohort 1)", 1, 3, 1, 5, 'F'], ['Grace Wanjiru Wachira', "Murang'a", 'Clinical Officer Paediatric', '0PD/MCH', "Murang'a District Hospital", "Murang'a 013", 3, 3, 3, 5, 'F'], ['Grace Wanjiru Wachira', "Murang'a", 'Clinical Officer Paediatric', '0PD/MCH', "Murang'a District Hospital", "Murang'a 013", 1, 1, 1, 5, 'F'], ['Stella Kangethe', "Murang'a", 'Paediatrician', 'NBU', "Murang'a District Hospital", "Murang'a 01 Cohort 1", 1, 3, 1, 5, 'F'], ['Grace Wanjiru Wachira', "Murang'a", 'Clinical Officer Paediatric', '0PD/MCH', "Murang'a District Hospital", '013', 1, 1, 2, 5, 'F'], ['Grace Wanjiru Wachira', "Murang'a", 'Clinical Officer Paediatric', '0PD/MCH', "Murang'a District Hospital", '013', 0, 1, 3, 5, 'F'], ['Phyllis Wambui Maina', 'Nyeri', 'Neonatal Nurses', 'NBU', 'Karatina District Hospital', 'NBU 1', 1, 1, 3, 4, 'F'], ['Brian Demesi', 'Vihiga', 'Clinical Officer Paediatric', 'COUNTY HEALTH MANAGEMENT TEAM (CHMT)', 'Vihiga County Referral Hosptial', 'Class 1', 0, 1, 1, 3, 'F'], ['Daisy Aluso', 'Bungoma', 'Paediatrician', 'NBU/IPD', 'Bungoma County Referral Hospital', 'Class 1', 2, 2, 16, 3, 'F'], ['Sharon Sunguti', 'Nairobi', 'General Nurses NBU', 'NBU', 'Mama Lucy Kibaki Hospital -Embakasi', 'Neo001', 3, 5, 8, 3, 'F'], ['Sharon Sunguti', 'Nairobi', 'General Nurses NBU', 'NBU', 'Mama Lucy Kibaki Hospital -Embakasi', 'Neo 002', 0, 1, 4, 3, 'F'], ['TIMINA WASILWA', 'Trans Nzoia', 'Medical Officer', 'NEW BORN UNIT', 'Kitale County refferal Hospital', 'Wktrh Newborn Mentorsh6 Class 1', 0, 2, 7, 3, 'F'], ['Samuel Ranogwa', 'Vihiga', 'Paediatric Nurses', 'NBU', 'Vihiga County Referral Hosptial', 'APRIL 2025 CLASS', 0, 1, 8, 2, 'F'], ['Zainab Kioni', 'Machakos', 'General Nurses - Paediatric', 'NBU', 'Machakos Level 5 Hospital', 'COHORT C', 0, 1, 8, 2, 'F'], ['Anne Muthoni Ndungu', "Murang'a", 'Clinical Officer Paediatric', 'NBU', "Murang'a District Hospital", 'MURANGA 05', 2, 2, 1, 2, 'F'], ['Winfred Ndinguri', 'Elgeyo Marakwet', 'Paediatrician', 'ALL', 'Iten County Referral Hospital', 'ITEN CRH PAEDS 2026 IFCC', 0, 1, 8, 2, 'F'], ['Mwangi Virginiah Nyambura', "Murang'a", 'Nutritionist', 'NUTRITION', "Murang'a District Hospital", 'Nut 001', 1, 2, 1, 2, 'F'], ['Ann Waikwa', 'Nyeri', 'Midwives', 'ANC, FP, CWC, MAT', 'Karatina District Hospital', 'mTERNITY 1', 2, 2, 4, 2, 'F'], ['Dr Virginia Muviku', 'Kitui', 'Paediatrician', 'NBU/IPD', 'Kitui District Hospital', 'Class 1', 1, 1, 5, 2, 'F'], ['Viola Kitur Rono', 'Bomet', 'Midwives', 'CCC, ANC, MATERNITY', 'Longisa County Referal Hospital', 'Class 1', 1, 1, 1, 2, 'F'], ['Viola Kitur Rono', 'Bomet', 'Midwives', 'CCC, ANC, MATERNITY', 'Longisa County Referal Hospital', 'Class 1', 0, 1, 1, 2, 'F'], ['Mercy Koech', 'Bomet', 'Medical officer', 'ALL DEPTS', 'Longisa County Referal Hospital', 'Kanyits', 2, 2, 1, 2, 'F'], ['Samuel Ow Ochieng', 'Vihiga', '—', 'NBU/IPD', 'Vihiga County Referral Hosptial', 'VTRH paeds', 1, 1, 7, 1, 'F'], ['Penina Kanini musyoka', 'Machakos', 'Paediatrician', 'NEW BORN UNIT', 'Machakos Level 5 Hospital', 'newborn feeding', 0, 1, 4, 1, 'F'], ['Cherotich Beatrice', 'Nairobi', 'General Nurses - Paediatric', 'PAEDIATRIC WARD', 'Mama Lucy Kibaki Hospital -Embakasi', 'Documentation 001', 1, 3, 4, 1, 'F'], ['Edwin Wandurwa', 'Nairobi', 'General Nurses - Paediatric', 'PAEDIATRIC WARD', 'Mbagathi District Hospital', 'Basic Life Support for infant/Child', 1, 3, 4, 1, 'F'], ['Wangari Lawreen t Ndwiga', "Murang'a", 'Medical Officer', 'NBU', "Murang'a District Hospital", 'MCRH 04 IPC JUNE 2026', 1, 1, 1, 1, 'F'], ['Judy Wanjiru Mwaura', "Murang'a", 'Paediatric Nurses', 'INPATIENT PAEDS WARD', "Murang'a District Hospital", 'Judy 010', 1, 2, 1, 1, 'F'], ['Mariko Kipyator Yego', 'Trans Nzoia', 'Clinical Officer Paediatric', '0PD/MCH', 'Kitale County refferal Hospital', 'Peads/opd cohort', 1, 1, 3, 1, 'F'], ['Fatma Bunu Said', 'Lamu', 'TOTs in Facility', 'INPATIENT PAEDS WARD', 'Lamu County Hospital', 'Lamu county referral/paeds/cohort1', 1, 1, 1, 1, 'F'], ['Carolyne Adongo Obuya', 'Homa Bay', 'Paediatric Nurses', 'ADMINISTRATION', 'Homa Bay County Teaching and Referral Hospital', 'Mat cohort 1', 1, 1, 1, 1, 'F'], ['Josephine Mumbua Kivuva', 'Nairobi', 'General Nurses - Paediatric', 'PAEDS', 'Mama Lucy Kibaki Hospital -Embakasi', 'class 1', 1, 3, 3, 1, 'F'], ['Peter Munyiri Ndirangu', 'Nyeri', 'Clinical Officer Paediatric', 'CHILD WELFARE CLINIC', 'Nyeri Provincial General Hospital (PGH)', 'paeds team', 1, 1, 1, 1, 'F'], ['mary beatrice kiende', 'Machakos', 'Paediatric Nurses', 'PEAD WARD', 'Machakos Level 5 Hospital', '2nd Class', 0, 1, 7, 1, 'F'], ['Catherine Chepchirchir Langat', 'Uasin Gishu', 'General Nurses NBU', 'NBU', 'Mama Rachel Ruto Maternity Hospital', 'MRRMH-0002', 0, 1, 6, 1, 'F'], ['Catherine Chepchirchir Langat', 'Uasin Gishu', 'General Nurses NBU', 'NBU', 'Mama Rachel Ruto Maternity Hospital', 'MRRMH / NBU_003', 0, 1, 5, 1, 'F'], ['Penina Kanini musyoka', 'Machakos', 'Paediatrician', 'NEW BORN UNIT', 'Machakos Level 5 Hospital', 'oct/ward 1 A COHOT', 0, 1, 9, 1, 'F'], ['Phanice Khaindi Wanyonyi', 'Trans Nzoia', 'Paediatric Nurses', 'NBU', 'Kitale County refferal Hospital', 'APRIL 2026', 0, 1, 7, 0, 'F'], ['Jacinta Sungu Juma', 'Nakuru', 'Clinical Officer Paediatric', '0PD/MCH', 'Nakuru Provincial General Hospital (PGH)', 'NAKURU PGH PAEDS COHORT 001', 0, 1, 1, 0, 'F'], ['Lucky Kiguongo', 'Nakuru', 'Medical Officer', 'NEW BORN UNIT', 'Nakuru Provincial General Hospital (PGH)', 'June NBU cohort', 0, 1, 5, 0, 'F'], ['Jacinta Mbithe Mutuku', 'Nairobi', 'General Nurses - Paediatric', 'PAEDIATRIC WARD', 'Mbagathi District Hospital', 'Pioneer class', 0, 4, 5, 0, 'F'], ['Stacy Letitia Wangechi', "Murang'a", 'General Nurses - Paediatric', 'PEAD WARD', "Murang'a District Hospital", "Murang'a 011", 0, 1, 1, 0, 'F'], ['Melan Asila Simid', 'Lamu', 'General Nurses NBU', 'ALL DEPTS', 'Lamu County Hospital', 'Champions group 1', 0, 1, 1, 0, 'F'], ['Kenneth Kipkebut Yatich', 'Busia', 'Clinical Officer Paediatric', '0PD/MCH', 'Busia County Refferal Hospital', 'Bcrh cohort much 1', 0, 1, 1, 0, 'F'], ['Evangeline Nkatha Joseph', 'Nairobi', 'General Nurses NBU', 'NBU', 'Mama Lucy Kibaki Hospital -Embakasi', 'Neona1', 0, 1, 3, 0, 'F'], ['Viola Chebet', 'Bomet', 'Paediatric Nurses', 'INPATIENT PAEDS WARD', 'Longisa County Referal Hospital', 'Class 1 opd', 0, 1, 2, 0, 'F'], ['ESTHER MAINA WANGESHI', 'Uasin Gishu', 'Neonatal Nurses', 'ADM', 'Moi Teaching Refferal Hospital', 'Oct  IPC 01', 0, 1, 7, 0, 'F']];

    private const FACILITIES = [['Longisa County Referal Hospital', '15077', 'Bomet East', 'Bomet', '05 Oct 2026', 'Kevin Magare', 'No', 'No', 'Pending', 'Not Eligible', 'Not Eligible', 5], ['Embu Provincial General Hospital', '12004', 'Manyatta', 'Embu', '05 Oct 2026', 'Collins Simiti', 'No', 'No', 'Given', 'Not Eligible', 'Not Eligible', 1], ['Nyeri Provincial General Hospital (PGH)', '10903', 'Nyeri Central', 'Nyeri', '28 Sep 2026', 'Naomy Arussey', 'No', 'No', 'Given', 'Not Eligible', 'Not Eligible', 3], ['Mwai Kibaki Teaching and Referral Hospital', '25045', 'Nyeri Central', 'Nyeri', '28 Sep 2026', 'Stella Kangethe', 'No', 'No', 'Given', 'Not Eligible', 'Not Eligible', 0], ['Busia County Refferal Hospital', '15834', 'Matayos', 'Busia', '03 Aug 2026', 'Collins Simiti', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 1], ['Chuka County Refferal Hospital', '11973', 'Chuka', 'Tharaka Nithi', '02 Aug 2026', 'Dr. Emelda Manguro', 'No', 'No', 'Given', 'Not Eligible', 'Not Eligible', 1], ['Karatina District Hospital', '10485', 'Mathira East', 'Nyeri', '02 Aug 2026', 'Patrick Too', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 5], ['Homa Bay County Teaching and Referral Hospital', '13608', 'Homa Bay Town', 'Homa Bay', '27 Jul 2026', 'Collins Simiti', 'Yes', 'N/A', 'Given', 'Eligible', 'Yes', 1], ['Makueni County Referral Hospital', '12457', 'Makueni', 'Makueni', '27 Jul 2026', 'Dr. Emelda Manguro', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 0], ['Lamu County Hospital', '11512', 'Lamu West', 'Lamu', '20 Jul 2026', 'Collins Simiti', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 4], ['Kitui District Hospital', '12366', 'Kitui Central', 'Kitui', '19 Jul 2026', 'Naomy Arussey', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 1], ['Baringo County Referral Hospital', '14607', 'Baringo Central', 'Baringo', '12 Mar 2026', 'Collins Facility', 'No', 'Yes', 'Given', 'Eligible', 'No', 0], ['Kapenguria County Referral Hospital', '14701', 'West Pokot', 'West Pokot', '10 Mar 2026', 'Collins Facility', 'No', 'No', 'Given', 'Not Eligible', 'Not Eligible', 0], ['Kitale County Refferal Hospital', '14947', 'Kiminini', 'Trans Nzoia', '09 Mar 2026', 'Collins Facility', 'Yes', 'N/A', 'Given', 'Eligible', 'Yes', 3], ['Iten County Referral Hospital', '14586', 'Keiyo North', 'Elgeyo Marakwet', '05 Mar 2026', 'Collins Facility', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 4], ['Emuhaya Sub County Referral Hospital', '15876', 'Luanda', 'Vihiga', '04 Mar 2026', 'Collins Facility', 'No', 'No', 'Pending', 'Not Eligible', 'Not Eligible', 1], ['Moi Teaching Refferal Hospital', '15204', 'Ainabkoi', 'Uasin Gishu', '03 Mar 2026', 'Collins Facility', 'Yes', 'N/A', 'Given', 'Eligible', 'No', 2], ['Kakamega Provincial General Hospital (PGH)', '15915', 'Lurambi', 'Kakamega', '03 Mar 2026', 'Collins Facility', 'Yes', 'N/A', 'Given', 'Eligible', 'Yes', 1], ['Vihiga County Referral Hosptial', '16157', 'Vihiga', 'Vihiga', '02 Mar 2026', 'Collins Facility', 'Yes', 'N/A', 'Given', 'Eligible', 'Yes', 6], ['West Health Centre', '157791', 'Turbo', 'Uasin Gishu', '02 Mar 2026', 'Collins Facility', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 1], ['Kapsabet County Referral Hospital', '14749', 'Emgwen', 'Nandi', '02 Mar 2026', 'Collins Facility', 'No', 'No', 'Given', 'Not Eligible', 'Not Eligible', 0], ["Murang'a District Hospital", '10777', 'Kiharu', "Murang'a", '26 Feb 2026', 'Collins Facility', 'No', 'No', 'Given', 'Not Eligible', 'Not Eligible', 10], ['Mutuini Sub-District Hospital', '13105', 'Dagoretti South', 'Nairobi', '19 Feb 2026', 'Collins Simiti', 'No', 'No', 'Pending', 'Not Eligible', 'Not Eligible', 0], ['Mama Margaret Uhuru Hospital', '28885', 'Ruaraka', 'Nairobi', '18 Feb 2026', 'Collins Simiti', 'No', 'Yes', 'Pending', 'Partial', 'Not Eligible', 0], ['Machakos Level 5 Hospital', '12438', 'Machakos', 'Machakos', '12 Feb 2026', 'Collins Simiti', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 8], ['Kiambu County Referal Hospital', '10539', 'Kiambu Town', 'Kiambu', '10 Feb 2026', 'Collins Simiti', 'No', 'No', 'Given', 'Not Eligible', 'Not Eligible', 0], ['Thika Level 5 Hospital', '11094', 'Thika Town', 'Kiambu', '09 Feb 2026', 'Collins Simiti', 'No', 'Yes', 'Pending', 'Partial', 'Not Eligible', 0], ['Mama Lucy Kibaki Hospital – Embakasi', '17411', 'Embakasi Central', 'Nairobi', '29 Jan 2026', 'Collins Simiti', 'Yes', 'N/A', 'Given', 'Eligible', 'Yes', 8], ['Pumwani Maternity Hospital', '13156', 'Kamukunji', 'Nairobi', '29 Jan 2026', 'Nahashon Kangethe Kamweru', 'Yes', 'N/A', 'Given', 'Eligible', 'Yes', 0], ['Bondo District Hospital', '13507', 'Bondo', 'Siaya', '27 Jan 2026', 'Allan Govoga', 'No', 'Yes', 'Pending', 'Partial', 'Not Eligible', 0], ['Bondo District Hospital', '13507', 'Bondo', 'Siaya', '27 Jan 2026', 'Allan Govoga', 'No', 'Yes', 'Pending', 'Partial', 'Not Eligible', 0], ['Rwambwa Sub County Hospital', '14063', 'Alego Usonga', 'Siaya', '14 Jan 2026', 'Allan Govoga', 'No', 'No', 'Pending', 'Not Eligible', 'Not Eligible', 0], ['Ahero County Hospital', '13468', 'Nyando', 'Kisumu', '14 Jan 2026', 'Vanessa Njeri Gicheru', 'No', 'No', 'Pending', 'Not Eligible', 'Not Eligible', 0], ['Kisumu County Hospital', '13704', 'Kisumu Central', 'Kisumu', '13 Jan 2026', 'Vanessa Njeri Gicheru', 'No', 'Yes', 'Pending', 'Partial', 'Not Eligible', 0], ['Msambweni County Refferal Hospital', '11655', 'Msambweni', 'Kwale', '04 Dec 2025', 'Allan Govoga', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 0], ['Mbagathi District Hospital', '13080', 'Kibra', 'Nairobi', '03 Dec 2025', 'Collins Simiti', 'No', 'Yes', 'Given', 'Eligible', 'Yes', 4], ['Bungoma County Referral Hospital', '15828', 'Kanduyi', 'Bungoma', '03 Dec 2025', 'Nahashon Kangethe Kamweru', 'No', 'Yes', 'Given', 'Eligible', 'No', 1], ['Nakuru Provincial General Hospital (PGH)', '15288', 'Nakuru West', 'Nakuru', '02 Dec 2025', 'Joseph Gachibi Thiongo', 'Yes', 'N/A', 'Given', 'Eligible', 'Yes', 3]];
}
