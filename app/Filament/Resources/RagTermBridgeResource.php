<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RagTermBridgeResource\Pages;
use App\Models\RagTermBridge;
use App\Support\RagAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RagTermBridgeResource extends Resource
{
    protected static ?string $model = RagTermBridge::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'knowledge Base';

    protected static ?string $navigationLabel = 'RAG Term Bridges';

    protected static ?string $modelLabel = 'RAG Term Bridge';

    protected static ?int $navigationSort = 91;

    public static function shouldRegisterNavigation(): bool
    {
        return RagAccess::canManageDocuments(auth()->user());
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bridge')
                    ->schema([
                        Forms\Components\TextInput::make('trigger')
                            ->helperText('Primary term detected in the user question, for example sepsis.')
                            ->required()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('category')
                            ->maxLength(120),
                        Forms\Components\Toggle::make('enabled')
                            ->default(true),
                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999)
                            ->default(50)
                            ->helperText('Higher priority bridges are merged first.'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Terminology')
                    ->schema([
                        Forms\Components\TagsInput::make('synonyms')
                            ->placeholder('Add synonym')
                            ->helperText('Optional terms or phrases that should activate this bridge.'),
                        Forms\Components\TagsInput::make('queries')
                            ->placeholder('Add expanded search query')
                            ->required()
                            ->helperText('Short deterministic search queries merged into standard retrieval.'),
                    ]),
                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('priority')->orderBy('trigger'))
            ->columns([
                Tables\Columns\TextColumn::make('trigger')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('synonyms')
                    ->badge()
                    ->separator(',')
                    ->limitList(3)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('queries')
                    ->badge()
                    ->separator(',')
                    ->limitList(3)
                    ->wrap(),
                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),
                Tables\Columns\IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('enabled'),
                Tables\Filters\SelectFilter::make('category')
                    ->options(fn (): array => RagTermBridge::query()
                        ->whereNotNull('category')
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRagTermBridges::route('/'),
            'create' => Pages\CreateRagTermBridge::route('/create'),
            'edit' => Pages\EditRagTermBridge::route('/{record}/edit'),
        ];
    }
}
