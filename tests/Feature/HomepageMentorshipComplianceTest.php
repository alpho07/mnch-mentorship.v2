<?php

namespace Tests\Feature;

use App\Models\Training;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomepageMentorshipComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_a_draft_mentorship_with_ongoing_dates_is_not_shown_as_ongoing(): void
    {
        // Never actually started (still draft), but its stale date fields
        // happen to bracket "now" — must not be advertised as running.
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'title' => 'Fake Ongoing Mentorship',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Fake Ongoing Mentorship');
    }

    public function test_a_genuinely_active_mentorship_is_shown_as_ongoing(): void
    {
        Training::factory()->facilityMentorship()->create([
            'status' => 'active',
            'title' => 'Real Ongoing Mentorship',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Real Ongoing Mentorship');
    }

    public function test_a_draft_mentorship_with_closed_dates_is_not_shown_as_closed(): void
    {
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'title' => 'Fake Closed Mentorship',
            'end_date' => now()->subDays(5),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Fake Closed Mentorship');
    }

    public function test_a_draft_mentorship_with_a_future_start_date_is_still_shown_as_upcoming(): void
    {
        // Upcoming is legitimately draft by definition — it hasn't started yet.
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'title' => 'Real Upcoming Mentorship',
            'start_date' => now()->addDays(5),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Real Upcoming Mentorship');
    }

    public function test_the_view_map_ribbon_count_excludes_stalled_draft_mentorships(): void
    {
        $county = \App\Models\County::factory()->create();
        Training::factory()->facilityMentorship()->create([
            'status' => 'active',
            'county_id' => $county->id,
        ]);
        // Never started — must not inflate the public coverage count.
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'county_id' => $county->id,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('1 Mentorship');
        $response->assertDontSee('2 Mentorships');
    }
}
