<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingExportResource\Pages;
use App\Models\Training;
use App\Models\User;
use App\Models\County;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Department;
use App\Models\Cadre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Carbon;

class TrainingExportResource extends Resource {

    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationLabel = 'Export Center';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'training-exports';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_training::export');}

    public static function form(Form $form): Form {
        return $form->schema([
                            Tabs::make('Export Configuration')
                            ->tabs([
                                // Tab 1: Export Type & Trainings
                                Tabs\Tab::make('1. Select Trainings/Mentorships')
                                ->icon('heroicon-o-academic-cap')
                                ->schema([
                                    Section::make('Export Type')
                                    ->description('Choose what type of data you want to export')
                                    ->schema([
                                        Select::make('export_type')
                                        ->label('What do you want to export?')
                                        ->options([
                                            'training_participants' => 'Participants/Mentee - Export participants/mentees from selected trainings/mentorships',
                                            'participant_trainings' => 'Participant/mentee History - Export all trainings/mentorships for selected participants/mentees',
                                            'training_summary' => 'Summary Report - Overview of selected trainings/mentorships',
                                        ])
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $set('selected_trainings', []);
                                            $set('selected_participants', []);
                                        })
                                        ->helperText('Select the type of export you need'),
                                    ]),
                                    // Training Selection
                                    Section::make('Select Trainings/Mentorships')
                                    ->description('Choose which trainings/mentorships to include in your export')
                                    ->visible(fn(Get $get) => in_array($get('export_type'), ['training_participants', 'training_summary']))
                                    ->schema([
                                        Grid::make(1)->schema([
                                            Select::make('training_type_filter')
                                            ->label('Type')
                                            ->options([
                                                'all' => 'All Types',
                                                'global_training' => 'MOH Trainings',
                                                'facility_mentorship' => 'Facility Mentorships',
                                            ])
                                            ->default('all')
                                            ->live(),
                                        ]),
                                        CheckboxList::make('selected_trainings')
                                        ->label('Available Trainings/Mentorships')
                                        ->options(function (Get $get) {
                                            $query = static::scopedTrainingQuery(auth()->user())
                                                ->with(['facility', 'county', 'partner', 'participants', 'assessmentCategories']);

                                            if ($get('training_type_filter') !== 'all') {
                                                $query->where('type', $get('training_type_filter'));
                                            }

                                            return $query->get()->mapWithKeys(function ($training) {
                                                        $type = $training->type === 'global_training' ? 'MOH Training' : 'Facility Mentorship';
                                                        $location = $training->facility?->name ?? $training->county?->name ?? $training->partner?->name ?? 'Various';
                                                        $participants = $training->participants()->count();
                                                        $assessmentCategories = $training->assessmentCategories()->count();
                                                        $dates = $training->start_date ? $training->start_date->format('M Y') : 'TBD';

                                                        return [
                                                            $training->id => "{$training->title} [{$type}] • {$location} • {$participants} participants • {$assessmentCategories} assessment categories • {$dates}"
                                                        ];
                                                    });
                                        })
                                        ->searchable()
                                        ->bulkToggleable()
                                        ->columns(1)
                                        ->gridDirection('row')
                                        ->required(fn(Get $get) => in_array($get('export_type'), ['training_participants', 'training_summary']))
                                        ->helperText('Select one or more trainings/mentorships. Each will be a separate worksheet.'),
                                        Placeholder::make('assessment_categories_preview')
                                        ->content(function (Get $get): HtmlString {
                                            $selectedTrainings = $get('selected_trainings') ?? [];
                                            if (empty($selectedTrainings)) {
                                                return new HtmlString('<div class="text-gray-500">Select trainings to see assessment categories</div>');
                                            }

                                            $trainings = Training::whereIn('id', $selectedTrainings)
                                                    ->with('assessmentCategories')
                                                    ->get();

                                            $preview = '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4">';
                                            $preview .= '<h4 class="text-sm font-medium text-blue-800 mb-2">Assessment Categories Found:</h4>';

                                            foreach ($trainings as $training) {
                                                $categories = $training->assessmentCategories;
                                                $type = $training->type === 'global_training' ? 'Training' : 'Mentorship';

                                                if ($categories->count() > 0) {
                                                    $preview .= "<div class='mb-2'>";
                                                    $preview .= "<strong>{$training->title} [{$type}]:</strong><br>";
                                                    foreach ($categories as $category) {
                                                        $weight = $category->pivot->weight_percentage ?? 0;
                                                        $preview .= "<span class='text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded mr-1 mb-1 inline-block'>";
                                                        $preview .= "{$category->name} ({$weight}%)";
                                                        $preview .= "</span>";
                                                    }
                                                    $preview .= "</div>";
                                                } else {
                                                    $preview .= "<div class='text-orange-600 text-sm'>⚠️ {$training->title} [{$type}]: No assessment categories configured</div>";
                                                }
                                            }

                                            $preview .= '</div>';
                                            return new HtmlString($preview);
                                        })
                                        ->columnSpanFull(),
                                    ]),
                                    // Participant Selection
                                    Section::make('Select Participants/Mentees')
                                    ->description('Choose which participants/mentees to get training/mentorship history for')
                                    ->visible(fn(Get $get) => $get('export_type') === 'participant_trainings')
                                    ->schema([
                                        CheckboxList::make('selected_participants')
                                        ->label('Available Participants/mentees')
                                        ->options(function () {
                                            $scopedTrainingIds = static::scopedTrainingQuery(auth()->user())->pluck('id');

                                            return User::whereHas('trainingParticipations', fn ($q) =>
                                                    $q->whereIn('training_id', $scopedTrainingIds)
                                                )
                                                ->with(['facility', 'department', 'cadre'])
                                                ->get()
                                                ->mapWithKeys(function ($user) use ($scopedTrainingIds) {
                                                    $name     = $user->full_name;
                                                    $facility = $user->facility?->name ?? 'No facility';
                                                    $trainings = $user->trainingParticipations()
                                                        ->whereIn('training_id', $scopedTrainingIds)
                                                        ->count();

                                                    return [
                                                        $user->id => "{$name} • {$facility} • {$trainings} activities"
                                                    ];
                                                });
                                        })
                                        ->searchable()
                                        ->bulkToggleable()
                                        ->columns(1)
                                        ->gridDirection('row')
                                        ->required(fn(Get $get) => $get('export_type') === 'participant_trainings')
                                        ->helperText('Select participants/mentees to get their complete training/mentorship history.'),
                                    ]),
                                ]),
                                // Tab 2: Filters & Criteria
                                Tabs\Tab::make('2. Filters & Criteria')
                                ->icon('heroicon-o-funnel')
                                ->schema([
                                    Section::make('Geographic Filters')
                                    ->description('Filter by location (only for MOH Trainings)')
                                    ->visible(function (Get $get) {
                                        $selectedTrainings = $get('selected_trainings') ?? [];
                                        if (empty($selectedTrainings))
                                            return false;

                                        $trainings = Training::whereIn('id', $selectedTrainings)->get();
                                        return $trainings->where('type', 'global_training')->isNotEmpty();
                                    })
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('filter_counties')
                                            ->label('Counties')
                                            ->multiple()
                                            ->options(County::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Leave empty to include all counties'),
                                            Select::make('filter_facilities')
                                            ->label('Facilities')
                                            ->multiple()
                                            ->options(Facility::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Leave empty to include all facilities'),
                                        ]),
                                    ]),
                                    Section::make('Participant Filters')
                                    ->description('Filter participants by their characteristics')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('filter_departments')
                                            ->label('Departments')
                                            ->multiple()
                                            ->options(Department::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload(),
                                            Select::make('filter_cadres')
                                            ->label('Cadres')
                                            ->multiple()
                                            ->options(Cadre::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload(),
                                        ]),
                                    ]),
                                    Section::make('Date Filters')
                                    ->description('Filter by year')
                                    ->schema([
                                        Select::make('filter_years')
                                        ->label('Years')
                                        ->multiple()
                                        ->options(function () {
                                            $currentYear = Carbon::now()->year;
                                            $years = [];
                                            for ($year = $currentYear; $year >= 2020; $year--) {
                                                $years[$year] = $year;
                                            }
                                            return $years;
                                        })
                                        ->helperText('Leave empty to include all years'),
                                    ]),
                                ]),
                                // Tab 3: Data Fields
                                Tabs\Tab::make('3. Data Fields')
                                ->icon('heroicon-o-table-cells')
                                ->schema([
                                    Section::make('Participant/Mentee Information')
                                    ->description('All participant/mentees fields are included in the specified order')
                                    ->schema([
                                        Placeholder::make('participant_fields_info')
                                        ->content(new HtmlString('
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-green-800 mb-2">Standard Participant/Mentee Fields (Fixed Order):</h4>
                                <div class="text-sm text-green-700">
                                    <strong>1.</strong> Attendant\'s Name →
                                    <strong>2.</strong> County →
                                    <strong>3.</strong> Subcounty →
                                    <strong>4.</strong> Health MFL Code →
                                    <strong>5.</strong> Facility Name →
                                    <strong>6.</strong> Department →
                                    <strong>7.</strong> Cadre →
                                    <strong>8.</strong> Mobile Number →
                                    <strong>9.</strong> Month →
                                    <strong>10.</strong> Year →
                                    <strong>11.</strong> Assessment Categories (if applicable) →
                                    <strong>12.</strong> Overall Result (Pass/Fail)
                                </div>
                            </div>
                        ')),
                                    ]),
                                    Section::make('Assessment Information')
                                    ->description('Select assessment details to include')
                                    ->schema([
                                        Toggle::make('include_assessments')
                                        ->label('Include Assessment Results')
                                        ->default(true)
                                        ->live()
                                        ->helperText('Include individual category results and overall pass/fail'),
                                        Toggle::make('include_individual_categories')
                                        ->label('Include Individual Category Results')
                                        ->default(true)
                                        ->visible(fn(Get $get) => $get('include_assessments'))
                                        ->live()
                                        ->helperText('Add columns for each assessment category (Pass/Fail)'),
                                        Select::make('category_column_format')
                                        ->label('Category Column Format')
                                        ->options([
                                            'result_only' => 'Result Only (PASS/FAIL)',
                                            'result_with_weight' => 'Result + Weight (PASS - 25%)',
                                        ])
                                        ->default('result_with_weight')
                                        ->visible(fn(Get $get) => $get('include_assessments') && $get('include_individual_categories'))
                                        ->helperText('How to display individual category results'),
                                        Select::make('incomplete_display')
                                        ->label('Display Incomplete Assessments As')
                                        ->options([
                                            'blank' => 'Blank Cell',
                                            'not_assessed' => 'NOT ASSESSED',
                                            'pending' => 'PENDING',
                                            'dash' => '—',
                                        ])
                                        ->default('not_assessed')
                                        ->visible(fn(Get $get) => $get('include_assessments')),
                                    ]),
                                ]),
                                // Tab 4: Export Options
                                Tabs\Tab::make('4. Export Options')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->schema([
                                    Section::make('File Format & Structure')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('file_format')
                                            ->label('File Format')
                                            ->options([
                                                'xlsx' => 'Excel (.xlsx) - Recommended',
                                                'csv' => 'CSV (.csv) - Single file',
                                            ])
                                            ->default('xlsx')
                                            ->required(),
                                            Select::make('worksheet_structure')
                                            ->label('Worksheet Structure')
                                            ->options([
                                                'per_training' => 'One worksheet per training/mentorship',
                                                'combined' => 'All data in one worksheet',
                                            ])
                                            ->default('per_training')
                                            ->visible(fn(Get $get) => $get('file_format') === 'xlsx'),
                                        ]),
                                    ]),
                                    Section::make('Additional Sheets')
                                    ->visible(fn(Get $get) => $get('include_assessments'))
                                    ->schema([
                                        Toggle::make('include_summary_sheet')
                                        ->label('Include Summary Dashboard')
                                        ->default(true)
                                        ->helperText('Add a summary worksheet with statistics and counts'),
                                        Toggle::make('create_assessment_summary')
                                        ->label('Create Assessment Summary Sheet')
                                        ->default(false)
                                        ->helperText('Create a separate worksheet with assessment statistics'),
                                        Toggle::make('include_category_definitions')
                                        ->label('Include Category Definitions Sheet')
                                        ->default(false)
                                        ->helperText('Add a sheet explaining each assessment category'),
                                    ]),
                                    Section::make('Formatting Options')
                                    ->schema([
                                        Toggle::make('color_code_results')
                                        ->label('Color Code Assessment Results')
                                        ->default(true)
                                        ->visible(fn(Get $get) => $get('include_assessments'))
                                        ->helperText('Green for PASS, Red for FAIL, Orange for incomplete'),
                                        Toggle::make('format_for_printing')
                                        ->label('Format for Printing')
                                        ->default(true)
                                        ->helperText('Optimize layout and formatting for printing'),
                                        Toggle::make('freeze_header_columns')
                                        ->label('Freeze Header Columns')
                                        ->default(true)
                                        ->helperText('Keep participant info visible when scrolling'),
                                    ]),
                                ]),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->query(Training::query()->whereNull('id'))
                        ->columns([])
                        ->emptyStateHeading('Training Export Center')
                        ->emptyStateDescription('Use the "Configure New Export" button to create custom training data exports with flexible filters and field selection.')
                        ->emptyStateIcon('heroicon-o-document-arrow-down')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Configure New Export')
                            ->icon('heroicon-o-plus')
                            ->color('primary'),
                        ])
                        ->paginated(false);
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListTrainingExports::route('/'),
            'create' => Pages\CreateTrainingExport::route('/create'),
            'preview' => Pages\PreviewTrainingExport::route('/preview')
        ];
    }

    public static function getNavigationBadge(): ?string {
        $count = static::scopedTrainingQuery(auth()->user())->whereHas('participants')->count();
        return $count > 0 ? (string) $count : null;
    }

    // ── Role-scoped base query ────────────────────────────────────────────────
    // MOH (global) trainings are always visible to everyone.
    // Facility mentorships are scoped the same way as MentorshipTrainingResource.

    private static function scopedTrainingQuery(User $user): Builder
    {
        $query = Training::query();

        if ($user->hasRole(['super_admin', 'admin', 'division', 'national_mentor', 'national_mentor_lead'])) {
            return $query;
        }

        if ($user->hasRole('county_mentor_lead')) {
            $countyIds = $user->counties()->pluck('counties.id')->toArray();
            if ($user->county_id) {
                $countyIds[] = $user->county_id;
            }
            $facilityIds = Facility::whereHas('subcounty',
                fn ($q) => $q->whereIn('county_id', array_unique($countyIds))
            )->pluck('id');

            return $query->where(fn ($q) => $q
                ->where('type', 'global_training')
                ->orWhereIn('facility_id', $facilityIds)
            );
        }

        if ($user->hasRole('subcounty_mentor_lead')) {
            $subcountyIds = $user->subcounties()->pluck('subcounties.id');
            $facilityIds  = Facility::whereIn('subcounty_id', $subcountyIds)->pluck('id');

            return $query->where(fn ($q) => $q
                ->where('type', 'global_training')
                ->orWhereIn('facility_id', $facilityIds)
            );
        }

        if ($user->hasRole('facility_mentor_lead')) {
            $facilityIds = $user->facilities()->pluck('facilities.id')->toArray();
            if ($user->facility_id) {
                $facilityIds[] = $user->facility_id;
            }

            return $query->where(fn ($q) => $q
                ->where('type', 'global_training')
                ->orWhereIn('facility_id', array_unique($facilityIds))
            );
        }

        // Default (facility_mentor, spoke_mentor, etc.) — own mentorships only
        return $query->where(fn ($q) => $q
            ->where('type', 'global_training')
            ->orWhere(fn ($q2) => $q2
                ->where('type', 'facility_mentorship')
                ->forMentorOrCoMentor($user->id)
            )
        );
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'info';
    }

    public static function canEdit($record): bool {
        return false;
    }

    public static function canView($record): bool {
        return false;
    }

    public static function canDelete($record): bool {
        return false;
    }
}
