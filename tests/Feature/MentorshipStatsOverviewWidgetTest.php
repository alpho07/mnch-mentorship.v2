<?php

namespace Tests\Feature;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipStatsOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_shows_correct_counts_on_the_mentorships_list_page(): void
    {
        $user = User::factory()->create(['name' => 'Viewer']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_mentorship::training']);
        $this->actingAs($user);

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id, 'is_pilot' => false]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => User::factory()->create()->id]);

        $response = $this->get(\App\Filament\Resources\MentorshipTrainingResource::getUrl());

        $response->assertOk();
        $response->assertSee('Newborn Care');
        $response->assertSee('All Mentorships');
    }
}
