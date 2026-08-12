<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Services\FormKernel\QuestionFieldBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionFieldBuilderFileSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuestion(string $type): SurveyQuestion
    {
        $survey = Survey::create(['code' => 'FS_'.$type, 'name' => 'File/Signature', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        return SurveyQuestion::create([
            'survey_section_id' => $section->id,
            'question_code' => 'FS_'.strtoupper($type),
            'question_text' => "A {$type} question",
            'question_type' => $type,
        ])->fresh();
    }

    public function test_file_upload_field_uses_the_public_disk(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('file_upload'), null);

        $this->assertInstanceOf(\Filament\Forms\Components\FileUpload::class, $field);
        $this->assertSame('public', $field->getDiskName());
        $this->assertSame('survey-uploads', $field->getDirectory());
    }

    public function test_signature_field_renders_the_signature_pad_view(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('signature'), null);

        $this->assertInstanceOf(\Filament\Forms\Components\ViewField::class, $field);
        $this->assertSame('filament.forms.components.signature-pad', $field->getView());
    }
}
