@php
    $programName = $class->training->program?->name ?? 'MNCH Mentorship';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification — {{ $participant->user->name ?? 'Mentee' }}</title>
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
                    {{ $isValid ? 'This certificate is authentic and has been issued by the MNCH Mentorship Platform.' : 'This certificate is not currently valid or has not yet completed the approval process.' }}
                </p>
            </div>

            <div class="px-6 py-8 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Recipient</p>
                        <p class="mt-1 text-lg font-semibold">{{ $participant->user->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Program</p>
                        <p class="mt-1 text-lg font-semibold">{{ $programName }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Class / Cohort</p>
                        <p class="mt-1 text-lg font-semibold">{{ $class->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Facility</p>
                        <p class="mt-1 text-lg font-semibold">{{ $class->training->facility?->name ?? '—' }}</p>
                    </div>
                </div>

                @if ($isValid)
                    <div class="border-t border-slate-100 dark:border-slate-700 pt-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Approval Chain</p>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-600 dark:text-slate-300">Mentor Approval</span>
                                <span class="text-sm font-semibold text-emerald-600">{{ $participant->mentorApprovedBy?->name ?? '—' }} &middot; {{ $participant->mentor_approved_at?->format('M d, Y') ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-600 dark:text-slate-300">Head DRMH Certification</span>
                                <span class="text-sm font-semibold text-emerald-600">{{ $participant->headDrmhApprovedBy?->name ?? '—' }} &middot; {{ $participant->head_drmh_approved_at?->format('M d, Y') ?? '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 pt-4">
                        <a href="{{ route('certificates.badge', [$class, $participant]) }}" target="_blank" class="inline-flex justify-center items-center gap-2 px-5 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-semibold transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                            View Digital Badge
                        </a>
                    </div>
                @endif
            </div>

            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 text-center text-xs text-slate-400">
                Verified by MNCH Mentorship Platform on {{ now()->format('F d, Y') }}.
            </div>
        </div>
    </main>
</body>
</html>
