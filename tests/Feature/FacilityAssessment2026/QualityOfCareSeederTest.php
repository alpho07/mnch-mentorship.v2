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
            'metadata' => ['parameters' => ['quality_of_care_timeline' => 'Neonates 7–28 days']],
        ]);
    }

    public function test_seeds_6_questions(): void
    {
        $type = $this->makeType();
        $this->seed(QualityOfCareSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'quality_of_care')->first();
        $this->assertSame(6, $section->questions()->count());
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
}
