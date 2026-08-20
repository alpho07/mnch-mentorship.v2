<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Traits\GuardsLockedAssessment;
use App\Filament\Resources\AssessmentResource\Traits\HasSectionNavigation;
use App\Models\AssessmentSection;
use App\Models\HumanResourceResponse;
use App\Models\MainCadre;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EditHumanResources extends EditRecord
{
    use HasSectionNavigation;
    use GuardsLockedAssessment;

    protected static string $resource = AssessmentResource::class;

    /**
     * The template's human_resources-kind section — looked up rather than
     * assumed, so this page 404s cleanly if the current assessment's
     * template doesn't include one (at most one is allowed per template,
     * enforced in SectionsRelationManager).
     */
    public AssessmentSection $section;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manage_cadres')
                ->label('Manage Cadres')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading('Manage Cadres for This Assessment')
                ->modalDescription('Select which cadres are present at this facility. Unchecked cadres will be hidden from the form — any data already entered for them is preserved.')
                ->modalSubmitActionLabel('Save Selection')
                ->fillForm(function () {
                    $allCadreIds = MainCadre::where('is_active', true)
                        ->where('assessment_type_id', $this->record->assessment_type_id)
                        ->pluck('id')->toArray();
                    $excludedIds = $this->record->excluded_cadre_ids ?? $this->defaultExcludedCadreIds();

                    return [
                        'included_cadre_ids' => array_values(array_diff($allCadreIds, $excludedIds)),
                    ];
                })
                ->form([
                    Forms\Components\CheckboxList::make('included_cadre_ids')
                        ->label('Cadres')
                        ->helperText('Uncheck any cadre not applicable to this facility.')
                        ->options(fn () => MainCadre::where('is_active', true)
                            ->where('assessment_type_id', $this->record->assessment_type_id)
                            ->orderBy('order')->pluck('name', 'id')->toArray())
                        ->bulkToggleable()
                        ->columns(2),
                ])
                ->action(function (array $data) {
                    $allCadreIds = MainCadre::where('is_active', true)
                        ->where('assessment_type_id', $this->record->assessment_type_id)
                        ->pluck('id')->toArray();
                    $includedIds = array_map('intval', $data['included_cadre_ids'] ?? []);
                    $excludedIds = array_values(array_diff($allCadreIds, $includedIds));

                    // Stored as an explicit array (even when empty) rather than
                    // collapsing to null — null is reserved to mean "assessor
                    // has never customized this," which is what makes the
                    // Others-excluded-by-default behavior below possible. Once
                    // the assessor saves any selection, including "everything",
                    // that explicit choice must stick.
                    $this->record->update(['excluded_cadre_ids' => $excludedIds]);

                    Notification::make()
                        ->title('Cadre selection updated')
                        ->success()
                        ->send();

                    $this->redirect(
                        AssessmentResource::getUrl('edit-human-resources', ['record' => $this->record->id])
                    );
                }),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->abortIfLocked($this->record)) {
            return;
        }

        // Filtered in PHP via resolvedKind() rather than a raw section_type
        // query — that DB column value ('structured_data') is shared with
        // the informational facility_profile/bed_capacity rows, which
        // resolvedKind() correctly excludes and a plain where() wouldn't.
        $section = $this->record->assessmentType
            ?->sections()
            ->where('is_active', true)
            ->get()
            ->first(fn (AssessmentSection $s) => $s->resolvedKind() === 'human_resources');

        if (! $section) {
            throw (new ModelNotFoundException)->setModel(AssessmentSection::class, ['human_resources']);
        }

        $this->section = $section;

        $this->form->fill($this->loadSavedResponses());
    }

    protected function loadSavedResponses(): array
    {
        $responses = HumanResourceResponse::where('assessment_id', $this->record->id)->get();

        $data = [];

        foreach ($responses as $response) {
            $prefix = "hr_{$response->cadre_id}_";
            $data["{$prefix}total_in_facility"] = $response->total_in_facility;
            $data["{$prefix}etat_plus"] = $response->etat_plus;
            $data["{$prefix}comprehensive_newborn_care"] = $response->comprehensive_newborn_care;
            $data["{$prefix}imnci"] = $response->imnci;
            $data["{$prefix}type_1_diabetes"] = $response->type_1_diabetes;
            $data["{$prefix}essential_newborn_care"] = $response->essential_newborn_care;
        }

        return $data;
    }

    /**
     * "Others" is excluded out of the box — it's a catch-all cadre that
     * only applies to some facilities, and defaulting it to visible meant
     * every assessment carried an empty, easy-to-miss section. Assessors
     * add it back manually via "Manage Cadres" when it's actually needed.
     * Only applies while excluded_cadre_ids is null (never customized) —
     * once an assessor saves a selection, their explicit choice wins.
     */
    private function defaultExcludedCadreIds(): array
    {
        return MainCadre::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->where('name', 'Others')
            ->pluck('id')
            ->toArray();
    }

    public function form(Form $form): Form
    {
        $excludedIds = $this->record->excluded_cadre_ids ?? $this->defaultExcludedCadreIds();

        $cadres = MainCadre::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->when(! empty($excludedIds), fn ($q) => $q->whereNotIn('id', $excludedIds))
            ->orderBy('order')
            ->get();

        return $form->schema([
            Forms\Components\View::make('filament.pages.assessment.section-chrome')
                ->viewData(fn () => [
                    'sections' => $this->getAllSections(),
                    'currentKey' => $this->section->code,
                ])
                ->columnSpanFull(),
            Forms\Components\Section::make('Human Resources Assessment')
                ->description('Enter staff training counts for each cadre')
                ->schema(
                    $cadres->map(function ($cadre) {
                        $visibleColumns = array_diff(MainCadre::TRAINING_COLUMNS, $cadre->na_training_columns ?? []);
                        $columnLabels = [
                            'etat_plus' => 'ETAT+',
                            'comprehensive_newborn_care' => 'Comprehensive Newborn Care',
                            'imnci' => 'IMNCI',
                            'type_1_diabetes' => 'Type 1 Diabetes',
                            'essential_newborn_care' => 'Essential Newborn Care',
                        ];

                        $schema = [];

                        if (! $cadre->hidesTotalInFacility()) {
                            $schema[] = Forms\Components\TextInput::make("hr_{$cadre->id}_total_in_facility")
                                ->label('Total Staff in Facility')
                                ->helperText('Total number of this cadre working at the facility')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->required()
                                ->columnSpanFull();
                        }

                        if (! empty($visibleColumns)) {
                            $schema[] = Forms\Components\Placeholder::make("hr_{$cadre->id}_divider")
                                ->label('Trained in '.count($visibleColumns).' Area'.(count($visibleColumns) === 1 ? '' : 's'))
                                ->content($cadre->hidesTotalInFacility()
                                    ? 'Enter the count trained in each programme:'
                                    : 'Enter how many of the total staff above are trained in each programme:')
                                ->columnSpanFull();

                            $schema[] = Forms\Components\Grid::make(count($visibleColumns))
                                ->schema(collect($visibleColumns)->map(fn ($column) => Forms\Components\TextInput::make("hr_{$cadre->id}_{$column}")
                                    ->label($columnLabels[$column])
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required())->all())
                                ->columns(count($visibleColumns));
                        }

                        return Forms\Components\Section::make($cadre->name)
                            ->schema($schema)
                            ->collapsible()
                            ->collapsed(false);
                    })->toArray()
                ),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->haltIfLocked($this->record);

        $cadres = MainCadre::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->get();

        foreach ($cadres as $cadre) {
            $prefix = "hr_{$cadre->id}_";

            // At least one field for an included cadre always renders —
            // total_in_facility, or (for a cadre that hides it, like ToTs)
            // at least one training column — so "any hr_{id}_* key present"
            // reliably distinguishes an included cadre from one excluded
            // via Manage Cadres, whose fields never rendered at all.
            $hasAnyFieldForCadre = collect($data)->keys()->contains(fn ($key) => str_starts_with($key, $prefix));
            if (! $hasAnyFieldForCadre) {
                continue;
            }

            HumanResourceResponse::updateOrCreate(
                [
                    'assessment_id' => $this->record->id,
                    'cadre_id' => $cadre->id,
                ],
                [
                    'total_in_facility' => $cadre->hidesTotalInFacility() ? null : (int) ($data["{$prefix}total_in_facility"] ?? 0),
                    'etat_plus' => $this->trainingColumnValue($cadre, 'etat_plus', $data, $prefix),
                    'comprehensive_newborn_care' => $this->trainingColumnValue($cadre, 'comprehensive_newborn_care', $data, $prefix),
                    'imnci' => $this->trainingColumnValue($cadre, 'imnci', $data, $prefix),
                    'type_1_diabetes' => $this->trainingColumnValue($cadre, 'type_1_diabetes', $data, $prefix),
                    'essential_newborn_care' => $this->trainingColumnValue($cadre, 'essential_newborn_care', $data, $prefix),
                ]
            );
        }

        $progress = $this->record->section_progress ?? [];
        $progress[$this->section->code] = true;
        $this->record->section_progress = $progress;
        $this->record->save();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'hr_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function trainingColumnValue(MainCadre $cadre, string $column, array $data, string $prefix): ?int
    {
        if ($cadre->isColumnNotApplicable($column)) {
            return null;
        }

        return (int) ($data["{$prefix}{$column}"] ?? 0);
    }

    protected function getCurrentSectionKey(): string
    {
        return $this->section->code;
    }

    protected function getSavedNotification(): ?Notification
    {
        $nextSection = $this->getNextSection();

        return Notification::make()
            ->title('Human Resources section saved successfully')
            ->body($nextSection ? "Moving to: {$nextSection}" : 'Returning to dashboard')
            ->success()
            ->duration(3000);
    }

    protected function getNextSection(): ?string
    {
        $sections = $this->getAllSections();
        $currentIndex = array_search($this->section->code, array_keys($sections));
        $sectionKeys = array_keys($sections);

        for ($i = $currentIndex + 1; $i < count($sectionKeys); $i++) {
            if (! $sections[$sectionKeys[$i]]['done']) {
                return $sections[$sectionKeys[$i]]['label'];
            }
        }

        return null;
    }

    public function getTitle(): string
    {
        return "Human Resources - {$this->record->facility->name}";
    }

    public static function getNavigationLabel(): string
    {
        return 'Human Resources';
    }
}
