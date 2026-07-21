@php
    $attempt = $status['attempt'];
    $quiz = $status['quiz'];
    $responses = $attempt->responses->keyBy('quiz_question_id');
    $revealAnswers = $revealAnswers ?? true;
@endphp

<div x-data="{ open: false }">
    <div class="rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50 px-4 py-3 mb-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                    Score: {{ $attempt->correct_answers }}/{{ $attempt->total_questions }}
                </p>
                @if(! $revealAnswers)
                    <p class="text-xs text-slate-500 mt-1">
                        Correct answers unlock after the post-test
                    </p>
                @endif
            </div>
            <button type="button"
                    @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shrink-0">
                <span x-text="open ? 'Hide review' : 'Show review'">Show review</span>
                <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>
    </div>

    <div class="space-y-4" x-show="open" x-cloak>
        @foreach($quiz->questions as $index => $question)
        @php
            $response = $responses->get($question->id);
            $selectedOption = $response?->option;
            $correctOption = $question->options->firstWhere('is_correct', true);
            $isCorrect = $response?->is_correct ?? false;
        @endphp
        <div class="p-4 rounded-xl border {{ $isCorrect ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-800/60 dark:bg-emerald-950/20' : 'border-red-200 bg-red-50/60 dark:border-red-800/60 dark:bg-red-950/20' }}">
            <p class="font-semibold text-slate-900 dark:text-white mb-3">
                <span class="text-brand-600 dark:text-brand-400 mr-1">{{ $index + 1 }}.</span>
                {!! $question->question_text !!}
            </p>
            <div class="space-y-2">
                @foreach($question->options as $option)
                    @php
                        $isSelected = $selectedOption && $selectedOption->id === $option->id;
                        $isCorrectOption = $revealAnswers && $correctOption && $correctOption->id === $option->id;
                    @endphp
                    <div class="flex items-start gap-2.5 p-2.5 rounded-lg text-sm
                        {{ $isSelected && $isCorrectOption ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' :
                           ($isSelected && ! $isCorrectOption ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' :
                           ($isCorrectOption ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300' :
                           'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-100 dark:border-slate-700')) }}">
                        @if($isSelected && $isCorrectOption)
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span><strong>Your answer:</strong> {{ $option->option_text }}</span>
                        @elseif($isSelected && ! $isCorrectOption)
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span><strong>Your answer:</strong> {{ $option->option_text }}</span>
                        @elseif($isCorrectOption)
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span><strong>Correct answer:</strong> {{ $option->option_text }}</span>
                        @else
                            <span class="w-4 h-4 rounded-full border border-slate-300 dark:border-slate-600 mt-0.5 shrink-0"></span>
                            <span>{{ $option->option_text }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
            @if($revealAnswers && $question->explanation)
                <div class="mt-3 text-xs text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-900 p-3 rounded-lg border border-slate-200 dark:border-slate-700">
                    <strong>Explanation:</strong> {{ $question->explanation }}
                </div>
            @endif
        </div>
    @endforeach
</div>
</div>
