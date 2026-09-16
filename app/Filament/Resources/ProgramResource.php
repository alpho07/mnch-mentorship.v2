<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgramResource\Pages;
use App\Filament\Resources\ProgramResource\RelationManagers\ProgramModulesRelationManager;
use App\Models\Program;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Curriculum';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_program');}

    /** Role options shown in the visibility picker. */
    public static function roleOptions(): array
    {
        return [
            'admin'                  => 'Admin',
            'facility_mentor'        => 'Facility Mentor',
            'facility_mentor_lead'   => 'Facility Mentor Lead',
            'spoke_mentor'           => 'Spoke Mentor',
            'spoke_mentor_lead'      => 'Spoke Mentor Lead',
            'county_mentor_lead'     => 'County Mentor Lead',
            'subcounty_mentor_lead'  => 'Subcounty Mentor Lead',
            'national_mentor_lead'   => 'National Mentor Lead',
            'division'               => 'Division Lead',
            'national'               => 'National',
            'county'                 => 'County Officer',
            'subcounty'              => 'Subcounty Officer',
            'mentee'                 => 'Mentee',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Program Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Program Name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Activation & Visibility')
                ->description('Control which users can see and use this program when creating a mentorship.')
                ->icon('heroicon-o-eye')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Program Active')
                        ->helperText('When off, this program is hidden from the mentorship creation form for most roles.')
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->default(true),

                    Forms\Components\CheckboxList::make('visible_to_roles')
                        ->label('Still visible to these roles when deactivated')
                        ->helperText('Super Admin always sees all programs regardless of this setting.')
                        ->options(static::roleOptions())
                        ->columns(3)
                        ->gridDirection('row')
                        ->visible(fn (Get $get): bool => ! $get('is_active'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Program Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Program $record): ?string => $record->description
                        ? str($record->description)->limit(60)->toString()
                        : null),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (Program $record): string => $record->is_active ? 'Active — visible to all' : 'Deactivated'),

                Tables\Columns\TextColumn::make('visible_to_roles')
                    ->label('Visible when off')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => static::roleOptions()[$state] ?? $state)
                    ->separator(',')
                    ->placeholder('—')
                    ->tooltip('Roles that can still see this program when deactivated'),

                Tables\Columns\TextColumn::make('module_count')
                    ->label('Modules')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('programModules_count')
                    ->label('Curriculum')
                    ->getStateUsing(fn (Program $record): int => $record->programModules()->whereNull('parent_id')->count())
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('training_count')
                    ->label('Trainings')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active only')
                    ->falseLabel('Deactivated only')
                    ->placeholder('All programs'),
                Tables\Filters\Filter::make('has_modules')
                    ->label('Has Modules')
                    ->query(fn ($query) => $query->withModules()),
                Tables\Filters\Filter::make('has_trainings')
                    ->label('Has Trainings')
                    ->query(fn ($query) => $query->withTrainings()),
            ])
            ->actions([
                // Quick toggle — flip is_active without opening the edit form
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (Program $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Program $record): string => $record->is_active
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (Program $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Program $record): string => $record->is_active
                        ? "Deactivate \"{$record->name}\"?"
                        : "Activate \"{$record->name}\"?")
                    ->modalDescription(fn (Program $record): string => $record->is_active
                        ? 'This program will no longer appear in the mentorship creation form for most roles. You can still allow specific roles to see it via Edit.'
                        : 'This program will become visible to all users in the mentorship creation form.')
                    ->action(function (Program $record): void {
                        $record->update(['is_active' => ! $record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Program activated' : 'Program deactivated')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Program Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Program Name')
                            ->size('lg')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->prose(),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        Infolists\Components\TextEntry::make('visible_to_roles')
                            ->label('Visible to (when off)')
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn ($state): string => static::roleOptions()[$state] ?? $state)
                            ->separator(',')
                            ->placeholder('None — hidden from all when deactivated'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('module_count')
                            ->label('Total Modules')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('programModules_count')
                            ->label('Curriculum Modules')
                            ->getStateUsing(fn (Program $record): int => $record->programModules()->whereNull('parent_id')->count())
                            ->badge()
                            ->color('warning'),
                        Infolists\Components\TextEntry::make('training_count')
                            ->label('Total Trainings')
                            ->badge()
                            ->color('success'),
                        Infolists\Components\TextEntry::make('active_training_count')
                            ->label('Active Trainings')
                            ->badge()
                            ->color('warning'),
                        Infolists\Components\TextEntry::make('total_participants')
                            ->label('Total Participants')
                            ->badge()
                            ->color('primary'),
                    ])
                    ->columns(4),
                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProgramModulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrograms::route('/'),
            'create' => Pages\CreateProgram::route('/create'),
            'view' => Pages\ViewProgram::route('/{record}'),
            'edit' => Pages\EditProgram::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
