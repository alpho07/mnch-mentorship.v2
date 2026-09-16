<?php

namespace App\Filament\Resources\CommodityCategoryResource\Pages;

use App\Filament\Resources\CommodityCategoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCommodityCategory extends EditRecord {

    protected static string $resource = CommodityCategoryResource::class;

    public function getTitle(): string {
        return "Category: {$this->record->name}";
    }

    public function getSubheading(): ?string {
        $total = $this->record->commodities()->count();
        $active = $this->record->commodities()->where('is_active', true)->count();
        return "{$total} commodities ({$active} active)";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\DeleteAction::make()
                    ->before(function () {
                        if ($this->record->commodities()->count() > 0) {
                            Notification::make()
                                    ->title('Cannot delete')
                                    ->body('Move or delete all commodities in this category first.')
                                    ->danger()
                                    ->send();
                            return false;
                        }
                    }),
        ];
    }
}
