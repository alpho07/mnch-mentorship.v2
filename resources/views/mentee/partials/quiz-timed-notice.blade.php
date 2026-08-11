@if($quiz->hasTimeLimit())
    <div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-950/30 px-4 py-3 mb-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-sky-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <p class="text-sm font-semibold text-sky-800 dark:text-sky-200">Timed test — {{ $quiz->time_limit_minutes }} minute{{ $quiz->time_limit_minutes === 1 ? '' : 's' }}</p>
            <p class="text-xs text-sky-700 dark:text-sky-300 mt-1">Find a quiet place with a stable internet connection before you start. The timer keeps running once started, so make sure you won't be interrupted.</p>
        </div>
    </div>
@endif
