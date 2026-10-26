<?php

namespace App\Services\Analytics;

/**
 * Every raw number the five analytics dashboards need.
 *
 * Rules for implementations:
 *  - Return plain arrays in exactly the shapes documented here; DashboardPresenter does all maths and formatting.
 *  - Apply the filters in DashboardFilters wherever they make sense (county, programme, year …).
 *  - "Unique" means distinct people (users), "enrolments" means rows in a class/programme.
 *  - County names must match resources/data/kenya-counties.json (e.g. "Murang'a", "Elgeyo Marakwet").
 *
 * Production: DatabaseMetrics. Tests and previews: Tests\Fixtures\LiveSnapshotMetrics.
 */
interface MetricsSource
{
    /* ───────────────────────── shared ───────────────────────── */

    /**
     * Options for the filter dropdowns of the current mode. Subcounties and facilities depend on $f->county.
     *
     * @return array{programmes: array<string,string>, years: list<int>, subcounties: list<string>,
     *               facilities: list<string>, cadres: list<string>}  programmes: value => label
     */
    public function filterOptions(DashboardFilters $f): array;

    /** Count of all registered facilities (the coverage denominator). e.g. 10703 */
    public function registeredFacilities(DashboardFilters $f): int;

    /**
     * Per-county reach for the map, keyed by county name, ALL 47 counties included.
     * $kind: 'mentorship' (people = unique mentees) or 'training' (people = participants).
     *
     * @return array<string, array{programmes:int, people:int, facilities_reached:int, facilities_total:int}>
     */
    public function countyReach(string $kind, DashboardFilters $f): array;

    /**
     * Facilities reached vs registered, per facility type.
     *
     * @return list<array{type:string, reached:int, total:int}>
     */
    public function facilityTypeReach(string $kind, DashboardFilters $f): array;

    /** @return array{draft:int, active:int, completed:int} classes (mentorship mode: Newborn & Child + Infant & Child programmes) */
    public function classLifecycle(DashboardFilters $f): array;

    /* ───────────────────────── Newborn & Child mentorship ───────────────────────── */

    /**
     * @return array{mentorships:int, completed:int, active:int, unique_mentees:int, facilities:int, module_attendance_pct:?float}
     */
    public function mentorshipSummary(DashboardFilters $f): array;

    /** Mentorships started per month, oldest first, 12 keys 'Y-m'. @return array<string,int> */
    public function mentorshipsStartedByMonth(DashboardFilters $f): array;

    /** @return array<string,int> label => participants, sorted desc */
    public function menteesByDepartment(DashboardFilters $f, int $limit = 10): array;

    /** @return array<string,int> */
    public function menteesByCadre(DashboardFilters $f, int $limit = 8): array;

    /** @return array<string,int> programme name => mentees */
    public function topMentorshipProgrammes(DashboardFilters $f, int $limit = 5): array;

    /** @return array{enrolled:int, completed:int} enrolment records (not unique people) */
    public function menteeEnrolmentStatus(DashboardFilters $f): array;

    /** Mentees by share of their modules completed. @return array{'0-25':int,'26-50':int,'51-75':int,'76-99':int,'100':int} */
    public function moduleCompletionBuckets(DashboardFilters $f): array;

    /* ───────────────────────── EmONC ───────────────────────── */

    /**
     * @return array{active_mentees:int, active_classes:int, completed_classes:int, modules_done:int,
     *               pending_video_reviews:int, awaiting_mentor_approval:int, pending_drmh:int, certificates_issued:int}
     */
    public function emoncSummary(DashboardFilters $f): array;

    /** @return array<string,int> 'Y-m' => classes started, 12 keys */
    public function emoncClassesByMonth(DashboardFilters $f): array;

    /**
     * @return list<array{mentee:string, facility:string, class:string, modules_done:int, modules_total:int,
     *                    video_review:string, mentor_approval:string, drmh:string, certificate:string}>
     */
    public function emoncMenteeMatrix(DashboardFilters $f): array;

    /* ───────────────────────── Mentors ───────────────────────── */

    /**
     * @return array{mentors:int, active_classes:int, completed_classes:int, draft_classes:int, modules_facilitated:int,
     *               mentees_supported:int, cpd_points:int, inactive_mentors:int, dropped_mentees:int,
     *               avg_assessment_score:?float, avg_practical_score:?float,
     *               levels:array{AP:int, P:int, F:int}}
     */
    public function mentorSummary(DashboardFilters $f): array;

    /** @return list<array{facility:string, classes:int, completion_pct:float, attendance_pct:float}> */
    public function facilityExceptions(DashboardFilters $f): array;

    /** Mentors with no review, attendance mark or feedback for 14+ days. @return list<array{name:string, days:int}> */
    public function inactiveMentors(DashboardFilters $f): array;

    /** Mentors with a closed class and 0 CPD. @return list<array{name:string, facility:string, classes:int}> */
    public function zeroCpdMentors(DashboardFilters $f): array;

    /** @return array<string,int> mentor => CPD points */
    public function cpdLeaders(DashboardFilters $f, int $limit = 10): array;

    /** @return array<string,int> mentor => mentees supported */
    public function reachLeaders(DashboardFilters $f, int $limit = 10): array;

    /** @return array<string,int> 'Y-m' => classes started, 12 keys */
    public function classesStartedByMonth(DashboardFilters $f): array;

    /** @return array<string,int> */
    public function mentorsByFacility(DashboardFilters $f, int $limit = 10): array;

    /** @return array<string,int> */
    public function mentorsByDepartment(DashboardFilters $f, int $limit = 10): array;

    /** @return array<string,int> module title => unique mentors */
    public function mentorsByModule(DashboardFilters $f, int $limit = 12): array;

    /**
     * One row per live class, sorted by mentor CPD desc.
     *
     * @return list<array{mentor:string, county:string, cadre:string, department:string, facility:string, class:string,
     *                    modules_done:int, modules_total:int, mentees:int, cpd:int, level:string}>
     */
    public function mentorClassMatrix(DashboardFilters $f): array;

    /* ───────────────────────── Assessments ───────────────────────── */

    /** @return array{completed:int, in_progress:int, draft:int, yoy_pct:?float, avg_score:?float} */
    public function assessmentSummary(DashboardFilters $f): array;

    /** @return array<string, array{baseline:int, midline:int, endline:int}> 'Y-m' => counts, 12 keys */
    public function assessmentsByMonth(DashboardFilters $f): array;

    /** Completed assessments by grade. @return array{good:int, fair:int, poor:int} */
    public function gradeDistribution(DashboardFilters $f): array;

    /** Average % per section, as stored (duplicates allowed). @return list<array{section:string, score:float}> */
    public function sectionScores(DashboardFilters $f): array;

    /**
     * Latest assessment per facility.
     *
     * @return list<array{assessment_id:?int, facility:string, mfl:string, subcounty:string, county:string, date:string, type:string,
     *                    assessor:string, skills_lab:string, room:string, feedback:string, eligibility:string,
     *                    trained:string, mentorships:int}>
     *   skills_lab/room: 'Yes'|'No'|'N/A'; feedback: 'Given'|'Pending'; eligibility: 'Eligible'|'Partial'|'Not Eligible'
     */
    public function assessedFacilities(DashboardFilters $f): array;

    /* ───────────────────────── Trainings ───────────────────────── */

    /** @return list<array{id:string, name:string, participants:int, facilities:int, year:int, status:string}> */
    public function trainingProgrammes(DashboardFilters $f): array;

    /** @return array{unique_participants:int, enrolments:int, facilities_reached:int, last_12_months_enrolments:int} */
    public function trainingSummary(DashboardFilters $f): array;

    /** @return array<string,int> */
    public function participantsByDepartment(DashboardFilters $f, int $limit = 10): array;

    /** @return array<string,int> */
    public function participantsByCadre(DashboardFilters $f, int $limit = 8): array;
}
