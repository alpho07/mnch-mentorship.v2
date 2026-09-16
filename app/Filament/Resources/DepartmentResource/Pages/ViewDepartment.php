<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\Training;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewDepartment extends ViewRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_user')
                ->label('Add User')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->url(fn(): string => route('filament.admin.resources.users.create', [
                    'department_id' => $this->record->id
                ])),

            Actions\Action::make('create_cadre')
                ->label('Add Cadre')
                ->icon('heroicon-o-user-group')
                ->color('info')
                ->url(fn(): string => route('filament.admin.resources.cadres.create')),

            Actions\Action::make('manage_trainings')
                ->label('Manage Trainings')
                ->icon('heroicon-o-academic-cap')
                ->color('warning')
                ->url(fn(): string => route('filament.admin.resources.trainings.index', [
                    'department' => $this->record->id
                ])),

            Actions\Action::make('export_department_data')
                ->label('Export Department Data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): void {
                    // Export logic would go here
                    $this->notify('success', 'Department data export initiated');
                }),

            Actions\EditAction::make(),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('This will delete the department and all its associated data. Users will have their department assignment removed.')
                ->modalSubmitActionLabel('Yes, delete department')
                ->before(function (): void {
                    // Remove department assignment from users
                    $this->record->users()->update(['department_id' => null]);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Department Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Department Name')
                            ->size('xl')
                            ->weight('bold')
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->prose()
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->headerActions([
                        Infolists\Components\Actions\Action::make('edit')
                            ->label('Edit Department')
                            ->icon('heroicon-o-pencil')
                            ->color('primary')
                            ->url(
                                fn(Department $record): string =>
                                route('filament.admin.resources.departments.edit', $record)
                            )
                            ->size('sm'),
                    ]),

                Infolists\Components\Section::make('Department Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('user_count')
                                    ->label('Total Staff')
                                    ->badge()
                                    ->size('lg')
                                    ->color('primary')
                                    ->icon('heroicon-o-users'),

                                Infolists\Components\TextEntry::make('active_users_count')
                                    ->label('Active Staff')
                                    ->badge()
                                    ->size('lg')
                                    ->color('success')
                                    ->icon('heroicon-o-check-circle')
                                    ->getStateUsing(function (Department $record): int {
                                        return $record->users()->where('status', 'active')->count();
                                    }),

                                Infolists\Components\TextEntry::make('training_count')
                                    ->label('Trainings Conducted')
                                    ->badge()
                                    ->size('lg')
                                    ->color('info')
                                    ->icon('heroicon-o-academic-cap'),

                                Infolists\Components\TextEntry::make('participant_count')
                                    ->label('Training Participants')
                                    ->badge()
                                    ->size('lg')
                                    ->color('warning')
                                    ->icon('heroicon-o-user-group'),
                            ]),
                    ])
                    ->columns(1),

                Infolists\Components\Section::make('Cadre Breakdown')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('cadres_with_counts')
                            ->label('Cadres in Department')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Cadre')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('users_count')
                                    ->label('Users')
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('active_users_count')
                                    ->label('Active')
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('trainee_users_count')
                                    ->label('Trainees')
                                    ->badge()
                                    ->color('warning'),
                            ])
                            ->getStateUsing(function (Department $record) {
                                return $record->users()
                                    ->with('cadre')
                                    ->get()
                                    ->groupBy('cadre.name')
                                    ->map(function ($users, $cadreName) {
                                        return [
                                            'name' => $cadreName ?: 'Unassigned',
                                            'users_count' => $users->count(),
                                            'active_users_count' => $users->where('status', 'active')->count(),
                                            'trainee_users_count' => $users->where('status', 'trainee')->count(),
                                        ];
                                    })
                                    ->values()
                                    ->toArray();
                            })
                            ->columns(4)
                            ->placeholder('No cadres with users in this department'),
                    ])
                    ->columns(1)
                    ->collapsible(),

                /*Infolists\Components\Section::make('Recent Training Activity')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('recent_trainings')
                            ->label('Recent Trainings')
                            ->schema([
                                Infolists\Components\TextEntry::make('title')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('facility.name')
                                    ->label('Facility')
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'upcoming' => 'warning',
                                        'ongoing' => 'success',
                                        'completed' => 'gray',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('participant_count')
                                    ->label('Participants')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('start_date')
                                    ->date(),
                            ])
                            ->getStateUsing(function (Department $record) {
                                return Training::whereHas('departments', function ($query) use ($record) {
                                    $query->where('department_id', $record->id);
                                })
                                    ->with(['facility', 'participants'])
                                    ->latest()
                                    ->limit(5)
                                    ->get()
                                    ->map(function ($training) {
                                        return [
                                            'title' => $training->title,
                                            'facility' => ['name' => $training->facility->name],
                                            'status' => $training->status,
                                            'participant_count' => $training->participants->count(),
                                            'start_date' => $training->start_date,
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->columns(5)
                            ->placeholder('No recent training activity'),

                        Infolists\Components\RepeatableEntry::make('top_participants')
                            ->label('Top Training Participants')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('cadre_name')
                                    ->label('Cadre')
                                    ->badge()
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('training_count')
                                    ->label('Trainings')
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('is_tot')
                                    ->label('ToT Status')
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Trainer' : 'Participant')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'primary' : 'gray'),
                            ])
                            ->getStateUsing(function (Department $record) {
                                return $record->trainingParticipants()
                                    ->with(['cadre'])
                                    ->selectRaw('training_participants.*, COUNT(*) as training_count')
                                    ->groupBy('training_participants.id')
                                    ->orderByDesc('training_count')
                                    ->limit(5)
                                    ->get()
                                    ->map(function ($participant) {
                                        return [
                                            'name' => $participant->name,
                                            'cadre_name' => $participant->cadre?->name ?: 'Unassigned',
                                            'training_count' => $participant->training_count,
                                            'is_tot' => $participant->is_tot,
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->columns(4)
                            ->placeholder('No training participants yet'),
                    ])
                    ->columns(1)
                    ->collapsible(),*/

                /*Infolists\Components\Section::make('Performance Metrics')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('monthly_trainings')
                                    ->label('Trainings This Month')
                                    ->badge()
                                    ->size('lg')
                                    ->color('info')
                                    ->getStateUsing(function (Department $record): int {
                                        return Training::whereHas('departments', function ($query) use ($record) {
                                            $query->where('department_id', $record->id);
                                        })
                                            ->whereMonth('start_date', now()->month)
                                            ->whereYear('start_date', now()->year)
                                            ->count();
                                    }),

                                Infolists\Components\TextEntry::make('avg_participants')
                                    ->label('Avg Participants/Training')
                                    ->badge()
                                    ->size('lg')
                                    ->color('success')
                                    ->getStateUsing(function (Department $record): string {
                                        $avgParticipants = Training::whereHas('departments', function ($query) use ($record) {
                                            $query->where('department_id', $record->id);
                                        })
                                            ->withCount('participants')
                                            ->get()
                                            ->avg('participants_count');

                                        return number_format($avgParticipants, 1);
                                    }),

                                Infolists\Components\TextEntry::make('completion_rate')
                                    ->label('Training Completion Rate')
                                    ->badge()
                                    ->size('lg')
                                    ->color('warning')
                                    ->getStateUsing(function (Department $record): string {
                                        $totalTrainings = Training::whereHas('departments', function ($query) use ($record) {
                                            $query->where('department_id', $record->id);
                                        })->count();

                                        $completedTrainings = Training::whereHas('departments', function ($query) use ($record) {
                                            $query->where('department_id', $record->id);
                                        })->completed()->count();

                                        if ($totalTrainings === 0) return '0%';

                                        $rate = ($completedTrainings / $totalTrainings) * 100;
                                        return number_format($rate, 1) . '%';
                                    }),
                            ]),
                    ])
                    ->columns(1)
                    ->collapsible(),

                Infolists\Components\Section::make('System Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->icon('heroicon-o-calendar'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime()
                            ->icon('heroicon-o-clock'),

                        Infolists\Components\TextEntry::make('id')
                            ->label('System ID')
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),*/
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
           // \App\Filament\Resources\Shared\RelationManagers\CadresRelationManager::class,
            \App\Filament\Resources\Shared\RelationManagers\UsersRelationManager::class,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Could add widgets here for charts/statistics
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Could add footer widgets
        ];
    }
}
