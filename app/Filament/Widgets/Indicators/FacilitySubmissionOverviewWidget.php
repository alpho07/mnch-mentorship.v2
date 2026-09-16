<?php

namespace App\Filament\Widgets\Indicators;

use App\Filament\Pages\Indicators\ViewSubmission;
use App\Models\Facility;
use App\Models\Indicators\FacilityIndicatorAssignment;
use App\Models\Indicators\IndicatorReportPeriod;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class FacilitySubmissionOverviewWidget extends BaseWidget {

    protected static ?string $heading = 'Facility Reporting Overview — Current Period';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool {
        return auth()->check() && auth()->user()->can('widget_FacilitySubmissionOverviewWidget');
    }

    public function table(Table $table): Table {
        $user = auth()->user();
        $countyId = null;

        if ($user->hasRole('county_mentor_lead')) {
            $countyId = $user->facility?->subcounty?->county_id ?? $user->counties()->first()?->id;
        }

        $currentYear = now()->year;
        $currentMonth = now()->month;

        return $table
                        ->query(
                                Facility::query()
                                ->whereHas('indicatorAssignment', fn($q) => $q->where('is_locked', true))
                                ->with([
                                    'subcounty.county',
                                    'indicatorAssignment',
                                    'indicatorReportPeriods' => fn($q) => $q
                                    ->where('period_year', $currentYear)
                                    ->where('period_month', $currentMonth),
                                ])
                                ->when($countyId, fn($q) =>
                                        $q->whereHas('subcounty', fn($sq) => $sq->where('county_id', $countyId))
                                )
                                ->orderBy('name')
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('name')
                            ->label('Facility')
                            ->searchable()
                            ->sortable()
                            ->description(fn($record) =>
                                    $record->subcounty?->county?->name
                                    . ($record->mfl_code ? ' · MFL ' . $record->mfl_code : '')
                            ),
                            Tables\Columns\TextColumn::make('subcounty.county.name')
                            ->label('County')
                            ->sortable()
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('report_types_count')
                            ->label('Report Types')
                            ->getStateUsing(fn($record) =>
                                    count($record->indicatorAssignment?->enabled_report_types ?? [])
                            )
                            ->badge()
                            ->color('primary')
                            ->alignCenter(),
                            // Newborn status for current month
                            Tables\Columns\TextColumn::make('newborn_status')
                            ->label('Newborn — ' . now()->format('M Y'))
                            ->getStateUsing(fn($record) =>
                                    $this->getPeriodStatus($record, 1, $currentYear, $currentMonth)
                            )
                            ->badge()
                            ->color(fn($state) => $this->statusColor($state)),
                            // Paediatric status for current month
                            Tables\Columns\TextColumn::make('paediatric_status')
                            ->label('Paediatric — ' . now()->format('M Y'))
                            ->getStateUsing(fn($record) =>
                                    $this->getPeriodStatus($record, 2, $currentYear, $currentMonth)
                            )
                            ->badge()
                            ->color(fn($state) => $this->statusColor($state)),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('county')
                            ->label('County')
                            ->relationship('subcounty.county', 'name')
                            ->visible(fn() => auth()->user()->can('view_any_county')),
                            Tables\Filters\SelectFilter::make('submission_status')
                            ->label('Reporting Status')
                            ->options([
                                'not_started' => 'Not Started',
                                'draft' => 'Draft',
                                'submitted' => 'Submitted',
                                'validated' => 'Validated',
                                'rejected' => 'Rejected',
                            ])
                            ->query(function (Builder $query, array $data) use ($currentYear, $currentMonth) {
                                if (!$data['value'])
                                    return $query;

                                if ($data['value'] === 'not_started') {
                                    return $query->whereDoesntHave('indicatorReportPeriods', fn($q) =>
                                                            $q->where('period_year', $currentYear)
                                                            ->where('period_month', $currentMonth)
                                            );
                                }

                                return $query->whereHas('indicatorReportPeriods', fn($q) =>
                                                        $q->where('period_year', $currentYear)
                                                        ->where('period_month', $currentMonth)
                                                        ->where('status', $data['value'])
                                        );
                            }),
                        ])
                        ->actions([
                            Tables\Actions\Action::make('view_report')
                            ->label('View')
                            ->icon('heroicon-o-eye')
                            ->color('gray')
                            ->visible(fn($record) =>
                                    $record->indicatorReportPeriods->isNotEmpty()
                            )
                            ->url(fn($record) => ViewSubmission::getUrl([
                                        'period' => $record->indicatorReportPeriods->first()?->id,
                                    ])),
                        ])
                        ->emptyStateHeading('No configured facilities')
                        ->emptyStateDescription('Facilities will appear here once they complete setup.')
                        ->paginated([10, 25, 50]);
    }

    private function getPeriodStatus(Facility $facility, int $reportTypeId, int $year, int $month): string {
        // Check if this report type is enabled for the facility
        $assignment = $facility->indicatorAssignment;
        if (!$assignment || !in_array($reportTypeId, $assignment->enabled_report_types ?? [])) {
            return 'N/A';
        }

        $period = IndicatorReportPeriod::where([
                    'facility_id' => $facility->id,
                    'report_type_id' => $reportTypeId,
                    'period_year' => $year,
                    'period_month' => $month,
                ])->value('status');

        return $period ? ucfirst(str_replace('_', ' ', $period)) : 'Not Started';
    }

    private function statusColor(string $state): string {
        return match (strtolower($state)) {
            'validated', 'pushed to dhis2' => 'success',
            'submitted' => 'info',
            'draft' => 'warning',
            'rejected' => 'danger',
            'not started' => 'gray',
            default => 'gray',
        };
    }
}
