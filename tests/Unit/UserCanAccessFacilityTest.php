<?php

namespace Tests\Unit;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCanAccessFacilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_no_facility_assignment_can_currently_access_any_facility(): void
    {
        $user = User::factory()->create(['facility_id' => null]);
        $otherFacility = Facility::factory()->create();

        $this->assertTrue(
            $user->canAccessFacility($otherFacility->id),
            'canAccessFacility() currently always returns true — the real check is commented out '
            . '(see docs/PHASE1-DISCOVERY-BASELINE.md §9.1). This test locks in that known-bad behavior. '
            . 'If this assertion starts failing, the check was restored — flip this test to assertFalse '
            . 'and add a companion test proving an above-site/scoped user still gets true.'
        );
    }

    public function test_a_user_assigned_to_one_facility_can_currently_access_a_different_facility_too(): void
    {
        $ownFacility = Facility::factory()->create();
        $otherFacility = Facility::factory()->create();
        $user = User::factory()->create(['facility_id' => $ownFacility->id]);

        $this->assertTrue(
            $user->canAccessFacility($otherFacility->id),
            'Real scoping (isAboveSite() || scopedFacilityIds()->contains()) is commented out in '
            . 'User::canAccessFacility() — see docs/PHASE1-DISCOVERY-BASELINE.md §9.1. Once fixed, this '
            . 'specific case (a facility-scoped user checking a facility they do NOT belong to) should '
            . 'assertFalse instead.'
        );
    }
}
