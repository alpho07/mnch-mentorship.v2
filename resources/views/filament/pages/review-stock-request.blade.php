{{-- resources/views/filament/resources/stock-request-notification-resource/pages/review-stock-request.blade.php --}}

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Page Header with Key Info --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            {{ $this->record->request_number }}
                        </span>
                    </h2>
                    <p class="text-sm text-gray-600 mt-2 flex items-center gap-4">
                        <span>Submitted {{ $this->record->created_at->diffForHumans() }}</span>
                        <span>•</span>
                        <span>{{ $this->record->total_items }} items</span>
                        <span>•</span>
                        <span>KES {{ number_format($this->record->total_requested_value, 2) }}</span>
                    </p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        {{ $this->record->priority === 'urgent' ? 'bg-red-100 text-red-800' :
                           ($this->record->priority === 'high' ? 'bg-yellow-100 text-yellow-800' :
                            'bg-blue-100 text-blue-800') }}">
                        {{ strtoupper($this->record->priority) }} PRIORITY
                    </span>
                    <div class="mt-2 text-sm text-gray-500">
                        {{ $this->record->days_pending }} days pending
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Action Buttons --}}
        @if($this->record->status === 'pending')
        <div class="flex flex-wrap gap-3">
            @if($this->record->canBeQuickApproved())
            <button
                wire:click="mountAction('quick_approve')"
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center gap-2 shadow-sm">
                <x-heroicon-o-check-circle class="w-5 h-5" />
                Quick Approve All Items
            </button>
            @else
            <button
                wire:click="mountAction('partial_approve')"
                class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center gap-2 shadow-sm">
                <x-heroicon-o-adjustments-horizontal class="w-5 h-5" />
                Partial Approval Required
            </button>
            @endif

            <button
                wire:click="mountAction('reject')"
                class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center gap-2 shadow-sm">
                <x-heroicon-o-x-circle class="w-5 h-5" />
                Reject Request
            </button>
        </div>
        @endif

        {{-- Stock Availability Overview Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <div class="text-sm font-medium text-gray-500">Total Items</div>
                <div class="text-3xl font-bold text-gray-900 mt-1">{{ $this->record->total_items }}</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <div class="text-sm font-medium text-gray-500">Can Fulfill</div>
                <div class="text-3xl font-bold text-green-600 mt-1">
                    {{ collect($this->record->stock_availability)->where('can_fulfill', true)->count() }}
                </div>
                <div class="text-xs text-gray-400 mt-1">Items with sufficient stock</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <div class="text-sm font-medium text-gray-500">Shortages</div>
                <div class="text-3xl font-bold text-red-600 mt-1">
                    {{ collect($this->record->stock_availability)->where('can_fulfill', false)->count() }}
                </div>
                <div class="text-xs text-gray-400 mt-1">Items with insufficient stock</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <div class="text-sm font-medium text-gray-500">Total Value</div>
                <div class="text-3xl font-bold text-gray-900 mt-1">
                    KES {{ number_format($this->record->total_requested_value, 0) }}
                </div>
            </div>
        </div>

        {{-- Main Info List --}}
        {{ $this->infolist }}

        {{-- Action Recommendations --}}
        @if($this->record->status === 'pending')
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-900 flex items-center gap-2 mb-4">
                <x-heroicon-o-light-bulb class="w-5 h-5" />
                Recommended Actions
            </h3>

            @php
                $canFulfillAll = collect($this->record->stock_availability)->every('can_fulfill');
                $canFulfillSome = collect($this->record->stock_availability)->some('can_fulfill');
                $canFulfillNone = collect($this->record->stock_availability)->every(fn($item) => !$item['can_fulfill']);
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @if($canFulfillAll)
                <div class="bg-green-100 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-600" />
                        <h4 class="font-medium text-green-900">Full Approval Recommended</h4>
                    </div>
                    <p class="text-sm text-green-800">All items are available in sufficient quantities. You can use Quick Approve for immediate processing.</p>
                </div>
                @elseif($canFulfillSome)
                <div class="bg-yellow-100 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600" />
                        <h4 class="font-medium text-yellow-900">Partial Approval Recommended</h4>
                    </div>
                    <p class="text-sm text-yellow-800">Some items have insufficient stock. Consider partial approval for available items and suggest alternatives.</p>
                </div>
                @else
                <div class="bg-red-100 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <x-heroicon-o-x-circle class="w-5 h-5 text-red-600" />
                        <h4 class="font-medium text-red-900">Consider Rejection</h4>
                    </div>
                    <p class="text-sm text-red-800">No items are available in sufficient quantities. Consider rejecting with suggestions for alternatives.</p>
                </div>
                @endif

                <div class="bg-blue-100 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <x-heroicon-o-clock class="w-5 h-5 text-blue-600" />
                        <h4 class="font-medium text-blue-900">Time Sensitivity</h4>
                    </div>
                    <p class="text-sm text-blue-800">
                        @if($this->record->priority === 'urgent')
                            This is an URGENT request requiring immediate attention.
                        @elseif($this->record->days_pending > 3)
                            This request is overdue ({{ $this->record->days_pending }} days). Process immediately.
                        @else
                            Standard processing timeframe. Please process within 24 hours.
                        @endif
                    </p>
                </div>

                <div class="bg-purple-100 border border-purple-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <x-heroicon-o-building-office class="w-5 h-5 text-purple-600" />
                        <h4 class="font-medium text-purple-900">Facility Impact</h4>
                    </div>
                    <p class="text-sm text-purple-800">
                        Request from {{ $this->record->requestingFacility->name }} for essential supplies.
                        Consider operational impact when making decisions.
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- Processing History (if any) --}}
        @if($this->record->status !== 'pending')
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <x-heroicon-o-clock class="w-5 h-5" />
                Processing History
            </h3>

            <div class="flow-root">
                <ul class="-mb-8">
                    @php
                        $history = [
                            [
                                'action' => 'Request Created',
                                'date' => $this->record->created_at,
                                'user' => $this->record->requestedBy->full_name,
                                'details' => "Request created with {$this->record->total_items} items",
                                'icon' => 'heroicon-o-plus-circle',
                                'color' => 'blue'
                            ]
                        ];

                        if ($this->record->approved_date) {
                            $history[] = [
                                'action' => $this->record->status === 'rejected' ? 'Request Rejected' : 'Request Approved',
                                'date' => $this->record->approved_date,
                                'user' => $this->record->approvedBy->full_name ?? 'System',
                                'details' => $this->record->status === 'rejected'
                                    ? "Rejected: {$this->record->rejection_reason}"
                                    : "Approved for KES " . number_format($this->record->total_approved_value, 2),
                                'icon' => $this->record->status === 'rejected' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle',
                                'color' => $this->record->status === 'rejected' ? 'red' : 'green'
                            ];
                        }

                        if ($this->record->dispatch_date) {
                            $history[] = [
                                'action' => 'Items Dispatched',
                                'date' => $this->record->dispatch_date,
                                'user' => $this->record->dispatchedBy->full_name ?? 'System',
                                'details' => "Items dispatched worth KES " . number_format($this->record->total_dispatched_value, 2),
                                'icon' => 'heroicon-o-truck',
                                'color' => 'purple'
                            ];
                        }

                        if ($this->record->received_date) {
                            $history[] = [
                                'action' => 'Items Received',
                                'date' => $this->record->received_date,
                                'user' => $this->record->receivedBy->full_name ?? 'System',
                                'details' => "Items received worth KES " . number_format($this->record->total_received_value, 2),
                                'icon' => 'heroicon-o-inbox-arrow-down',
                                'color' => 'green'
                            ];
                        }
                    @endphp

                    @foreach($history as $index => $event)
                    <li>
                        <div class="relative pb-8">
                            @if(!$loop->last)
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                            @endif
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full bg-{{ $event['color'] }}-500 flex items-center justify-center ring-8 ring-white">
                                        @php
                                            $iconClass = $event['icon'];
                                        @endphp
                                        <x-dynamic-component :component="$iconClass" class="w-4 h-4 text-white" />
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-sm text-gray-900 font-medium">{{ $event['action'] }}</p>
                                        <p class="text-sm text-gray-500">{{ $event['details'] }}</p>
                                        <p class="text-xs text-gray-400">by {{ $event['user'] }}</p>
                                    </div>
                                    <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                        <time datetime="{{ $event['date']->toISOString() }}">
                                            {{ $event['date']->format('M j, Y g:i A') }}
                                        </time>
                                        <div class="text-xs text-gray-400">
                                            {{ $event['date']->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- Additional Actions --}}
        @if($this->record->status === 'pending')
        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Additional Options</h3>
            <div class="flex flex-wrap gap-3">
                <a href="/admin/stock-levels?tableFilters[facility][value]={{ $this->record->central_store_id }}"
                   class="bg-white hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg border border-gray-300 transition-colors flex items-center gap-2">
                    <x-heroicon-o-cube class="w-4 h-4" />
                    View Central Store Stock
                </a>

                <a href="/admin/facilities/{{ $this->record->requesting_facility_id }}"
                   class="bg-white hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg border border-gray-300 transition-colors flex items-center gap-2">
                    <x-heroicon-o-building-office class="w-4 h-4" />
                    View Requesting Facility
                </a>

                <button type="button"
                        onclick="window.print()"
                        class="bg-white hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg border border-gray-300 transition-colors flex items-center gap-2">
                    <x-heroicon-o-printer class="w-4 h-4" />
                    Print Request
                </button>
            </div>
        </div>
        @endif
    </div>

    {{-- Action Modals --}}
    <x-filament-actions::modals />

    {{-- Print Styles --}}
    <style>
        @media print {
            .fi-topbar, .fi-sidebar, button, .no-print {
                display: none !important;
            }
            .bg-red-50, .bg-green-50 {
                background-color: #f9fafb !important;
            }
            body {
                font-size: 12px;
            }
            .text-3xl {
                font-size: 1.5rem !important;
            }
        }
    </style>

    {{-- Auto-refresh for real-time updates --}}
    <script>
        // Auto-refresh stock availability every 30 seconds
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                @this.call('$refresh');
            }
        }, 30000);

        // Refresh when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                @this.call('$refresh');
            }
        });
    </script>
</x-filament-panels::page>
