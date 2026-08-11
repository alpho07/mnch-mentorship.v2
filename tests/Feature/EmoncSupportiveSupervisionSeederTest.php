<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use Database\Seeders\EmoncSupportiveSupervisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmoncSupportiveSupervisionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_categories_and_the_assessment_type(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $this->assertTrue(AssessmentTypeCategory::where('name', 'EmONC')->exists());
        $this->assertTrue(AssessmentTypeCategory::where('name', 'Newborn, Infant & Child')->exists());

        $type = AssessmentType::where('code', 'EMONC_SUPPORTIVE_SUPERVISION')->first();
        $this->assertNotNull($type);
        $this->assertSame('EmONC', $type->category->name);
        $this->assertTrue($type->is_active);
    }

    public function test_section_a_is_seeded_with_29_unscored_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $type = AssessmentType::where('code', 'EMONC_SUPPORTIVE_SUPERVISION')->first();
        $section = $type->sections()->where('code', 'emonc_facility_context')->first();

        $this->assertNotNull($section);
        $this->assertFalse($section->is_scored);
        $this->assertSame(29, $section->questions()->count());
        $this->assertSame(0, $section->questions()->where('is_scored', true)->count());

        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_A_FACILITY_CATEGORY')->exists());
        $facilityCategory = AssessmentQuestion::where('question_code', 'EMONC_A_FACILITY_CATEGORY')->first();
        $this->assertSame(['CEMONC', 'BEMONC'], $facilityCategory->options);
    }

    public function test_section_b_is_seeded_with_10_questions_one_scored(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_feedback')->first();

        $this->assertNotNull($section);
        $this->assertTrue($section->is_scored);
        $this->assertSame(10, $section->questions()->count());
        $this->assertSame(1, $section->questions()->where('is_scored', true)->count());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_B_FEEDBACK_MEETING_DONE')->where('is_scored', true)->exists());
    }

    public function test_section_c_is_seeded_with_2_scored_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_capacity_building')->first();

        $this->assertNotNull($section);
        $this->assertSame(2, $section->questions()->count());
        $this->assertSame(2, $section->questions()->where('is_scored', true)->count());
        $cmes = AssessmentQuestion::where('question_code', 'EMONC_C_CMES')->first();
        $this->assertSame('Confirm using the CME register/booklet', $cmes->help_text);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);
        $countBefore = AssessmentQuestion::where('question_code', 'like', 'EMONC_%')->count();

        $this->seed(EmoncSupportiveSupervisionSeeder::class);
        $countAfter = AssessmentQuestion::where('question_code', 'like', 'EMONC_%')->count();

        $this->assertSame($countBefore, $countAfter);
    }
}
