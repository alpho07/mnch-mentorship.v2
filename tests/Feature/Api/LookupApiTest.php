<?php

namespace Tests\Feature\Api;

use App\Models\County;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LookupApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('facility_mentor');
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_can_list_programs(): void
    {
        Program::factory()->create(['name' => 'MNCH Program']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson('/api/v1/programs')
             ->assertOk()
             ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_can_list_program_modules(): void
    {
        $program = Program::factory()->create();
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson("/api/v1/programs/{$program->id}/modules")
             ->assertOk()
             ->assertJsonStructure(['data' => [['id', 'name', 'order_sequence', 'session_count']]]);
    }

    public function test_can_list_counties(): void
    {
        County::factory()->create(['name' => 'Nairobi']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson('/api/v1/counties')
             ->assertOk()
             ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_user_search_requires_two_chars(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson('/api/v1/users/search?q=a')
             ->assertUnprocessable();
    }

    public function test_unauthenticated_cannot_access_lookups(): void
    {
        $this->getJson('/api/v1/programs')->assertUnauthorized();
        $this->getJson('/api/v1/counties')->assertUnauthorized();
    }

    public function test_user_search_filters_by_facility(): void
    {
        $facility = \App\Models\Facility::factory()->create();
        $inFacility = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Nurse']);
        $inFacility->facilities()->attach($facility->id);

        User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Other']); // not in facility

        $results = $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson("/api/v1/users/search?q=Alice&facility_id={$facility->id}")
             ->assertOk()
             ->json('data');

        $this->assertCount(1, $results);
        $this->assertEquals('Alice Nurse', $results[0]['name']);
    }
}
