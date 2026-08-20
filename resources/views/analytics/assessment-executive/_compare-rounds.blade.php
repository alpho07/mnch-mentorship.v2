{{--
    Comparative view for the executive dashboard — everything up to and
    including the assessment being viewed, in round order (baseline alone
    shows no comparison, so this partial is only ever included when at
    least 2 rounds exist — see the parent view's @if($comparison) guard).
    "Current vs previous" deltas always compare the two most recent
    entries in $comparison['rounds'], regardless of how many rounds are
    shown in total.
--}}
@php
    $rounds = $comparison['rounds'];
    $roundCount = count($rounds);
    $currentRound = $rounds[$roundCount - 1];
    $previousRound = $rounds[$roundCount - 2];

    $numericDelta = function ($current, $previous) {
        if (! is_numeric($current) || ! is_numeric($previous)) {
            return null;
        }
        $diff = round((float) $current - (float) $previous, 1);

        return [
            'diff' => $diff,
            'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'),
        ];
    };

    $deltaChip = function (?array $delta, bool $isProportion = false) {
        if ($delta === null) {
            return '<span style="color:#94a3b8;font-size:.72rem;">—</span>';
        }
        $icon = match ($delta['direction']) {
            'up' => 'fa-arrow-up',
            'down' => 'fa-arrow-down',
            default => 'fa-minus',
        };
        $sign = $delta['diff'] > 0 ? '+' : '';
        $suffix = $isProportion ? ' pts' : '';
        $pct = (! $isProportion && ($delta['percent_change'] ?? null) !== null)
            ? ' <span style="opacity:.7;">('.($delta['percent_change'] > 0 ? '+' : '').$delta['percent_change'].'%)</span>'
            : '';

        return '<span class="delta-chip delta-'.$delta['direction'].'"><i class="fas '.$icon.'"></i> '.$sign.$delta['diff'].$suffix.'</span>'.$pct;
    };

    $gradeColorFor = fn ($pct) => $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
@endphp

{{-- ── Rounds strip ─────────────────────────────────────────────────── --}}
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#ecfeff;color:#0e7490;"><i class="fas fa-code-compare"></i></div>
        <div>
            <div class="section-title">Rounds Compared</div>
            <div class="section-sub">Current vs. previous deltas below compare {{ $previousRound['label'] }} &rarr; {{ $currentRound['label'] }}</div>
        </div>
    </div>
    <div class="section-body">
        <div class="score-strip" style="grid-template-columns: repeat({{ $roundCount }}, 1fr);">
            @foreach($rounds as $r)
            @php
                $ov = $comparison['overallScore'][$r['id']] ?? null;
                $pct = $ov['percentage'] ?? null;
                $grade = $ov['grade'] ?? null;
                $stripCls = match($grade) { 'green','yellow','red' => $grade, default => 'gray' };
            @endphp
            <div class="score-strip-card {{ $stripCls }}">
                <div class="score-strip-pct" style="color:{{ is_numeric($pct) ? $gradeColorFor((float)$pct) : '#94a3b8' }}">
                    {{ is_numeric($pct) ? number_format((float)$pct, 1).'%' : '—' }}
                </div>
                <div class="score-strip-name">{{ $r['label'] }} &bull; {{ \Illuminate\Support\Carbon::parse($r['date'])->format('M Y') }}</div>
            </div>
            @endforeach
        </div>

        @php
            $overallDelta = $numericDelta(
                $comparison['overallScore'][$currentRound['id']]['percentage'] ?? null,
                $comparison['overallScore'][$previousRound['id']]['percentage'] ?? null
            );
        @endphp
        @if($overallDelta)
        <div style="margin-top:1rem;font-size:.82rem;color:#475569;">
            Overall score change ({{ $previousRound['label'] }} &rarr; {{ $currentRound['label'] }}): {!! $deltaChip($overallDelta) !!} percentage points
        </div>
        @endif
    </div>
</div>

{{-- ── Section scores across rounds ────────────────────────────────── --}}
@if(!empty($comparison['sectionScores']))
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#eff6ff;color:#1d4ed8;"><i class="fas fa-layer-group"></i></div>
        <div>
            <div class="section-title">Section Scores</div>
            <div class="section-sub">Percentage by section, one column per round</div>
        </div>
    </div>
    <div class="section-body">
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Section</th>
                    @foreach($rounds as $r)
                    <th style="text-align:right;">{{ $r['label'] }}</th>
                    @endforeach
                    <th style="text-align:right;">&Delta; ({{ $previousRound['label'] }}&rarr;{{ $currentRound['label'] }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($comparison['sectionScores'] as $row)
                @php
                    $curPct = $row['values'][$currentRound['id']]['percentage'] ?? null;
                    $prevPct = $row['values'][$previousRound['id']]['percentage'] ?? null;
                    $rowDelta = $numericDelta($curPct, $prevPct);
                @endphp
                <tr>
                    <td>{{ $row['label'] }}</td>
                    @foreach($rounds as $r)
                    @php $p = $row['values'][$r['id']]['percentage'] ?? null; @endphp
                    <td style="text-align:right;font-weight:600;color:{{ is_numeric($p) ? $gradeColorFor((float)$p) : '#94a3b8' }};">
                        {{ is_numeric($p) ? number_format((float)$p, 1).'%' : '—' }}
                    </td>
                    @endforeach
                    <td style="text-align:right;">{!! $deltaChip($rowDelta) !!}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif

{{-- ── Indicators comparison ───────────────────────────────────────── --}}
@php
    $indicatorBlocks = [
        'Newborn Clinical Indicators' => ['indicatorsNewborn', false],
        'Paediatric Clinical Indicators' => ['indicatorsPaediatric', false],
        'Newborn Proportion Indicators' => ['indicatorsNewbornProportions', true],
        'Paediatric Proportion Indicators' => ['indicatorsPaediatricProportions', true],
    ];
@endphp
@foreach($indicatorBlocks as $blockTitle => [$key, $isProportion])
@if(!empty($comparison[$key]))
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-chart-line"></i></div>
        <div>
            <div class="section-title">{{ $blockTitle }}</div>
            <div class="section-sub">
                {{ $isProportion ? 'Percentage-point change' : 'Count and % change' }}
                ({{ $previousRound['label'] }} &rarr; {{ $currentRound['label'] }})
            </div>
        </div>
    </div>
    <div class="section-body">
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Indicator</th>
                    @foreach($rounds as $r)
                    <th style="text-align:right;">{{ $r['label'] }}</th>
                    @endforeach
                    <th style="text-align:right;">&Delta;</th>
                </tr>
            </thead>
            <tbody>
                @foreach($comparison[$key] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    @foreach($rounds as $r)
                    <td style="text-align:right;">{{ $row['values'][$r['id']]['response'] ?? '—' }}</td>
                    @endforeach
                    <td style="text-align:right;">{!! $deltaChip($row['delta'] ?? null, $isProportion) !!}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif
@endforeach
