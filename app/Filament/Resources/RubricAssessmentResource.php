<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RubricAssessmentResource\Pages;
use App\Models\ClassModule;
use App\Models\ModuleRubric;
use App\Models\RubricAssessment;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RubricAssessmentResource extends Resource
{
    protected static ?string $model = RubricAssessment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Practical Assessments';

    protected static ?string $navigationGroup = 'Mentorships';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Practical Assessment';

    protected static ?string $pluralModelLabel = 'Practical Assessments';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_rubric::assessment');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Assessment Context')
                ->schema([
                    Forms\Components\Select::make('module_rubric_id')
                        ->label('Rubric / Skill')
                        ->options(fn () => ModuleRubric::with('programModule')
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn ($r) => [$r->id => $r->programModule->name . ' — ' . $r->title]))
                        ->searchable()
                        ->required()
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('mentee_id')
                        ->label('Mentee')
                        ->options(fn () => User::role('mentee')->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn ($u) => [$u->id => $u->full_name . ' (' . $u->email . ')']))
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('mentor_id')
                        ->label('Assessor (Mentor)')
                        ->default(fn () => auth()->id())
                        ->options(fn () => User::whereHas('roles', fn ($q) => $q->where('name', 'like', '%mentor%'))
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn ($u) => [$u->id => $u->full_name]))
                        ->searchable()
                        ->required(),

                    Forms\Components\DateTimePicker::make('assessed_at')
                        ->label('Assessment Date & Time')
                        ->default(now())
                        ->required(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Additional Notes / Observations')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mentee.full_name')
                    ->label('Mentee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rubric.programModule.name')
                    ->label('Module / Track')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('rubric.title')
                    ->label('Rubric')
                    ->limit(35)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->formatStateUsing(fn ($record) => $record->score . ' / ' . ($record->rubric?->total_marks ?? '?'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('score_pct')
                    ->label('%')
                    ->state(fn ($record) => $record->scorePercentage() . '%')
                    ->sortable(false),

                Tables\Columns\IconColumn::make('passed')
                    ->label('Result')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('mentor.full_name')
                    ->label('Assessed by')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('assessed_at')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('assessed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('module_rubric_id')
                    ->label('Rubric')
                    ->relationship('rubric', 'title'),

                Tables\Filters\TernaryFilter::make('passed')
                    ->label('Result')
                    ->trueLabel('Pass only')
                    ->falseLabel('Fail only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->url(fn ($record) => Pages\EditRubricAssessment::getUrl(['record' => $record->id])),
            ])
            ->bulkActions([]);
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRubricAssessments::route('/'),
            'create' => Pages\ConductRubricAssessment::route('/create'),
            'view' => Pages\ViewRubricAssessment::route('/{record}'),
            'edit' => Pages\EditRubricAssessment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['rubric.programModule', 'mentee', 'mentor']);
    }
}
