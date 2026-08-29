<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_xml_renders(): void
    {
        cache()->forget('sitemap');

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('text/xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee('<urlset', false);
        $response->assertSee(route('home'), false);
        $response->assertSee(route('resources.index'), false);
    }

    public function test_feed_rss_renders(): void
    {
        cache()->forget('rss_feed');

        $response = $this->get('/feed');

        $response->assertOk();
        $this->assertStringContainsString('application/rss+xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee('<rss version="2.0">', false);
        $response->assertSee('<channel>', false);
    }
}
