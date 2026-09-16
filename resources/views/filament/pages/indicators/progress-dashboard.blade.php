<x-filament-panels::page>

<style>
    /* ── Base ── */
    .pd-card {
        background:#fff; border:1px solid #e5e7eb; border-radius:14px;
        overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.06);
    }
    .dark .pd-card { background:#111827; border-color:#1f2937; }
    .pd-card-header {
        padding:14px 20px 12px; border-bottom:1px solid #f3f4f6;
        display:flex; align-items:center; justify-content:space-between;
    }
    .dark .pd-card-header { border-color:#1f2937; }
    .pd-card-title { font-size:13.5px; font-weight:700; color:#111827; display:flex; align-items:center; gap:7px; }
    .dark .pd-card-title { color:#f3f4f6; }

    /* ── Filters bar ── */
    .pd-filters {
        background:#fff; border:1px solid #e5e7eb; border-radius:14px;
        padding:14px 20px; box-shadow:0 1px 3px rgba(0,0,0,.05);
        display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap;
    }
    .dark .pd-filters { background:#111827; border-color:#1f2937; }
    .pd-filter-label {
        font-size:10.5px; font-weight:700; letter-spacing:.07em;
        text-transform:uppercase; color:#9ca3af; display:block; margin-bottom:5px;
    }
    .pd-filter-wrap { position:relative; display:flex; flex-direction:column; }
    /* Kill Filament's injected bg-image arrow AND browser native arrow, then use our own */
    .pd-select {
        appearance:none !important;
        -webkit-appearance:none !important;
        -moz-appearance:none !important;
        background-image:none !important;
        border:1.5px solid #e5e7eb; border-radius:9px;
        padding:8px 32px 8px 12px;
        font-size:13px; font-weight:500; color:#374151; background-color:#fff !important;
        outline:none; min-width:150px; cursor:pointer;
        transition:border-color .15s, box-shadow .15s;
        line-height:1.3; box-shadow:none !important;
    }
    .pd-select:focus { border-color:#3b82f6 !important; box-shadow:0 0 0 3px rgba(59,130,246,.12) !important; }
    .dark .pd-select { background-color:#1f2937 !important; border-color:#374151 !important; color:#d1d5db !important; }
    .pd-filter-arrow {
        position:absolute; right:9px; bottom:9px;
        pointer-events:none; color:#9ca3af; line-height:1;
    }
    .pd-divider { width:1px; height:36px; background:#e5e7eb; align-self:flex-end; margin-bottom:2px; }
    .dark .pd-divider { background:#1f2937; }

    /* ── Stat cards ── */
    .pd-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:1rem; }
    @media(max-width:1100px){ .pd-stats { grid-template-columns:repeat(3,1fr); } }
    @media(max-width:640px){ .pd-stats { grid-template-columns:repeat(2,1fr); } }
    .pd-stat {
        background:#fff; border:1px solid #e5e7eb; border-radius:14px;
        padding:18px 18px 16px; box-shadow:0 1px 3px rgba(0,0,0,.05);
        position:relative; overflow:hidden;
    }
    .dark .pd-stat { background:#111827; border-color:#1f2937; }
    .pd-stat-accent { position:absolute; top:0; left:0; right:0; height:3px; border-radius:14px 14px 0 0; }
    .pd-stat-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; }
    .pd-stat-val { font-size:26px; font-weight:800; color:#111827; line-height:1; }
    .dark .pd-stat-val { color:#f3f4f6; }
    .pd-stat-label { font-size:11.5px; color:#6b7280; margin-top:4px; }
    .pd-stat-sub { font-size:11px; margin-top:6px; display:flex; align-items:center; gap:4px; }

    /* ── Tab nav ── */
    .pd-tabs { display:flex; gap:4px; background:#f3f4f6; border-radius:10px; padding:3px; }
    .dark .pd-tabs { background:#1f2937; }
    .pd-tab {
        padding:7px 16px; border-radius:7px; font-size:13px; font-weight:600;
        color:#6b7280; cursor:pointer; border:none; background:transparent;
        transition:all .15s; white-space:nowrap;
    }
    .pd-tab:hover { color:#374151; }
    .dark .pd-tab:hover { color:#d1d5db; }
    .pd-tab.active { background:#fff; color:#111827; box-shadow:0 1px 3px rgba(0,0,0,.1); }
    .dark .pd-tab.active { background:#111827; color:#f3f4f6; }

    /* ── Trend chart (pure CSS bar chart) ── */
    .pd-chart { padding:20px; overflow-x:auto; }
    .pd-bars { display:flex; align-items:flex-end; gap:8px; height:180px; min-width:100%; }
    .pd-bar-group { display:flex; flex-direction:column; align-items:center; gap:4px; flex:1; min-width:44px; }
    .pd-bar-track { width:100%; background:#f3f4f6; border-radius:6px 6px 0 0; position:relative; overflow:hidden; }
    .dark .pd-bar-track { background:#1f2937; }
    .pd-bar-fill { border-radius:6px 6px 0 0; transition:height .6s ease; width:100%; position:absolute; bottom:0; }
    .pd-bar-val { font-size:10px; font-weight:700; color:#6b7280; }
    .pd-bar-label { font-size:10px; color:#9ca3af; text-align:center; }
    .pd-bar-tooltip { font-size:10px; color:#6b7280; text-align:center; }

    /* ── Facility trend table ── */
    .pd-trend-table { width:100%; border-collapse:collapse; font-size:13px; }
    .pd-trend-table thead { background:#f8fafc; }
    .dark .pd-trend-table thead { background:rgba(255,255,255,.03); }
    .pd-trend-table th {
        padding:9px 14px; font-size:10px; font-weight:700;
        letter-spacing:.07em; text-transform:uppercase; color:#9ca3af;
        border-bottom:1px solid #f3f4f6; text-align:left;
    }
    .dark .pd-trend-table th { border-color:#1f2937; }
    .pd-trend-table th:not(:first-child) { text-align:center; }
    .pd-trend-table td { padding:10px 14px; border-bottom:1px solid #f9fafb; vertical-align:middle; }
    .dark .pd-trend-table td { border-color:rgba(255,255,255,.04); }
    .pd-trend-table tr:last-child td { border-bottom:none; }
    .pd-trend-table tr:hover td { background:#fafafa; }
    .dark .pd-trend-table tr:hover td { background:rgba(255,255,255,.018); }

    /* Trend arrow */
    .pd-arrow-up     { color:#16a34a; }
    .pd-arrow-down   { color:#dc2626; }
    .pd-arrow-stable { color:#d97706; }
    .pd-arrow-unknown{ color:#d1d5db; }

    /* Mini bar inline */
    .pd-mini-wrap { display:flex; align-items:center; gap:6px; }
    .pd-mini-track { flex:1; height:6px; background:#e5e7eb; border-radius:99px; overflow:hidden; min-width:60px; }
    .dark .pd-mini-track { background:#1f2937; }
    .pd-mini-fill { height:100%; border-radius:99px; }

    /* Change badge */
    .pd-change { display:inline-flex; align-items:center; gap:3px; font-size:11.5px; font-weight:700; padding:2px 8px; border-radius:99px; }
    .pd-change-up     { background:#dcfce7; color:#15803d; }
    .pd-change-down   { background:#fee2e2; color:#b91c1c; }
    .pd-change-stable { background:#fef9c3; color:#a16207; }
    .dark .pd-change-up     { background:rgba(22,163,74,.18);  color:#4ade80; }
    .dark .pd-change-down   { background:rgba(239,68,68,.18);  color:#f87171; }
    .dark .pd-change-stable { background:rgba(202,138,4,.18);  color:#fbbf24; }

    /* ── Rankings ── */
    .pd-rank-list { padding:0 16px 16px; }
    .pd-rank-item { display:flex; align-items:center; gap:10px; padding:9px 6px; border-bottom:1px solid #f9fafb; }
    .dark .pd-rank-item { border-color:rgba(255,255,255,.04); }
    .pd-rank-item:last-child { border-bottom:none; }
    .pd-rank-num { width:22px; font-size:12px; font-weight:800; color:#9ca3af; flex-shrink:0; text-align:center; }
    .pd-rank-name { flex:1; min-width:0; font-size:13px; font-weight:600; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .dark .pd-rank-name { color:#f3f4f6; }
    .pd-rank-sub { font-size:11px; color:#9ca3af; }
    .pd-rank-pct { font-size:14px; font-weight:800; flex-shrink:0; }

    /* ── Indicator breakdown ── */
    .pd-ind-row { display:flex; align-items:center; gap:10px; padding:9px 16px; border-bottom:1px solid #f9fafb; }
    .dark .pd-ind-row { border-color:rgba(255,255,255,.04); }
    .pd-ind-row:last-child { border-bottom:none; }
    .pd-ind-code { background:#f3f4f6; color:#6b7280; font-size:9.5px; font-weight:700; font-family:monospace; padding:2px 5px; border-radius:4px; flex-shrink:0; }
    .dark .pd-ind-code { background:#1f2937; color:#9ca3af; }
    .pd-ind-name { flex:1; min-width:0; font-size:12.5px; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dark .pd-ind-name { color:#d1d5db; }
    .pd-ind-group { font-size:10px; color:#9ca3af; }

    /* Target line */
    .pd-target { display:inline-flex; align-items:center; gap:3px; font-size:10.5px; color:#6b7280; flex-shrink:0; }

    /* Category dot */
    .pd-cat { font-size:10px; font-weight:600; padding:1px 6px; border-radius:99px; flex-shrink:0; }
    .cat-outcome { background:#f3e8ff; color:#7c3aed; }
    .cat-output  { background:#dbeafe; color:#1d4ed8; }
    .cat-process { background:#fff7ed; color:#c2410c; }

    /* Empty state */
    .pd-empty { padding:48px 20px; text-align:center; color:#9ca3af; font-size:13px; }

    /* Spinner */
    .pd-loading { display:flex; align-items:center; justify-content:center; padding:40px; }

    /* Grid layouts */
    .pd-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; }
    @media(max-width:900px){ .pd-grid-2 { grid-template-columns:1fr; } }
</style>

    {{-- ── Filter bar ──────────────────────────────────────────────────────── --}}
    <div class="pd-filters">
        {{-- Label --}}
        <div style="display:flex;align-items:center;gap:6px;padding-bottom:2px;align-self:flex-end;flex-shrink:0;">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="#9ca3af"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.553.894l-4 2A1 1 0 016 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
            <span style="font-size:12px;font-weight:600;color:#9ca3af;">Filters</span>
        </div>

        <div class="pd-divider"></div>

        {{-- Report Type --}}
        <div class="pd-filter-wrap">
            <label class="pd-filter-label">Report Type</label>
            <select wire:model.live="filterReportTypeId" class="pd-select">
                <option value="">All Report Types</option>
                @foreach($filterOptions['reportTypes'] ?? [] as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <svg class="pd-filter-arrow" width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </div>

        {{-- Year --}}
        <div class="pd-filter-wrap">
            <label class="pd-filter-label">Year</label>
            <select wire:model.live="filterYear" class="pd-select" style="min-width:110px;">
                <option value="">All Years</option>
                @foreach($filterOptions['years'] ?? [] as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
            <svg class="pd-filter-arrow" width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </div>

        @if(auth()->user()->hasRole(['super_admin','admin','national_mentor']))
            {{-- County --}}
            <div class="pd-filter-wrap">
                <label class="pd-filter-label">County</label>
                <select wire:model.live="filterCountyId" class="pd-select">
                    <option value="">All Counties</option>
                    @foreach($filterOptions['counties'] ?? [] as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            <svg class="pd-filter-arrow" width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </div>
        @endif

        {{-- Reporting summary pill --}}
        <div style="margin-left:auto;align-self:flex-end;padding-bottom:2px;">
            <div style="display:inline-flex;align-items:center;gap:6px;background:#f0f7ff;border:1px solid #bfdbfe;border-radius:99px;padding:5px 12px;">
                <div style="width:7px;height:7px;border-radius:50%;background:#3b82f6;flex-shrink:0;"></div>
                <span style="font-size:12px;font-weight:700;color:#1d4ed8;">{{ $stats['facilities_reporting'] ?? 0 }}</span>
                <span style="font-size:12px;color:#3b82f6;">facilities reporting</span>
            </div>
        </div>
    </div>

    {{-- ── Headline stats ───────────────────────────────────────────────────── --}}
    <div class="pd-stats">
        {{-- Total validated reports --}}
        <div class="pd-stat">
            <div class="pd-stat-accent" style="background:linear-gradient(90deg,#3b82f6,#60a5fa);"></div>
            <div class="pd-stat-icon" style="background:#dbeafe;">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="#1d4ed8"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
            </div>
            <div class="pd-stat-val">{{ number_format($stats['total_reports'] ?? 0) }}</div>
            <div class="pd-stat-label">Validated Reports</div>
            <div class="pd-stat-sub" style="color:#3b82f6;">
                <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Across all facilities
            </div>
        </div>

        {{-- Facilities reporting --}}
        <div class="pd-stat">
            <div class="pd-stat-accent" style="background:linear-gradient(90deg,#8b5cf6,#a78bfa);"></div>
            <div class="pd-stat-icon" style="background:#ede9fe;">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="#7c3aed"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-5L9 4H4zm7 5a1 1 0 10-2 0v1H8a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd"/></svg>
            </div>
            <div class="pd-stat-val">{{ number_format($stats['facilities_reporting'] ?? 0) }}</div>
            <div class="pd-stat-label">Facilities Reporting</div>
            <div class="pd-stat-sub" style="color:#7c3aed;">With validated data</div>
        </div>

        {{-- Avg completion --}}
        <div class="pd-stat">
            <div class="pd-stat-accent" style="background:linear-gradient(90deg,
                {{ ($stats['avg_completion'] ?? 0) >= 80 ? '#22c55e,#4ade80' : (($stats['avg_completion'] ?? 0) >= 50 ? '#f59e0b,#fbbf24' : '#ef4444,#f87171') }});"></div>
            <div class="pd-stat-icon" style="background:{{ ($stats['avg_completion'] ?? 0) >= 80 ? '#dcfce7' : (($stats['avg_completion'] ?? 0) >= 50 ? '#fef9c3' : '#fee2e2') }};">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="{{ ($stats['avg_completion'] ?? 0) >= 80 ? '#15803d' : (($stats['avg_completion'] ?? 0) >= 50 ? '#ca8a04' : '#b91c1c') }}"><path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"/><path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"/></svg>
            </div>
            <div class="pd-stat-val" style="color:{{ ($stats['avg_completion'] ?? 0) >= 80 ? '#16a34a' : (($stats['avg_completion'] ?? 0) >= 50 ? '#d97706' : '#dc2626') }};">
                {{ $stats['avg_completion'] ?? 0 }}%
            </div>
            <div class="pd-stat-label">Avg Indicator Score</div>
            <div class="pd-stat-sub" style="color:#9ca3af;">Across validated reports</div>
        </div>

        {{-- Improving facilities --}}
        <div class="pd-stat">
            <div class="pd-stat-accent" style="background:linear-gradient(90deg,#22c55e,#4ade80);"></div>
            <div class="pd-stat-icon" style="background:#dcfce7;">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="#15803d"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/></svg>
            </div>
            <div class="pd-stat-val" style="color:#16a34a;">{{ number_format($stats['improving_facilities'] ?? 0) }}</div>
            <div class="pd-stat-label">Improving Facilities</div>
            <div class="pd-stat-sub" style="color:#16a34a;">Latest > earliest report</div>
        </div>

        {{-- Mentorships --}}
        <div class="pd-stat">
            <div class="pd-stat-accent" style="background:linear-gradient(90deg,#f59e0b,#fbbf24);"></div>
            <div class="pd-stat-icon" style="background:#fef9c3;">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="#ca8a04"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v1h8v-1zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-1a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v1h-3zM4.75 12.094A5.973 5.973 0 004 15v1H1v-1a3 3 0 013.75-2.906z"/></svg>
            </div>
            <div class="pd-stat-val" style="color:#d97706;">{{ number_format($stats['mentorship_count'] ?? 0) }}</div>
            <div class="pd-stat-label">Mentorship Sessions</div>
            <div class="pd-stat-sub" style="color:#d97706;">Linked to facilities</div>
        </div>
    </div>

    {{-- ── Tab navigation ───────────────────────────────────────────────────── --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div class="pd-tabs">
            <button class="pd-tab {{ $activeTab === 'overview' ? 'active' : '' }}" wire:click="setTab('overview')">
                Overview
            </button>
            <button class="pd-tab {{ $activeTab === 'facilities' ? 'active' : '' }}" wire:click="setTab('facilities')">
                Facility Trends
            </button>
            <button class="pd-tab {{ $activeTab === 'indicators' ? 'active' : '' }}" wire:click="setTab('indicators')">
                Indicator Breakdown
            </button>
            <button class="pd-tab {{ $activeTab === 'rankings' ? 'active' : '' }}" wire:click="setTab('rankings')">
                Rankings
            </button>
        </div>
        <div style="font-size:11.5px;color:#9ca3af;">
            Showing {{ $filterYear ? $filterYear : 'all years' }}
            @if($filterReportTypeId) · {{ collect($filterOptions['reportTypes'] ?? [])->get($filterReportTypeId) }} @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: OVERVIEW ───────────────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'overview')

        {{-- Trend chart --}}
        <div class="pd-card">
            <div class="pd-card-header">
                <div class="pd-card-title">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="#3b82f6"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11 4a1 1 0 10-2 0v4a1 1 0 102 0V7zm-3 1a1 1 0 10-2 0v3a1 1 0 102 0V8zM8 9a1 1 0 00-2 0v2a1 1 0 102 0V9z" clip-rule="evenodd"/></svg>
                    Average Indicator Score Over Time
                </div>
                <span style="font-size:11.5px;color:#9ca3af;">{{ count($trendData) }} periods</span>
            </div>
            @if(empty($trendData))
                <div class="pd-empty">No trend data available for the selected filters.</div>
            @else
                <div class="pd-chart">
                    <div class="pd-bars">
                        @foreach($trendData as $point)
                            @php
                                $h = max(4, round(($point['avg_pct'] ?? 0) * 1.8));
                                $col = ($point['avg_pct'] ?? 0) >= 80 ? '#22c55e' : (($point['avg_pct'] ?? 0) >= 50 ? '#3b82f6' : '#f59e0b');
                            @endphp
                            <div class="pd-bar-group">
                                <div class="pd-bar-val">{{ $point['avg_pct'] ?? '—' }}%</div>
                                <div class="pd-bar-track" style="height:160px;flex:1;width:100%;">
                                    <div class="pd-bar-fill" style="height:{{ $h }}px;background:{{ $col }};opacity:.85;"></div>
                                </div>
                                <div class="pd-bar-label">{{ $point['label'] }}</div>
                                <div class="pd-bar-tooltip" style="font-size:9.5px;color:#d1d5db;">{{ $point['facility_count'] }}f</div>
                            </div>
                        @endforeach
                    </div>
                    <div style="display:flex;align-items:center;gap:16px;margin-top:12px;padding-top:10px;border-top:1px solid #f3f4f6;">
                        <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#6b7280;">
                            <div style="width:10px;height:10px;border-radius:2px;background:#22c55e;"></div> ≥80% Good
                        </div>
                        <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#6b7280;">
                            <div style="width:10px;height:10px;border-radius:2px;background:#3b82f6;"></div> 50–79% Moderate
                        </div>
                        <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#6b7280;">
                            <div style="width:10px;height:10px;border-radius:2px;background:#f59e0b;"></div> &lt;50% Needs attention
                        </div>
                        <div style="font-size:11px;color:#9ca3af;margin-left:auto;">f = facilities contributing</div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Bottom: Top performers + quick facility table --}}
        <div class="pd-grid-2">
            {{-- Top 5 --}}
            <div class="pd-card">
                <div class="pd-card-header">
                    <div class="pd-card-title">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="#16a34a"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/></svg>
                        Top Performers
                    </div>
                </div>
                <div class="pd-rank-list">
                    @forelse($performers['top'] ?? [] as $i => $p)
                        <div class="pd-rank-item">
                            <div class="pd-rank-num" style="color:{{ $i === 0 ? '#f59e0b' : ($i === 1 ? '#9ca3af' : ($i === 2 ? '#cd7c32' : '#d1d5db')) }};">
                                {{ $i + 1 }}
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="pd-rank-name">{{ $p['facility_name'] }}</div>
                                <div class="pd-rank-sub">{{ $p['report_type'] }} · {{ $p['period_label'] }}</div>
                            </div>
                            <div class="pd-rank-pct" style="color:{{ ($p['avg_pct'] ?? 0) >= 80 ? '#16a34a' : (($p['avg_pct'] ?? 0) >= 50 ? '#d97706' : '#dc2626') }};">
                                {{ $p['avg_pct'] ?? '—' }}%
                            </div>
                        </div>
                    @empty
                        <div class="pd-empty">No data yet.</div>
                    @endforelse
                </div>
            </div>

            {{-- Needs attention (bottom) --}}
            <div class="pd-card">
                <div class="pd-card-header">
                    <div class="pd-card-title">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="#dc2626"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        Needs Attention
                    </div>
                </div>
                <div class="pd-rank-list">
                    @forelse($performers['bottom'] ?? [] as $i => $p)
                        <div class="pd-rank-item">
                            <div class="pd-rank-num" style="color:#d1d5db;">{{ $i + 1 }}</div>
                            <div style="flex:1;min-width:0;">
                                <div class="pd-rank-name">{{ $p['facility_name'] }}</div>
                                <div class="pd-rank-sub">{{ $p['report_type'] }} · {{ $p['period_label'] }}</div>
                            </div>
                            <div class="pd-rank-pct" style="color:#dc2626;">{{ $p['avg_pct'] ?? '—' }}%</div>
                        </div>
                    @empty
                        <div class="pd-empty">No data yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: FACILITY TRENDS ─────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'facilities')
        <div class="pd-card">
            <div class="pd-card-header">
                <div class="pd-card-title">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="#3b82f6"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                    Facility Progress — First vs Latest Report
                </div>
                <span style="font-size:11.5px;color:#9ca3af;">{{ count($facilityTrends) }} facility-report combinations</span>
            </div>
            @if(empty($facilityTrends))
                <div class="pd-empty">No facility trend data for the selected filters.</div>
            @else
                <div style="overflow-x:auto;">
                    <table class="pd-trend-table">
                        <thead>
                            <tr>
                                <th>Facility</th>
                                <th>Report Type</th>
                                <th>Reports</th>
                                <th>First Reading</th>
                                <th>Latest Reading</th>
                                <th>Change</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($facilityTrends as $ft)
                                @php
                                    $changeClass = match($ft['trend']) {
                                        'up'     => 'pd-change-up',
                                        'down'   => 'pd-change-down',
                                        default  => 'pd-change-stable',
                                    };
                                    $arrowClass = match($ft['trend']) {
                                        'up'    => 'pd-arrow-up',
                                        'down'  => 'pd-arrow-down',
                                        'stable'=> 'pd-arrow-stable',
                                        default => 'pd-arrow-unknown',
                                    };
                                    $latestPct = $ft['latest_pct'] ?? 0;
                                    $barColor  = $latestPct >= 80 ? '#22c55e' : ($latestPct >= 50 ? '#3b82f6' : '#f59e0b');
                                @endphp
                                <tr>
                                    <td>
                                        <div style="font-weight:600;font-size:13px;color:#111827;" class="dark:text-gray-100">
                                            {{ $ft['facility_name'] }}
                                        </div>
                                        @if($ft['mfl_code'])
                                            <div style="font-size:11px;color:#9ca3af;">MFL {{ $ft['mfl_code'] }}</div>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">
                                        <span style="font-size:11.5px;font-weight:600;color:#374151;" class="dark:text-gray-300">
                                            {{ $ft['report_type'] }}
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span style="font-size:13px;font-weight:700;color:#6b7280;">{{ $ft['period_count'] }}</span>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="font-size:15px;font-weight:800;color:#9ca3af;">
                                            {{ $ft['first_pct'] !== null ? $ft['first_pct'].'%' : '—' }}
                                        </div>
                                        <div style="font-size:10.5px;color:#d1d5db;">{{ $ft['first_label'] }}</div>
                                    </td>
                                    <td style="text-align:center;min-width:140px;">
                                        <div class="pd-mini-wrap">
                                            <div class="pd-mini-track">
                                                <div class="pd-mini-fill" style="width:{{ $latestPct }}%;background:{{ $barColor }};"></div>
                                            </div>
                                            <span style="font-size:14px;font-weight:800;color:{{ $barColor }};flex-shrink:0;min-width:40px;text-align:right;">
                                                {{ $ft['latest_pct'] !== null ? $ft['latest_pct'].'%' : '—' }}
                                            </span>
                                        </div>
                                        <div style="font-size:10.5px;color:#d1d5db;margin-top:2px;text-align:right;">{{ $ft['latest_label'] }}</div>
                                    </td>
                                    <td style="text-align:center;">
                                        @if($ft['change'] !== null)
                                            <span class="pd-change {{ $changeClass }}">
                                                {{ $ft['change'] > 0 ? '+' : '' }}{{ $ft['change'] }}%
                                            </span>
                                        @else
                                            <span style="color:#d1d5db;font-size:13px;">—</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="{{ $arrowClass }}" style="font-size:18px;font-weight:800;">
                                            @if($ft['trend'] === 'up') ↑
                                            @elseif($ft['trend'] === 'down') ↓
                                            @elseif($ft['trend'] === 'stable') →
                                            @else —
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Summary row --}}
                @php
                    $upCount     = collect($facilityTrends)->where('trend','up')->count();
                    $downCount   = collect($facilityTrends)->where('trend','down')->count();
                    $stableCount = collect($facilityTrends)->where('trend','stable')->count();
                @endphp
                <div style="padding:12px 16px;border-top:1px solid #f3f4f6;display:flex;gap:20px;font-size:12.5px;" class="dark:border-gray-800">
                    <span style="color:#16a34a;font-weight:600;">↑ {{ $upCount }} improving</span>
                    <span style="color:#dc2626;font-weight:600;">↓ {{ $downCount }} declining</span>
                    <span style="color:#d97706;font-weight:600;">→ {{ $stableCount }} stable</span>
                </div>
            @endif
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: INDICATOR BREAKDOWN ─────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'indicators')
        <div class="pd-card">
            <div class="pd-card-header">
                <div class="pd-card-title">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="#7c3aed"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                    Per-Indicator Average Score (All Facilities)
                </div>
                <span style="font-size:11.5px;color:#9ca3af;">{{ count($indicatorBreakdown) }} indicators</span>
            </div>
            @php $currentGroup = null; @endphp
            @forelse($indicatorBreakdown as $ind)
                @if($currentGroup !== $ind['group_name'])
                    @php $currentGroup = $ind['group_name']; @endphp
                    <div style="padding:8px 16px 6px;background:#f8fafc;border-top:1px solid #f3f4f6;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6b7280;" class="dark:bg-gray-800/30 dark:border-gray-800 dark:text-gray-500">
                        {{ $ind['group_name'] }}
                    </div>
                @endif
                @php
                    $pct = $ind['avg_pct'] ?? 0;
                    $barColor = $pct >= 80 ? '#22c55e' : ($pct >= 50 ? '#3b82f6' : '#f59e0b');
                    $catClass = match($ind['category']) { 'outcome' => 'cat-outcome', 'output' => 'cat-output', default => 'cat-process' };
                    $barWidth = min(100, max(0, $pct));
                @endphp
                <div class="pd-ind-row">
                    <span class="pd-ind-code">{{ $ind['code'] }}</span>
                    <div style="flex:1;min-width:0;">
                        <div class="pd-ind-name" title="{{ $ind['name'] }}">{{ $ind['name'] }}</div>
                        <div class="pd-ind-group">{{ $ind['facility_count'] }} facilities · {{ $ind['data_points'] }} data points</div>
                    </div>
                    <span class="pd-cat {{ $catClass }}">{{ $ind['category'] }}</span>

                    {{-- Range --}}
                    <div style="text-align:center;flex-shrink:0;min-width:90px;">
                        <div style="font-size:10px;color:#9ca3af;">{{ $ind['min_pct'] }}% – {{ $ind['max_pct'] }}%</div>
                        <div style="font-size:10px;color:#d1d5db;">range</div>
                    </div>

                    {{-- Target vs actual --}}
                    @if($ind['target_value'])
                        <div class="pd-target">
                            <svg width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Target {{ $ind['target_value'] }}%
                        </div>
                    @endif

                    {{-- Score bar + value --}}
                    <div style="display:flex;align-items:center;gap:7px;flex-shrink:0;min-width:130px;">
                        <div style="flex:1;height:7px;background:#e5e7eb;border-radius:99px;overflow:hidden;" class="dark:bg-gray-700">
                            <div style="height:100%;width:{{ $barWidth }}%;background:{{ $barColor }};border-radius:99px;"></div>
                        </div>
                        <span style="font-size:14px;font-weight:800;color:{{ $barColor }};min-width:38px;text-align:right;">{{ $pct }}%</span>
                    </div>
                </div>
            @empty
                <div class="pd-empty">No indicator data for the selected filters.</div>
            @endforelse
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: RANKINGS ────────────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'rankings')
        <div class="pd-grid-2">
            {{-- Top performers full list --}}
            <div class="pd-card">
                <div class="pd-card-header">
                    <div class="pd-card-title" style="color:#16a34a;">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/></svg>
                        Highest Scoring Facilities
                    </div>
                </div>
                <div class="pd-rank-list">
                    @forelse($performers['top'] ?? [] as $i => $p)
                        <div class="pd-rank-item">
                            <div class="pd-rank-num" style="font-size:{{ $i < 3 ? '14px' : '12px' }};color:{{ $i === 0 ? '#f59e0b' : ($i === 1 ? '#9ca3af' : ($i === 2 ? '#cd7c32' : '#e5e7eb')) }};">
                                @if($i === 0) 🥇
                                @elseif($i === 1) 🥈
                                @elseif($i === 2) 🥉
                                @else {{ $i + 1 }}
                                @endif
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="pd-rank-name">{{ $p['facility_name'] }}</div>
                                <div class="pd-rank-sub">{{ $p['report_type'] }} · {{ $p['period_label'] }}</div>
                            </div>
                            <div style="display:flex;align-items:center;gap:7px;">
                                <div style="width:60px;height:5px;background:#e5e7eb;border-radius:99px;overflow:hidden;" class="dark:bg-gray-700">
                                    <div style="height:100%;width:{{ min(100, $p['avg_pct'] ?? 0) }}%;background:#22c55e;border-radius:99px;"></div>
                                </div>
                                <span class="pd-rank-pct" style="color:#16a34a;">{{ $p['avg_pct'] ?? '—' }}%</span>
                            </div>
                        </div>
                    @empty
                        <div class="pd-empty">No data yet.</div>
                    @endforelse
                </div>
            </div>

            {{-- Bottom performers --}}
            <div class="pd-card">
                <div class="pd-card-header">
                    <div class="pd-card-title" style="color:#dc2626;">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clip-rule="evenodd"/></svg>
                        Facilities Needing Support
                    </div>
                </div>
                <div class="pd-rank-list">
                    @forelse($performers['bottom'] ?? [] as $i => $p)
                        <div class="pd-rank-item">
                            <div class="pd-rank-num" style="color:#fca5a5;font-size:12px;">{{ $i + 1 }}</div>
                            <div style="flex:1;min-width:0;">
                                <div class="pd-rank-name">{{ $p['facility_name'] }}</div>
                                <div class="pd-rank-sub">{{ $p['report_type'] }} · {{ $p['period_label'] }}</div>
                            </div>
                            <div style="display:flex;align-items:center;gap:7px;">
                                <div style="width:60px;height:5px;background:#e5e7eb;border-radius:99px;overflow:hidden;" class="dark:bg-gray-700">
                                    <div style="height:100%;width:{{ min(100, $p['avg_pct'] ?? 0) }}%;background:#ef4444;border-radius:99px;"></div>
                                </div>
                                <span class="pd-rank-pct" style="color:#dc2626;">{{ $p['avg_pct'] ?? '—' }}%</span>
                            </div>
                        </div>
                    @empty
                        <div class="pd-empty">No data yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>