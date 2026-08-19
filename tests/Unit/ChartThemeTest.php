<?php

namespace Tests\Unit;

use App\Support\ChartTheme;
use Tests\TestCase;

class ChartThemeTest extends TestCase
{
    public function test_base_returns_shared_chart_options(): void
    {
        $options = ChartTheme::base();

        $this->assertTrue($options['responsive']);
        $this->assertFalse($options['maintainAspectRatio']);
        $this->assertSame(6, $options['elements']['bar']['borderRadius']);
    }

    public function test_merge_overlays_caller_options_onto_the_base(): void
    {
        $merged = ChartTheme::merge([
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ]);

        // Caller's leaf value wins.
        $this->assertFalse($merged['plugins']['legend']['display']);

        // Base's unrelated keys under the same top-level array survive the merge.
        $this->assertTrue($merged['responsive']);
        $this->assertSame(6, $merged['elements']['bar']['borderRadius']);
    }

    public function test_merge_deep_merges_nested_scales_without_losing_base_grid_styling(): void
    {
        $merged = ChartTheme::merge([
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ]);

        $this->assertTrue($merged['scales']['y']['beginAtZero']);
        $this->assertSame('rgba(15, 23, 42, 0.06)', $merged['scales']['x']['grid']['color']);
    }
}
