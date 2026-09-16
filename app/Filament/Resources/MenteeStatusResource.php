<?php

// app/Filament/Resources/MenteeStatusResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\MenteeStatusResource\Pages;
use App\Models\MenteeStatus;
use BackedEnum;
use UnitEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Illuminate\Database\Eloquent\Builder;

class MenteeStatusResource extends Resource {

    protected static ?string $model = MenteeStatus::class;
    // Place where you want it in the nav
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Mentee Statuses';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_mentee::status');}

    public static function getGloballySearchableAttributes(): array {
        return ['name'];
    }

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make()
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->label('Status Name')
                                ->required()
                                ->maxLength(100)
                                ->unique(ignoreRecord: true),
                                Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Columns\TextColumn::make('id')->sortable()->toggleable()->toggledHiddenByDefault(),
                            Columns\TextColumn::make('name')
                            ->label('Status')
                            ->searchable()
                            ->sortable(),
                            Columns\BadgeColumn::make('is_active')
                            ->label('State')
                            ->formatStateUsing(fn(bool $state) => $state ? 'Active' : 'Inactive')
                            ->colors([
                                'success' => fn(bool $state) => $state === true,
                                'danger' => fn(bool $state) => $state === false,
                            ])
                            ->icons([
                                'heroicon-s-check-circle' => fn(bool $state) => $state === true,
                                'heroicon-s-x-circle' => fn(bool $state) => $state === false,
                            ])
                            ->sortable(),
                            Columns\TextColumn::make('updated_at')->since()->label('Updated'),
                            Columns\TextColumn::make('created_at')->dateTime()->toggleable()->toggledHiddenByDefault(),
                        ])
                        ->filters([
                            Filters\SelectFilter::make('is_active')
                            ->label('State')
                            ->options(['1' => 'Active', '0' => 'Inactive'])
                            ->query(function (Builder $query, array $data) {
                                if ($data['value'] === '1') {
                                    $query->where('is_active', true);
                                } elseif ($data['value'] === '0') {
                                    $query->where('is_active', false);
                                }
                            }),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkAction::make('activate')
                            ->label('Activate')
                            ->icon('heroicon-o-check')
                            ->requiresConfirmation()
                            ->action(fn($records) => $records->each->update(['is_active' => true])),
                            Tables\Actions\BulkAction::make('deactivate')
                            ->label('Deactivate')
                            ->icon('heroicon-o-x-mark')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(fn($records) => $records->each->update(['is_active' => false])),
                            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListMenteeStatuses::route('/'),
            'create' => Pages\CreateMenteeStatus::route('/create'),
            'view' => Pages\ViewMenteeStatus::route('/{record}'),
            'edit' => Pages\EditMenteeStatus::route('/{record}/edit'),
        ];
    }
}
