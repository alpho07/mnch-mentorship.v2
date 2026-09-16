<?php
namespace App\Filament\Resources\ResourceResource\Pages;

use App\Filament\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListResources extends ListRecords
{
    protected static string $resource = ResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Resource')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Resources')
                ->badge(fn () => $this->getModel()::count()),

            'published' => Tab::make('Published')
                ->badge(fn () => $this->getModel()::where('status', 'published')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'published')),

            'draft' => Tab::make('Drafts')
                ->badge(fn () => $this->getModel()::where('status', 'draft')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')),

            'featured' => Tab::make('Featured')
                ->badge(fn () => $this->getModel()::where('is_featured', true)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_featured', true)),

            'with_files' => Tab::make('With Files')
                ->badge(fn () => $this->getModel()::whereNotNull('file_path')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('file_path')),
        ];
    }
}
