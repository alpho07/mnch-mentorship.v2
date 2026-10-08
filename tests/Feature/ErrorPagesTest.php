<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_unknown_page_shows_the_friendly_404_not_the_framework_default(): void
    {
        $this->get('/this-page-does-not-exist-'.uniqid())
            ->assertNotFound()
            ->assertSee("We can't find that page")
            ->assertSee('Back to home')
            ->assertSee('mnch@health.go.ke')
            ->assertDontSee('NOT FOUND');
    }

    public function test_error_pages_are_self_contained(): void
    {
        // They must render even when Vite assets, CDNs or the database are what failed.
        $html = $this->get('/nope-'.uniqid())->getContent();

        $this->assertStringNotContainsString('/build/assets', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $html);
    }

    /**
     * @dataProvider statusCodes
     */
    public function test_each_status_has_a_plain_language_page(int $code, string $headline): void
    {
        Route::get('/__err/'.$code, fn () => abort($code));

        $this->get('/__err/'.$code)
            ->assertStatus($code)
            ->assertSee($headline)
            ->assertSee('Details for support')
            ->assertSee((string) $code);
    }

    public static function statusCodes(): array
    {
        return [
            [400, "That link didn't work"],
            [401, 'Please sign in to continue'],
            [403, "You don't have access to this page"],
            [404, "We can't find that page"],
            [405, "That action isn't available here"],
            [408, 'That took too long'],
            [410, 'This page is no longer available'],
            [413, 'That file is too big'],
            [419, 'Your session timed out'],
            [422, 'Something needs fixing'],
            [429, 'Too many tries'],
            [500, 'Something went wrong on our side'],
            [502, "We're having trouble connecting"],
            [503, "We'll be right back"],
            [504, "That's taking too long"],
            // Codes without their own page use the generic 4xx / 5xx ones.
            [418, "We couldn't open that"],
            [507, 'Something went wrong on our side'],
        ];
    }

    public function test_a_server_crash_never_leaks_the_exception_message(): void
    {
        config(['app.debug' => false]);
        Route::get('/__boom', fn () => throw new \RuntimeException('secret db password is hunter2'));

        $this->get('/__boom')
            ->assertStatus(500)
            ->assertSee('Something went wrong on our side')
            ->assertSee('Try again')
            ->assertDontSee('hunter2');
    }

    public function test_rate_limit_page_counts_down_from_retry_after(): void
    {
        Route::get('/__slow', fn () => abort(429, '', ['Retry-After' => 42]));

        $this->get('/__slow')
            ->assertStatus(429)
            ->assertSee('var left = 42', false)
            ->assertSee('disabled', false);
    }

    public function test_maintenance_style_503_refreshes_itself(): void
    {
        Route::get('/__down', fn () => abort(503));

        $this->get('/__down')->assertStatus(503)->assertSee('http-equiv="refresh"', false);
    }

    public function test_api_errors_stay_json_even_without_an_accept_header(): void
    {
        $this->get('/api/v1/definitely-not-a-route')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);

        // Unauthenticated: JSON 401, not a redirect to a login page or an HTML page.
        $this->get('/api/v1/assessments')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_json_requests_to_web_routes_still_get_json(): void
    {
        $this->getJson('/nope-'.uniqid())->assertNotFound()->assertJsonStructure(['message']);
    }
}
