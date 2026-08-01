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
            $this->record->section_progress = [
                'facility_assessor' => true,
                'infrastructure' => false,
                'skills_lab' => false,
                'human_resources' => false,
                'health_products' => false,
                'information_systems' => false,
                'quality_of_care' => false,
            ];
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
                        TextEntry::make('assessment_type')->label('Type'),
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
     * Define all sections
     */
    protected function getAllSections(): array
    {
        return [
            [
                'key' => 'facility_assessor',
                'label' => 'Facility & Assessor',
                'route' => null,
                'done' => $this->record->section_progress['facility_assessor'] ?? false,
                'icon' => 'heroicon-o-building-office-2',
            ],
            [
                'key' => 'infrastructure',
                'label' => 'Infrastructure',
                'route' => AssessmentResource::getUrl('edit-infrastructure', ['record' => $this->record->id]),
                'done' => $this->record->section_progress['infrastructure'] ?? false,
                'icon' => 'heroicon-o-building-office',
            ],
            [
                'key' => 'skills_lab',
                'label' => 'Skills Lab',
                'route' => AssessmentResource::getUrl('edit-skills-lab', ['record' => $this->record->id]),
                'done' => $this->record->section_progress['skills_lab'] ?? false,
                'icon' => 'heroicon-o-beaker',
            ],
            [
                'key' => 'human_resources',
                'label' => 'Human Resources',
                'route' => AssessmentResource::getUrl('edit-human-resources', ['record' => $this->record->id]),
                'done' => $this->record->section_progress['human_resources'] ?? false,
                'icon' => 'heroicon-o-user-group',
            ],
            [
                'key' => 'health_products',
                'label' => 'Health Products',
                'route' => AssessmentResource::getUrl('edit-health-products', ['record' => $this->record->id]),
                'done' => $this->record->section_progress['health_products'] ?? false,
                'icon' => 'heroicon-o-cube',
            ],
            [
                'key' => 'information_systems',
                'label' => 'Information Systems',
                'route' => AssessmentResource::getUrl('edit-information-systems', ['record' => $this->record->id]),
                'done' => $this->record->section_progress['information_systems'] ?? false,
                'icon' => 'heroicon-o-computer-desktop',
            ],
            [
                'key' => 'quality_of_care',
                'label' => 'Quality of Care',
                'route' => AssessmentResource::getUrl('edit-quality-of-care', ['record' => $this->record->id]),
                'done' => $this->record->section_progress['quality_of_care'] ?? false,
                'icon' => 'heroicon-o-star',
            ],
        ];
    }

    public function submitAssessment()
    {
        $progress = $this->record->section_progress;

        if (in_array(false, $progress, true)) {
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

        Notification::make()
            ->success()
            ->title('Assessment submitted')
            ->body('Assessment successfully completed.')
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

    private function canSubmit(): bool
    {
        $progress = $this->record->section_progress ?? [];

        return $progress && ! in_array(false, $progress, true);
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
        ];
    }
}
