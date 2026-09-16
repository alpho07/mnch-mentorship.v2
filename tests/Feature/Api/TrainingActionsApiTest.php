<?php

namespace Tests\Feature\Api;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainingActionsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('mentee');
        $this->token = $this->user->createToken('test')->plainTextToken;
        $this->training = Training::factory()->create(['type' => 'global_training', 'status' => 'active']);
    }

    public function test_user_can_enroll_in_training(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/trainings/{$this->training->id}/enroll")
             ->assertCreated()
             ->assertJsonStructure(['data' => ['participant_id', 'status']]);

        $this->assertDatabaseHas('training_participants', [
            'training_id' => $this->training->id,
            'user_id'     => $this->user->id,
        ]);
    }

    public function test_duplicate_enrollment_returns_409(): void
    {
        TrainingParticipant::create(['training_id' => $this->training->id, 'user_id' => $this->user->id]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/trainings/{$this->training->id}/enroll")
             ->assertConflict();
    }

    public function test_user_can_mark_own_attendance(): void
    {
        TrainingParticipant::create(['training_id' => $this->training->id, 'user_id' => $this->user->id]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/trainings/{$this->training->id}/attendance")
             ->assertOk()
             ->assertJsonPath('message', 'Attendance recorded.');
    }

    public function test_unenrolled_user_cannot_mark_attendance(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/trainings/{$this->training->id}/attendance")
             ->assertForbidden();
    }

    public function test_can_list_resources(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson('/api/v1/resources')
             ->assertOk()
             ->assertJsonStructure(['data']);
    }
}
