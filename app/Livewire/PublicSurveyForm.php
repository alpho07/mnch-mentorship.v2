<?php

namespace App\Livewire;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Services\SurveyFormBuilder;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;

class PublicSurveyForm extends Component implements HasForms
{
    use InteractsWithForms;

    public int $surveyId;

    public ?array $data = [];

    public bool $submitted = false;

    public function mount(int $surveyId): void
    {
        $this->surveyId = $surveyId;
        $this->form->fill();
    }

    protected function getSurvey(): Survey
    {
        return Survey::findOrFail($this->surveyId);
    }

    public function form(Form $form): Form
    {
        $respondentSection = Forms\Components\Section::make('Your Details')
            ->schema([
                Forms\Components\TextInput::make('respondent_name')->label('Name')->maxLength(255),
                Forms\Components\TextInput::make('respondent_email')->label('Email')->email()->maxLength(255),
                Forms\Components\TextInput::make('respondent_contact')->label('Phone')->tel()->maxLength(255),
            ]);

        return $form
            ->schema([$respondentSection, ...SurveyFormBuilder::buildForSurvey($this->getSurvey())])
            ->statePath('data');
    }

    public function submit(): void
    {
        $survey = $this->getSurvey();
        $formData = $this->form->getState();

        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_name' => $formData['respondent_name'] ?? null,
            'respondent_email' => $formData['respondent_email'] ?? null,
            'respondent_contact' => $formData['respondent_contact'] ?? null,
            'status' => 'draft',
        ]);

        foreach ($survey->sections()->active()->get() as $section) {
            SurveyFormBuilder::saveResponses($response->id, $section->id, $formData);
        }

        $response->markSubmitted();

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.public-survey-form');
    }
}
