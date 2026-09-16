<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityAssessmentResource\Pages;
use App\Models\FacilityAssessment;
use App\Models\Facility;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class FacilityAssessmentResource extends Resource
{
    protected static ?string $model = FacilityAssessment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Facility Assessments';

    protected static ?string $navigationGroup = 'Reporting';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'facility-assessments';

    protected static ?string $recordTitleAttribute = 'facility.name';
    
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

  

    public static function form(Form $form): Form  
    {
        return $form
            ->schema([
                Section::make('Assessment Overview')
                    ->description('Evaluate facility readiness for mentorship training programs')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('facility_id')
                                    ->label('Facility')
                                    ->relationship('facility', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            // Check for existing assessments
                                            $existingAssessment = FacilityAssessment::where('facility_id', $state)
                                                ->latest()
                                                ->first();
                                            
                                            if ($existingAssessment) {
                                                $set('has_previous_assessment', true);
                                                $set('previous_assessment_date', $existingAssessment->assessment_date);
                                                $set('previous_assessment_score', $existingAssessment->overall_score);
                                            } else {
                                                $set('has_previous_assessment', false);
                                            }
                                        }
                                    }),

                                DatePicker::make('assessment_date')
                                    ->label('Assessment Date')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now()),
                            ]),

                        Placeholder::make('previous_assessment_info')
                            ->content(function (Get $get): HtmlString {
                                if ($get('has_previous_assessment')) {
                                    $date = $get('previous_assessment_date');
                                    $score = $get('previous_assessment_score');
                                    return new HtmlString("
                                        <div class='bg-blue-50 border border-blue-200 rounded-lg p-4'>
                                            <h4 class='font-medium text-blue-900 mb-1'>Previous Assessment Found</h4>
                                            <p class='text-sm text-blue-700'>
                                                Last assessed on {$date} with a score of {$score}%
                                            </p>
                                        </div>
                                    ");
                                }
                                return new HtmlString('');
                            })
                            ->visible(fn (Get $get) => $get('has_previous_assessment'))
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('has_previous_assessment'),
                        Forms\Components\Hidden::make('previous_assessment_date'),
                        Forms\Components\Hidden::make('previous_assessment_score'),
                    ]),

                Section::make('Assessment Criteria')
                    ->description('Rate each area from 0-100 based on facility readiness')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('infrastructure_score')
                                    ->label('Infrastructure & Environment')
                                    ->helperText('Training rooms, seating, lighting, ventilation')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        static::calculateOverallScore($set, $get);
                                    }),

                                TextInput::make('equipment_score')
                                    ->label('Equipment & Technology')
                                    ->helperText('Training materials, AV equipment, medical devices')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        static::calculateOverallScore($set, $get);
                                    }),

                                TextInput::make('staff_capacity_score')
                                    ->label('Staff Capacity')
                                    ->helperText('Qualified mentors, availability, workload')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        static::calculateOverallScore($set, $get);
                                    }),

                                TextInput::make('training_environment_score')
                                    ->label('Training Environment')
                                    ->helperText('Learning atmosphere, safety, accessibility')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        static::calculateOverallScore($set, $get);
                                    }),
                            ]),

                        Placeholder::make('overall_score_display')
                            ->content(function (Get $get): HtmlString {
                                $score = $get('overall_score');
                                if ($score !== null) {
                                    $color = $score >= 70 ? 'green' : 'red';
                                    $status = $score >= 70 ? 'APPROVED' : 'NEEDS IMPROVEMENT';
                                    return new HtmlString("
                                        <div class='text-center'>
                                            <div class='text-2xl font-bold text-{$color}-600'>{$score}%</div>
                                            <div class='text-sm text-{$color}-600 font-medium'>{$status}</div>
                                        </div>
                                    ");
                                }
                                return new HtmlString('<div class="text-center text-gray-500">Calculating...</div>');
                            }),

                        Forms\Components\Hidden::make('overall_score'),
                    ]),

                Section::make('Assessment Details')
                    ->schema([
                        Select::make('assessor_id')
                            ->label('Assessor')
                            ->relationship('assessor', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn(User $record): string => 
                                "{$record->full_name} - {$record->facility?->name}"
                            )
                            ->searchable(['first_name', 'last_name'])
                            ->preload()
                            ->default(auth()->id()),

                        Textarea::make('assessment_notes')
                            ->label('Assessment Notes')
                            ->rows(4)
                            ->placeholder('Document observations, challenges, and specific findings...')
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('recommendations')
                            ->label('Recommendations')
                            ->placeholder('Add improvement recommendations...')
                            ->suggestions([
                                'Upgrade training room furniture',
                                'Install audio-visual equipment',
                                'Improve lighting in training areas',
                                'Assign dedicated mentors',
                                'Create quiet learning spaces',
                                'Stock additional training materials',
                                'Improve internet connectivity',
                                'Enhance safety protocols',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected static function calculateOverallScore(Set $set, Get $get): void
    {
        $scores = [
            $get('infrastructure_score'),
            $get('equipment_score'),
            $get('staff_capacity_score'),
            $get('training_environment_score'),
        ];

        $validScores = array_filter($scores, fn($score) => $score !== null && $score !== '');
        
        if (count($validScores) === 4) {
            $overall = array_sum($validScores) / 4;
            $set('overall_score', round($overall, 1));
            
            // Auto-set status and next assessment date
            if ($overall >= 70) {
                $set('status', FacilityAssessment::STATUS_APPROVED);
                $set('next_assessment_due', now()->addYear()->format('Y-m-d'));
            } else {
                $set('status', FacilityAssessment::STATUS_REJECTED);
                $set('next_assessment_due', now()->addMonths(3)->format('Y-m-d'));
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assessment_date')
                    ->label('Assessment Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('overall_score')
                    ->label('Overall Score')
                    ->suffix('%')
                    ->sortable()
                    ->badge()
                    ->color(function ($state): string {
                        if ($state >= 90) return 'success';
                        if ($state >= 70) return 'warning';
                        return 'danger';
                    }),

                BadgeColumn::make('status')
                    ->colors([
                        'secondary' => FacilityAssessment::STATUS_PENDING,
                        'success' => FacilityAssessment::STATUS_APPROVED,
                        'danger' => FacilityAssessment::STATUS_REJECTED,
                        'warning' => FacilityAssessment::STATUS_EXPIRED,
                    ])
                    ->icons([
                        'heroicon-o-clock' => FacilityAssessment::STATUS_PENDING,
                        'heroicon-o-check-circle' => FacilityAssessment::STATUS_APPROVED,
                        'heroicon-o-x-circle' => FacilityAssessment::STATUS_REJECTED,
                        'heroicon-o-exclamation-triangle' => FacilityAssessment::STATUS_EXPIRED,
                    ]),

                TextColumn::make('readiness_level')
                    ->badge()
                    ->color(function ($state): string {
                        return match($state) {
                            'Excellent' => 'success',
                            'Very Good', 'Good' => 'warning',
                            default => 'danger',
                        };
                    }),

                TextColumn::make('next_assessment_due')
                    ->label('Next Due')
                    ->date('M j, Y')
                    ->sortable()
                    ->color(function ($state): string {
                        if (!$state) return 'gray';
                        return $state <= now() ? 'danger' : 'success';
                    }),

                TextColumn::make('assessor.full_name')
                    ->label('Assessor')
                    ->searchable(['first_name', 'last_name']),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(FacilityAssessment::getStatusOptions())
                    ->multiple(),

                SelectFilter::make('facility')
                    ->relationship('facility', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\Filter::make('expired')
                    ->label('Assessment Expired')
                    ->query(fn (Builder $query): Builder => $query->expired())
                    ->toggle(),

                Tables\Filters\Filter::make('needs_reassessment')
                    ->label('Due for Reassessment')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('next_assessment_due', '<=', now()->addMonth())
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FacilityAssessment $record): bool => 
                        $record->status !== FacilityAssessment::STATUS_APPROVED
                    )
                    ->action(function (FacilityAssessment $record): void {
                        $record->update([
                            'status' => FacilityAssessment::STATUS_APPROVED,
                            'next_assessment_due' => now()->addYear(),
                        ]);

                        Notification::make()
                            ->title('Assessment Approved')
                            ->body('Facility is now approved for mentorship training.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reassess')
                    ->label('Reassess')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->url(fn (FacilityAssessment $record): string => 
                        static::getUrl('create', ['facility_id' => $record->facility_id])
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('assessment_date', 'desc')
            ->emptyStateHeading('No Facility Assessments')
            ->emptyStateDescription('Start by assessing facilities to enable mentorship training programs.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacilityAssessments::route('/'),
            'create' => Pages\CreateFacilityAssessment::route('/create'),
            //'view' => Pages\ViewFacilityAssessment::route('/{record}'),
            'edit' => Pages\EditFacilityAssessment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        // Show count of expired assessments
        $count = static::getModel()::expired()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->facility->name . ' Assessment';
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Date' => $record->assessment_date->format('M j, Y'),
            'Score' => $record->overall_score . '%',
            'Status' => ucfirst($record->status),
        ];
    }
}