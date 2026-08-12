<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Services\FormKernel\QuestionFieldBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionFieldBuilderMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function makeMatrixQuestion(): SurveyQuestion
    {
        $survey = Survey::create(['code' => 'MATRIX_TEST', 'name' => 'Matrix', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        return SurveyQuestion::create([
            'survey_section_id' => $section->id,
            'question_code' => 'MATRIX_Q1',
            'question_text' => 'Rate the training session',
            'question_type' => 'matrix',
            'options' => [
                'columns' => ['Disagree', 'Neutral', 'Agree'],
                'rows' => [
                    ['key' => 'clarity', 'label' => 'The instructions were clear'],
                    ['key' => 'pace', 'label' => 'The pace was right'],
                ],
            ],
        ])->fresh();
    }

    public function test_matrix_field_builds_one_radio_group_per_row_sharing_the_column_options(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeMatrixQuestion(), null);

        $this->assertInstanceOf(\Filament\Forms\Components\Group::class, $field);

        $radios = collect($field->getChildComponents())
            ->filter(fn ($c) => $c instanceof \Filament\Forms\Components\Radio);

        $this->assertCount(2, $radios);
        $this->assertTrue($radios->every(fn ($r) => $r->getOptions() === ['Disagree' => 'Disagree', 'Neutral' => 'Neutral', 'Agree' => 'Agree']));
    }

    public function test_matrix_field_prefills_existing_per_row_answers(): void
    {
        $question = $this->makeMatrixQuestion();
        $survey = Survey::find($question->section->survey_id);
        $surveyResponse = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);
        $existing = SurveyQuestionResponse::create([
            'survey_response_id' => $surveyResponse->id,
            'survey_question_id' => $question->id,
            'response_value' => json_encode(['clarity' => 'Agree', 'pace' => 'Neutral']),
        ]);

        $field = QuestionFieldBuilder::buildField($question, $existing);

        $clarityRadio = collect($field->getChildComponents())
            ->first(fn ($c) => $c instanceof \Filament\Forms\Components\Radio && str_ends_with($c->getName(), '_clarity'));

        $this->assertSame('Agree', $clarityRadio->getDefaultState());
    }
}
