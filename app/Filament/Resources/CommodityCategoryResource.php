<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommodityCategoryResource\Pages;
use App\Filament\Resources\CommodityCategoryResource\RelationManagers;
use App\Models\CommodityCategory;
use App\Models\Commodity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class CommodityCategoryResource extends Resource {

    protected static ?string $model = CommodityCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Assessment Management';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Commodity Categories';
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_commodity::category');}

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('view_any_commodity::category');}

    // ─────────────────────────────────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Category Details')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('name')
                                    ->label('Category Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Medicines, Equipment, Consumables')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, $state, $context) {
                                        if ($context === 'create' && $state) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                    Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->alphaDash()
                                    ->helperText('Auto-generated. Used for report grouping.'),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(fn() => (CommodityCategory::max('order') ?? 0) + 10)
                                    ->required()
                                    ->helperText('Lower = shown first within each department tab'),
                                    Forms\Components\TextInput::make('icon')
                                    ->label('Heroicon Name')
                                    ->placeholder('heroicon-o-beaker')
                                    ->helperText('Optional section icon'),
                                ]),
                                Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(2)
                                ->placeholder('Optional description for this category')
                                ->columnSpanFull(),
                            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('order')
                            ->label('#')
                            ->sortable()
                            ->alignCenter()
                            ->width(50),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Category')
                            ->searchable()
                            ->sortable()
                            ->weight('semibold')
                            ->description(fn(CommodityCategory $record): string => $record->description ?? ''),
                            Tables\Columns\TextColumn::make('slug')
                            ->label('Slug')
                            ->badge()
                            ->color('gray')
                            ->searchable(),
                            Tables\Columns\TextColumn::make('commodities_count')
                            ->label('Commodities')
                            ->counts('commodities')
                            ->badge()
                            ->color('success')
                            ->alignCenter()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('active_commodities_count')
                            ->label('Active')
                            ->counts(['commodities' => fn($q) => $q->where('is_active', true)])
                            ->badge()
                            ->color('info')
                            ->alignCenter(),
                        ])
                        ->defaultSort('order')
                        ->reorderable('order')
                        ->actions([
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\Action::make('duplicate')
                            ->label('Duplicate')
                            ->icon('heroicon-o-document-duplicate')
                            ->color('gray')
                            ->requiresConfirmation()
                            ->modalDescription('This duplicates the category. Commodities are NOT duplicated.')
                            ->action(function (CommodityCategory $record) {
                                $new = $record->replicate();
                                $new->name = $record->name . ' (Copy)';
                                $new->slug = $record->slug . '-copy-' . time();
                                $new->order = $record->order + 5;
                                $new->save();

                                Notification::make()
                                        ->title('Category duplicated')
                                        ->success()
                                        ->send();
                            }),
                            Tables\Actions\DeleteAction::make()
                            ->before(function (CommodityCategory $record) {
                                if ($record->commodities()->count() > 0) {
                                    Notification::make()
                                            ->title('Cannot delete')
                                            ->body('Move or delete all commodities in this category first.')
                                            ->danger()
                                            ->send();
                                    return false;
                                }
                            }),
                        ])
                        ->emptyStateHeading('No Commodity Categories')
                        ->emptyStateDescription('Create categories to organise commodities in the Health Products section.')
                        ->emptyStateIcon('heroicon-o-tag');
    }

    public static function getRelations(): array {
        return [
            RelationManagers\CommoditiesRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListCommodityCategories::route('/'),
            'create' => Pages\CreateCommodityCategory::route('/create'),
            'edit' => Pages\EditCommodityCategory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return (string) CommodityCategory::count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'primary';
    }
}
