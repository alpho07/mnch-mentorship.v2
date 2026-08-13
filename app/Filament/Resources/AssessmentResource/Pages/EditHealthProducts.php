<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Traits\HasSectionNavigation;
use App\Models\AssessmentCommodityResponse;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentSection;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EditHealthProducts extends EditRecord
{
    use HasSectionNavigation;

    protected static string $resource = AssessmentResource::class;

    /**
     * The template's commodity_matrix-kind section — looked up rather than
     * assumed, so this page 404s cleanly if the current assessment's
     * template doesn't include one (at most one is allowed per template,
     * enforced in SectionsRelationManager).
     */
    public AssessmentSection $section;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Filtered in PHP via resolvedKind() for consistency with
        // EditHumanResources — commodity_matrix isn't actually ambiguous
        // today, but this avoids the same class of bug if that ever changes.
        $section = $this->record->assessmentType
            ?->sections()
            ->where('is_active', true)
            ->get()
            ->first(fn (AssessmentSection $s) => $s->resolvedKind() === 'commodity_matrix');

        if (! $section) {
            throw (new ModelNotFoundException)->setModel(AssessmentSection::class, ['health_products']);
        }

        $this->section = $section;

        $this->form->fill($this->loadSavedResponses());
    }

    protected function loadSavedResponses(): array
    {
        $responses = AssessmentCommodityResponse::where('assessment_id', $this->record->id)->get();

        $data = ['commodities' => []];

        foreach ($responses as $response) {
            $data['commodities'][$response->assessment_department_id][$response->commodity_id] = $response->not_applicable
                ? 'na'
                : ($response->available ? 1 : 0);
        }

        return $data;
    }

    public function form(Form $form): Form
    {
        $departments = AssessmentDepartment::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->orderBy('order')
            ->get();

        $categories = CommodityCategory::where('assessment_type_id', $this->record->assessment_type_id)
            ->orderBy('order')
            ->get();

        return $form->schema([
            Forms\Components\Tabs::make('Departments')
                ->tabs(
                    $departments->map(function ($dept) use ($categories) {
                        return Forms\Components\Tabs\Tab::make($dept->name)
                            ->schema($this->buildCategorySections($dept, $categories));
                    })->toArray()
                )
                ->columnSpanFull()
                ->contained(false),
        ]);
    }

    private function buildCategorySections($dept, $categories): array
    {
        return $categories->map(function ($category) use ($dept) {
            $commodities = Commodity::where('commodity_category_id', $category->id)
                ->where('is_active', true)
                ->whereHas('applicableDepartments', function ($q) use ($dept) {
                    $q->where('assessment_department_id', $dept->id);
                })
                ->orderBy('order')
                ->get();

            if ($commodities->isEmpty()) {
                return null;
            }

            return Forms\Components\Section::make($category->name)
                ->description("({$commodities->count()} items)")
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema($commodities->map(function ($commodity) use ($dept) {
                            return Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Placeholder::make("label_{$dept->id}_{$commodity->id}")
                                        ->label('')
                                        ->content($commodity->name)
                                        ->columnSpan(1),
                                    Forms\Components\ToggleButtons::make("commodities.{$dept->id}.{$commodity->id}")
                                        ->label('')
                                        ->options([
                                            1 => 'Available',
                                            0 => 'Not Available',
                                            'na' => 'Not Applicable',
                                        ])
                                        ->colors([
                                            1 => 'success',
                                            0 => 'danger',
                                            'na' => 'gray',
                                        ])
                                        ->icons([
                                            1 => 'heroicon-o-check-circle',
                                            0 => 'heroicon-o-x-circle',
                                            'na' => 'heroicon-o-minus-circle',
                                        ])
                                        ->inline()
                                        ->columnSpan(1),
                                ])
                                ->columns(2);
                        })->toArray()),
                ])
                ->collapsible();
        })->filter()->values()->toArray();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $payload = $data['commodities'] ?? [];

        foreach ($payload as $departmentId => $commodityEntries) {
            foreach ($commodityEntries as $commodityId => $value) {
                $isNotApplicable = $value === 'na';

                AssessmentCommodityResponse::updateOrCreate(
                    [
                        'assessment_id' => $this->record->id,
                        'assessment_department_id' => $departmentId,
                        'commodity_id' => $commodityId,
                    ],
                    [
                        'available' => $isNotApplicable ? false : (bool) $value,
                        'not_applicable' => $isNotApplicable,
                    ]
                );
            }

            app(\App\Services\CommodityScoringService::class)
                ->recalculateDepartmentScore($this->record->id, $departmentId);
        }

        $progress = $this->record->section_progress ?? [];
        $progress[$this->section->code] = true;
        $this->record->section_progress = $progress;
        $this->record->save();

        unset($data['commodities']);

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
            ->title('Health Products section saved successfully')
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
        return "Health Products - {$this->record->facility->name}";
    }
}
