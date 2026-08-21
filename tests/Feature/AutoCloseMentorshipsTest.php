<?php

namespace Tests\Feature;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCloseMentorshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_closes_a_qualifying_mentorship_whose_end_date_passed(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $training->update(['status' => 'active']);
        $training->update(['end_date' => now()->subDay()]);

        $this->artisan('mentorships:auto-close')->assertSuccessful();

        $this->assertSame('completed', $training->fresh()->status);
    }

    public function test_does_not_auto_close_a_mentorship_that_never_started(): void
    {
        // Simulates pre-existing data that predates the Training::canActivate()
        // guard — withoutEvents bypasses it, same as a legacy DB row would.
        $training = Training::withoutEvents(fn () => Training::factory()->facilityMentorship()->create([
            'status' => 'active',
            'end_date' => now()->subDay(),
        ]));

        $this->artisan('mentorships:auto-close')->assertSuccessful();

        $this->assertSame('active', $training->fresh()->status);
    }

    public function test_does_not_auto_close_a_mentorship_whose_class_never_left_draft(): void
    {
        $training = Training::withoutEvents(fn () => Training::factory()->facilityMentorship()->create([
            'status' => 'active',
            'end_date' => now()->subDay(),
        ]));
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'draft']);
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->artisan('mentorships:auto-close')->assertSuccessful();

        $this->assertSame('active', $training->fresh()->status);
    }

    public function test_auto_closes_a_pilot_mentorship_regardless_of_class_state(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'is_pilot' => true,
        ]);
        $training->update(['status' => 'active']);
        $training->update(['end_date' => now()->subDay()]);

        $this->artisan('mentorships:auto-close')->assertSuccessful();

        $this->assertSame('completed', $training->fresh()->status);
    }
}
