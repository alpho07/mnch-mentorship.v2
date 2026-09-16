<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\PartnerResource;
use App\Models\Partner;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPartners extends ListRecords
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Partner')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }

    public function getTitle(): string
    {
        return 'Partner Organizations';
    }

    public function getSubheading(): ?string
    {
        $stats = $this->getQuickStats();
        return "Training and development partners • {$stats['total']} total • {$stats['active']} active • {$stats['with_trainings']} with trainings";
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Partners')
                ->badge($this->getTabCount('all'))
                ->badgeColor('gray'),

            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true))
                ->badge($this->getTabCount('active'))
                ->badgeColor('success'),

            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false))
                ->badge($this->getTabCount('inactive'))
                ->badgeColor('danger'),

            'ngo' => Tab::make('NGOs')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'ngo'))
                ->badge($this->getTabCount('ngo'))
                ->badgeColor('primary'),

            'international' => Tab::make('International')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'international'))
                ->badge($this->getTabCount('international'))
                ->badgeColor('info'),

            'private' => Tab::make('Private')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'private'))
                ->badge($this->getTabCount('private'))
                ->badgeColor('warning'),

            'with_trainings' => Tab::make('With Trainings')
                ->modifyQueryUsing(fn (Builder $query) => $query->has('trainings'))
                ->badge($this->getTabCount('with_trainings'))
                ->badgeColor('success'),
        ];
    }

    protected function getQuickStats(): array
    {
        return [
            'total' => Partner::count(),
            'active' => Partner::where('is_active', true)->count(),
            'with_trainings' => Partner::has('trainings')->count(),
        ];
    }

    protected function getTabCount(string $tab): int
    {
        return match ($tab) {
            'all' => Partner::count(),
            'active' => Partner::where('is_active', true)->count(),
            'inactive' => Partner::where('is_active', false)->count(),
            'ngo' => Partner::where('type', 'ngo')->count(),
            'international' => Partner::where('type', 'international')->count(),
            'private' => Partner::where('type', 'private')->count(),
            'with_trainings' => Partner::has('trainings')->count(),
            default => 0,
        };
    }
} 