<?php


namespace App\Filament\Resources\MenteeProfileResource\RelationManagers;

use App\Filament\Resources\MenteeProfileResource;
use App\Models\MenteeAssessmentResult;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TrainingParticipationsRelationManager extends RelationManager {

    protected static string $relationship = 'trainingParticipations';
    protected static ?string $title = 'Trainings Attended';

    /** Make this relation manager span the full page width */
    public static function getColumnSpan(): int|string|array {
        return 'full';
    }

    public function table(Table $table): Table {
        return $table
                        ->recordTitleAttribute('id')
                        ->columns([
                            Tables\Columns\TextColumn::make('training.title')
                            ->label('Training')
                            ->searchable()
                            ->wrap(),
                            Tables\Columns\TextColumn::make('training.type')
                            ->label('Scope')
                            ->formatStateUsing(fn($state) => match ($state) {
                                        'facility_mentorship' => 'Mentorship',
                                        'global_training' => 'MOH/Global',
                                        default => ucfirst(str_replace('_', ' ', $state ?? '')),
                                    })
                            ->badge(),
                            Tables\Columns\TextColumn::make('training.start_date')
                            ->label('Start')
                            ->date(),
                            Tables\Columns\TextColumn::make('training.end_date')
                            ->label('End')
                            ->date(),
                            Tables\Columns\TextColumn::make('attendance_status')
                            ->label('Attendance')
                            ->badge(),
                            Tables\Columns\TextColumn::make('completion_status')
                            ->label('Completion')
                            ->badge(),
                            Tables\Columns\TextColumn::make('completion_date')
                            ->label('Completed On')
                            ->date(),
                            // Final Result from MenteeAssessmentResult.result (latest by assessment_date)
                            Tables\Columns\BadgeColumn::make('final_result')
                            ->label('Result')
                            ->state(function ($record) {
                                /** @var \App\Models\TrainingParticipant $record */
                                $r = MenteeAssessmentResult::query()
                                        ->where('participant_id', $record->id)
                                        ->orderByDesc('assessment_date')
                                        ->orderByDesc('created_at')
                                        ->first();

                                return $r?->result; // 'pass' | 'fail' | null
                            })
                            ->formatStateUsing(fn($state) => $state ? strtoupper($state) : '—')
                            ->colors([
                                'success' => 'pass',
                                'danger' => 'fail',
                                'gray' => fn($state) => blank($state),
                            ])
                            ->icons([
                                'heroicon-s-check-circle' => 'pass',
                                'heroicon-s-x-circle' => 'fail',
                            ]),
                        ])
                        ->filters([])
                        ->headerActions([]) // read-only list; updates happen via the View actions
                        ->actions([])
                        ->bulkActions([]);
    }
}
