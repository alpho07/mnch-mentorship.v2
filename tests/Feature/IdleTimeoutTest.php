<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Inactivity timer: endpoints, defaults and where the script is (and is not) loaded. */
class IdleTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_15_minutes_with_a_60_second_warning(): void
    {
        $this->assertSame(900, config('session.idle_timeout'));
        $this->assertSame(60, config('session.idle_warning'));
    }

    public function test_keep_alive_needs_a_signed_in_user(): void
    {
        $this->post(route('session.keep-alive'))->assertRedirect();
        $this->actingAs(User::factory()->create())->post(route('session.keep-alive'))->assertNoContent();
    }

    public function test_expire_signs_the_user_out_and_sends_them_to_the_front_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('session.expire'))->assertRedirect('/?signed_out=inactive');
        $this->assertGuest();
    }

    public function test_the_timer_script_loads_for_signed_in_users_only(): void
    {
        foreach ([route('home'), route('resources.index'), route('categories.index')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('idle-timeout.js', false);
        }

        $this->actingAs(User::factory()->create());
        foreach ([route('home'), route('resources.index'), route('categories.index')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('idle-timeout.js', false)
                ->assertSee('data-timeout="900"', false)
                ->assertSee('data-expire="'.route('session.expire').'"', false);
        }
    }

    public function test_the_front_page_explains_why_the_user_was_signed_out(): void
    {
        $this->get('/?signed_out=inactive')->assertOk()->assertSee('You were signed out')->assertSee('15 minutes of inactivity');
        $this->get('/')->assertOk()->assertDontSee('You were signed out');
    }

    public function test_the_login_page_shows_the_same_notice_and_the_front_page_links_to_it(): void
    {
        $this->get('/admin/login?signed_out=inactive')->assertOk()->assertSee('You were signed out')->assertSee('15 minutes of inactivity');
        $this->get('/admin/login')->assertOk()->assertDontSee('You were signed out');
        $this->get('/?signed_out=inactive')->assertSee('/admin/login?signed_out=inactive', false);
    }
}
