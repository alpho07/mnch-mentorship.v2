@if(session('quiz_attempt_id'))
    @php $attempt = App\Models\QuizAttempt::with(['quiz.questions.options'])->find(session('quiz_attempt_id')); @endphp
    @if($attempt)
        @php
            $hasTimeLimit = $attempt->quiz->time_limit_minutes !== null;
            $deadlineMs = $hasTimeLimit
                ? $attempt->started_at->clone()->addMinutes($attempt->quiz->time_limit_minutes)->valueOf()
                : null;
        @endphp
        <div
             x-data="{
                open: true,
                hasTimer: {{ $hasTimeLimit ? 'true' : 'false' }},
                deadline: {{ $deadlineMs ?? 'null' }},
                remaining: 0,
                isOnline: navigator.onLine,
                pausedRemainingMs: null,
                timerHandle: null,
                init() {
                    if (!this.hasTimer) return;
                    this.tick();
                    this.timerHandle = setInterval(() => this.tick(), 1000);
                    window.addEventListener('offline', () => { this.isOnline = false; });
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        if (this.pausedRemainingMs !== null) {
                            this.deadline = Date.now() + this.pausedRemainingMs;
                            this.pausedRemainingMs = null;
                        }
                    });
                },
                tick() {
                    if (!this.isOnline) {
                        if (this.pausedRemainingMs === null) {
                            this.pausedRemainingMs = Math.max(0, this.deadline - Date.now());
                        }
                        return;
                    }
                    this.remaining = Math.max(0, Math.round((this.deadline - Date.now()) / 1000));
                    if (this.remaining <= 0) {
                        clearInterval(this.timerHandle);
                        this.autoSubmit();
                    }
                },
                autoSubmit() {
                    const form = this.$refs.quizForm;
                    form.querySelectorAll('[required]').forEach(el => el.removeAttribute('required'));
                    form.requestSubmit();
                },
                formatRemaining() {
                    const m = Math.floor(this.remaining / 60);
                    const s = this.remaining % 60;
                    return m + ':' + String(s).padStart(2, '0');
                }
             }"
             x-show="open"
             class="quiz-guard fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-sm"
             @copy.prevent @cut.prevent @paste.prevent @contextmenu.prevent @selectstart.prevent @dragstart.prevent
             @keydown="const k=$event.key.toLowerCase();const mod=$event.ctrlKey||$event.metaKey;if((mod&&['c','x','v','a','s','p','u'].includes(k))||k==='f12'||(mod&&$event.shiftKey&&['i','j','c'].includes(k))){$event.preventDefault()}"
             x-cloak>
            <div @click.away="open = false" class="bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-2xl border-t sm:border border-slate-200 dark:border-slate-800 shadow-2xl w-full sm:max-w-2xl max-h-[92vh] overflow-y-auto">
                <div class="sticky top-0 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-5 py-4 flex items-center justify-between rounded-t-3xl sm:rounded-t-2xl">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $attempt->quiz->title }}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $attempt->quiz->questions->count() }} question{{ $attempt->quiz->questions->count() === 1 ? '' : 's' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            x-show="hasTimer"
                            x-cloak
                            class="quiz-timer inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold tabular-nums"
                            :class="remaining <= 60 ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>
                            <span x-text="formatRemaining()"></span>
                        </span>
                        <button @click="open = false" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 hover:text-slate-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div
                    x-show="hasTimer && !isOnline"
                    x-cloak
                    class="quiz-offline-banner mx-5 mt-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-xs font-medium text-amber-700 dark:text-amber-300"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 11-1.414-1.414 1 1 0 011.414 1.414zM3 3l18 18"/></svg>
                    You're offline — the timer is paused. Reconnect to continue.
                </div>
                <div class="p-5">
                    <form x-ref="quizForm" action="{{ route('mentee.class.quiz.submit', [$class->id, $classModule->id, $attempt->id]) }}" method="POST" class="space-y-4">
                        @csrf
                        @foreach($attempt->quiz->questions as $index => $question)
                            <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 p-4">
                                <p class="font-semibold text-slate-900 dark:text-white mb-3 text-sm leading-relaxed">
                                    <span class="inline-flex w-6 h-6 rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-400 items-center justify-center text-[11px] font-bold mr-2 shrink-0 align-text-bottom">{{ $index + 1 }}</span>
                                    {!! $question->question_text !!}
                                </p>
                                <div class="space-y-2">
                                    @foreach($question->options as $option)
                                        <label class="flex items-start gap-3 p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-brand-300 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:has-[:checked]:bg-brand-950/30">
                                            <input type="radio" name="responses[{{ $question->id }}]" value="{{ $option->id }}" required class="mt-0.5 text-brand-600 focus:ring-brand-500 shrink-0">
                                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ $option->option_text }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition-colors shadow-sm">
                            Submit Quiz
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endif
