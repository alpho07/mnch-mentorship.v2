<?php

// Create SupplierResource

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class SupplierResource extends Resource {

    protected static ?string $model = Supplier::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_supplier');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Supplier Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Enter supplier name'),
                                Forms\Components\TextInput::make('supplier_code')
                                ->label('Supplier Code')
                                ->unique(ignoreRecord: true)
                                ->placeholder('AUTO-GENERATED')
                                ->disabled()
                                ->dehydrated(false),
                                Forms\Components\Select::make('supplier_type')
                                ->options([
                                    'manufacturer' => 'Manufacturer',
                                    'distributor' => 'Distributor',
                                    'wholesaler' => 'Wholesaler',
                                    'retailer' => 'Retailer',
                                    'government' => 'Government Agency',
                                    'ngo' => 'NGO/Non-Profit'
                                ])
                                ->required()
                                ->default('distributor'),
                                Forms\Components\Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                    'suspended' => 'Suspended',
                                    'blacklisted' => 'Blacklisted'
                                ])
                                ->required()
                                ->default('active'),
                            ])->columns(2),
                            Forms\Components\Section::make('Contact Information')
                            ->schema([
                                Forms\Components\TextInput::make('contact_person')
                                ->maxLength(255)
                                ->placeholder('Primary contact person'),
                                Forms\Components\TextInput::make('phone')
                                ->tel()
                                ->maxLength(20)
                                ->placeholder('+254700123456'),
                                Forms\Components\TextInput::make('email')
                                ->email()
                                ->maxLength(255)
                                ->placeholder('supplier@example.com'),
                                Forms\Components\TextInput::make('website')
                                ->url()
                                ->maxLength(255)
                                ->placeholder('https://www.supplier.com'),
                            ])->columns(2),
                            Forms\Components\Section::make('Address & Location')
                            ->schema([
                                Forms\Components\Textarea::make('address')
                                ->rows(3)
                                ->placeholder('Physical address'),
                                Forms\Components\TextInput::make('city')
                                ->maxLength(100),
                                Forms\Components\TextInput::make('postal_code')
                                ->maxLength(20),
                                Forms\Components\Select::make('country')
                                ->options([
                                    'KE' => 'Kenya',
                                    'UG' => 'Uganda',
                                    'TZ' => 'Tanzania',
                                    'RW' => 'Rwanda',
                                    'Other' => 'Other'
                                ])
                                ->default('KE')
                                ->searchable(),
                            ])->columns(2),
                            Forms\Components\Section::make('Business Information')
                            ->schema([
                                Forms\Components\TextInput::make('tax_number')
                                ->label('Tax/VAT Number')
                                ->maxLength(50),
                                Forms\Components\TextInput::make('registration_number')
                                ->label('Business Registration Number')
                                ->maxLength(50),
                                Forms\Components\Select::make('payment_terms')
                                ->options([
                                    'cash_on_delivery' => 'Cash on Delivery',
                                    'net_7' => 'Net 7 Days',
                                    'net_15' => 'Net 15 Days',
                                    'net_30' => 'Net 30 Days',
                                    'net_60' => 'Net 60 Days',
                                    'net_90' => 'Net 90 Days',
                                ])
                                ->default('net_30'),
                                Forms\Components\TextInput::make('credit_limit')
                                ->numeric()
                                ->prefix('KES')
                                ->placeholder('0.00'),
                            ])->columns(2),
                            Forms\Components\Section::make('Additional Information')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                ->rows(3)
                                ->placeholder('Additional notes about the supplier'),
                                Forms\Components\Toggle::make('is_preferred')
                                ->label('Preferred Supplier')
                                ->helperText('Mark as preferred supplier'),
                                Forms\Components\Toggle::make('requires_po')
                                ->label('Requires Purchase Order')
                                ->helperText('Supplier requires formal PO')
                                ->default(true),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('supplier_code')
                            ->label('Code')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('name')
                            ->searchable()
                            ->sortable()
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('supplier_type')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'gray',
                                        'suspended' => 'warning',
                                        'blacklisted' => 'danger',
                                    }),
                            Tables\Columns\TextColumn::make('contact_person')
                            ->searchable(),
                            Tables\Columns\TextColumn::make('phone')
                            ->searchable(),
                            Tables\Columns\TextColumn::make('email')
                            ->searchable(),
                            Tables\Columns\TextColumn::make('inventoryItems_count')
                            ->label('Items')
                            ->counts('inventoryItems')
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\IconColumn::make('is_preferred')
                            ->label('Preferred')
                            ->boolean(),
                            Tables\Columns\TextColumn::make('created_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                                'blacklisted' => 'Blacklisted'
                            ]),
                            Tables\Filters\SelectFilter::make('supplier_type')
                            ->options([
                                'manufacturer' => 'Manufacturer',
                                'distributor' => 'Distributor',
                                'wholesaler' => 'Wholesaler',
                                'retailer' => 'Retailer',
                                'government' => 'Government Agency',
                                'ngo' => 'NGO/Non-Profit'
                            ]),
                            Tables\Filters\TernaryFilter::make('is_preferred')
                            ->label('Preferred Supplier'),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\Action::make('activate')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->visible(fn($record) => $record->status !== 'active')
                            ->action(fn($record) => $record->update(['status' => 'active']))
                            ->requiresConfirmation(),
                            Tables\Actions\Action::make('suspend')
                            ->icon('heroicon-o-pause-circle')
                            ->color('warning')
                            ->visible(fn($record) => $record->status === 'active')
                            ->action(fn($record) => $record->update(['status' => 'suspended']))
                            ->requiresConfirmation(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                                Tables\Actions\BulkAction::make('activate')
                                ->label('Activate Selected')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->action(fn($records) => $records->each->update(['status' => 'active'])),
                            ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Infolists\Components\Section::make('Supplier Details')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                ->schema([
                                    Infolists\Components\TextEntry::make('supplier_code')
                                    ->label('Supplier Code')
                                    ->badge()
                                    ->color('primary'),
                                    Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                                'active' => 'success',
                                                'inactive' => 'gray',
                                                'suspended' => 'warning',
                                                'blacklisted' => 'danger',
                                            }),
                                    Infolists\Components\TextEntry::make('supplier_type')
                                    ->badge(),
                                ]),
                                Infolists\Components\TextEntry::make('name')
                                ->size('lg')
                                ->weight('bold'),
                            ]),
                            Infolists\Components\Section::make('Contact Information')
                            ->schema([
                                Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('contact_person'),
                                    Infolists\Components\TextEntry::make('phone'),
                                    Infolists\Components\TextEntry::make('email')
                                    ->copyable(),
                                    Infolists\Components\TextEntry::make('website')
                                    ->url()
                                    ->openUrlInNewTab(),
                                ]),
                            ]),
                            Infolists\Components\Section::make('Business Information')
                            ->schema([
                                Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('tax_number'),
                                    Infolists\Components\TextEntry::make('registration_number'),
                                    Infolists\Components\TextEntry::make('payment_terms'),
                                    Infolists\Components\TextEntry::make('credit_limit')
                                    ->money('KES'),
                                ]),
                            ]),
                            Infolists\Components\Section::make('Performance Metrics')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                ->schema([
                                    Infolists\Components\TextEntry::make('inventoryItems_count')
                                    ->label('Total Items Supplied')
                                    ->badge()
                                    ->color('primary'),
                                    Infolists\Components\IconEntry::make('is_preferred')
                                    ->label('Preferred Supplier')
                                    ->boolean(),
                                    Infolists\Components\IconEntry::make('requires_po')
                                    ->label('Requires PO')
                                    ->boolean(),
                                ]),
                            ]),
        ]);
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            // 'view' => Pages\ViewSupplier::route('/{record}'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
