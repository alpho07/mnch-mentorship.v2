<?php

namespace Tests\Unit;

use App\Models\Assessment;
use Tests\TestCase;

class AssessmentRoundDisplayTest extends TestCase
{
    public function test_round_display_returns_ucfirst_label_for_standard_rounds(): void
    {
        $assessment = new Assessment(['round' => 'midline']);

        $this->assertSame('Midline', $assessment->round_display);
    }

    public function test_round_display_returns_round_label_for_other(): void
    {
        $assessment = new Assessment([
            'round' => 'other',
            'round_label' => 'Post-COVID Re-assessment',
        ]);

        $this->assertSame('Post-COVID Re-assessment', $assessment->round_display);
    }

    public function test_round_sort_weight_orders_baseline_before_midline_before_endline_before_other(): void
    {
        $baseline = new Assessment(['round' => 'baseline']);
        $midline = new Assessment(['round' => 'midline']);
        $endline = new Assessment(['round' => 'endline']);
        $other = new Assessment(['round' => 'other']);

        $this->assertSame(0, $baseline->roundSortWeight());
        $this->assertSame(1, $midline->roundSortWeight());
        $this->assertSame(2, $endline->roundSortWeight());
        $this->assertSame(3, $other->roundSortWeight());
    }
}
