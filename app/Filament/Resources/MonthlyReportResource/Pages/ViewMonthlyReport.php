<?php
// ViewMonthlyReport.php
namespace App\Filament\Resources\MonthlyReportResource\Pages;

use App\Filament\Resources\MonthlyReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\FontWeight;

class ViewMonthlyReport extends ViewRecord
{
    protected static string $resource = MonthlyReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn(): bool => $this->getRecord()->canEdit()),
            Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    $this->getRecord()->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => auth()->id(),
                    ]);
                })
                ->visible(fn(): bool =>
                $this->getRecord()->canApprove() &&
                    auth()->user()->can('delete_any_monthly::report'))
                ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Report Information')
                    ->schema([
                        TextEntry::make('facility.name')
                            ->label('Facility'),
                        TextEntry::make('reportTemplate.name')
                            ->label('Report Template'),
                        TextEntry::make('reporting_period')
                            ->date('F Y')
                            ->label('Reporting Period'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'draft' => 'gray',
                                'submitted' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                            }),
                        TextEntry::make('completion_percentage')
                            ->label('Completion')
                            ->formatStateUsing(fn($state): string => number_format($state, 0) . '%'),
                        TextEntry::make('createdBy.name')
                            ->label('Created By'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('submitted_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('approvedBy.name')
                            ->label('Approved By')
                            ->placeholder('-'),
                        TextEntry::make('approved_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                Section::make('Comments')
                    ->schema([
                        TextEntry::make('comments')
                            ->placeholder('No comments')
                            ->markdown(),
                    ])
                    ->visible(fn(): bool => !empty($this->getRecord()->comments)),

                Section::make('Indicator Values')
                    ->schema([
                        RepeatableEntry::make('indicatorValues')
                            ->schema([
                                TextEntry::make('indicator.name')
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('indicator.description')
                                    ->color('gray'),
                                TextEntry::make('numerator')
                                    ->placeholder('-'),
                                TextEntry::make('denominator')
                                    ->placeholder('-')
                                    ->visible(fn($record): bool =>
                                    $record->indicator->calculation_type !== 'count'),
                                TextEntry::make('formatted_value')
                                    ->label('Result')
                                    ->weight(FontWeight::SemiBold)
                                    ->color(function ($record): string {
                                        if (!$record->calculated_value || !$record->indicator->target_value) {
                                            return 'gray';
                                        }
                                        return $record->calculated_value >= $record->indicator->target_value ? 'success' : 'danger';
                                    }),
                                TextEntry::make('indicator.target_value')
                                    ->label('Target')
                                    ->formatStateUsing(fn($state, $record): string =>
                                    $state ? $state . match ($record->indicator->calculation_type) {
                                        'percentage' => '%',
                                        'rate' => ' per 1000',
                                        default => '',
                                    } : '-'),
                                TextEntry::make('comments')
                                    ->placeholder('No comments')
                                    ->columnSpanFull(),
                            ])
                            ->columns(6)
                            ->contained(false),
                    ]),
            ]);
    }
}
