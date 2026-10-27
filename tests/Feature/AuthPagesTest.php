<?php

namespace Tests\Feature;

use App\Livewire\Auth\CustomResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Sign-in, register and password-reset pages share one design and show live platform figures. */
class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_register_and_reset_request_pages_share_the_hero_and_show_live_figures(): void
    {
        foreach (['/admin/login', '/admin/register', '/admin/password-reset/request'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('impact-card', $html, "$path is missing the shared hero");
            $this->assertStringContainsString('Live nationwide impact', $html);
            $this->assertStringNotContainsString('2,400+', $html, "$path still shows the made-up figures");
            $this->assertStringNotContainsString('unsplash.com', $html);
        }
    }

    public function test_reset_password_page_uses_the_shared_design(): void
    {
        Livewire::withQueryParams(['email' => 'user@example.org'])
            ->test(CustomResetPassword::class, ['token' => 'abc'])
            ->assertSee('Set your new password')
            ->assertSee('Live nationwide impact')
            ->assertSee('At least 8 characters');
    }

    public function test_register_and_reset_links_between_the_pages_still_work(): void
    {
        $this->get('/admin/register')->assertOk()->assertSee(route('filament.admin.auth.login'), false);
        $this->get('/admin/login')->assertOk()
            ->assertSee(route('filament.admin.auth.register'), false)
            ->assertSee(route('filament.admin.auth.password-reset.request'), false);
    }
}
