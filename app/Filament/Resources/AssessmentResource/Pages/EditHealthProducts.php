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
        $responsesByCode = $this->responsesByQuestionCode();

        $departments = AssessmentDepartment::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->orderBy('order')
            ->get()
            ->filter(fn (AssessmentDepartment $dept) => $this->isBlockVisible($dept->display_conditions, $responsesByCode));

        $categories = CommodityCategory::where('assessment_type_id', $this->record->assessment_type_id)
            ->orderBy('order')
            ->get()
            ->filter(fn (CommodityCategory $cat) => $this->isBlockVisible($cat->display_conditions, $responsesByCode));

        return $form->schema([
            Forms\Components\View::make('filament.pages.assessment.section-chrome')
                ->viewData(fn () => [
                    'sections' => $this->getAllSections(),
                    'currentKey' => $this->section->code,
                ])
                ->columnSpanFull(),
            Forms\Components\Tabs::make('Departments')
                ->tabs(
                    $departments->map(function ($dept) use ($categories, $responsesByCode) {
                        return Forms\Components\Tabs\Tab::make($dept->name)
                            ->schema([
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make("save_dept_{$dept->id}")
                                        ->label("Save {$dept->name}")
                                        ->icon('heroicon-o-check-circle')
                                        ->color('success')
                                        ->action(fn () => $this->saveDepartmentTab($dept->id)),
                                ])->columnSpanFull(),
                                ...$this->buildCategorySections($dept, $categories, $responsesByCode),
                            ]);
                    })->toArray()
                )
                ->columnSpanFull()
                ->contained(false),
        ]);
    }

    /**
     * Save just one department's commodities without touching the rest of
     * the form or navigating away — lets an assessor save progress
     * department-by-department instead of always submitting all
     * departments (up to ~800 commodities for a large facility) at once.
     */
    public function saveDepartmentTab(int $departmentId): void
    {
        $payload = $this->data['commodities'][$departmentId] ?? [];

        $this->persistCommodityResponses([$departmentId => $payload]);

        app(\App\Services\CommodityScoringService::class)
            ->recalculateDepartmentScore($this->record->id, $departmentId);

        $departmentName = AssessmentDepartment::find($departmentId)?->name ?? 'Department';

        Notification::make()
            ->title("{$departmentName} saved")
            ->success()
            ->duration(2500)
            ->send();
    }

    private function responsesByQuestionCode(): array
    {
        return \App\Models\AssessmentQuestionResponse::query()
            ->where('assessment_id', $this->record->id)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
            ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
            ->all();
    }

    private function isBlockVisible(?array $conditions, array $responsesByCode): bool
    {
        if (empty($conditions)) {
            return true;
        }

        return \App\Services\ConditionalLogicEvaluator::isVisible(
            $conditions,
            fn (string $code) => $responsesByCode[$code] ?? null
        );
    }

    private function buildCategorySections($dept, $categories, array $responsesByCode): array
    {
        return $categories->map(function ($category) use ($dept, $responsesByCode) {
            $commodities = Commodity::where('commodity_category_id', $category->id)
                ->where('is_active', true)
                ->whereHas('applicableDepartments', function ($q) use ($dept) {
                    $q->where('assessment_department_id', $dept->id);
                })
                ->orderBy('order')
                ->get()
                ->filter(fn (Commodity $c) => $this->isBlockVisible($c->display_conditions, $responsesByCode))
                ->values();

            if ($commodities->isEmpty()) {
                return null;
            }

            $annotated = \App\Services\FormKernel\LineItemGrouper::annotate(
                $commodities->all(),
                fn (Commodity $c) => $c->group_label,
                fn (Commodity $c) => $c->indent_level,
            );

            $rows = [];
            // Numbers each top-level item — a standalone commodity, or a
            // lettered group as one unit (its a)/b)/c) members share the
            // group header's number rather than getting their own) —
            // restarting at 1 for every category, same idea as the
            // checklist modal's per-group item numbering.
            $number = 0;
            foreach ($annotated as ['item' => $commodity, 'letter' => $letter, 'is_group_start' => $isGroupStart]) {
                if ($isGroupStart) {
                    $number++;
                    $rows[] = Forms\Components\Placeholder::make("group_header_{$dept->id}_{$commodity->id}")
                        ->label('')
                        ->content("{$number}. {$commodity->group_label}")
                        ->extraAttributes(['class' => 'font-semibold'])
                        ->columnSpanFull();
                } elseif ($letter === null) {
                    $number++;
                }

                if ($letter !== null) {
                    $label = "{$letter}) {$commodity->name}";
                } elseif ($isGroupStart) {
                    $label = $commodity->name;
                } else {
                    $label = "{$number}. {$commodity->name}";
                }
                $rowStyle = $commodity->indent_level > 0 ? ['style' => 'margin-left: 1.5rem;'] : [];

                $rows[] = Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Placeholder::make("label_{$dept->id}_{$commodity->id}")
                            ->label('')
                            ->content($label)
                            ->extraAttributes($rowStyle)
                            ->columnSpan(1),
                        Forms\Components\ToggleButtons::make("commodities.{$dept->id}.{$commodity->id}")
                            ->label('')
                            ->options([
                                1 => 'Available',
                                0 => 'Not Available',
                            ])
                            ->colors([
                                1 => 'success',
                                0 => 'danger',
                            ])
                            ->icons([
                                1 => 'heroicon-o-check-circle',
                                0 => 'heroicon-o-x-circle',
                            ])
                            ->inline()
                            ->columnSpan(1),
                    ])
                    ->columns(2);
            }

            return Forms\Components\Section::make($category->name)
                ->description("({$commodities->count()} items)")
                ->schema([Forms\Components\Grid::make(2)->schema($rows)])
                ->collapsible();
        })->filter()->values()->toArray();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $payload = $data['commodities'] ?? [];

        $this->persistCommodityResponses($payload);

        foreach (array_keys($payload) as $departmentId) {
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

    /**
     * Bulk-upserts commodity responses in one query instead of one
     * updateOrCreate() per commodity. AssessmentCommodityResponse's
     * `saved` model event recalculates the whole department's score on
     * every row — fine for a single live-form toggle, but with hundreds
     * of commodities saved together that meant a full department rescore
     * per row instead of once per department, which is what turned a
     * single Save click into a 30-second-plus timeout. upsert() bypasses
     * Eloquent events entirely, so the score column is set here to match
     * what the model's `saving` event would have computed.
     *
     * @param  array<int, array<int, mixed>>  $payload  [departmentId => [commodityId => 1|0|'na']]
     */
    private function persistCommodityResponses(array $payload): void
    {
        $now = now();
        $rows = [];

        foreach ($payload as $departmentId => $commodityEntries) {
            foreach ($commodityEntries as $commodityId => $value) {
                $isNotApplicable = $value === 'na';
                $available = $isNotApplicable ? false : (bool) $value;

                $rows[] = [
                    'assessment_id' => $this->record->id,
                    'assessment_department_id' => $departmentId,
                    'commodity_id' => $commodityId,
                    'available' => $available,
                    'not_applicable' => $isNotApplicable,
                    'score' => ($isNotApplicable || ! $available) ? 0 : 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        AssessmentCommodityResponse::upsert(
            $rows,
            ['assessment_id', 'commodity_id', 'assessment_department_id'],
            ['available', 'not_applicable', 'score', 'updated_at']
        );
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
