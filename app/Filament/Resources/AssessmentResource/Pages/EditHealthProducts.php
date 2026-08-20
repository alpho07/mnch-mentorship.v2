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
use Illuminate\Support\Collection;

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

    /**
     * Only this department's commodities are ever queried, built into form
     * fields, or sent over the wire — a large facility's full commodity
     * matrix is up to ~800 fields, and Filament's Tabs render every tab's
     * content into the same Livewire component regardless of which is
     * active, so keeping all departments in one form made every load and
     * every save move the whole payload. One department per request/save
     * (switched via ?dept=<slug>, a normal page load) keeps both
     * proportional to a single department's size instead of the facility's
     * total.
     */
    public AssessmentDepartment $activeDepartment;

    /**
     * Public (not protected) so Livewire actually dehydrates/rehydrates it
     * across requests — mount() only runs on the initial full page load,
     * not on subsequent AJAX action calls like the per-department "Save"
     * button, and Livewire only restores public properties on those.
     *
     * @var Collection<int, AssessmentDepartment>
     */
    public Collection $visibleDepartments;

    private ?array $responsesByCodeCache = null;

    public function mount(int|string $record): void
    {
        // Not delegated to parent::mount(): it resolves the record, then
        // immediately calls fillForm(), which builds the form schema (i.e.
        // calls form() below) before control ever returns here. form()
        // reads $this->activeDepartment directly (not just inside deferred
        // closures — e.g. the "Save {department}" action label), so it must
        // already be set by the time fillForm() runs. Resolving the record
        // and department first, then filling the form ourselves, is
        // equivalent to parent::mount() with that setup spliced in before
        // the fill step.
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();

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

        $responsesByCode = $this->responsesByQuestionCode();

        $this->visibleDepartments = AssessmentDepartment::where('is_active', true)
            ->where('assessment_type_id', $this->record->assessment_type_id)
            ->orderBy('order')
            ->get()
            ->filter(fn (AssessmentDepartment $dept) => $this->isBlockVisible($dept->display_conditions, $responsesByCode))
            ->values();

        if ($this->visibleDepartments->isEmpty()) {
            throw (new ModelNotFoundException)->setModel(AssessmentDepartment::class);
        }

        $requestedSlug = request()->query('dept');

        $this->activeDepartment = $this->visibleDepartments
            ->first(fn (AssessmentDepartment $d) => $d->slug === $requestedSlug)
            ?? $this->visibleDepartments->first();

        $this->form->fill($this->loadSavedResponses());

        $this->previousUrl = url()->previous();
    }

    protected function loadSavedResponses(): array
    {
        $responses = AssessmentCommodityResponse::where('assessment_id', $this->record->id)
            ->where('assessment_department_id', $this->activeDepartment->id)
            ->get();

        $data = ['commodities' => [$this->activeDepartment->id => []], 'commodities_quantity' => [$this->activeDepartment->id => []]];

        foreach ($responses as $response) {
            $data['commodities'][$this->activeDepartment->id][$response->commodity_id] = $response->not_applicable
                ? 'na'
                : ($response->available ? 1 : 0);

            if ($response->quantity !== null) {
                $data['commodities_quantity'][$this->activeDepartment->id][$response->commodity_id] = $response->quantity;
            }
        }

        return $data;
    }

    public function form(Form $form): Form
    {
        $responsesByCode = $this->responsesByQuestionCode();

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
            Forms\Components\View::make('filament.pages.assessment.department-tabs')
                ->viewData(fn () => [
                    'departments' => $this->visibleDepartments,
                    'activeDepartmentId' => $this->activeDepartment->id,
                    'baseUrl' => AssessmentResource::getUrl('edit-health-products', ['record' => $this->record->id]),
                ])
                ->columnSpanFull(),
            ...$this->buildCategorySections($this->activeDepartment, $categories, $responsesByCode),
            // Filament's default bottom Save/Cancel bar is suppressed
            // (see getFormActions() below) in favor of this — sits where
            // that bar used to, after every category, so it reads as "the
            // save button" rather than a mid-page action. Still drives the
            // section-completion path: on the last department,
            // saveDepartmentTab()'s getRedirectUrl() falls through to the
            // next top-level section (or the dashboard) exactly as it did
            // before this moved.
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('save_active_department')
                    ->label("Save {$this->activeDepartment->name}")
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn () => $this->saveDepartmentTab($this->activeDepartment->id)),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * Removes Filament's default bottom Save/Cancel bar — the per-
     * department "Save {Department}" action above (inside the form
     * schema itself) is this page's only save control now.
     */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Saves just the active department's commodities, then advances to the
     * next department tab (or, after the last department, the next
     * top-level assessment section / dashboard — same as getRedirectUrl()).
     */
    public function saveDepartmentTab(int $departmentId): void
    {
        $payload = $this->data['commodities'][$departmentId] ?? [];
        $quantityPayload = $this->data['commodities_quantity'][$departmentId] ?? [];

        $this->persistCommodityResponses([$departmentId => $payload], [$departmentId => $quantityPayload]);

        app(\App\Services\CommodityScoringService::class)
            ->recalculateDepartmentScore($this->record->id, $departmentId);

        $progress = $this->record->section_progress ?? [];
        $progress[$this->section->code] = true;
        $this->record->section_progress = $progress;
        $this->record->save();

        $departmentName = AssessmentDepartment::find($departmentId)?->name ?? 'Department';

        Notification::make()
            ->title("{$departmentName} saved")
            ->success()
            ->duration(2500)
            ->send();

        $redirectUrl = $this->getRedirectUrl();

        $this->redirect($redirectUrl, navigate: \Filament\Support\Facades\FilamentView::hasSpaMode($redirectUrl));
    }

    /**
     * The department immediately after the active one in tab order, or null
     * if the active department is the last one.
     */
    private function nextDepartment(): ?AssessmentDepartment
    {
        $currentIndex = $this->visibleDepartments->search(
            fn (AssessmentDepartment $d) => $d->id === $this->activeDepartment->id
        );

        if ($currentIndex === false) {
            return null;
        }

        return $this->visibleDepartments->get($currentIndex + 1);
    }

    /**
     * Overrides HasSectionNavigation's version: saving a department should
     * move to the next department tab within Health Products first, and
     * only fall through to the next top-level assessment section (or the
     * dashboard) once every department here has been saved.
     */
    protected function getRedirectUrl(): string
    {
        $nextDepartment = $this->nextDepartment();

        if ($nextDepartment) {
            return AssessmentResource::getUrl('edit-health-products', ['record' => $this->record->id])
                .'?dept='.$nextDepartment->slug;
        }

        return $this->getNextSectionRoute();
    }

    private function responsesByQuestionCode(): array
    {
        return $this->responsesByCodeCache ??= \App\Models\AssessmentQuestionResponse::query()
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

                $answerField = Forms\Components\ToggleButtons::make("commodities.{$dept->id}.{$commodity->id}")
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
                    ->live()
                    ->columnSpan(1);

                $rowFields = [
                    Forms\Components\Placeholder::make("label_{$dept->id}_{$commodity->id}")
                        ->label('')
                        ->content($label)
                        ->extraAttributes($rowStyle)
                        ->columnSpan(1),
                    $answerField,
                ];

                // The commodity's own label promises a follow-up number
                // when the answer is Yes (e.g. "Functional Infusion
                // Pumps. If yes indicate number") — nothing captured that
                // number before requires_quantity existed, despite the
                // label asking for it.
                if ($commodity->requires_quantity) {
                    $rowFields[] = Forms\Components\TextInput::make("commodities_quantity.{$dept->id}.{$commodity->id}")
                        ->label('Number')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get) => (int) $get("commodities.{$dept->id}.{$commodity->id}") === 1)
                        ->columnSpan(1);
                }

                $rows[] = Forms\Components\Grid::make(count($rowFields))
                    ->schema($rowFields)
                    ->columns(count($rowFields));
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
        $quantityPayload = $data['commodities_quantity'] ?? [];

        $this->persistCommodityResponses($payload, $quantityPayload);

        foreach (array_keys($payload) as $departmentId) {
            app(\App\Services\CommodityScoringService::class)
                ->recalculateDepartmentScore($this->record->id, $departmentId);
        }

        $progress = $this->record->section_progress ?? [];
        $progress[$this->section->code] = true;
        $this->record->section_progress = $progress;
        $this->record->save();

        unset($data['commodities'], $data['commodities_quantity']);

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
     * @param  array<int, array<int, mixed>>  $quantityPayload  [departmentId => [commodityId => number|null]] — only set for commodities with requires_quantity=true.
     */
    private function persistCommodityResponses(array $payload, array $quantityPayload = []): void
    {
        $now = now();
        $rows = [];

        foreach ($payload as $departmentId => $commodityEntries) {
            foreach ($commodityEntries as $commodityId => $value) {
                $isNotApplicable = $value === 'na';
                $available = $isNotApplicable ? false : (bool) $value;
                $quantity = $available ? ($quantityPayload[$departmentId][$commodityId] ?? null) : null;

                $rows[] = [
                    'assessment_id' => $this->record->id,
                    'assessment_department_id' => $departmentId,
                    'commodity_id' => $commodityId,
                    'available' => $available,
                    'not_applicable' => $isNotApplicable,
                    'quantity' => $quantity !== null && $quantity !== '' ? (int) $quantity : null,
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
            ['available', 'not_applicable', 'quantity', 'score', 'updated_at']
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

