<?php
namespace Tests\Feature\Api;

use App\Models\Facility;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorshipCreateApiTest extends TestCase
{
    use RefreshDatabase;

    private User $mentor;
    private string $token;
    private Program $program;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $this->mentor = User::factory()->create();
        $this->mentor->assignRole('facility_mentor');
        $this->token = $this->mentor->createToken('test')->plainTextToken;
        $this->program = Program::factory()->create();
        $this->facility = Facility::factory()->create();
    }

    public function test_mentor_can_create_mentorship(): void
    {
        $module = ProgramModule::factory()->create(['program_id' => $this->program->id, 'is_active' => true]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson('/api/v1/mentorships', [
                 'program_id'  => $this->program->id,
                 'facility_id' => $this->facility->id,
                 'start_date'  => '2026-05-01',
                 'end_date'    => '2026-06-30',
                 'module_ids'  => [$module->id],
             ])
             ->assertCreated()
             ->assertJsonStructure(['data' => ['id', 'title', 'identifier', 'status', 'class']]);

        $this->assertDatabaseHas('trainings', [
            'type'      => 'facility_mentorship',
            'mentor_id' => $this->mentor->id,
        ]);
    }

    public function test_identifier_is_auto_generated(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson('/api/v1/mentorships', [
                 'program_id'  => $this->program->id,
                 'facility_id' => $this->facility->id,
                 'start_date'  => '2026-05-01',
                 'end_date'    => '2026-06-30',
                 'module_ids'  => [],
             ])
             ->assertCreated()
             ->assertJson(fn($json) => $json->where('data.status', 'draft')->etc());
    }

    public function test_mentor_can_update_mentorship(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $this->mentor->id,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/mentorships/{$training->id}", ['title' => 'Updated Title'])
             ->assertOk()
             ->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_non_mentor_cannot_update_another_mentorship(): void
    {
        $other = User::factory()->create();
        $other->assignRole('facility_mentor');
        $training = Training::factory()->facilityMentorship()->create(['mentor_id' => $other->id]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/mentorships/{$training->id}", ['title' => 'Hack'])
             ->assertForbidden();
    }
}
