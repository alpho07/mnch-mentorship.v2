<?php

namespace Tests\Unit\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\RubricAssessment;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorAnalyticsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorAnalyticsDashboardServiceGapKpisTest extends TestCase
{
    use RefreshDatabase;

    private function makeScopedClass(): array
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
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        return compact('viewer', 'mentor', 'class', 'classModule', 'programModule');
    }

    public function test_mentor_to_mentee_ratio_divides_the_two_existing_kpis(): void
    {
        ['viewer' => $viewer, 'class' => $class] = $this->makeScopedClass();

        $menteeA = User::factory()->create();
        $menteeB = User::factory()->create();
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => $menteeA->id, 'status' => 'active']);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => $menteeB->id, 'status' => 'active']);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);

        $this->assertSame(2.0, $result['kpis']['mentor_to_mentee_ratio']);
    }

    public function test_assessment_score_stats_are_null_with_no_data_and_computed_with_data(): void
    {
        ['viewer' => $viewer, 'class' => $class] = $this->makeScopedClass();

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertNull($result['kpis']['avg_assessment_score']);
        $this->assertNull($result['kpis']['assessment_pass_rate']);

        // Two separate mentees, not two rows for the same participant+module —
        // mentee_module_progress has a unique (class_participant_id, class_module_id).
        $menteeA = User::factory()->create();
        $participantA = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $menteeA->id, 'status' => 'active',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participantA->id,
            'class_module_id' => $class->classModules()->first()->id,
            'assessment_score' => 80,
            'assessment_status' => 'passed',
        ]);

        $menteeB = User::factory()->create();
        $participantB = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $menteeB->id, 'status' => 'active',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participantB->id,
            'class_module_id' => $class->classModules()->first()->id,
            'assessment_score' => 40,
            'assessment_status' => 'failed',
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertSame(60.0, $result['kpis']['avg_assessment_score']);
        $this->assertSame(50.0, $result['kpis']['assessment_pass_rate']);
    }

    public function test_rubric_score_stats_are_null_with_no_data_and_computed_with_data(): void
    {
        ['viewer' => $viewer, 'class' => $class, 'mentor' => $mentor, 'classModule' => $classModule, 'programModule' => $programModule] = $this->makeScopedClass();

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertNull($result['kpis']['avg_rubric_score']);
        $this->assertNull($result['kpis']['rubric_pass_rate']);

        $mentee = User::factory()->create();
        $rubric = ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Test Rubric',
            'total_marks' => 100,
            'pass_marks' => 70,
        ]);
        RubricAssessment::create([
            'module_rubric_id' => $rubric->id,
            'class_module_id' => $classModule->id,
            'mentee_id' => $mentee->id,
            'mentor_id' => $mentor->id,
            'score' => 90,
            'passed' => true,
            'assessed_at' => now(),
        ]);
        RubricAssessment::create([
            'module_rubric_id' => $rubric->id,
            'class_module_id' => $classModule->id,
            'mentee_id' => $mentee->id,
            'mentor_id' => $mentor->id,
            'score' => 50,
            'passed' => false,
            'assessed_at' => now(),
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);
        $this->assertSame(70.0, $result['kpis']['avg_rubric_score']);
        $this->assertSame(50.0, $result['kpis']['rubric_pass_rate']);
    }

    public function test_dropped_mentees_are_counted_separately_from_the_active_participant_scope(): void
    {
        ['viewer' => $viewer, 'class' => $class] = $this->makeScopedClass();

        $mentee = User::factory()->create();
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $mentee->id, 'status' => 'dropped',
        ]);

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);

        $this->assertSame(1, $result['kpis']['dropped_mentees']);
        $this->assertSame(0, $result['kpis']['total_mentees'], 'Dropped participants are excluded from total_mentees, per the existing $participants scope.');
    }

    public function test_inactive_mentors_kpi_matches_the_tier_2_exception_count(): void
    {
        ['viewer' => $viewer] = $this->makeScopedClass();

        $result = app(MentorAnalyticsDashboardService::class)->build($viewer);

        $expectedTier2Count = collect($result['exceptions'])->where('tier', 2)->count();
        $this->assertSame($expectedTier2Count, $result['kpis']['inactive_mentors']);
    }
}
