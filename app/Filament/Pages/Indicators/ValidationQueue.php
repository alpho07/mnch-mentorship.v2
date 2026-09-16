<?php

namespace App\Filament\Pages\Indicators;

use App\Models\Indicators\IndicatorReportPeriod;
use App\Services\IndicatorReportingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;

class ValidationQueue extends Page implements HasTable {

    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Validation Queue';
    protected static ?string $navigationGroup = 'Indicator Reporting';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'indicators/validation-queue';
    protected static string $view = 'filament.pages.indicators.validation-queue';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('page_ValidationQueue');
    }

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('page_ValidationQueue');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table {
        return $table
                        ->query(
                                IndicatorReportPeriod::query()
                                ->with(['facility.subcounty.county', 'reportType', 'frequency', 'submittedByUser'])
                                ->whereIn('status', [
                                    IndicatorReportPeriod::STATUS_SUBMITTED,
                                    IndicatorReportPeriod::STATUS_VALIDATED,
                                    IndicatorReportPeriod::STATUS_REJECTED,
                                ])
                                ->orderByRaw("FIELD(status, 'submitted', 'rejected', 'validated')")
                                ->orderByDesc('submitted_at')
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('facility.name')
                            ->label('Facility')
                            ->searchable()
                            ->sortable()
                            ->description(fn($record) => $record->facility?->subcounty?->county?->name),
                            Tables\Columns\TextColumn::make('reportType.name')
                            ->label('Report Type')
                            ->badge()
                            ->color(fn($record) => $record->reportType?->color ?? 'gray'),
                            Tables\Columns\TextColumn::make('period_label')
                            ->label('Period')
                            ->getStateUsing(fn($record) => $record->period_label),
                            Tables\Columns\TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn($record) => $record->getStatusColor())
                            ->icon(fn($record) => $record->getStatusIcon())
                            ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state))),
                            Tables\Columns\TextColumn::make('submittedByUser.name')
                            ->label('Submitted By')
                            ->default('—'),
                            Tables\Columns\TextColumn::make('submitted_at')
                            ->label('Submitted')
                            ->dateTime('d M Y, H:i')
                            ->sortable()
                            ->default('—'),
                            Tables\Columns\TextColumn::make('completion')
                            ->label('Completion')
                            ->getStateUsing(function ($record) {
                                $stats = app(IndicatorReportingService::class)->getOverallCompletion($record);
                                return $stats['percentage'] . '% (' . $stats['filled'] . '/' . $stats['total'] . ')';
                            }),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'submitted' => 'Submitted',
                                'validated' => 'Validated',
                                'rejected' => 'Rejected',
                            ])
                            ->default('submitted'),
                            Tables\Filters\SelectFilter::make('report_type_id')
                            ->label('Report Type')
                            ->relationship('reportType', 'name'),
                        ])
                        ->actions([
                            Tables\Actions\Action::make('view')
                            ->label('Review')
                            ->icon('heroicon-o-eye')
                            ->color('primary')
                            ->url(fn($record) => ViewSubmission::getUrl(['period' => $record->id])),
                            Tables\Actions\Action::make('validate')
                            ->label('Validate')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading('Validate Report')
                            ->modalDescription('Mark this report as validated. This will make it eligible for DHIS2 sync.')
                            ->visible(fn($record) => $record->canBeValidated())
                            ->action(function ($record) {
                                try {
                                    app(IndicatorReportingService::class)->validate($record, auth()->id());
                                    Notification::make()
                                            ->title('Report validated')
                                            ->body("Report for {$record->facility->name} ({$record->period_label}) has been validated.")
                                            ->success()
                                            ->send();
                                } catch (\Exception $e) {
                                    Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                                }
                            }),
                            Tables\Actions\Action::make('reject')
                            ->label('Reject')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->form([
                                \Filament\Forms\Components\Textarea::make('rejection_reason')
                                ->label('Reason for Rejection')
                                ->required()
                                ->rows(3)
                                ->placeholder('Explain what needs to be corrected…'),
                            ])
                            ->visible(fn($record) => $record->canBeValidated())
                            ->action(function ($record, array $data) {
                                try {
                                    app(IndicatorReportingService::class)->reject(
                                            $record,
                                            auth()->id(),
                                            $data['rejection_reason']
                                    );
                                    Notification::make()
                                            ->title('Report rejected')
                                            ->body("Report returned to {$record->facility->name} for corrections.")
                                            ->warning()
                                            ->send();
                                } catch (\Exception $e) {
                                    Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                                }
                            }),
                        ])
                        ->emptyStateHeading('No reports pending validation')
                        ->emptyStateDescription('Submitted reports will appear here for review.')
                        ->emptyStateIcon('heroicon-o-clipboard-document-check')
                        ->defaultSort('submitted_at', 'desc');
    }

    public function getTitle(): string {
        return 'Indicator Validation Queue';
    }
}
