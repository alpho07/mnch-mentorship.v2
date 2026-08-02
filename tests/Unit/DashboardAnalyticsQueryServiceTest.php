<?php

namespace Tests\Unit;

use App\Models\County;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Subcounty;
use App\Models\Training;
use App\Models\User;
use App\Services\DashboardAnalyticsQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardAnalyticsQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_coverage_summary_returns_scoped_counts(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $county = County::factory()->create(['name' => 'Kisumu']);
        $subcounty = Subcounty::create(['name' => 'Kisumu East', 'county_id' => $county->id]);
        $facility = Facility::factory()->create(['subcounty_id' => $subcounty->id]);
        Training::factory()->facilityMentorship()->create(['facility_id' => $facility->id, 'is_pilot' => false]);

        $service = new DashboardAnalyticsQueryService;
        $result = $service->countyCoverageSummary($admin, 'Kisumu');

        $this->assertSame('Kisumu', $result['county']);
        $this->assertSame(1, $result['facilities']);
        $this->assertSame(1, $result['mentorships']);
    }

    public function test_county_coverage_summary_returns_null_for_a_county_outside_the_users_scope(): void
    {
        $countyLead = User::factory()->create();
        Role::firstOrCreate(['name' => 'county_mentor_lead', 'guard_name' => 'web']);
        $countyLead->assignRole('county_mentor_lead');

        $ownCounty = County::factory()->create(['name' => 'Kisumu']);
        $otherCounty = County::factory()->create(['name' => 'Nairobi']);
        $countyLead->counties()->attach($ownCounty->id);

        $service = new DashboardAnalyticsQueryService;
        $result = $service->countyCoverageSummary($countyLead, 'Nairobi');

        $this->assertNull($result);
    }

    public function test_program_summary_returns_totals_and_county_breakdown(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $county = County::factory()->create(['name' => 'Kisumu']);
        $subcounty = Subcounty::create(['name' => 'Kisumu East', 'county_id' => $county->id]);
        $facility = Facility::factory()->create(['subcounty_id' => $subcounty->id]);
        Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'facility_id' => $facility->id, 'is_pilot' => false]);

        $service = new DashboardAnalyticsQueryService;
        $result = $service->programSummary($admin, 'Newborn Care');

        $this->assertSame(1, $result['mentorships']);
        $this->assertNotEmpty($result['by_county']);
    }

    public function test_training_completion_stats_returns_a_completion_rate(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        // Training's facility_id defaults to null on the factory — the
        // service intentionally scopes by facility, so a real one is
        // required for these rows to be counted at all.
        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        Training::factory()->facilityMentorship()->create(['facility_id' => $facilityA->id, 'is_pilot' => false, 'status' => 'completed']);
        Training::factory()->facilityMentorship()->create(['facility_id' => $facilityB->id, 'is_pilot' => false, 'status' => 'active']);

        $service = new DashboardAnalyticsQueryService;
        $result = $service->trainingCompletionStats($admin);

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(50.0, $result['completion_rate']);
    }
}
