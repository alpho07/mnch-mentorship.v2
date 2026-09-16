<?php

namespace App\Filament\Resources\AssessmentDepartmentResource\RelationManagers;

use App\Models\Commodity;
use App\Models\CommodityCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class ApplicableCommoditiesRelationManager extends RelationManager {

    protected static string $relationship = 'applicableCommodities';
    protected static ?string $title = 'Applicable Commodities';

    // ─────────────────────────────────────────────────────────────────────────
    // FORM — used when attaching
    // ─────────────────────────────────────────────────────────────────────────

    public function form(Form $form): Form {
        // This form is only used for AttachAction; commodity details live in CommodityResource
        return $form->schema([
                            Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content('Use the Attach button to add commodities applicable to this department. To create new commodities, go to the Commodities resource.')
                            ->columnSpanFull(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table {
        return $table
                        ->recordTitleAttribute('name')
                        ->columns([
                            Tables\Columns\TextColumn::make('category.name')
                            ->label('Category')
                            ->badge()
                            ->color('primary')
                            ->sortable()
                            ->searchable(),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Commodity')
                            ->searchable()
                            ->sortable()
                            ->weight('medium'),
                            Tables\Columns\TextColumn::make('description')
                            ->label('Description')
                            ->limit(60)
                            ->placeholder('—'),
                            Tables\Columns\TextColumn::make('order')
                            ->label('Order')
                            ->sortable()
                            ->alignCenter(),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->alignCenter(),
                        ])
                        ->defaultSort('category.name')
                        ->filters([
                            Tables\Filters\SelectFilter::make('commodity_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->preload()
                            ->searchable(),
                            Tables\Filters\TernaryFilter::make('is_active')
                            ->label('Active')
                            ->default(true),
                        ])
                        ->headerActions([
                            // Attach existing commodities
                            Tables\Actions\AttachAction::make()
                            ->label('Attach Commodity')
                            ->preloadRecordSelect()
                            ->recordSelectOptionsQuery(fn($query) => $query->with('category')->orderBy('commodity_category_id')->orderBy('order'))
                            ->recordSelectSearchColumns(['name', 'description'])
                            ->multiple(),
                            // Bulk attach: attach ALL commodities in a category
                            Tables\Actions\Action::make('attach_category')
                            ->label('Attach Entire Category')
                            ->icon('heroicon-o-squares-plus')
                            ->color('info')
                            ->form([
                                Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->options(CommodityCategory::orderBy('order')->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->helperText('All active commodities in this category will be attached to this department'),
                            ])
                            ->action(function (array $data) {
                                $categoryId = $data['category_id'];
                                $commodities = Commodity::where('commodity_category_id', $categoryId)
                                        ->where('is_active', true)
                                        ->pluck('id');

                                if ($commodities->isEmpty()) {
                                    Notification::make()
                                            ->title('No commodities found')
                                            ->body('This category has no active commodities.')
                                            ->warning()
                                            ->send();
                                    return;
                                }

                                // syncWithoutDetaching to avoid removing existing
                                $this->getOwnerRecord()->applicableCommodities()->syncWithoutDetaching($commodities);

                                $category = CommodityCategory::find($categoryId);
                                Notification::make()
                                        ->title('Category attached')
                                        ->body("{$commodities->count()} commodities from '{$category->name}' attached to this department.")
                                        ->success()
                                        ->send();
                            }),
                            // Detach all in a category
                            Tables\Actions\Action::make('detach_category')
                            ->label('Detach Entire Category')
                            ->icon('heroicon-o-minus-circle')
                            ->color('warning')
                            ->requiresConfirmation()
                            ->form([
                                Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->options(CommodityCategory::orderBy('order')->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->helperText('All commodities in this category will be detached from this department'),
                            ])
                            ->action(function (array $data) {
                                $categoryId = $data['category_id'];
                                $commodities = Commodity::where('commodity_category_id', $categoryId)
                                        ->pluck('id');

                                $this->getOwnerRecord()->applicableCommodities()->detach($commodities);

                                $category = CommodityCategory::find($categoryId);
                                Notification::make()
                                        ->title('Category detached')
                                        ->body("All commodities from '{$category->name}' removed from this department.")
                                        ->success()
                                        ->send();
                            }),
                        ])
                        ->actions([
                            Tables\Actions\DetachAction::make()
                            ->label('Remove'),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DetachBulkAction::make()
                                ->label('Remove Selected'),
                            ]),
                        ])
                        ->emptyStateHeading('No Commodities Attached')
                        ->emptyStateDescription('Attach commodities that are relevant to this department. Only attached commodities appear in assessments for this department.')
                        ->emptyStateIcon('heroicon-o-squares-2x2');
    }
}
