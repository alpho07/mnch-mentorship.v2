<?php
// RecentReportsWidget.php
namespace App\Filament\Widgets;

use App\Models\MonthlyReport;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentReportsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->query(
                MonthlyReport::query()
                    ->with(['facility', 'reportTemplate', 'createdBy'])
                    /*->when(
                        !$user->isAboveSite(),
                        fn ($query) => $query->whereIn('facility_id', $user->scopedFacilityIds())
                    )*/
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('facility.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reportTemplate.name')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('reporting_period')
                    ->date('F Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('completion_percentage')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state): string => number_format($state, 0) . '%'),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (MonthlyReport $record): string =>
                        route('filament.admin.resources.monthly-reports.view', $record))
                    ->icon('heroicon-m-eye'),
            ])
            ->heading('Recent Reports')
            ->description('Latest monthly reports across all facilities');
    }
}
