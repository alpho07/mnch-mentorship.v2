<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Keeps the manual and guides in step with how mentorships are really created and certified. */
class GuideContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sop_and_manual_describe_every_creation_method(): void
    {
        foreach (['/guides/mentorship-sop', '/user-manual'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            foreach (['New Mentorship', 'Guided Setup', 'Quick Setup', 'Chat Setup', 'MNCHGPT', 'Mentorship Settings', 'Pending Mentorships'] as $term) {
                $this->assertStringContainsString($term, $html, "$path no longer mentions $term");
            }
        }
    }

    public function test_creation_facts_match_the_app(): void
    {
        $sop = $this->get('/guides/mentorship-sop')->assertOk()->getContent();

        $this->assertStringContainsString('between 2 and 10', $sop);
        $this->assertStringContainsString('Co-mentors come after you save', $sop);
        $this->assertStringContainsString('EmONC has no mentorship dates', $sop);
        $this->assertStringNotContainsString('Training name', $this->get('/user-manual')->getContent(), 'the create form has no name field');
    }

    public function test_certification_wording_matches_the_buttons(): void
    {
        foreach (['/user-manual', '/guides/mentorship-sop', '/guides/emonc'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('Mentor Approve', $html, "$path should name the Mentor Approve button");
            $this->assertStringNotContainsString('Recommend for certificate', $html);
            $this->assertStringNotContainsString('Recommend for Certificate', $html);
        }
    }
}
