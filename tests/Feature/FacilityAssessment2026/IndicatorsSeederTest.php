<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\IndicatorsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicatorsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        return AssessmentType::create(['name' => 'Ind Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
    }

    public function test_creates_the_section_unscored(): void
    {
        $type = $this->makeType();
        $this->seed(IndicatorsSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'newborn_paediatric_indicators')->firstOrFail();
        $this->assertFalse($section->is_scored);
        $this->assertSame(29, $section->questions()->count());
    }

    public function test_all_questions_are_number_type_and_unscored(): void
    {
        $this->makeType();
        $this->seed(IndicatorsSeeder::class);

        $questions = AssessmentQuestion::whereHas('section', fn ($q) => $q->where('code', 'newborn_paediatric_indicators'))->get();
        $this->assertTrue($questions->every(fn ($q) => $q->question_type === 'number' && $q->is_scored === false));
    }

    public function test_o2sat_and_headtotoe_are_gated_on_emr_access(): void
    {
        $this->makeType();
        $this->seed(IndicatorsSeeder::class);

        foreach (['IND_NEWBORN_O2SAT_TAKEN', 'IND_NEWBORN_HEADTOTOE'] as $code) {
            $q = AssessmentQuestion::where('question_code', $code)->firstOrFail();
            $this->assertSame(['question_code' => 'INFOSYS_EMR_ACCESS', 'operator' => 'equals', 'value' => 'Yes'], $q->display_conditions);
        }
    }
}
