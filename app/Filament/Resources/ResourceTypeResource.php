<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResourceTypeResource\Pages;
use App\Models\ResourceType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ResourceTypeResource extends Resource {

    protected static ?string $model = ResourceType::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_resource::type');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Type Information')
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
                                ->unique(ResourceType::class, 'slug', ignoreRecord: true)
                                ->rules(['alpha_dash']),
                                Forms\Components\Textarea::make('description')
                                ->rows(3)
                                ->columnSpanFull(),
                                Forms\Components\TextInput::make('icon')
                                ->hint('Heroicon name (e.g., document-text)')
                                ->helperText('View available icons at heroicons.com'),
                                Forms\Components\ColorPicker::make('color')
                                ->default('#3B82F6')
                                ->label('Theme Color'),
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
                            Tables\Columns\ViewColumn::make('icon_preview')
                            ->label('Icon')
                            ->view('filament.tables.columns.icon-preview')
                            ->toggleable(),
                            Tables\Columns\TextColumn::make('name')
                            ->searchable()
                            ->sortable()
                            ->description(fn(ResourceType $record): string =>
                                    $record->description ? Str::limit($record->description, 50) : ''
                            ),
                            Tables\Columns\TextColumn::make('slug')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\ColorColumn::make('color')
                            ->label('Color'),
                            Tables\Columns\TextColumn::make('icon')
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\TextColumn::make('resource_count')
                            ->label('Resources')
                            ->numeric()
                            ->sortable()
                            ->counts('resources'),
                            Tables\Columns\TextColumn::make('published_resource_count')
                            ->label('Published')
                            ->numeric()
                            ->sortable()
                            ->getStateUsing(fn(ResourceType $record): int =>
                                    $record->resources()->published()->count()
                            ),
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
                            Tables\Filters\Filter::make('is_active')
                            ->query(fn(Builder $query): Builder => $query->where('is_active', true))
                            ->label('Active Only'),
                            Tables\Filters\Filter::make('has_resources')
                            ->query(fn(Builder $query): Builder => $query->has('resources'))
                            ->label('Has Resources'),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
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
                        ->defaultSort('sort_order')
                        ->emptyStateHeading('No Resource Types Yet')
                        ->emptyStateDescription('Resource types classify knowledge base content (e.g. guide, video, form).')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
                        ]);
    }

    public static function getRelations(): array {
        return [
                //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListResourceTypes::route('/'),
            'create' => Pages\CreateResourceType::route('/create'),
            // 'view' => Pages\ViewResourceType::route('/{record}'),
            'edit' => Pages\EditResourceType::route('/{record}/edit'),
        ];
    }
}
