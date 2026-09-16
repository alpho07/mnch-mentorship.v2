<?php

namespace Tests\Unit;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use App\Services\Chat\Tools\MentorshipStatsToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorshipStatsToolProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_mentorship_counts_returns_overall_and_program_figures(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'is_pilot' => false, 'mentor_id' => $user->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => User::factory()->create()->id]);

        $tool = MentorshipStatsToolProvider::countsTool();

        $this->assertTrue($tool->authorize($user));

        $result = $tool->execute(['program_name' => 'Newborn Care'], $user);

        $this->assertSame(1, $result['overall']['mentorships']);
        $this->assertNotNull($result['program']);
    }

    public function test_get_mentorship_trends_returns_a_period_series(): void
    {
        $user = User::factory()->create();

        $tool = MentorshipStatsToolProvider::trendsTool();

        $result = $tool->execute(['period' => 'monthly', 'periods_back' => 3], $user);

        $this->assertCount(3, $result['trends']);
        $this->assertArrayHasKey('period', $result['trends'][0]);
        $this->assertArrayHasKey('mentorships', $result['trends'][0]);
        $this->assertArrayHasKey('mentees', $result['trends'][0]);
    }
}
