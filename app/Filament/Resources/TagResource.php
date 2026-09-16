<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TagResource extends Resource {

    protected static ?string $model = Tag::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_tag');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Tag Information')
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
                                ->unique(Tag::class, 'slug', ignoreRecord: true)
                                ->rules(['alpha_dash']),
                                Forms\Components\ColorPicker::make('color')
                                ->default('#6B7280')
                                ->label('Tag Color'),
                            ])
                            ->columns(2),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\ViewColumn::make('tag_preview')
                            ->label('Preview')
                            ->view('filament.tables.columns.tag-preview'),
                            Tables\Columns\TextColumn::make('name')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('slug')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\ColorColumn::make('color')
                            ->label('Color'),
                            Tables\Columns\TextColumn::make('resource_count')
                            ->label('Resources')
                            ->numeric()
                            ->sortable()
                            ->counts('resources'),
                            Tables\Columns\TextColumn::make('created_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\Filter::make('popular')
                            ->query(fn(Builder $query): Builder =>
                                    $query->withCount('resources')->having('resources_count', '>', 0)
                            )
                            ->label('Popular Tags'),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
                        ])
                        ->defaultSort('name');
    }

    public static function getRelations(): array {
        return [
                //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            //'view' => Pages\ViewTag::route('/{record}'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
