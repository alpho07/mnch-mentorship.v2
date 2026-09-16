<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentDepartmentResource\Pages;
use App\Filament\Resources\AssessmentDepartmentResource\RelationManagers;
use App\Models\AssessmentDepartment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AssessmentDepartmentResource extends Resource {

    protected static ?string $model = AssessmentDepartment::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Assessment Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Departments';
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_assessment::department');}

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('view_any_assessment::department');}

    // ─────────────────────────────────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Department Details')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('name')
                                    ->label('Department Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Maternity Ward, NICU, Paediatric')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, $state, $context) {
                                        if ($context === 'create' && $state) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                    Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->alphaDash()
                                    ->helperText('Auto-generated from name. Used internally.'),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(fn() => (AssessmentDepartment::max('order') ?? 0) + 10)
                                    ->required()
                                    ->helperText('Determines tab order in Health Products form'),
                                    Forms\Components\ColorPicker::make('color')
                                    ->label('Color')
                                    ->default('#3B82F6')
                                    ->helperText('Used for badge/tab highlighting'),
                                    Forms\Components\TextInput::make('icon')
                                    ->label('Heroicon Name')
                                    ->placeholder('heroicon-o-building-office')
                                    ->helperText('Optional icon for the department tab'),
                                ]),
                                Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->helperText('Inactive departments are hidden from the assessment form'),
                            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('order')
                            ->label('#')
                            ->sortable()
                            ->alignCenter()
                            ->width(50),
                            Tables\Columns\ColorColumn::make('color')
                            ->label('')
                            ->width(30),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Department')
                            ->searchable()
                            ->sortable()
                            ->weight('semibold'),
                            Tables\Columns\TextColumn::make('slug')
                            ->label('Slug')
                            ->badge()
                            ->color('gray')
                            ->searchable(),
                            Tables\Columns\TextColumn::make('applicable_commodities_count')
                            ->label('Commodities')
                            ->counts('applicableCommodities')
                            ->badge()
                            ->color('success')
                            ->alignCenter()
                            ->sortable(),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->alignCenter(),
                        ])
                        ->defaultSort('order')
                        ->reorderable('order')
                        ->filters([
                            Tables\Filters\TernaryFilter::make('is_active')
                            ->label('Active')
                            ->default(true),
                        ])
                        ->actions([
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\Action::make('manage_commodities')
                            ->label('Commodities')
                            ->icon('heroicon-o-squares-2x2')
                            ->color('primary')
                            ->url(fn(AssessmentDepartment $record) => static::getUrl('edit', ['record' => $record])),
                            Tables\Actions\DeleteAction::make()
                            ->before(function (AssessmentDepartment $record) {
                                if ($record->applicableCommodities()->count() > 0) {
                                    \Filament\Notifications\Notification::make()
                                            ->title('Cannot delete')
                                            ->body('Remove all commodity applicabilities for this department first.')
                                            ->danger()
                                            ->send();
                                    return false;
                                }
                            }),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\BulkAction::make('activate')
                                ->label('Activate')
                                ->icon('heroicon-o-check-circle')
                                ->action(fn($records) => $records->each->update(['is_active' => true]))
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('deactivate')
                                ->label('Deactivate')
                                ->icon('heroicon-o-x-circle')
                                ->color('warning')
                                ->action(fn($records) => $records->each->update(['is_active' => false]))
                                ->deselectRecordsAfterCompletion(),
                            ]),
                        ])
                        ->emptyStateHeading('No Departments')
                        ->emptyStateDescription('Departments define the tabs in the Health Products assessment section.')
                        ->emptyStateIcon('heroicon-o-building-office-2');
    }

    public static function getRelations(): array {
        return [
            RelationManagers\ApplicableCommoditiesRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListAssessmentDepartments::route('/'),
            'create' => Pages\CreateAssessmentDepartment::route('/create'),
            'edit' => Pages\EditAssessmentDepartment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return (string) AssessmentDepartment::where('is_active', true)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'info';
    }
}
