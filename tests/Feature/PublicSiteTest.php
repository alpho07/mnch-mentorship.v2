<?php

namespace Tests\Feature;

use App\Services\Site\ResourceFilters;
use App\Services\Site\SiteDataSource;
use App\Services\Site\SitePresenter;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\LiveSiteSnapshot;
use Tests\TestCase;

/**
 * The redesigned public pages (home, /resources, /categories), run against the 26 Oct 2026 snapshot of the live site.
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(SiteDataSource::class, LiveSiteSnapshot::class);
        $this->travelTo(new DateTimeImmutable('2026-10-26 09:00:00', new DateTimeZone('Africa/Nairobi')));
        Cache::flush();
    }

    private function presenter(): SitePresenter
    {
        return new SitePresenter(new LiveSiteSnapshot, [
            'home' => '/', 'resources' => '/resources', 'categories' => '/categories',
            'resource' => '/resources', 'dashboard' => '/analytics/dashboard', 'asset' => '/',
        ], resource_path('data/kenya-counties.json'));
    }

    public function test_home_renders_headline_figures_and_the_new_fonts(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Wix+Madefor+Display', false)
            ->assertSeeInOrder(['94', 'mentorships · 20 counties', '302', 'mentees reached'])
            ->assertSeeInOrder(['Learning pipeline', '5', 'Counties covered', '20', 'Facilities reached', '28', 'County coverage', '43%']);
    }

    public function test_every_page_keeps_all_the_footer_links(): void
    {
        foreach ([route('home'), route('resources.index'), route('categories.index')] as $url) {
            $r = $this->get($url)->assertOk();
            foreach ([route('resources.index'), route('categories.index'), route('resources.browse'), route('guides.mentorship'),
                route('guides.emonc'), route('manual'), url('analytics/dashboard'), 'MNCH%20Mentorship.apk'] as $href) {
                $r->assertSee($href, false);
            }
            $r->assertSee('Top categories');
        }
    }

    public function test_home_map_shades_47_counties_and_lists_the_top_eight(): void
    {
        $vm = $this->presenter()->home(now());

        $this->assertCount(47, $vm['map']);
        $this->assertCount(20, collect($vm['map'])->where('live', true));
        $this->assertSame('Nairobi', $vm['firstCounty']);
        $this->assertSame(['Nairobi', 'Nyeri', "Murang'a", 'Vihiga', 'Machakos', 'Uasin Gishu', 'Bomet', 'Elgeyo Marakwet'], $vm['countyList']);
        $this->assertSame(94, array_sum(array_column($vm['byCounty'], 'total')));
        $this->assertSame('Turkana — no live mentorship', collect($vm['map'])->firstWhere('name', 'Turkana')['label']);
    }

    public function test_calendar_sorts_into_live_soon_and_closed_from_the_dates(): void
    {
        $cal = $this->presenter()->home(now())['calendar'];

        $this->assertCount(6, $cal['live']);
        $this->assertCount(3, $cal['soon']);
        $this->assertCount(4, $cal['done']);
        $this->assertSame('1 day left', $cal['live'][0]['label']);
        $this->assertSame('In 1 day', $cal['soon'][0]['label']);
        $this->assertSame('Completed', $cal['done'][0]['label']);
    }

    public function test_final_day_never_reads_zero_days_left(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-10-27 08:00:00'));
        $live = $this->presenter()->home(now())['calendar']['live'];

        $this->assertSame('Final day', collect($live)->firstWhere('facility', 'Lamu County Hospital')['label']);
        $this->assertStringNotContainsString('0 days', collect($live)->pluck('label')->implode('|'));
    }

    public function test_resources_lists_twelve_per_page(): void
    {
        $this->get(route('resources.index'))
            ->assertOk()
            ->assertSee('Showing 1–12 of 37')
            ->assertSeeInOrder(['Infant &amp; Child Mentorship Manual', 'Basic Paediatric Protocol (5th edition)'], false);
    }

    public function test_resources_filter_on_the_server_and_keep_other_filters_in_links(): void
    {
        $vm = $this->presenter()->resources(ResourceFilters::fromArray(['topic' => 'newborn-care', 'format' => 'Slides', 'sort' => 'downloads', 'view' => 'list']), now());

        $this->assertSame(14, $vm['total']);
        $this->assertSame('Oxygen Therapy', $vm['items'][0]['title']);
        $this->assertSame('/resources?topic=newborn-care&format=Slides&sort=downloads&view=list&page=2', $vm['pageLinks'][1]['href']);
    }

    public function test_resources_empty_state_and_bad_query_values(): void
    {
        $this->get(route('resources.index', ['q' => 'zzzz']))->assertOk()->assertSee('No resources match')->assertSee('Clear filters');
        $this->get(route('resources.index', ['sort' => 'drop table', 'format' => 'Video', 'page' => '-3']))->assertOk()->assertSee('Showing 1–12 of 37');
        $this->get(route('resources.index', ['page' => 99]))->assertOk()->assertSee('Showing 37–37 of 37');
    }

    public function test_categories_count_direct_plus_subtopics(): void
    {
        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Browse 18 resources')
            ->assertSee('Browse 11 resources')
            ->assertSee('No resources here yet');

        $vm = $this->presenter()->categories();
        $this->assertSame(['Newborn Care', 'Infant Health'], array_column($vm['featured'], 'name'));
        $this->assertSame(0, array_column($vm['rest'], 'total', 'name')['Quality Improvement']);
    }

    public function test_subtopics_link_to_the_filtered_library(): void
    {
        $newborn = $this->presenter()->categories()['featured'][0];

        $this->assertSame('/resources?topic=newborn-care&sub=Preterm+%26+Low+Birth+Weight', $newborn['subs'][0]['href']);
        $this->assertTrue(collect($newborn['subs'])->firstWhere('name', 'Newborn Screening')['empty']);
    }

    #[DataProvider('ages')]
    public function test_relative_ages(string $date, string $expected): void
    {
        $this->assertSame($expected, SitePresenter::ago($date, new DateTimeImmutable('2026-10-26')));
    }

    public static function ages(): array
    {
        return [
            ['2026-10-26', 'today'], ['2026-10-25', '1 day ago'], ['2026-10-12', '2 weeks ago'],
            ['2026-08-26', '2 months ago'], ['2026-02-26', '8 months ago'], ['2025-10-01', '1 year ago'],
        ];
    }
}
