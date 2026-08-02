<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentSectionScore;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentExecutiveDataQualityTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Test Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->assignRole('assessor');
        $user->givePermissionTo('view_any_assessment');
        $this->actingAs($user);

        return $user;
    }

    /**
     * Builds an assessment with:
     *  - Infrastructure: 3/3 questions answered (100% complete).
     *  - Skills Lab: 3/3 questions answered, ALL "No" — a straight-lining
     *    pattern — and a low section score (0%), which should also trip
     *    the Infrastructure ↔ Skills Lab cross-section insight since
     *    infra is complete/strong but skills lab is weak.
     */
    private function buildScenario(User $assessor): Assessment
    {
        $type = AssessmentType::create([
            'name' => 'Data Quality Test Template',
            'code' => 'DATA_QUALITY_TEST',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'status' => 'completed',
            'assessor_id' => $assessor->id,
            'assessor_name' => $assessor->name,
        ]);

        $infraSection = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Infrastructure',
            'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);

        foreach (range(1, 3) as $i) {
            $question = AssessmentQuestion::create([
                'assessment_section_id' => $infraSection->id,
                'question_code' => "INFRA_{$i}",
                'question_text' => "Infra question {$i}?",
                'question_type' => 'yes_no',
                'is_scored' => true,
                'scoring_map' => ['Yes' => 1, 'No' => 0],
                'order' => $i,
                'is_active' => true,
            ]);

            AssessmentQuestionResponse::create([
                'assessment_id' => $assessment->id,
                'assessment_question_id' => $question->id,
                'response_value' => 'Yes',
            ]);
        }

        AssessmentSectionScore::create([
            'assessment_id' => $assessment->id,
            'assessment_section_id' => $infraSection->id,
            'total_score' => 3,
            'max_score' => 3,
            'percentage' => 100,
            'grade' => 'green',
            'total_questions' => 3,
            'answered_questions' => 3,
            'skipped_questions' => 0,
        ]);

        $skillsSection = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Skills Lab',
            'code' => 'skills_lab',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 2,
            'is_active' => true,
        ]);

        foreach (range(1, 3) as $i) {
            $question = AssessmentQuestion::create([
                'assessment_section_id' => $skillsSection->id,
                'question_code' => "SKILLS_{$i}",
                'question_text' => "Skills question {$i}?",
                'question_type' => 'yes_no',
                'is_scored' => true,
                'scoring_map' => ['Yes' => 1, 'No' => 0],
                'order' => $i,
                'is_active' => true,
            ]);

            AssessmentQuestionResponse::create([
                'assessment_id' => $assessment->id,
                'assessment_question_id' => $question->id,
                'response_value' => 'No',
            ]);
        }

        AssessmentSectionScore::create([
            'assessment_id' => $assessment->id,
            'assessment_section_id' => $skillsSection->id,
            'total_score' => 0,
            'max_score' => 3,
            'percentage' => 0,
            'grade' => 'red',
            'total_questions' => 3,
            'answered_questions' => 3,
            'skipped_questions' => 0,
        ]);

        return $assessment;
    }

    public function test_executive_dashboard_shows_overall_and_per_section_completeness(): void
    {
        $assessor = $this->actingAsAssessor();
        $assessment = $this->buildScenario($assessor);

        $response = $this->get(route('assessment.executive', $assessment));

        $response->assertOk();
        $response->assertSee('Data Quality');
        $response->assertSee('100% complete');
        $response->assertSee('Response Completeness by Section');
    }

    public function test_executive_dashboard_flags_straight_lined_sections(): void
    {
        $assessor = $this->actingAsAssessor();
        $assessment = $this->buildScenario($assessor);

        $response = $this->get(route('assessment.executive', $assessment));

        $response->assertOk();
        $response->assertSee('Response Pattern Flags');
        $response->assertSee('Skills Lab');
        $response->assertSee('all 3 answered items marked', false);
    }

    public function test_executive_dashboard_surfaces_infrastructure_vs_skills_lab_relationship(): void
    {
        $assessor = $this->actingAsAssessor();
        $assessment = $this->buildScenario($assessor);

        $response = $this->get(route('assessment.executive', $assessment));

        $response->assertOk();
        $response->assertSee('Infrastructure ↔ Skills Lab');
    }

    public function test_completeness_ratio_shows_a_percentage_alongside_the_count(): void
    {
        $assessor = $this->actingAsAssessor();
        $assessment = $this->buildScenario($assessor);

        $response = $this->get(route('assessment.executive', $assessment));

        $response->assertOk();
        // Infrastructure: 3/3 answered.
        $response->assertSee('3/3 (100%)', false);
    }

    /**
     * 1111 is a known junk sentinel from bad data entry, not a real
     * question count — a section score row carrying it should render as
     * "N/A" rather than a misleading ratio, and should not skew the
     * overall completeness percentage.
     */
    public function test_a_junk_1111_count_is_displayed_as_na_and_excluded_from_the_overall_percentage(): void
    {
        $assessor = $this->actingAsAssessor();
        $assessment = $this->buildScenario($assessor);

        AssessmentSectionScore::where('assessment_id', $assessment->id)
            ->whereHas('section', fn ($q) => $q->where('code', 'infrastructure'))
            ->update(['total_questions' => 1111]);

        $response = $this->get(route('assessment.executive', $assessment));

        $response->assertOk();
        $response->assertSee('N/A');
        $response->assertDontSee('1111');
        // Only Skills Lab (0/3) remains in the overall figure once the
        // junk Infrastructure row is excluded — not skewed by "1111".
        $response->assertSee('0% complete');
    }
}
