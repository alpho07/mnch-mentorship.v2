<div class="fi-wi-stats-overview grid gap-4 md:gap-6 lg:gap-8 md:grid-cols-2 lg:grid-cols-5">
    {{-- Total Mentees --}}
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <div class="grid gap-y-2">
            <div class="flex items-center gap-x-2">
                <span class="fi-wi-stats-overview-stat-icon inline-flex items-center justify-center rounded-md bg-blue-50 p-2 text-blue-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </span>
                <span class="fi-wi-stats-overview-stat-label text-sm font-medium text-gray-500">
                    Total Mentees
                </span>
            </div>
            <div class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950">
                {{ $stats['total'] }}
            </div>
        </div>
    </div>

    {{-- Active Mentees --}}
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <div class="grid gap-y-2">
            <div class="flex items-center gap-x-2">
                <span class="fi-wi-stats-overview-stat-icon inline-flex items-center justify-center rounded-md bg-green-50 p-2 text-green-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </span>
                <span class="fi-wi-stats-overview-stat-label text-sm font-medium text-gray-500">
                    Active
                </span>
            </div>
            <div class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950">
                {{ $stats['active'] }}
            </div>
            <div class="fi-wi-stats-overview-stat-description text-xs text-gray-500">
                {{ $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100, 1) : 0 }}% of total
            </div>
        </div>
    </div>

    {{-- High Performers --}}
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <div class="grid gap-y-2">
            <div class="flex items-center gap-x-2">
                <span class="fi-wi-stats-overview-stat-icon inline-flex items-center justify-center rounded-md bg-purple-50 p-2 text-purple-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </span>
                <span class="fi-wi-stats-overview-stat-label text-sm font-medium text-gray-500">
                    High Performers
                </span>
            </div>
            <div class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950">
                {{ $stats['high_performers'] }}
            </div>
            <div class="fi-wi-stats-overview-stat-description text-xs text-gray-500">
                85%+ average scores
            </div>
        </div>
    </div>

    {{-- At Risk --}}
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <div class="grid gap-y-2">
            <div class="flex items-center gap-x-2">
                <span class="fi-wi-stats-overview-stat-icon inline-flex items-center justify-center rounded-md bg-red-50 p-2 text-red-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833-.192 2.5 1.732 2.5z"></path>
                    </svg>
                </span>
                <span class="fi-wi-stats-overview-stat-label text-sm font-medium text-gray-500">
                    At Risk
                </span>
            </div>
            <div class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950">
                {{ $stats['at_risk'] }}
            </div>
            <div class="fi-wi-stats-overview-stat-description text-xs text-gray-500">
                Need intervention
            </div>
        </div>
    </div>

    {{-- Retention Rate --}}
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <div class="grid gap-y-2">
            <div class="flex items-center gap-x-2">
                <span class="fi-wi-stats-overview-stat-icon inline-flex items-center justify-center rounded-md bg-indigo-50 p-2 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </span>
                <span class="fi-wi-stats-overview-stat-label text-sm font-medium text-gray-500">
                    Retention Rate
                </span>
            </div>
            <div class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950">
                {{ $stats['retention_rate'] }}%
            </div>
            <div class="fi-wi-stats-overview-stat-description text-xs text-gray-500">
                Last 30 days
            </div>
        </div>
    </div>
</div>