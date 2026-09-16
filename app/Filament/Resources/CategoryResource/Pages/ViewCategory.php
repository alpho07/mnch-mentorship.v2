<?php
namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewCategory extends ViewRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Category Details')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('name')
                                    ->size('lg')
                                    ->weight('bold'),
                                Components\TextEntry::make('code')
                                    ->badge(),
                                Components\TextEntry::make('description')
                                    ->prose(),
                                Components\TextEntry::make('parent.name')
                                    ->label('Parent Category')
                                    ->badge()
                                    ->color('gray'),
                                Components\ColorEntry::make('color'),
                                Components\TextEntry::make('icon'),
                                Components\IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),
                                Components\TextEntry::make('sort_order')
                                    ->label('Sort Order'),
                            ]),
                    ]),
                Components\Section::make('Statistics')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('item_count')
                                    ->label('Total Items')
                                    ->badge()
                                    ->color('info'),
                                Components\TextEntry::make('total_value')
                                    ->label('Total Value')
                                    ->money('KES'),
                                Components\TextEntry::make('children_count')
                                    ->label('Sub-categories')
                                    ->state(fn ($record) => $record->children()->count())
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ]),
            ]);
    }
}