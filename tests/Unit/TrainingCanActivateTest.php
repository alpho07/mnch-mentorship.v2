<?php

namespace Tests\Unit;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCanActivateTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_activate_a_mentorship_with_no_classes(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);

        $this->assertFalse($training->canActivate());

        $this->expectException(\LogicException::class);
        $training->update(['status' => 'active']);
    }

    public function test_cannot_activate_a_mentorship_whose_class_is_still_draft(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'draft']);
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertFalse($training->canActivate());
    }

    public function test_cannot_activate_a_mentorship_whose_started_class_has_no_mentees(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);
        MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);

        $this->assertFalse($training->canActivate());
    }

    public function test_can_activate_a_mentorship_with_a_started_class_and_a_mentee(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertTrue($training->canActivate());

        $training->update(['status' => 'active']);
        $this->assertSame('active', $training->fresh()->status);
    }

    public function test_pilot_mentorships_are_exempt_from_the_guard(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'is_pilot' => true,
        ]);

        $this->assertTrue($training->canActivate());
        $training->update(['status' => 'active']);
        $this->assertSame('active', $training->fresh()->status);
    }

    public function test_saving_unrelated_fields_does_not_trigger_the_guard(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);

        $training->update(['notes' => 'updated notes']);

        $this->assertSame('draft', $training->fresh()->status);
    }
}
