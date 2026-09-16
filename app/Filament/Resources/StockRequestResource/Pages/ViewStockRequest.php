<?php
// app/Filament/Resources/StockRequestResource/Pages/ViewStockRequest.php

namespace App\Filament\Resources\StockRequestResource\Pages;

use App\Filament\Resources\StockRequestResource;
use App\Models\StockRequest;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification; // Add this import

class ViewStockRequest extends ViewRecord
{
    protected static string $resource = StockRequestResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('request_number')
                                    ->label('Request Number')
                                    ->badge()
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'partially_approved' => 'info',
                                        'rejected' => 'danger',
                                        'dispatched' => 'primary',
                                        'received' => 'success',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('priority')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'urgent' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        'low' => 'gray',
                                    }),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('requestingFacility.name')
                                    ->label('Requesting Facility'),
                                Infolists\Components\TextEntry::make('centralStore.name')
                                    ->label('Central Store'),
                                Infolists\Components\TextEntry::make('requestedBy.full_name')
                                    ->label('Requested By'),
                                Infolists\Components\TextEntry::make('request_date')
                                    ->date(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Approval Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('approvedBy.full_name')
                                    ->label('Approved By')
                                    ->placeholder('Not yet approved'),
                                Infolists\Components\TextEntry::make('approved_date')
                                    ->date()
                                    ->placeholder('Not yet approved'),
                                Infolists\Components\TextEntry::make('dispatchedBy.full_name')
                                    ->label('Dispatched By')
                                    ->placeholder('Not yet dispatched'),
                                Infolists\Components\TextEntry::make('dispatch_date')
                                    ->date()
                                    ->placeholder('Not yet dispatched'),
                            ]),
                    ])
                    ->visible(fn (StockRequest $record): bool =>
                        $record->approved_by || $record->dispatched_by
                    ),

                Infolists\Components\Section::make('Requested Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\Grid::make(6)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('inventoryItem.name')
                                            ->label('Item')
                                            ->weight('bold'),
                                        Infolists\Components\TextEntry::make('quantity_requested')
                                            ->label('Requested')
                                            ->badge()
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('quantity_approved')
                                            ->label('Approved')
                                            ->badge()
                                            ->color('success'),
                                        Infolists\Components\TextEntry::make('quantity_dispatched')
                                            ->label('Dispatched')
                                            ->badge()
                                            ->color('primary'),
                                        Infolists\Components\TextEntry::make('quantity_received')
                                            ->label('Received')
                                            ->badge()
                                            ->color('success'),
                                        Infolists\Components\TextEntry::make('total_requested_value')
                                            ->label('Total Value')
                                            ->money('KES'),
                                    ]),
                                Infolists\Components\TextEntry::make('notes')
                                    ->label('Notes')
                                    ->placeholder('No notes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),
                    ]),

                Infolists\Components\Section::make('Summary')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_items')
                                    ->label('Total Items')
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('total_requested_value')
                                    ->label('Total Requested Value')
                                    ->money('KES'),
                                Infolists\Components\TextEntry::make('total_approved_value')
                                    ->label('Total Approved Value')
                                    ->money('KES'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Notes & Comments')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Request Notes')
                            ->placeholder('No additional notes'),
                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->placeholder('Not rejected')
                            ->visible(fn (?StockRequest $record): bool => $record?->status === 'rejected'),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (?StockRequest $record): bool => $record?->status === 'pending'),

            Actions\Action::make('approve')
                ->label('Approve Request')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (?StockRequest $record): bool => $record?->can_be_approved ?? false)
                ->form([
                    Forms\Components\Textarea::make('approval_notes')
                        ->label('Approval Notes')
                        ->rows(3),
                    Forms\Components\Repeater::make('item_approvals')
                        ->label('Item Approvals')
                        ->schema([
                            Forms\Components\TextInput::make('item_name')
                                ->label('Item')
                                ->disabled(),
                            Forms\Components\TextInput::make('quantity_requested')
                                ->label('Requested')
                                ->disabled(),
                            Forms\Components\TextInput::make('quantity_approved')
                                ->label('Approve Quantity')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->default(function (StockRequest $record) {
                            return $record->items->map(function ($item) {
                                return [
                                    'item_id' => $item->id,
                                    'item_name' => $item->inventoryItem->name,
                                    'quantity_requested' => $item->quantity_requested,
                                    'quantity_approved' => $item->quantity_requested,
                                ];
                            })->toArray();
                        })
                        ->addable(false)
                        ->deletable(false),
                ])
                ->action(function (StockRequest $record, array $data): void {
                    try {
                        $itemApprovals = collect($data['item_approvals'])
                            ->pluck('quantity_approved', 'item_id')
                            ->toArray();

                        $record->approve(auth()->user(), $itemApprovals);

                        // ✅ Correct Filament notification
                        Notification::make()
                            ->title('Request Approved Successfully')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        // ✅ Correct error notification
                        Notification::make()
                            ->title('Approval Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('reject')
                ->label('Reject Request')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (?StockRequest $record): bool => $record?->can_be_approved ?? false)
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (StockRequest $record, array $data): void {
                    try {
                        $record->reject(auth()->user(), $data['rejection_reason']);

                        // ✅ Correct Filament notification
                        Notification::make()
                            ->title('Request Rejected')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        // ✅ Correct error notification
                        Notification::make()
                            ->title('Rejection Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('dispatch')
                ->label('Dispatch Items')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn (?StockRequest $record): bool => $record?->can_be_dispatched ?? false)
                ->requiresConfirmation()
                ->modalHeading('Dispatch Request Items')
                ->modalDescription('This will dispatch the approved items and update stock levels.')
                ->action(function (StockRequest $record): void {
                    try {
                        $record->dispatch(auth()->user());

                        // ✅ Correct Filament notification
                        Notification::make()
                            ->title('Items Dispatched Successfully')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        // ✅ Correct error notification
                        Notification::make()
                            ->title('Dispatch Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('receive')
                ->label('Receive Items')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn (?StockRequest $record): bool => $record?->can_be_received ?? false)
                ->form([
                    Forms\Components\Repeater::make('received_items')
                        ->label('Received Items')
                        ->schema([
                            Forms\Components\TextInput::make('item_name')
                                ->label('Item')
                                ->disabled(),
                            Forms\Components\TextInput::make('quantity_dispatched')
                                ->label('Dispatched')
                                ->disabled(),
                            Forms\Components\TextInput::make('quantity_received')
                                ->label('Quantity Received')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->default(function (StockRequest $record) {
                            return $record->items->map(function ($item) {
                                return [
                                    'item_id' => $item->id,
                                    'item_name' => $item->inventoryItem->name,
                                    'quantity_dispatched' => $item->quantity_dispatched,
                                    'quantity_received' => $item->quantity_dispatched,
                                ];
                            })->toArray();
                        })
                        ->addable(false)
                        ->deletable(false),
                ])
                ->action(function (StockRequest $record, array $data): void {
                    try {
                        $receivedQuantities = collect($data['received_items'])
                            ->pluck('quantity_received', 'item_id')
                            ->toArray();

                        $record->receive(auth()->user(), $receivedQuantities);

                        // ✅ Correct Filament notification
                        Notification::make()
                            ->title('Items Received Successfully')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        // ✅ Correct error notification
                        Notification::make()
                            ->title('Receive Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }
}
