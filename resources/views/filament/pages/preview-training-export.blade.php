<x-filament-panels::page>
    <div class="space-y-6">
    

      

        {{-- Tabs for Multiple Sheets --}}
        @if(count($previewData['sheets']) > 1)
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex space-x-4" aria-label="Tabs">
                    @foreach($previewData['sheets'] as $index => $sheet)
                        <button
                            wire:click="changeTab({{ $loop->index }})"
                            class="px-4 py-2 text-sm font-medium rounded-t-lg transition {{ $loop->index === $activeTab ? 'bg-primary-600 text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}"
                        >
                            {{ $sheet['name'] }}
                        </button>
                    @endforeach
                </nav>
            </div>
        @endif

        {{-- Data Table --}}
        @if($selectedTraining && isset($previewData['sheets'][$selectedTraining]))
            @php
                $sheet = $previewData['sheets'][$selectedTraining];
                $filteredRows = $this->getFilteredRows();
                $paginatedRows = $this->getPaginatedRows();
                $totalPages = $this->getTotalPages();
            @endphp

            <x-filament::section>
                {{-- Sheet Header --}}
                <div class="mb-4">
                    <h2 class="text-xl font-bold">{{ $sheet['name'] }}</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $sheet['type'] }}</p>
                    
                    @if(!empty($sheet['info']))
                        <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach($sheet['info'] as $label => $value)
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $label }}:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Search and Filter Bar --}}
                <div class="flex flex-col md:flex-row gap-4 mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex-1">
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search in all columns..."
                            >
                                <x-slot name="prefix">
                                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400" />
                                </x-slot>
                            </x-filament::input>
                        </x-filament::input.wrapper>
                    </div>
                    
                    <div class="flex gap-2">
                        <x-filament::button
                            color="gray"
                            size="sm"
                            icon="heroicon-o-arrow-path"
                            wire:click="resetFilters"
                        >
                            Reset
                        </x-filament::button>
                        
                        <x-filament::button
                            color="success"
                            size="sm"
                            icon="heroicon-o-arrow-down-tray"
                            wire:click="exportCurrentView"
                        >
                            Export Filtered ({{ count($filteredRows) }})
                        </x-filament::button>
                    </div>
                </div>

                {{-- Results Info --}}
                <div class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                    Showing {{ count($paginatedRows) }} of {{ count($filteredRows) }} records
                    @if($search)
                        <span class="text-primary-600 dark:text-primary-400">(filtered from {{ count($sheet['rows']) }} total)</span>
                    @endif
                </div>

                {{-- Scrollable Table --}}
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="w-full text-sm border-collapse min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                @foreach($sheet['headers'] as $index => $header)
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap border-r border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                        wire:click="sortBy({{ $index }})">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $header }}</span>
                                            @if($sortColumn === $index)
                                                @if($sortDirection === 'asc')
                                                    <x-heroicon-o-chevron-up class="w-4 h-4" />
                                                @else
                                                    <x-heroicon-o-chevron-down class="w-4 h-4" />
                                                @endif
                                            @else
                                                <x-heroicon-o-chevron-up-down class="w-4 h-4 text-gray-400" />
                                            @endif
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($paginatedRows as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                    @foreach($row as $cell)
                                        <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700 whitespace-nowrap">
                                            @if(in_array(strtoupper($cell), ['PASS', 'PASSED']))
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    ✓ {{ $cell }}
                                                </span>
                                            @elseif(in_array(strtoupper($cell), ['FAIL', 'FAILED']))
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    ✗ {{ $cell }}
                                                </span>
                                            @elseif(in_array(strtoupper($cell), ['NOT ASSESSED', 'PENDING', 'ASSESSMENT INCOMPLETE']))
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                                    ⏳ {{ $cell }}
                                                </span>
                                            @elseif(in_array(strtoupper($cell), ['COMPLETED']))
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    ✓ {{ $cell }}
                                                </span>
                                            @elseif(in_array(strtoupper($cell), ['DROPPED']))
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                    {{ $cell }}
                                                </span>
                                            @else
                                                {{ $cell }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($sheet['headers']) }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <x-heroicon-o-magnifying-glass class="w-12 h-12 text-gray-400" />
                                            <p class="font-medium">No results found</p>
                                            <p class="text-sm">Try adjusting your search criteria</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($totalPages > 1)
                    <div class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Page {{ $currentPage }} of {{ $totalPages }}
                        </div>
                        
                        <div class="flex gap-2">
                            <x-filament::button
                                color="gray"
                                size="sm"
                                icon="heroicon-o-chevron-left"
                                wire:click="previousPage"
                                :disabled="$currentPage <= 1"
                            >
                                Previous
                            </x-filament::button>
                            
                            <x-filament::button
                                color="gray"
                                size="sm"
                                icon="heroicon-o-chevron-right"
                                icon-position="after"
                                wire:click="nextPage"
                                :disabled="$currentPage >= $totalPages"
                            >
                                Next
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>

    {{-- Print Styles --}}
    <style>
        @media print {
            .fi-topbar, .fi-sidebar, button, .fi-btn {
                display: none !important;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
</x-filament-panels::page>