<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RagRetrievalTraceResource\Pages;
use App\Models\RagRetrievalTrace;
use App\Support\RagAccess;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RagRetrievalTraceResource extends Resource
{
    protected static ?string $model = RagRetrievalTrace::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'knowledge Base';

    protected static ?string $navigationLabel = 'RAG Retrieval Traces';

    protected static ?int $navigationSort = 92;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('question')->limit(80)->searchable(),
                Tables\Columns\TextColumn::make('decision')->badge()->sortable(),
                Tables\Columns\TextColumn::make('shadow_decision')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('gate_score')->numeric(4)->sortable(),
                Tables\Columns\TextColumn::make('answer_route')->badge()->sortable(),
                Tables\Columns\TextColumn::make('answer_model')->limit(30)->toggleable(),
                Tables\Columns\TextColumn::make('source_count')->sortable(),
                Tables\Columns\TextColumn::make('unsupported_count')->sortable(),
                Tables\Columns\TextColumn::make('total_ms')->label('Total ms')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('decision')->options([
                    'answer' => 'Answer',
                    'expand' => 'Expand',
                    'abstain' => 'Abstain',
                ]),
                Tables\Filters\SelectFilter::make('answer_route')->options([
                    'cache' => 'Cache',
                    'local' => 'Local',
                    'remote' => 'Remote',
                    'listing' => 'Listing',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request')
                    ->schema([
                        Infolists\Components\TextEntry::make('question')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('decision'),
                        Infolists\Components\TextEntry::make('shadow_decision'),
                        Infolists\Components\TextEntry::make('gate_score'),
                        Infolists\Components\TextEntry::make('answer_route'),
                        Infolists\Components\TextEntry::make('answer_model'),
                    ])->columns(3),
                Infolists\Components\Section::make('Signals')
                    ->schema([
                        Infolists\Components\TextEntry::make('gate_signals')
                            ->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Sources And Grounding')
                    ->schema([
                        Infolists\Components\TextEntry::make('selected_documents')
                            ->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('unsupported_sentences')
                            ->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRagRetrievalTraces::route('/'),
            'view' => Pages\ViewRagRetrievalTrace::route('/{record}'),
        ];
    }
}
