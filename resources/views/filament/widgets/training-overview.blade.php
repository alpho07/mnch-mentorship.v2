<div class="fi-wi-stats-overview grid gap-4 md:gap-6 lg:gap-8 md:grid-cols-2 lg:grid-cols-4">
    {{-- Total Mentees --}}
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="grid gap-y-2">
            <div class="flex items-center gap-x-2">
                <span class="fi-wi-stats-overview-stat-icon inline-flex items-center justify-center rounded-md bg-blue-50 p-2 text-blue-600 dark:bg-blue-500/10">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </span>
                <span class="fi-wi-stats-overview-stat-label text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Mentees
                </span>
            </div>
            <div class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                {{ $stats['total_mentees'] }}
            </div>
            @if($stats['total_mentees'] > 0 && $training->max_participants)
                <div class="fi-wi-stats-overview-stat-description text-xs text-gray-500 dark:text-gray-400">
                    {{ round(($stats['total_mentees'] / $training->max_participants) * 100, 1) }}% of capacity
                </div>
            @endif
        </div>
    </div>

    {{-- Completion Rate --}}
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="grid gap-y-2">
            <div class="flex items-center gap-x-2">
                <span class="fi-wi-stats-overview-stat-icon inline-flex items-center justify-center rounded-md bg-green-50 p-2 text-green-600 dark:bg-green-500/10">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </span>
                <span class="fi-wi-stats-overview-stat-label text-sm font-medium text-gray-500 dark:text-gray-400">
                    Completion Rate
                </span>
            </div>
            <div class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                {{ $stats['completion_rate'] }}%
            </div>
            <div class="fi-wi-stats-overview-stat-description text-xs text-gray-500 dark:text-gray-400">
                {{ $stats['completed'] ?? 0 }} of {{ $stats['total_mentees'] }} completed
            </div>
        </div>
    </div>

    {{-- Pass Rate --}}
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="grid gap-y-2">
            <div class="flex items-center gap-x-2">
                <span class="fi-wi-stats-overview-stat-icon inline-flex items-center justify-center rounded-md 
                    {{ $stats['pass_rate'] >= 80 ? 'bg-green-50 text-green-600' : ($stats['pass_rate'] >= 70 ? 'bg-yellow-50 text-yellow-600' : 'bg-red-50 text-red-600') }} p-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </span>
                <span class="fi-wi-stats-overview-stat-label text-sm font-medium text-gray-500 dark:text-gray-400">
                    Pass Rate
                </span>
            </div>
            <div class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                {{ $stats['pass_rate'] }}%
            </div>
            <div class="fi-wi-stats-overview-stat-description text-xs text-gray-500 dark:text-gray-400">
                Target: 80% or higher
            </div>
        </div>
    </div>

    {{-- Material Cost --}}
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="grid gap-y-2">
            <div class="flex items-center gap-x-2">
                <span class="fi-wi-stats-overview-stat-icon inline-flex items-center justify-center rounded-md bg-purple-50 p-2 text-purple-600 dark:bg-purple-500/10">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </span>
                <span class="fi-wi-stats-overview-stat-label text-sm font-medium text-gray-500 dark:text-gray-400">
                    Material Cost
                </span>
            </div>
            <div class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                ${{ number_format($stats['total_material_cost'], 0) }}
            </div>
            <div class="fi-wi-stats-overview-stat-description text-xs text-gray-500 dark:text-gray-400">
                ${{ number_format($stats['cost_per_mentee'], 0) }} per mentee
            </div>
        </div>
    </div>
</div>