<?php

// app/Filament/Resources/AssessmentSectionResource/Pages/EditAssessmentSection.php

namespace App\Filament\Resources\AssessmentSectionResource\Pages;

use App\Filament\Resources\AssessmentSectionResource;
use App\Models\AssessmentSection;
use App\Models\AssessmentQuestion;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAssessmentSection extends EditRecord {

    protected static string $resource = AssessmentSectionResource::class;

    public function getTitle(): string {
        return "Section: {$this->record->name}";
    }

    public function getSubheading(): ?string {
        $qCount = $this->record->questions()->count();
        $activeCount = $this->record->questions()->where('is_active', true)->count();
        $groups = $this->record->questions()->whereNotNull('group')->distinct()->count('group');

        return "{$qCount} questions ({$activeCount} active)" . ($groups > 0 ? " · {$groups} groups" : '');
    }

    protected function getHeaderActions(): array {
        return [
            // Quick: renumber all questions in this section
                    Actions\Action::make('renumber_questions')
                    ->label('Renumber Questions')
                    ->icon('heroicon-o-bars-arrow-up')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Re-number all questions in this section in increments of 10, preserving their current visual order.')
                    ->action(function () {
                        $questions = $this->record
                                ->questions()
                                ->orderBy('order')
                                ->get();

                        $i = 10;
                        foreach ($questions as $q) {
                            $q->update(['order' => $i]);
                            $i += 10;
                        }

                        Notification::make()
                                ->title('Questions renumbered')
                                ->body("Renumbered {$questions->count()} questions in increments of 10.")
                                ->success()
                                ->send();
                    }),
            // Toggle section active
            Actions\Action::make('toggle_active')
                    ->label(fn() => $this->record->is_active ? 'Deactivate Section' : 'Activate Section')
                    ->icon(fn() => $this->record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn() => $this->record->is_active ? 'warning' : 'success')
                    ->action(function () {
                        $this->record->update(['is_active' => !$this->record->is_active]);
                        Notification::make()
                                ->title('Section status updated')
                                ->success()
                                ->send();
                    }),
                    Actions\DeleteAction::make()
                    ->before(function () {
                        if ($this->record->questions()->count() > 0) {
                            Notification::make()
                                    ->title('Cannot delete section')
                                    ->body('Move or delete all questions in this section first.')
                                    ->danger()
                                    ->send();
                            return false;
                        }
                    }),
        ];
    }
}
