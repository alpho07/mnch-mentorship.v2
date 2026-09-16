<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\MentorshipClass;
use App\Models\Methodology;
use App\Models\Training;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ManageModuleSessions extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-module-sessions';
    protected static bool $shouldRegisterNavigation = false;
    public Training $training;
    public MentorshipClass $class;
    public ClassModule $module;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load('programModule');
    }

    public function getTitle(): string {
        return "Sessions — {$this->module->programModule?->name}";
    }

    public function getSubheading(): ?string {
        $count = $this->module->sessions()->count();
        $totalMinutes = $this->module->sessions()->sum('duration_minutes');
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";

        return "{$count} sessions · Total duration: {$duration} · Class: {$this->class->name}";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Header Actions
    // ─────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('back')
                    ->label('Back to Modules')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('class-modules', [
                                'training' => $this->training->id,
                                'class' => $this->class->id,
                            ])),
                    Actions\Action::make('add_session')
                    ->label('Add Session')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn() => $this->module->status !== 'completed')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading('Add Session to Module')
                    ->form($this->getSessionForm())
                    ->action(function (array $data) {
                        $lastOrder = ClassSession::where('class_module_id', $this->module->id)
                                        ->max('session_number') ?? 0;

                        ClassSession::create([
                            'class_module_id' => $this->module->id,
                            'session_number' => $lastOrder + 1,
                            'title' => $data['title'],
                            'description' => $data['description'] ?? null,
                            'duration_minutes' => $data['duration_minutes'] ?? null,
                            'scheduled_date' => $data['scheduled_date'] ?? null,
                            'scheduled_time' => $data['scheduled_time'] ?? null,
                            'location' => $data['location'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'status' => 'scheduled',
                            'attendance_taken' => false,
                        ]);

                        Notification::make()
                                ->success()
                                ->title('Session Added')
                                ->send();
                    }),
                    Actions\Action::make('reset_from_template')
                    ->label('Reset from Template')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn() => $this->module->status === 'not_started')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Sessions from Program Template')
                    ->modalDescription('This will delete all current sessions and regenerate them from the program module template. This cannot be undone.')
                    ->action(function () {
                        $this->module->sessions()->delete();
                        $count = $this->module->autoCreateSessions();

                        Notification::make()
                                ->success()
                                ->title('Sessions Reset')
                                ->body("{$count} sessions created from program template.")
                                ->send();
                    }),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Table
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table {
        return $table
                        ->query(
                                ClassSession::query()
                                ->where('class_module_id', $this->module->id)
                                ->orderBy('session_number')
                        )
                        ->reorderable('session_number')
                        ->defaultSort('session_number')
                        ->columns([
                            Tables\Columns\TextColumn::make('session_number')
                            ->label('#')
                            ->width(50)
                            ->sortable(),
                            Tables\Columns\TextColumn::make('title')
                            ->label('Session Title')
                            ->searchable()
                            ->description(fn(ClassSession $record) => $record->description ? \Illuminate\Support\Str::limit($record->description, 80) : null),
                            Tables\Columns\TextColumn::make('duration_minutes')
                            ->label('Duration')
                            ->formatStateUsing(fn(?int $state) => $state ? ($state >= 60 ? intdiv($state, 60) . 'h ' . ($state % 60) . 'm' : "{$state} min") : '—')
                            ->alignCenter(),
                            Tables\Columns\TextColumn::make('scheduled_date')
                            ->label('Scheduled Date')
                            ->date('d M Y')
                            ->placeholder('—'),
                            Tables\Columns\TextColumn::make('scheduled_time')
                            ->label('Time')
                            ->time('H:i')
                            ->placeholder('—'),
                            Tables\Columns\TextColumn::make('location')
                            ->label('Location')
                            ->placeholder('—')
                            ->toggleable(),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'gray' => 'scheduled',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                                'danger' => 'cancelled',
                            ]),
                        ])
                        ->actions([
                            Tables\Actions\EditAction::make()
                            ->slideOver()
                            ->modalWidth('2xl')
                            ->form($this->getSessionForm())
                            ->visible(fn(ClassSession $record) => $record->status !== 'completed'),
                            Tables\Actions\Action::make('duplicate')
                            ->label('Duplicate')
                            ->icon('heroicon-o-document-duplicate')
                            ->color('gray')
                            ->action(function (ClassSession $record) {
                                $lastOrder = ClassSession::where('class_module_id', $this->module->id)
                                                ->max('session_number') ?? 0;

                                ClassSession::create([
                                    'class_module_id' => $this->module->id,
                                    'session_number' => $lastOrder + 1,
                                    'title' => $record->title . ' (Copy)',
                                    'description' => $record->description,
                                    'duration_minutes' => $record->duration_minutes,
                                    'scheduled_date' => null,
                                    'scheduled_time' => null,
                                    'location' => $record->location,
                                    'notes' => $record->notes,
                                    'status' => 'scheduled',
                                    'attendance_taken' => false,
                                ]);

                                Notification::make()
                                        ->success()
                                        ->title('Session Duplicated')
                                        ->send();
                            }),
                            Tables\Actions\DeleteAction::make()
                            ->visible(fn(ClassSession $record) => $record->status === 'scheduled'),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make()
                                ->label('Delete Selected')
                                ->requiresConfirmation(),
                            ]),
                        ])
                        ->emptyStateHeading('No Sessions Yet')
                        ->emptyStateDescription('Add sessions manually or reset from the program module template.')
                        ->emptyStateIcon('heroicon-o-calendar-days')
                        ->paginated(false)
                        ->striped();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared Form Schema
    // ─────────────────────────────────────────────────────────────────────────

    private function getSessionForm(): array {
        $methodologies = Methodology::where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();

        return [
                    Forms\Components\TextInput::make('title')
                    ->label('Session Title')
                    ->required()
                    ->maxLength(255),
            Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('duration_minutes')
                        ->label('Duration (minutes)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(480)
                        ->placeholder('e.g. 90'),
                        Forms\Components\Select::make('methodology_id')
                        ->label('Methodology')
                        ->options($methodologies)
                        ->searchable()
                        ->placeholder('Select methodology...'),
            ]),
            Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DatePicker::make('scheduled_date')
                        ->label('Scheduled Date')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                        Forms\Components\TimePicker::make('scheduled_time')
                        ->label('Scheduled Time')
                        ->native(false),
            ]),
                    Forms\Components\TextInput::make('location')
                    ->label('Location / Room')
                    ->placeholder('e.g. Training Room 1, Ward 3...')
                    ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                    ->label('Session Description')
                    ->rows(2)
                    ->placeholder('What will be covered in this session?'),
                    Forms\Components\Textarea::make('notes')
                    ->label('Facilitator Notes')
                    ->rows(2)
                    ->placeholder('Internal notes for the facilitator...'),
        ];
    }
}
