{{--
    Partial: indicator-row
    Variables: $indicator (array), $values (array from parent), $valueErrors (array), $isEditable (bool)
--}}
@php
    $id       = $indicator['id'];
    $val      = $values[$id] ?? [];
    $hasError = isset($valueErrors[$id]);
    $type     = $indicator['indicator_type'];

    $n    = $val['numerator']   ?? null;
    $d    = $val['denominator'] ?? null;
    $cnt  = $val['count']       ?? null;
    $yn   = $val['yes_no']      ?? null;

    $pct  = ($type === 'proportion' && is_numeric($n) && is_numeric($d) && (int)$d > 0)
        ? round(((int)$n / (int)$d) * 100, 1)
        : null;

    $isFilled = ! is_null($n) || ! is_null($d) || ! is_null($cnt) || ! is_null($yn);
@endphp

<div class="px-5 py-5 {{ $hasError ? 'bg-danger-50 dark:bg-danger-950/20' : '' }}">
    <div class="flex items-start gap-3">

        {{-- Filled dot --}}
        <span class="shrink-0 mt-0.5 w-5 h-5 rounded-full flex items-center justify-center
            {{ $isFilled ? 'bg-success-100 text-success-600 dark:bg-success-900/30 dark:text-success-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800' }}">
            @if($isFilled)
                <x-heroicon-m-check class="w-3 h-3" />
            @else
                <x-heroicon-m-minus class="w-3 h-3" />
            @endif
        </span>

        <div class="flex-1 min-w-0">
            {{-- Indicator header --}}
            <div class="flex items-start justify-between gap-2 mb-3">
                <div>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100 leading-snug">
                        <span class="inline-flex items-center justify-center rounded bg-gray-100 dark:bg-gray-700 text-xs font-bold text-gray-500 px-1.5 py-0.5 mr-1.5 shrink-0">{{ $indicator['code'] }}</span>
                        {{ $indicator['name'] }}
                    </p>

                    @if($indicator['source_document'])
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <x-heroicon-m-document-text class="w-3 h-3 shrink-0" />
                            {{ $indicator['source_document'] }}
                        </p>
                    @endif

                    @if($indicator['display_hint'])
                        <p class="text-xs text-primary-600 dark:text-primary-400 mt-1 italic">
                            <x-heroicon-m-information-circle class="w-3 h-3 inline mr-0.5" />{{ $indicator['display_hint'] }}
                        </p>
                    @endif
                </div>

                {{-- Category badge --}}
                <span class="shrink-0 text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full
                    @if($indicator['category'] === 'outcome') bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400
                    @elseif($indicator['category'] === 'output') bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400
                    @elseif($indicator['category'] === 'process') bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400
                    @else bg-gray-100 text-gray-500 @endif">
                    {{ $indicator['category'] }}
                </span>
            </div>

            {{-- ── Input fields per type ────────────────────────────────── --}}

            @if($type === 'proportion')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">

                    {{-- Numerator --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                        Numerator
                            @if($indicator['has_numerator'] && $indicator['numerator_label'])
                                <span class="font-normal italic text-gray-400 ml-1 text-[11px]">({{ Str::limit($indicator['numerator_label'], 60) }})</span>
                            @endif
                        </label>
                        @if($isEditable)
                            <input
                        type="number"
                        min="0"
                        wire:model.lazy="values.{{ $id }}.numerator"
                        class="w-full rounded-lg border text-sm py-2 px-3
                                    {{ $hasError ? 'border-danger-400 ring-1 ring-danger-400' : 'border-gray-300 dark:border-gray-600' }}
                        bg-white dark:bg-gray-800 dark:text-gray-100
                        focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="Enter count"
                        />
                        @else
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                                {{ $n ?? '—' }}
                            </div>
                        @endif
                    </div>

                    {{-- Denominator --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                        Denominator
                            @if($indicator['has_denominator'] && $indicator['denominator_label'])
                                <span class="font-normal italic text-gray-400 ml-1 text-[11px]">({{ Str::limit($indicator['denominator_label'], 60) }})</span>
                            @endif
                        </label>
                        @if($isEditable)
                            <input
                        type="number"
                        min="0"
                        wire:model.lazy="values.{{ $id }}.denominator"
                        class="w-full rounded-lg border text-sm py-2 px-3
                                    {{ $hasError ? 'border-danger-400 ring-1 ring-danger-400' : 'border-gray-300 dark:border-gray-600' }}
                        bg-white dark:bg-gray-800 dark:text-gray-100
                        focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="Enter total"
                        />
                        @else
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                                {{ $d ?? '—' }}
                            </div>
                        @endif
                    </div>

                    {{-- Computed % --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Computed %</label>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-3 py-2 text-center">
                            @if($pct !== null)
                                <span class="text-lg font-bold {{ $pct >= 80 ? 'text-success-600' : ($pct >= 50 ? 'text-warning-500' : 'text-danger-500') }}">
                                    {{ $pct }}%
                                </span>
                            @else
                                <span class="text-gray-400 text-sm">—</span>
                            @endif
                        </div>
                    </div>
                </div>

            @elseif($type === 'count')
                <div class="max-w-xs">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Count</label>
                    @if($isEditable)
                        <input
                    type="number"
                    min="0"
                    wire:model.lazy="values.{{ $id }}.count"
                    class="w-full rounded-lg border text-sm py-2 px-3
                                {{ $hasError ? 'border-danger-400 ring-1 ring-danger-400' : 'border-gray-300 dark:border-gray-600' }}
                    bg-white dark:bg-gray-800 dark:text-gray-100
                    focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Enter number"
                    />
                    @else
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm">
                            {{ $cnt ?? '—' }}
                        </div>
                    @endif
                </div>

            @elseif($type === 'yes_no')
                <div class="flex items-center gap-4">
                    @if($isEditable)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="values.{{ $id }}.yes_no" value="1"
                           class="text-primary-600 border-gray-300 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="values.{{ $id }}.yes_no" value="0"
                           class="text-primary-600 border-gray-300 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">No</span>
                        </label>
                    @else
                        <span class="text-sm {{ $yn === null ? 'text-gray-400' : ($yn ? 'text-success-600' : 'text-danger-600') }}">
                            {{ $yn === null ? '—' : ($yn ? 'Yes' : 'No') }}
                        </span>
                    @endif
                </div>
            @endif

            {{-- Error --}}
            @if($hasError)
                <p class="mt-2 text-xs text-danger-600 dark:text-danger-400 flex items-center gap-1">
                    <x-heroicon-m-exclamation-triangle class="w-3 h-3 shrink-0" />
                    {{ $valueErrors[$id] }}
                </p>
            @endif

            {{-- Optional comment field --}}
            @if($isEditable)
                <div class="mt-3">
                    <input
                    type="text"
                    wire:model.lazy="values.{{ $id }}.comment"
                    class="w-full rounded border border-gray-200 dark:border-gray-700 bg-transparent text-xs py-1 px-2 text-gray-400 placeholder-gray-300 dark:placeholder-gray-600 focus:ring-1 focus:ring-gray-300 focus:border-gray-300"
                    placeholder="Optional comment or data quality note…"
                    />
                </div>
            @elseif($val['comment'] ?? null)
                <p class="mt-2 text-xs text-gray-400 italic">Note: {{ $val['comment'] }}</p>
            @endif

        </div>
    </div>
</div>