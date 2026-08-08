<?php

namespace Tests\Unit;

use App\Support\RagSourceFormatter;
use Tests\TestCase;

class RagSourceFormatterTest extends TestCase
{
    public function test_dense_slide_excerpt_is_split_into_readable_sections(): void
    {
        $excerpt = 'Role of Filtered Sunlight Phototherapy Filtered sunlight Filtered sunlight is noninferior to conventional phototherapy for the treatment of neonatal hyperbilirubinemia 2 Do not recommend the use unfiltered sunlight Risks- UV radiation, hyperthermia and sun burn 1. Role of filtered sunlight – where Film canopies are used to Filter out most Ultraviolet A,B and C and infrared (heat) radiation. After filtering allows passage of therapeutic blue light 400-520 nm Filtered sunlight provides above the threshold of intensive phototherapy(at least 30 uW/cm 2/nm) . A Randomized Trial of Phototherapy with Filtered Sunlight in African Neonates. N Engl J Med. 2015;373(12):1115-1124.2 doi:10.1056/NEJMoa 1501074 Management of Neonatal Jaundice Speaker notes: By avoiding endotracheal intubation and mechanical ventilation, the constant distending pressure maintained in the lung by CPAP may also provide some physiologic benefits regarding lung protection and development.';

        $markdown = RagSourceFormatter::markdown($excerpt);

        $this->assertStringContainsString('**Role Of Filtered Sunlight Phototherapy**', $markdown);
        $this->assertStringContainsString("\nFiltered sunlight is noninferior", $markdown);
        $this->assertStringContainsString("\nDo not recommend", $markdown);
        $this->assertStringContainsString("\n**Risks**", $markdown);
        $this->assertStringContainsString("\n**Speaker Notes**", $markdown);
        $this->assertGreaterThanOrEqual(7, substr_count($markdown, "\n"));
    }
}
