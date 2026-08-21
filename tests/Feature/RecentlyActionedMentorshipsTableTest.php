<?php

namespace Tests\Feature;

use App\Livewire\RecentlyActionedMentorshipsTable;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecentlyActionedMentorshipsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_a_cancelled_mentorship_as_inactive(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'cancelled',
            'title' => 'Deactivated One',
        ]);

        Livewire::test(RecentlyActionedMentorshipsTable::class)
            ->assertCanSeeTableRecords([$training])
            ->assertSee('Inactive');
    }

    public function test_lists_a_soft_deleted_mentorship_as_deleted(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'title' => 'Deleted One',
        ]);
        $training->delete();

        Livewire::test(RecentlyActionedMentorshipsTable::class)
            ->assertCanSeeTableRecords([$training])
            ->assertSee('Deleted');
    }

    public function test_excludes_a_mentorship_cancelled_more_than_90_days_ago(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'cancelled',
            'title' => 'Old Cancelled One',
        ]);
        // Eloquent's save() auto-touches updated_at, overriding a plain
        // ->update() call — bypass via the query builder directly.
        \Illuminate\Support\Facades\DB::table('trainings')->where('id', $training->id)->update(['updated_at' => now()->subDays(120)]);

        Livewire::test(RecentlyActionedMentorshipsTable::class)
            ->assertCanNotSeeTableRecords([$training]);
    }

    public function test_reactivate_reverses_a_deactivated_mentorship(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'cancelled']);

        Livewire::test(RecentlyActionedMentorshipsTable::class)
            ->call('reactivateMentorship', $training->id);

        $this->assertSame('draft', $training->fresh()->status);
    }

    public function test_restore_reverses_a_deleted_mentorship(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);
        $training->delete();

        Livewire::test(RecentlyActionedMentorshipsTable::class)
            ->call('restoreMentorship', $training->id);

        $this->assertDatabaseHas('trainings', ['id' => $training->id, 'deleted_at' => null]);
    }

    public function test_state_filter_narrows_to_deleted_only(): void
    {
        $deleted = Training::factory()->facilityMentorship()->create(['status' => 'draft', 'title' => 'Del']);
        $deleted->delete();
        $inactive = Training::factory()->facilityMentorship()->create(['status' => 'cancelled', 'title' => 'Inact']);

        Livewire::test(RecentlyActionedMentorshipsTable::class)
            ->filterTable('state', 'deleted')
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_mentor_filter_options_do_not_crash_when_a_mentor_has_no_name_column(): void
    {
        $mentor = User::factory()->create(['name' => null, 'first_name' => 'Only', 'last_name' => 'First']);
        Training::factory()->facilityMentorship()->create(['status' => 'cancelled', 'mentor_id' => $mentor->id]);

        Livewire::test(RecentlyActionedMentorshipsTable::class)->assertSuccessful();
    }
}
