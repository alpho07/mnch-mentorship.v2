<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentResource\Pages;
use App\Models\Assessment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssessmentResource extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Assessments';

    protected static ?string $navigationGroup = 'Facility Assessment';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_assessment');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_assessment');
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    /**
     * Row-level scoping: super_admin/admin/division see every assessment;
     * everyone else (assessor and any other role) only sees assessments
     * they personally conducted. This also naturally blocks direct-URL
     * access to another assessor's record, since edit/view/dashboard pages
     * resolve records through this same scoped query.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->where('assessor_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form; // Empty — pages define their own forms
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->formatStateUsing(fn (string $state, $record): string => $record->facility?->mfl_code
                        ? "{$state} ({$record->facility->mfl_code})"
                        : $state)
                    ->searchable(['name', 'mfl_code'])
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-building-office-2')
                    ->iconColor('primary'),
                Tables\Columns\BadgeColumn::make('assessment_type')
                    ->label('Type')
                    ->sortable()
                    ->colors([
                        'primary' => 'baseline',
                        'success' => 'midline',
                        'warning' => 'endline',
                    ])
                    ->icons([
                        'heroicon-m-flag' => 'baseline',
                        'heroicon-m-arrow-trending-up' => 'midline',
                        'heroicon-m-check-circle' => 'endline',
                    ]),
                Tables\Columns\TextColumn::make('assessment_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->icon('heroicon-m-calendar')
                    ->iconColor('info'),
                Tables\Columns\TextColumn::make('assessor.name')
                    ->label('Assessor')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-user')
                    ->limit(20),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'rejected',
                    ])
                    ->icons([
                        'heroicon-m-pencil' => 'draft',
                        'heroicon-m-clock' => 'in_progress',
                        'heroicon-m-check-badge' => 'completed',
                        'heroicon-m-x-circle' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(function ($record) {
                        $progress = $record->section_progress ?? [];
                        $completed = count(array_filter($progress));
                        $total = count($progress);

                        return $total > 0 ? round(($completed / $total) * 100).'%' : '0%';
                    })
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === '100%' => 'success',
                        (int) str_replace('%', '', $state) >= 50 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ])
                    ->multiple()
                    ->placeholder('All Statuses'),
                SelectFilter::make('assessment_type')
                    ->label('Assessment Type')
                    ->options([
                        'baseline' => 'Baseline',
                        'midline' => 'Midline',
                        'endline' => 'Endline',
                    ])
                    ->multiple()
                    ->placeholder('All Types'),
                SelectFilter::make('facility_id')
                    ->label('Facility')
                    ->relationship('facility', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->placeholder('All Facilities'),
                Filter::make('assessment_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('assessment_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('assessment_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('From '.\Carbon\Carbon::parse($data['from'])->toFormattedDateString())
                                ->removeField('from');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Until '.\Carbon\Carbon::parse($data['until'])->toFormattedDateString())
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
                Filter::make('completed')
                    ->label('Completed Assessments')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'completed'))
                    ->toggle(),
                Filter::make('in_progress')
                    ->label('In Progress')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'in_progress'))
                    ->toggle(),
                Filter::make('recent')
                    ->label('Last 30 Days')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(30)))
                    ->toggle(),
            ], layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('dashboard')
                        ->label('Continue Assessment')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->url(fn ($record) => static::getUrl('dashboard', ['record' => $record]))
                        ->color('primary'),
                    Tables\Actions\Action::make('view_summary')
                        ->label('View Summary')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn ($record) => AssessmentResource::getUrl('summary', ['record' => $record])),
                    Tables\Actions\Action::make('executive_dashboard')
                        ->label('Executive Dashboard')
                        ->icon('heroicon-o-chart-pie')
                        ->color('warning')
                        ->url(fn ($record) => route('assessment.executive', $record))
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => $record->status === 'completed'),
                    Tables\Actions\Action::make('markFeedbackGiven')
                        ->label(fn ($record) => $record->feedback_given ? 'Update Feedback' : 'Mark Feedback Given')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->visible(fn ($record) => $record->status === 'completed')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('feedback_notes')
                                ->label('Feedback notes (optional)')
                                ->default(fn ($record) => $record->feedback_notes)
                                ->rows(3),
                        ])
                        ->modalHeading(fn ($record) => $record->feedback_given
                            ? 'Update Feedback Record'
                            : 'Mark Feedback as Given')
                        ->modalDescription(fn ($record) => $record->feedback_given
                            ? 'Update the feedback notes for this assessment.'
                            : 'Confirm that feedback has been given to the facility for this assessment.')
                        ->action(function ($record, array $data): void {
                            $record->update([
                                'feedback_given' => true,
                                'feedback_given_by' => auth()->id(),
                                'feedback_given_at' => now(),
                                'feedback_notes' => $data['feedback_notes'] ?? null,
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('Feedback marked as given')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('markTrained')
                        ->label(fn ($record) => match ($record->trained_before_mentorship) {
                            true => 'Has Been Trained (Yes)',
                            false => 'Has Been Trained (No)',
                            default => 'Mark Has Been Trained',
                        })
                        ->icon('heroicon-o-academic-cap')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'completed' && $record->feedback_given)
                        ->form([
                            \Filament\Forms\Components\Select::make('trained_before_mentorship')
                                ->label('Has this facility been trained?')
                                ->options([
                                    '1' => 'Yes — facility has been trained',
                                    '0' => 'No — facility has not been trained',
                                ])
                                ->default(fn ($record) => $record->trained_before_mentorship === null
                                    ? null
                                    : (string) (int) $record->trained_before_mentorship)
                                ->required(),
                        ])
                        ->modalHeading('Mark Has Been Trained')
                        ->modalDescription('Record whether this facility has been trained. This overrides the auto-computed value.')
                        ->action(function ($record, array $data): void {
                            $record->update([
                                'trained_before_mentorship' => (bool) $data['trained_before_mentorship'],
                                'trained_marked_by' => auth()->id(),
                                'trained_marked_at' => now(),
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('Training status updated')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('download')
                        ->label('Download Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function ($record) {
                            // Add download logic here
                        })
                        ->visible(fn ($record) => $record->status === 'completed'),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Assessment')
                        ->modalDescription('This assessment will be soft-deleted and can be restored later by a super admin.')
                        ->visible(fn ($record) => auth()->user()->hasRole('super_admin') &&
                            $record->status !== 'completed'
                        ),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_in_progress')
                        ->label('Mark as In Progress')
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['status' => 'in_progress']))
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'completed_by' => auth()->id(),
                        ]))
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No assessments yet')
            ->emptyStateDescription('Start by creating your first facility assessment.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Assessment')
                    ->icon('heroicon-o-plus'),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessments::route('/'),
            'create' => Pages\CreateAssessment::route('/create'),
            'dashboard' => Pages\AssessmentDashboard::route('/{record}/dashboard'),
            'edit-infrastructure' => Pages\EditInfrastructure::route('/{record}/infrastructure'),
            'edit-skills-lab' => Pages\EditSkillsLab::route('/{record}/skills-lab'),
            'edit-human-resources' => Pages\EditHumanResources::route('/{record}/human-resources'),
            'edit-health-products' => Pages\EditHealthProducts::route('/{record}/health-products'),
            'edit-information-systems' => Pages\EditInformationSystems::route('/{record}/information-systems'),
            'edit-quality-of-care' => Pages\EditQualityOfCare::route('/{record}/quality-of-care'),
            'summary' => Pages\ViewAssessmentSummary::route('/{record}/summary'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereIn('status', ['draft', 'in_progress'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
