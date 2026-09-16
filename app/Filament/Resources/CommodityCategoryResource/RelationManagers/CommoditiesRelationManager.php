<?php

namespace App\Filament\Resources\CommodityCategoryResource\RelationManagers;

use App\Models\AssessmentDepartment;
use App\Models\Commodity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class CommoditiesRelationManager extends RelationManager {

    protected static string $relationship = 'commodities';
    protected static ?string $title = 'Commodities in this Category';

    // ─────────────────────────────────────────────────────────────────────────
    // FORM — create / edit commodity inline
    // ─────────────────────────────────────────────────────────────────────────

    public function form(Form $form): Form {
        return $form->schema([
                    Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('name')
                                ->label('Commodity Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., Oxytocin 10 IU/1 ml injection')
                                ->columnSpan(2),
                                Forms\Components\Textarea::make('description')
                                ->label('Description / Specification')
                                ->rows(2)
                                ->placeholder('Optional: dosage form, strength, size, etc.')
                                ->columnSpan(2),
                                Forms\Components\TextInput::make('order')
                                ->label('Display Order')
                                ->numeric()
                                ->default(fn() => (Commodity::where(
                                                'commodity_category_id',
                                                $this->getOwnerRecord()->id
                                        )->max('order') ?? 0) + 10)
                                ->required(),
                                Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                    ]),
                            Forms\Components\Section::make('Department Applicability')
                            ->description('Which departments should this commodity be assessed in?')
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
                                ->columnSpanFull(),
                            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table {
        return $table
                        ->recordTitleAttribute('name')
                        ->defaultSort('order')
                        ->reorderable('order')
                        ->columns([
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
                            // Show which departments this commodity is assigned to
                            Tables\Columns\TextColumn::make('applicableDepartments.name')
                            ->label('Departments')
                            ->badge()
                            ->separator(', ')
                            ->color('info')
                            ->searchable()
                            ->wrap(),
                            Tables\Columns\TextColumn::make('applicable_departments_count')
                            ->label('# Depts')
                            ->counts('applicableDepartments')
                            ->badge()
                            ->color(fn($state) => $state > 0 ? 'success' : 'danger')
                            ->alignCenter()
                            ->tooltip(fn(Commodity $record) => $record->applicableDepartments->isEmpty() ? '⚠ No departments — this commodity will NOT appear in assessments' : null
                            ),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->alignCenter(),
                        ])
                        ->filters([
                            Tables\Filters\TernaryFilter::make('is_active')
                            ->label('Active')
                            ->default(true),
                            Tables\Filters\Filter::make('no_departments')
                            ->label('Missing Departments')
                            ->query(fn($query) => $query->whereDoesntHave('applicableDepartments')),
                        ])
                        ->headerActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Add Commodity')
                            ->icon('heroicon-o-plus'),
                            // Quick: apply all to a specific department
                            Tables\Actions\Action::make('assign_all_to_department')
                            ->label('Assign All to Department')
                            ->icon('heroicon-o-building-office-2')
                            ->color('info')
                            ->form([
                                Forms\Components\Select::make('department_id')
                                ->label('Department')
                                ->options(
                                        AssessmentDepartment::where('is_active', true)
                                        ->orderBy('order')
                                        ->pluck('name', 'id')
                                )
                                ->required()
                                ->searchable()
                                ->helperText('All active commodities in this category will be attached to the selected department'),
                            ])
                            ->action(function (array $data) {
                                $departmentId = $data['department_id'];
                                $commodities = $this->getOwnerRecord()
                                        ->commodities()
                                        ->where('is_active', true)
                                        ->pluck('id');

                                $department = AssessmentDepartment::find($departmentId);
                                $department->applicableCommodities()->syncWithoutDetaching($commodities);

                                Notification::make()
                                        ->title('Department assigned')
                                        ->body("{$commodities->count()} commodities assigned to '{$department->name}'.")
                                        ->success()
                                        ->send();
                            }),
                        ])
                        ->actions([
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\Action::make('manage_depts')
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
                                ->columns(2),
                                    ])
                            ->action(function (Commodity $record, array $data) {
                                $record->applicableDepartments()->sync($data['department_ids'] ?? []);
                                Notification::make()
                                        ->title('Departments updated')
                                        ->success()
                                        ->send();
                            }),
                            Tables\Actions\Action::make('toggle_active')
                            ->label(fn(Commodity $r) => $r->is_active ? 'Deactivate' : 'Activate')
                            ->icon(fn(Commodity $r) => $r->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                            ->color(fn(Commodity $r) => $r->is_active ? 'warning' : 'success')
                            ->action(fn(Commodity $r) => $r->update(['is_active' => !$r->is_active])),
                            Tables\Actions\DeleteAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\BulkAction::make('bulk_assign')
                                ->label('Assign Departments')
                                ->icon('heroicon-o-building-office-2')
                                ->form([
                                    Forms\Components\CheckboxList::make('department_ids')
                                    ->label('Add to Departments')
                                    ->options(
                                            AssessmentDepartment::where('is_active', true)
                                            ->orderBy('order')
                                            ->pluck('name', 'id')
                                    )
                                    ->columns(2)
                                    ->required(),
                                ])
                                ->action(function (Collection $records, array $data) {
                                    foreach ($records as $r) {
                                        $r->applicableDepartments()->syncWithoutDetaching($data['department_ids']);
                                    }
                                    Notification::make()
                                            ->title("{$records->count()} commodities assigned to departments")
                                            ->success()
                                            ->send();
                                })
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('bulk_activate')
                                ->label('Activate')
                                ->icon('heroicon-o-check-circle')
                                ->action(fn($r) => $r->each->update(['is_active' => true]))
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('bulk_deactivate')
                                ->label('Deactivate')
                                ->icon('heroicon-o-x-circle')
                                ->color('warning')
                                ->action(fn($r) => $r->each->update(['is_active' => false]))
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
        ]);
    }
}
