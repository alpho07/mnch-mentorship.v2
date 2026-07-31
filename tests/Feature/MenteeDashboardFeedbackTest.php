<?php

namespace Tests\Feature;

use App\Filament\Pages\MenteeDashboard;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MenteeDashboardFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_video_review_notes_appear_in_recommendations(): void
    {
        $mentee = User::factory()->create();
        Permission::firstOrCreate(['name' => 'page_MenteeDashboard', 'guard_name' => 'web']);
        $mentee->givePermissionTo('page_MenteeDashboard');

        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'PPH Management']);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
            'video_review_status' => 'failed',
            'video_review_notes' => 'Redo the uterine massage technique.',
            'video_reviewed_at' => now(),
        ]);

        $this->actingAs($mentee);

        $component = Livewire::test(MenteeDashboard::class);

        $recommendations = $component->get('recommendations');
        $this->assertCount(1, $recommendations);
        $this->assertSame('video_review', $recommendations[0]['type']);
        $this->assertSame('Redo the uterine massage technique.', $recommendations[0]['text']);

        $component->assertSee('Redo the uterine massage technique.');
    }
}
