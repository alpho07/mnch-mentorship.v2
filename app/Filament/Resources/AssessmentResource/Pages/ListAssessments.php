<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Services\AssessmentExportService;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListAssessments extends ListRecords
{
    protected static string $resource = AssessmentResource::class;

    protected function getHeaderWidgets(): array
    {
        if (request()->get('tab') === 'home') {
            return [\App\Filament\Widgets\AssessmentAnalyticsEmbed::class];
        }

        return [];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getTabs(): array
    {
        // Scoped through the resource's own getEloquentQuery() (not the
        // plain model) so these badge counts match what an assessor
        // actually sees in the table below, not every assessor's total.
        $total = AssessmentResource::getEloquentQuery()->count();
        $active = AssessmentResource::getEloquentQuery()->whereIn('status', ['draft', 'in_progress'])->count();
        $completed = AssessmentResource::getEloquentQuery()->where('status', 'completed')->count();

        return [
            'all' => Tab::make('All Assessments')
                ->icon('heroicon-o-clipboard-document-check')
                ->badge($total ?: null)
                ->badgeColor('gray'),

            'active' => Tab::make('Active')
                ->icon('heroicon-o-clock')
                ->badge($active ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['draft', 'in_progress'])),

            'completed' => Tab::make('Completed')
                ->icon('heroicon-o-check-circle')
                ->badge($completed ?: null)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed')),

            'home' => Tab::make('Home')
                ->icon('heroicon-o-chart-bar-square')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('1 = 0')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_all')
                ->label('Export All to CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    // Scoped the same way as the table/tabs above — an
                    // assessor exporting "all" should only get their own,
                    // not every assessor's records.
                    $assessments = AssessmentResource::getEloquentQuery()->with([
                        'facility.subcounty.county',
                        'sectionScores.section',
                    ])->get();

                    $service = app(AssessmentExportService::class);
                    $csv = $service->exportMultipleAssessments($assessments);

                    $filename = 'mnch-assessments-bulk-'.now()->format('Y-m-d').'.csv';

                    return response()->streamDownload(function () use ($csv) {
                        echo $csv;
                    }, $filename, [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
            Actions\CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Facility')
                    ->formatStateUsing(fn (string $state, $record): string => $record->facility?->mfl_code
                        ? "{$state} ({$record->facility->mfl_code})"
                        : $state)
                    ->searchable(['name', 'mfl_code'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('assessment_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'baseline' => 'info',
                        'midline' => 'warning',
                        'endline' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('assessmentType.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('assessment_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('overall_percentage')
                    ->label('Score')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 1).'%' : 'N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('overall_grade')
                    ->label('Grade')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'green' => 'success',
                        'yellow' => 'warning',
                        'red' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('assessor.name')
                    ->label('Assessor')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ]),
                Tables\Filters\SelectFilter::make('assessment_type')
                    ->options([
                        'baseline' => 'Baseline',
                        'midline' => 'Midline',
                        'endline' => 'Endline',
                    ]),
                Tables\Filters\SelectFilter::make('overall_grade')
                    ->label('Grade')
                    ->options([
                        'green' => 'Green',
                        'yellow' => 'Yellow',
                        'red' => 'Red',
                    ]),
            ])
            ->filtersLayout(FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // View Summary Action
                    Tables\Actions\Action::make('view_summary')
                        ->label('View')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn ($record) => AssessmentResource::getUrl('summary', ['record' => $record])),
                    // Dashboard Action
                    Tables\Actions\Action::make('dashboard')
                        ->label('Continue')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('primary')
                        ->visible(fn ($record) => $record->status !== 'completed')
                        ->url(fn ($record) => AssessmentResource::getUrl('dashboard', ['record' => $record])),
                    // Export Single CSV Action
                    Tables\Actions\Action::make('export_csv')
                        ->label('CSV')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function ($record) {
                            $service = app(AssessmentExportService::class);
                            $csv = $service->exportAssessmentToCSV($record);

                            $filename = sprintf(
                                'mnch-assessment-%s-%s.csv',
                                $record->facility->name,
                                $record->assessment_date->format('Y-m-d')
                            );

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, $filename, [
                                'Content-Type' => 'text/csv',
                            ]);
                        }),
                    // Download PDF Action
                    Tables\Actions\Action::make('download_pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'completed')
                        ->action(function ($record) {
                            $service = app(\App\Services\AssessmentPdfReportService::class);
                            $pdf = $service->generateExecutiveReport($record);

                            $filename = sprintf(
                                'MNCH-Assessment-%s-%s.pdf',
                                $record->facility->name,
                                $record->assessment_date->format('Y-m-d')
                            );

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->output();
                            }, $filename);
                        }),
                    // Executive Dashboard Action
                    Tables\Actions\Action::make('executive_dashboard')
                        ->label('Executive Dashboard')
                        ->icon('heroicon-o-chart-pie')
                        ->color('warning')
                        ->url(fn ($record) => route('assessment.executive', $record))
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => $record->status === 'completed'),
                    // Mark Feedback Given Action
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
                    // Mark Trained Before Mentorship Action
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
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Export Selected to CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function ($records) {
                            $service = app(AssessmentExportService::class);
                            $csv = $service->exportMultipleAssessments($records);

                            $filename = 'mnch-assessments-selected-'.now()->format('Y-m-d').'.csv';

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, $filename, [
                                'Content-Type' => 'text/csv',
                            ]);
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
