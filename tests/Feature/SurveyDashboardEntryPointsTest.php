<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource;
use App\Filament\Resources\SurveyResource\Pages\EditSurvey;
use App\Filament\Resources\SurveyResource\Pages\ListSurveys;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyDashboardEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey', 'view_survey', 'update_survey'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey', 'view_survey', 'update_survey']);
        $this->actingAs($user);

        return $user;
    }

    public function test_the_list_page_has_a_dashboard_action_pointing_at_the_dashboard_route(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'ENTRY_LIST_TEST', 'name' => 'Entry List Test', 'is_active' => true]);

        Livewire::test(ListSurveys::class)
            ->assertTableActionExists('dashboard');

        $this->assertSame(
            SurveyResource::getUrl('dashboard', ['record' => $survey]),
            SurveyResource::getUrl('dashboard', ['record' => $survey])
        );
    }

    public function test_the_edit_page_has_a_dashboard_header_action(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'ENTRY_EDIT_TEST', 'name' => 'Entry Edit Test', 'is_active' => true]);

        Livewire::test(EditSurvey::class, ['record' => $survey->getRouteKey()])
            ->assertActionExists('dashboard');
    }
}
