<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StockRequestResource;
use App\Models\StockRequest;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;

class FulfillStockRequest extends Page implements Forms\Contracts\HasForms
{
    use InteractsWithFormActions;

    protected static string $resource = StockRequestResource::class;
    protected static string $view = 'filament.pages.fulfill-stock-request';
    protected static ?string $title = 'Fulfill Stock Request';

    public ?array $data = [];
    public StockRequest $record;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if (!$this->record->can_be_fulfilled) {
            Notification::make()
                ->title('Cannot fulfill this request')
                ->body('This request is not in a state that allows fulfillment.')
                ->danger()
                ->send();

            redirect()->route('filament.admin.resources.stock-requests.view', $this->record);
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
                    'quantity_approved' => $item->quantity_approved,
                    'quantity_fulfilled' => $item->quantity_fulfilled,
                    'quantity_pending' => $item->quantity_pending,
                    'available_stock' => $item->inventoryItem->getStockAtLocation(
                        $this->record->supplying_facility_id ?: 1,
                        $this->record->supplying_facility_id ? 'facility' : 'main_store'
                    ),
                    'fulfillment_quantity' => min(
                        $item->quantity_pending,
                        $item->inventoryItem->getStockAtLocation(
                            $this->record->supplying_facility_id ?: 1,
                            $this->record->supplying_facility_id ? 'facility' : 'main_store'
                        )
                    ),
                ];
            })->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Information')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('request_number')
                                    ->label('Request Number')
                                    ->content($this->record->request_number),

                                Forms\Components\Placeholder::make('requesting_facility')
                                    ->label('Requesting Facility')
                                    ->content($this->record->requestingFacility->name),

                                Forms\Components\Placeholder::make('supplying_facility')
                                    ->label('Supplying From')
                                    ->content($this->record->supplyingFacility?->name ?? 'Main Store'),
                            ]),
                    ]),

                Forms\Components\Section::make('Items to Fulfill')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\Grid::make(6)
                                    ->schema([
                                        Forms\Components\Placeholder::make('item_name')
                                            ->label('Item')
                                            ->columnSpan(2),

                                        Forms\Components\Placeholder::make('sku')
                                            ->label('SKU'),

                                        Forms\Components\Placeholder::make('quantity_pending')
                                            ->label('Pending')
                                            ->badge()
                                            ->color('warning'),

                                        Forms\Components\Placeholder::make('available_stock')
                                            ->label('Available')
                                            ->badge()
                                            ->color(fn (?int $state, Forms\Get $get): string =>
                                                $state >= $get('quantity_pending') ? 'success' : 'danger'),

                                        Forms\Components\TextInput::make('fulfillment_quantity')
                                            ->label('Fulfill Qty')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(fn (Forms\Get $get): int =>
                                                min($get('quantity_pending'), $get('available_stock')))
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                $pending = $get('quantity_pending');
                                                $available = $get('available_stock');

                                                if ($state > $available) {
                                                    $set('fulfillment_quantity', $available);
                                                    Notification::make()
                                                        ->title('Insufficient stock')
                                                        ->body('Adjusted to available quantity.')
                                                        ->warning()
                                                        ->send();
                                                }
                                            }),
                                    ]),

                                Forms\Components\Hidden::make('id'),
                                Forms\Components\Hidden::make('inventory_item_id'),
                                Forms\Components\Hidden::make('quantity_approved'),
                                Forms\Components\Hidden::make('quantity_fulfilled'),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Fulfillment Notes')
                    ->schema([
                        Forms\Components\Textarea::make('fulfillment_notes')
                            ->label('Notes')
                            ->placeholder('Any additional notes about this fulfillment...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('fulfill')
                ->label('Fulfill Request')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Confirm Fulfillment')
                ->modalDescription('Are you sure you want to fulfill this request? This will update stock levels and cannot be undone.')
                ->action('fulfill'),

            Actions\Action::make('partial_fulfill')
                ->label('Partial Fulfillment')
                ->color('warning')
                ->icon('heroicon-o-minus-circle')
                ->requiresConfirmation()
                ->modalHeading('Confirm Partial Fulfillment')
                ->modalDescription('This will partially fulfill the request. You can fulfill remaining items later.')
                ->action('fulfill'),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function fulfill(): void
    {
        $data = $this->form->getState();

        // Validate that at least one item has fulfillment quantity
        $hasItems = collect($data['items'])->some(fn ($item) => $item['fulfillment_quantity'] > 0);

        if (!$hasItems) {
            Notification::make()
                ->title('No items to fulfill')
                ->body('Please specify quantities to fulfill.')
                ->warning()
                ->send();
            return;
        }

        // Prepare fulfillment data
        $fulfillments = collect($data['items'])
            ->filter(fn ($item) => $item['fulfillment_quantity'] > 0)
            ->pluck('fulfillment_quantity', 'id')
            ->toArray();

        try {
            // Fulfill the request
            $this->record->fulfill($fulfillments, auth()->user());

            Notification::make()
                ->title('Request fulfilled successfully')
                ->body('Stock has been transferred and inventory updated.')
                ->success()
                ->send();

            // Redirect to the request view page
            redirect()->route('filament.admin.resources.stock-requests.view', $this->record);

        } catch (\Exception $e) {
            Notification::make()
                ->title('Fulfillment failed')
                ->body('An error occurred while fulfilling the request: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function resolveRecord(int|string $key): Model
    {
        return StockRequest::findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_request')
                ->label('View Request')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (): string => $this->getResource()::getUrl('view', ['record' => $this->record])),
        ];
    }
}
