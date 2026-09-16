<?php

namespace App\Filament\Widgets\Indicators;

use App\Filament\Pages\Indicators\FillReport;
use App\Filament\Pages\Indicators\ViewSubmission;
use App\Models\Indicators\IndicatorReportPeriod;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentSubmissionsWidget extends BaseWidget {

    protected static ?string $heading = 'Recent Indicator Reports';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool {
        return auth()->check() && auth()->user()->facility_id !== null;
    }

    public function table(Table $table): Table {
        $facilityId = auth()->user()->facility_id;

        return $table
                        ->query(
                                IndicatorReportPeriod::query()
                                ->where('facility_id', $facilityId)
                                ->with(['reportType', 'frequency'])
                                ->orderByDesc('updated_at')
                                ->limit(10)
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('reportType.name')
                            ->label('Report Type')
                            ->badge()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('period_label')
                            ->label('Period')
                            ->getStateUsing(fn($record) => $record->period_label),
                            Tables\Columns\TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn($record) => $record->getStatusColor())
                            ->icon(fn($record) => $record->getStatusIcon())
                            ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state))),
                            Tables\Columns\TextColumn::make('completion')
                            ->label('Completion')
                            ->getStateUsing(function ($record) {
                                $stats = app(\App\Services\Indicators\IndicatorReportingService::class)
                                        ->getOverallCompletion($record);
                                return $stats['percentage'] . '%';
                            })
                            ->badge()
                            ->color(fn($record) => (
                                    app(\App\Services\Indicators\IndicatorReportingService::class)
                                    ->getOverallCompletion($record)['percentage']
                                    ) === 100 ? 'success' : 'warning'),
                            Tables\Columns\TextColumn::make('updated_at')
                            ->label('Last Updated')
                            ->since()
                            ->sortable(),
                        ])
                        ->actions([
                            Tables\Actions\Action::make('open')
                            ->label('Open')
                            ->icon('heroicon-o-arrow-right')
                            ->color('primary')
                            ->url(fn($record) => $record->isEditable() ? FillReport::getUrl(['period' => $record->id]) : ViewSubmission::getUrl(['period' => $record->id])
                            ),
                        ])
                        ->emptyStateHeading('No reports yet')
                        ->emptyStateDescription('Start by selecting a reporting period above.')
                        ->emptyStateIcon('heroicon-o-clipboard-document-list')
                        ->paginated(false);
    }
}
