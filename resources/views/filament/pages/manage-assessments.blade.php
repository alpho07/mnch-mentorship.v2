{{-- resources/views/filament/resources/stock-request-resource/pages/fulfill-stock-request.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Alert for low stock items --}}
        @php
            $lowStockItems = collect($this->data['items'])->filter(function ($item) {
                return $item['available_stock'] < $item['quantity_pending'];
            });
        @endphp

        @if($lowStockItems->count() > 0)
            <x-filament::section
                icon="heroicon-o-exclamation-triangle"
                icon-color="warning"
            >
                <x-slot name="heading">
                    Stock Availability Warning
                </x-slot>

                <div class="space-y-2">
                    <p class="text-sm text-warning-700 dark:text-warning-300">
                        The following items have insufficient stock for full fulfillment:
                    </p>

                    <div class="space-y-1">
                        @foreach($lowStockItems as $item)
                            <div class="flex items-center justify-between p-2 bg-warning-50 dark:bg-warning-950 rounded">
                                <span class="text-sm font-medium">{{ $item['item_name'] }}</span>
                                <span class="text-sm">
                                    Need: {{ $item['quantity_pending'] }} | Available: {{ $item['available_stock'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">
                        {{ collect($this->data['items'])->count() }}
                    </div>
                    <div class="text-sm text-gray-500">Total Items</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-warning-600">
                        {{ collect($this->data['items'])->sum('quantity_pending') }}
                    </div>
                    <div class="text-sm text-gray-500">Pending Quantity</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600">
                        {{ collect($this->data['items'])->sum('available_stock') }}
                    </div>
                    <div class="text-sm text-gray-500">Available Stock</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-info-600">
                        {{ collect($this->data['items'])->sum('fulfillment_quantity') }}
                    </div>
                    <div class="text-sm text-gray-500">To Fulfill</div>
                </div>
            </x-filament::section>
        </div>

        {{-- Form --}}
        <form wire:submit="fulfill">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-4">
                <x-filament::button
                    color="gray"
                    tag="a"
                    href="{{ \App\Filament\Resources\StockRequestResource::getUrl('view', ['record' => $this->record]) }}"
                >
                    Cancel
                </x-filament::button>

                <x-filament::button
                    type="submit"
                    color="success"
                    icon="heroicon-o-check-circle"
                >
                    Fulfill Request
                </x-filament::button>
            </div>
        </form>

        {{-- Quick Actions --}}
        <x-filament::section>
            <x-slot name="heading">
                Quick Actions
            </x-slot>

            <div class="flex gap-4">
                <x-filament::button
                    color="info"
                    size="sm"
                    wire:click="$set('data.items.*.fulfillment_quantity', 0)"
                >
                    Clear All
                </x-filament::button>

                <x-filament::button
                    color="warning"
                    size="sm"
                    wire:click="fillMaxAvailable"
                >
                    Fill Max Available
                </x-filament::button>

                <x-filament::button
                    color="success"
                    size="sm"
                    wire:click="fillFullQuantities"
                >
                    Fill Full Quantities
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>

    @script
    <script>
        // Auto-calculate totals when quantities change
        function updateTotals() {
            const items = document.querySelectorAll('[data-fulfillment-quantity]');
            let totalToFulfill = 0;

            items.forEach(item => {
                const quantity = parseInt(item.value) || 0;
                totalToFulfill += quantity;
            });

            // Update the summary card
            const summaryCard = document.querySelector('[data-total-fulfill]');
            if (summaryCard) {
                summaryCard.textContent = totalToFulfill;
            }
        }

        // Add event listeners to quantity inputs
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInputs = document.querySelectorAll('[data-fulfillment-quantity]');
            quantityInputs.forEach(input => {
                input.addEventListener('input', updateTotals);
            });
        });
    </script>
    @endscript
</x-filament-panels::page>
