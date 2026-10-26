<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use App\Support\ProgramArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramAreaAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_admin_is_confined_and_admin_is_not(): void
    {
        // Production safety: never run RefreshDatabase against MySQL.
        $this->assertSame('sqlite', config('database.default'));

        foreach (['newborn_child_admin', 'emonc_admin', 'admin', 'mentee'] as $r) {
            Role::create(['name' => $r, 'guard_name' => 'web']);
        }
        $nb = Program::create(['name' => 'Newborn Care', 'program_area' => 'newborn_child']);
        Program::create(['name' => 'Maternal Health (EmONC)', 'program_area' => 'emonc']);

        $nbAdmin = User::factory()->create(['program_area' => 'newborn_child']);
        $nbAdmin->assignRole('newborn_child_admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertSame('newborn_child', ProgramArea::forUser($nbAdmin));
        $this->assertNull(ProgramArea::forUser($admin));
        $this->assertSame([$nb->id], $nbAdmin->allowedProgramIds());
        $this->assertTrue($nbAdmin->isAboveSite());

        $mentee = User::factory()->create();
        $mentee->assignRole('mentee');
        $emUser = User::factory()->create(['program_area' => 'emonc']);
        $emUser->assignRole('emonc_admin');

        $visible = ProgramArea::scopeUsers(User::query(), $nbAdmin)->pluck('id')->all();
        $this->assertContains($mentee->id, $visible);
        $this->assertContains($nbAdmin->id, $visible);
        $this->assertNotContains($emUser->id, $visible);
        $this->assertTrue(ProgramArea::requiresArea(['mentee', 'county']));
        $this->assertFalse(ProgramArea::requiresArea(['mentee', 'facility_mentor']));
    }
}
