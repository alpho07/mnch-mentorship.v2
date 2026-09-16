<?php

namespace Tests\Feature\Api;

use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $mentor;
    private string $token;
    private Training $training;
    private MentorshipClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        $this->mentor = User::factory()->create();
        $this->mentor->assignRole('facility_mentor');
        $this->token = $this->mentor->createToken('test')->plainTextToken;

        $this->training = Training::factory()->facilityMentorship()->create(['mentor_id' => $this->mentor->id]);
        $this->class = MentorshipClass::factory()->create([
            'training_id' => $this->training->id,
            'status'      => 'draft',
        ]);
    }

    public function test_mentor_can_enroll_mentee(): void
    {
        $mentee = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/classes/{$this->class->id}/mentees", ['user_id' => $mentee->id])
             ->assertCreated()
             ->assertJsonStructure(['data' => ['participant_id', 'user_id', 'name']]);

        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $this->class->id,
            'user_id'             => $mentee->id,
        ]);
    }

    public function test_non_mentor_cannot_enroll_mentee(): void
    {
        $other = User::factory()->create();
        $other->assignRole('facility_mentor');
        $otherToken = $other->createToken('test')->plainTextToken;
        $mentee = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $otherToken)
             ->postJson("/api/v1/classes/{$this->class->id}/mentees", ['user_id' => $mentee->id])
             ->assertForbidden();
    }

    public function test_mentor_can_remove_enrolled_mentee(): void
    {
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $this->class->id,
            'user_id'             => $mentee->id,
            'status'              => 'enrolled',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->deleteJson("/api/v1/classes/{$this->class->id}/mentees/{$participant->id}")
             ->assertOk();

        $this->assertDatabaseMissing('class_participants', ['id' => $participant->id]);
    }
}
