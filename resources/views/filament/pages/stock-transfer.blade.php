{{-- resources/views/filament/resources/stock-transfer/pages/track.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Transfer Details Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $this->record->transfer_number }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Transfer Number</p>
                </div>
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($this->record->status === 'pending') bg-yellow-100 text-yellow-800
                        @elseif($this->record->status === 'approved') bg-green-100 text-green-800
                        @elseif($this->record->status === 'in_transit') bg-blue-100 text-blue-800
                        @elseif($this->record->status === 'delivered') bg-green-100 text-green-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ ucfirst(str_replace('_', ' ', $this->record->status)) }}
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $this->record->fromFacility->name }} → {{ $this->record->toFacility->name }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Route</p>
                </div>
            </div>
        </div>

        {{-- Map Container --}}
        @if($this->record->fromFacility->coordinates && $this->record->toFacility->coordinates)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Transfer Route</h3>
            <div id="transfer-map" class="h-96 bg-gray-100 dark:bg-gray-700 rounded-lg"></div>
        </div>
        @endif

        {{-- Tracking Timeline --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Tracking Timeline</h3>
            
            <div class="flow-root">
                <ul class="-mb-8">
                    @foreach($this->record->trackingEvents->sortBy('created_at') as $event)
                    <li>
                        <div class="relative pb-8">
                            @if(!$loop->last)
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-600" aria-hidden="true"></span>
                            @endif
                            
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full 
                                        @if($event->event_type === 'created') bg-gray-400
                                        @elseif($event->event_type === 'approved') bg-green-500
                                        @elseif($event->event_type === 'dispatched') bg-blue-500
                                        @elseif($event->event_type === 'in_transit') bg-yellow-500
                                        @elseif($event->event_type === 'received') bg-green-600
                                        @else bg-gray-400
                                        @endif
                                        flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            @if($event->event_type === 'approved')
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            @elseif($event->event_type === 'dispatched')
                                                <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                                <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1V8a1 1 0 00-1-1h-3z"/>
                                            @else
                                                <circle cx="10" cy="10" r="3"/>
                                            @endif
                                        </svg>
                                    </span>
                                </div>
                                
                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-sm text-gray-900 dark:text-white font-medium">
                                            {{ ucfirst(str_replace('_', ' ', $event->event_type)) }}
                                        </p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ $event->description }}
                                        </p>
                                        @if($event->location)
                                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                            📍 {{ $event->location['lat'] }}, {{ $event->location['lng'] }}
                                        </p>
                                        @endif
                                    </div>
                                    
                                    <div class="text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        <time datetime="{{ $event->created_at->toISOString() }}">
                                            {{ $event->created_at->format('M j, Y g:i A') }}
                                        </time>
                                        @if($event->createdBy)
                                        <p class="text-xs">{{ $event->createdBy->full_name }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Transfer Items --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Transfer Items</h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Item</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Dispatched</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Received</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->record->items as $item)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ $item->inventoryItem->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ number_format($item->quantity) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ number_format($item->quantity_dispatched) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ number_format($item->quantity_received) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item->is_fully_received)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Complete
                                    </span>
                                @elseif($item->quantity_dispatched > 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        In Transit
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Pending
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Location Update Form (for in-transit transfers) --}}
        @if($this->record->status === 'in_transit')
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Update Location</h3>
            
            <form wire:submit.prevent="addLocationUpdate">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude</label>
                        <input type="number" step="any" wire:model="latitude" 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude</label>
                        <input type="number" step="any" wire:model="longitude"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <input type="text" wire:model="description" placeholder="Current location..."
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm">
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Update Location
                    </button>
                </div>
            </form>
        </div>
        @endif
    </div>

    @if($this->record->fromFacility->coordinates && $this->record->toFacility->coordinates)
    @push('scripts')
    <script>
        // Basic map implementation (you can use Leaflet, Google Maps, etc.)
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize your map here
            console.log('Map should be initialized here');
            console.log('From:', @json($this->record->fromFacility->coordinates));
            console.log('To:', @json($this->record->toFacility->coordinates));
        });
    </script>
    @endpush
    @endif
</x-filament-panels::page>