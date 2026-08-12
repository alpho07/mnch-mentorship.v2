<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource\RelationManagers\EventsRelationManager;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyEventsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_event_can_be_created_through_the_relation_manager(): void
    {
        $user = User::factory()->create();
        foreach (['view_any_survey', 'update_survey'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey', 'update_survey']);
        $this->actingAs($user);

        $survey = Survey::create(['code' => 'EVENTS_RM_TEST', 'name' => 'Events RM Test', 'is_active' => true]);

        Livewire::test(EventsRelationManager::class, [
            'ownerRecord' => $survey,
            'pageClass' => \App\Filament\Resources\SurveyResource\Pages\EditSurvey::class,
        ])
            ->callTableAction('create', data: [
                'code' => 'baseline',
                'name' => 'Baseline',
                'order' => 1,
                'repeatable' => false,
            ]);

        $this->assertDatabaseHas('survey_events', [
            'survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'repeatable' => false,
        ]);
    }
}
