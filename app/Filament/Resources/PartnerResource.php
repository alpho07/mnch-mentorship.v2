<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages;
use App\Models\Partner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

class PartnerResource extends Resource {

    protected static ?string $model = Partner::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Partners';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_partner');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Section::make('Basic Information')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                    Select::make('type')
                                    ->options(Partner::getTypeOptions())
                                    ->required()
                                    ->searchable(),
                                    Toggle::make('is_active')
                                    ->label('Active Status')
                                    ->default(true),
                                ]),
                                Textarea::make('description')
                                ->rows(3)
                                ->columnSpanFull(),
                            ]),
                            Section::make('Contact Information')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    TextInput::make('contact_person')
                                    ->label('Contact Person')
                                    ->maxLength(255),
                                    TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                    TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(20),
                                    TextInput::make('registration_number')
                                    ->label('Registration Number')
                                    ->maxLength(100),
                                ]),
                                TextInput::make('website')
                                ->url()
                                ->maxLength(255)
                                ->prefix('https://')
                                ->columnSpanFull(),
                                Textarea::make('address')
                                ->rows(2)
                                ->columnSpanFull(),
                            ])
                            ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            TextColumn::make('name')
                            ->searchable()
                            ->sortable()
                            ->weight('bold'),
                            BadgeColumn::make('type')
                            ->colors([
                                'primary' => 'ngo',
                                'success' => 'international',
                                'warning' => 'private',
                                'info' => 'faith_based',
                                'secondary' => 'academic',
                                'danger' => 'development',
                                'gray' => 'other',
                            ])
                            ->formatStateUsing(fn(string $state): string =>
                                    Partner::getTypeOptions()[$state] ?? ucfirst($state)
                            ),
                            TextColumn::make('contact_person')
                            ->label('Contact Person')
                            ->searchable()
                            ->toggleable(),
                            TextColumn::make('email')
                            ->searchable()
                            ->toggleable()
                            ->copyable(),
                            TextColumn::make('phone')
                            ->searchable()
                            ->toggleable()
                            ->copyable(),
                            BooleanColumn::make('is_active')
                            ->label('Active')
                            ->sortable(),
                            TextColumn::make('training_count')
                            ->label('Trainings')
                            ->getStateUsing(fn(Partner $record): int => $record->training_count)
                            ->badge()
                            ->color('success')
                            ->sortable(),
                            TextColumn::make('active_training_count')
                            ->label('Active Trainings')
                            ->getStateUsing(fn(Partner $record): int => $record->active_training_count)
                            ->badge()
                            ->color('warning')
                            ->toggleable(),
                            TextColumn::make('created_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            SelectFilter::make('type')
                            ->options(Partner::getTypeOptions())
                            ->multiple(),
                            TernaryFilter::make('is_active')
                            ->label('Active Status')
                            ->boolean()
                            ->trueLabel('Active partners only')
                            ->falseLabel('Inactive partners only')
                            ->native(false),
                            Tables\Filters\TrashedFilter::make(),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\ViewAction::make()
                                ->color('info'),
                                Tables\Actions\EditAction::make()
                                ->color('warning'),
                                Tables\Actions\Action::make('view_trainings')
                                ->label('View Trainings')
                                ->icon('heroicon-o-academic-cap')
                                ->color('success')
//                        ->url(fn (Partner $record): string => 
//                            route('filament.admin.resources.global-trainings.index', [
//                                'tableFilters[partner][values][0]' => $record->id
//                            ])
//                        )
                                ->visible(fn(Partner $record): bool => $record->training_count > 0),
                                Tables\Actions\DeleteAction::make(),
                                Tables\Actions\ForceDeleteAction::make(),
                                Tables\Actions\RestoreAction::make(),
                            ])
                            ->label('Actions')
                            ->icon('heroicon-m-ellipsis-horizontal')
                            ->size('sm')
                            ->color('gray')
                            ->button()
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                                Tables\Actions\ForceDeleteBulkAction::make(),
                                Tables\Actions\RestoreBulkAction::make(),
                                Tables\Actions\BulkAction::make('activate')
                                ->label('Activate Selected')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->action(function ($records) {
                                    $records->each(fn(Partner $record) =>
                                            $record->update(['is_active' => true])
                                    );
                                })
                                ->requiresConfirmation(),
                                Tables\Actions\BulkAction::make('deactivate')
                                ->label('Deactivate Selected')
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->action(function ($records) {
                                    $records->each(fn(Partner $record) =>
                                            $record->update(['is_active' => false])
                                    );
                                })
                                ->requiresConfirmation(),
                            ]),
                        ])
                        ->defaultSort('name', 'asc')
                        ->striped()
                        ->emptyStateHeading('No Partners Found')
                        ->emptyStateDescription('Create your first partner organization to get started.')
                        ->emptyStateIcon('heroicon-o-building-office')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Create Partner')
                            ->icon('heroicon-o-plus'),
        ]);
    }

    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->withoutGlobalScopes([
                            SoftDeletingScope::class,
        ]);
    }

    public static function getRelations(): array {
        return [
                //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListPartners::route('/'),
            'create' => Pages\CreatePartner::route('/create'),
            'view' => Pages\ViewPartner::route('/{record}'),
            'edit' => Pages\EditPartner::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'success';
    }

    public static function getGloballySearchableAttributes(): array {
        return ['name', 'contact_person', 'email', 'registration_number'];
    }
}
