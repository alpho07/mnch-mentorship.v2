<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Traits\HasSectionNavigation;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\Cadre;
use App\Services\CadreMatrixSyncService;
use App\Services\DynamicFormBuilder;
use App\Services\DynamicScoringService;
use Filament\Forms;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
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

        // EmONC's Human Resources block has no fixed cadre list — its rows
        // come from the admin-managed Cadre list (category: 'emonc'),
        // which can change any time. Re-synced on every visit so a cadre
        // added or deactivated since the last visit is reflected
        // immediately, without needing to re-run any seeder. A no-op
        // (single indexed WHERE, no writes) for every other section code.
        if ($section->code === 'emonc_facility_context') {
            app(CadreMatrixSyncService::class)->syncMaternityHrQuestions($section);
        }

        $this->authorizeAccess();

        $this->previousUrl = url()->previous();

        $this->form->fill($this->loadSavedResponses());
    }

    protected function loadSavedResponses(): array
    {
        $responses = AssessmentQuestionResponse::where('assessment_id', $this->record->id)
            ->with('question')
            ->get();

        $data = [];

        foreach ($responses as $resp) {
            $fieldName = "question_response_{$resp->assessment_question_id}";
            $responseValue = $resp->response_value;

            // repeater/checkbox/multi_select store their answer as a
            // JSON-encoded array in response_value (see
            // DynamicFormBuilder::saveResponses()) — Filament's
            // Repeater/CheckboxList/multiple-Select need the decoded array
            // as their state, not the raw JSON string, or hydration blows
            // up (or silently shows nothing selected) trying to treat a
            // string as an array.
            if (in_array($resp->question?->question_type, ['repeater', 'checkbox', 'multi_select'], true) && is_string($responseValue)) {
                $decoded = json_decode($responseValue, true);
                $responseValue = is_array($decoded) ? $decoded : [];
            }

            $data[$fieldName] = $responseValue;

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
        $fields = DynamicFormBuilder::buildForSection($this->section->id, $this->record->id);

        if ($this->section->code === 'emonc_facility_context') {
            $fields = $this->insertManageCadresAction($fields);
        }

        $type = $this->record->assessmentType;
        $sectionName = $type ? $type->interpolate($this->section->name) : $this->section->name;
        $sectionDescription = $type ? $type->interpolate($this->section->description) : $this->section->description;

        return $form->schema([
            Forms\Components\View::make('filament.pages.assessment.section-chrome')
                ->viewData(fn () => [
                    'sections' => $this->getAllSections(),
                    'currentKey' => $this->section->code,
                ])
                ->columnSpanFull(),
            Forms\Components\Section::make("{$sectionName} Assessment")
                ->description($sectionDescription)
                ->icon('heroicon-o-clipboard-document-check')
                ->extraAttributes(['class' => 'aqs'])
                ->schema($fields)
                ->columns(1),
        ]);
    }

    /**
     * Splices the "Manage Cadres" action directly above the Human
     * Resources table it affects, rather than in the page header where
     * its connection to that one table (out of everything else on this
     * page) wasn't obvious. Appended at the end if that table isn't
     * present yet (e.g. no active 'emonc' cadres at all, so
     * CadreMatrixSyncService produced no table for buildForSection() to
     * return) — the button must still show, since it's the only way to
     * add the first cadre.
     */
    private function insertManageCadresAction(array $fields): array
    {
        $hrTableIndex = null;

        foreach ($fields as $index => $field) {
            if (method_exists($field, 'getLabel') && $field->getLabel() === 'Human Resources in Maternity Unit') {
                $hrTableIndex = $index;
                break;
            }
        }

        $action = FormActions::make([$this->manageCadresAction()])->columnSpanFull();

        if ($hrTableIndex === null) {
            $fields[] = $action;

            return $fields;
        }

        array_splice($fields, $hrTableIndex, 0, [$action]);

        return $fields;
    }

    /**
     * Assessors need a way to add or retire a cadre without leaving this
     * page — the Human Resources table's rows come from the admin-managed
     * Cadre list (category: 'emonc'). Mirrors EditHumanResources' existing
     * "Manage Cadres" pattern.
     */
    private function manageCadresAction(): FormAction
    {
        return FormAction::make('manage_emonc_cadres')
            ->label('Manage Cadres')
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->modalHeading('Manage Human Resources Cadres')
            ->modalDescription('Uncheck a cadre to remove its row from the table below, or add a new one. Changes apply immediately.')
            ->fillForm(fn () => [
                'active_cadre_ids' => Cadre::category('emonc')->active()->pluck('id')->toArray(),
            ])
            ->form([
                Forms\Components\CheckboxList::make('active_cadre_ids')
                    ->label('Active Cadres')
                    ->options(fn () => Cadre::category('emonc')->ordered()->pluck('name', 'id'))
                    ->columns(2),
                Forms\Components\TextInput::make('new_cadre_name')
                    ->label('Add a new cadre')
                    ->placeholder('e.g. Anaesthetists')
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                app(CadreMatrixSyncService::class)->applyMaternityCadreManagement(
                    $this->section,
                    $data['active_cadre_ids'] ?? [],
                    $data['new_cadre_name'] ?? null,
                );

                Notification::make()
                    ->title('Cadres updated')
                    ->success()
                    ->send();

                $this->redirect(static::getResource()::getUrl('edit-section', [
                    'record' => $this->record->id,
                    'sectionCode' => $this->section->code,
                ]));
            });
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
