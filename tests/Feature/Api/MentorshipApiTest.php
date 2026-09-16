<?php

namespace Tests\Feature\Api;

use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorshipApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the roles exist for tests
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
    }

    public function test_mentor_can_list_their_mentorships(): void
    {
        $mentor = User::factory()->create();
        $mentor->assignRole('facility_mentor');

        $training = Training::factory()->create([
            'type' => 'facility_mentorship',
            'mentor_id' => $mentor->id,
            'status' => 'active',
        ]);

        $token = $mentor->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/mentorships')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'title', 'status', 'class_count']]]);
    }

    public function test_draft_mentorships_are_excluded_from_the_list(): void
    {
        $mentor = User::factory()->create();
        $mentor->assignRole('facility_mentor');

        $active = Training::factory()->create([
            'type' => 'facility_mentorship',
            'mentor_id' => $mentor->id,
            'status' => 'active',
            'title' => 'Active Mentorship',
        ]);
        Training::factory()->create([
            'type' => 'facility_mentorship',
            'mentor_id' => $mentor->id,
            'status' => 'draft',
            'title' => 'Draft Mentorship',
        ]);

        $token = $mentor->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/mentorships')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertSame(1, $ids->count());
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/v1/mentorships')->assertUnauthorized();
    }
}
