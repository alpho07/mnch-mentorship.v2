<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\QualityOfCareSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityOfCareSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        return AssessmentType::create([
            'name' => 'QoC Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true,
            'template_parameters' => ['quality_of_care_timeline' => 'Neonates 7–28 days'],
        ]);
    }

    public function test_seeds_7_questions(): void
    {
        $type = $this->makeType();
        $this->seed(QualityOfCareSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'quality_of_care')->first();
        $this->assertSame(7, $section->questions()->count());
        $this->assertSame('Select agreed timelines: {{quality_of_care_timeline}}', $section->description);
        $this->assertSame('Select agreed timelines: Neonates 7–28 days', $type->interpolate($section->description));
    }

    public function test_moh527_is_indented_under_neonatal_audits(): void
    {
        $this->makeType();
        $this->seed(QualityOfCareSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'QOC_NEONATAL_MOH527')->firstOrFail();
        $this->assertSame(1, $q->indent_level);
        $this->assertNull($q->group);
    }

    /**
     * The neonatal-audit follow-ups (MoH 527, KHIS upload, action points
     * implemented) only make sense once an audit is actually confirmed —
     * hidden entirely when QOC_NEONATAL_AUDITS is "No", not just informed
     * by the answer.
     */
    public function test_neonatal_audit_follow_ups_are_gated_on_the_audits_question_being_yes(): void
    {
        $this->makeType();
        $this->seed(QualityOfCareSeeder::class);

        $expected = ['question_code' => 'QOC_NEONATAL_AUDITS', 'operator' => 'equals', 'value' => 'Yes'];
        foreach (['QOC_NEONATAL_MOH527', 'QOC_NEONATAL_KHIS_UPLOAD', 'QOC_NEONATAL_ACTION_POINTS'] as $code) {
            $q = AssessmentQuestion::where('question_code', $code)->firstOrFail();
            $this->assertSame($expected, $q->display_conditions, "{$code} should be gated on QOC_NEONATAL_AUDITS = Yes");
        }
    }

    public function test_reasons_question_only_shows_when_action_points_were_not_implemented(): void
    {
        $this->makeType();
        $this->seed(QualityOfCareSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'QOC_NEONATAL_ACTION_REASONS')->firstOrFail();
        $this->assertSame('text', $q->question_type);
        $this->assertFalse($q->is_scored);
        $this->assertSame(
            ['question_code' => 'QOC_NEONATAL_ACTION_POINTS', 'operator' => 'equals', 'value' => 'No'],
            $q->display_conditions
        );
    }

    public function test_child_death_register_follow_up_is_gated_on_child_audits_being_yes(): void
    {
        $this->makeType();
        $this->seed(QualityOfCareSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'QOC_CHILD_REGISTER')->firstOrFail();
        $this->assertSame(
            ['question_code' => 'QOC_CHILD_AUDITS', 'operator' => 'equals', 'value' => 'Yes'],
            $q->display_conditions
        );
    }
}
