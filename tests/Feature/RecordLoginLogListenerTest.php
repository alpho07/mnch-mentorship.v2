<?php

namespace Tests\Feature;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordLoginLogListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_event_creates_a_login_log_row(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertSame(1, LoginLog::where('user_id', $user->id)->count());
        $log = LoginLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log->logged_in_at);
    }

    public function test_two_logins_create_two_rows(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));
        event(new Login('web', $user, false));

        $this->assertSame(2, LoginLog::where('user_id', $user->id)->count());
    }
}

