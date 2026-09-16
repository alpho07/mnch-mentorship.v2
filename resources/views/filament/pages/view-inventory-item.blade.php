{{-- resources/views/filament/resources/inventory-item/pages/view-inventory-item.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Widgets --}}
        @if ($this->hasHeaderWidgets())
            <x-filament-widgets::widgets :widgets="$this->getHeaderWidgets()" :columns="$this->getHeaderWidgetsColumns()" :record="$record" />
        @endif

        {{-- Main Infolist --}}
        {{ $this->infolist }}

        {{-- Custom Stock Levels Grid --}}
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Stock Levels by Location
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($this->getViewData()['stockStatus']['locations'] as $location)
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-gray-900 dark:text-white">
                                    {{ $location['location_name'] }}
                                </h4>
                                <span
                                    class="text-xs px-2 py-1 rounded-full {{ $location['location_type'] === 'main_store' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                    {{ ucfirst(str_replace('_', ' ', $location['location_type'])) }}
                                </span>
                            </div>

                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Current Stock:</span>
                                    <span class="font-medium">{{ number_format($location['current_stock']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Reserved:</span>
                                    <span
                                        class="font-medium text-orange-600">{{ number_format($location['reserved_stock']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Available:</span>
                                    <span
                                        class="font-medium text-green-600">{{ number_format($location['available_stock']) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Serial Numbers Section (if serialized) --}}
        @if ($record->is_serialized)
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Serial Numbers ({{ $this->getViewData()['serialNumbers']->count() }} of
                            {{ $record->serialNumbers()->count() }})
                        </h3>
                        <a href="{{ route('filament.admin.resources.serial-numbers.index', ['tableFilters[inventory_item_id][value]' => $record->id]) }}"
                            class="text-sm text-blue-600 hover:text-blue-800">
                            View All →
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @forelse ($this->getViewData()['serialNumbers'] as $serial)
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-mono text-sm font-medium">{{ $serial->serial_number }}</span>
                                    <span
                                        class="text-xs px-2 py-1 rounded-full {{ $serial->status_badge_color === 'success' ? 'bg-green-100 text-green-800' : ($serial->status_badge_color === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                        {{ ucfirst($serial->status) }}
                                    </span>
                                </div>

                                <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                    <div>Location: {{ $serial->current_location_name }}</div>
                                    @if ($serial->assigned_to_user_id)
                                        <div>Assigned to: {{ $serial->assignedToUser->full_name }}</div>
                                    @endif
                                    <div>Last tracked: {{ $serial->last_tracked_at?->diffForHumans() ?? 'Never' }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center py-8 text-gray-500">
                                No serial numbers registered yet
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- Pending Requests --}}
        @if ($this->getViewData()['pendingRequests']->isNotEmpty())
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        Pending Requests ({{ $this->getViewData()['pendingRequests']->count() }})
                    </h3>

                    <div class="space-y-3">
                        @foreach ($this->getViewData()['pendingRequests'] as $request)
                            <div
                                class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                <div>
                                    <div class="font-medium">{{ $request->request_number }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $request->quantity_requested }} units requested by
                                        {{ $request->requestedBy->full_name }}
                                        from {{ $request->requesting_location_name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Required by: {{ $request->required_by_date->format('M d, Y') }}
                                        @if ($request->is_overdue)
                                            <span class="text-red-600 font-medium">(Overdue)</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span
                                        class="px-2 py-1 text-xs rounded-full {{ $request->priority_badge_color === 'danger' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ ucfirst($request->priority) }}
                                    </span>
                                    <a href="{{ route('filament.admin.resources.inventory-requests.view', $request) }}"
                                        class="text-blue-600 hover:text-blue-800 text-sm">View →</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Footer Widgets --}}
        @if ($this->hasFooterWidgets())
            <x-filament-widgets::widgets :widgets="$this->getFooterWidgets()" :columns="$this->getFooterWidgetsColumns()" :record="$record" />
        @endif
    </div>
</x-filament-panels::page>
