<?php

// app/Filament/Resources/AssessmentQuestionResource/Pages/EditAssessmentQuestion.php

namespace App\Filament\Resources\AssessmentQuestionResource\Pages;

use App\Filament\Resources\AssessmentQuestionResource;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAssessmentQuestion extends EditRecord {

    protected static string $resource = AssessmentQuestionResource::class;

    protected function getHeaderActions(): array {
        return [
            // Quick jump: view the section this question belongs to
                    Actions\Action::make('view_section')
                    ->label('View Section')
                    ->icon('heroicon-o-rectangle-stack')
                    ->color('gray')
                    ->url(fn() => \App\Filament\Resources\AssessmentSectionResource::getUrl('edit', [
                                'record' => $this->record->assessment_section_id,
                            ])),
            // Toggle active without leaving the page
            Actions\Action::make('toggle_active')
                    ->label(fn() => $this->record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn() => $this->record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn() => $this->record->is_active ? 'warning' : 'success')
                    ->action(function () {
                        $this->record->update(['is_active' => !$this->record->is_active]);
                        Notification::make()
                                ->title('Status updated')
                                ->body("Question is now " . ($this->record->is_active ? 'active' : 'inactive'))
                                ->success()
                                ->send();
                    }),
            // Move to section inline action
            Actions\Action::make('move_to_section')
                    ->label('Move to Section')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('gray')
                    ->form([
                        \Filament\Forms\Components\Select::make('target_section_id')
                        ->label('Target Section')
                        ->options(
                                AssessmentSection::where('is_active', true)
                                ->orderBy('order')
                                ->pluck('name', 'id')
                        )
                        ->required()
                        ->default($this->record->assessment_section_id)
                        ->searchable(),
                    ])
                    ->action(function (array $data) {
                        $newOrder = (AssessmentQuestion::where('assessment_section_id', $data['target_section_id'])->max('order') ?? 0) + 10;
                        $this->record->update([
                            'assessment_section_id' => $data['target_section_id'],
                            'order' => $newOrder,
                        ]);
                        $section = AssessmentSection::find($data['target_section_id']);
                        Notification::make()
                                ->title("Moved to '{$section->name}'")
                                ->success()
                                ->send();
                    }),
                    Actions\DeleteAction::make()
                    ->before(function () {
                        if ($this->record->responses()->count() > 0) {
                            Notification::make()
                                    ->title('Cannot delete — has responses')
                                    ->body('Deactivate this question instead of deleting it.')
                                    ->danger()
                                    ->send();
                            return false;
                        }
                    }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array {
        return CreateAssessmentQuestion::resolveConditionalLogic($data);
    }

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('index');
    }
}
