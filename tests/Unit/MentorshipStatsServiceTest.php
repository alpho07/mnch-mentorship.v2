<?php

namespace Tests\Unit;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorshipStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorshipStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_for_returns_overall_and_named_program_figures(): void
    {
        $mentor = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $mentor->assignRole('super_admin');

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'is_pilot' => false]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => User::factory()->create()->id]);

        $service = new MentorshipStatsService;
        $result = $service->countsFor($mentor, 'Newborn Care');

        $this->assertSame(1, $result['overall']['mentorships']);
        $this->assertSame(1, $result['overall']['mentees']);
        $this->assertNotNull($result['program']);
        $this->assertSame(1, $result['program']['mentorships']);
        $this->assertSame(1, $result['program']['mentees']);
    }

    public function test_a_facility_mentor_only_sees_their_own_mentorships(): void
    {
        $mentorA = User::factory()->create();
        $mentorB = User::factory()->create();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $mentorA->assignRole('facility_mentor');
        $mentorB->assignRole('facility_mentor');

        Training::factory()->facilityMentorship()->create(['mentor_id' => $mentorA->id, 'is_pilot' => false]);
        Training::factory()->facilityMentorship()->create(['mentor_id' => $mentorB->id, 'is_pilot' => false]);

        $service = new MentorshipStatsService;
        $result = $service->countsFor($mentorA);

        $this->assertSame(1, $result['overall']['mentorships']);
    }
}
