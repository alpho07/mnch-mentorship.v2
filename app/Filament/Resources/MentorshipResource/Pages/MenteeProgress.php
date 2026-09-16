<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class MenteeProgress extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.mentee-progress';
    protected static bool $shouldRegisterNavigation = false;
    
    public Training $record;
    public ClassParticipant $participant;

    public function mount(Training $record, ClassParticipant $participant): void
    {
      
        $this->participant = $participant->load(['user', 'mentorshipClass']);
    }

    public function getTitle(): string
    {
        return "Progress: {$this->participant->user->full_name}";
    }

    public function getSubheading(): ?string
    {
        return "Class: {$this->participant->mentorshipClass->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to Class')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn() => MentorshipTrainingResource::getUrl('class-mentees', [
                    'training' => $this->record->id,
                    'class' => $this->participant->mentorship_class_id,
                ])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MenteeModuleProgress::query()
                    ->where('class_participant_id', $this->participant->id)
                    ->with([
                        'classModule.programModule',
                        'classModule.moduleAssessments',
                        'assessmentResults.moduleAssessment'
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('classModule.programModule.name')
                    ->label('Module')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'not_started',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'info' => 'exempted',
                    ])
                    ->formatStateUsing(fn(string $state): string =>
                        match ($state) {
                            'not_started' => 'Not Started',
                            'in_progress' => 'In Progress',
                            'completed' => 'Completed',
                            'exempted' => 'Exempted',
                            default => ucfirst($state),
                        }
                    ),
                
                Tables\Columns\IconColumn::make('completed_in_previous_class')
                    ->label('Previously Completed')
                    ->boolean()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('attendance_percentage')
                    ->label('Attendance')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable()
                    ->default('—'),
                
                Tables\Columns\TextColumn::make('assessment_score')
                    ->label('Assessment Score')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable()
                    ->default('—'),
                
                Tables\Columns\BadgeColumn::make('assessment_status')
                    ->label('Assessment')
                    ->colors([
                        'secondary' => 'pending',
                        'success' => 'passed',
                        'danger' => 'failed',
                    ])
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
           // ->defaultSort('classModule.order_sequence')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'not_started' => 'Not Started',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'exempted' => 'Exempted',
                    ]),
                
                Tables\Filters\SelectFilter::make('assessment_status')
                    ->label('Assessment Status')
                    ->options([
                        'pending' => 'Pending',
                        'passed' => 'Passed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_assessments')
                    ->label('Assessments')
                    ->icon('heroicon-o-academic-cap')
                    ->modalHeading(fn($record) => 'Assessments: ' . $record->classModule->programModule->name)
                    ->modalContent(function ($record) {
                        $assessments = $record->classModule->moduleAssessments;
                        $results = $record->assessmentResults;
                        
                        return view('filament.components.module-assessments', [
                            'assessments' => $assessments,
                            'results' => $results,
                        ]);
                    })
                    ->modalWidth('3xl')
                    ->visible(fn($record) => $record->classModule->moduleAssessments->count() > 0),
            ]);
    }
}
