<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonthlyReportResource\Pages;
use App\Models\MonthlyReport;
use App\Models\ReportTemplate;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;

class MonthlyReportResource extends Resource {

    protected static ?string $model = MonthlyReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Report Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Performance Tracker';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_monthly::report');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Report Information')
                            ->schema([
                                Forms\Components\Select::make('facility_id')
                                ->label('Facility')
                                ->relationship('facility', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn(string $operation): bool => $operation === 'edit'),
                                Forms\Components\Select::make('report_template_id')
                                ->label('Report Template')
                                ->relationship('reportTemplate', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn(string $operation): bool => $operation === 'edit')
                                ->helperText(fn (): ?\Illuminate\Support\HtmlString => \App\Models\ReportTemplate::active()->count() > 0
                                    ? null
                                    : new \Illuminate\Support\HtmlString(
                                        'No report templates yet — <a href="' . \App\Filament\Resources\ReportTemplateResource::getUrl('create') . '" class="underline">create one first</a>.'
                                    ))
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state, string $operation) {
                                    if ($operation === 'create' && $state) {
                                        // Load template indicators and create indicator value entries
                                        $template = \App\Models\ReportTemplate::with('indicators')->find($state);
                                        if ($template) {
                                            $indicatorValues = [];
                                            foreach ($template->indicators as $index => $indicator) {
                                                $indicatorValues[] = [
                                                    'indicator_id' => $indicator->id,
                                                    'numerator' => null,
                                                    'denominator' => null,
                                                    'calculated_value' => null,
                                                    'comments' => null,
                                                ];
                                            }
                                            $set('indicatorValues', $indicatorValues);
                                        }
                                    }
                                }),
                                Forms\Components\DatePicker::make('reporting_period')
                                ->label('Reporting Period')
                                ->displayFormat('F Y')
                                ->format('Y-m-01')
                                ->required()
                                ->disabled(fn(string $operation): bool => $operation === 'edit')
                                ->helperText('Select the month and year for this report'),
                                Forms\Components\Select::make('status')
                                ->options([
                                    'draft' => 'Draft',
                                    'submitted' => 'Submitted',
                                    'approved' => 'Approved',
                                    'rejected' => 'Rejected',
                                ])
                                ->default('draft')
                                ->required()
                                /* ->disabled(fn ($record): bool => 
                                  !auth()->user()->can('delete_any_monthly::report') &&
                                  $record && $record->status === 'approved') */,
                                Forms\Components\Textarea::make('comments')
                                ->rows(3)
                                ->columnSpanFull(),
                            ])
                            ->columns(2),
                            Forms\Components\Section::make('Indicator Values')
                            ->description('Enter the data for each indicator. Values will be calculated automatically.')
                            ->schema([
                                Forms\Components\Repeater::make('indicatorValues')
                                ->schema([
                                    Forms\Components\Hidden::make('indicator_id'),
                                    Forms\Components\Placeholder::make('indicator_info')
                                    ->label('')
                                    ->content(function (Forms\Get $get, $record) {
                                        $indicatorId = $record ? $record->indicator_id : $get('indicator_id');
                                        $indicator = \App\Models\Indicator::find($indicatorId);

                                        if (!$indicator)
                                            return 'Loading indicator...';

                                        $html = '<div class="space-y-2">';
                                        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white">' . $indicator->name . '</h4>';

                                        if ($indicator->description) {
                                            $html .= '<p class="text-sm text-gray-600 dark:text-gray-400">' . $indicator->description . '</p>';
                                        }

                                        $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">';
                                        $html .= '<div><span class="font-medium text-blue-600">Numerator:</span> ' . $indicator->numerator_description . '</div>';

                                        if ($indicator->calculation_type !== 'count' && $indicator->denominator_description) {
                                            $html .= '<div><span class="font-medium text-green-600">Denominator:</span> ' . $indicator->denominator_description . '</div>';
                                        }
                                        $html .= '</div>';

                                        /* if ($indicator->target_value) {
                                          $targetSuffix = match ($indicator->calculation_type) {
                                          'percentage' => '%',
                                          'rate' => ' per 1000',
                                          default => '',
                                          };
                                          $html .= '<div class="text-xs"><span class="font-medium text-orange-600">Target:</span> ' . $indicator->target_value . $targetSuffix . '</div>';
                                          }

                                          $html .= '</div>'; */

                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->columnSpanFull(),
                                    Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('numerator')
                                        ->label('Numerator')
                                        ->numeric()
                                        ->minValue(0),
                                        /* ->live(debounce: 500)
                                          ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state, $record) {
                                          $indicatorId = $record ? $record->indicator_id : $get('indicator_id');
                                          if ($indicatorId && $state !== null) {
                                          $indicator = \App\Models\Indicator::find($indicatorId);
                                          if ($indicator) {
                                          $denominator = $get('denominator');
                                          $calculated = $indicator->calculateValue($state, $denominator);
                                          $set('calculated_value', $calculated);
                                          }
                                          }
                                          }), */
                                        Forms\Components\TextInput::make('denominator')
                                        ->label('Denominator')
                                        ->numeric()
                                        ->minValue(0)
                                        /* ->live(debounce: 500)
                                          ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state, $record) {
                                          $indicatorId = $record ? $record->indicator_id : $get('indicator_id');
                                          if ($indicatorId && $get('numerator') !== null) {
                                          $indicator = \App\Models\Indicator::find($indicatorId);
                                          if ($indicator) {
                                          $numerator = $get('numerator');
                                          $calculated = $indicator->calculateValue($numerator, $state);
                                          $set('calculated_value', $calculated);
                                          }
                                          }
                                          }) */
                                        ->hidden(function (Forms\Get $get, $record) {
                                            $indicatorId = $record ? $record->indicator_id : $get('indicator_id');
                                            if ($indicatorId) {
                                                $indicator = \App\Models\Indicator::find($indicatorId);
                                                return $indicator?->calculation_type === 'count';
                                            }
                                            return false;
                                        }),
                                            /* Forms\Components\TextInput::make('calculated_value')
                                              ->label('Result')
                                              ->disabled()
                                              ->dehydrated()
                                              ->formatStateUsing(function ($state, Forms\Get $get, $record): string {
                                              if ($state === null) return '';
                                              $indicatorId = $record ? $record->indicator_id : $get('indicator_id');
                                              if ($indicatorId) {
                                              $indicator = \App\Models\Indicator::find($indicatorId);
                                              if ($indicator) {
                                              return match ($indicator->calculation_type) {
                                              'percentage' => number_format($state, 1) . '%',
                                              'rate' => number_format($state, 1) . ' per 1000',
                                              'ratio' => number_format($state, 2) . ':1',
                                              default => number_format($state, 0),
                                              };
                                              }
                                              }
                                              return '';
                                              }) */
                                            /* ->badge()
                                              ->color(function ($state, Forms\Get $get, $record): string {
                                              if (!$state) return 'gray';
                                              $indicatorId = $record ? $record->indicator_id : $get('indicator_id');
                                              $indicator = \App\Models\Indicator::find($indicatorId);
                                              if (!$indicator || !$indicator->target_value) return 'gray';
                                              return $state >= $indicator->target_value ? 'success' : 'danger';
                                              }), */
                                    ]),
                                    Forms\Components\Textarea::make('comments')
                                    ->label('Comments')
                                    ->rows(2)
                                    ->placeholder('Add any relevant comments or explanations...')
                                    ->columnSpanFull(),
                                ])
                                ->itemLabel(function (array $state, $record): ?string {
                                    if ($record && $record->indicator) {
                                        return $record->indicator->name;
                                    }
                                    if (!empty($state['indicator_id'])) {
                                        $indicator = \App\Models\Indicator::find($state['indicator_id']);
                                        return $indicator?->name ?? 'Unknown Indicator';
                                    }
                                    return 'Indicator';
                                })
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->collapsible()
                                ->cloneable(false)
                                ->defaultItems(0)
                                ->relationship('indicatorValues'), // Only use relationship on edit
                            ])
                            ->visible(function (string $operation, Forms\Get $get) {
                                // Show if editing OR if creating and template is selected
                                return $operation === 'edit' || ($operation === 'create' && $get('report_template_id'));
                            }),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('facility.name')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('reportTemplate.name')
                            ->searchable()
                            ->sortable()
                            ->wrap(),
                            Tables\Columns\TextColumn::make('reporting_period')
                            ->date('F Y')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->color(function (string $state): string {
                                return match ($state) {
                                    'draft' => 'gray',
                                    'submitted' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    default => 'gray',
                                };
                            }),
                            Tables\Columns\TextColumn::make('completion_percentage')
                            ->label('Progress')
                            ->formatStateUsing(function ($state): string {
                                return $state ? number_format($state, 0) . '%' : '0%';
                            })
                            ->color(function ($state): string {
                                if (!$state)
                                    return 'gray';
                                return match (true) {
                                    $state >= 80 => 'success',
                                    $state >= 50 => 'warning',
                                    default => 'danger',
                                };
                            }),
                            Tables\Columns\TextColumn::make('createdBy.name')
                            ->label('Created By')
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\TextColumn::make('created_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\TextColumn::make('submitted_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('facility_id')
                            ->relationship('facility', 'name')
                            ->searchable()
                            ->preload(),
                            Tables\Filters\SelectFilter::make('report_template_id')
                            ->relationship('reportTemplate', 'name'),
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'submitted' => 'Submitted',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ]),
                            Tables\Filters\Filter::make('reporting_period')
                            ->form([
                                Forms\Components\DatePicker::make('from')
                                ->label('From Month'),
                                Forms\Components\DatePicker::make('until')
                                ->label('Until Month'),
                            ])
                            ->query(function (Builder $query, array $data): Builder {
                                return $query
                                                ->when(
                                                        $data['from'],
                                                        fn(Builder $query, $date): Builder => $query->where('reporting_period', '>=', $date),
                                                )
                                                ->when(
                                                        $data['until'],
                                                        fn(Builder $query, $date): Builder => $query->where('reporting_period', '<=', $date),
                                                );
                            }),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make()
                            ->visible(function (?MonthlyReport $record): bool {
                                if (!$record)
                                    return false;

                                // Check if user can access this facility
                                if (!auth()->user()->canAccessFacility($record->facility_id)) {
                                    return false;
                                }

                                // Check if report can be edited based on status
                                return $record->canEdit();
                            }),
                            Tables\Actions\Action::make('submit')
                            ->icon('heroicon-o-paper-airplane')
                            ->color('warning')
                            ->action(function (MonthlyReport $record) {
                                $record->update([
                                    'status' => 'submitted',
                                    'submitted_at' => now(),
                                ]);
                            })
                            ->visible(function (?MonthlyReport $record): bool {
                                return $record ? $record->canSubmit() : true;
                            })
                            ->requiresConfirmation(),
                            Tables\Actions\Action::make('approve')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->action(function (MonthlyReport $record) {
                                $record->update([
                                    'status' => 'approved',
                                    'approved_at' => now(),
                                    'approved_by' => auth()->id(),
                                ]);
                            })
                            /* ->visible(function (?MonthlyReport $record): bool {
                              return $record &&
                              $record->canApprove() &&
                              auth()->user()->can('delete_any_monthly::report');
                              }) */
                            ->requiresConfirmation(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
                        ])
                        ->defaultSort('reporting_period', 'desc');
    }

    public static function getRelations(): array {
        return [
                //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListMonthlyReports::route('/'),
            'create' => Pages\CreateMonthlyReport::route('/create'),
            'view' => Pages\ViewMonthlyReport::route('/{record}'),
            'edit' => Pages\EditMonthlyReport::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->with(['facility', 'reportTemplate', 'createdBy'])
                        ->when(
                            !auth()->user()->isAboveSite(),
                            fn (Builder $query) => $query->whereIn('facility_id', auth()->user()->scopedFacilityIds())
                        );
    }
}
