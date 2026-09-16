<div class="space-y-6">
    <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $quiz->title }}</h2>
        @if($quiz->description)
            <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $quiz->description }}</p>
        @endif
        <div class="mt-3 flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-900/20 dark:text-blue-300">
                Type: {{ match($quiz->type) { 'pre_test' => 'Pre-test', 'post_test' => 'Post-test', 'both' => 'Pre & Post', default => $quiz->type } }}
            </span>
            <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/20 dark:text-green-300">
                Pass mark: {{ $quiz->pass_mark_percentage }}%
            </span>
            <span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 dark:bg-purple-900/20 dark:text-purple-300">
                Questions: {{ $quiz->questions->count() }}
            </span>
        </div>
    </div>

    @forelse($quiz->questions as $index => $question)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-start gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $index + 1 }}
                </span>
                <div class="flex-1">
                    <h3 class="font-medium text-gray-900 dark:text-white">{!! $question->question_text !!}</h3>

                    <div class="mt-3 space-y-2">
                        @foreach($question->options as $option)
                            <div class="flex items-center gap-3 rounded-md border px-3 py-2 {{ $option->is_correct ? 'border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700' }}">
                                <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border {{ $option->is_correct ? 'border-green-500 bg-green-500' : 'border-gray-300 dark:border-gray-600' }}">
                                    @if($option->is_correct)
                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    @endif
                                </div>
                                <span class="text-sm text-gray-700 dark:text-gray-300 {{ $option->is_correct ? 'font-medium text-green-800 dark:text-green-200' : '' }}">
                                    {!! $option->option_text !!}
                                </span>
                                @if($option->is_correct)
                                    <span class="ml-auto text-xs font-semibold text-green-700 dark:text-green-300">Correct</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($question->explanation)
                        <div class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                            <span class="font-semibold">Explanation:</span> {!! $question->explanation !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-gray-500 dark:border-gray-700 dark:text-gray-400">
            No questions added to this quiz yet.
        </div>
    @endforelse
</div>
