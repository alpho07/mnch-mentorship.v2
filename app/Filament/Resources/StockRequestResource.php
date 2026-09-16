<?php

// Enhanced version of your existing StockRequestResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\StockRequestResource\Pages;
use App\Models\StockRequest;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class StockRequestResource extends Resource {

    protected static ?string $model = StockRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_stock::request');}

    // Add notification badge to show pending approvals
    public static function getNavigationBadge(): ?string {
        $user = auth()->user();

        if ($user->can('approve-stock-requests')) {
            $count = StockRequest::where('status', 'pending')
                    ->whereHas('centralStore', function ($query) use ($user) {
                        if (!$user->isAboveSite()) {
                            $query->whereIn('id', $user->scopedFacilityIds())
                                    ->where('is_central_store', true);
                        } else {
                            $query->where('is_central_store', true);
                        }
                    })
                    ->count();

            return $count > 0 ? (string) $count : null;
        }

        return null;
    }

    public static function getNavigationBadgeColor(): ?string {
        $count = (int) static::getNavigationBadge();

        if ($count > 10)
            return 'danger';
        if ($count > 5)
            return 'warning';
        if ($count > 0)
            return 'primary';

        return null;
    }

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Request Information')
                            ->schema([
                                Forms\Components\TextInput::make('request_number')
                                ->disabled()
                                ->dehydrated(false),
                                Forms\Components\Select::make('requesting_facility_id')
                                ->label('Requesting Facility')
                                ->relationship('requestingFacility', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                                Forms\Components\Select::make('central_store_id')
                                ->label('Central Store')
                                ->relationship('centralStore', 'name', fn($query) => $query->where('is_central_store', true))
                                ->searchable()
                                ->preload()
                                ->required(),
                                Forms\Components\Select::make('priority')
                                ->options([
                                    'low' => 'Low',
                                    'medium' => 'Medium',
                                    'high' => 'High',
                                    'urgent' => 'Urgent',
                                ])
                                ->default('medium')
                                ->required(),
                            ])->columns(2),
                            Forms\Components\Section::make('Request Details')
                            ->schema([
                                Forms\Components\DatePicker::make('request_date')
                                ->default(now())
                                ->required(),
                                Forms\Components\Textarea::make('notes')
                                ->rows(3),
                            ]),
                            Forms\Components\Section::make('Requested Items')
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                ->relationship()
                                ->schema([
                                    Forms\Components\Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->relationship('inventoryItem', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $item = \App\Models\InventoryItem::find($state);
                                            $set('unit_price', $item?->unit_price ?? 0);
                                        }
                                    }),
                                    Forms\Components\TextInput::make('quantity_requested')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                                    Forms\Components\TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->disabled(),
                                    Forms\Components\Textarea::make('notes')
                                    ->rows(2),
                                ])
                                ->columns(2)
                                ->collapsible()
                                ->defaultItems(1),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('request_number')
                            ->searchable()
                            ->sortable()
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('requestingFacility.name')
                            ->label('Requesting Facility')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('centralStore.name')
                            ->label('Central Store')
                            ->searchable()
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'partially_approved' => 'info',
                                        'rejected' => 'danger',
                                        'dispatched' => 'primary',
                                        'received' => 'success',
                                        default => 'gray',
                                    }),
                            Tables\Columns\TextColumn::make('priority')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'urgent' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        'low' => 'gray',
                                    }),
                            Tables\Columns\TextColumn::make('request_date')
                            ->date()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('total_items')
                            ->label('Items')
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\TextColumn::make('total_requested_value')
                            ->label('Value')
                            ->money('KES'),
                            // Add days pending for approval workflow
                            Tables\Columns\TextColumn::make('days_pending')
                            ->label('Days Pending')
                            ->getStateUsing(fn($record) => $record->status === 'pending' ? now()->diffInDays($record->created_at) : null)
                            ->badge()
                            ->color(fn($state) => match (true) {
                                        $state === null => 'gray',
                                        $state <= 1 => 'success',
                                        $state <= 3 => 'warning',
                                        default => 'danger'
                                    })
                            ->visible(fn() => auth()->user()->can('approve-stock-requests')),
                        ])
                        ->defaultSort('created_at', 'desc')
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'dispatched' => 'Dispatched',
                                'received' => 'Received',
                            ]),
                            Tables\Filters\SelectFilter::make('priority')
                            ->options([
                                'urgent' => 'Urgent',
                                'high' => 'High',
                                'medium' => 'Medium',
                                'low' => 'Low',
                            ]),
                            Tables\Filters\SelectFilter::make('requesting_facility')
                            ->relationship('requestingFacility', 'name'),
                            Tables\Filters\SelectFilter::make('central_store')
                            ->relationship('centralStore', 'name'),
                            // Add approval-specific filters
                            Tables\Filters\Filter::make('needs_approval')
                            ->label('Needs Approval')
                            ->query(fn(Builder $query): Builder => $query->where('status', 'pending'))
                            ->visible(fn() => auth()->user()->can('approve-stock-requests')),
                            Tables\Filters\Filter::make('overdue')
                            ->label('Overdue (3+ Days)')
                            ->query(fn(Builder $query): Builder =>
                                    $query->where('status', 'pending')
                                    ->where('created_at', '<', now()->subDays(3))
                            )
                            ->visible(fn() => auth()->user()->can('approve-stock-requests')),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make()
                            ->visible(fn($record) => $record->status === 'pending'),
                            // Add approval workflow actions
                            Tables\Actions\Action::make('quick_approve')
                            ->label('Quick Approve')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->visible(fn($record) => $record->status === 'pending' &&
                                    auth()->user()->can('approve-stock-requests') &&
                                    $record->canBeQuickApproved())
                            ->requiresConfirmation()
                            ->modalHeading('Quick Approve Request')
                            ->modalDescription(fn($record) =>
                                    "This will approve all items at requested quantities for {$record->requestingFacility->name}. Are you sure?"
                            )
                            ->action(function ($record) {
                                try {
                                    $record->quickApprove(auth()->user());

                                    Notification::make()
                                            ->title('Request Approved')
                                            ->success()
                                            ->body("Request {$record->request_number} has been approved and dispatched.")
                                            ->send();
                                } catch (\Exception $e) {
                                    Notification::make()
                                            ->title('Approval Failed')
                                            ->danger()
                                            ->body($e->getMessage())
                                            ->send();
                                }
                            }),
                            Tables\Actions\Action::make('detailed_review')
                            ->label('Detailed Review')
                            ->icon('heroicon-o-eye')
                            ->color('info')
                            ->visible(fn($record) => $record->status === 'pending' &&
                                    auth()->user()->can('approve-stock-requests'))
                            ->url(fn($record): string =>
                                    route('filament.admin.resources.stock-requests.review', [
                                        'record' => $record->id
                                    ])
                            ),
                            Tables\Actions\Action::make('reject')
                            ->label('Reject')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->visible(fn($record) => $record->status === 'pending' &&
                                    auth()->user()->can('approve-stock-requests'))
                            ->form([
                                Forms\Components\Textarea::make('rejection_reason')
                                ->label('Rejection Reason')
                                ->required()
                                ->rows(3)
                                ->placeholder('Please provide a clear reason for rejection...'),
                            ])
                            ->action(function ($record, array $data) {
                                try {
                                    $record->reject(auth()->user(), $data['rejection_reason']);

                                    Notification::make()
                                            ->title('Request Rejected')
                                            ->success()
                                            ->body("Request {$record->request_number} has been rejected.")
                                            ->send();
                                } catch (\Exception $e) {
                                    Notification::make()
                                            ->title('Rejection Failed')
                                            ->danger()
                                            ->body($e->getMessage())
                                            ->send();
                                }
                            }),
                            Tables\Actions\Action::make('dispatch')
                            ->label('Dispatch')
                            ->icon('heroicon-o-truck')
                            ->color('primary')
                            ->visible(fn($record) => $record->can_be_dispatched &&
                                    auth()->user()->can('dispatch-stock-requests'))
                            ->requiresConfirmation()
                            ->action(function ($record) {
                                try {
                                    $record->dispatch(auth()->user());

                                    Notification::make()
                                            ->title('Items Dispatched')
                                            ->success()
                                            ->send();
                                } catch (\Exception $e) {
                                    Notification::make()
                                            ->title('Dispatch Failed')
                                            ->danger()
                                            ->body($e->getMessage())
                                            ->send();
                                }
                            }),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make()
                                ->requiresConfirmation(),
                                // Add bulk approval actions
                                Tables\Actions\BulkAction::make('bulk_approve')
                                ->label('Bulk Approve')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->visible(fn() => auth()->user()->can('approve-stock-requests'))
                                ->requiresConfirmation()
                                ->action(function ($records) {
                                    $results = StockRequest::bulkApprove(
                                            $records->pluck('id')->toArray(),
                                            auth()->user()
                                    );

                                    $successCount = count($results['approved']);
                                    $failedCount = count($results['failed']);

                                    if ($successCount > 0) {
                                        Notification::make()
                                                ->title('Bulk Approval Complete')
                                                ->success()
                                                ->body("Approved {$successCount} requests" .
                                                        ($failedCount > 0 ? ", {$failedCount} failed" : ""))
                                                ->send();
                                    }
                                })
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('bulk_reject')
                                ->label('Bulk Reject')
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->visible(fn() => auth()->user()->can('approve-stock-requests'))
                                ->form([
                                    Forms\Components\Textarea::make('rejection_reason')
                                    ->label('Rejection Reason')
                                    ->required()
                                    ->rows(3),
                                ])
                                ->action(function ($records, array $data) {
                                    $results = StockRequest::bulkReject(
                                            $records->pluck('id')->toArray(),
                                            auth()->user(),
                                            $data['rejection_reason']
                                    );

                                    $successCount = count($results['rejected']);

                                    if ($successCount > 0) {
                                        Notification::make()
                                                ->title('Bulk Rejection Complete')
                                                ->success()
                                                ->body("Rejected {$successCount} requests")
                                                ->send();
                                    }
                                })
                                ->deselectRecordsAfterCompletion(),
                            ]),
                        ])
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
        ]);
    }

    // Enhanced query to prioritize pending requests for approvers
    public static function getEloquentQuery(): Builder {
        $user = auth()->user();

        return parent::getEloquentQuery()
                        ->with(['requestingFacility', 'centralStore', 'requestedBy', 'items.inventoryItem'])
                        ->when($user->can('approve-stock-requests'), function ($query) use ($user) {
                            // Show pending requests first for approvers
                            return $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                                            ->orderByRaw("CASE
                               WHEN priority = 'urgent' THEN 0
                               WHEN priority = 'high' THEN 1
                               WHEN priority = 'medium' THEN 2
                               ELSE 3 END")
                                            ->orderBy('created_at', 'desc');
                        })
                        ->when(!$user->can('approve-stock-requests'), function ($query) {
                            return $query->orderBy('created_at', 'desc');
                        });
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListStockRequests::route('/'),
            'create' => Pages\CreateStockRequest::route('/create'),
            'view' => Pages\ViewStockRequest::route('/{record}'),
            'edit' => Pages\EditStockRequest::route('/{record}/edit'),
            'review' => Pages\ReviewStockRequest::route('/{record}/review'), // Add review page
        ];
    }
}
