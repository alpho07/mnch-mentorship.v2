<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommodityResource\Pages;
use App\Filament\Resources\CommodityResource\RelationManagers;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\AssessmentDepartment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class CommodityResource extends Resource {

    protected static ?string $model = Commodity::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Assessment Management';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Commodities';
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_commodity');}

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('view_any_commodity');}

    // ─────────────────────────────────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Commodity Details')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('commodity_category_id')
                                    ->label('Category')
                                    ->options(CommodityCategory::orderBy('order')->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('The category this commodity belongs to'),
                                    Forms\Components\TextInput::make('order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->helperText('Within category, lower = shown first'),
                                ]),
                                Forms\Components\TextInput::make('name')
                                ->label('Commodity Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., Oxytocin 10 IU/1 ml injection')
                                ->columnSpanFull(),
                                Forms\Components\Textarea::make('description')
                                ->label('Description / Specification')
                                ->rows(2)
                                ->placeholder('Optional: dosage form, strength, size, etc.')
                                ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->helperText('Inactive commodities are hidden from assessment forms'),
                            ]),
                            Forms\Components\Section::make('Department Applicability')
                            ->description('Select which departments this commodity applies to. This drives what assessors see in the Health Products section.')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Forms\Components\CheckboxList::make('applicableDepartments')
                                ->label('Applicable Departments')
                                ->relationship('applicableDepartments', 'name')
                                ->options(
                                        AssessmentDepartment::where('is_active', true)
                                        ->orderBy('order')
                                        ->pluck('name', 'id')
                                )
                                ->columns(3)
                                ->helperText('Tick every department where this commodity should be assessed')
                                ->columnSpanFull(),
                            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('category.name')
                            ->label('Category')
                            ->badge()
                            ->color('primary')
                            ->sortable()
                            ->searchable(),
                            Tables\Columns\TextColumn::make('order')
                            ->label('Order')
                            ->sortable()
                            ->alignCenter()
                            ->width(70),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Commodity')
                            ->searchable()
                            ->sortable()
                            ->weight('medium')
                            ->description(fn(Commodity $record): string => $record->description ?? ''),
                            Tables\Columns\TextColumn::make('applicable_departments_count')
                            ->label('Departments')
                            ->counts('applicableDepartments')
                            ->badge()
                            ->color(fn($state) => $state > 0 ? 'success' : 'danger')
                            ->alignCenter()
                            ->tooltip(fn(Commodity $record) => $record->applicableDepartments->pluck('name')->join(', ') ?: 'No departments assigned'),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->alignCenter(),
                        ])
                        ->defaultSort('commodity_category_id')
                        ->filters([
                            Tables\Filters\SelectFilter::make('commodity_category_id')
                            ->label('Category')
                            ->options(CommodityCategory::orderBy('order')->pluck('name', 'id'))
                            ->searchable(),
                            Tables\Filters\SelectFilter::make('department')
                            ->label('Department')
                            ->options(AssessmentDepartment::where('is_active', true)->orderBy('order')->pluck('name', 'id'))
                            ->query(fn($query, $data) => $data['value'] ? $query->whereHas('applicableDepartments', fn($q) => $q->where('assessment_departments.id', $data['value'])) : $query
                            ),
                            Tables\Filters\TernaryFilter::make('is_active')
                            ->label('Active')
                            ->default(true),
                            Tables\Filters\Filter::make('no_departments')
                            ->label('No Departments Assigned')
                            ->query(fn($query) => $query->whereDoesntHave('applicableDepartments')),
                        ])
                        ->actions([
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\Action::make('manage_applicability')
                            ->label('Departments')
                            ->icon('heroicon-o-building-office-2')
                            ->color('info')
                            ->form(fn(Commodity $record) => [
                                Forms\Components\CheckboxList::make('department_ids')
                                ->label('Applicable Departments')
                                ->options(
                                        AssessmentDepartment::where('is_active', true)
                                        ->orderBy('order')
                                        ->pluck('name', 'id')
                                )
                                ->default($record->applicableDepartments->pluck('id')->toArray())
                                ->columns(2)
                                ->helperText('Save to update department applicability for this commodity'),
                                    ])
                            ->action(function (Commodity $record, array $data) {
                                $record->applicableDepartments()->sync($data['department_ids'] ?? []);
                                Notification::make()
                                        ->title('Applicability updated')
                                        ->body("Department assignments saved for '{$record->name}'.")
                                        ->success()
                                        ->send();
                            }),
                            Tables\Actions\Action::make('toggle_active')
                            ->label(fn(Commodity $record) => $record->is_active ? 'Deactivate' : 'Activate')
                            ->icon(fn(Commodity $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                            ->color(fn(Commodity $record) => $record->is_active ? 'warning' : 'success')
                            ->action(fn(Commodity $record) => $record->update(['is_active' => !$record->is_active])),
                            Tables\Actions\DeleteAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                // Bulk assign departments
                                Tables\Actions\BulkAction::make('bulk_assign_departments')
                                ->label('Assign Departments')
                                ->icon('heroicon-o-building-office-2')
                                ->form([
                                    Forms\Components\CheckboxList::make('department_ids')
                                    ->label('Add to Departments (will not remove existing)')
                                    ->options(
                                            AssessmentDepartment::where('is_active', true)
                                            ->orderBy('order')
                                            ->pluck('name', 'id')
                                    )
                                    ->columns(2)
                                    ->required(),
                                ])
                                ->action(function (Collection $records, array $data) {
                                    $deptIds = $data['department_ids'];
                                    foreach ($records as $record) {
                                        $record->applicableDepartments()->syncWithoutDetaching($deptIds);
                                    }
                                    Notification::make()
                                            ->title('Departments assigned')
                                            ->body("{$records->count()} commodities assigned to selected departments.")
                                            ->success()
                                            ->send();
                                })
                                ->deselectRecordsAfterCompletion(),
                                // Bulk remove from departments
                                Tables\Actions\BulkAction::make('bulk_remove_departments')
                                ->label('Remove from Departments')
                                ->icon('heroicon-o-minus-circle')
                                ->color('warning')
                                ->form([
                                    Forms\Components\CheckboxList::make('department_ids')
                                    ->label('Remove from Departments')
                                    ->options(
                                            AssessmentDepartment::where('is_active', true)
                                            ->orderBy('order')
                                            ->pluck('name', 'id')
                                    )
                                    ->columns(2)
                                    ->required(),
                                ])
                                ->action(function (Collection $records, array $data) {
                                    $deptIds = $data['department_ids'];
                                    foreach ($records as $record) {
                                        $record->applicableDepartments()->detach($deptIds);
                                    }
                                    Notification::make()
                                            ->title('Departments removed')
                                            ->body("{$records->count()} commodities removed from selected departments.")
                                            ->success()
                                            ->send();
                                })
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('bulk_activate')
                                ->label('Activate')
                                ->icon('heroicon-o-check-circle')
                                ->action(fn(Collection $records) => $records->each->update(['is_active' => true]))
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('bulk_deactivate')
                                ->label('Deactivate')
                                ->icon('heroicon-o-x-circle')
                                ->color('warning')
                                ->action(fn(Collection $records) => $records->each->update(['is_active' => false]))
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
                        ])
                        ->emptyStateHeading('No Commodities')
                        ->emptyStateDescription('Create commodities and assign them to departments and categories.')
                        ->emptyStateIcon('heroicon-o-squares-2x2');
    }

    public static function getRelations(): array {
        return [];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListCommodities::route('/'),
            'create' => Pages\CreateCommodity::route('/create'),
            'edit' => Pages\EditCommodity::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        $count = Commodity::where('is_active', true)
                ->whereDoesntHave('applicableDepartments')
                ->count();
        return $count > 0 ? "{$count} unassigned" : null;
    }
}
