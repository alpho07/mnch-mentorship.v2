<?php
// app/Filament/Resources/StockRequestNotificationResource/Pages/ReviewStockRequest.php

namespace App\Filament\Resources\StockRequestResource\Pages;

use App\Filament\Resources\StockRequestResource;
use App\Models\StockRequest;
use App\Models\StockLevel;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ReviewStockRequest extends Page
{
    protected static string $resource = StockRequestResource::class;
    protected static string $view = 'filament.pages.review-stock-request';

    public StockRequest $record;

    public function mount(int|string $record): void
    {
        $this->record = StockRequest::with([
            'requestingFacility.subcounty.county',
            'centralStore',
            'requestedBy',
            'items.inventoryItem.category'
        ])->findOrFail($record);

        // Check if user can approve this request
        if (!$this->record->canBeApprovedBy(auth()->user())) {
            abort(403, 'You do not have permission to review this request.');
        }
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Infolists\Components\Section::make('Request Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('request_number')
                                    ->label('Request Number')
                                    ->badge()
                                    ->color('primary')
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('priority')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'urgent' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        'low' => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                                Infolists\Components\TextEntry::make('request_date')
                                    ->label('Requested Date')
                                    ->date()
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ])
                    ->columnSpan('full'),

                Infolists\Components\Section::make('Facilities & Personnel')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('requestingFacility.name')
                                    ->label('Requesting Facility')
                                    ->weight('bold')
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('centralStore.name')
                                    ->label('Central Store')
                                    ->weight('bold')
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('requestingFacility.subcounty.county.name')
                                    ->label('County')
                                    ->badge()
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('requestedBy.full_name')
                                    ->label('Requested By')
                                    ->badge()
                                    ->color('warning'),
                            ]),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Stock Availability Analysis')
                    ->description('Real-time stock availability check for all requested items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('stock_availability')
                            ->label('')
                            ->getStateUsing(fn () => $this->record->stock_availability)
                            ->schema([
                                Infolists\Components\Grid::make(7)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('item_name')
                                            ->label('Item Name')
                                            ->weight('bold')
                                            ->limit(30),

                                        Infolists\Components\TextEntry::make('item_sku')
                                            ->label('SKU')
                                            ->badge()
                                            ->color('gray'),

                                        Infolists\Components\TextEntry::make('requested')
                                            ->label('Requested')
                                            ->badge()
                                            ->color('info')
                                            ->formatStateUsing(fn ($state) => number_format($state)),

                                        Infolists\Components\TextEntry::make('available')
                                            ->label('Available')
                                            ->badge()
                                            ->color(fn ($state, $record) =>
                                                $state >= $record['requested'] ? 'success' : 'danger'
                                            )
                                            ->formatStateUsing(fn ($state) => number_format($state)),

                                        Infolists\Components\TextEntry::make('can_fulfill')
                                            ->label('Status')
                                            ->formatStateUsing(fn ($state) => $state ? 'Can Fulfill' : 'Insufficient Stock')
                                            ->badge()
                                            ->color(fn ($state) => $state ? 'success' : 'danger')
                                            ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),

                                        Infolists\Components\TextEntry::make('shortage')
                                            ->label('Shortage')
                                            ->badge()
                                            ->color('warning')
                                            ->visible(fn ($state) => $state > 0)
                                            ->formatStateUsing(fn ($state) => number_format($state)),

                                        Infolists\Components\TextEntry::make('total_value')
                                            ->label('Value')
                                            ->money('KES')
                                            ->badge()
                                            ->color('primary'),
                                    ]),
                            ])
                            ->columns(1),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Request Summary')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_items')
                                    ->label('Total Items')
                                    ->badge()
                                    ->color('info')
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('can_fulfill_count')
                                    ->label('Can Fulfill')
                                    ->getStateUsing(fn () => collect($this->record->stock_availability)->where('can_fulfill', true)->count())
                                    ->badge()
                                    ->color('success')
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('shortage_count')
                                    ->label('Shortages')
                                    ->getStateUsing(fn () => collect($this->record->stock_availability)->where('can_fulfill', false)->count())
                                    ->badge()
                                    ->color('danger')
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('total_requested_value')
                                    ->label('Total Value')
                                    ->money('KES')
                                    ->badge()
                                    ->color('primary')
                                    ->size('lg'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Request Notes')
                            ->placeholder('No additional notes provided')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('days_pending')
                            ->label('Days Pending')
                            ->badge()
                            ->color(fn ($state) => match (true) {
                                $state <= 1 => 'success',
                                $state <= 3 => 'warning',
                                default => 'danger'
                            })
                            ->formatStateUsing(fn ($state) => "{$state} days"),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        if ($this->record->status !== 'pending') {
            return [
                Actions\Action::make('back')
                    ->label('Back to Notifications')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(route('filament.admin.resources.stock-request-notifications.index')),
            ];
        }

        return [
            Actions\Action::make('back')
                ->label('Back to Notifications')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(route('filament.admin.resources.stock-request-notifications.index')),

            Actions\Action::make('quick_approve')
                ->label('Quick Approve All')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->canBeQuickApproved())
                ->requiresConfirmation()
                ->modalHeading('Quick Approve All Items')
                ->modalDescription(fn () =>
                    "This will approve all items at requested quantities for {$this->record->requestingFacility->name}. " .
                    "Items will be automatically dispatched if stock is available. Are you sure you want to proceed?"
                )
                ->modalSubmitActionLabel('Yes, Approve All')
                ->action(function () {
                    try {
                        $this->record->quickApprove(auth()->user());

                        Notification::make()
                            ->title('Request Approved Successfully')
                            ->success()
                            ->body("Request {$this->record->request_number} has been approved and items are being dispatched.")
                            ->send();

                        return redirect()->route('filament.admin.resources.stock-request-notifications.index');
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Approval Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('partial_approve')
                ->label('Partial Approval')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('warning')
                ->form([
                    Forms\Components\Section::make('Item-by-Item Approval')
                        ->description('Review and approve quantities for each item based on stock availability')
                        ->schema([
                            Forms\Components\Repeater::make('item_approvals')
                                ->label('Item Approvals')
                                ->schema([
                                    Forms\Components\Grid::make(6)
                                        ->schema([
                                            Forms\Components\TextInput::make('item_name')
                                                ->label('Item')
                                                ->disabled()
                                                ->columnSpan(2),

                                            Forms\Components\TextInput::make('quantity_requested')
                                                ->label('Requested')
                                                ->disabled()
                                                ->numeric(),

                                            Forms\Components\TextInput::make('available_stock')
                                                ->label('Available')
                                                ->disabled()
                                                ->numeric(),

                                            Forms\Components\TextInput::make('quantity_approved')
                                                ->label('Approve Quantity')
                                                ->numeric()
                                                ->required()
                                                ->minValue(0)
                                                ->live()
                                                ->rules([
                                                    function () {
                                                        return function (string $attribute, $value, \Closure $fail) {
                                                            $itemData = $this->getItemDataFromAttribute($attribute);
                                                            if ($itemData && $value > $itemData['available_stock']) {
                                                                $fail("Approved quantity cannot exceed available stock ({$itemData['available_stock']})");
                                                            }
                                                        };
                                                    }
                                                ]),

                                            Forms\Components\TextInput::make('approval_value')
                                                ->label('Value')
                                                ->disabled()
                                                ->prefix('KES')
                                                ->formatStateUsing(function ($state, Forms\Get $get) {
                                                    $qty = $get('quantity_approved') ?? 0;
                                                    $unitPrice = $get('unit_price') ?? 0;
                                                    return number_format($qty * $unitPrice, 2);
                                                }),
                                        ]),

                                    Forms\Components\Hidden::make('item_id'),
                                    Forms\Components\Hidden::make('unit_price'),
                                ])
                                ->default(function () {
                                    return $this->record->items->map(function ($item) {
                                        $availableStock = StockLevel::where('facility_id', $this->record->central_store_id)
                                            ->where('inventory_item_id', $item->inventory_item_id)
                                            ->sum('available_stock');

                                        return [
                                            'item_id' => $item->id,
                                            'item_name' => $item->inventoryItem->name,
                                            'quantity_requested' => $item->quantity_requested,
                                            'available_stock' => $availableStock,
                                            'quantity_approved' => min($item->quantity_requested, $availableStock),
                                            'unit_price' => $item->unit_price,
                                        ];
                                    })->toArray();
                                })
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false),
                        ]),
                ])
                ->action(function (array $data) {
                    try {
                        DB::transaction(function () use ($data) {
                            $itemApprovals = collect($data['item_approvals'])
                                ->pluck('quantity_approved', 'item_id')
                                ->toArray();

                            $this->record->approve(auth()->user(), $itemApprovals);

                            // Try to auto-dispatch if possible
                            try {
                                $this->record->dispatch(auth()->user());
                            } catch (\Exception $e) {
                                // Approval succeeded, dispatch failed - that's ok
                                \Log::warning("Auto-dispatch failed for request {$this->record->request_number}: " . $e->getMessage());
                            }
                        });

                        Notification::make()
                            ->title('Request Approved Successfully')
                            ->success()
                            ->body("Request {$this->record->request_number} has been approved with custom quantities.")
                            ->send();

                        return redirect()->route('filament.admin.resources.stock-request-notifications.index');
                    } catch (\Exception $e) {
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
                ->form([
                    Forms\Components\Section::make('Rejection Details')
                        ->description('Please provide a clear and detailed reason for rejecting this request')
                        ->schema([
                            Forms\Components\Select::make('rejection_category')
                                ->label('Rejection Category')
                                ->options([
                                    'insufficient_stock' => 'Insufficient Stock Available',
                                    'invalid_request' => 'Invalid or Inappropriate Request',
                                    'duplicate_request' => 'Duplicate Request',
                                    'budget_constraints' => 'Budget or Financial Constraints',
                                    'policy_violation' => 'Policy Violation',
                                    'technical_issue' => 'Technical or System Issue',
                                    'other' => 'Other (specify below)',
                                ])
                                ->required()
                                ->live(),

                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Detailed Reason')
                                ->required()
                                ->rows(4)
                                ->placeholder('Please provide a clear explanation that will help the facility understand the rejection and take appropriate action...')
                                ->helperText('This message will be sent to the requesting facility, so please be clear and constructive.'),

                            Forms\Components\Textarea::make('suggested_alternatives')
                                ->label('Suggested Alternatives (Optional)')
                                ->rows(3)
                                ->placeholder('Suggest alternative items, reduced quantities, or other solutions...')
                                ->helperText('Help the facility by suggesting alternatives or next steps.'),
                        ]),
                ])
                ->action(function (array $data) {
                    try {
                        $reason = $data['rejection_reason'];

                        if (!empty($data['suggested_alternatives'])) {
                            $reason .= "\n\nSuggested Alternatives:\n" . $data['suggested_alternatives'];
                        }

                        $this->record->reject(auth()->user(), $reason);

                        Notification::make()
                            ->title('Request Rejected')
                            ->success()
                            ->body("Request {$this->record->request_number} has been rejected. The facility has been notified.")
                            ->send();

                        return redirect()->route('filament.admin.resources.stock-request-notifications.index');
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Rejection Failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Helper method to extract item data from form attribute path
     */
    private function getItemDataFromAttribute(string $attribute): ?array
    {
        // Extract index from attribute like "data.item_approvals.0.quantity_approved"
        if (preg_match('/item_approvals\.(\d+)\./', $attribute, $matches)) {
            $index = (int) $matches[1];
            $availability = $this->record->stock_availability;

            if (isset($availability[$index])) {
                return $availability[$index];
            }
        }

        return null;
    }

    public function getTitle(): string
    {
        return "Review Stock Request: {$this->record->request_number}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.resources.stock-request-notifications.index') => 'Stock Request Notifications',
            '' => "Review {$this->record->request_number}",
        ];
    }
}
