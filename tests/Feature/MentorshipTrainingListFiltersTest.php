<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ListMentorshipTrainings;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorshipTrainingListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Admin User']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_mentorship::training']);
        $user->assignRole('super_admin');
        $this->actingAs($user);

        return $user;
    }

    public function test_list_page_shows_county_and_facility_filters_alongside_the_existing_ones(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListMentorshipTrainings::class)
            ->assertSuccessful()
            ->assertSeeHtml('County')
            ->assertSeeHtml('Facility')
            ->assertSeeHtml('Program Area')
            ->assertSeeHtml('Status');
    }

    public function test_county_filter_scopes_the_table_to_that_county(): void
    {
        $this->actingAsAdmin();

        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $program = Program::factory()->create(['is_active' => true]);

        $trainingA = Training::factory()->facilityMentorship()->create([
            'facility_id' => $facilityA->id,
            'county_id' => $facilityA->subcounty->county_id,
            'program_id' => $program->id,
        ]);
        $trainingB = Training::factory()->facilityMentorship()->create([
            'facility_id' => $facilityB->id,
            'county_id' => $facilityB->subcounty->county_id,
            'program_id' => $program->id,
        ]);

        Livewire::test(ListMentorshipTrainings::class)
            ->filterTable('county_id', $facilityA->subcounty->county_id)
            ->assertCanSeeTableRecords([$trainingA])
            ->assertCanNotSeeTableRecords([$trainingB]);
    }

    public function test_facility_filter_scopes_the_table_to_that_facility(): void
    {
        $this->actingAsAdmin();

        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $program = Program::factory()->create(['is_active' => true]);

        $trainingA = Training::factory()->facilityMentorship()->create([
            'facility_id' => $facilityA->id,
            'county_id' => $facilityA->subcounty->county_id,
            'program_id' => $program->id,
        ]);
        $trainingB = Training::factory()->facilityMentorship()->create([
            'facility_id' => $facilityB->id,
            'county_id' => $facilityB->subcounty->county_id,
            'program_id' => $program->id,
        ]);

        Livewire::test(ListMentorshipTrainings::class)
            ->filterTable('facility_id', ['facility_id' => $facilityA->id])
            ->assertCanSeeTableRecords([$trainingA])
            ->assertCanNotSeeTableRecords([$trainingB]);
    }

    public function test_status_filter_still_works_alongside_the_new_filters(): void
    {
        $this->actingAsAdmin();

        $active = Training::factory()->facilityMentorship()->create(['status' => 'active']);
        $draft = Training::factory()->facilityMentorship()->create(['status' => 'draft']);

        Livewire::test(ListMentorshipTrainings::class)
            ->filterTable('status', 'active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$draft]);
    }
}
