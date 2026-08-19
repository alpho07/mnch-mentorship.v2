<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification — {{ $mentor->name ?? 'Mentor' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
    <main class="max-w-2xl mx-auto px-4 py-12">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-8 text-center border-b border-slate-100 dark:border-slate-700">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4 {{ $isValid ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' }}">
                    @if ($isValid)
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    @else
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    @endif
                </div>
                <h1 class="text-2xl font-extrabold">
                    {{ $isValid ? 'Certificate Verified' : 'Certificate Not Verified' }}
                </h1>
                <p class="mt-2 text-slate-500 dark:text-slate-400">
                    {{ $isValid ? 'This mentor facilitation certificate is authentic and has been issued by the MNCH Mentorship Platform.' : 'This mentor has not yet completed facilitation of every module in this program.' }}
                </p>
            </div>

            <div class="px-6 py-8 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Mentor</p>
                        <p class="mt-1 text-lg font-semibold">{{ $mentor->full_name ?? $mentor->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Program</p>
                        <p class="mt-1 text-lg font-semibold">{{ $program->name }}</p>
                    </div>
                    @if($progress)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Modules Facilitated</p>
                        <p class="mt-1 text-lg font-semibold">{{ $progress['modules_done'] }} of {{ $progress['modules_total'] }}</p>
                    </div>
                    @if(($progress['tracks_total'] ?? 0) > 0)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tracks Facilitated</p>
                        <p class="mt-1 text-lg font-semibold">{{ $progress['tracks_done'] }} of {{ $progress['tracks_total'] }}</p>
                    </div>
                    @endif
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Progress</p>
                        <p class="mt-1 text-lg font-semibold">{{ $progress['percent'] }}%</p>
                    </div>
                    @endif
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 text-center text-xs text-slate-400">
                Verified by MNCH Mentorship Platform on {{ now()->format('F d, Y') }}.
            </div>
        </div>
    </main>
</body>
</html>
