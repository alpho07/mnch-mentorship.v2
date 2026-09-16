<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Traits\HasSectionNavigation;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Services\DynamicFormBuilder;
use App\Services\DynamicScoringService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Replaces EditInfrastructure/EditSkillsLab/EditInformationSystems/
 * EditQualityOfCare — those 4 pages were identical except for a hardcoded
 * section code and title. Any "question_group"-kind section, on any
 * template, renders and saves through this one page instead, parameterized
 * by the section's code in the URL.
 */
class EditSection extends EditRecord
{
    use HasSectionNavigation;

    protected static string $resource = AssessmentResource::class;

    public AssessmentSection $section;

    /**
     * Deliberately does NOT call parent::mount(): EditRecord's version
     * resolves the record and immediately calls fillForm() -> form(), but
     * this page's form() needs $this->section, which isn't known yet at
     * that point — calling it would access the property before
     * initialization. Record resolution, section resolution, and the
     * authorization check are replicated here in the right order instead.
     */
    public function mount(int|string $record): void
    {
        // Scoped via the resource's own getEloquentQuery() — an assessor
        // can't reach another assessor's section-edit page directly by URL.
        $this->record = AssessmentResource::getEloquentQuery()->findOrFail($record);

        $sectionCode = request()->route('sectionCode');

        $section = $this->record->assessmentType
            ?->sections()
            ->where('code', $sectionCode)
            ->where('is_active', true)
            ->first();

        if (! $section || $section->resolvedKind() !== 'question_group') {
            throw (new ModelNotFoundException)->setModel(AssessmentSection::class, [$sectionCode]);
        }

        $this->section = $section;

        $this->authorizeAccess();

        $this->previousUrl = url()->previous();

        $this->form->fill($this->loadSavedResponses());
    }

    protected function loadSavedResponses(): array
    {
        $responses = AssessmentQuestionResponse::where('assessment_id', $this->record->id)->get();

        $data = [];

        foreach ($responses as $resp) {
            $fieldName = "question_response_{$resp->assessment_question_id}";
            $data[$fieldName] = $resp->response_value;

            if ($resp->explanation) {
                $data[$fieldName.'_explanation'] = $resp->explanation;
            }

            if ($resp->metadata) {
                foreach ($resp->metadata as $key => $value) {
                    if ($key === 'positive_count') {
                        $data["{$fieldName}_positive_count"] = $value;
                    } elseif ($key === 'sample_size') {
                        $data["{$fieldName}_sample_size"] = $value;
                    } elseif ($key === 'calculated_proportion') {
                        $data["{$fieldName}_proportion"] = $value;
                    } else {
                        $data["{$fieldName}_{$key}"] = $value;
                    }
                }
            }
        }

        return $data;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("{$this->section->name} Assessment")
                ->description($this->section->description)
                ->schema(
                    DynamicFormBuilder::buildForSection($this->section->id, $this->record->id)
                )
                ->columns(1),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        DynamicFormBuilder::saveResponses($this->record->id, $this->section->id, $data);
        DynamicScoringService::recalculateSectionScore($this->record->id, $this->section->id);

        $progress = $this->record->section_progress ?? [];
        $progress[$this->section->code] = true;
        $this->record->section_progress = $progress;
        $this->record->save();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'question_response_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function getCurrentSectionKey(): string
    {
        return $this->section->code;
    }

    protected function getSavedNotification(): ?Notification
    {
        $nextSection = $this->getNextSection();

        return Notification::make()
            ->title("{$this->section->name} section saved successfully")
            ->body($nextSection ? "Moving to: {$nextSection}" : 'Returning to dashboard')
            ->success()
            ->duration(3000);
    }

    protected function getNextSection(): ?string
    {
        $sections = $this->getAllSections();
        $sectionKeys = array_keys($sections);
        $currentIndex = array_search($this->section->code, $sectionKeys);

        if ($currentIndex === false) {
            return null;
        }

        for ($i = $currentIndex + 1; $i < count($sectionKeys); $i++) {
            if (! $sections[$sectionKeys[$i]]['done']) {
                return $sections[$sectionKeys[$i]]['label'];
            }
        }

        return null;
    }

    public function getTitle(): string
    {
        // $this->section may still be uninitialized if this is called while
        // Laravel is rendering the error response for a ModelNotFoundException
        // thrown earlier in mount() — stay defensive rather than compounding
        // that into an uncaught "must not be accessed before initialization".
        if (! isset($this->section)) {
            return 'Assessment Section';
        }

        return "{$this->section->name} - {$this->record->facility->name}";
    }
}
