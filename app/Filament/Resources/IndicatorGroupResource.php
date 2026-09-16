<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IndicatorGroupResource\Pages;
use App\Filament\Resources\IndicatorGroupResource\RelationManagers;
use App\Models\Indicators\IndicatorGroup;
use App\Models\Indicators\IndicatorReportType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IndicatorGroupResource extends Resource {

    protected static ?string $model = IndicatorGroup::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Indicator Groups';
    protected static ?string $navigationGroup = 'Indicator Catalog';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_indicator::group');}

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('view_any_indicator::group');}

    // ──────────────────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form {
        return $form->schema([
                    Forms\Components\Group::make()->schema([
                                Forms\Components\Section::make('Group Details')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\Select::make('report_type_id')
                                        ->label('Report Type')
                                        ->relationship('reportType', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live(),
                                        Forms\Components\TextInput::make('code')
                                        ->label('Group Code')
                                        ->required()
                                        ->maxLength(20)
                                        ->placeholder('e.g. NB-M1')
                                        ->helperText('Short unique code for this group.'),
                                    ]),
                                    Forms\Components\TextInput::make('name')
                                    ->label('Group Name')
                                    ->required()
                                    ->maxLength(150)
                                    ->placeholder('e.g. Module 1: Infection Prevention & Control')
                                    ->columnSpanFull(),
                                    Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                ]),
                    ])->columnSpan(2),
                    Forms\Components\Group::make()->schema([
                                Forms\Components\Section::make('Settings')
                                ->schema([
                                    Forms\Components\TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers appear first.'),
                                    Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                ]),
                                Forms\Components\Section::make('DHIS2')
                                ->schema([
                                    Forms\Components\TextInput::make('dhis2_section_id')
                                    ->label('DHIS2 Section ID')
                                    ->maxLength(100)
                                    ->placeholder('e.g. Mf8VPMbSrIe')
                                    ->helperText('Optional. Maps to a DHIS2 dataset section.'),
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
                            ->sortable(),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Name')
                            ->searchable()
                            ->sortable()
                            ->description(fn($record) => $record->description),
                            Tables\Columns\TextColumn::make('reportType.name')
                            ->label('Report Type')
                            ->badge()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('indicators_count')
                            ->label('Indicators')
                            ->counts('indicators')
                            ->badge()
                            ->color('info')
                            ->alignCenter(),
                            Tables\Columns\TextColumn::make('dhis2_section_id')
                            ->label('DHIS2 Section')
                            ->default('—')
                            ->copyable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('sort_order')
                            ->label('Order')
                            ->sortable()
                            ->alignCenter(),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('report_type_id')
                            ->label('Report Type')
                            ->relationship('reportType', 'name'),
                            Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                        ])
                        ->actions([
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make()
                            ->before(function ($record, $action) {
                                if ($record->indicators()->exists()) {
                                    \Filament\Notifications\Notification::make()
                                            ->title('Cannot delete')
                                            ->body('This group has indicators. Remove or reassign them first.')
                                            ->danger()->send();
                                    $action->cancel();
                                }
                            }),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
                        ])
                        ->defaultSort('sort_order')
                        ->reorderable('sort_order');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Pages
    // ──────────────────────────────────────────────────────────────────────────

    public static function getRelationManagers(): array {
        return [
            RelationManagers\IndicatorsRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListIndicatorGroups::route('/'),
            'create' => Pages\CreateIndicatorGroup::route('/create'),
            'edit' => Pages\EditIndicatorGroup::route('/{record}/edit'),
        ];
    }
}
