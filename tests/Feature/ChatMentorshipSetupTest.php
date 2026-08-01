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

    public function test_completing_the_training_details_stage_creates_the_training(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $facility->subcounty->county_id);
        $component->call('answer', 'facility_id', $facility->id);
        $component->call('answer', 'program_id', $program->id);
        $component->call('answer', 'start_date', now()->addDay()->toDateString());
        $component->call('answer', 'end_date', now()->addMonth()->toDateString());
        $component->call('answer', 'max_participants', 8);

        $this->assertDatabaseHas('trainings', [
            'program_id' => $program->id,
            'facility_id' => $facility->id,
            'max_participants' => 8,
            'guided_setup_method' => 'chat',
        ]);
        $this->assertNotNull($component->instance()->training);
    }

    public function test_emonc_program_skips_the_date_slots(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Maternal Health (EmONC)', 'is_active' => true]);
        $facility = \App\Models\Facility::factory()->create();

        $component = Livewire::test(ChatMentorshipSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $facility->subcounty->county_id);
        $component->call('answer', 'facility_id', $facility->id);
        $component->call('answer', 'program_id', $program->id);
        $component->call('answer', 'max_participants', 8);

        $this->assertNotNull($component->instance()->training);
        $this->assertNull($component->instance()->training->start_date);
    }
}
