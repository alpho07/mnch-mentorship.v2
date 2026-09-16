<?php

namespace App\Filament\Resources\Shared\RelationManagers;

use App\Models\Cadre;
use App\Models\User;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;

class CadresRelationManager extends RelationManager
{
    protected static string $relationship = 'cadres';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cadre Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Cadre Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(Cadre::class, 'name', ignoreRecord: true)
                            ->columnSpanFull()
                            ->helperText('Enter a unique name for this cadre (e.g., Clinical Officer, Nurse, Lab Technician)'),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Optional description of this cadre role and responsibilities'),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cadre Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Cadre $record): ?string => $record->description),
                
                Tables\Columns\TextColumn::make('contextual_user_count')
                    ->label($this->getUserCountLabel())
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function (Cadre $record): int {
                        return $this->getContextualUserCount($record);
                    })
                    ->description($this->getUserCountDescription()),
                
                Tables\Columns\TextColumn::make('total_users')
                    ->label('Total Users')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(fn (Cadre $record): int => $record->user_count)
                    ->description('System-wide'),
                
                Tables\Columns\TextColumn::make('active_users')
                    ->label('Active Users')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(function (Cadre $record): int {
                        $query = $record->users()->where('status', 'active');
                        
                        // Filter by context if we're in a department
                        if ($this->getOwnerDepartmentId()) {
                            $query->where('department_id', $this->getOwnerDepartmentId());
                        }
                        
                        return $query->count();
                    })
                    ->description($this->getActiveUsersDescription()),
                
                Tables\Columns\TextColumn::make('training_participants')
                    ->label('Training Participants')
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(fn (Cadre $record): int => $record->training_participation_count)
                    ->description('All-time participants'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('has_users_in_context')
                    ->label($this->getHasUsersFilterLabel())
                    ->query(function ($query) {
                        if ($this->getOwnerDepartmentId()) {
                            return $query->whereHas('users', function ($q) {
                                $q->where('department_id', $this->getOwnerDepartmentId());
                            });
                        }
                        return $query->has('users');
                    }),
                
                Tables\Filters\Filter::make('has_active_users')
                    ->label('Has Active Users')
                    ->query(function ($query) {
                        $baseQuery = $query->whereHas('users', function ($q) {
                            $q->where('status', 'active');
                            
                            if ($this->getOwnerDepartmentId()) {
                                $q->where('department_id', $this->getOwnerDepartmentId());
                            }
                        });
                        
                        return $baseQuery;
                    }),
                
                Tables\Filters\Filter::make('has_training_participants')
                    ->label('Has Training Participants')
                    ->query(fn ($query) => $query->has('trainingParticipants')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->slideOver()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Cadre created')
                            ->body('The cadre has been created successfully.')
                    ),
                
                Tables\Actions\Action::make('import_cadres')
                    ->label('Import Cadres')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->form([
                        Forms\Components\Textarea::make('cadre_names')
                            ->label('Cadre Names')
                            ->placeholder('Enter cadre names, one per line')
                            ->rows(5)
                            ->required()
                            ->helperText('Enter multiple cadre names, one per line. Duplicates will be ignored.'),
                    ])
                    ->action(function (array $data): void {
                        $names = array_filter(array_map('trim', explode("\n", $data['cadre_names'])));
                        $created = 0;
                        $skipped = 0;
                        
                        foreach ($names as $name) {
                            if (Cadre::where('name', $name)->exists()) {
                                $skipped++;
                                continue;
                            }
                            
                            Cadre::create(['name' => $name]);
                            $created++;
                        }
                        
                        Notification::make()
                            ->title('Import completed')
                            ->body("Created {$created} cadres, skipped {$skipped} duplicates")
                            ->success()
                            ->send();
                    })
                    ->modalWidth('md'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Cadre $record): string => 
                        \App\Filament\Resources\CadreResource::getUrl('view', ['record' => $record])
                    )
                    ->visible(fn (): bool => class_exists(\App\Filament\Resources\CadreResource::class)),
                
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Cadre updated')
                            ->body('The cadre has been updated successfully.')
                    ),
                
                Tables\Actions\Action::make('view_users')
                    ->label('View Users')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->modalContent(fn (Cadre $record): View => view(
                        'filament.modals.cadre-users',
                        [
                            'cadre' => $record,
                            'context' => $this->getContextName(),
                            'contextRecord' => $this->ownerRecord,
                            'users' => $this->getContextualUsers($record)
                        ]
                    ))
                    ->modalHeading(fn (Cadre $record): string => 
                        "Users: {$record->name}" . ($this->getOwnerDepartmentId() ? " (in {$this->ownerRecord->name})" : "")
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('4xl'),
                
                Tables\Actions\Action::make('assign_users')
                    ->label('Assign Users')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('users')
                            ->label('Select Users to Assign')
                            ->options(function () {
                                $query = User::whereNull('cadre_id');
                                
                                // Filter by department context if applicable
                                if ($this->getOwnerDepartmentId()) {
                                    $query->where('department_id', $this->getOwnerDepartmentId());
                                }
                                
                                return $query->get()->pluck('full_name', 'id');
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText($this->getAssignUsersHelperText()),
                    ])
                    ->action(function (Cadre $record, array $data): void {
                        User::whereIn('id', $data['users'])
                            ->update(['cadre_id' => $record->id]);
                        
                        $count = count($data['users']);
                        $context = $this->getOwnerDepartmentId() ? " in {$this->ownerRecord->name}" : "";
                        
                        Notification::make()
                            ->title('Users assigned')
                            ->body("{$count} users assigned to {$record->name}{$context}")
                            ->success()
                            ->send();
                    })
                    ->visible(function (Cadre $record): bool {
                        $query = User::whereNull('cadre_id');
                        
                        if ($this->getOwnerDepartmentId()) {
                            $query->where('department_id', $this->getOwnerDepartmentId());
                        }
                        
                        return $query->exists();
                    }),
                
                Tables\Actions\Action::make('statistics')
                    ->label('Statistics')
                    ->icon('heroicon-o-chart-bar')
                    ->color('warning')
                    ->modalContent(fn (Cadre $record): View => view(
                        'filament.modals.cadre-statistics',
                        [
                            'cadre' => $record,
                            'context' => $this->getContextName(),
                            'contextRecord' => $this->ownerRecord,
                            'stats' => $this->getCadreStatistics($record)
                        ]
                    ))
                    ->modalHeading(fn (Cadre $record): string => 
                        "Statistics: {$record->name}" . ($this->getContextName() ? " ({$this->getContextName()})" : "")
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('2xl'),
                
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to delete this cadre? Users assigned to this cadre will have their cadre assignment removed.')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Cadre deleted')
                            ->body('The cadre has been deleted successfully.')
                    )
                    ->before(function (Cadre $record): void {
                        // Remove cadre assignment from users
                        $record->users()->update(['cadre_id' => null]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_assign_users')
                        ->label('Assign Users to Selected')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('users')
                                ->label('Select Users to Assign')
                                ->options(function () {
                                    $query = User::whereNull('cadre_id');
                                    
                                    if ($this->getOwnerDepartmentId()) {
                                        $query->where('department_id', $this->getOwnerDepartmentId());
                                    }
                                    
                                    return $query->get()->pluck('full_name', 'id');
                                })
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(),
                            
                            Forms\Components\Select::make('target_cadre')
                                ->label('Assign to Cadre')
                                ->placeholder('Select cadre to assign users to')
                                ->required()
                                ->helperText('Users will be assigned to the selected cadre'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $cadre = $records->firstWhere('id', $data['target_cadre']);
                            if (!$cadre) {
                                $cadre = $records->first();
                            }
                            
                            User::whereIn('id', $data['users'])
                                ->update(['cadre_id' => $cadre->id]);
                            
                            $count = count($data['users']);
                            Notification::make()
                                ->title('Bulk assignment completed')
                                ->body("{$count} users assigned to {$cadre->name}")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                    
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('Are you sure you want to delete these cadres? Users assigned to these cadres will have their cadre assignments removed.')
                        ->before(function (Collection $records): void {
                            // Remove cadre assignments from users
                            foreach ($records as $record) {
                                $record->users()->update(['cadre_id' => null]);
                            }
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->slideOver(),
            ])
            ->emptyStateHeading($this->getEmptyStateHeading())
            ->emptyStateDescription($this->getEmptyStateDescription())
            ->emptyStateIcon('heroicon-o-user-group');
    }

    // Context detection methods
    protected function getOwnerDepartmentId(): ?int
    {
        return $this->ownerRecord instanceof Department ? $this->ownerRecord->id : null;
    }

    protected function getContextName(): ?string
    {
        if ($this->ownerRecord instanceof Department) {
            return $this->ownerRecord->name;
        }
        return null;
    }

    // Dynamic content methods
    protected function getUserCountLabel(): string
    {
        return $this->getOwnerDepartmentId() ? 'Users in Department' : 'Users';
    }

    protected function getUserCountDescription(): string
    {
        return $this->getOwnerDepartmentId() ? 'In this department' : 'System-wide';
    }

    protected function getActiveUsersDescription(): string
    {
        return $this->getOwnerDepartmentId() ? 'Active in department' : 'Active system-wide';
    }

    protected function getHasUsersFilterLabel(): string
    {
        return $this->getOwnerDepartmentId() ? 'Has Users in Department' : 'Has Users';
    }

    protected function getAssignUsersHelperText(): string
    {
        $base = 'Only users without a cadre assignment are shown';
        return $this->getOwnerDepartmentId() ? $base . ' (filtered by department)' : $base;
    }

    protected function getEmptyStateHeading(): string
    {
        return $this->getOwnerDepartmentId() 
            ? 'No cadres in this department yet'
            : 'No cadres yet';
    }

    protected function getEmptyStateDescription(): string
    {
        return 'Cadres represent different professional roles within the organization. Create your first cadre to organize users by their professional designation.';
    }

    // Data methods
    protected function getContextualUserCount(Cadre $record): int
    {
        $query = $record->users();
        
        if ($this->getOwnerDepartmentId()) {
            $query->where('department_id', $this->getOwnerDepartmentId());
        }
        
        return $query->count();
    }

    protected function getContextualUsers(Cadre $record)
    {
        $query = $record->users()->with(['facility', 'roles']);
        
        if ($this->getOwnerDepartmentId()) {
            $query->where('department_id', $this->getOwnerDepartmentId());
        }
        
        return $query->get();
    }

    protected function getCadreStatistics(Cadre $record): array
    {
        $baseQuery = $record->users();
        
        if ($this->getOwnerDepartmentId()) {
            $baseQuery->where('department_id', $this->getOwnerDepartmentId());
        }
        
        return [
            'total_users' => $baseQuery->count(),
            'active_users' => (clone $baseQuery)->where('status', 'active')->count(),
            'inactive_users' => (clone $baseQuery)->where('status', 'inactive')->count(),
            'trainee_users' => (clone $baseQuery)->where('status', 'trainee')->count(),
            'recent_users' => (clone $baseQuery)->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }
}