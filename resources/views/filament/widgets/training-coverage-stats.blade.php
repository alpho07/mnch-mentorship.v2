<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
    <!-- Total Trainings -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                <x-heroicon-o-academic-cap class="w-6 h-6 text-blue-600 dark:text-blue-300" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Trainings</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->getTotalTrainings()) }}</p>
            </div>
        </div>
    </div>

    <!-- Total Participants -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                <x-heroicon-o-users class="w-6 h-6 text-green-600 dark:text-green-300" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Trained HCWs</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->getTotalParticipants()) }}</p>
            </div>
        </div>
    </div>

    <!-- Facilities Trained -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                <x-heroicon-o-building-office-2 class="w-6 h-6 text-purple-600 dark:text-purple-300" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Facilities Trained</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->getFacilitiesTrained()) }}</p>
            </div>
        </div>
    </div>

    <!-- Coverage Percentage -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-rose-100 dark:bg-rose-900">
                <x-heroicon-o-chart-pie class="w-6 h-6 text-rose-600 dark:text-rose-300" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Coverage %</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->getCoveragePercentage(), 1) }}%</p>
            </div>
        </div>
    </div>

    <!-- Last Training -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 dark:bg-orange-900">
                <x-heroicon-o-clock class="w-6 h-6 text-orange-600 dark:text-orange-300" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Last Training</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $this->getLastTrainingDate() ?? 'N/A' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Active Months -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900">
                <x-heroicon-o-calendar-days class="w-6 h-6 text-indigo-600 dark:text-indigo-300" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Months</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->getActiveMonths()) }}</p>
            </div>
        </div>
    </div>
</div>
