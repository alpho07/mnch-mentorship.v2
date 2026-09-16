@php
    $id      = $indicator['id'];
    $val     = $values[$id] ?? [];
    $hasError= isset($valueErrors[$id]);
    $type    = $indicator['indicator_type'];

    $n   = $val['numerator']   ?? null;
    $d   = $val['denominator'] ?? null;
    $cnt = $val['count']       ?? null;
    $yn  = $val['yes_no']      ?? null;

    $pct = ($type === 'proportion' && is_numeric($n) && is_numeric($d) && (int)$d > 0)
        ? round(((int)$n / (int)$d) * 100, 1)
        : null;

    $isFilled = ! is_null($n) || ! is_null($d) || ! is_null($cnt) || ! is_null($yn);

    $catClass = match($indicator['category'] ?? '') {
        'outcome'      => 'fr-cat-outcome',
        'output'       => 'fr-cat-output',
        'process'      => 'fr-cat-process',
        'satisfaction' => 'fr-cat-other',
        default        => 'fr-cat-other',
    };

    $pctColor = $pct === null ? '#d1d5db' : ($pct >= 80 ? '#16a34a' : ($pct >= 50 ? '#d97706' : '#dc2626'));
@endphp

<div class="fr-ind {{ $hasError ? 'error-bg' : '' }}">
    <div class="fr-ind-header">

        {{-- Status dot --}}
        <span class="fr-status-dot {{ $isFilled ? 'filled' : 'empty' }}">
            @if($isFilled)
                <svg width="9" height="9" viewBox="0 0 12 12" fill="none">
                    <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            @else
                <svg width="8" height="8" viewBox="0 0 12 12" fill="none">
                    <path d="M3 6h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            @endif
        </span>

        <div class="fr-ind-body">
            <div class="fr-ind-top">
                <p class="fr-ind-name">
                    <span class="fr-code">{{ $indicator['code'] }}</span>{{ $indicator['name'] }}
                </p>
                <span class="fr-cat {{ $catClass }}">{{ $indicator['category'] ?? '' }}</span>
            </div>

            @if($indicator['source_document'])
                <p class="fr-meta-row">
                    <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0;">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                    </svg>
                    {{ $indicator['source_document'] }}
                </p>
            @endif

            @if($indicator['display_hint'])
                <p class="fr-hint-row">
                    <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0;margin-top:1px;">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    {{ $indicator['display_hint'] }}
                </p>
            @endif
        </div>
    </div>

    {{-- ── Input area ────────────────────────────────────────────────────── --}}

    @if($type === 'proportion')
        <div class="fr-input-grid">

            {{-- Numerator --}}
            <div>
                <label class="fr-lbl">
                Numerator
                    @if($indicator['numerator_label'])
                        <span class="fr-sublbl">{{ \Illuminate\Support\Str::limit($indicator['numerator_label'], 70) }}</span>
                    @endif
                </label>
                @if($isEditable)
                    <input type="number" min="0"
                   wire:model.lazy="values.{{ $id }}.numerator"
                   class="fr-input {{ $hasError ? 'err' : '' }}"
                   placeholder="Enter count"/>
                @else
                    <div class="fr-ro">{{ $n ?? '—' }}</div>
                @endif
            </div>

            {{-- Denominator --}}
            <div>
                <label class="fr-lbl">
                Denominator
                    @if($indicator['denominator_label'])
                        <span class="fr-sublbl">{{ \Illuminate\Support\Str::limit($indicator['denominator_label'], 70) }}</span>
                    @endif
                </label>
                @if($isEditable)
                    <input type="number" min="0"
                   wire:model.lazy="values.{{ $id }}.denominator"
                   class="fr-input {{ $hasError ? 'err' : '' }}"
                   placeholder="Enter total"/>
                @else
                    <div class="fr-ro">{{ $d ?? '—' }}</div>
                @endif
            </div>

            {{-- Computed % --}}
            <div>
                <label class="fr-lbl">Computed %</label>
                <div class="fr-pct-box">
                    @if($pct !== null)
                        <div class="fr-pct-val" style="color:{{ $pctColor }};">{{ $pct }}%</div>
                    @else
                        <div style="font-size:18px;color:#d1d5db;font-weight:700;">—</div>
                    @endif
                </div>
            </div>
        </div>

    @elseif($type === 'count')
        <div style="max-width:220px;">
            <label class="fr-lbl">Count</label>
            @if($isEditable)
                <input type="number" min="0"
               wire:model.lazy="values.{{ $id }}.count"
               class="fr-input {{ $hasError ? 'err' : '' }}"
               placeholder="Enter number"/>
            @else
                <div class="fr-ro">{{ $cnt ?? '—' }}</div>
            @endif
        </div>

    @elseif($type === 'yes_no')
        <div class="fr-yn">
            @if($isEditable)
                <label class="fr-yn-opt">
                    <input type="radio" wire:model="values.{{ $id }}.yes_no" value="1"
                   style="width:15px;height:15px;accent-color:#3b82f6;"/>
            Yes
                </label>
                <label class="fr-yn-opt">
                    <input type="radio" wire:model="values.{{ $id }}.yes_no" value="0"
                   style="width:15px;height:15px;accent-color:#3b82f6;"/>
            No
                </label>
            @else
                <span style="font-size:14px;font-weight:600;
              color:{{ $yn === null ? '#9ca3af' : ($yn ? '#16a34a' : '#dc2626') }};">
                    {{ $yn === null ? '—' : ($yn ? 'Yes' : 'No') }}
                </span>
            @endif
        </div>
    @endif

    {{-- Error message --}}
    @if($hasError)
        <p class="fr-err">
            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $valueErrors[$id] }}
        </p>
    @endif

    {{-- Optional comment --}}
    @if($isEditable)
        <input type="text"
           wire:model.lazy="values.{{ $id }}.comment"
           class="fr-comment"
           placeholder="Optional comment or data quality note…"/>
    @elseif($val['comment'] ?? null)
        <p style="margin-top:8px;font-size:12px;color:#9ca3af;font-style:italic;">
            Note: {{ $val['comment'] }}
        </p>
    @endif

</div>