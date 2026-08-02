<?php

namespace Tests\Unit;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramModuleQuizTimeLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_time_limit_minutes_is_fillable_and_nullable_by_default(): void
    {
        $program = Program::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);

        $quiz = ProgramModuleQuiz::create([
            'program_module_id' => $module->id,
            'type' => 'pre_test',
            'title' => 'Pre-Test',
            'pass_mark_percentage' => 80,
            'order_sequence' => 1,
            'is_active' => true,
        ]);

        $this->assertNull($quiz->fresh()->time_limit_minutes);
    }

    public function test_time_limit_minutes_can_be_set(): void
    {
        $program = Program::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);

        $quiz = ProgramModuleQuiz::create([
            'program_module_id' => $module->id,
            'type' => 'pre_test',
            'title' => 'Pre-Test',
            'pass_mark_percentage' => 80,
            'order_sequence' => 1,
            'is_active' => true,
            'time_limit_minutes' => 15,
        ]);

        $this->assertSame(15, $quiz->fresh()->time_limit_minutes);
    }
}
