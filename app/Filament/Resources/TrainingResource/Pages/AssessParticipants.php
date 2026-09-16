<?php

namespace App\Filament\Resources\TrainingResource\Pages;

use App\Filament\Resources\TrainingResource;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Objective;
use App\Models\ParticipantObjectiveResult;
use App\Models\Grade;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;

class AssessParticipants extends Page
{
    protected static string $resource = TrainingResource::class;
    protected static string $view = 'filament.pages.assess-participants';
    protected static ?string $title = 'Assess Participants';

    public ?array $data = [];
    public Training $training;
    public $participants;
    public $objectives;
    public $grades;
    public bool $isSaving = false;

    public function mount(Training $record): void
    {
        $this->training = $record;
        $this->participants = $record->participants()->with(['objectiveResults', 'cadre', 'department'])->get();
        $this->objectives = $record->sessions()
            ->with('objectives')
            ->get()
            ->pluck('objectives')
            ->flatten()
            ->sortBy('objective_order');
        $this->grades = Grade::all();

        // Initialize form data
        $formData = ['results' => []];
        
        foreach ($this->participants as $participant) {
            foreach ($this->objectives as $objective) {
                $existingResult = $participant->objectiveResults
                    ->where('objective_id', $objective->id)
                    ->first();

                $formData['results'][$participant->id][$objective->id] = [
                    'grade_id' => $existingResult?->grade_id,
                    'comments' => $existingResult?->comments ?? '',
                ];
            }
        }

        $this->data = $formData;
        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        $schema = [];

        foreach ($this->participants as $participant) {
            $participantFields = [];

            foreach ($this->objectives as $objective) {
                $participantFields[] = Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Placeholder::make('objective_text')
                            ->label('Objective')
                            ->content($objective->objective_text)
                            ->extraAttributes(['class' => 'font-medium']),

                        Forms\Components\Placeholder::make('objective_type')
                            ->label('Type')
                            ->content(ucfirst($objective->type))
                            ->extraAttributes([
                                'class' => $objective->type === 'skill' 
                                    ? 'text-blue-600 dark:text-blue-400' 
                                    : 'text-gray-600 dark:text-gray-400'
                            ]),

                        Forms\Components\Select::make("results.{$participant->id}.{$objective->id}.grade_id")
                            ->label('Grade')
                            ->options($this->grades->pluck('name', 'id'))
                            ->placeholder('Select Grade')
                            ->required()
                            ->extraAttributes(['class' => 'max-w-xs']),

                        Forms\Components\Textarea::make("results.{$participant->id}.{$objective->id}.comments")
                            ->label('Comments (Optional)')
                            ->rows(2)
                            ->columnSpan(3),
                    ])
                    ->extraAttributes(['class' => 'border-b pb-4 mb-4']);
            }

            $schema[] = Forms\Components\Section::make($participant->name)
                ->description("Cadre: {$participant->cadre?->name} | Department: {$participant->department?->name}")
                ->schema($participantFields)
                ->collapsible()
                ->collapsed(false);
        }

        return $form
            ->schema($schema)
            ->statePath('data');
    }

    public function save(): void
    {
        $this->isSaving = true;
        
        try {
            $data = $this->form->getState();
            
            $savedCount = 0;
            $totalAssessments = 0;

            foreach ($data['results'] as $participantId => $objectives) {
                foreach ($objectives as $objectiveId => $result) {
                    if (!empty($result['grade_id'])) {
                        $totalAssessments++;
                        
                        ParticipantObjectiveResult::updateOrCreate(
                            [
                                'training_participant_id' => $participantId,
                                'objective_id' => $objectiveId,
                            ],
                            [
                                'result' => 'assessed',
                                'grade_id' => $result['grade_id'],
                                'comments' => $result['comments'] ?? null,
                            ]
                        );
                        
                        $savedCount++;
                    }
                }
            }

            // Update participant outcomes based on their results
            foreach ($this->participants as $participant) {
                $this->updateParticipantOutcome($participant);
            }

            // Show success notification with details
            Notification::make()
                ->title('Assessments Saved Successfully')
                ->body("Saved {$savedCount} assessments for {$this->participants->count()} participants.")
                ->success()
                ->duration(5000)
                ->send();

            // Refresh the page data
            $this->mount($this->training);
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Saving Assessments')
                ->body('An error occurred while saving. Please try again.')
                ->danger()
                ->send();
                
           // \Log::error('Assessment save error: ' . $e->getMessage());
        } finally {
            $this->isSaving = false;
        }
    }

    protected function updateParticipantOutcome(TrainingParticipant $participant): void
    {
        // Get all results for this participant
        $results = ParticipantObjectiveResult::where('training_participant_id', $participant->id)
            ->with('grade')
            ->get();

        if ($results->isEmpty()) {
            return;
        }

        // Calculate overall outcome based on pass/fail grades
        $totalObjectives = $this->objectives->count();
        $assessedObjectives = $results->count();
        $passedObjectives = $results->filter(function ($result) {
            return $result->grade && strtolower($result->grade->name) === 'pass';
        })->count();

        // Determine overall outcome (customize this logic as needed)
        $passPercentage = ($passedObjectives / $totalObjectives) * 100;
        
        if ($assessedObjectives < $totalObjectives) {
            // Not all objectives assessed yet
            return;
        }

        // Example logic: 80% or more objectives passed = Pass
        $outcomeGradeName = $passPercentage >= 80 ? 'Pass' : 'Fail';
        $outcomeGrade = Grade::where('name', $outcomeGradeName)->first();
        
        if ($outcomeGrade) {
            $participant->update(['outcome_id' => $outcomeGrade->id]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to Training')
                ->url($this->getResource()::getUrl('edit', ['record' => $this->training]))
                ->icon('heroicon-o-arrow-left'),
        ];
    }
    
    public function getAssessmentProgressProperty(): array
    {
        $totalPossible = $this->participants->count() * $this->objectives->count();
        $totalAssessed = 0;
        
        foreach ($this->participants as $participant) {
            $totalAssessed += $participant->objectiveResults
                ->whereIn('objective_id', $this->objectives->pluck('id'))
                ->count();
        }
        
        $percentage = $totalPossible > 0 ? round(($totalAssessed / $totalPossible) * 100) : 0;
        
        return [
            'total' => $totalPossible,
            'assessed' => $totalAssessed,
            'percentage' => $percentage
        ];
    }
}