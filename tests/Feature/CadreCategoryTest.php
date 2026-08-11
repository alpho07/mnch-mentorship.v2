<?php

namespace Tests\Feature;

use App\Models\Cadre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadreCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadres_can_be_scoped_by_category(): void
    {
        $emonc = Cadre::create(['name' => 'Test EmONC Cadre', 'code' => 'test_emonc_cadre', 'category' => 'emonc', 'is_active' => true]);
        $other = Cadre::create(['name' => 'Test Other Cadre', 'code' => 'test_other_cadre', 'category' => null, 'is_active' => true]);

        $emoncCadres = Cadre::category('emonc')->pluck('id')->all();

        $this->assertContains($emonc->id, $emoncCadres);
        $this->assertNotContains($other->id, $emoncCadres);
    }
}
