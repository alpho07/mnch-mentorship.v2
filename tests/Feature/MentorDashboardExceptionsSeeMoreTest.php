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

class MentorDashboardExceptionsSeeMoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Renders the mentor-mode view directly with real service data, bypassing
     * AnalyticsDashboardController@index (pre-existing MySQL-only YEAR() gap
     * unrelated to this change — see docs/PHASE1-DISCOVERY-BASELINE.md §9.12),
     * matching the pattern already established in
     * MentorAnalyticsDashboardRenderSmokeTest.
     */
    private function renderMentorMode(array $data): string
    {
        return view('analytics.dashboard.index', [
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
    }

    public function test_more_than_5_exceptions_shows_only_5_inline_plus_a_see_more_button(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $viewer->assignRole('super_admin');
        $this->actingAs($viewer);

        $program = Program::factory()->create(['name' => 'Newborn Care']);

        // 6 mentors, each leading one class with one incomplete module in its
        // own facility. Each unit trips BOTH tier 1 (facility: 0% completion,
        // below the 40% threshold) and tier 3 (mentor: 0 CPD) — 12 exceptions
        // total, more than enough to exercise the >5 "See more" path.
        for ($i = 0; $i < 6; $i++) {
            $mentor = User::factory()->create();
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
        }

        $data = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertCount(12, $data['exceptions'], 'Fixture sanity check: 6 units x (1 facility + 1 mentor) exception each.');

        $html = $this->renderMentorMode($data);

        $this->assertStringContainsString('See all 12 exceptions', $html);
        $this->assertStringContainsString('id="exceptionsModal"', $html);

        // Tier 1 (facility) items sort before tier 3 (mentor) items, and all
        // 6 facility items share the same sort_ts, so PHP 8's stable sort
        // preserves insertion order — the 6th item (index 5) is the last
        // facility exception, guaranteed to fall outside array_slice(0, 5)
        // and reachable only via the modal's full table.
        $sixthItemHeadline = $data['exceptions'][5]['headline'];
        $this->assertStringContainsString(e($sixthItemHeadline), $html);
    }

    public function test_5_or_fewer_exceptions_does_not_show_the_see_more_button(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $viewer->assignRole('super_admin');
        $this->actingAs($viewer);

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $mentor = User::factory()->create();
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
        // 1 facility (0% completion) + 1 mentor (0 CPD) exception — both under the 5-item cap.
        $this->assertCount(2, $data['exceptions']);

        $html = $this->renderMentorMode($data);

        $this->assertStringNotContainsString('See all', $html);
        $this->assertStringContainsString('0 CPD points', $html);
    }
}
