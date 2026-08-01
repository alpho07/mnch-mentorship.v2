<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChatMentorshipSetupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Ada Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_page_loads_with_a_greeting_and_the_first_question(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(ChatMentorshipSetup::class)
            ->assertSuccessful()
            ->assertSee('Welcome, Ada!')
            ->assertSee('Is this a real live mentorship or a pilot/test run?');
    }

    public function test_answering_is_pilot_appends_an_echo_and_asks_for_county(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);

        $messages = $component->instance()->messages;

        $this->assertSame('bot', $messages[0]['role']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertSame('Live Mentorship', $messages[1]['text']);
        $this->assertSame('Which county?', $messages[2]['text']);
    }

    public function test_answering_county_asks_for_facility_scoped_to_that_county(): void
    {
        $this->actingAsCoordinator();
        $facility = \App\Models\Facility::factory()->create(['name' => 'Kiambu Level 4']);
        $countyId = $facility->subcounty->county_id;

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $countyId);

        $slot = collect(\App\Services\Chat\MentorshipChatScript::build($component->instance()))
            ->first(fn ($s) => $s->id === 'facility_id');

        $this->assertArrayHasKey($facility->id, $slot->getOptions($component->instance()->answers));
    }
}
