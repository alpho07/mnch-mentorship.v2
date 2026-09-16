<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IndicatorReportTypeResource\Pages;
use App\Filament\Resources\IndicatorReportTypeResource\RelationManagers;
use App\Models\Indicators\IndicatorFrequency;
use App\Models\Indicators\IndicatorReportType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table; 

class IndicatorReportTypeResource extends Resource {

    protected static ?string $model = IndicatorReportType::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Report Types';
    protected static ?string $navigationGroup = 'Indicator Catalog';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_indicator::report::type');}

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('view_any_indicator::report::type');}

    // ──────────────────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form {
        return $form->schema([
                    Forms\Components\Group::make()->schema([
                                Forms\Components\Section::make('Basic Information')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('code')
                                        ->label('Code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(20)
                                        ->placeholder('e.g. NEWBORN')
                                        ->helperText('Short unique identifier used in DHIS2 mappings.'),
                                        Forms\Components\TextInput::make('name')
                                        ->label('Name')
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('e.g. Newborn Indicators'),
                                    ]),
                                    Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                ]),
                                Forms\Components\Section::make('DHIS2 Configuration')
                                ->description('Leave blank until DHIS2 UIDs are available.')
                                ->schema([
                                    Forms\Components\TextInput::make('dhis2_dataset_id')
                                    ->label('DHIS2 Dataset ID')
                                    ->maxLength(100)
                                    ->placeholder('e.g. BfMAe6Itzgt')
                                    ->helperText('The DHIS2 dataset UID this report type maps to.'),
                                ]),
                    ])->columnSpan(2),
                    Forms\Components\Group::make()->schema([
                                Forms\Components\Section::make('Display')
                                ->schema([
                                    Forms\Components\ColorPicker::make('color')
                                    ->label('Theme Color')
                                    ->default('#3b82f6'),
                                    Forms\Components\TextInput::make('icon')
                                    ->label('Heroicon Name')
                                    ->placeholder('heroicon-o-heart')
                                    ->helperText('Full heroicon class name for sidebar/badge display.'),
                                    Forms\Components\TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0),
                                    Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                ]),
                                Forms\Components\Section::make('Frequencies')
                                ->description('Select which reporting frequencies apply to this report type.')
                                ->schema([
                                    Forms\Components\CheckboxList::make('frequencies')
                                    ->label('')
                                    ->relationship('frequencies', 'name')
                                    ->columns(1),
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
                            Tables\Columns\TextColumn::make('groups_count')
                            ->label('Groups')
                            ->counts('groups')
                            ->badge()
                            ->color('info')
                            ->alignCenter(),
                            Tables\Columns\TextColumn::make('frequencies.name')
                            ->label('Frequencies')
                            ->badge()
                            ->separator(','),
                            Tables\Columns\TextColumn::make('dhis2_dataset_id')
                            ->label('DHIS2 Dataset')
                            ->default('—')
                            ->copyable()
                            ->toggleable(),
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
                            Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                        ])
                        ->actions([
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make()
                            ->before(function ($record, $action) {
                                if ($record->groups()->exists()) {
                                    \Filament\Notifications\Notification::make()
                                            ->title('Cannot delete')
                                            ->body('This report type has indicator groups. Remove them first.')
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
            'index' => Pages\ListIndicatorReportTypes::route('/'),
            'create' => Pages\CreateIndicatorReportType::route('/create'),
            'edit' => Pages\EditIndicatorReportType::route('/{record}/edit'),
        ];
    }
}
