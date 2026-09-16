<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StockTransferResource;
use App\Models\StockTransfer;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;

class ReceiveStockTransfer extends Page implements Forms\Contracts\HasForms
{
    use InteractsWithFormActions;

    protected static string $resource = StockTransferResource::class;
    protected static string $view = 'filament.pages.receive-stock-transfer';
    protected static ?string $title = 'Receive Stock Transfer';

    public ?array $data = [];
    public StockTransfer $record;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if (!$this->record->can_be_received) {
            Notification::make()
                ->title('Cannot receive this transfer')
                ->body('This transfer is not in a state that allows receiving.')
                ->danger()
                ->send();

            redirect()->route('filament.admin.resources.stock-transfers.view', $this->record);
        }

        $this->fillForm();
    }

    public function fillForm(): void
    {
        $this->form->fill([
            'items' => $this->record->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'item_name' => $item->inventoryItem->name,
                    'sku' => $item->inventoryItem->sku,
                    'quantity_shipped' => $item->quantity,
                    'quantity_received' => $item->quantity_received,
                    'quantity_pending' => $item->quantity_pending,
                    'received_quantity' => $item->quantity, // Default to full quantity
                    'condition' => 'good', // Default condition
                    'has_damage' => false,
                ];
            })->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transfer Information')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Placeholder::make('transfer_number')
                                    ->label('Transfer Number')
                                    ->content($this->record->transfer_number),

                                Forms\Components\Placeholder::make('from_facility')
                                    ->label('From Facility')
                                    ->content($this->record->fromFacility?->name ?? 'Main Store'),

                                Forms\Components\Placeholder::make('to_facility')
                                    ->label('To Facility')
                                    ->content($this->record->toFacility->name),

                                Forms\Components\Placeholder::make('status')
                                    ->label('Current Status')
                                    ->content($this->record->status_name),
                            ]),
                    ]),

                Forms\Components\Section::make('Items to Receive')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\Grid::make(8)
                                    ->schema([
                                        Forms\Components\Placeholder::make('item_name')
                                            ->label('Item')
                                            ->columnSpan(2),

                                        Forms\Components\Placeholder::make('sku')
                                            ->label('SKU'),

                                        Forms\Components\Placeholder::make('quantity_shipped')
                                            ->label('Shipped')
                                            ->badge()
                                            ->color('info'),

                                        Forms\Components\TextInput::make('received_quantity')
                                            ->label('Received Qty')
                                            ->numeric()
                                            ->minValue(0)
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                $shipped = $get('quantity_shipped');
                                                if ($state != $shipped) {
                                                    $set('has_variance', true);
                                                } else {
                                                    $set('has_variance', false);
                                                }
                                            }),

                                        Forms\Components\Select::make('condition')
                                            ->options([
                                                'excellent' => 'Excellent',
                                                'good' => 'Good',
                                                'fair' => 'Fair',
                                                'poor' => 'Poor',
                                                'damaged' => 'Damaged',
                                            ])
                                            ->default('good')
                                            ->required(),

                                        Forms\Components\Checkbox::make('has_damage')
                                            ->label('Damaged')
                                            ->live(),

                                        Forms\Components\TextInput::make('damage_notes')
                                            ->label('Damage Notes')
                                            ->placeholder('Describe damage...')
                                            ->visible(fn (Forms\Get $get): bool => $get('has_damage'))
                                            ->required(fn (Forms\Get $get): bool => $get('has_damage')),
                                    ]),

                                Forms\Components\Hidden::make('id'),
                                Forms\Components\Hidden::make('inventory_item_id'),
                                Forms\Components\Hidden::make('quantity_shipped'),
                                Forms\Components\Hidden::make('quantity_received'),
                                Forms\Components\Hidden::make('has_variance'),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Receipt Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('received_date')
                                    ->label('Received Date & Time')
                                    ->default(now())
                                    ->required(),

                                Forms\Components\Select::make('received_by')
                                    ->label('Received By')
                                    ->relationship('receivedBy', 'full_name')
                                    ->default(auth()->id())
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('receipt_notes')
                            ->label('Receipt Notes')
                            ->placeholder('Any additional notes about this receipt...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('receive_all')
                ->label('Receive All Items')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Confirm Receipt')
                ->modalDescription('Are you sure you want to receive all items? This will update stock levels and cannot be undone.')
                ->action('receiveTransfer'),

            Actions\Action::make('partial_receive')
                ->label('Partial Receipt')
                ->color('warning')
                ->icon('heroicon-o-minus-circle')
                ->requiresConfirmation()
                ->modalHeading('Confirm Partial Receipt')
                ->modalDescription('This will partially receive the transfer. You can receive remaining items later.')
                ->action('receiveTransfer'),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function receiveTransfer(): void
    {
        $data = $this->form->getState();

        // Validate that at least one item has received quantity
        $hasItems = collect($data['items'])->some(fn ($item) => $item['received_quantity'] > 0);

        if (!$hasItems) {
            Notification::make()
                ->title('No items to receive')
                ->body('Please specify quantities to receive.')
                ->warning()
                ->send();
            return;
        }

        // Prepare receipt data
        $receipts = collect($data['items'])
            ->filter(fn ($item) => $item['received_quantity'] > 0)
            ->mapWithKeys(fn ($item) => [$item['id'] => $item['received_quantity']])
            ->toArray();

        try {
            // Receive the transfer
            $this->record->receive($receipts, auth()->user());

            // Update actual arrival date if provided
            if (!empty($data['received_date'])) {
                $this->record->update([
                    'actual_arrival_date' => $data['received_date'],
                ]);
            }

            // Update individual item conditions and notes
            foreach ($data['items'] as $itemData) {
                if ($itemData['received_quantity'] > 0) {
                    $item = $this->record->items()->find($itemData['id']);
                    if ($item) {
                        $conditionNotes = '';
                        if ($itemData['has_damage'] && !empty($itemData['damage_notes'])) {
                            $conditionNotes = "DAMAGED: " . $itemData['damage_notes'];
                        }

                        $item->update([
                            'condition_notes' => $conditionNotes,
                        ]);

                        // If item is damaged, create a damage transaction
                        if ($itemData['has_damage']) {
                            $item->inventoryItem->transactions()->create([
                                'location_id' => $this->record->to_facility_id,
                                'location_type' => 'facility',
                                'type' => 'damage',
                                'quantity' => $itemData['received_quantity'],
                                'reference_type' => StockTransfer::class,
                                'reference_id' => $this->record->id,
                                'user_id' => auth()->id(),
                                'remarks' => "Damage reported during transfer receipt: " . $itemData['damage_notes'],
                                'transaction_date' => now(),
                            ]);
                        }
                    }
                }
            }

            Notification::make()
                ->title('Transfer received successfully')
                ->body('Stock has been received and inventory updated.')
                ->success()
                ->send();

            // Redirect to the transfer view page
            redirect()->route('filament.admin.resources.stock-transfers.view', $this->record);

        } catch (\Exception $e) {
            Notification::make()
                ->title('Receipt failed')
                ->body('An error occurred while receiving the transfer: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function resolveRecord(int|string $key): Model
    {
        return StockTransfer::findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_transfer')
                ->label('View Transfer')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (): string => $this->getResource()::getUrl('view', ['record' => $this->record])),
        ];
    }
}
