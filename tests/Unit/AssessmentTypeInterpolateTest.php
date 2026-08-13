<?php

namespace Tests\Unit;

use App\Models\AssessmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTypeInterpolateTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_a_set_parameter(): void
    {
        $type = AssessmentType::create([
            'name' => 'Interp Test', 'code' => 'INTERP_TEST', 'is_active' => true,
            'template_parameters' => ['timeline' => 'Neonates 7-28 days'],
        ]);

        $this->assertSame('Select agreed timelines: Neonates 7-28 days', $type->interpolate('Select agreed timelines: {{timeline}}'));
    }

    public function test_leaves_an_unset_parameter_token_visible(): void
    {
        $type = AssessmentType::create(['name' => 'Interp Test 2', 'code' => 'INTERP_TEST_2', 'is_active' => true]);

        $this->assertSame('Value: {{missing}}', $type->interpolate('Value: {{missing}}'));
    }

    public function test_passes_through_null_and_plain_text_unchanged(): void
    {
        $type = AssessmentType::create(['name' => 'Interp Test 3', 'code' => 'INTERP_TEST_3', 'is_active' => true]);

        $this->assertNull($type->interpolate(null));
        $this->assertSame('Plain text', $type->interpolate('Plain text'));
    }
}
