<?php

namespace App\Filament\Resources\AssessmentDepartmentResource\Pages;

use App\Filament\Resources\AssessmentDepartmentResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAssessmentDepartment extends EditRecord {

    protected static string $resource = AssessmentDepartmentResource::class;

    public function getTitle(): string {
        return "Department: {$this->record->name}";
    }

    public function getSubheading(): ?string {
        $count = $this->record->applicableCommodities()->count();
        return "{$count} commodities applicable to this department";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('toggle_active')
                    ->label(fn() => $this->record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn() => $this->record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn() => $this->record->is_active ? 'warning' : 'success')
                    ->action(function () {
                        $this->record->update(['is_active' => !$this->record->is_active]);
                        Notification::make()
                                ->title('Status updated')
                                ->success()
                                ->send();
                    }),
                    Actions\DeleteAction::make()
                    ->before(function () {
                        if ($this->record->applicableCommodities()->count() > 0) {
                            Notification::make()
                                    ->title('Cannot delete')
                                    ->body('Remove all commodity applicabilities for this department first.')
                                    ->danger()
                                    ->send();
                            return false;
                        }
                    }),
        ];
    }
}
