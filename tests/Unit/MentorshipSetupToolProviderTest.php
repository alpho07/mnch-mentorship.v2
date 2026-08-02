<?php

namespace Tests\Unit;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\User;
use App\Services\Chat\Tools\MentorshipSetupToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipSetupToolProviderTest extends TestCase
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

    public function test_schema_only_lists_currently_eligible_unfilled_slots(): void
    {
        $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $properties = array_keys($tool->schema()['properties']);

        $this->assertContains('is_pilot', $properties);
        $this->assertContains('county_id', $properties);
        // class_name belongs to a later stage, not eligible yet.
        $this->assertNotContains('class_name', $properties);
    }

    public function test_execute_fills_valid_slots_and_reports_rejected_ones(): void
    {
        $user = $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);

        $result = $tool->execute([
            'is_pilot' => 0,
            'max_participants' => 999, // invalid — over the 2-10 cap
        ], $user);

        $this->assertContains('is_pilot', $result['filled']);
        $this->assertContains('max_participants', $result['rejected']);
        $this->assertSame(0, $page->answers['is_pilot']);
        $this->assertArrayNotHasKey('max_participants', $page->answers);
    }
}
