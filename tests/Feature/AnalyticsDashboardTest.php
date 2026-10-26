<?php

namespace Tests\Feature;

use App\Services\Analytics\DashboardFilters;
use App\Services\Analytics\MetricsSource;
use App\Services\Analytics\Viz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Fixtures\LiveSnapshotMetrics;
use Tests\TestCase;

/**
 * /analytics/dashboard against a fixed snapshot of live numbers (LiveSnapshotMetrics), so no database
 * is touched. The fixture numbers and expected strings must not be edited to make a test pass.
 */
class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'), 'Refusing to run against a non-sqlite database');
        $this->app->bind(MetricsSource::class, LiveSnapshotMetrics::class);
        Cache::flush();
    }

    public function test_newborn_child_dashboard_shows_derived_figures(): void
    {
        $this->get('/analytics/dashboard?mode=mentorship')->assertOk()
            ->assertSeeText('Infant, Child & Newborn Care')
            ->assertSeeText('Unique mentees')
            ->assertSeeText('285 unique ÷ 76 mentorships')
            ->assertSeeText('3.8')
            ->assertSeeText('Vihiga leads with 55 mentees')
            ->assertSeeText('19 of 47 counties')
            ->assertSeeText('Show all 19 counties');
    }

    public function test_unknown_mode_falls_back_to_newborn_child(): void
    {
        $this->get('/analytics/dashboard?mode=nope')->assertOk()->assertSeeText('Infant, Child & Newborn Care');
    }

    public function test_county_focus_panel(): void
    {
        $this->get('/analytics/dashboard?mode=mentorship&county=Vihiga')->assertOk()
            ->assertSeeText('County focus')
            ->assertSeeText('2 / 107')
            ->assertSeeText('Ranked #1 of 19 counties by mentees.')
            ->assertSeeText('Back to national view');
    }

    public function test_county_without_mentorship_is_explained(): void
    {
        $this->get('/analytics/dashboard?mode=mentorship&county=Garissa')->assertOk()
            ->assertSeeText('Nothing recorded in this county yet');
    }

    public function test_emonc_setup_banner_while_nothing_is_tagged(): void
    {
        $this->get('/analytics/dashboard?mode=emonc')->assertOk()
            ->assertSeeText('No EmONC mentorship data yet')
            ->assertSeeText('No EmONC mentees yet');
    }

    public function test_mentor_dashboard_flags_impossible_attendance(): void
    {
        $this->get('/analytics/dashboard?mode=mentor')->assertOk()
            ->assertSeeText('145 ÷ 58 mentors')
            ->assertSeeText('69% of all mentors')
            ->assertSeeText('205%')
            ->assertSee('Attendance above 100%', false)
            ->assertSeeText('The performance matrix shows 10 mentors on 0 CPD points');
    }

    public function test_mentor_matrix_filters_by_level(): void
    {
        $this->get('/analytics/dashboard?mode=mentor&level=AP')->assertOk()->assertSeeText('3 of 53 live classes');
    }

    public function test_each_assessed_facility_is_counted_once(): void
    {
        $html = $this->get('/analytics/dashboard?mode=assessment')->assertOk()
            ->assertSeeText('8 skills labs · 16 rooms only')
            ->assertSeeText('37 of 10,703 facilities')
            ->assertSeeText('8 ready facilities have no mentorship')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Bondo District Hospital'));
    }

    public function test_trainings_keep_enrolments_and_unique_participants_apart(): void
    {
        $this->get('/analytics/dashboard?mode=training')->assertOk()
            ->assertSeeText('3,897')
            ->assertSeeText('4,703 programme enrolments')
            ->assertSeeText('1,176')
            ->assertSeeText('Every county has trained staff')
            ->assertSeeText('224 do not and are not mapped');
    }

    public function test_selected_training_programme_is_highlighted(): void
    {
        $this->get('/analytics/dashboard?mode=training&programme=cnc')->assertOk()
            ->assertSeeText('CNC enrolments')
            ->assertSeeText('18.8% of all enrolments');
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $f = DashboardFilters::fromArray(['mode' => 'assessment', 'year' => '20x6', 'type' => 'other', 'level' => 'X']);
        $this->assertNull($f->year);
        $this->assertNull($f->assessmentType);
        $this->assertNull($f->level);

        $this->get('/analytics/dashboard?mode=assessment&year=abc')->assertOk();
    }

    public function test_small_shares_do_not_read_as_zero(): void
    {
        $this->assertSame('<0.1%', Viz::pct(1, 4323));
        $this->assertSame('0.2%', Viz::pct(23, 10703));
        $this->assertSame('0%', Viz::pct(0, 10));
    }
}
