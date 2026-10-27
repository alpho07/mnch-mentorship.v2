<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public site installs and behaves like an app on phones: manifest, service worker, drawer, tab bar. */
class MobileAppShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_web_app_manifest_is_installable(): void
    {
        $m = json_decode(file_get_contents(public_path('site.webmanifest')), true);

        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/', $m['scope']);
        $this->assertNotEmpty($m['name']);
        $this->assertNotEmpty($m['short_name']);
        $sizes = collect($m['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', collect($m['icons'])->pluck('purpose')->all());
        foreach ($m['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), "{$icon['src']} is missing");
        }
        foreach ($m['shortcuts'] as $sc) {
            $this->assertStringStartsWith('/', $sc['url']);
        }
    }

    public function test_the_service_worker_and_offline_page_exist_and_never_cache_pages(): void
    {
        $sw = file_get_contents(public_path('sw-site.js'));

        $this->assertStringContainsString("'/offline'", $sw);
        $this->assertStringContainsString("startsWith('/admin')", $sw, 'the admin panel must stay out of the public worker');
        $this->assertStringContainsString("req.method !== 'GET'", $sw);
        $this->get('/offline')->assertOk()->assertSee('offline');
    }

    public function test_public_and_dashboard_pages_carry_the_app_shell(): void
    {
        foreach (['/', '/resources', '/categories', '/analytics/dashboard'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('site.webmanifest', $html, "$path has no manifest link");
            $this->assertStringContainsString('theme-color', $html);
            $this->assertStringContainsString('viewport-fit=cover', $html);
            $this->assertStringContainsString('class="tabbar"', $html, "$path has no bottom tab bar");
            $this->assertStringContainsString('class="drawer"', $html, "$path has no menu drawer");
            $this->assertStringContainsString('sw-site.js', $html);
        }
    }

    public function test_the_tab_bar_marks_the_current_section(): void
    {
        $this->get('/resources')->assertSee('class="tab-i on"', false);
        $this->get('/analytics/dashboard')->assertSee('Dashboards');
    }

    public function test_auth_pages_keep_the_photo_banner_on_phones(): void
    {
        foreach (['/admin/login', '/admin/register', '/admin/password-reset/request'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('max-width:780px', $html, "$path lost its phone layout");
            $this->assertStringContainsString('mnch-mentorship-login-hero', $html);
        }
    }
}
