<?php

namespace Tests\Unit;

use App\Models\County;
use App\Models\Facility;
use App\Models\Subcounty;
use App\Models\Training;
use App\Models\User;
use App\Services\Chat\Tools\DashboardAnalyticsToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardAnalyticsToolProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_county_coverage_summary_tool_returns_data_for_an_authorized_user(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $county = County::factory()->create(['name' => 'Kisumu']);
        $subcounty = Subcounty::create(['name' => 'Kisumu East', 'county_id' => $county->id]);
        $facility = Facility::factory()->create(['subcounty_id' => $subcounty->id]);
        Training::factory()->facilityMentorship()->create(['facility_id' => $facility->id, 'is_pilot' => false]);

        $tool = DashboardAnalyticsToolProvider::countyCoverageTool();

        $result = $tool->execute(['county_name' => 'Kisumu'], $admin);

        $this->assertSame('Kisumu', $result['county']);
    }
}
