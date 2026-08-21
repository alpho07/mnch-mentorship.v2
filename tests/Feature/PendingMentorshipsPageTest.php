<?php

namespace Tests\Feature;

use App\Filament\Pages\PendingMentorships;
use App\Models\County;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PendingMentorshipsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMentor(): User
    {
        $user = User::factory()->create(['name' => 'Mentor One']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo('create_mentorship::training');
        $this->actingAs($user);

        return $user;
    }

    public function test_page_is_hidden_from_users_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(PendingMentorships::canAccess());
    }

    public function test_mentor_sees_only_their_own_stalled_mentorships(): void
    {
        $mentor = $this->actingAsMentor();
        $otherMentor = User::factory()->create();

        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => $mentor->id,
            'title' => 'My Own Mentorship',
        ]);
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => $otherMentor->id,
            'title' => "Someone Else's Mentorship",
        ]);

        $response = $this->get(PendingMentorships::getUrl());

        $response->assertOk();
        $response->assertSee('My Own Mentorship');
        $response->assertDontSee("Someone Else's Mentorship");
    }

    public function test_empty_state_when_nothing_is_pending(): void
    {
        $this->actingAsMentor();

        $response = $this->get(PendingMentorships::getUrl());

        $response->assertOk();
        $response->assertSee('Nothing pending');
    }

    public function test_an_above_site_user_sees_every_mentors_stalled_mentorships(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin User']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        $admin->givePermissionTo('create_mentorship::training');
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'title' => "Somebody Else's Mentorship",
        ]);

        $response = $this->get(PendingMentorships::getUrl());

        $response->assertOk();
        $response->assertSee("Somebody Else's Mentorship");
    }

    public function test_a_county_lead_sees_their_own_plus_mentorships_in_their_county(): void
    {
        Role::firstOrCreate(['name' => 'county_mentor_lead', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);

        $myCounty = County::factory()->create();
        $otherCounty = County::factory()->create();

        $lead = User::factory()->create(['name' => 'County Lead']);
        $lead->givePermissionTo('create_mentorship::training');
        $lead->assignRole('county_mentor_lead');
        $lead->counties()->attach($myCounty->id);
        $this->actingAs($lead);

        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'county_id' => $myCounty->id,
            'title' => 'In My County',
        ]);
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'county_id' => $otherCounty->id,
            'title' => 'In A Different County',
        ]);
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => $lead->id,
            'county_id' => null,
            'title' => 'My Own With No County',
        ]);

        $response = $this->get(PendingMentorships::getUrl());

        $response->assertOk();
        $response->assertSee('In My County');
        $response->assertSee('My Own With No County');
        $response->assertDontSee('In A Different County');
    }
}
