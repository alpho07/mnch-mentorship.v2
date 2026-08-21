<?php

namespace Tests\Feature;

use App\Filament\Pages\PendingMentorships;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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
}
