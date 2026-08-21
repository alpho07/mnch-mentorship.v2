<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackPageVisit;
use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrackPageVisitMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', TrackPageVisit::class])
            ->get('/__test-track-visit', fn () => 'ok')
            ->name('__test.track-visit');

        Route::middleware(['web', TrackPageVisit::class])
            ->post('/livewire/__test-update', fn () => 'ok');

        Route::middleware(['web', TrackPageVisit::class])
            ->get('/storage/__test-asset.png', fn () => 'ok');
    }

    public function test_a_real_page_get_creates_a_page_visit_row_with_route_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/__test-track-visit');

        $visit = PageVisit::where('path', '/__test-track-visit')->first();
        $this->assertNotNull($visit);
        $this->assertSame('__test.track-visit', $visit->route_name);
        $this->assertSame($user->id, $visit->user_id);
    }

    public function test_a_livewire_post_does_not_create_a_row(): void
    {
        $this->post('/livewire/__test-update');

        $this->assertSame(0, PageVisit::where('path', 'livewire/__test-update')->count());
    }

    public function test_a_storage_asset_path_does_not_create_a_row(): void
    {
        $this->get('/storage/__test-asset.png');

        $this->assertSame(0, PageVisit::where('path', 'storage/__test-asset.png')->count());
    }

    public function test_a_guest_page_visit_has_a_null_user_id(): void
    {
        $this->get('/__test-track-visit');

        $visit = PageVisit::where('path', '/__test-track-visit')->first();
        $this->assertNotNull($visit);
        $this->assertNull($visit->user_id);
    }
}

