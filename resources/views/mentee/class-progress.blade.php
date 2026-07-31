<!DOCTYPE html>
<html lang="en" class="h-full">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $class->name }} — My Progress</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                        colors: {
                            brand: {
                                50:  '#eef2ff',
                                100: '#e0e7ff',
                                200: '#c7d2fe',
                                400: '#818cf8',
                                500: '#6366f1',
                                600: '#4f46e5',
                                700: '#4338ca',
                            }
                        }
                    }
                }
            }
        </script>
        <style>
            .ring-fill {
                stroke: #6366f1;
                stroke-linecap: round;
                transition: stroke-dashoffset 1s cubic-bezier(.4,0,.2,1);
            }
            .dark .ring-fill { stroke: #818cf8; }
            .ring-track { stroke: #e2e8f0; }
            .dark .ring-track { stroke: #1e293b; }

            @keyframes slideUp {
                from { opacity: 0; transform: translateY(12px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .module-card { animation: slideUp .35s ease both; }
            .module-card:nth-child(1)  { animation-delay: .04s; }
            .module-card:nth-child(2)  { animation-delay: .08s; }
            .module-card:nth-child(3)  { animation-delay: .12s; }
            .module-card:nth-child(4)  { animation-delay: .16s; }
            .module-card:nth-child(5)  { animation-delay: .20s; }
            .module-card:nth-child(n+6){ animation-delay: .24s; }

            @keyframes pulse-dot {
                0%, 100% { opacity: 1; }
                50%       { opacity: .4; }
            }
            .pulse-dot { animation: pulse-dot 1.6s ease-in-out infinite; }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-6px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .cert-banner { animation: fadeIn .5s ease both; }
        </style>
    </head>
    <body class="min-h-full bg-slate-50 dark:bg-slate-950 font-sans antialiased text-slate-900 dark:text-slate-100">

    <div class="min-h-screen">

        {{-- ── Sticky top bar ────────────────────────────────────────────────── --}}
        <header class="sticky top-0 z-20 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-4">
                <a href="{{ url('admin/mentee-dashboard') }}"
                   class="flex items-center gap-2 text-sm font-semibold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Dashboard
                </a>
                <span class="text-xs text-slate-400 dark:text-slate-500 font-medium truncate">My Class Progress</span>
            </div>
        </header>

        <main class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-4">

            {{-- ── Flash messages ─────────────────────────────────────────────── --}}
            @if(session('success'))
                <div class="flex items-start gap-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('info'))
                <div class="flex items-start gap-3 rounded-xl bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800 px-4 py-3">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ session('info') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-start gap-3 rounded-xl bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 px-4 py-3">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
                </div>
            @endif

            {{-- ── Certification banner (EmONC) ───────────────────────────────── --}}
            @if($isEmonc && $participant->isCertified())
                <div class="cert-banner relative overflow-hidden rounded-2xl bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-500 p-5 sm:p-6 text-amber-950 shadow-lg shadow-amber-200/50 dark:shadow-amber-900/30">
                    <div class="absolute inset-0 opacity-10" style="background-image: url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23000000' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\");"></div>
                    <div class="relative flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-white/30 flex items-center justify-center shrink-0">
                            <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 1L9.33 6.4 3 7.27l4.5 4.37-1.06 6.18L12 15l5.56 2.82-1.06-6.18L21 7.27l-6.33-.87z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs font-bold uppercase tracking-widest opacity-70 mb-0.5">Congratulations!</p>
                            <h2 class="text-lg font-bold leading-tight">You're Certified in {{ $training->title }}</h2>
                            <p class="text-sm opacity-80 mt-0.5">Certified on {{ \Carbon\Carbon::parse($participant->head_drmh_approved_at)->format('d M Y') }}</p>
                        </div>
                        <a href="{{ route('reports.class.certificate', [$class->id, $participant->id]) }}"
                           class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-white/30 hover:bg-white/50 font-bold text-sm transition-colors shrink-0 backdrop-blur-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Download Certificate
                        </a>
                    </div>
                </div>
            @elseif($isEmonc && $participant->mentor_approved_at)
                <div class="cert-banner rounded-2xl bg-violet-50 dark:bg-violet-950/40 border border-violet-200 dark:border-violet-800 p-4 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/50 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-violet-800 dark:text-violet-200">Mentor Approved</p>
                        <p class="text-xs text-violet-600 dark:text-violet-400 mt-0.5">
                            Approved {{ \Carbon\Carbon::parse($participant->mentor_approved_at)->format('d M Y') }} · Awaiting Head DRMH certification
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-violet-100 dark:bg-violet-900/60 text-[11px] font-semibold text-violet-700 dark:text-violet-300 shrink-0">
                        <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-violet-500"></span>
                        Pending
                    </span>
                </div>
            @endif

            {{-- ── Hero card ───────────────────────────────────────────────────── --}}
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="h-1.5 bg-gradient-to-r from-brand-600 via-violet-500 to-purple-600"></div>

                <div class="p-5 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center gap-5">

                    {{-- Circular progress ring --}}
                    @php $circumference = 2 * M_PI * 30; $filled = ($completionRate / 100) * $circumference; @endphp
                    <div class="relative shrink-0 w-20 h-20 sm:w-24 sm:h-24">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 72 72">
                            <circle class="ring-track" cx="36" cy="36" r="30" fill="none" stroke-width="7"/>
                            <circle class="ring-fill" cx="36" cy="36" r="30" fill="none" stroke-width="7"
                                    stroke-dasharray="{{ number_format($circumference, 3) }}"
                                    stroke-dashoffset="{{ number_format($circumference - $filled, 3) }}"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center leading-none">
                            <span class="text-[17px] sm:text-lg font-bold text-brand-600 dark:text-brand-400">{{ $completionRate }}%</span>
                            <span class="text-[9px] sm:text-[10px] text-slate-400 mt-0.5">complete</span>
                        </div>
                    </div>

                    {{-- Class info --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-widest text-brand-600 dark:text-brand-400 mb-1">
                            {{ $training->title }}
                        </p>
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white leading-tight">
                            {{ $class->name }}
                        </h1>
                        @if($class->facility?->name ?? $training->facility?->name)
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5 flex-wrap">
                                <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                {{ $class->facility?->name ?? $training->facility?->name }}
                                @if($class->start_date)
                                    <span class="text-slate-300 dark:text-slate-600">·</span>
                                    {{ \Carbon\Carbon::parse($class->start_date)->format('d M Y') }}
                                    @if($class->end_date) – {{ \Carbon\Carbon::parse($class->end_date)->format('d M Y') }}@endif
                                @endif
                            </p>
                        @elseif($class->start_date)
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                {{ \Carbon\Carbon::parse($class->start_date)->format('d M Y') }}
                                @if($class->end_date) – {{ \Carbon\Carbon::parse($class->end_date)->format('d M Y') }}@endif
                            </p>
                        @endif

                        {{-- Inline progress message --}}
                        @if($completionRate === 100)
                            <p class="mt-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                All modules complete
                            </p>
                        @elseif($completionRate >= 50)
                            <p class="mt-2 text-xs font-medium text-brand-600 dark:text-brand-400">
                                {{ $totalCount - $completedCount }} module{{ ($totalCount - $completedCount) === 1 ? '' : 's' }} remaining
                            </p>
                        @elseif($inProgressCount > 0)
                            <p class="mt-2 text-xs font-medium text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                {{ $inProgressCount }} module{{ $inProgressCount === 1 ? '' : 's' }} in progress
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Stat bar --}}
                <div class="grid grid-cols-4 border-t border-slate-100 dark:border-slate-800 divide-x divide-slate-100 dark:divide-slate-800">
                    <div class="py-3 text-center">
                        <p class="text-base sm:text-lg font-bold text-slate-800 dark:text-slate-200">{{ $totalCount }}</p>
                        <p class="text-[11px] text-slate-400">Modules</p>
                    </div>
                    <div class="py-3 text-center">
                        <p class="text-base sm:text-lg font-bold text-amber-600 dark:text-amber-400">{{ $inProgressCount }}</p>
                        <p class="text-[11px] text-slate-400">In Progress</p>
                    </div>
                    <div class="py-3 text-center">
                        <p class="text-base sm:text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $completedCount }}</p>
                        <p class="text-[11px] text-slate-400">Completed</p>
                    </div>
                    <div class="py-3 text-center">
                        <p class="text-base sm:text-lg font-bold text-violet-600 dark:text-violet-400">{{ $exemptedCount }}</p>
                        <p class="text-[11px] text-slate-400">Exempted</p>
                    </div>
                </div>
            </div>

            {{-- ── EmONC Next Up banner ────────────────────────────────────────── --}}
            @if($isEmonc && $nextUp)
                <div class="bg-gradient-to-r from-brand-600 to-violet-600 rounded-2xl p-5 sm:p-6 text-white shadow-lg shadow-brand-600/20">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-widest text-white/60 mb-1">Continue Where You Left Off</p>
                            <h2 class="text-base sm:text-lg font-bold leading-snug">{{ $nextUp['module_name'] }}</h2>
                            <div class="mt-2.5 flex flex-wrap gap-2">
                                @foreach($nextUp['steps'] as $i => $step)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $i === 0 ? 'bg-white text-brand-700 shadow-sm' : 'bg-white/15 text-white' }}">
                                        @if($i === 0)
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        @else
                                            <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                        {{ $step }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <a href="{{ route('mentee.class.module', [$class->id, $nextUp['module_id']]) }}"
                           class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-white text-brand-700 hover:bg-brand-50 font-bold text-sm transition-colors shadow-sm shrink-0">
                            Continue
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                </div>
            @endif

            {{-- ── Module cards ────────────────────────────────────────────────── --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Modules</h2>
                    @if($totalCount > 0)
                        <div class="flex items-center gap-3">
                            @if($completedCount > 0)
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                    {{ $completedCount }} done
                                </span>
                            @endif
                            @if($inProgressCount > 0)
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                    <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    {{ $inProgressCount }} active
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="space-y-3">
                    @forelse($moduleProgress as $progress)
                        @php
                            $classModule    = $progress->classModule;
                            $moduleId       = $classModule?->id;
                            $moduleName     = $classModule?->programModule?->name ?? 'Unknown Module';
                            $moduleStatus   = $classModule?->status ?? 'not_started';
                            $progressStatus = $progress->status;
                            $linkActive     = (bool) ($classModule?->attendance_link_active ?? false);
                            $token          = $classModule?->attendance_token;

                            $hasConfirmed      = in_array($moduleId, $confirmedModuleIds);
                            $alreadyPresent    = $hasConfirmed || in_array($progressStatus, ['in_progress', 'completed', 'exempted']);
                            $showConfirmButton = ($moduleStatus === 'in_progress') && $linkActive && !$alreadyPresent;

                            $displayState = match(true) {
                                $progressStatus === 'exempted'  => 'exempted',
                                $progressStatus === 'completed' => 'completed',
                                $alreadyPresent                 => 'present',
                                $moduleStatus === 'in_progress' => 'awaiting',
                                $moduleStatus === 'completed'   => 'absent',
                                default                         => 'not_started',
                            };

                            [$accentBg, $borderCls, $badgeBg, $badgeText, $badgeLabel] = match($displayState) {
                                'completed'  => ['bg-emerald-500', 'border-emerald-200 dark:border-emerald-800/60',  'bg-emerald-100 dark:bg-emerald-900/40',  'text-emerald-700 dark:text-emerald-300',  'Completed'],
                                'present'    => ['bg-blue-500',   'border-blue-200 dark:border-blue-800/60',        'bg-blue-100 dark:bg-blue-900/40',         'text-blue-700 dark:text-blue-300',        'Present'],
                                'exempted'   => ['bg-violet-500', 'border-violet-200 dark:border-violet-800/60',    'bg-violet-100 dark:bg-violet-900/40',     'text-violet-700 dark:text-violet-300',    'Exempted'],
                                'awaiting'   => ['bg-amber-500',  'border-amber-200 dark:border-amber-800/60',      'bg-amber-100 dark:bg-amber-900/40',       'text-amber-700 dark:text-amber-300',      'Action Required'],
                                'absent'     => ['bg-red-400',    'border-red-200 dark:border-red-800/60',          'bg-red-100 dark:bg-red-900/40',           'text-red-700 dark:text-red-300',          'Absent'],
                                default      => ['bg-slate-200 dark:bg-slate-700', 'border-slate-200 dark:border-slate-700', 'bg-slate-100 dark:bg-slate-800', 'text-slate-500 dark:text-slate-400', 'Not Started'],
                            };

                            // EmONC pipeline computed values
                            $quizzes        = $classModule?->programModule?->quizzes ?? collect();
                            $hasPreTest     = $quizzes->contains(fn($q) => $q->isPreTest());
                            $hasPostTest    = $quizzes->contains(fn($q) => $q->isPostTest());
                            $preTestDone    = $progress->pre_test_attempt_id !== null;
                            $videoSubmitted = $progress->hands_on_video_url || $progress->hands_on_video_path;
                            $videoStatus    = $progress->video_review_status; // null | passed | failed
                            $postTestDone   = $progress->post_test_attempt_id !== null;

                            $showEmonPipeline = $isEmonc && $progressStatus !== 'not_started';
                        @endphp

                        <div class="module-card bg-white dark:bg-slate-900 rounded-xl border {{ $borderCls }} overflow-hidden">
                            <div class="flex">

                                {{-- Left state accent --}}
                                <div class="w-1 shrink-0 {{ $accentBg }}"></div>

                                <div class="flex-1 p-4 sm:p-5">

                                    {{-- Row 1: Name + badge --}}
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            @if(in_array($displayState, ['present', 'completed', 'exempted', 'absent']))
                                            <a href="{{ route('mentee.class.module', [$class->id, $classModule->id]) }}"
                                               class="font-semibold text-[15px] text-slate-900 dark:text-white leading-snug hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                                                {{ $moduleName }}
                                            </a>
                                            @else
                                            <span class="font-semibold text-[15px] leading-snug
                                                @if($displayState === 'awaiting') text-amber-700 dark:text-amber-400
                                                @else text-slate-400 dark:text-slate-500
                                                @endif">
                                                @if($displayState === 'not_started')
                                                    <svg class="inline w-3.5 h-3.5 mr-1 -mt-0.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                @elseif($displayState === 'awaiting')
                                                    <svg class="inline w-3.5 h-3.5 mr-1 -mt-0.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                                @endif
                                                {{ $moduleName }}
                                            </span>
                                            @endif
                                            @if($classModule?->programModule?->parent)
                                                <p class="text-xs text-brand-500 dark:text-brand-400 mt-0.5 font-medium">
                                                    {{ $classModule->programModule->parent->name }}
                                                </p>
                                            @endif
                                            @if($progress->started_at || ($progress->completed_at && $progressStatus === 'completed'))
                                                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 flex flex-wrap items-center gap-x-1.5">
                                                    @if($progress->started_at)
                                                        <span>Started {{ \Carbon\Carbon::parse($progress->started_at)->format('d M Y') }}</span>
                                                    @endif
                                                    @if($progress->completed_at && $progressStatus === 'completed')
                                                        <span class="opacity-40">·</span>
                                                        <span>Completed {{ \Carbon\Carbon::parse($progress->completed_at)->format('d M Y') }}</span>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>

                                        {{-- Status badge --}}
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold shrink-0 {{ $badgeBg }} {{ $badgeText }}">
                                            @if($displayState === 'awaiting')
                                                <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            @elseif($displayState === 'completed')
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                            @elseif($displayState === 'present')
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @elseif($displayState === 'exempted')
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                            @elseif($displayState === 'absent')
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            @else
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @endif
                                            {{ $badgeLabel }}
                                        </span>
                                    </div>

                                    {{-- EmONC 4-step pipeline ──────────────────────────── --}}
                                    @if($showEmonPipeline)
                                        @php
                                            $pipelineSteps = [
                                                ['label' => 'Pre-test',  'done' => !$hasPreTest || $preTestDone,   'na' => !$hasPreTest,  'failed' => false],
                                                ['label' => 'Video',     'done' => (bool)$videoSubmitted,          'na' => false,         'failed' => false],
                                                ['label' => 'Reviewed',  'done' => $videoStatus === 'passed',      'na' => false,         'failed' => $videoStatus === 'failed'],
                                                ['label' => 'Post-test', 'done' => !$hasPostTest || $postTestDone, 'na' => !$hasPostTest, 'failed' => false],
                                            ];
                                        @endphp
                                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                            <div class="flex items-center">
                                                @foreach($pipelineSteps as $i => $step)
                                                    @if($i > 0)
                                                        <div class="flex-1 h-px {{ $pipelineSteps[$i-1]['done'] && !$pipelineSteps[$i-1]['failed'] ? 'bg-emerald-400 dark:bg-emerald-600' : 'bg-slate-200 dark:bg-slate-700' }}"></div>
                                                    @endif
                                                    <div class="flex flex-col items-center gap-1 shrink-0">
                                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold
                                                            @if($step['na'])
                                                                bg-slate-100 dark:bg-slate-800 text-slate-300 dark:text-slate-600 ring-1 ring-slate-200 dark:ring-slate-700
                                                            @elseif($step['failed'])
                                                                bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 ring-1 ring-red-200 dark:ring-red-800
                                                            @elseif($step['done'])
                                                                bg-emerald-500 text-white shadow-sm shadow-emerald-200 dark:shadow-none
                                                            @else
                                                                bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 ring-1 ring-slate-200 dark:ring-slate-700
                                                            @endif">
                                                            @if($step['na'])
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                                            @elseif($step['failed'])
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            @elseif($step['done'])
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                            @else
                                                                {{ $i + 1 }}
                                                            @endif
                                                        </div>
                                                        <span class="text-[9px] font-semibold leading-none
                                                            @if($step['na'])
                                                                text-slate-300 dark:text-slate-600
                                                            @elseif($step['failed'])
                                                                text-red-500 dark:text-red-400
                                                            @elseif($step['done'])
                                                                text-emerald-600 dark:text-emerald-400
                                                            @else
                                                                text-slate-400 dark:text-slate-500
                                                            @endif">
                                                            {{ $step['label'] }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @if($videoStatus === 'failed')
                                                <p class="mt-2.5 text-[11px] text-red-600 dark:text-red-400 font-medium flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                    Video needs improvement — please upload a revised video
                                                </p>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Confirm attendance CTA ─────────────────────────── --}}
                                    @if($showConfirmButton && $token)
                                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center gap-3">
                                            <div class="flex items-start gap-2.5 flex-1">
                                                <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0 mt-0.5">
                                                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                                    </svg>
                                                </div>
                                                <p class="text-sm text-slate-600 dark:text-slate-400 leading-snug">
                                                    This module is in progress — confirm your attendance now.
                                                </p>
                                            </div>
                                            <a href="{{ route('module.attendance', ['token' => $token]) }}"
                                               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-sm font-semibold transition-all shadow-sm shrink-0 whitespace-nowrap">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Confirm Attendance
                                            </a>
                                        </div>
                                    @endif

                                    {{-- Attendance confirmed (non-EmONC, module still running) ── --}}
                                    @if($alreadyPresent && $moduleStatus === 'in_progress' && !$showConfirmButton && !$isEmonc)
                                        <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                                            </svg>
                                            <p class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">
                                                Attendance confirmed · Awaiting mentor to finalise the module.
                                            </p>
                                        </div>
                                    @endif

                                    {{-- Mentor recommendation / feedback ─────────────────── --}}
                                    @if($progress->mentor_recommendation && in_array($progressStatus, ['completed', 'in_progress']))
                                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                            <div class="flex items-center gap-2 mb-2">
                                                <div class="w-5 h-5 rounded-md bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center shrink-0">
                                                    <svg class="w-3 h-3 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                </div>
                                                <span class="text-xs font-bold text-brand-700 dark:text-brand-300">Mentor Feedback</span>
                                            </div>
                                            <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line bg-slate-50 dark:bg-slate-800/60 rounded-lg px-3.5 py-3 border-l-2 border-brand-300 dark:border-brand-700">{{ $progress->mentor_recommendation }}</p>
                                        </div>
                                    @endif

                                </div>
                            </div>
                        </div>

                    @empty
                        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 px-6 py-14 text-center">
                            <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                <svg class="w-7 h-7 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No modules started yet</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Your mentor will start modules and you'll see them here.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- ── Resource links ─────────────────────────────────────────────── --}}
            <div class="space-y-2 pt-2">
                <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-3">Reference Materials</h2>

                @if($isEmonc)
                    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 flex items-center gap-3 hover:border-brand-200 dark:hover:border-brand-800 transition-colors group">
                        <div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-950/50 flex items-center justify-center shrink-0 border border-brand-100 dark:border-brand-900/50">
                            <svg class="w-5 h-5 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">EmONC Mentorship Manual</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">Reference for emergency obstetric and newborn care</p>
                        </div>
                        <a href="{{ url('/resources/emonc-mentorship-manual') }}"
                           target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-1 text-xs font-semibold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 transition-colors shrink-0">
                            Open
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </div>
                @else
                    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 flex items-center gap-3 hover:border-brand-200 dark:hover:border-brand-800 transition-colors group">
                        <div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-950/50 flex items-center justify-center shrink-0 border border-brand-100 dark:border-brand-900/50">
                            <svg class="w-5 h-5 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Infant and Child Mentorship Manual</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">Reference for infant and child care</p>
                        </div>
                        <a href="https://mnchkenyamentorship.org/resources/infant-child-mentorship-manual"
                           target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-1 text-xs font-semibold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 transition-colors shrink-0">
                            Open
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </div>

                    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 flex items-center gap-3 hover:border-brand-200 dark:hover:border-brand-800 transition-colors group">
                        <div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-950/50 flex items-center justify-center shrink-0 border border-brand-100 dark:border-brand-900/50">
                            <svg class="w-5 h-5 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Newborn Mentorship Mentor's Manual</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">Reference for newborn care</p>
                        </div>
                        <a href="https://mnchkenyamentorship.org/resources/newborn-mentorship-mentors-manual"
                           target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-1 text-xs font-semibold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 transition-colors shrink-0">
                            Open
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </div>
                @endif
            </div>

            <div class="h-6"></div>
        </main>

    </div>
    </body>
</html>
