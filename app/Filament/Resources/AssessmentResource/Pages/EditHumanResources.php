<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
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
                    $allCadreIds = MainCadre::where('is_active', true)->pluck('id')->toArray();
                    $excludedIds = $this->record->excluded_cadre_ids ?? [];

                    return [
                        'included_cadre_ids' => array_values(array_diff($allCadreIds, $excludedIds)),
                    ];
                })
                ->form([
                    Forms\Components\CheckboxList::make('included_cadre_ids')
                        ->label('Cadres')
                        ->helperText('Uncheck any cadre not applicable to this facility.')
                        ->options(fn () => MainCadre::where('is_active', true)->orderBy('order')->pluck('name', 'id')->toArray())
                        ->bulkToggleable()
                        ->columns(2),
                ])
                ->action(function (array $data) {
                    $allCadreIds = MainCadre::where('is_active', true)->pluck('id')->toArray();
                    $includedIds = array_map('intval', $data['included_cadre_ids'] ?? []);
                    $excludedIds = array_values(array_diff($allCadreIds, $includedIds));

                    $this->record->update(['excluded_cadre_ids' => $excludedIds ?: null]);

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

    public function form(Form $form): Form
    {
        $excludedIds = $this->record->excluded_cadre_ids ?? [];

        $cadres = MainCadre::where('is_active', true)
            ->when(! empty($excludedIds), fn ($q) => $q->whereNotIn('id', $excludedIds))
            ->orderBy('order')
            ->get();

        return $form->schema([
            Forms\Components\Section::make('Human Resources Assessment')
                ->description('Enter staff training counts for each cadre')
                ->schema(
                    $cadres->map(function ($cadre) {
                        return Forms\Components\Section::make($cadre->name)
                            ->schema([
                                // Total staff row — spans full width, visually distinct
                                Forms\Components\TextInput::make("hr_{$cadre->id}_total_in_facility")
                                    ->label('Total Staff in Facility')
                                    ->helperText('Total number of this cadre working at the facility')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required()
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make("hr_{$cadre->id}_divider")
                                    ->label('Trained in 5 Areas')
                                    ->content('Enter how many of the total staff above are trained in each programme:')
                                    ->columnSpanFull(),
                                Forms\Components\Grid::make(5)
                                    ->schema([
                                        Forms\Components\TextInput::make("hr_{$cadre->id}_etat_plus")
                                            ->label('ETAT+')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),

                                        Forms\Components\TextInput::make("hr_{$cadre->id}_comprehensive_newborn_care")
                                            ->label('Comprehensive Newborn Care')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),

                                        Forms\Components\TextInput::make("hr_{$cadre->id}_imnci")
                                            ->label('IMNCI')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),

                                        Forms\Components\TextInput::make("hr_{$cadre->id}_type_1_diabetes")
                                            ->label('Type 1 Diabetes')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),

                                        Forms\Components\TextInput::make("hr_{$cadre->id}_essential_newborn_care")
                                            ->label('Essential Newborn Care')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                    ])
                                    ->columns(5),
                            ])
                            ->collapsible()
                            ->collapsed(false);
                    })->toArray()
                ),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $cadres = MainCadre::where('is_active', true)->get();

        foreach ($cadres as $cadre) {
            $prefix = "hr_{$cadre->id}_";

            // Check if any field exists for this cadre
            if (! isset($data["{$prefix}etat_plus"])) {
                continue;
            }

            HumanResourceResponse::updateOrCreate(
                [
                    'assessment_id' => $this->record->id,
                    'cadre_id' => $cadre->id,
                ],
                [
                    'total_in_facility' => (int) ($data["{$prefix}total_in_facility"] ?? 0),
                    'etat_plus' => (int) ($data["{$prefix}etat_plus"] ?? 0),
                    'comprehensive_newborn_care' => (int) ($data["{$prefix}comprehensive_newborn_care"] ?? 0),
                    'imnci' => (int) ($data["{$prefix}imnci"] ?? 0),
                    'type_1_diabetes' => (int) ($data["{$prefix}type_1_diabetes"] ?? 0),
                    'essential_newborn_care' => (int) ($data["{$prefix}essential_newborn_care"] ?? 0),
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
