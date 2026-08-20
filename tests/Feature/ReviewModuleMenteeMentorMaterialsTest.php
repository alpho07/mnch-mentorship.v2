<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ReviewModuleMentee;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ReviewModuleMenteeMentorMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_exposes_all_mentor_materials_rows_not_just_the_first(): void
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);

        ProgramModuleContent::create([
            'program_module_id' => $programModule->id,
            'type' => 'mentor_materials',
            'title' => 'Section 1 — Admission',
            'content' => 'First section content.',
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        ProgramModuleContent::create([
            'program_module_id' => $programModule->id,
            'type' => 'mentor_materials',
            'title' => 'Section 2 — Supportive Care',
            'content' => 'Second section content.',
            'order_sequence' => 2,
            'is_active' => true,
        ]);

        $mentor = User::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);

        Auth::login($mentor);
        $page = new ReviewModuleMentee;
        $page->mount($training, $class, $classModule, $participant);

        $getViewData = new \ReflectionMethod($page, 'getViewData');
        $getViewData->setAccessible(true);
        $viewData = $getViewData->invoke($page);
        $mentorMaterials = $viewData['mentorMaterials'];

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $mentorMaterials);
        $this->assertCount(2, $mentorMaterials);
        $this->assertSame('Section 1 — Admission', $mentorMaterials->first()->title);
        $this->assertSame('Section 2 — Supportive Care', $mentorMaterials->last()->title);
    }
}
