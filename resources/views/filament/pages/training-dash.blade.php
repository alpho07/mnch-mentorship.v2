<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Form --}}
        <div
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <form wire:submit.prevent="updatedFilters">
                    {{ $this->form }}
                </form>
            </div>
        </div>

        {{-- Applied Filters Summary --}}
        @if (count($this->getAppliedFiltersSummary()) > 0)
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-center space-x-2 mb-3">
                    <x-heroicon-o-funnel class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    <h3 class="text-sm font-medium text-blue-900 dark:text-blue-100">Applied Filters</h3>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->getAppliedFiltersSummary() as $filterType => $filterValue)
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                            <strong class="mr-1">{{ $filterType }}:</strong> {{ $filterValue }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Core Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @php $stats = $this->getCoreStats(); @endphp

            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-academic-cap class="h-8 w-8 text-blue-500" />
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Trainings
                            </dt>
                            <dd class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($stats['total_trainings']) }}</dd>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-users class="h-8 w-8 text-green-500" />
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Participants
                                Trained</dt>
                            <dd class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($stats['total_participants']) }}</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($stats['unique_participants']) }} unique
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-building-office-2 class="h-8 w-8 text-yellow-500" />
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Facilities Covered
                            </dt>
                            <dd class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($stats['facilities_covered']) }}</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($stats['counties_covered']) }} counties
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-document-text class="h-8 w-8 text-purple-500" />
                        </div>
                        <div class="ml-4">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Programs Delivered
                            </dt>
                            <dd class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($stats['programs_delivered']) }}</dd>
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

            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">TOT Participants</dt>
                            <dd class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                {{ number_format($stats['tot_participants']) }}</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $stats['tot_percentage'] }}% of all participants
                            </div>
                        </div>
                        <x-heroicon-o-star class="h-8 w-8 text-indigo-500" />
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Retention Rate</dt>
                            <dd class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ $retention['retention_rate'] }}%</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($retention['repeat_participants']) }} repeat participants
                            </div>
                        </div>
                        <x-heroicon-o-arrow-trending-up class="h-8 w-8 text-green-500" />
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Unique Participants</dt>
                            <dd class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {{ number_format($retention['total_unique_participants']) }}</dd>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Based on email addresses
                            </div>
                        </div>
                        <x-heroicon-o-identification class="h-8 w-8 text-blue-500" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Monthly Trends Chart --}}
            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Training Trends</h3>
                    <div id="monthlyTrendsChart" class="h-80"></div>
                </div>
            </div>

            {{-- Cadre Distribution Chart --}}
            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Cadre Distribution</h3>
                    <div id="cadreDistributionChart" class="h-80"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- County Heatmap --}}
            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Geographic Distribution</h3>
                    <div id="countyHeatmap" class="h-80 overflow-auto">
                        @php $counties = $this->getCountyDistribution(); @endphp
                        <div class="space-y-2">
                            @foreach ($counties as $county)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $county['county'] }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $county['facilities'] }} facilities
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                            {{ $county['trainings'] }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $county['participants'] }} participants</div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="w-20 bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full"
                                                style="width: {{ min(100, ($county['intensity'] / max(array_column($counties, 'intensity'))) * 100) }}%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Facility Type Distribution --}}
            <div
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Facility Type Distribution</h3>
                    <div id="facilityTypeChart" class="h-80"></div>
                </div>
            </div>
        </div>

        {{-- Top Performers Table --}}
        <div
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Top Performing Organizers</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Organizer</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Trainings</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Facilities</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Performance</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @php $organizers = $this->getTopOrganizers(); @endphp
                            @foreach ($organizers as $organizer)
                                <tr>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $organizer['organizer'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $organizer['trainings'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $organizer['facilities'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                                                <div class="bg-green-600 h-2 rounded-full"
                                                    style="width: {{ min(100, ($organizer['trainings'] / max(array_column($organizers, 'trainings'))) * 100) }}%">
                                                </div>
                                            </div>
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ round(($organizer['trainings'] / max(array_column($organizers, 'trainings'))) * 100) }}%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Charts --}}
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Monthly Trends Chart
                const monthlyData = @json($this->getMonthlyTrends());
                const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
                new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyData.map(item => item.period),
                        datasets: [{
                            label: 'Trainings',
                            data: monthlyData.map(item => item.trainings),
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Participants',
                            data: monthlyData.map(item => item.participants),
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false,
                                },
                            }
                        }
                    }
                });

                // Cadre Distribution Chart
                const cadreData = @json($this->getCadreDistribution());
                const cadreCtx = document.getElementById('cadreDistributionChart').getContext('2d');
                new Chart(cadreCtx, {
                    type: 'doughnut',
                    data: {
                        labels: cadreData.map(item => item.cadre),
                        datasets: [{
                            data: cadreData.map(item => item.count),
                            backgroundColor: [
                                '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                                '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6B7280'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });

                // Facility Type Chart
                const facilityTypeData = @json($this->getFacilityTypeDistribution());
                const facilityTypeCtx = document.getElementById('facilityTypeChart').getContext('2d');
                new Chart(facilityTypeCtx, {
                    type: 'bar',
                    data: {
                        labels: facilityTypeData.map(item => item.facility_type),
                        datasets: [{
                            label: 'Trainings',
                            data: facilityTypeData.map(item => item.count),
                            backgroundColor: 'rgba(59, 130, 246, 0.8)',
                            borderColor: 'rgb(59, 130, 246)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Listen for filter changes
                Livewire.on('refreshDashboard', () => {
                    location.reload();
                });
            });
        </script>
    @endpush
</x-filament-panels::page>
