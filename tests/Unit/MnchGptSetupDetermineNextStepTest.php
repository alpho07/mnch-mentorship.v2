<?php

namespace Tests\Unit;

use App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup;
use App\Models\County;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MnchGptSetupDetermineNextStepTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_ambiguous_candidates_from_the_last_turn_take_priority(): void
    {
        $this->actingAsCoordinator();
        $page = new MnchGptSetup;
        $page->mount();

        $step = $page->determineNextStep([
            'county_id' => [
                ['id' => 1, 'label' => 'Tharaka Nithi'],
                ['id' => 2, 'label' => 'Tharaka North'],
            ],
        ]);

        $this->assertSame('county_id', $step['slot']);
        $this->assertSame('Tharaka Nithi', $step['options'][1]['label']);
        $this->assertSame('Tharaka North', $step['options'][2]['label']);
    }

    public function test_a_small_enum_next_slot_is_shown_proactively(): void
    {
        $this->actingAsCoordinator();
        $page = new MnchGptSetup;
        $page->mount();

        // Nothing filled yet — is_pilot (2 options) is next, well under the
        // proactive-list threshold.
        $step = $page->determineNextStep([]);

        $this->assertSame('is_pilot', $step['slot']);
        $this->assertCount(2, $step['options']);
    }

    public function test_a_large_enum_next_slot_is_not_shown_proactively(): void
    {
        $this->actingAsCoordinator();
        County::factory()->count(15)->create();
        $page = new MnchGptSetup;
        $page->mount();
        $page->answer('is_pilot', 0);

        // county_id has more than MAX_PROACTIVE_OPTIONS options — no list,
        // just the open question (handled by the normal reply, not here).
        $step = $page->determineNextStep([]);

        $this->assertNull($step);
    }

    public function test_no_list_once_past_the_generic_slot_flow(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $page = new MnchGptSetup;
        $page->mount();
        $page->answer('is_pilot', 0);
        $page->answer('county_id', $facility->subcounty->county_id);
        $page->answer('facility_id', $facility->id);
        $page->answer('program_id', $program->id);
        $page->answer('start_date', now()->addDay()->toDateString());
        $page->answer('end_date', now()->addMonth()->toDateString());
        $page->answer('max_participants', 8);
        $page->answer('class_name', 'Cohort A');
        $page->answer('class_start_date', now()->addDay()->toDateString());
        $page->answer('class_end_date', now()->addMonth()->toDateString());
        $page->answer('class_description', 'skip');

        $this->assertSame('modules', $page->activeStage());

        $step = $page->determineNextStep([]);

        $this->assertNull($step);
    }
}
