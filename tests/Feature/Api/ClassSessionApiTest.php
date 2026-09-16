<?php

namespace Tests\Feature\Api;

use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassSessionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $mentor;
    private string $token;
    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $this->mentor = User::factory()->create();
        $this->mentor->assignRole('facility_mentor');
        $this->token = $this->mentor->createToken('test')->plainTextToken;

        $training = Training::factory()->facilityMentorship()->create(['mentor_id' => $this->mentor->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $module = ClassModule::factory()->create(['mentorship_class_id' => $class->id, 'status' => 'in_progress']);
        $this->session = ClassSession::factory()->create(['class_module_id' => $module->id]);
    }

    public function test_mentor_can_update_session_notes(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/sessions/{$this->session->id}", [
                 'actual_date' => '2026-05-10',
                 'notes'       => 'Good session.',
                 'location'    => 'Ward 3',
             ])
             ->assertOk()
             ->assertJsonPath('data.notes', 'Good session.');
    }

    public function test_non_mentor_cannot_update_session(): void
    {
        $other = User::factory()->create();
        $other->assignRole('facility_mentor');
        $otherToken = $other->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $otherToken)
             ->putJson("/api/v1/sessions/{$this->session->id}", ['notes' => 'Hack'])
             ->assertForbidden();
    }
}
