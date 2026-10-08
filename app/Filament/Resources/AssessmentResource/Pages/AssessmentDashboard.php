<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class AssessmentDashboard extends Page
{
    protected static string $resource = AssessmentResource::class;

    protected static string $view = 'filament.pages.assessment.dashboard';

    protected static ?string $infolist = 'assessment_summary';

    public Assessment $record;

    public ?string $searchTerm = null;

    public ?string $statusFilter = null;

    public ?string $completionFilter = null;

    public function mount(int|string $record): void
    {
        // Scoped through the resource's own getEloquentQuery() — an
        // assessor hitting another assessor's dashboard URL directly
        // should 404, same as the list/edit/view pages.
        // $record (the method param) isn't reliable here — Livewire already
        // resolves the typed $this->record property via implicit binding
        // before mount() runs, using the *unscoped* model; $this->record->id
        // is the one safe thing to pull off it. Re-fetching by that id
        // through the resource's scoped query is what actually enforces
        // that an assessor can't reach another assessor's dashboard.
        $this->record = AssessmentResource::getEloquentQuery()->findOrFail($this->record->id);

        if (! $this->record->section_progress) {
            $sectionCodes = $this->record->assessmentType
                ?->sections()->active()->pluck('code')->toArray() ?? [];

            $this->record->section_progress = array_merge(
                ['facility_assessor' => true],
                array_fill_keys($sectionCodes, false)
            );
            $this->record->save();
        }
    }

    public function getInfolist(string $name): ?Infolist
    {
        if ($name !== 'assessment_summary') {
            return null;
        }

        return Infolist::make()
            ->record($this->record)
            ->schema([
                Section::make('Assessment Details')
                    ->schema([
                        TextEntry::make('facility.name')->label('Facility'),
                        TextEntry::make('assessmentType.category.name')->label('Category')->placeholder('—'),
                        TextEntry::make('round')->label('Round')->formatStateUsing(fn ($record) => $record->round_display),
                        TextEntry::make('assessment_date')->label('Date')->date(),
                        TextEntry::make('assessor.name')->label('Assessor'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Filter form in the header
     */
    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('searchTerm')
                    ->label('Search sections')
                    ->placeholder('Search by section name...')
                    ->live(onBlur: true),
                Select::make('statusFilter')
                    ->label('Status')
                    ->options([
                        'all' => 'All Sections',
                        'completed' => 'Completed',
                        'incomplete' => 'Incomplete',
                    ])
                    ->default('all')
                    ->live(),
                Select::make('completionFilter')
                    ->label('Progress')
                    ->options([
                        'all' => 'All',
                        'not_started' => 'Not Started',
                        'in_progress' => 'In Progress',
                        'done' => 'Completed',
                    ])
                    ->default('all')
                    ->live(),
            ])
            ->columns(3)
            ->statePath('filters');
    }

    /**
     * Get filtered sections based on search and filters
     */
    protected function getFilteredSections(): array
    {
        $sections = $this->getAllSections();

        // Apply search filter
        if ($this->searchTerm) {
            $sections = array_filter($sections, function ($section) {
                return str_contains(
                    strtolower($section['label']),
                    strtolower($this->searchTerm)
                );
            });
        }

        // Apply status filter
        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $sections = array_filter($sections, function ($section) {
                if ($this->statusFilter === 'completed') {
                    return $section['done'] === true;
                }

                return $section['done'] === false;
            });
        }

        return array_values($sections);
    }

    /**
     * All sections on this assessment's own template, in order — dynamic
     * per template rather than a fixed list of 6. "Facility & Assessor"
     * isn't a real assessment_sections row; it's a synthetic first entry
     * representing the create-assessment step itself, always complete once
     * the record exists.
     */
    protected function getAllSections(): array
    {
        $progress = $this->record->section_progress ?? [];

        $result = [
            [
                'key' => 'facility_assessor',
                'label' => 'Facility & Assessor',
                'route' => null,
                'done' => $progress['facility_assessor'] ?? false,
                'icon' => 'heroicon-o-building-office-2',
            ],
        ];

        $sections = $this->record->assessmentType
            ?->sections()
            ->where('is_active', true)
            ->orderBy('order')
            ->get() ?? collect();

        // Commodity-matrix completion is derived from the responses on
        // record rather than read from section_progress — the stored flag
        // was set on the first department saved (over-reporting), and only
        // ever on the one commodity_matrix section EditHealthProducts
        // resolved, leaving any sibling matrix section stuck on "Pending".
        // sync() writes the derived value back so allSectionsComplete()
        // and the submit gate agree with what's shown here.
        $matrixComplete = null;

        foreach ($sections as $section) {
            $kind = $section->resolvedKind();

            $route = match ($kind) {
                'question_group' => AssessmentResource::getUrl('edit-section', [
                    'record' => $this->record->id,
                    'sectionCode' => $section->code,
                ]),
                'human_resources' => AssessmentResource::getUrl('edit-human-resources', ['record' => $this->record->id]),
                'commodity_matrix' => AssessmentResource::getUrl('edit-health-products', ['record' => $this->record->id]),
                default => null, // informational — no dedicated page
            };

            if ($route === null) {
                continue;
            }

            $result[] = [
                'key' => $section->code,
                'label' => $section->name,
                'route' => $route,
                'done' => $kind === 'commodity_matrix'
                    ? ($matrixComplete ??= app(\App\Services\CommodityMatrixProgressService::class)->sync($this->record))
                    : ($progress[$section->code] ?? false),
                'icon' => $section->icon ?: match ($kind) {
                    'human_resources' => 'heroicon-o-user-group',
                    'commodity_matrix' => 'heroicon-o-cube',
                    default => 'heroicon-o-clipboard-document-list',
                },
            ];
        }

        return $result;
    }

    public function submitAssessment()
    {
        if (! $this->record->allSectionsComplete()) {
            Notification::make()
                ->warning()
                ->title('Cannot submit')
                ->body('Please complete all sections before submitting.')
                ->send();

            return;
        }

        $this->record->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ]);
        $this->record->lock(auth()->id());

        Notification::make()
            ->success()
            ->title('Assessment submitted')
            ->body('Assessment successfully completed and locked. Only the team lead or an admin can reopen it.')
            ->send();

        return redirect(AssessmentResource::getUrl());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('submitAssessment')
                ->label('Submit Assessment')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn () => $this->canSubmit())
                ->action('submitAssessment'),
        ];
    }

    /**
     * Mirrors submitAssessment()'s own gate. The raw section_progress array
     * can't be used directly: mount() seeds a false entry for *every*
     * active section including informational ones, which have no page and
     * can never be marked done — so `! in_array(false, $progress)` kept the
     * Submit button hidden forever on any template with one.
     */
    private function canSubmit(): bool
    {
        // Refreshes the commodity_matrix flags from the responses on record
        // before the gate reads them.
        $this->getAllSections();

        return $this->record->allSectionsComplete();
    }

    protected function getViewData(): array
    {
        $allSections = $this->getAllSections();
        $completed = count(array_filter($allSections, fn ($s) => $s['done']));
        $total = count($allSections);

        return [
            'record' => $this->record,
            'sections' => $this->getFilteredSections(),
            'progressStats' => [
                'completed' => $completed,
                'total' => $total,
                'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
            ],
            'team' => app(\App\Services\AssessmentTeamService::class)->getTeamForDisplay($this->record),
        ];
    }
}
