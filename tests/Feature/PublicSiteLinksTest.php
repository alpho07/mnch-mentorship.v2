<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\ResourceType;
use App\Models\Subcounty;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Crawls the public front site from its entry pages and fails on any internal link that answers 4xx/5xx.
 * Runs on seeded sqlite data, so it covers resource, category, search, guide and manual pages.
 */
class PublicSiteLinksTest extends TestCase
{
    use RefreshDatabase;

    private const ENTRY = ['/', '/resources', '/categories', '/resources/browse', '/user-manual', '/guides/mentorship-sop', '/guides/emonc'];

    /** Static files that exist under public/ (the test kernel does not serve them). */
    private function isPublicFile(string $path): bool
    {
        return is_file(public_path(urldecode(strtok($path, '?'))));
    }

    private function seedContent(): void
    {
        Cache::flush();
        $author = User::factory()->create();
        $type = ResourceType::create(['name' => 'PDF Document', 'slug' => 'pdf-document', 'is_active' => true]);
        $parent = ResourceCategory::create(['name' => 'Newborn Care', 'slug' => 'newborn-care', 'is_active' => true, 'sort_order' => 1]);
        $child = ResourceCategory::create(['name' => 'Preterm', 'slug' => 'preterm', 'parent_id' => $parent->id, 'is_active' => true]);
        ResourceCategory::create(['name' => 'Empty topic', 'slug' => 'empty-topic', 'is_active' => true, 'sort_order' => 2]);

        foreach ([[$parent, true], [$child, false]] as $i => [$cat, $featured]) {
            Resource::create([
                'title' => "Sample resource $i", 'slug' => "sample-resource-$i", 'excerpt' => 'Sample',
                'resource_type_id' => $type->id, 'category_id' => $cat->id, 'author_id' => $author->id,
                'status' => 'published', 'visibility' => 'public', 'is_featured' => $featured,
                'published_at' => now()->subDay(), 'external_url' => 'https://example.org/doc',
            ]);
        }

        $county = County::factory()->create();
        $sub = Subcounty::create(['county_id' => $county->id, 'name' => 'Sub']);
        $facility = Facility::factory()->create(['subcounty_id' => $sub->id]);
        Training::factory()->facilityMentorship()->create([
            'status' => 'active', 'facility_id' => $facility->id, 'county_id' => $county->id,
            'start_date' => now()->subDays(3), 'end_date' => now()->addDays(3),
        ]);
    }

    public function test_no_internal_link_on_the_front_site_is_broken(): void
    {
        $this->seedContent();

        $queue = self::ENTRY;
        $seen = [];
        $broken = [];
        $crawled = 0;

        while ($queue && $crawled < 300) {
            $url = array_shift($queue);
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $response = $this->get($url);
            $status = $response->getStatusCode();

            if ($status >= 300 && $status < 400) {
                $queue[] = $this->internalPath((string) $response->headers->get('Location')) ?? '';
                continue;
            }
            if ($status >= 400) {
                $broken[$url] = $status;
                continue;
            }
            $crawled++;

            preg_match_all('~<a\b[^>]*\shref="([^"]*)"~i', $response->getContent(), $m);
            foreach (array_unique($m[1]) as $href) {
                $path = $this->internalPath(html_entity_decode($href));
                if ($path === null || str_starts_with($path, '/admin') || $this->isPublicFile($path)) {
                    continue;
                }
                $queue[] = $path;
            }
        }

        $this->assertGreaterThan(10, $crawled, 'the crawl should reach resource and category pages, not just the entry pages');
        $this->assertSame([], $broken, 'Broken internal links: '.json_encode($broken, JSON_UNESCAPED_SLASHES));
    }

    public function test_the_footer_and_sign_in_links_resolve_on_every_front_page(): void
    {
        $this->seedContent();

        foreach (['/', '/resources', '/categories'] as $page) {
            $html = $this->get($page)->assertOk()->getContent();
            foreach (['/admin/login', '/guides/mentorship-sop', '/guides/emonc', '/user-manual', '/analytics/dashboard', '/resources/browse'] as $path) {
                $this->assertStringContainsString($path, $html, "$page is missing a link to $path");
                $this->get($path)->assertSuccessful();
            }
        }
    }

    /** Path (+query) for same-site links; null for external, mailto, tel, anchors and script placeholders. */
    private function internalPath(string $href): ?string
    {
        if ($href === '' || $href[0] === '#' || preg_match('~^(mailto:|tel:|javascript:)~', $href)) {
            return null;
        }
        $p = parse_url($href);
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        if (isset($p['host']) && $p['host'] !== $host && $p['host'] !== 'localhost') {
            return null;
        }
        $path = $p['path'] ?? '/';

        return ($path[0] === '/' ? $path : '/'.$path).(isset($p['query']) ? '?'.$p['query'] : '');
    }
}
