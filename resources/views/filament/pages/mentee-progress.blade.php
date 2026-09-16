<x-filament-panels::page>
    {{-- Progress Summary Card --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                Progress Summary
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @php
                    $allProgress = $this->getTableRecords();
                    $total = $allProgress->count();
                    $completed = $allProgress->where('status', 'completed')->count();
                    $exempted = $allProgress->where('status', 'exempted')->count();
                    $inProgress = $allProgress->where('status', 'in_progress')->count();
                    $progressPercent = $total > 0 ? round((($completed + $exempted) / $total) * 100, 1) : 0;
                @endphp

                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <div class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Modules</div>
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">{{ $total }}</div>
                </div>

                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                    <div class="text-sm font-medium text-green-600 dark:text-green-400">Completed</div>
                    <div class="text-2xl font-bold text-green-900 dark:text-green-100 mt-1">{{ $completed }}</div>
                </div>

                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                    <div class="text-sm font-medium text-purple-600 dark:text-purple-400">Exempted</div>
                    <div class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-1">{{ $exempted }}</div>
                </div>

                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                    <div class="text-sm font-medium text-yellow-600 dark:text-yellow-400">Progress</div>
                    <div class="text-2xl font-bold text-yellow-900 dark:text-yellow-100 mt-1">{{ $progressPercent }}%</div>
                </div>
            </div>

            {{-- Progress Bar --}}
            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Overall Progress</span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $progressPercent }}%</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                    <div class="bg-primary-600 h-2.5 rounded-full transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Main Table --}}
    {{ $this->table }}
</x-filament-panels::page>