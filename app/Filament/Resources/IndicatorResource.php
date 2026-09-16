<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IndicatorResource\Pages;
use App\Models\Indicators\Indicator;
use App\Models\Indicators\IndicatorGroup;
use App\Models\Indicators\IndicatorReportType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IndicatorResource extends Resource {

    protected static ?string $model = Indicator::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Indicators';
    protected static ?string $navigationGroup = 'Indicator Catalog';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_indicator');}

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('view_any_indicator');}

    // ──────────────────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form {
        return $form->schema([
                    Forms\Components\Group::make()->schema([
                        // ── Identity ────────────────────────────────────────────────
                                Forms\Components\Section::make('Identity')
                                ->schema([
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\Select::make('report_type_id')
                                        ->label('Report Type')
                                        ->options(IndicatorReportType::active()->pluck('name', 'id'))
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn(Forms\Set $set) => $set('group_id', null))
                                        ->helperText('Newborn or Paediatric.'),
                                        Forms\Components\Select::make('group_id')
                                        ->label('Module Group')
                                        ->options(fn(Get $get) =>
                                                $get('report_type_id') ? IndicatorGroup::where('report_type_id', $get('report_type_id'))
                                                ->where('is_active', true)
                                                ->orderBy('sort_order')
                                                ->pluck('name', 'id') : []
                                        )
                                        ->required()
                                        ->searchable()
                                        ->helperText('Module this indicator belongs to.'),
                                        Forms\Components\Select::make('parent_indicator_id')
                                        ->label('Parent Indicator')
                                        ->options(fn(Get $get) =>
                                                $get('group_id') ? Indicator::where('group_id', $get('group_id'))
                                                ->whereNull('parent_indicator_id')
                                                ->orderBy('sort_order')
                                                ->pluck('name', 'id') : []
                                        )
                                        ->searchable()
                                        ->nullable()
                                        ->placeholder('None (top-level)')
                                        ->helperText('Set for weight/gestational age sub-bands.'),
                                    ]),
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('code')
                                        ->label('Indicator Code')
                                        ->required()
                                        ->maxLength(30)
                                        ->placeholder('e.g. NB-M1-01')
                                        ->helperText('Unique code used in reports and exports.'),
                                        Forms\Components\TextInput::make('short_name')
                                        ->label('Short Name')
                                        ->maxLength(100)
                                        ->placeholder('e.g. KMC initiation <2hrs')
                                        ->helperText('Abbreviated name for tables and badges.'),
                                    ]),
                                    Forms\Components\TextInput::make('name')
                                    ->label('Full Indicator Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                    Forms\Components\Textarea::make('definition')
                                    ->label('Definition')
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->placeholder('Clinical or operational definition of this indicator…')
                                    ->columnSpanFull(),
                                ]),
                        // ── Measurement ─────────────────────────────────────────────
                        Forms\Components\Section::make('Measurement')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\Select::make('indicator_type')
                                        ->label('Indicator Type')
                                        ->options([
                                            'proportion' => 'Proportion (Numerator / Denominator)',
                                            'count' => 'Count',
                                            'yes_no' => 'Yes / No',
                                            'rate' => 'Rate',
                                        ])
                                        ->required()
                                        ->live()
                                        ->default('proportion'),
                                        Forms\Components\Select::make('category')
                                        ->label('Category')
                                        ->options([
                                            'process' => 'Process',
                                            'output' => 'Output',
                                            'outcome' => 'Outcome',
                                            'satisfaction' => 'Satisfaction',
                                        ])
                                        ->required()
                                        ->default('process'),
                                    ]),
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('numerator_label')
                                        ->label('Numerator Label')
                                        ->maxLength(200)
                                        ->placeholder('e.g. Number of eligible neonates who received KMC')
                                        ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate'])),
                                        Forms\Components\TextInput::make('denominator_label')
                                        ->label('Denominator Label')
                                        ->maxLength(200)
                                        ->placeholder('e.g. Total eligible neonates admitted')
                                        ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate'])),
                                    ]),
                                ]),
                        // ── Source & Hints ───────────────────────────────────────────
                        Forms\Components\Section::make('Source & Display')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('source_document')
                                        ->label('Source Document')
                                        ->maxLength(150)
                                        ->placeholder('e.g. MOH 377 — Maternity Register'),
                                        Forms\Components\TextInput::make('source_document_code')
                                        ->label('Source Document Code')
                                        ->maxLength(50)
                                        ->placeholder('e.g. MOH_377'),
                                    ]),
                                    Forms\Components\Textarea::make('display_hint')
                                    ->label('Data Entry Hint')
                                    ->rows(2)
                                    ->maxLength(300)
                                    ->placeholder('Guidance shown to the user when filling in this indicator…')
                                    ->columnSpanFull(),
                                ]),
                    ])->columnSpan(2),
                    Forms\Components\Group::make()->schema([
                        // ── Settings ────────────────────────────────────────────────
                                Forms\Components\Section::make('Settings')
                                ->schema([
                                    Forms\Components\TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0),
                                    Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                    Forms\Components\Toggle::make('has_numerator')
                                    ->label('Has Numerator Field')
                                    ->default(true)
                                    ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate'])),
                                    Forms\Components\Toggle::make('has_denominator')
                                    ->label('Has Denominator Field')
                                    ->default(true)
                                    ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate'])),
                                ]),
                        // ── DHIS2 UID Mapping ────────────────────────────────────────
                        Forms\Components\Section::make('DHIS2 UID Mapping')
                                ->description('Leave blank until DHIS2 UIDs are available. These enable automated sync.')
                                ->schema([
                                    Forms\Components\TextInput::make('dhis2_numerator_uid')
                                    ->label('Numerator Data Element UID')
                                    ->maxLength(11)
                                    ->placeholder('e.g. AbCdEfGhIjK')
                                    ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate']))
                                    ->helperText('11-character DHIS2 data element UID.'),
                                    Forms\Components\TextInput::make('dhis2_denominator_uid')
                                    ->label('Denominator Data Element UID')
                                    ->maxLength(11)
                                    ->placeholder('e.g. AbCdEfGhIjK')
                                    ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate']))
                                    ->helperText('11-character DHIS2 data element UID.'),
                                    Forms\Components\TextInput::make('dhis2_count_uid')
                                    ->label('Count Data Element UID')
                                    ->maxLength(11)
                                    ->placeholder('e.g. AbCdEfGhIjK')
                                    ->visible(fn(Get $get) => $get('indicator_type') === 'count')
                                    ->helperText('11-character DHIS2 data element UID.'),
                                    Forms\Components\TextInput::make('dhis2_indicator_uid')
                                    ->label('Computed Indicator UID')
                                    ->maxLength(11)
                                    ->placeholder('e.g. AbCdEfGhIjK')
                                    ->helperText('Optional. The DHIS2 indicator UID (not data element).'),
                                ]),
                    ])->columnSpan(1),
                ])->columns(3);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('code')
                            ->label('Code')
                            ->badge()
                            ->color('gray')
                            ->searchable()
                            ->sortable()
                            ->copyable(),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Indicator')
                            ->searchable()
                            ->sortable()
                            ->wrap()
                            ->description(fn($record) => $record->short_name),
                            Tables\Columns\TextColumn::make('group.name')
                            ->label('Module')
                            ->sortable()
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\TextColumn::make('group.reportType.name')
                            ->label('Type')
                            ->sortable()
                            ->badge(),
                            Tables\Columns\BadgeColumn::make('indicator_type')
                            ->label('Type')
                            ->colors([
                                'primary' => 'proportion',
                                'info' => 'count',
                                'success' => 'yes_no',
                                'warning' => 'rate',
                            ])
                            ->formatStateUsing(fn($state) => ucfirst($state)),
                            Tables\Columns\BadgeColumn::make('category')
                            ->label('Category')
                            ->colors([
                                'warning' => 'process',
                                'info' => 'output',
                                'success' => 'outcome',
                                'gray' => 'satisfaction',
                            ])
                            ->formatStateUsing(fn($state) => ucfirst($state)),
                            // DHIS2 mapping status — green tick if all required UIDs are filled
                            Tables\Columns\IconColumn::make('dhis2_ready')
                            ->label('DHIS2 Ready')
                            ->getStateUsing(fn($record) => $record->isDhis2Ready())
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('gray'),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('sort_order')
                            ->label('Order')
                            ->sortable()
                            ->alignCenter()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('report_type')
                            ->label('Report Type')
                            ->relationship('group.reportType', 'name')
                            ->query(fn(Builder $query, array $data) =>
                                    $data['value'] ? $query->whereHas('group', fn($q) => $q->where('report_type_id', $data['value'])) : $query
                            ),
                            Tables\Filters\SelectFilter::make('group_id')
                            ->label('Module Group')
                            ->relationship('group', 'name'),
                            Tables\Filters\SelectFilter::make('indicator_type')
                            ->label('Indicator Type')
                            ->options([
                                'proportion' => 'Proportion',
                                'count' => 'Count',
                                'yes_no' => 'Yes / No',
                                'rate' => 'Rate',
                            ]),
                            Tables\Filters\SelectFilter::make('category')
                            ->label('Category')
                            ->options([
                                'process' => 'Process',
                                'output' => 'Output',
                                'outcome' => 'Outcome',
                                'satisfaction' => 'Satisfaction',
                            ]),
                            Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                            Tables\Filters\Filter::make('dhis2_unmapped')
                            ->label('DHIS2 Not Mapped')
                            ->query(fn(Builder $query) => $query->where(function ($q) {
                                        $q->whereNull('dhis2_numerator_uid')
                                                ->orWhereNull('dhis2_denominator_uid');
                                    }))
                            ->toggle(),
                            Tables\Filters\Filter::make('top_level')
                            ->label('Top-level Only')
                            ->query(fn(Builder $query) => $query->whereNull('parent_indicator_id'))
                            ->toggle(),
                        ])
                        ->actions([
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\ReplicateAction::make()
                            ->label('Duplicate')
                            ->mutateRecordDataUsing(function (array $data): array {
                                $data['code'] = $data['code'] . '_COPY';
                                $data['name'] = $data['name'] . ' (Copy)';
                                // Clear DHIS2 UIDs on duplicate
                                $data['dhis2_numerator_uid'] = null;
                                $data['dhis2_denominator_uid'] = null;
                                $data['dhis2_count_uid'] = null;
                                $data['dhis2_indicator_uid'] = null;
                                return $data;
                            }),
                            Tables\Actions\DeleteAction::make()
                            ->before(function ($record, $action) {
                                if ($record->values()->exists()) {
                                    \Filament\Notifications\Notification::make()
                                            ->title('Cannot delete')
                                            ->body('This indicator has report data. Deactivate it instead.')
                                            ->danger()->send();
                                    $action->cancel();
                                }
                            }),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                // Bulk activate
                                Tables\Actions\BulkAction::make('activate')
                                ->label('Activate Selected')
                                ->icon('heroicon-o-check')
                                ->color('success')
                                ->action(fn($records) => $records->each->update(['is_active' => true]))
                                ->deselectRecordsAfterCompletion(),
                                // Bulk deactivate
                                Tables\Actions\BulkAction::make('deactivate')
                                ->label('Deactivate Selected')
                                ->icon('heroicon-o-x-mark')
                                ->color('warning')
                                ->requiresConfirmation()
                                ->action(fn($records) => $records->each->update(['is_active' => false]))
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
                        ])
                        ->defaultSort('sort_order')
                        ->reorderable('sort_order')
                        ->groups([
                            Tables\Grouping\Group::make('group.name')
                            ->label('Module Group')
                            ->collapsible(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Pages
    // ──────────────────────────────────────────────────────────────────────────

    public static function getPages(): array {
        return [
            'index' => Pages\ListIndicators::route('/'),
            'create' => Pages\CreateIndicator::route('/create'),
            'edit' => Pages\EditIndicator::route('/{record}/edit'),
        ];
    }
}
