<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\MenteeAssessmentResult;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;

class ManageGlobalTrainingAssessments extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = GlobalTrainingResource::class;
    protected static string $view = 'filament.pages.simple-assessment-matrix';
    public Training $record;

    public function mount(int|string $record): void {
        $this->record = Training::where('type', 'global_training')
                ->with(['assessmentCategories', 'participants.user', 'participants.assessmentResults'])
                ->findOrFail($this->record->id);
    }

    public function getTitle(): string {
        return "Assessment Matrix - {$this->record->title}";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('assess_category')
                    ->label('Assess All for Category')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->form([
                        Select::make('category_id')
                        ->label('Assessment Category')
                        ->options($this->record->assessmentCategories->pluck('name', 'id'))
                        ->required(),
                        Select::make('result')
                        ->label('Result for All Mentees')
                        ->options(['pass' => 'Pass All', 'fail' => 'Fail All'])
                        ->required(),
                        Textarea::make('feedback')
                        ->label('Feedback')
                        ->rows(2),
                    ])
                    ->action(fn(array $data) => $this->bulkAssessCategory($data)),
                    Actions\Action::make('back')
                    ->label('Back to Training')
                    ->icon('heroicon-o-arrow-left')
                    ->url(fn() => GlobalTrainingResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                TrainingParticipant::query()
                                ->where('training_id', $this->record->id)
                                ->with([
                                    'user:id,name,first_name,last_name,department_id,cadre_id',
                                    'user.department:id,name',
                                    'user.cadre:id,name',
                                    'assessmentResults'
                                ])
                        )
                        ->columns([
                            TextColumn::make('user.name')
                            ->label('Mentee Name')
                            ->searchable(['first_name', 'last_name'])
                            ->weight('bold'),
                            TextColumn::make('user.department.name')
                            ->label('Department')
                            ->badge()
                            ->color('info'),
                            // Dynamic columns for each category - simple pass/fail
                            ...collect($this->record->assessmentCategories)->map(function ($category) {
                                return TextColumn::make("category_{$category->id}")
                                                ->label($category->name . " ({$category->pivot->weight_percentage}%)")
                                                ->getStateUsing(function ($record) use ($category) {
                                                    $result = $record->assessmentResults
                                                            ->where('assessment_category_id', $category->id)
                                                            ->first();
                                                    return $result ? strtoupper($result->result) : 'NOT ASSESSED';
                                                })
                                                ->badge()
                                                ->color(function ($record) use ($category) {
                                                    $result = $record->assessmentResults
                                                            ->where('assessment_category_id', $category->id)
                                                            ->first();

                                                    if (!$result)
                                                        return 'gray';
                                                    return $result->result === 'pass' ? 'success' : 'danger';
                                                });
                            })->toArray(),
                            TextColumn::make('overall_status')
                            ->label('Overall')
                            ->getStateUsing(fn($record) => $this->record->calculateOverallScore($record)['status'])
                            ->badge()
                            ->color(function ($record) {
                                $status = $this->record->calculateOverallScore($record)['status'];
                                return match ($status) {
                                    'PASSED' => 'success',
                                    'FAILED' => 'danger',
                                    'INCOMPLETE' => 'warning',
                                    default => 'gray',
                                };
                            }),
                            TextColumn::make('score')
                            ->label('Score')
                            ->getStateUsing(function ($record) {
                                $calc = $this->record->calculateOverallScore($record);
                                return $calc['all_assessed'] ? $calc['score'] . '%' : 'Incomplete';
                            })
                            ->badge()
                            ->color('primary'),
                        ])
                        ->actions([
                            Tables\Actions\Action::make('assess')
                            ->label('Assess')
                            ->icon('heroicon-o-pencil')
                            ->color('primary')
                            ->form(fn($record) => $this->getSimpleAssessForm($record))
                            ->action(fn($record, array $data) => $this->assessMentee($record, $data)),
                        ])
                        ->defaultSort('id', 'desc');
    }

    // Simple assessment form - just pass/fail for each category
    private function getSimpleAssessForm($record): array {
        return [
                    Forms\Components\Placeholder::make('mentee_info')
                    ->content("Assessing: {$record->user->full_name} ({$record->user->department?->name})")
                    ->columnSpanFull(),
                    Repeater::make('assessments')
                    ->schema([
                        Forms\Components\Placeholder::make('category_name')
                        ->content(function (Get $get) {
                            $categoryId = $get('category_id');
                            $category = $this->record->assessmentCategories->find($categoryId);
                            return $category ? "{$category->name} ({$category->pivot->weight_percentage}%)" : '';
                        }),
                        Select::make('result')
                        ->options(['pass' => 'Pass', 'fail' => 'Fail'])
                        ->required(),
                        Textarea::make('feedback')
                        ->rows(1)
                        ->placeholder('Optional feedback'),
                        Forms\Components\Hidden::make('category_id'),
                    ])
                    ->afterStateHydrated(function (Repeater $component, $record) {
                        $items = $this->record->assessmentCategories->map(function ($category) use ($record) {
                                    $existing = $record->assessmentResults
                                            ->where('assessment_category_id', $category->id)
                                            ->first();

                                    return [
                                        'category_id' => $category->id,
                                        'result' => $existing?->result,
                                        'feedback' => $existing?->feedback,
                                    ];
                                })->toArray();

                        $component->state($items);
                    })
                    ->addable(false)
                    ->deletable(false)
                    ->columnSpanFull(),
        ];
    }

    // Simple assess mentee method
    private function assessMentee($record, array $data): void {
        $assessments = $data['assessments'];
        $saved = 0;

        foreach ($assessments as $assessment) {
            if (empty($assessment['result']))
                continue;

            $category = $this->record->assessmentCategories->find($assessment['category_id']);

            MenteeAssessmentResult::updateOrCreate([
                'participant_id' => $record->id,
                'assessment_category_id' => $assessment['category_id'],
                    ], [
                'result' => $assessment['result'],
                'feedback' => $assessment['feedback'],
                'assessment_date' => now(),
                'assessed_by' => auth()->id(),
                'category_weight' => $category->pivot->weight_percentage,
            ]);

            $saved++;
        }

        // Update participant status
        $calculation = $this->record->calculateOverallScore($record);
        if ($calculation['all_assessed']) {
            $record->update([
                'completion_status' => 'completed',
                'completion_date' => now(),
                'outcome_id' => $calculation['status'] === 'PASSED' ? 1 : 2,
            ]);
        }

        Notification::make()
                ->title('Assessment Saved')
                ->body("Saved {$saved} results for {$record->user->full_name}")
                ->success()
                ->send();
    }

    // Simple bulk assess
    private function bulkAssessCategory(array $data): void {
        $categoryId = $data['category_id'];
        $result = $data['result'];
        $feedback = $data['feedback'];

        $category = $this->record->assessmentCategories->find($categoryId);
        $participants = $this->record->participants;
        $processed = 0;

        foreach ($participants as $participant) {
            MenteeAssessmentResult::updateOrCreate([
                'participant_id' => $participant->id,
                'assessment_category_id' => $categoryId,
                    ], [
                'result' => $result,
                'feedback' => $feedback,
                'assessment_date' => now(),
                'assessed_by' => auth()->id(),
                'category_weight' => $category->pivot->weight_percentage,
            ]);

            // Update participant status
            $calculation = $this->record->calculateOverallScore($participant);
            if ($calculation['all_assessed']) {
                $participant->update([
                    'completion_status' => 'completed',
                    'completion_date' => now(),
                    'outcome_id' => $calculation['status'] === 'PASSED' ? 1 : 2,
                ]);
            }

            $processed++;
        }

        Notification::make()
                ->title('Bulk Assessment Complete')
                ->body("Assessed {$processed} mentees for '{$category->name}' as " . strtoupper($result))
                ->success()
                ->send();
    }
}
