<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResourceCategoryResource\Pages;
use App\Models\ResourceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ResourceCategoryResource extends Resource {

    protected static ?string $model = ResourceCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_resource::category');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Category Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn(string $context, $state, Forms\Set $set) =>
                                        $context === 'create' ? $set('slug', Str::slug($state)) : null
                                ),
                                Forms\Components\TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ResourceCategory::class, 'slug', ignoreRecord: true)
                                ->rules(['alpha_dash']),
                                Forms\Components\Textarea::make('description')
                                ->rows(3)
                                ->columnSpanFull(),
                                Forms\Components\Select::make('parent_id')
                                ->relationship('parent', 'name', fn(Builder $query) =>
                                        $query->whereNull('parent_id')->orderBy('name')
                                )
                                ->searchable()
                                ->label('Parent Category')
                                ->hint('Leave blank for top-level category'),
                                Forms\Components\FileUpload::make('image')
                                ->image()
                                ->directory('categories')
                                ->visibility('public')
                                ->imageEditor(),
                                Forms\Components\Toggle::make('is_active')
                                ->default(true)
                                ->label('Active'),
                                Forms\Components\TextInput::make('sort_order')
                                ->numeric()
                                ->default(0)
                                ->label('Sort Order')
                                ->hint('Lower numbers appear first'),
                            ])
                            ->columns(2),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\ImageColumn::make('image')
                            ->circular()
                            ->defaultImageUrl(url('/images/placeholder-category.png')),
                            Tables\Columns\TextColumn::make('name')
                            ->searchable()
                            ->sortable()
                            ->description(fn(ResourceCategory $record): string =>
                                    $record->description ? Str::limit($record->description, 50) : ''
                            ),
                            Tables\Columns\TextColumn::make('full_path')
                            ->label('Category Path')
                            ->searchable()
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('parent.name')
                            ->label('Parent')
                            ->sortable()
                            ->placeholder('Top Level'),
                            Tables\Columns\TextColumn::make('resource_count')
                            ->label('Resources')
                            ->numeric()
                            ->sortable()
                            ->counts('resources'),
                            Tables\Columns\TextColumn::make('children_count')
                            ->label('Sub-categories')
                            ->numeric()
                            ->sortable()
                            ->counts('children'),
                            Tables\Columns\IconColumn::make('is_active')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                            Tables\Columns\TextColumn::make('sort_order')
                            ->label('Order')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\TextColumn::make('created_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('parent_id')
                            ->relationship('parent', 'name')
                            ->label('Parent Category'),
                            Tables\Filters\Filter::make('is_active')
                            ->query(fn(Builder $query): Builder => $query->where('is_active', true))
                            ->label('Active Only'),
                            Tables\Filters\Filter::make('top_level')
                            ->query(fn(Builder $query): Builder => $query->whereNull('parent_id'))
                            ->label('Top Level Only'),
                            Tables\Filters\TrashedFilter::make(),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make(),
                            Tables\Actions\RestoreAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                                Tables\Actions\RestoreBulkAction::make(),
                                Tables\Actions\BulkAction::make('activate')
                                ->label('Activate')
                                ->icon('heroicon-o-check-circle')
                                ->action(fn($records) => $records->each(fn($record) =>
                                                $record->update(['is_active' => true])
                                        ))
                                ->color('success'),
                                Tables\Actions\BulkAction::make('deactivate')
                                ->label('Deactivate')
                                ->icon('heroicon-o-x-circle')
                                ->action(fn($records) => $records->each(fn($record) =>
                                                $record->update(['is_active' => false])
                                        ))
                                ->color('danger'),
                            ]),
                        ])
                        ->defaultSort('sort_order');
    }

    public static function getRelations(): array {
        return [
                //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListResourceCategories::route('/'),
            'create' => Pages\CreateResourceCategory::route('/create'),
            // 'view' => Pages\ViewResourceCategory::route('/{record}'),
            'edit' => Pages\EditResourceCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->withoutGlobalScopes([
                            SoftDeletingScope::class,
        ]);
    }
}
