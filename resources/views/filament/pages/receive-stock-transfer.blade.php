{{-- resources/views/filament/resources/stock-transfer-resource/pages/receive-stock-transfer.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Transfer Status Banner --}}
        <div class="bg-info-50 dark:bg-info-950 border border-info-200 dark:border-info-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <x-heroicon-o-truck class="w-6 h-6 text-info-600" />
                <div>
                    <h3 class="text-lg font-semibold text-info-900 dark:text-info-100">
                        Transfer {{ $this->record->transfer_number }}
                    </h3>
                    <p class="text-sm text-info-700 dark:text-info-300">
                        From: {{ $this->record->fromFacility?->name ?? 'Main Store' }} →
                        To: {{ $this->record->toFacility->name }}
                    </p>
                    @if ($this->record->expected_arrival_date)
                        <p class="text-sm text-info-700 dark:text-info-300">
                            Expected: {{ $this->record->expected_arrival_date->format('M d, Y g:i A') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Alert for variances --}}
        @php
            $hasVariances = collect($this->data['items'])->some(function ($item) {
                return isset($item['received_quantity']) && $item['received_quantity'] != $item['quantity_shipped'];
            });
        @endphp

        @if ($hasVariances)
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
                <x-slot name="heading">
                    Quantity Variances Detected
                </x-slot>

                <div class="space-y-2">
                    <p class="text-sm text-warning-700 dark:text-warning-300">
                        Some items have different received quantities than shipped quantities. Please verify these
                        variances:
                    </p>

                    <div class="space-y-1">
                        @foreach (collect($this->data['items'])->filter(fn($item) => isset($item['received_quantity']) && $item['received_quantity'] != $item['quantity_shipped']) as $item)
                            <div
                                class="flex items-center justify-between p-2 bg-warning-50 dark:bg-warning-950 rounded">
                                <span class="text-sm font-medium">{{ $item['item_name'] }}</span>
                                <span class="text-sm">
                                    Shipped: {{ $item['quantity_shipped'] }} | Received:
                                    {{ $item['received_quantity'] ?? 0 }}
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
                    <div class="text-2xl font-bold text-info-600">
                        {{ collect($this->data['items'])->sum('quantity_shipped') }}
                    </div>
                    <div class="text-sm text-gray-500">Total Shipped</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600" data-total-received>
                        {{ collect($this->data['items'])->sum('received_quantity') }}
                    </div>
                    <div class="text-sm text-gray-500">To Receive</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-warning-600">
                        {{ collect($this->data['items'])->filter(fn($item) => isset($item['has_damage']) && $item['has_damage'])->count() }}
                    </div>
                    <div class="text-sm text-gray-500">Damaged Items</div>
                </div>
            </x-filament::section>
        </div>

        {{-- Form --}}
        <form wire:submit="receiveTransfer">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-4">
                <x-filament::button color="gray" tag="a"
                    href="{{ \App\Filament\Resources\StockTransferResource::getUrl('view', ['record' => $this->record]) }}">
                    Cancel
                </x-filament::button>

                <x-filament::button type="submit" color="success" icon="heroicon-o-check-circle">
                    Receive Transfer
                </x-filament::button>
            </div>
        </form>

        {{-- Quick Actions --}}
        <x-filament::section>
            <x-slot name="heading">
                Quick Actions
            </x-slot>

            <div class="flex gap-4">
                <x-filament::button color="info" size="sm" wire:click="fillShippedQuantities">
                    Receive All as Shipped
                </x-filament::button>

                <x-filament::button color="warning" size="sm"
                    wire:click="$set('data.items.*.received_quantity', 0)">
                    Clear All Quantities
                </x-filament::button>

                <x-filament::button color="danger" size="sm" wire:click="markAllDamaged">
                    Mark All as Damaged
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- Delivery Performance --}}
        @if ($this->record->expected_arrival_date)
            <x-filament::section>
                <x-slot name="heading">
                    Delivery Performance
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="text-sm text-gray-500">Expected Arrival</div>
                        <div class="font-semibold">
                            {{ $this->record->expected_arrival_date->format('M d, Y g:i A') }}
                        </div>
                    </div>

                    <div class="text-center">
                        <div class="text-sm text-gray-500">Actual Arrival</div>
                        <div class="font-semibold">
                            {{ now()->format('M d, Y g:i A') }}
                        </div>
                    </div>

                    <div class="text-center">
                        <div class="text-sm text-gray-500">Performance</div>
                        <div class="font-semibold">
                            @php
                                $isOnTime = now()->lte($this->record->expected_arrival_date);
                                $daysDiff = abs(now()->diffInDays($this->record->expected_arrival_date));
                            @endphp

                            @if ($isOnTime)
                                <span class="text-success-600">On Time</span>
                            @else
                                <span class="text-danger-600">{{ $daysDiff }} day(s) late</span>
                            @endif
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>

    @script
        <script>
            // Auto-calculate totals when quantities change
            function updateTotals() {
                const items = document.querySelectorAll('[data-received-quantity]');
                let totalToReceive = 0;
                let damagedCount = 0;

                items.forEach(item => {
                    const quantity = parseInt(item.value) || 0;
                    totalToReceive += quantity;

                    // Check if item is marked as damaged
                    const damageCheckbox = item.closest('.grid').querySelector('[data-has-damage]');
                    if (damageCheckbox && damageCheckbox.checked) {
                        damagedCount++;
                    }
                });

                // Update the summary cards
                const totalReceivedCard = document.querySelector('[data-total-received]');
                if (totalReceivedCard) {
                    totalReceivedCard.textContent = totalToReceive;
                }
            }

            // Add event listeners to quantity inputs and damage checkboxes
            document.addEventListener('DOMContentLoaded', function() {
                const quantityInputs = document.querySelectorAll('[data-received-quantity]');
                const damageCheckboxes = document.querySelectorAll('[data-has-damage]');

                quantityInputs.forEach(input => {
                    input.addEventListener('input', updateTotals);
                });

                damageCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', updateTotals);
                });
            });

            // Quick action functions
            window.fillShippedQuantities = function() {
                const items = document.querySelectorAll('[data-received-quantity]');
                items.forEach(item => {
                    const shippedQuantity = item.getAttribute('data-shipped-quantity');
                    if (shippedQuantity) {
                        item.value = shippedQuantity;
                    }
                });
                updateTotals();
            };

            window.markAllDamaged = function() {
                const damageCheckboxes = document.querySelectorAll('[data-has-damage]');
                damageCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event('change'));
                });
            };
        </script>
    @endscript
</x-filament-panels::page>
