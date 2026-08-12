<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource\Pages\CreateSurvey;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey', 'create_survey', 'update_survey'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey', 'create_survey', 'update_survey']);
        $this->actingAs($user);

        return $user;
    }

    public function test_a_survey_can_be_created_through_the_resource_form(): void
    {
        $this->actingAdmin();

        Livewire::test(CreateSurvey::class)
            ->fillForm([
                'name' => 'Training Feedback',
                'code' => 'TRAINING_FEEDBACK',
                'version' => '1.0',
                'is_active' => true,
                'is_public' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('surveys', ['code' => 'TRAINING_FEEDBACK', 'name' => 'Training Feedback']);
    }

    public function test_get_link_action_generates_a_token_for_a_public_survey(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'PUBLIC_TEST', 'name' => 'Public Test', 'is_active' => true, 'is_public' => true]);

        $this->assertNull($survey->access_token);

        \Livewire\Livewire::test(\App\Filament\Resources\SurveyResource\Pages\ListSurveys::class)
            ->callTableAction('get_link', $survey);

        $this->assertNotNull($survey->fresh()->access_token);
    }
}
