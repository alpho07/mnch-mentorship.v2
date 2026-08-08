<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    // ── Action sets ──────────────────────────────────────────────────────────

    private const FULL   = ['view_any_','view_','create_','update_','restore_any_','restore_','replicate_','reorder_','delete_any_','delete_','force_delete_any_','force_delete_'];
    private const MANAGE = ['view_any_','view_','create_','update_','delete_any_','delete_'];
    private const WRITE  = ['view_any_','view_','create_','update_'];
    private const READ   = ['view_any_','view_'];

    // ── Resource slug groups ─────────────────────────────────────────────────

    private const TRAINING_SLUGS  = ['global::training','mentorship::training','training','training::export','approved::training::area'];
    private const ASSESSMENT_SLUGS = ['assessment','assessment::category','assessment::department','assessment::question','assessment::section','assessment::type','facility::assessment'];
    private const INDICATOR_SLUGS  = ['indicator','indicator::group','indicator::report::type'];
    private const REPORT_SLUGS     = ['monthly::report','report::template'];
    private const GEO_SLUGS        = ['county','subcounty','facility','facility::type','facility::level','facility::ownership','division','location'];
    private const CURRICULUM_SLUGS = ['program','program::module','program::module::quiz','module','activity','methodology','rubric::management'];
    private const SETTINGS_SLUGS   = ['grade','mentee::status','partner','cadre','department'];
    private const PROFILE_SLUGS    = ['mentee::profile','participant::profile'];
    private const KB_SLUGS         = ['resource','resource::category','resource::comment','resource::type','tag','category','topic','access::group'];
    private const INVENTORY_SLUGS  = ['inventory::item','stock::level','stock::request','supplier','commodity','commodity::category'];

    // ── Page groups ──────────────────────────────────────────────────────────

    private const PAGES_ADMIN = [
        'page_CoverageDashboard','page_CoverageOverview','page_FillReport',
        'page_IndicatorReporting','page_IndicatorsReference','page_KnowledgeBaseArticleDetail',
        'page_KnowledgeBasePortal','page_MentorDashboard','page_MentorshipProgressDashboard',
        'page_OrgUnitExplorer','page_ParticipantProfilePage','page_ReportManagement',
        'page_ReviewReport','page_TrainingCoverageDashboard','page_ValidationQueue',
        'page_ViewSubmission','page_HeadDrmhDashboard','page_EmoncDashboard','page_HeadDrmhReviewMentee',
        'page_MyCertificates','page_MentorCertificates',
    ];

    private const PAGES_MENTOR = [
        'page_MentorDashboard','page_IndicatorReporting','page_IndicatorsReference',
        'page_MentorshipProgressDashboard','page_ParticipantProfilePage',
        'page_TrainingCoverageDashboard','page_EmoncDashboard',
    ];

    private const PAGES_COUNTY = [
        'page_CoverageDashboard','page_CoverageOverview','page_OrgUnitExplorer',
        'page_IndicatorReporting','page_IndicatorsReference','page_MentorshipProgressDashboard',
        'page_TrainingCoverageDashboard','page_ValidationQueue',
    ];

    private const PAGES_INDICATOR = [
        'page_IndicatorReporting','page_IndicatorsReference','page_MentorshipProgressDashboard',
        'page_ValidationQueue','page_FillReport',
    ];

    private const PAGES_REPORT = [
        'page_ReportManagement','page_FillReport','page_ReviewReport',
        'page_ViewSubmission','page_IndicatorReporting',
    ];

    private const PAGES_KB = [
        'page_KnowledgeBaseArticleDetail','page_KnowledgeBasePortal',
    ];

    private const PAGES_MENTEE = [
        'page_MenteeDashboard',
        'page_MyCertificates',
    ];

    // ── Widget groups ────────────────────────────────────────────────────────

    private const WIDGETS_ALL = [
        'widget_AssessmentAnalyticsEmbed','widget_CentralStoreDistributionWidget',
        'widget_CentralStoreOverviewWidget','widget_CentralStoreStatsWidget',
        'widget_CentralStoreStockDistributionWidget','widget_CountyInsightsWidget',
        'widget_FacilityReportingStatusWidget','widget_FacilityRequestsWidget',
        'widget_FacilityStockOverviewWidget','widget_FacilityStockStatsWidget',
        'widget_FacilitySubmissionOverviewWidget','widget_HubFacilityStatsWidget',
        'widget_InventoryOverviewWidget','widget_KenyaTrainingHeatmapWidget',
        'widget_LowStockAlertWidget','widget_MenteeStatsOverview','widget_MenteeStatsWidget',
        'widget_MentorDashboard','widget_MentorshipAnalyticsEmbed',
        'widget_MentorshipGuidanceNotice','widget_MentorshipSetupNotice',
        'widget_MentorshipStatsOverview','widget_MentorStatsWidget',
        'widget_ParticipantsByCadreChartWidget','widget_ParticipantsByDepartmentChartWidget',
        'widget_QuickStockEntryWidget','widget_RecentReportsWidget',
        'widget_RecentSubmissionsWidget','widget_ReportingStatsWidget',
        'widget_SpokeManagementWidget','widget_TrainingAnalyticsEmbed',
        'widget_TrainingChartsWidget','widget_TrainingCoverageStatsWidget',
        'widget_TrainingInsightsWidget','widget_TrainingOverviewWidget',
        'widget_TrainingsByCountyChartWidget','widget_TrainingsByMonthChartWidget',
        'widget_UserStatsOverview','widget_ValidationQueueStatsWidget',
    ];

    private const WIDGETS_MENTOR = [
        'widget_MentorDashboard','widget_MentorStatsWidget','widget_MentorshipStatsOverview',
        'widget_MentorshipAnalyticsEmbed','widget_MentorshipGuidanceNotice',
        'widget_MentorshipSetupNotice','widget_SpokeManagementWidget',
        'widget_TrainingInsightsWidget','widget_TrainingAnalyticsEmbed',
        'widget_TrainingOverviewWidget','widget_ParticipantsByCadreChartWidget',
        'widget_ParticipantsByDepartmentChartWidget','widget_AssessmentAnalyticsEmbed',
        'widget_TrainingCoverageStatsWidget','widget_TrainingsByCountyChartWidget',
        'widget_TrainingsByMonthChartWidget',
    ];

    private const WIDGETS_MENTEE = [
        'widget_MenteeStatsWidget','widget_MenteeStatsOverview',
        'widget_MentorshipGuidanceNotice','widget_MentorshipSetupNotice',
    ];

    private const WIDGETS_COUNTY = [
        'widget_CountyInsightsWidget','widget_FacilityReportingStatusWidget',
        'widget_FacilitySubmissionOverviewWidget','widget_HubFacilityStatsWidget',
        'widget_KenyaTrainingHeatmapWidget','widget_RecentReportsWidget',
        'widget_RecentSubmissionsWidget','widget_ReportingStatsWidget',
        'widget_TrainingChartsWidget','widget_TrainingCoverageStatsWidget',
        'widget_TrainingInsightsWidget','widget_ValidationQueueStatsWidget',
        'widget_UserStatsOverview',
    ];

    private const WIDGETS_INVENTORY = [
        'widget_CentralStoreDistributionWidget','widget_CentralStoreOverviewWidget',
        'widget_CentralStoreStatsWidget','widget_CentralStoreStockDistributionWidget',
        'widget_FacilityRequestsWidget','widget_FacilityStockOverviewWidget',
        'widget_FacilityStockStatsWidget','widget_InventoryOverviewWidget',
        'widget_LowStockAlertWidget','widget_QuickStockEntryWidget',
    ];

    private const WIDGETS_REPORT = [
        'widget_FacilityReportingStatusWidget','widget_RecentReportsWidget',
        'widget_RecentSubmissionsWidget','widget_ReportingStatsWidget',
        'widget_ValidationQueueStatsWidget',
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->ensureRoles();
        $this->ensureExtraPermissions();

        // super_admin gets everything (Shield gate bypass is also in place)
        Role::findByName('super_admin', 'web')->syncPermissions(Permission::all());

        // Functional roles
        $this->seedAdmin();
        $this->seedNationalLevel();
        $this->seedCountySubcounty();
        $this->seedMentorRoles();
        $this->seedMentee();
        $this->seedAssessor();
        $this->seedInventory();
        $this->seedReporting();
        $this->seedResourceManager();
        $this->seedTeamLead();
        $this->seedHeadDrmh();
    }

    // ── Extra permissions not generated by Shield ─────────────────────────────

    private function ensureExtraPermissions(): void
    {
        $extras = [
            'page_HeadDrmhDashboard',
            'page_EmoncDashboard',
            'page_HeadDrmhReviewMentee',
            'page_MyCertificates',
            'page_MentorCertificates',
        ];

        foreach ($extras as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    // ── Role creation ─────────────────────────────────────────────────────────

    private function ensureRoles(): void
    {
        $names = [
            'super_admin','admin','division','national','county','subcounty',
            'facility_mentor','facility_mentor_lead',
            'spoke_mentor','spoke_mentor_lead',
            'county_mentor_lead','subcounty_mentor_lead','national_mentor_lead',
            'division_lead','national_mentor','head_drmh',
            'mentee','assessor','inventory','reporting','resource_manager','team_lead',
        ];

        foreach ($names as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    // ── Per-role seeders ──────────────────────────────────────────────────────

    private function seedAdmin(): void
    {
        // Admin has all permissions — can configure the platform
        $this->grant('admin', Permission::all()->pluck('name')->toArray());
    }

    private function seedNationalLevel(): void
    {
        // division, national, division_lead, national_mentor (stale alias) get full management
        $perms = array_merge(
            ['access_admin_panel','view_users','view_mentees','view_profile'],
            $this->perms(self::GEO_SLUGS, self::FULL),
            $this->perms(self::TRAINING_SLUGS, self::FULL),
            $this->perms(self::ASSESSMENT_SLUGS, self::FULL),
            $this->perms(self::INDICATOR_SLUGS, self::FULL),
            $this->perms(self::REPORT_SLUGS, self::FULL),
            $this->perms(self::CURRICULUM_SLUGS, self::READ),
            $this->perms(self::SETTINGS_SLUGS, self::READ),
            $this->perms(self::PROFILE_SLUGS, self::MANAGE),
            $this->perms(['user'], self::MANAGE),
            $this->perms(self::KB_SLUGS, self::READ),
            $this->perms(self::INVENTORY_SLUGS, self::READ),
            self::PAGES_ADMIN,
            self::WIDGETS_ALL,
        );

        foreach (['division','national','division_lead'] as $role) {
            $this->grant($role, $perms);
        }
    }

    private function seedCountySubcounty(): void
    {
        $perms = array_merge(
            ['access_admin_panel','view_users','view_mentees','view_profile'],
            $this->perms(self::GEO_SLUGS, self::READ),
            $this->perms(self::TRAINING_SLUGS, self::READ),
            $this->perms(self::ASSESSMENT_SLUGS, self::READ),
            $this->perms(self::INDICATOR_SLUGS, self::WRITE),
            $this->perms(self::REPORT_SLUGS, self::READ),
            $this->perms(self::CURRICULUM_SLUGS, self::READ),
            $this->perms(self::SETTINGS_SLUGS, self::READ),
            $this->perms(self::PROFILE_SLUGS, self::READ),
            $this->perms(self::KB_SLUGS, self::READ),
            $this->perms(['user'], self::READ),
            self::PAGES_COUNTY,
            self::WIDGETS_COUNTY,
        );

        foreach (['county','subcounty'] as $role) {
            $this->grant($role, $perms);
        }
    }

    private function seedMentorRoles(): void
    {
        // Base permissions for all mentor variants — exactly 5 nav items:
        //   Mentor Dashboard | MOH Trainings | Mentorships | Export Center | Indicator Reporting
        // Data scope differences are handled in MentorshipTrainingResource::applyRoleScope()
        $base = array_unique(array_merge(
            ['access_admin_panel', 'view_users', 'view_mentees', 'view_profile'],
            $this->perms(['mentorship::training'], self::MANAGE),    // → Mentorships nav
            $this->perms(['global::training'],     self::READ),      // → MOH Trainings nav
            $this->perms(['training::export'],     self::READ),      // → Export Center nav
            ['page_MentorDashboard', 'page_IndicatorReporting', 'page_FillReport', 'page_MentorCertificates'],
            self::WIDGETS_MENTOR,
        ));

        // Rubric assessment permissions — mentors conduct practical assessments
        $base = array_merge($base, $this->perms(['rubric::assessment'], self::MANAGE));

        // All facility/spoke/county/subcounty/national mentor roles share $base.
        // Data scoping per role is handled in MentorshipTrainingResource::applyRoleScope():
        //   national_mentor       → all facilities (no filter)
        //   county_mentor_lead    → facilities in their county
        //   subcounty_mentor_lead → facilities in their subcounty
        //   facility_mentor_lead  → all mentorships in their assigned facility
        //   facility_mentor       → only mentorships they are assigned to
        foreach ([
            'facility_mentor', 'facility_mentor_lead',
            'spoke_mentor', 'spoke_mentor_lead',
            'county_mentor_lead', 'subcounty_mentor_lead',
            'national_mentor',
        ] as $role) {
            $this->grant($role, $base);
        }

        // National mentor lead — full authority
        $nationalMentorPerms = array_merge(
            $base,
            $this->perms(self::TRAINING_SLUGS, self::FULL),
            $this->perms(self::ASSESSMENT_SLUGS, self::FULL),
            $this->perms(self::INDICATOR_SLUGS, self::FULL),
            $this->perms(self::REPORT_SLUGS, self::MANAGE),
            $this->perms(self::PROFILE_SLUGS, self::FULL),
            $this->perms(self::GEO_SLUGS, self::READ),
            $this->perms(self::SETTINGS_SLUGS, self::READ),
            $this->perms(['user'], self::MANAGE),
            self::PAGES_ADMIN,
            self::WIDGETS_ALL,
        );
        $this->grant('national_mentor_lead', array_unique($nationalMentorPerms));
    }

    private function seedMentee(): void
    {
        // Strip everything; mentees only access the mentee dashboard
        $this->grant('mentee', array_merge(
            ['access_admin_panel'],
            self::PAGES_MENTEE,
            self::WIDGETS_MENTEE,
        ));
    }

    private function seedAssessor(): void
    {
        $this->grant('assessor', array_merge(
            ['access_admin_panel','view_profile'],
            $this->perms(self::ASSESSMENT_SLUGS, self::FULL),
            $this->perms(self::GEO_SLUGS, self::READ),
            $this->perms(['user'], self::READ),
            ['widget_AssessmentAnalyticsEmbed'],
        ));
    }

    private function seedInventory(): void
    {
        $this->grant('inventory', array_merge(
            ['access_admin_panel','view_profile'],
            $this->perms(self::INVENTORY_SLUGS, self::FULL),
            $this->perms(self::GEO_SLUGS, self::READ),
            ['widget_AssessmentAnalyticsEmbed'],
            self::WIDGETS_INVENTORY,
        ));
    }

    private function seedReporting(): void
    {
        $this->grant('reporting', array_merge(
            ['access_admin_panel','view_profile'],
            $this->perms(self::REPORT_SLUGS, self::FULL),
            $this->perms(self::INDICATOR_SLUGS, self::WRITE),
            $this->perms(self::TRAINING_SLUGS, self::READ),
            $this->perms(self::GEO_SLUGS, self::READ),
            self::PAGES_REPORT,
            self::PAGES_INDICATOR,
            self::WIDGETS_REPORT,
        ));
    }

    private function seedResourceManager(): void
    {
        $this->grant('resource_manager', array_merge(
            ['access_admin_panel','view_profile'],
            $this->perms(self::KB_SLUGS, self::FULL),
            self::PAGES_KB,
        ));
    }

    private function seedTeamLead(): void
    {
        // Team lead — similar scope to county_mentor_lead
        $this->grant('team_lead', array_merge(
            ['access_admin_panel','view_users','view_profile'],
            $this->perms(self::TRAINING_SLUGS, self::MANAGE),
            $this->perms(self::ASSESSMENT_SLUGS, self::MANAGE),
            $this->perms(self::INDICATOR_SLUGS, self::WRITE),
            $this->perms(self::REPORT_SLUGS, self::READ),
            $this->perms(self::GEO_SLUGS, self::READ),
            $this->perms(self::PROFILE_SLUGS, self::MANAGE),
            $this->perms(['user'], self::READ),
            self::PAGES_MENTOR,
            self::PAGES_COUNTY,
            self::WIDGETS_MENTOR,
        ));
    }

    private function seedHeadDrmh(): void
    {
        // Head DRMH — only their own dashboard + read-only view of mentorships
        $this->grant('head_drmh', [
            'access_admin_panel',
            'page_HeadDrmhDashboard',
            'page_HeadDrmhReviewMentee',
            'view_any_mentorship::training',
            'view_mentorship::training',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function grant(string $roleName, array $permNames): void
    {
        $role = Role::findByName($roleName, 'web');
        $existing = Permission::whereIn('name', array_unique($permNames))->get();
        $role->syncPermissions($existing);
    }

    private function perms(array $slugs, array $actions): array
    {
        $result = [];
        foreach ($slugs as $slug) {
            foreach ($actions as $action) {
                $result[] = $action . $slug;
            }
        }
        return $result;
    }
}
