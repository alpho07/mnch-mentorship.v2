<?php

namespace Tests\Unit;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserCanAccessFacilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_assigned_to_a_facility_can_access_that_facility(): void
    {
        $facility = Facility::factory()->create();
        $user = User::factory()->create(['facility_id' => $facility->id]);

        $this->assertTrue($user->canAccessFacility($facility->id));
    }

    public function test_a_user_assigned_to_one_facility_cannot_access_a_different_facility(): void
    {
        $ownFacility = Facility::factory()->create();
        $otherFacility = Facility::factory()->create();
        $user = User::factory()->create(['facility_id' => $ownFacility->id]);

        $this->assertFalse(
            $user->canAccessFacility($otherFacility->id),
            'canAccessFacility() was previously hardcoded to always return true — see '
            . 'docs/PHASE1-DISCOVERY-BASELINE.md §9.1 (now fixed). This is the regression case that '
            . 'defect would have failed.'
        );
    }

    public function test_a_user_with_no_facility_assignment_cannot_access_any_facility(): void
    {
        $facility = Facility::factory()->create();
        $user = User::factory()->create(['facility_id' => null]);

        $this->assertFalse($user->canAccessFacility($facility->id));
    }

    public function test_an_above_site_user_can_access_any_facility_regardless_of_assignment(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['facility_id' => null]);
        $user->assignRole('super_admin');
        $facility = Facility::factory()->create();

        $this->assertTrue($user->canAccessFacility($facility->id));
    }
}
