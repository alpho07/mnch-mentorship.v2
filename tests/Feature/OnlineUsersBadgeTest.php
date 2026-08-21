<?php

namespace Tests\Feature;

use App\Livewire\OnlineUsersBadge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnlineUsersBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_counts_only_recently_seen_users(): void
    {
        User::factory()->create(['name' => 'Online Person', 'last_seen_at' => now()->subMinutes(2)]);
        User::factory()->create(['name' => 'Stale Person', 'last_seen_at' => now()->subMinutes(10)]);
        User::factory()->create(['name' => 'Never Seen', 'last_seen_at' => null]);

        Livewire::test(OnlineUsersBadge::class)
            ->assertSee('1 online');
    }

    public function test_badge_links_to_the_activity_page_currently_online_section(): void
    {
        Livewire::test(OnlineUsersBadge::class)
            ->assertSeeHtml(\App\Filament\Pages\ActivityDashboard::getUrl().'#currently-online');
    }
}

