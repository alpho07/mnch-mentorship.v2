<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Services\FormKernel\QuestionFieldBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionFieldBuilderNewTypesTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuestion(string $type, array $overrides = []): SurveyQuestion
    {
        $survey = Survey::create(['code' => 'NEWTYPES_'.$type, 'name' => 'New Types', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        return SurveyQuestion::create(array_merge([
            'survey_section_id' => $section->id,
            'question_code' => 'NT_'.strtoupper($type),
            'question_text' => "A {$type} question",
            'question_type' => $type,
        ], $overrides))->fresh();
    }

    public function test_date_field_builds_a_date_picker(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('date'), null);

        $this->assertInstanceOf(\Filament\Forms\Components\DatePicker::class, $field);
    }

    /**
     * Pure informational text (e.g. a title placed before a group of
     * questions) — a Placeholder, not an input, so it never has anything
     * to submit back.
     */
    public function test_heading_field_builds_a_placeholder_with_no_input(): void
    {
        $question = $this->makeQuestion('heading', ['question_text' => 'Section title text']);

        $field = QuestionFieldBuilder::buildField($question, null);

        $this->assertInstanceOf(\Filament\Forms\Components\Placeholder::class, $field);
        $this->assertSame('Section title text', $field->getContent());
    }

    public function test_datetime_field_builds_a_datetime_picker(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('datetime'), null);

        $this->assertInstanceOf(\Filament\Forms\Components\DateTimePicker::class, $field);
    }

    public function test_email_field_enables_email_validation(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('email'), null);

        $this->assertSame('email', $field->getType());
    }

    public function test_phone_field_enables_tel_validation(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('phone'), null);

        $this->assertSame('tel', $field->getType());
    }

    public function test_checkbox_field_builds_a_checkbox_list_with_options(): void
    {
        $question = $this->makeQuestion('checkbox', ['options' => ['Red', 'Green', 'Blue']]);

        $field = QuestionFieldBuilder::buildField($question, null);

        $this->assertInstanceOf(\Filament\Forms\Components\CheckboxList::class, $field);
        $this->assertSame(['Red' => 'Red', 'Green' => 'Green', 'Blue' => 'Blue'], $field->getOptions());
    }

    public function test_rating_field_builds_options_up_to_configured_max(): void
    {
        $question = $this->makeQuestion('rating', ['validation_rules' => ['max' => 3]]);

        $field = QuestionFieldBuilder::buildField($question, null);

        $this->assertInstanceOf(\Filament\Forms\Components\Radio::class, $field);
        $this->assertSame(['1' => '1', '2' => '2', '3' => '3'], $field->getOptions());
    }
}
