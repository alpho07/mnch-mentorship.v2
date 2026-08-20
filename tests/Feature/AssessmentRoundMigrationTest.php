<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentRoundMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_an_other_round_with_a_free_text_round_label(): void
    {
        $type = AssessmentType::create([
            'name' => 'Test Template',
            'code' => 'TEST_TEMPLATE',
            'version' => '1.0',
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'round' => 'other',
            'round_label' => 'Post-COVID Re-assessment',
            'assessment_date' => now(),
            'assessor_name' => 'Test Assessor',
        ]);

        $this->assertSame('other', $assessment->fresh()->round);
        $this->assertSame('Post-COVID Re-assessment', $assessment->fresh()->round_label);
    }
}
