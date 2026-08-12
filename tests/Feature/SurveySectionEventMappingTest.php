<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource\RelationManagers\SectionsRelationManager;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveySectionEventMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_section_can_attach_it_to_specific_events(): void
    {
        $user = User::factory()->create();
        foreach (['view_any_survey', 'update_survey'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey', 'update_survey']);
        $this->actingAs($user);

        $survey = Survey::create(['code' => 'SECTION_EVENT_MAP_TEST', 'name' => 'Section Event Map Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'demographics', 'name' => 'Demographics', 'order' => 1]);

        Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $survey,
            'pageClass' => \App\Filament\Resources\SurveyResource\Pages\EditSurvey::class,
        ])
            ->callTableAction('edit', $section, data: [
                'name' => 'Demographics',
                'code' => 'demographics',
                'order' => 1,
                'is_scored' => true,
                'is_active' => true,
                'events' => [$baseline->id],
            ]);

        $this->assertSame([$baseline->id], $section->fresh()->events->pluck('id')->all());
    }
}
