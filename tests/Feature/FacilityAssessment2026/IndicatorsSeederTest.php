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
        $this->assertSame(31, $section->questions()->count());
    }

    public function test_all_questions_are_unscored_and_number_type_except_the_group_headings(): void
    {
        $this->makeType();
        $this->seed(IndicatorsSeeder::class);

        $questions = AssessmentQuestion::whereHas('section', fn ($q) => $q->where('code', 'newborn_paediatric_indicators'))->get();
        $this->assertTrue($questions->every(fn ($q) => $q->is_scored === false));

        $headings = $questions->whereIn('question_code', ['IND_NEWBORN_HEADING', 'IND_PAED_HEADING']);
        $this->assertCount(2, $headings);
        $this->assertTrue($headings->every(fn ($q) => $q->question_type === 'heading'));

        $rest = $questions->reject(fn ($q) => in_array($q->question_code, ['IND_NEWBORN_HEADING', 'IND_PAED_HEADING'], true));
        $this->assertTrue($rest->every(fn ($q) => $q->question_type === 'number'));
    }

    public function test_each_group_starts_with_its_descriptive_heading(): void
    {
        $this->makeType();
        $this->seed(IndicatorsSeeder::class);

        $newbornFirst = AssessmentQuestion::where('group', 'Newborn Indicators')->orderBy('order')->first();
        $this->assertSame('IND_NEWBORN_HEADING', $newbornFirst->question_code);
        $this->assertSame(
            'Newborn Inpatient Files (sample 30 newborn inpatient files) (Newborns admitted in the newborn unit)',
            $newbornFirst->question_text
        );

        $paedFirst = AssessmentQuestion::where('group', 'Paediatric Indicators')->orderBy('order')->first();
        $this->assertSame('IND_PAED_HEADING', $paedFirst->question_code);
        $this->assertSame(
            'Paediatric Inpatient Files (sample 30 paediatric inpatient files) (Children admitted in the paediatric ward)',
            $paedFirst->question_text
        );
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

    public function test_newborn_and_paediatric_questions_are_split_into_separate_groups(): void
    {
        $this->makeType();
        $this->seed(IndicatorsSeeder::class);

        $newbornCount = AssessmentQuestion::where('group', 'Newborn Indicators')->count();
        $paedCount = AssessmentQuestion::where('group', 'Paediatric Indicators')->count();

        $this->assertSame(15, $newbornCount);
        $this->assertSame(16, $paedCount);

        $admissions = AssessmentQuestion::where('question_code', 'IND_NEWBORN_ADMISSIONS')->firstOrFail();
        $this->assertSame('Newborn Indicators', $admissions->group);

        $paedAdmissions = AssessmentQuestion::where('question_code', 'IND_PAED_ADMISSIONS')->firstOrFail();
        $this->assertSame('Paediatric Indicators', $paedAdmissions->group);
    }
}
