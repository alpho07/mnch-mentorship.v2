<?php

namespace Tests\Unit\Services;

use App\Models\ClassModule;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\CpdPointsService;
use App\Services\MentorAnalyticsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorAnalyticsDashboardServiceCpdTest extends TestCase
{
    use RefreshDatabase;

    public function test_cpd_total_matches_cpd_points_service_even_when_the_class_is_not_completed(): void
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
        // Class is 'active', not 'completed' — CPD points are now awarded per
        // module the moment it's completed, independent of the class's
        // overall status.
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'completed',
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $dashboardCpd = collect($result['matrix'])->firstWhere('mentor_id', $mentor->id)['cpd_total'] ?? null;

        $realCpd = app(CpdPointsService::class)->forMentor($mentor)['total'];

        $this->assertSame($realCpd, $dashboardCpd);
        $this->assertSame(1, $dashboardCpd, 'Module is completed, so it earns 1 CPD point even though the class itself is still active.');
    }
}
