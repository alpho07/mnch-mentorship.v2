<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorshipStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorshipStatsByRoleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $attrs = []): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $u = User::factory()->create($attrs);
        $u->assignRole($role);

        return $u;
    }

    public function test_live_mentorship_counts_per_role(): void
    {
        // Production safety: never RefreshDatabase against MySQL.
        $this->assertSame('sqlite', config('database.default'));

        $nb = Program::factory()->create(['name' => 'Newborn Care', 'program_area' => 'newborn_child']);
        $em = Program::factory()->create(['name' => 'Maternal Health (EmONC)', 'program_area' => 'emonc']);

        $mentor = $this->user('facility_mentor');
        $mk = fn (Program $p, string $area, bool $pilot, ?int $mentorId = null) => Training::factory()->create([
            'type' => 'facility_mentorship', 'program_id' => $p->id, 'program_area' => $area,
            'is_pilot' => $pilot, 'mentor_id' => $mentorId ?? User::factory()->create()->id,
        ]);

        $mk($nb, 'newborn_child', false, $mentor->id);
        $mk($nb, 'newborn_child', false);
        $mk($em, 'emonc', false);
        $mk($em, 'emonc', false);
        $mk($em, 'emonc', true); // pilot — never counted

        $svc = app(MentorshipStatsService::class);

        $this->assertSame(4, $svc->overallStats($this->user('super_admin'))['mentorships']);
        $this->assertSame(4, $svc->overallStats($this->user('admin'))['mentorships']);
        $this->assertSame(2, $svc->overallStats($this->user('newborn_child_admin', ['program_area' => 'newborn_child']))['mentorships']);
        $emonc = $this->user('emonc_admin', ['program_area' => 'emonc']);
        $this->assertSame(2, $svc->overallStats($emonc)['mentorships']);
        $this->assertSame(['Maternal Health (EmONC)'], array_column($svc->programStats($emonc), 'name'));
        // Mentors keep seeing only their own.
        $this->assertSame(1, $svc->overallStats($mentor)['mentorships']);
    }
}
