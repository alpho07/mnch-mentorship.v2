<?php

namespace Tests\Unit\Models;

use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAllowedProgramIdsTest extends TestCase
{
    use RefreshDatabase;

    private function makeMentor(string $scope = 'both'): User
    {
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);

        $user = User::factory()->create(['program_scope' => $scope]);
        $user->assignRole('facility_mentor');

        return $user;
    }

    public function test_returns_null_when_scoping_setting_is_off(): void
    {
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, false);
        $user = $this->makeMentor('emonc');

        $this->assertNull($user->allowedProgramIds());
    }

    public function test_returns_null_for_a_role_not_in_the_scoped_list(): void
    {
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, true);

        Role::firstOrCreate(['name' => 'national_mentor_lead', 'guard_name' => 'web']);
        $user = User::factory()->create(['program_scope' => 'emonc']);
        $user->assignRole('national_mentor_lead');

        $this->assertNull($user->allowedProgramIds());
    }

    public function test_returns_null_when_scope_is_both(): void
    {
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, true);
        $user = $this->makeMentor('both');

        $this->assertNull($user->allowedProgramIds());
    }

    public function test_returns_only_the_emonc_program_id_when_scoped_to_emonc(): void
    {
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, true);

        $emonc = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        Program::factory()->create(['name' => 'Newborn Care']);
        Program::factory()->create(['name' => 'Infant and Child Care']);

        $user = $this->makeMentor('emonc');

        $this->assertSame([$emonc->id], $user->allowedProgramIds());
    }

    public function test_returns_only_the_newborn_program_id_when_scoped_to_newborn(): void
    {
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, true);

        Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $newborn = Program::factory()->create(['name' => 'Newborn Care']);
        Program::factory()->create(['name' => 'Infant and Child Care']);

        $user = $this->makeMentor('newborn');

        $this->assertSame([$newborn->id], $user->allowedProgramIds());
    }

    public function test_super_admin_is_never_scoped_even_when_also_holding_a_mentor_tier_role(): void
    {
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, true);

        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        Program::factory()->create(['name' => 'Maternal Health (EmONC)']);

        $user = User::factory()->create(['program_scope' => 'emonc']);
        $user->assignRole(['facility_mentor', 'super_admin']);

        $this->assertNull($user->allowedProgramIds());
    }
}
