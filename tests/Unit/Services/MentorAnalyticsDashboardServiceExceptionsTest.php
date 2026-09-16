<?php

namespace Tests\Unit\Services;

use App\Models\ClassModule;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorAnalyticsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorAnalyticsDashboardServiceExceptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_result_includes_exceptions_key_alongside_existing_keys(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $viewer = User::factory()->create();
        $viewer->assignRole('super_admin');

        $mentor = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);

        $this->assertArrayHasKey('kpis', $result);
        $this->assertArrayHasKey('matrix', $result);
        $this->assertArrayHasKey('chartData', $result);
        $this->assertArrayHasKey('insights', $result);
        $this->assertArrayHasKey('exceptions', $result);
        $this->assertIsArray($result['exceptions']);
    }
}
