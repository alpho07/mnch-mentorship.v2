<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Executive Assessment Report — {{ $assessment->facility->name }}</title>
@php $isPdf = $isPdf ?? false; @endphp

@if(!$isPdf)
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endif

<style>
/* ── Reset & base ─────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: #1e293b;
    background: #f1f5f9;
    line-height: 1.5;
}
@media print {
    body { background: #fff; }
    .no-print { display: none !important; }
    .page-break { page-break-before: always; }
}

/* ── Layout ───────────────────────────────────────────────────────────── */
.container { max-width: 1100px; margin: 0 auto; padding: 0 1rem 3rem; }

/* ── Cover / header ──────────────────────────────────────────────────── */
.cover {
    background: linear-gradient(135deg, #0097A7 0%, #00BCD4 60%, #006064 100%);
    color: #fff;
    padding: 2.5rem 2rem 2rem;
    border-radius: 16px;
    margin: 1.5rem 0 1.5rem;
    position: relative;
    overflow: hidden;
}
.cover::after {
    content: '';
    position: absolute;
    right: -60px; top: -60px;
    width: 260px; height: 260px;
    background: rgba(255,255,255,.07);
    border-radius: 50%;
}
.cover-title { font-size: 1.7rem; font-weight: 700; letter-spacing: -.3px; }
.cover-sub   { font-size: .95rem; opacity: .85; margin-top: .25rem; }
.cover-meta  { display: flex; flex-wrap: wrap; gap: 1.5rem; margin-top: 1.4rem; font-size: .82rem; }
.cover-meta-item { display: flex; align-items: center; gap: .4rem; opacity: .9; }
.cover-badge {
    display: inline-block;
    padding: .25rem .75rem;
    border-radius: 99px;
    font-size: .78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-top: .6rem;
}
.badge-green  { background: rgba(16,185,129,.2); color: #34d399; border: 1px solid rgba(52,211,153,.35); }
.badge-yellow { background: rgba(245,158,11,.2); color: #fbbf24; border: 1px solid rgba(251,191,36,.35); }
.badge-red    { background: rgba(239,68,68,.2);  color: #f87171; border: 1px solid rgba(248,113,113,.35); }
.badge-gray   { background: rgba(148,163,184,.2); color: #cbd5e1; border: 1px solid rgba(203,213,225,.35); }

/* ── Overall score chips ─────────────────────────────────────────────── */
.score-row { display: flex; gap: 1rem; margin-top: 1.4rem; flex-wrap: wrap; }
.score-chip {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.25);
    border-radius: 12px;
    padding: .6rem 1.1rem;
    text-align: center;
    min-width: 100px;
}
.score-chip-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
.score-chip-label { font-size: .7rem; opacity: .8; text-transform: uppercase; letter-spacing: .5px; margin-top: .2rem; }

/* ── Toolbar ─────────────────────────────────────────────────────────── */
.toolbar {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: 1.25rem;
}
.btn {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .45rem 1rem;
    border-radius: 8px;
    font-size: .82rem; font-weight: 600;
    text-decoration: none; cursor: pointer; border: none;
    transition: opacity .15s;
}
.btn:hover { opacity: .85; }
.btn-teal    { background: #0097A7; color: #fff; }
.btn-outline { background: #fff; color: #0097A7; border: 1.5px solid #0097A7; }
.btn-ghost   { background: #f1f5f9; color: #475569; }

/* ── View tabs ───────────────────────────────────────────────────────── */
.view-tabs { display: flex; gap: .5rem; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; }
.view-tab {
    display: flex; align-items: center; gap: .45rem;
    padding: .65rem 1.1rem;
    font-size: .85rem; font-weight: 600; color: #64748b;
    background: none; border: none; border-bottom: 2px solid transparent;
    cursor: pointer;
}
.view-tab:hover { color: #0097A7; }
.view-tab.active { color: #0097A7; border-bottom-color: #0097A7; }
.view-tab-count {
    background: #e2e8f0; color: #475569;
    font-size: .68rem; font-weight: 700;
    padding: .05rem .45rem; border-radius: 99px;
}
.view-tab.active .view-tab-count { background: #ccfbf1; color: #0f766e; }

/* ── Compare-rounds delta chips ─────────────────────────────────────── */
.delta-chip {
    display: inline-flex; align-items: center; gap: .25rem;
    font-size: .72rem; font-weight: 700;
    padding: .1rem .5rem; border-radius: 99px;
    white-space: nowrap;
}
.delta-up   { background: #d1fae5; color: #065f46; }
.delta-down { background: #fee2e2; color: #991b1b; }
.delta-flat { background: #f1f5f9; color: #64748b; }

/* ── Section wrapper ─────────────────────────────────────────────────── */
.section-wrap {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.section-header {
    display: flex; align-items: center; gap: .75rem;
    padding: 1rem 1.4rem;
    border-bottom: 1px solid #f1f5f9;
}
.section-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.section-title { font-size: 1rem; font-weight: 700; }
.section-sub   { font-size: .75rem; color: #64748b; margin-top: .1rem; }
.section-body  { padding: 1.2rem 1.4rem; }

/* ── Score pill ──────────────────────────────────────────────────────── */
.score-pill {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .3rem .75rem;
    border-radius: 99px;
    font-size: .78rem; font-weight: 700;
    margin-left: auto; flex-shrink: 0;
}
.pill-green  { background: #d1fae5; color: #065f46; }
.pill-yellow { background: #fef3c7; color: #92400e; }
.pill-red    { background: #fee2e2; color: #991b1b; }
.pill-gray   { background: #f1f5f9; color: #475569; }

/* ── Radar / chart containers ────────────────────────────────────────── */
.charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 1.5rem; }
@media (max-width:700px) { .charts-row { grid-template-columns: 1fr; } }
.chart-wrap {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    padding: 1.2rem 1.4rem;
}
.chart-wrap-title { font-size: .82rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 1rem; }
.chart-canvas-wrap { position: relative; height: 240px; }

/* ── Indicator table ─────────────────────────────────────────────────── */
.indicator-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: .7rem;
}
.indicator-item {
    display: flex; align-items: flex-start; gap: .6rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    padding: .6rem .8rem;
}
.indicator-dot {
    width: 20px; height: 20px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .65rem; flex-shrink: 0; margin-top: .1rem;
}
.dot-yes  { background: #d1fae5; color: #065f46; }
.dot-no   { background: #fee2e2; color: #991b1b; }
.dot-na   { background: #f1f5f9; color: #94a3b8; }
.indicator-text { font-size: .78rem; color: #334155; line-height: 1.35; }

/* ── Insight cards ───────────────────────────────────────────────────── */
.insights-wrap { display: flex; flex-direction: column; gap: .7rem; margin-bottom: 1.5rem; }
.insight-card {
    display: flex; align-items: flex-start; gap: .9rem;
    padding: .85rem 1.1rem;
    border-radius: 10px;
    border-left: 4px solid;
}
.insight-card.success { background: #f0fdf4; border-color: #16a34a; }
.insight-card.warning { background: #fffbeb; border-color: #d97706; }
.insight-card.danger  { background: #fff1f2; border-color: #dc2626; }
.insight-icon { font-size: 1rem; width: 20px; flex-shrink: 0; padding-top: .1rem; }
.insight-card.success .insight-icon { color: #16a34a; }
.insight-card.warning .insight-icon { color: #d97706; }
.insight-card.danger  .insight-icon { color: #dc2626; }
.insight-area { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; opacity: .6; }
.insight-text { font-size: .82rem; color: #1e293b; line-height: 1.45; }

/* ── HR table ────────────────────────────────────────────────────────── */
.data-table { width: 100%; border-collapse: collapse; font-size: .78rem; }
.data-table th {
    background: #f8fafc; font-weight: 700; font-size: .68rem;
    text-transform: uppercase; letter-spacing: .4px; color: #64748b;
    padding: .55rem .75rem; border-bottom: 1.5px solid #e2e8f0;
    text-align: left;
}
.data-table td { padding: .5rem .75rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #f8fafc; }

/* ── Progress bar ────────────────────────────────────────────────────── */
.pbar-wrap { background: #e2e8f0; border-radius: 99px; height: 6px; flex: 1; }
.pbar-fill  { height: 100%; border-radius: 99px; }
.pbar-row   { display: flex; align-items: center; gap: .6rem; }
.pbar-label { font-size: .72rem; color: #64748b; white-space: nowrap; width: 120px; overflow: hidden; text-overflow: ellipsis; }
.pbar-val   { font-size: .72rem; font-weight: 700; white-space: nowrap; min-width: 36px; text-align: right; }

/* ── Section score summary strip ─────────────────────────────────────── */
.score-strip { display: grid; grid-template-columns: repeat(5,1fr); gap: .75rem; margin-bottom: 1.5rem; }
@media (max-width:700px) { .score-strip { grid-template-columns: repeat(2,1fr); } }
.score-strip-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    padding: .9rem 1rem;
    text-align: center;
    border-top: 4px solid;
}
.score-strip-card.green  { border-color: #10b981; }
.score-strip-card.yellow { border-color: #f59e0b; }
.score-strip-card.red    { border-color: #ef4444; }
.score-strip-card.gray   { border-color: #94a3b8; }
.score-strip-pct  { font-size: 1.5rem; font-weight: 700; }
.score-strip-name { font-size: .68rem; color: #64748b; text-transform: uppercase; letter-spacing: .4px; margin-top: .2rem; }

/* ── Color helpers ───────────────────────────────────────────────────── */
.text-green  { color: #065f46; }
.text-yellow { color: #92400e; }
.text-red    { color: #991b1b; }

/* ── Commodity dept bar ──────────────────────────────────────────────── */
.dept-bars { display: flex; flex-direction: column; gap: .6rem; }
.dept-bar-row { display: flex; align-items: center; gap: .75rem; }
.dept-name { font-size: .75rem; width: 130px; flex-shrink: 0; color: #334155; }
.dept-bar-wrap { flex: 1; background: #e2e8f0; border-radius: 99px; height: 8px; }
.dept-bar-fill { height: 100%; border-radius: 99px; }
.dept-pct { font-size: .72rem; font-weight: 700; min-width: 36px; text-align: right; }
</style>
</head>
<body>
<div class="container">

@php
    $gradeColor = match($assessment->overall_grade ?? 'red') {
        'green'  => 'pill-green',
        'yellow' => 'pill-yellow',
        default  => 'pill-red',
    };
    $gradeBadge = match($assessment->overall_grade ?? 'red') {
        'green'  => 'badge-green',
        'yellow' => 'badge-yellow',
        default  => 'badge-red',
    };
    $scoreColor = fn($grade) => match($grade ?? 'red') {
        'green'  => '#10b981',
        'yellow' => '#f59e0b',
        default  => '#ef4444',
    };
    $pillClass = fn($grade) => match($grade ?? 'red') {
        'green'  => 'pill-green',
        'yellow' => 'pill-yellow',
        default  => 'pill-red',
    };
    $stripClass = fn($grade) => $grade ?? 'gray';
@endphp

{{-- ── Toolbar ──────────────────────────────────────────────────────── --}}
<div class="toolbar no-print" style="margin-top:1.25rem;">
    <a href="{{ url()->previous() }}" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="{{ route('assessment.executive.export', $assessment) }}" class="btn btn-teal" target="_blank">
        <i class="fas fa-file-pdf"></i> Export PDF
    </a>
    <a href="/admin/assessments/{{ $assessment->id }}/summary" class="btn btn-outline" target="_blank">
        <i class="fas fa-file-alt"></i> Full Report
    </a>
    <span style="margin-left:auto;font-size:.78rem;color:#64748b;">Generated {{ now()->format('d M Y, H:i') }}</span>
</div>

@if(!$isPdf && $comparison)
{{-- ── View tabs ────────────────────────────────────────────────────── --}}
<div class="view-tabs no-print">
    <button type="button" class="view-tab active" data-tab="overview" onclick="mnchSwitchTab('overview')">
        <i class="fas fa-chart-pie"></i> Overview
    </button>
    <button type="button" class="view-tab" data-tab="compare" onclick="mnchSwitchTab('compare')">
        <i class="fas fa-code-compare"></i> Compare Rounds
        <span class="view-tab-count">{{ count($comparison['rounds']) }}</span>
    </button>
</div>
@endif

<div id="mnch-tab-overview">

{{-- ── Cover ────────────────────────────────────────────────────────── --}}
<div class="cover">
    <div class="cover-title">{{ $assessment->facility->name }}</div>
    <div class="cover-sub">Executive Assessment Report &mdash; {{ ucfirst($assessment->assessment_type) }} Assessment</div>
    <span class="cover-badge {{ $gradeBadge }}">
        {{ strtoupper($assessment->overall_grade ?? 'Incomplete') }}
    </span>

    <div class="score-row">
        <div class="score-chip">
            <div class="score-chip-value">{{ number_format($assessment->overall_percentage ?? 0, 1) }}%</div>
            <div class="score-chip-label">Overall Score</div>
        </div>
        <div class="score-chip">
            <div class="score-chip-value">{{ $sectionScores->count() }}</div>
            <div class="score-chip-label">Sections Scored</div>
        </div>
        <div class="score-chip">
            <div class="score-chip-value">{{ $assessment->assessment_date->format('M Y') }}</div>
            <div class="score-chip-label">Assessment Date</div>
        </div>
    </div>

    <div class="cover-meta">
        <div class="cover-meta-item"><i class="fas fa-map-marker-alt"></i> {{ $assessment->facility->subcounty->county->name ?? '—' }} County</div>
        <div class="cover-meta-item"><i class="fas fa-sitemap"></i> {{ $assessment->facility->subcounty->name ?? '—' }} Subcounty</div>
        @if($assessment->facility->facilityLevel)
        <div class="cover-meta-item"><i class="fas fa-hospital"></i> {{ $assessment->facility->facilityLevel->name }}</div>
        @endif
        <div class="cover-meta-item"><i class="fas fa-user-md"></i> Assessor: {{ $assessment->assessor_name }}</div>
        <div class="cover-meta-item"><i class="fas fa-calendar"></i> {{ $assessment->assessment_date->format('d M Y') }}</div>
    </div>
</div>

{{-- ── Section Score Strip ──────────────────────────────────────────── --}}
<div class="score-strip">
    @foreach($sectionScores as $code => $ss)
    <div class="score-strip-card {{ $stripClass($ss->grade) }}">
        <div class="score-strip-pct" style="color:{{ $scoreColor($ss->grade) }}">{{ number_format($ss->percentage, 1) }}%</div>
        <div class="score-strip-name">{{ Str::limit($ss->name, 28) }}</div>
    </div>
    @endforeach
    @if($sectionScores->isEmpty())
    <div class="score-strip-card gray" style="grid-column:1/-1;text-align:center;color:#94a3b8;font-size:.8rem;">
        No section scores recorded yet. Assessment may be incomplete.
    </div>
    @endif
</div>

{{-- ── CEO Insights ─────────────────────────────────────────────────── --}}
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#eff6ff;color:#1d4ed8;"><i class="fas fa-lightbulb"></i></div>
        <div>
            <div class="section-title">Executive Insights</div>
            <div class="section-sub">Strategic findings for leadership decision-making</div>
        </div>
    </div>
    <div class="section-body">
        <div class="insights-wrap">
            @foreach($insights as $insight)
            <div class="insight-card {{ $insight['type'] }}">
                <div class="insight-icon"><i class="fas fa-{{ $insight['icon'] }}"></i></div>
                <div>
                    <div class="insight-area">{{ $insight['area'] }}</div>
                    <div class="insight-text">{{ $insight['text'] }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Data Quality ─────────────────────────────────────────────────── --}}
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-magnifying-glass-chart"></i></div>
        <div style="flex:1;">
            <div class="section-title">Data Quality</div>
            <div class="section-sub">Response completeness and cross-section relationships in this assessment</div>
        </div>
        <span class="score-pill {{ $pillClass($overallCompleteness >= 90 ? 'green' : ($overallCompleteness >= 70 ? 'yellow' : 'red')) }}">{{ $overallCompleteness }}% complete</span>
    </div>
    <div class="section-body">

        {{-- Completeness by section --}}
        @if($sectionCompleteness->isNotEmpty())
        <div style="margin-bottom:1.2rem;">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:.75rem;">Response Completeness by Section</div>
            <div class="dept-bars">
                @foreach($sectionCompleteness as $sc)
                @php
                    $scPct = (float) $sc['percentage'];
                    $scColor = ! $sc['valid'] ? '#94a3b8' : ($scPct >= 90 ? '#10b981' : ($scPct >= 70 ? '#f59e0b' : '#ef4444'));
                @endphp
                <div class="dept-bar-row">
                    <div class="dept-name">{{ Str::limit($sc['name'], 28) }}</div>
                    <div class="dept-bar-wrap">
                        <div class="dept-bar-fill" style="width:{{ $sc['valid'] ? $scPct : 0 }}%;background:{{ $scColor }};"></div>
                    </div>
                    <div class="dept-pct" style="color:{{ $scColor }};">{{ $sc['display'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Straight-lining flags --}}
        @if(!empty($straightLiningFlags))
        <div style="margin-bottom:1.2rem;">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:.75rem;">Response Pattern Flags</div>
            @foreach($straightLiningFlags as $flag)
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;padding:.7rem 1rem;border-radius:9px;background:#fffbeb;border:1px solid #fde68a;">
                <i class="fas fa-triangle-exclamation" style="color:#d97706;font-size:1rem;"></i>
                <div style="font-size:.78rem;color:#92400e;">
                    <strong>{{ $flag['section'] }}</strong> — all {{ $flag['count'] }} answered items marked "{{ $flag['value'] }}". Worth a spot-check that each item was assessed individually rather than answered uniformly.
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Cross-section relationship insights --}}
        @if(!empty($dataQualityInsights))
        <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:.75rem;">Relationships Across Sections</div>
            <div class="insights-wrap">
                @foreach($dataQualityInsights as $insight)
                <div class="insight-card {{ $insight['type'] }}">
                    <div class="insight-icon"><i class="fas fa-{{ $insight['icon'] }}"></i></div>
                    <div>
                        <div class="insight-area">{{ $insight['area'] }}</div>
                        <div class="insight-text">{{ $insight['text'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if(empty($straightLiningFlags) && empty($dataQualityInsights) && $sectionCompleteness->isEmpty())
        <p style="font-size:.8rem;color:#94a3b8;font-style:italic;">Not enough scored data yet to compute data-quality insights.</p>
        @endif

    </div>
</div>

{{-- ── Charts Row ───────────────────────────────────────────────────── --}}
@if(!$isPdf && $sectionScores->isNotEmpty())
<div class="charts-row">
    <div class="chart-wrap">
        <div class="chart-wrap-title">Section Score Radar</div>
        <div class="chart-canvas-wrap">
            <canvas id="radarChart"></canvas>
        </div>
    </div>
    <div class="chart-wrap">
        <div class="chart-wrap-title">Commodity Availability by Department</div>
        <div class="chart-canvas-wrap">
            <canvas id="deptChart"></canvas>
        </div>
    </div>
</div>
@endif

{{-- ── 1. Infrastructure ────────────────────────────────────────────── --}}
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-building"></i></div>
        <div style="flex:1;">
            <div class="section-title">Infrastructure</div>
            <div class="section-sub">Physical environment enabling quality newborn & paediatric care</div>
        </div>
        @if($sectionScores->has('infrastructure'))
        @php $ss = $sectionScores->get('infrastructure'); @endphp
        <span class="score-pill {{ $pillClass($ss->grade) }}">{{ number_format($ss->percentage,1) }}%</span>
        @endif
    </div>
    <div class="section-body">
        <div class="indicator-grid">
            @foreach($infraResponses as $item)
            @php
                $yes = $item->response_value === 'Yes';
                $answered = !is_null($item->response_value);
            @endphp
            <div class="indicator-item">
                <div class="indicator-dot {{ $answered ? ($yes ? 'dot-yes' : 'dot-no') : 'dot-na' }}">
                    @if(!$answered) <i class="fas fa-minus"></i>
                    @elseif($yes) <i class="fas fa-check"></i>
                    @else <i class="fas fa-times"></i>
                    @endif
                </div>
                <div class="indicator-text">{{ $item->question_text }}</div>
            </div>
            @endforeach
        </div>

        @if($sectionScores->has('infrastructure'))
        @php $ss = $sectionScores->get('infrastructure'); @endphp
        <div style="margin-top:1rem;padding:.75rem 1rem;background:#f8fafc;border-radius:9px;font-size:.78rem;color:#475569;">
            <strong>{{ $ss->total_questions !== null ? "{$ss->answered_questions}/{$ss->total_questions}" : 'N/A' }}</strong> indicators met &nbsp;&bull;&nbsp;
            Score: <strong>{{ number_format($ss->total_score,0) }}/{{ $ss->max_score }}</strong>
        </div>
        @endif
    </div>
</div>

{{-- ── 2. Skills Lab ────────────────────────────────────────────────── --}}
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-flask"></i></div>
        <div style="flex:1;">
            <div class="section-title">Skills Lab</div>
            <div class="section-sub">Simulation & training equipment readiness</div>
        </div>
        @if($sectionScores->has('skills_lab'))
        @php $ss = $sectionScores->get('skills_lab'); @endphp
        <span class="score-pill {{ $pillClass($ss->grade) }}">{{ number_format($ss->percentage,1) }}%</span>
        @endif
    </div>
    <div class="section-body">
        {{-- Dedicated lab status --}}
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.7rem 1rem;border-radius:9px;background:{{ $hasDedicatedLab ? '#f0fdf4' : '#fff1f2' }};border:1px solid {{ $hasDedicatedLab ? '#bbf7d0' : '#fecaca' }};">
            <i class="fas fa-{{ $hasDedicatedLab ? 'check-circle' : 'times-circle' }}" style="color:{{ $hasDedicatedLab ? '#16a34a' : '#dc2626' }};font-size:1.2rem;"></i>
            <div>
                <div style="font-weight:700;font-size:.82rem;color:{{ $hasDedicatedLab ? '#065f46' : '#991b1b' }};">
                    {{ $hasDedicatedLab ? 'Dedicated Skills Lab: Present' : 'Dedicated Skills Lab: Not Present' }}
                </div>
                @if(!$hasDedicatedLab)
                @php $hasRoom = $skillsResponses->firstWhere('question_code','SKILLS_ROOM'); @endphp
                <div style="font-size:.75rem;color:#64748b;margin-top:.15rem;">
                    Alternative room/space: {{ ($hasRoom && $hasRoom->response_value === 'Yes') ? 'Available' : 'Not available' }}
                </div>
                @endif
            </div>
        </div>

        {{-- Available items --}}
        @if($skillsAvailable->isNotEmpty())
        <div style="margin-bottom:.75rem;">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:.5rem;">Available Items ({{ $skillsAvailable->count() }})</div>
            <div class="indicator-grid">
                @foreach($skillsAvailable->take(12) as $item)
                <div class="indicator-item">
                    <div class="indicator-dot dot-yes"><i class="fas fa-check"></i></div>
                    <div class="indicator-text">{{ Str::limit($item->question_text, 70) }}</div>
                </div>
                @endforeach
                @if($skillsAvailable->count() > 12)
                <div class="indicator-item" style="background:none;border-color:transparent;color:#64748b;font-size:.75rem;align-items:center;">
                    + {{ $skillsAvailable->count() - 12 }} more available
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Missing scored items --}}
        @if($skillsMissing->isNotEmpty())
        <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc2626;margin-bottom:.5rem;">Missing Scored Items ({{ $skillsMissing->count() }})</div>
            <div class="indicator-grid">
                @foreach($skillsMissing as $item)
                <div class="indicator-item" style="background:#fff1f2;border-color:#fecaca;">
                    <div class="indicator-dot dot-no"><i class="fas fa-times"></i></div>
                    <div class="indicator-text">{{ Str::limit($item->question_text, 70) }}</div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ── 3. Human Resources ───────────────────────────────────────────── --}}
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#fef9c3;color:#ca8a04;"><i class="fas fa-users"></i></div>
        <div style="flex:1;">
            <div class="section-title">Human Resources</div>
            <div class="section-sub">Staff composition and specialist training coverage</div>
        </div>
        @php
            $hrGrade = $hrCoverage >= 80 ? 'green' : ($hrCoverage >= 50 ? 'yellow' : 'red');
        @endphp
        <span class="score-pill {{ $pillClass($hrGrade) }}">{{ $hrCoverage }}% trained</span>
    </div>
    <div class="section-body">
        @if($hrRows->isNotEmpty())
        {{-- Summary chips --}}
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:.6rem 1rem;text-align:center;min-width:90px;">
                <div style="font-size:1.3rem;font-weight:700;color:#0097A7;">{{ number_format($totalStaff) }}</div>
                <div style="font-size:.68rem;color:#64748b;text-transform:uppercase;letter-spacing:.4px;">Total Staff</div>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:.6rem 1rem;text-align:center;min-width:90px;">
                <div style="font-size:1.3rem;font-weight:700;color:#16a34a;">{{ number_format($totalTrained) }}</div>
                <div style="font-size:.68rem;color:#64748b;text-transform:uppercase;letter-spacing:.4px;">Trained</div>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:.6rem 1rem;text-align:center;min-width:90px;">
                <div style="font-size:1.3rem;font-weight:700;color:{{ $hrCoverage >= 60 ? '#16a34a' : ($hrCoverage >= 30 ? '#d97706' : '#dc2626') }};">{{ $hrCoverage }}%</div>
                <div style="font-size:.68rem;color:#64748b;text-transform:uppercase;letter-spacing:.4px;">Coverage</div>
            </div>
        </div>

        {{-- HR staff bars --}}
        <div class="dept-bars" style="margin-bottom:1rem;">
            @foreach($hrRows->take(10) as $row)
            @php $barW = $totalStaff > 0 ? min(100, round($row->total_in_facility/$totalStaff*100)) : 0; @endphp
            <div class="dept-bar-row">
                <div class="dept-name" title="{{ $row->cadre }}">{{ Str::limit($row->cadre, 22) }}</div>
                <div class="dept-bar-wrap">
                    <div class="dept-bar-fill" style="width:{{ $barW }}%;background:#0097A7;"></div>
                </div>
                <div class="dept-pct" style="color:#0097A7;">{{ $row->total_in_facility }}</div>
            </div>
            @endforeach
        </div>

        {{-- Full HR table --}}
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cadre</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:right;">ETAT+</th>
                    <th style="text-align:right;">Comp. Newborn</th>
                    <th style="text-align:right;">IMNCI</th>
                    <th style="text-align:right;">Type-1 DM</th>
                    <th style="text-align:right;">Ess. Newborn</th>
                    <th style="text-align:right;">Coverage</th>
                </tr>
            </thead>
            <tbody>
                @foreach($hrRows as $row)
                <tr>
                    <td style="font-weight:600;">{{ $row->cadre }}</td>
                    <td style="text-align:right;">{{ $row->total_in_facility }}</td>
                    <td style="text-align:right;">{{ $row->etat_plus }}</td>
                    <td style="text-align:right;">{{ $row->comprehensive_newborn_care }}</td>
                    <td style="text-align:right;">{{ $row->imnci }}</td>
                    <td style="text-align:right;">{{ $row->type_1_diabetes }}</td>
                    <td style="text-align:right;">{{ $row->essential_newborn_care }}</td>
                    <td style="text-align:right;">
                        <span style="font-weight:700;color:{{ $row->coverage_pct >= 60 ? '#16a34a' : ($row->coverage_pct >= 30 ? '#d97706' : '#dc2626') }}">
                            {{ $row->coverage_pct }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @else
        <div style="text-align:center;color:#94a3b8;padding:1.5rem;font-size:.82rem;">
            <i class="fas fa-user-slash fa-2x" style="display:block;margin-bottom:.5rem;"></i>
            No HR data recorded for this assessment.
        </div>
        @endif
    </div>
</div>

{{-- ── 4. Health Products ───────────────────────────────────────────── --}}
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#fdf4ff;color:#9333ea;"><i class="fas fa-capsules"></i></div>
        <div style="flex:1;">
            <div class="section-title">Health Products &amp; Technologies</div>
            <div class="section-sub">Commodity availability across clinical departments</div>
        </div>
        @php $hpGrade = $overallCommodityPct >= 80 ? 'green' : ($overallCommodityPct >= 50 ? 'yellow' : 'red'); @endphp
        <span class="score-pill {{ $pillClass($hpGrade) }}">{{ $overallCommodityPct }}% overall</span>
    </div>
    <div class="section-body">
        @if($deptScores->isNotEmpty())
        <div style="margin-bottom:1.2rem;">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:.75rem;">Availability by Department</div>
            <div class="dept-bars">
                @foreach($deptScores as $dept)
                @php
                    $dPct = (float) $dept->percentage;
                    $dColor = $dPct >= 75 ? '#10b981' : ($dPct >= 50 ? '#f59e0b' : '#ef4444');
                @endphp
                <div class="dept-bar-row">
                    <div class="dept-name">{{ $dept->department }}</div>
                    <div class="dept-bar-wrap">
                        <div class="dept-bar-fill" style="width:{{ $dPct }}%;background:{{ $dColor }};"></div>
                    </div>
                    <div class="dept-pct" style="color:{{ $dColor }};">{{ $dPct }}%</div>
                </div>
                @endforeach
            </div>
        </div>

        @if($categoryScores->isNotEmpty())
        <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:.75rem;">Availability by Commodity Category</div>
            <div class="dept-bars">
                @foreach($categoryScores as $cat)
                @php
                    $cPct = (float) $cat->percentage;
                    $cColor = $cPct >= 75 ? '#10b981' : ($cPct >= 50 ? '#f59e0b' : '#ef4444');
                @endphp
                <div class="dept-bar-row">
                    <div class="dept-name">{{ Str::limit($cat->category, 18) }}</div>
                    <div class="dept-bar-wrap">
                        <div class="dept-bar-fill" style="width:{{ $cPct }}%;background:{{ $cColor }};"></div>
                    </div>
                    <div class="dept-pct" style="color:{{ $cColor }};">{{ $cPct }}%</div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @else
        <div style="text-align:center;color:#94a3b8;padding:1.5rem;font-size:.82rem;">
            <i class="fas fa-box-open fa-2x" style="display:block;margin-bottom:.5rem;"></i>
            No commodity data recorded for this assessment.
        </div>
        @endif
    </div>
</div>

{{-- ── 5. Information Systems ───────────────────────────────────────── --}}
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#f0f9ff;color:#0369a1;"><i class="fas fa-database"></i></div>
        <div style="flex:1;">
            <div class="section-title">Information Systems &amp; Record Keeping</div>
            <div class="section-sub">Registers, forms, and digital readiness</div>
        </div>
        @if($sectionScores->has('information_systems'))
        @php $ss = $sectionScores->get('information_systems'); @endphp
        <span class="score-pill {{ $pillClass($ss->grade) }}">{{ number_format($ss->percentage,1) }}%</span>
        @endif
    </div>
    <div class="section-body">
        @php
            $infoScored   = $infoResponsesUngrouped->where('is_scored', 1);
        @endphp
        <div class="indicator-grid">
            @foreach($infoScored as $item)
            @php
                $yes = $item->response_value === 'Yes';
                $answered = !is_null($item->response_value);
            @endphp
            <div class="indicator-item">
                <div class="indicator-dot {{ $answered ? ($yes ? 'dot-yes' : 'dot-no') : 'dot-na' }}">
                    @if(!$answered) <i class="fas fa-minus"></i>
                    @elseif($yes) <i class="fas fa-check"></i>
                    @else <i class="fas fa-times"></i>
                    @endif
                </div>
                <div class="indicator-text">{{ $item->question_text }}</div>
            </div>
            @endforeach
        </div>

        @if($infoDataToolsTable->isNotEmpty())
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin:1.2rem 0 .5rem;">Data Collection Tools &amp; Registers</div>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Form / Register</th><th>Available</th><th>Completeness</th></tr></thead>
            <tbody>
                @foreach($infoDataToolsTable as $row)
                <tr>
                    <td>{{ $row['form'] }}</td>
                    <td>
                        @if($row['available'] === 'Yes') <span class="pill-green score-pill" style="margin:0;">Yes</span>
                        @elseif($row['available'] === 'No') <span class="pill-red score-pill" style="margin:0;">No</span>
                        @else <span class="pill-gray score-pill" style="margin:0;">{{ $row['available'] }}</span>
                        @endif
                    </td>
                    <td>{{ $row['completeness'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif

        @if($sectionScores->has('information_systems'))
        @php $ss = $sectionScores->get('information_systems'); @endphp
        <div style="margin-top:1rem;padding:.75rem 1rem;background:#f8fafc;border-radius:9px;font-size:.78rem;color:#475569;">
            <strong>{{ $ss->total_questions !== null ? "{$ss->answered_questions}/{$ss->total_questions}" : 'N/A' }}</strong> records/registers in place &nbsp;&bull;&nbsp;
            Score: <strong>{{ number_format($ss->total_score,0) }}/{{ $ss->max_score }}</strong>
        </div>
        @endif
    </div>
</div>

{{-- ── 6. Quality of Care ───────────────────────────────────────────── --}}
<div class="section-wrap">
    <div class="section-header">
        <div class="section-icon" style="background:#fff1f2;color:#e11d48;"><i class="fas fa-heartbeat"></i></div>
        <div style="flex:1;">
            <div class="section-title">Quality of Care</div>
            <div class="section-sub">Death audits and clinical outcome indicators</div>
        </div>
        @if($sectionScores->has('quality_of_care'))
        @php $ss = $sectionScores->get('quality_of_care'); @endphp
        <span class="score-pill {{ $pillClass($ss->grade) }}">{{ number_format($ss->percentage,1) }}%</span>
        @endif
    </div>
    <div class="section-body">
        {{-- Scored: audit indicators --}}
        @php
            $auditItems = ['QOC_NEONATAL_AUDIT','QOC_AUDIT_FREQUENCY','QOC_CHILD_AUDIT'];
        @endphp
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:.6rem;">Audit Practices</div>
        <div class="indicator-grid" style="margin-bottom:1.2rem;">
            @foreach($auditItems as $code)
            @if($qocAll->has($code))
            @php
                $item = $qocAll->get($code);
                $val  = $item->response_value;
                $yes  = $val === 'Yes';
                $answered = !is_null($val);
            @endphp
            <div class="indicator-item">
                <div class="indicator-dot {{ $answered ? ($yes ? 'dot-yes' : 'dot-no') : 'dot-na' }}">
                    @if(!$answered) <i class="fas fa-minus"></i>
                    @elseif($yes) <i class="fas fa-check"></i>
                    @elseif($code === 'QOC_AUDIT_FREQUENCY') <i class="fas fa-clock"></i>
                    @else <i class="fas fa-times"></i>
                    @endif
                </div>
                <div class="indicator-text">
                    {{ $item->question_text }}
                    @if($code === 'QOC_AUDIT_FREQUENCY' && $val)
                        <br><strong>{{ $val }}</strong>
                    @endif
                </div>
            </div>
            @endif
            @endforeach
        </div>

        {{-- Newborn stats --}}
        @php
            $newbornStats = ['QOC_NEWBORN_ADMISSIONS','QOC_PRETERMS_34','QOC_PRETERM_ASPHYXIA','QOC_CPAP_PLACED','QOC_APNOEA','QOC_CAFFEINE','QOC_NEWBORNS_34PLUS','QOC_ASPHYXIA_34PLUS','QOC_HYPOTHERMIA','QOC_O2_SAT_TAKEN','QOC_RBS_TAKEN','QOC_HEAD_TO_TOE'];
            $paedStats    = ['QOC_PAED_ADMISSIONS','QOC_PAED_O2_SAT','QOC_PAED_LOW_O2','QOC_PAED_O2_STARTED','QOC_PAED_RBS','QOC_PAED_HYPERGLYCEMIC'];
            $hasNewborn   = collect($newbornStats)->first(fn($c) => $qocAll->has($c) && $qocAll->get($c)->response_value !== null);
            $hasPaed      = collect($paedStats)->first(fn($c) => $qocAll->has($c) && $qocAll->get($c)->response_value !== null);
        @endphp

        @if($hasNewborn)
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:.5rem;margin-top:.5rem;">Newborn Clinical Indicators (3-month snapshot)</div>
        <div style="overflow-x:auto;margin-bottom:1rem;">
        <table class="data-table">
            <thead><tr><th>Indicator</th><th style="text-align:right;">Count</th></tr></thead>
            <tbody>
                @foreach($newbornStats as $code)
                @if($qocAll->has($code))
                @php $item = $qocAll->get($code); @endphp
                <tr>
                    <td>{{ Str::limit($item->question_text, 80) }}</td>
                    <td style="text-align:right;font-weight:600;">{{ $item->response_value ?? '—' }}</td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
        </div>
        @endif

        @if($hasPaed)
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:.5rem;">Paediatric Clinical Indicators</div>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Indicator</th><th style="text-align:right;">Count</th></tr></thead>
            <tbody>
                @foreach($paedStats as $code)
                @if($qocAll->has($code))
                @php $item = $qocAll->get($code); @endphp
                <tr>
                    <td>{{ Str::limit($item->question_text, 80) }}</td>
                    <td style="text-align:right;font-weight:600;">{{ $item->response_value ?? '—' }}</td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
</div>

</div>{{-- end #mnch-tab-overview --}}

@if(!$isPdf && $comparison)
<div id="mnch-tab-compare" style="display:none;">
    @include('analytics.assessment-executive._compare-rounds', ['comparison' => $comparison])
</div>
@endif

{{-- ── Footer ───────────────────────────────────────────────────────── --}}
<div style="text-align:center;padding:1.5rem 0;font-size:.72rem;color:#94a3b8;">
    MNCH Mentorship Programme &bull; Kenya &bull; Confidential &bull; Generated {{ now()->format('d M Y') }}
</div>

</div>{{-- end .container --}}

@if(!$isPdf && $comparison)
<script>
function mnchSwitchTab(tab) {
    document.getElementById('mnch-tab-overview').style.display = tab === 'overview' ? '' : 'none';
    document.getElementById('mnch-tab-compare').style.display = tab === 'compare' ? '' : 'none';
    document.querySelectorAll('.view-tab').forEach(function (btn) {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
}
</script>
@endif

{{-- ── Chart.js scripts ─────────────────────────────────────────────── --}}
@if(!$isPdf)
<script>
(function () {
    // Radar chart — section scores
    const radarEl = document.getElementById('radarChart');
    if (radarEl) {
        const labels = @json($sectionScores->pluck('name')->values());
        const data   = @json($sectionScores->pluck('percentage')->map(fn($v) => round($v,1))->values());
        new Chart(radarEl, {
            type: 'radar',
            data: {
                labels,
                datasets: [{
                    label: 'Section Score %',
                    data,
                    backgroundColor: 'rgba(0,151,167,.15)',
                    borderColor: '#0097A7',
                    pointBackgroundColor: '#0097A7',
                    pointRadius: 4,
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    r: {
                        min: 0, max: 100,
                        ticks: { stepSize: 20, font: { size: 10 } },
                        pointLabels: { font: { size: 10 } },
                        grid: { color: 'rgba(0,0,0,.07)' },
                        angleLines: { color: 'rgba(0,0,0,.07)' },
                    }
                }
            }
        });
    }

    // Dept bar chart
    const deptEl = document.getElementById('deptChart');
    if (deptEl) {
        const deptLabels = @json($deptScores->pluck('department')->values());
        const deptData   = @json($deptScores->pluck('percentage')->map(fn($v) => (float)$v)->values());
        const deptColors = deptData.map(v => v >= 75 ? '#10b981' : (v >= 50 ? '#f59e0b' : '#ef4444'));
        new Chart(deptEl, {
            type: 'bar',
            data: {
                labels: deptLabels,
                datasets: [{
                    label: 'Availability %',
                    data: deptData,
                    backgroundColor: deptColors,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { min: 0, max: 100, ticks: { font: { size: 10 } } },
                    y: { ticks: { font: { size: 10 } } }
                }
            }
        });
    }
})();
</script>
@endif

</body>
</html>
