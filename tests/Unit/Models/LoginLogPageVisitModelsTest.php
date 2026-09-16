<?php

namespace Tests\Unit\Models;

use App\Models\LoginLog;
use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLogPageVisitModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_log_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $log = LoginLog::create([
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_in_at' => now(),
        ]);

        $this->assertTrue($log->user->is($user));
    }

    public function test_page_visit_belongs_to_user_and_has_no_updated_at(): void
    {
        $user = User::factory()->create();

        $visit = PageVisit::create([
            'user_id' => $user->id,
            'route_name' => 'filament.admin.pages.dashboard',
            'path' => '/admin',
            'created_at' => now(),
        ]);

        $this->assertTrue($visit->user->is($user));
        $this->assertNull($visit->getAttribute('updated_at'));
        $this->assertArrayNotHasKey('updated_at', $visit->getAttributes());
    }

    public function test_page_visit_user_id_is_nullable_for_guests(): void
    {
        $visit = PageVisit::create([
            'user_id' => null,
            'route_name' => null,
            'path' => '/resources',
            'created_at' => now(),
        ]);

        $this->assertNull($visit->user_id);
    }

    public function test_user_last_seen_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        User::where('id', $user->id)->update(['last_seen_at' => now()]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->refresh()->last_seen_at);
    }

    public function test_user_has_page_visits_relationship(): void
    {
        $user = User::factory()->create();

        PageVisit::create(['user_id' => $user->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()]);

        $this->assertTrue($user->pageVisits->contains(fn ($v) => $v->path === '/admin'));
    }

    public function test_user_has_login_logs_relationship(): void
    {
        $user = User::factory()->create();

        LoginLog::create(['user_id' => $user->id, 'logged_in_at' => now()]);

        $this->assertTrue($user->loginLogs->contains(fn ($l) => $l->user_id === $user->id));
    }
}
