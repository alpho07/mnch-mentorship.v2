<?php

/*
 * Program-area access matrix (newborn & child vs EmONC).
 *
 * Edit this file, then run:  php artisan access:sync
 * The command is idempotent and only touches the roles named below
 * (admin, newborn_child_admin, emonc_admin) — it never resets other roles.
 * Permissions are Spatie/Shield permissions, so anything can still be fine
 * tuned afterwards in the Shield role editor (until the next sync).
 */

$dashboards = [
    'page_CoverageDashboard', 'page_CoverageOverview', 'page_MentorDashboard', 'page_MenteeDashboard',
    'page_MyCertificates', 'page_MentorCertificates', 'page_HeadDrmhDashboard', 'page_HeadDrmhReviewMentee',
    'page_OrgUnitExplorer', 'page_EmoncDashboard',
];

$reportsAnalytics = ['page_TrainingCoverageDashboard', 'page_ReportManagement'];

$reportingIndicators = [
    'page_IndicatorReporting', 'page_FillReport', 'page_ReviewReport', 'page_ViewSubmission',
    'page_ValidationQueue', 'page_MentorshipProgressDashboard',
];

$common = array_merge(
    ['access_admin_panel', 'page_MyProfile', 'page_KnowledgeBaseArticleDetail', 'page_KnowledgeBasePortal'],
    $dashboards, $reportsAnalytics, $reportingIndicators,
);

$resourceManagement = ['resource', 'resource::category', 'resource::comment', 'resource::type', 'tag'];
$training = ['global::training', 'mentorship::training', 'training', 'training::export'];
$curriculum = ['program', 'program::module', 'module', 'topic', 'methodology'];

return [

    'areas' => [
        'newborn_child' => ['label' => 'Newborn & Child', 'role' => 'newborn_child_admin'],
        'emonc' => ['label' => 'EmONC (Maternal Health)', 'role' => 'emonc_admin'],
    ],

    /*
     * Mentor and mentee roles are universal: they are not tied to a program
     * area, and area admins can see them in the Users list.
     */
    'universal_roles' => [
        'mentee', 'facility_mentor', 'facility_mentor_lead', 'spoke_mentor', 'spoke_mentor_lead',
        'county_mentor_lead', 'subcounty_mentor_lead', 'national_mentor', 'national_mentor_lead',
    ],

    /*
     * `admin` gets every permission EXCEPT these. super_admin is unaffected.
     */
    'admin_excluded' => [
        'resources' => ['scope', 'role', 'rag::document', 'rag::eval::case', 'rag::retrieval::trace', 'rag::lexicon::term', 'rag::term::bridge'],
        'permissions' => [
            'page_MentorshipSettings',  // Mentorship settings
            'page_DatabaseManagement',  // App configuration
            'page_RagChat', 'use_rag_chat', // Knowledge base
        ],
    ],

    /*
     * Resource slugs get the full Shield action set (view_any, create, update, delete, ...).
     * Data scoping inside each resource is handled by App\Support\ProgramArea.
     */
    'roles' => [

        'newborn_child_admin' => [
            'resources' => array_merge(
                ['assessment', 'facility::assessment'],       // Assessments (scoped)
                $training,                                    // MOH, Mentorship, Pending, Export (scoped)
                ['indicator', 'indicator::group', 'indicator::report::type'], // Indicator Catalog (scoped)
                $curriculum,                                  // Curriculum (scoped) — no rubrics
                $resourceManagement,
                ['user'],                                     // Users (scoped)
            ),
            'permissions' => array_merge($common, ['page_IndicatorsReference']),
            'widgets' => 'all',
        ],

        'emonc_admin' => [
            'resources' => array_merge(
                ['assessment', 'facility::assessment'],
                $training,
                ['program::module::quiz'],                    // Quiz management (EmONC only for now)
                $curriculum,
                ['activity'],                                 // Activities are EmONC only
                ['rubric::management', 'rubric::assessment'], // Rubric Management
                $resourceManagement,
                ['user'],
            ),
            'permissions' => $common,                         // no Indicator Catalog
            'widgets' => 'all',
        ],
    ],

    // Widgets that are never granted to area admins (inventory / central store).
    'widget_denylist_patterns' => ['CentralStore', 'Stock', 'Inventory', 'HubFacility', 'FacilityRequests', 'SpokeManagement'],
];
