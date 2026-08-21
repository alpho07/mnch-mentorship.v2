<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackUserActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrackUserActivityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', TrackUserActivity::class])
            ->get('/__test-track-activity', fn () => 'ok');
    }

    public function test_first_request_sets_last_seen_at(): void
    {
        $user = User::factory()->create(['last_seen_at' => null]);

        $this->actingAs($user)->get('/__test-track-activity');

        $this->assertNotNull($user->refresh()->last_seen_at);
    }

    public function test_a_second_request_within_60_seconds_does_not_change_last_seen_at(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/__test-track-activity');
        $firstSeen = $user->refresh()->last_seen_at;

        $this->travel(30)->seconds();
        $this->actingAs($user)->get('/__test-track-activity');

        $this->assertTrue($firstSeen->equalTo($user->refresh()->last_seen_at));
    }

    public function test_a_request_after_60_seconds_updates_last_seen_at(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/__test-track-activity');
        $firstSeen = $user->refresh()->last_seen_at;

        $this->travel(61)->seconds();
        $this->actingAs($user)->get('/__test-track-activity');

        $this->assertTrue($firstSeen->lessThan($user->refresh()->last_seen_at));
    }

    public function test_guest_requests_do_not_error(): void
    {
        $response = $this->get('/__test-track-activity');

        $response->assertOk();
    }
}

