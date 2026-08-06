<?php

namespace Tests\Feature;

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

class MentorAnalyticsDashboardRenderSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Renders the mentor-mode view directly with real service data, bypassing
     * AnalyticsDashboardController@index — that controller computes shared
     * filter data (available years) via YEAR(), a pre-existing MySQL-only SQL
     * gap unrelated to this change (see docs/PHASE1-DISCOVERY-BASELINE.md
     * §9.12), which blocks every mode under the SQLite test suite, not just
     * mentor mode. This test isolates the Blade template itself.
     */
    public function test_mentor_mode_view_renders_with_the_new_kpi_tiles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $viewer->assignRole('super_admin');
        $this->actingAs($viewer);

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

        $data = app(MentorAnalyticsDashboardService::class)->build($viewer);

        $html = view('analytics.dashboard.index', [
            'mode' => 'mentor',
            'selectedYear' => null,
            'availableYears' => [],
            'mentorKpis' => $data['kpis'],
            'mentorMatrix' => $data['matrix'],
            'mentorCharts' => $data['chartData'],
            'mentorInsights' => $data['insights'],
            'mentorExceptions' => $data['exceptions'],
            'mentorFilters' => [],
            'mentorPrograms' => collect(),
            'mentorCounties' => collect(),
            'mentorSubcounties' => collect(),
            'mentorFacilities' => collect(),
            'mentorCadres' => collect(),
            'mentorDepartments' => collect(),
            'mentorUsers' => collect(),
        ])->render();

        $this->assertStringContainsString('Mentees per Mentor', $html);
        $this->assertStringContainsString('Avg Assessment Score', $html);
        $this->assertStringContainsString('No data yet', $html);
        $this->assertStringContainsString('Avg Practical Skills Score', $html);
        $this->assertStringContainsString('Inactive Mentors', $html);
        $this->assertStringContainsString('Dropped Mentees', $html);
    }
}
