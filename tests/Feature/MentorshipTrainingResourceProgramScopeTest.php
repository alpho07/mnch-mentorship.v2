<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorshipTrainingResourceProgramScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_mentor_only_sees_trainings_for_their_program_when_setting_is_on(): void
    {
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, true);

        $emonc = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $newborn = Program::factory()->create(['name' => 'Newborn Care']);

        $mentor = User::factory()->create(['program_scope' => 'emonc']);
        $mentor->assignRole('facility_mentor');

        $emoncTraining = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $emonc->id,
        ]);
        $newbornTraining = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $newborn->id,
        ]);

        $this->actingAs($mentor);

        $ids = MentorshipTrainingResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($emoncTraining->id));
        $this->assertFalse($ids->contains($newbornTraining->id));
    }

    public function test_scoped_mentor_sees_all_their_trainings_when_setting_is_off(): void
    {
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, false);

        $emonc = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $newborn = Program::factory()->create(['name' => 'Newborn Care']);

        $mentor = User::factory()->create(['program_scope' => 'emonc']);
        $mentor->assignRole('facility_mentor');

        $emoncTraining = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $emonc->id,
        ]);
        $newbornTraining = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $newborn->id,
        ]);

        $this->actingAs($mentor);

        $ids = MentorshipTrainingResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($emoncTraining->id));
        $this->assertTrue($ids->contains($newbornTraining->id));
    }

    public function test_mentor_scoped_to_both_sees_every_program(): void
    {
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, true);

        $emonc = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $newborn = Program::factory()->create(['name' => 'Newborn Care']);

        $mentor = User::factory()->create(['program_scope' => 'both']);
        $mentor->assignRole('facility_mentor');

        $emoncTraining = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $emonc->id,
        ]);
        $newbornTraining = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $newborn->id,
        ]);

        $this->actingAs($mentor);

        $ids = MentorshipTrainingResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($emoncTraining->id));
        $this->assertTrue($ids->contains($newbornTraining->id));
    }
}
