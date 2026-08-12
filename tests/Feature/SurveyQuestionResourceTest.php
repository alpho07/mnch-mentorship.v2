<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyQuestionResource\Pages\CreateSurveyQuestion;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyQuestionResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey::question', 'create_survey::question'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey::question', 'create_survey::question']);
        $this->actingAs($user);

        return $user;
    }

    public function test_a_simple_question_can_be_created_through_the_resource_form(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'SQR_TEST', 'name' => 'SQR Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        Livewire::test(CreateSurveyQuestion::class)
            ->fillForm([
                'survey_section_id' => $section->id,
                'question_code' => 'SQR_Q1',
                'question_text' => 'Did you enjoy the training?',
                'question_type' => 'yes_no',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('survey_questions', ['question_code' => 'SQR_Q1', 'question_type' => 'yes_no']);
    }

    public function test_a_matrix_question_persists_structured_json_options_from_the_raw_editor(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'SQR_MATRIX', 'name' => 'SQR Matrix', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        Livewire::test(CreateSurveyQuestion::class)
            ->fillForm([
                'survey_section_id' => $section->id,
                'question_code' => 'SQR_MX1',
                'question_text' => 'Rate the session',
                'question_type' => 'matrix',
                'options_json' => json_encode(['columns' => ['Agree', 'Disagree'], 'rows' => [['key' => 'r1', 'label' => 'Row 1']]]),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $question = SurveyQuestion::where('question_code', 'SQR_MX1')->firstOrFail();
        $this->assertSame(['Agree', 'Disagree'], $question->options['columns']);
    }
}
