{{-- resources/views/filament/modals/question-conditional-detail.blade.php --}}
{{-- Used in AssessmentQuestionResource "view_conditional" action --}}

<div class="space-y-4 p-4">
    <div class="flex items-center gap-2 mb-4">
        <x-heroicon-o-link class="w-5 h-5 text-warning-500" />
        <span class="font-semibold text-gray-900 dark:text-white">Question: {{ $question->question_code }}</span>
    </div>

    @php
        $logic = $question->display_conditions ?? [];
        $hasOperator = isset($logic['operator']) && in_array($logic['operator'], ['and', 'or']);
        $conditions  = $hasOperator ? ($logic['conditions'] ?? []) : [];
        $isSingle    = isset($logic['question_code']);
    @endphp

    {{-- Single condition --}}
    @if ($isSingle)
        <div class="rounded-lg border border-warning-300 bg-warning-50 dark:bg-warning-900/20 p-4">
            <p class="text-sm font-medium text-warning-700 dark:text-warning-300 mb-2">Shows when:</p>
            <div class="grid grid-cols-3 gap-2 text-sm">
                <div class="bg-white dark:bg-gray-800 rounded p-2 border">
                    <p class="text-xs text-gray-500 mb-1">Parent Code</p>
                    <p class="font-mono font-semibold text-primary-600">{{ $logic['question_code'] }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded p-2 border">
                    <p class="text-xs text-gray-500 mb-1">Operator</p>
                    <p class="font-semibold">{{ ucwords(str_replace('_', ' ', $logic['operator'] ?? 'equals')) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded p-2 border">
                    <p class="text-xs text-gray-500 mb-1">Value</p>
                    <p class="font-semibold text-success-600">{{ is_array($logic['value'] ?? null) ? implode(', ', $logic['value']) : ($logic['value'] ?? '—') }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Multi-condition (AND / OR) --}}
    @if ($hasOperator && count($conditions) > 0)
        <div class="rounded-lg border border-info-300 bg-info-50 dark:bg-info-900/20 p-4">
            <p class="text-sm font-medium text-info-700 dark:text-info-300 mb-3">
                Shows when <span class="uppercase font-bold text-info-800">{{ $logic['operator'] }}</span> of the following:
            </p>
            <div class="space-y-2">
                @foreach ($conditions as $cond)
                    <div class="grid grid-cols-3 gap-2 text-sm bg-white dark:bg-gray-800 rounded p-2 border">
                        <div>
                            <p class="text-xs text-gray-500">Parent Code</p>
                            <p class="font-mono font-semibold text-primary-600">{{ $cond['question_code'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Operator</p>
                            <p class="font-semibold">{{ ucwords(str_replace('_', ' ', $cond['operator'] ?? 'equals')) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Value</p>
                            <p class="font-semibold text-success-600">{{ $cond['value'] ?? '—' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Raw JSON (fallback) --}}
    @if (!$isSingle && !$hasOperator && !empty($logic))
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p class="text-sm font-medium text-gray-600 mb-2">Raw Conditional Logic JSON:</p>
            <pre class="text-xs font-mono text-gray-800 overflow-auto">{{ json_encode($logic, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    {{-- Dependent questions (who depends on this one?) --}}
    @php
        $dependents = \App\Models\AssessmentQuestion::where('display_conditions->question_code', $question->question_code)
            ->orWhereJsonContains('display_conditions->conditions', ['question_code' => $question->question_code])
            ->get();
    @endphp

    @if ($dependents->isNotEmpty())
        <div class="rounded-lg border border-gray-200 bg-gray-50 dark:bg-gray-800/50 p-4 mt-4">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
            Follower questions that depend on this question's answer:
            </p>
            <ul class="space-y-1">
                @foreach ($dependents as $dep)
                    <li class="flex items-center gap-2 text-sm">
                        <x-heroicon-o-arrow-turn-down-right class="w-4 h-4 text-gray-400" />
                        <span class="font-mono text-primary-600">{{ $dep->question_code }}</span>
                        <span class="text-gray-600">{{ Str::limit($dep->question_text, 60) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>