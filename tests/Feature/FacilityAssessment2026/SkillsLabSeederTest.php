<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\ChecklistsSeeder;
use Database\Seeders\FacilityAssessment2026\SkillsLabSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillsLabSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        $type = AssessmentType::create(['name' => 'Skills Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
        $this->seed(ChecklistsSeeder::class);

        return $type;
    }

    public function test_seeds_21_questions(): void
    {
        $type = $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'skills_lab')->first();
        $this->assertSame(21, $section->questions()->count());
    }

    public function test_yes_branch_questions_are_gated_on_skills_has_lab(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_POWER_OUTLETS')->firstOrFail();
        $this->assertSame(['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'], $q->display_conditions);
    }

    public function test_manikin_anne_additionally_requires_nicu(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_MANIKIN_ANNE')->firstOrFail();
        $this->assertSame('and', $q->display_conditions['operator']);
        $this->assertCount(2, $q->display_conditions['conditions']);
    }

    public function test_no_branch_questions_are_gated_on_skills_has_lab_no(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_NO_ROOM_SPACE')->firstOrFail();
        $this->assertSame(['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'No'], $q->display_conditions);
    }

    public function test_lockable_store_question_links_the_skills_lab_checklist(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_LOCKABLE_STORE')->firstOrFail();
        $this->assertSame('Skills Lab Checklist Requirements', $q->checklist->title);
    }
}
