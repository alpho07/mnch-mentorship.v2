<?php

namespace Tests\Unit\Services;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\CoordinatorExceptionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinatorExceptionResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrainingWithClass(?User $mentor = null): array
    {
        $mentor = $mentor ?? User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
            'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'Test Module']);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        return compact('mentor', 'program', 'facility', 'training', 'class', 'programModule', 'classModule');
    }

    private function loadForResolver(array $env): array
    {
        // Fresh fetches, not ->load() on the already-instantiated $env models — ->load() only
        // refreshes the named relation, not the base model's own attributes (e.g. a backdated
        // created_at written via a query-builder update() after $env was built would otherwise
        // never be seen here).
        return [
            collect([Training::with('facility', 'mentor')->find($env['training']->id)]),
            collect([MentorshipClass::with('classModules')->find($env['class']->id)]),
        ];
    }

    public function test_facility_below_completion_threshold_is_tier_one(): void
    {
        $env = $this->makeTrainingWithClass();
        // Second module, neither completed => 0% completion, well under 40%
        ClassModule::factory()->create([
            'mentorship_class_id' => $env['class']->id,
            'program_module_id' => $env['programModule']->id,
            'status' => 'in_progress',
        ]);
        [$trainings, $liveClasses] = $this->loadForResolver($env);

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), []);

        $tier1 = collect($result)->firstWhere('tier', 1);
        $this->assertNotNull($tier1);
    }

    public function test_facility_at_or_above_thresholds_is_not_tier_one(): void
    {
        $env = $this->makeTrainingWithClass();
        $env['classModule']->update(['status' => 'completed']);
        // Single module, completed => 100% completion; no participants => attendance check skipped
        [$trainings, $liveClasses] = $this->loadForResolver($env);

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), []);

        $this->assertNull(collect($result)->firstWhere('tier', 1));
    }

    public function test_mentor_with_no_activity_and_stale_class_is_tier_two(): void
    {
        $env = $this->makeTrainingWithClass();
        // Backdate the class so the "no activity at all" fallback baseline is 20 days ago, not "now".
        MentorshipClass::where('id', $env['class']->id)->update(['created_at' => now()->subDays(20)]);
        [$trainings, $liveClasses] = $this->loadForResolver($env);

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), []);

        $tier2 = collect($result)->firstWhere('tier', 2);
        $this->assertNotNull($tier2);
    }

    public function test_mentor_with_recent_attendance_mark_is_not_tier_two(): void
    {
        $env = $this->makeTrainingWithClass();
        MentorshipClass::where('id', $env['class']->id)->update(['created_at' => now()->subDays(20)]);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $env['class']->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        ClassAttendance::create([
            'class_id' => $env['class']->id,
            'class_module_id' => $env['classModule']->id,
            'user_id' => $mentee->id,
            'marked_by' => $env['mentor']->id,
            'marked_at' => now(),
            'source' => 'manual',
        ]);
        [$trainings, $liveClasses] = $this->loadForResolver($env);
        $participants = collect([$participant]);

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, $participants, []);

        $this->assertNull(collect($result)->firstWhere('tier', 2));
    }

    public function test_mentor_with_zero_live_classes_is_never_tier_two(): void
    {
        $env = $this->makeTrainingWithClass();
        MentorshipClass::where('id', $env['class']->id)->update(['created_at' => now()->subDays(60)]);
        $trainings = collect([$env['training']->load('facility', 'mentor')]);
        // Empty $liveClasses — this mentor has no active class in scope.
        $liveClasses = collect();

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), []);

        $this->assertSame([], $result);
    }

    public function test_zero_cpd_mentor_is_tier_three(): void
    {
        $env = $this->makeTrainingWithClass();
        [$trainings, $liveClasses] = $this->loadForResolver($env);
        $cpdData = [$env['mentor']->id => ['total' => 0]];

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), $cpdData);

        $tier3 = collect($result)->firstWhere('tier', 3);
        $this->assertNotNull($tier3);
    }

    public function test_mentor_with_cpd_is_not_tier_three(): void
    {
        $env = $this->makeTrainingWithClass();
        [$trainings, $liveClasses] = $this->loadForResolver($env);
        $cpdData = [$env['mentor']->id => ['total' => 5]];

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), $cpdData);

        $this->assertNull(collect($result)->firstWhere('tier', 3));
    }

    public function test_mentor_qualifying_for_tier_two_and_three_appears_once_at_tier_two(): void
    {
        $env = $this->makeTrainingWithClass();
        MentorshipClass::where('id', $env['class']->id)->update(['created_at' => now()->subDays(20)]);
        // Module completed so this facility's completion rate is 100% — isolates this test to the
        // Tier 2/Tier 3 mentor dedup, rather than also incidentally tripping Tier 1 (facility-level,
        // independent of mentor dedup) since the single module would otherwise sit at 0% complete.
        $env['classModule']->update(['status' => 'completed']);
        [$trainings, $liveClasses] = $this->loadForResolver($env);
        $cpdData = [$env['mentor']->id => ['total' => 0]];

        $result = (new CoordinatorExceptionResolver())->resolve($trainings, $liveClasses, collect(), $cpdData);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['tier']);
    }

    public function test_empty_trainings_returns_empty_array(): void
    {
        $result = (new CoordinatorExceptionResolver())->resolve(collect(), collect(), collect(), []);

        $this->assertSame([], $result);
    }
}
