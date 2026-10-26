<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Logs in as each role and opens every sidebar item the role can see.
 * A 404/500 means a dead link or a crash. sqlite in-memory only.
 */
class AdminNavigationSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermissions(): void
    {
        $prefixes = config('filament-shield.permission_prefixes.resource');
        $names = collect(config('program_areas.roles'))->flatMap(fn ($d) => array_merge(
            collect($d['resources'])->flatMap(fn ($s) => collect($prefixes)->map(fn ($p) => "{$p}_{$s}"))->all(),
            $d['permissions'],
        ));
        $names = $names->merge(['view_any_user', 'view_user', 'create_user', 'update_user', 'delete_user', 'view_any_scope', 'view_any_role', 'page_MentorshipSettings'])->unique();

        foreach ($names as $n) {
            Permission::firstOrCreate(['name' => $n, 'guard_name' => 'web']);
        }
        foreach (['super_admin', 'admin', 'division', 'mentee', 'facility_mentor', 'county_mentor_lead', 'national_mentor'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Artisan::call('access:sync');
        Role::findByName('facility_mentor')->givePermissionTo(['access_admin_panel', 'page_MyProfile']);
        Role::findByName('mentee')->givePermissionTo(['access_admin_panel', 'page_MyProfile']);
    }

    /** Tables production has but the sqlite migration set doesn't create. */
    private function ensureSqliteGaps(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('approved_training_areas')) {
            \Illuminate\Support\Facades\Schema::create('approved_training_areas', function ($t) {
                $t->id();
                $t->string('name');
                $t->text('description')->nullable();
                $t->boolean('is_active')->default(true);
                $t->integer('sort_order')->default(0);
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public static function roles(): array
    {
        return [['super_admin', null], ['admin', null], ['newborn_child_admin', 'newborn_child'], ['emonc_admin', 'emonc'], ['facility_mentor', null], ['mentee', null]];
    }

    /** @dataProvider roles */
    public function test_every_visible_nav_item_opens(string $role, ?string $area): void
    {
        $this->assertContains(config('database.connections.'.config('database.default').'.database'), [':memory:', 'mnch_test'], 'Refusing to run against a non-test database');
        $this->seedPermissions();
        $this->ensureSqliteGaps();

        $user = User::factory()->create(['name' => "Test $role", 'program_area' => $area, 'status' => 'active']);
        $user->assignRole($role);
        $this->actingAs($user);

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $urls = [];
        foreach ($panel->getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $urls[$item->getLabel()] = $item->getUrl();
            }
        }
        foreach ($panel->getNavigation() as $item) {
            // ungrouped items are returned as groups with no label; covered above.
        }

        $this->assertNotEmpty($urls, "$role sees no navigation at all");

        $bad = [];
        foreach ($urls as $label => $url) {
            if (! $url) {
                continue;
            }
            $response = $this->get(parse_url($url, PHP_URL_PATH).(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : ''));
            $status = $response->getStatusCode();
            if ($status >= 500 || $status === 404) {
                $bad[] = "$label ($url) → $status ".substr((string) $response->exception?->getMessage(), 0, 140);
            }
        }

        $this->assertSame([], $bad, "$role has broken nav links:\n".implode("\n", $bad));
    }

    private function labelsFor(string $role, ?string $area): array
    {
        $this->seedPermissions();
        $u = User::factory()->create(['name' => "Test $role", 'program_area' => $area]);
        $u->assignRole($role);
        $this->actingAs($u);
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        return collect($panel->getNavigation())->flatMap(fn ($g) => $g->getItems())->map->getLabel()->values()->all();
    }

    public function test_newborn_child_admin_menu_matches_the_brief(): void
    {
        $nb = $this->labelsFor('newborn_child_admin', 'newborn_child');

        foreach (['MOH Trainings', 'Mentorship', 'Pending Mentorships', 'Export Center', 'Indicators', 'Programs', 'Program Modules', 'All Users', 'Resources', 'Assessments', 'Executive Brief'] as $l) {
            $this->assertContains($l, $nb, "newborn_child_admin should see $l");
        }
        foreach (['Mentorship Settings', 'Activities', 'Rubric Definitions', 'Practical Assessments', 'Program Module Quizzes', 'Modules', 'RAG Documents', 'Database Management'] as $l) {
            $this->assertNotContains($l, $nb, "newborn_child_admin must not see $l");
        }
    }

    public function test_emonc_admin_menu_matches_the_brief(): void
    {
        $em = $this->labelsFor('emonc_admin', 'emonc');

        foreach (['MOH Trainings', 'Mentorship', 'Pending Mentorships', 'Export Center', 'Program Module Quizzes', 'Activities', 'Rubric Definitions', 'Practical Assessments', 'Programs', 'All Users', 'Resources', 'Assessments'] as $l) {
            $this->assertContains($l, $em, "emonc_admin should see $l");
        }
        foreach (['Indicators', 'Indicator Groups', 'Report Types', 'Executive Brief', 'Mentorship Settings', 'Modules', 'RAG Documents', 'Database Management'] as $l) {
            $this->assertNotContains($l, $em, "emonc_admin must not see $l");
        }
    }

    public function test_admin_menu_excludes_settings_config_knowledge_base_and_shield(): void
    {
        $admin = $this->labelsFor('admin', null);

        foreach (['Mentorship Settings', 'RAG Documents', 'RAG Chat', 'Database Management', 'Roles'] as $l) {
            $this->assertNotContains($l, $admin, "admin must not see $l");
        }
        $this->assertContains('Programs', $admin);
    }
}
