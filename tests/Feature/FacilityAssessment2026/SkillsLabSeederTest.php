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

    public function test_seeds_23_questions(): void
    {
        $type = $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'skills_lab')->first();
        $this->assertSame(23, $section->questions()->count());
    }

    public function test_handwash_sink_question_mentions_clean_running_water(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_HANDWASH_SINK')->firstOrFail();
        $this->assertStringContainsString('clean running water', $q->question_text);
    }

    public function test_fire_exits_and_extinguishers_are_separate_questions(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $exits = AssessmentQuestion::where('question_code', 'SKILLS_YES_FIRE_EXITS')->firstOrFail();
        $extinguishers = AssessmentQuestion::where('question_code', 'SKILLS_YES_FIRE_EXTINGUISHERS')->firstOrFail();

        $this->assertStringContainsString('fire exits?', $exits->question_text);
        $this->assertStringNotContainsString('extinguishers', $exits->question_text);
        $this->assertStringContainsString('fire extinguishers?', $extinguishers->question_text);
        $this->assertSame(['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'], $extinguishers->display_conditions);
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

    public function test_monthly_reports_additionally_requires_biomed_maintenance(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_MONTHLY_REPORTS')->firstOrFail();
        $this->assertSame('and', $q->display_conditions['operator']);
        $this->assertSame(
            [
                ['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'],
                ['question_code' => 'SKILLS_YES_BIOMED_MAINTENANCE', 'operator' => 'equals', 'value' => 'Yes'],
            ],
            $q->display_conditions['conditions']
        );
    }

    public function test_questions_are_numbered_in_one_continuous_sequence(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $this->assertSame('1. Is there a functional skills lab?', AssessmentQuestion::where('question_code', 'SKILLS_HAS_LAB')->value('question_text'));
        $this->assertSame('21. Newborn Anne Manikin that can be intubated and has an umbilicus for UVC insertion?', AssessmentQuestion::where('question_code', 'SKILLS_YES_MANIKIN_ANNE')->value('question_text'));
        $this->assertSame('23. Is there a lockable storage area for the equipment to be used in skills teaching and simulation?', AssessmentQuestion::where('question_code', 'SKILLS_NO_LOCKABLE_STORAGE')->value('question_text'));
    }

    public function test_power_backup_is_a_yes_no_gate_with_a_reason_on_no(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_POWER_BACKUP')->firstOrFail();
        $this->assertSame('yes_no', $q->question_type);
        $this->assertSame('3. Is there a power back up system?', $q->question_text);
        $this->assertSame(['No'], $q->requires_explanation_on);
        $this->assertSame('Reason', $q->explanation_label);
    }

    public function test_power_backup_type_dropdown_only_shows_once_power_backup_is_yes(): void
    {
        $this->makeType();
        $this->seed(SkillsLabSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'SKILLS_YES_POWER_BACKUP_TYPE')->firstOrFail();
        $this->assertSame('multi_select', $q->question_type);
        $this->assertSame(['Generator', 'Solar', 'Other'], $q->options);
        $this->assertSame(
            ['question_code' => 'SKILLS_YES_POWER_BACKUP', 'operator' => 'equals', 'value' => 'Yes'],
            $q->display_conditions
        );
    }
}
