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
