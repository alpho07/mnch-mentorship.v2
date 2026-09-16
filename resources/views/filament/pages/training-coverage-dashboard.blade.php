<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Form --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <form wire:submit.prevent="updateFilters">
                    {{ $this->form }}
                </form>
            </div>
        </div>

        {{-- Applied Filters Summary --}}
        @if(count($this->getAppliedFiltersSummary()) > 0)
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-center space-x-2 mb-3">
                    <x-heroicon-o-funnel class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    <h3 class="text-sm font-medium text-blue-900 dark:text-blue-100">Applied Filters</h3>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->getAppliedFiltersSummary() as $filterType => $filterValue)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                            <strong class="mr-1">{{ $filterType }}:</strong> {{ $filterValue }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Core Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @php $stats = $this->getCoreStats(); @endphp
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-academic-cap class="h-8 w-8 text-blue-500" />
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Trainings</dt>
                            <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_trainings']) }}</dd>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-users class="h-8 w-8 text-green-500" />
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Participants Trained</dt>
                            <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_participants']) }}</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($stats['unique_participants']) }} unique
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-building-office-2 class="h-8 w-8 text-yellow-500" />
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Facilities Covered</dt>
                            <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['facilities_covered']) }}</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($stats['counties_covered']) }} counties
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-document-text class="h-8 w-8 text-purple-500" />
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Programs Delivered</dt>
                            <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['programs_delivered']) }}</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $stats['avg_participants_per_training'] }} avg per training
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- TOT and Retention Metrics --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @php $retention = $this->getRetentionAnalysis(); @endphp
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">TOT Participants</dt>
                            <dd class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($stats['tot_participants']) }}</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $stats['tot_percentage'] }}% of all participants
                            </div>
                        </div>
                        <x-heroicon-o-star class="h-8 w-8 text-indigo-500" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Retention Rate</dt>
                            <dd class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $retention['retention_rate'] }}%</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($retention['repeat_participants']) }} repeat participants
                            </div>
                        </div>
                        <x-heroicon-o-arrow-trending-up class="h-8 w-8 text-green-500" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Unique Participants</dt>
                            <dd class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($retention['total_unique_participants']) }}</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Based on email addresses
                            </div>
                        </div>
                        <x-heroicon-o-identification class="h-8 w-8 text-blue-500" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts Section with Livewire Components --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Monthly Trends Chart --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Training Trends</h3>
                    @livewire('charts.monthly-trends-chart', ['filters' => $this->getFiltersArray()], key('monthly-trends-' . md5(json_encode($this->getFiltersArray()))))
                </div>
            </div>

            {{-- Cadre Distribution Chart --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Cadre Distribution</h3>
                    @livewire('charts.cadre-distribution-chart', ['filters' => $this->getFiltersArray()], key('cadre-dist-' . md5(json_encode($this->getFiltersArray()))))
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Geographic Distribution --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Geographic Distribution</h3>
                    <div class="h-80 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                        @php $counties = $this->getCountyDistribution(); @endphp
                        @if(count($counties) > 0)
                            <div class="space-y-1 p-2">
                                @foreach($counties as $county)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-900 dark:text-white truncate">{{ $county['county'] }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $county['facilities'] }} facilities
                                            </div>
                                        </div>
                                        <div class="text-right mx-4">
                                            <div class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $county['trainings'] }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $county['participants'] }} participants</div>
                                        </div>
                                        <div class="w-20 flex-shrink-0">
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                @php 
                                                    $maxIntensity = max(array_column($counties, 'intensity'));
                                                    $percentage = $maxIntensity > 0 ? min(100, ($county['intensity'] / $maxIntensity) * 100) : 0;
                                                @endphp
                                                <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full transition-all duration-300" 
                                                     style="width: {{ $percentage }}%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 text-center mt-1">
                                                {{ round($percentage) }}%
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex items-center justify-center h-full text-gray-500 dark:text-gray-400">
                                <div class="text-center">
                                    <x-heroicon-o-map class="h-12 w-12 mx-auto mb-2" />
                                    <p>No training data available</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Facility Type Distribution Chart --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Facility Type Distribution</h3>
                    @livewire('charts.facility-type-chart', ['filters' => $this->getFiltersArray()], key('facility-type-' . md5(json_encode($this->getFiltersArray()))))
                </div>
            </div>
        </div>

        {{-- Additional Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Approach Distribution Chart --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Training Approach Distribution</h3>
                    @livewire('charts.approach-distribution-chart', ['filters' => $this->getFiltersArray()], key('approach-dist-' . md5(json_encode($this->getFiltersArray()))))
                </div>
            </div>

            {{-- Performance Metrics Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Performance Insights</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Average Training Size</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Participants per training</div>
                            </div>
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {{ $stats['avg_participants_per_training'] }}
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Training Coverage</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Counties reached</div>
                            </div>
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ $stats['counties_covered'] }}
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Facility Reach</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Different facilities</div>
                            </div>
                            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {{ $stats['facilities_covered'] }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top Performers Table --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Top Performing Organizers</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Organizer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trainings</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Participants</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Facilities</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Performance</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @php $organizers = $this->getTopOrganizers(); @endphp
                            @if(count($organizers) > 0)
                                @foreach($organizers as $organizer)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $organizer['organizer'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ number_format($organizer['trainings']) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ number_format($organizer['participants'] ?? 0) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ number_format($organizer['facilities']) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div class="bg-green-600 h-2 rounded-full transition-all duration-300" 
                                                         style="width: {{ min(100, ($organizer['trainings'] / max(array_column($organizers, 'trainings'))) * 100) }}%"></div>
                                                </div>
                                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ round(($organizer['trainings'] / max(array_column($organizers, 'trainings'))) * 100) }}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No organizer data available
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Load Chart.js once for all charts --}}
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</x-filament-panels::page>