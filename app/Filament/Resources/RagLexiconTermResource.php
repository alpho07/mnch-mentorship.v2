<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RagLexiconTermResource\Pages;
use App\Models\RagLexiconTerm;
use App\Support\RagAccess;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RagLexiconTermResource extends Resource
{
    protected static ?string $model = RagLexiconTerm::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = 'knowledge Base';

    protected static ?string $navigationLabel = 'RAG Lexicon';

    protected static ?int $navigationSort = 93;

    public static function shouldRegisterNavigation(): bool
    {
        return RagAccess::canManageDocuments(auth()->user());
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('chunk_frequency'))
            ->columns([
                Tables\Columns\TextColumn::make('term')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('normalised')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('document_frequency')->sortable(),
                Tables\Columns\TextColumn::make('chunk_frequency')->sortable(),
                Tables\Columns\TextColumn::make('df_ratio')->numeric(5)->sortable(),
                Tables\Columns\IconColumn::make('is_stopword')->boolean()->sortable(),
                Tables\Columns\IconColumn::make('is_acronym')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('corpus_version')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_stopword'),
                Tables\Filters\TernaryFilter::make('is_acronym'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRagLexiconTerms::route('/'),
        ];
    }
}
