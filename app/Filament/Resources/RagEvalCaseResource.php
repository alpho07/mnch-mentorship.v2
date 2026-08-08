<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RagEvalCaseResource\Pages;
use App\Models\RagEvalCase;
use App\Support\RagAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RagEvalCaseResource extends Resource
{
    protected static ?string $model = RagEvalCase::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'knowledge Base';

    protected static ?string $navigationLabel = 'RAG Eval Cases';

    protected static ?int $navigationSort = 94;

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
        return $form->schema([
            Forms\Components\Textarea::make('question')->required()->rows(3)->columnSpanFull(),
            Forms\Components\TextInput::make('origin')->required()->maxLength(16),
            Forms\Components\Toggle::make('enabled')->default(true),
            Forms\Components\Toggle::make('frozen')->default(false),
            Forms\Components\TextInput::make('expected_decision')->maxLength(16),
            Forms\Components\TextInput::make('expected_route')->maxLength(16),
            Forms\Components\TagsInput::make('expected_documents')->columnSpanFull(),
            Forms\Components\TagsInput::make('must_include')->columnSpanFull(),
            Forms\Components\TagsInput::make('must_not_include')->columnSpanFull(),
            Forms\Components\Toggle::make('require_citations')->default(true),
            Forms\Components\Toggle::make('forbid_title_only')->default(true),
            Forms\Components\TextInput::make('max_latency_ms')->numeric(),
            Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->latest())
            ->columns([
                Tables\Columns\TextColumn::make('question')->limit(90)->searchable(),
                Tables\Columns\TextColumn::make('origin')->badge()->sortable(),
                Tables\Columns\IconColumn::make('enabled')->boolean()->sortable(),
                Tables\Columns\IconColumn::make('frozen')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('expected_decision')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('expected_route')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('enabled'),
                Tables\Filters\TernaryFilter::make('frozen'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRagEvalCases::route('/'),
            'create' => Pages\CreateRagEvalCase::route('/create'),
            'edit' => Pages\EditRagEvalCase::route('/{record}/edit'),
        ];
    }
}
