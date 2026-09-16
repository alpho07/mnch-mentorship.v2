<?php

namespace App\Filament\Resources\CommodityResource\Pages;

use App\Filament\Resources\CommodityResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCommodity extends EditRecord {

    protected static string $resource = CommodityResource::class;

    public function getTitle(): string {
        return $this->record->name;
    }

    public function getSubheading(): ?string {
        $depts = $this->record->applicableDepartments->pluck('name')->join(', ');
        return $depts ? "Applicable to: {$depts}" : '⚠ No departments assigned — will not appear in assessments';
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
            Actions\DeleteAction::make(),
        ];
    }
}
