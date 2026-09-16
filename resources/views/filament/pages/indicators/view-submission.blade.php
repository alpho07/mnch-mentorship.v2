<x-filament-panels::page>

    <style>
        .vs-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .dark .vs-card {
            background: #111827;
            border-color: #1f2937;
        }

        /* ── Hero ── */
        .vs-hero {
            border-radius: 16px;
            padding: 26px 28px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
            box-shadow: 0 6px 24px rgba(0,0,0,.14);
        }
        @media (max-width: 768px) {
            .vs-hero {
                grid-template-columns: auto 1fr;
            }
            .vs-hero-meta {
                display: none !important;
            }
        }
        .vs-hero-validated {
            background: linear-gradient(135deg, #052e16 0%, #166534 100%);
        }
        .vs-hero-submitted {
            background: linear-gradient(135deg, #0c1a4e 0%, #1d4ed8 100%);
        }
        .vs-hero-rejected  {
            background: linear-gradient(135deg, #450a0a 0%, #b91c1c 100%);
        }
        .vs-hero-pushed    {
            background: linear-gradient(135deg, #2e1065 0%, #5b21b6 100%);
        }
        .vs-hero-draft     {
            background: linear-gradient(135deg, #422006 0%, #b45309 100%);
        }

        .vs-hero-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 0 0 8px rgba(255,255,255,.06);
        }
        .vs-hero-title {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
        }
        .vs-hero-breadcrumb {
            font-size: 12.5px;
            color: rgba(255,255,255,.65);
            margin-top: 5px;
        }
        .vs-hero-note {
            margin-top: 10px;
            font-size: 12.5px;
            color: rgba(255,255,255,.75);
            font-style: italic;
            display: flex;
            align-items: flex-start;
            gap: 6px;
            background: rgba(255,255,255,.08);
            border-radius: 8px;
            padding: 8px 12px;
        }
        .vs-hero-meta {
            border-left: 1px solid rgba(255,255,255,.15);
            padding-left: 22px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 200px;
        }
        .vs-meta-row {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            color: rgba(255,255,255,.6);
        }
        .vs-meta-val {
            color: #fff;
            font-weight: 600;
        }
        .vs-ring-wrap {
            position: relative;
            width: 52px;
            height: 52px;
            flex-shrink: 0;
        }
        .vs-ring-label {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }

        /* ── Rejection / notes ── */
        .vs-alert {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 13px;
        }
        .vs-alert-rejection {
            background: #fef2f2;
            border: 1.5px solid #fca5a5;
            color: #991b1b;
        }
        .vs-alert-note      {
            background: #eff6ff;
            border: 1.5px solid #bfdbfe;
            color: #1e40af;
        }
        .dark .vs-alert-rejection {
            background: rgba(239,68,68,.08);
            border-color: rgba(239,68,68,.3);
            color: #fca5a5;
        }
        .dark .vs-alert-note      {
            background: rgba(59,130,246,.06);
            border-color: rgba(59,130,246,.25);
            color: #93c5fd;
        }

        /* ── Module accordion ── */
        .vs-mod-header {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            user-select: none;
            transition: background .12s;
        }
        .vs-mod-header:hover {
            background: #fafafa;
        }
        .dark .vs-mod-header:hover {
            background: rgba(255,255,255,.025);
        }
        .vs-mod-num {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
        }
        .mod-done    {
            background: #dcfce7;
            color: #15803d;
        }
        .mod-partial {
            background: #fef9c3;
            color: #a16207;
        }
        .mod-empty   {
            background: #f3f4f6;
            color: #9ca3af;
        }
        .dark .mod-done    {
            background: rgba(22,163,74,.2);
            color: #4ade80;
        }
        .dark .mod-partial {
            background: rgba(202,138,4,.2);
            color: #fbbf24;
        }
        .dark .mod-empty   {
            background: rgba(255,255,255,.07);
            color: #6b7280;
        }
        .vs-mod-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #111827;
            flex: 1;
        }
        .dark .vs-mod-name {
            color: #f3f4f6;
        }
        .vs-mod-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .vs-mini-track {
            width: 72px;
            height: 5px;
            background: #e5e7eb;
            border-radius: 99px;
            overflow: hidden;
        }
        .dark .vs-mini-track {
            background: #1f2937;
        }
        .vs-mini-fill {
            height: 100%;
            border-radius: 99px;
        }
        .vs-chevron {
            transition: transform .2s;
            flex-shrink: 0;
        }

        /* ── Table header ── */
        .vs-thead {
            display: grid;
            grid-template-columns: 1fr 90px 90px 72px;
            border-top: 1px solid #f3f4f6;
            border-bottom: 1px solid #f3f4f6;
            background: #f8fafc;
        }
        .dark .vs-thead {
            background: rgba(255,255,255,.025);
            border-color: #1f2937;
        }
        .vs-th {
            padding: 7px 14px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: #9ca3af;
        }
        .vs-th-c {
            text-align: center;
        }

        /* ── Indicator rows ── */
        .vs-row {
            display: grid;
            grid-template-columns: 1fr 90px 90px 72px;
            border-top: 1px solid #f9fafb;
            transition: background .1s;
        }
        .dark .vs-row {
            border-color: rgba(255,255,255,.035);
        }
        .vs-row:hover {
            background: #fafafa;
        }
        .dark .vs-row:hover {
            background: rgba(255,255,255,.018);
        }
        .vs-cell {
            padding: 10px 14px;
            display: flex;
            align-items: center;
        }
        .vs-cell-c {
            justify-content: center;
            text-align: center;
        }
        .vs-cell-name {
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }

        .vs-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2px;
        }
        .vs-dot-filled {
            background: #dcfce7;
        }
        .vs-dot-empty  {
            background: #f3f4f6;
        }
        .dark .vs-dot-filled {
            background: rgba(22,163,74,.2);
        }
        .dark .vs-dot-empty  {
            background: rgba(255,255,255,.06);
        }

        .vs-code {
            background: #f3f4f6;
            color: #6b7280;
            font-size: 10px;
            font-weight: 700;
            font-family: monospace;
            padding: 1px 5px;
            border-radius: 4px;
            margin-right: 5px;
            flex-shrink: 0;
        }
        .dark .vs-code {
            background: #1f2937;
            color: #9ca3af;
        }
        .vs-name {
            font-size: 12.5px;
            font-weight: 500;
            color: #374151;
            line-height: 1.35;
        }
        .dark .vs-name {
            color: #d1d5db;
        }
        .vs-comment {
            font-size: 11px;
            color: #9ca3af;
            font-style: italic;
        }
        .vs-source  {
            font-size: 10.5px;
            color: #c4c9d4;
        }

        .vs-val {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
        }
        .dark .vs-val {
            color: #f3f4f6;
        }
        .vs-empty {
            font-size: 13px;
            color: #d1d5db;
        }
        .dark .vs-empty {
            color: #2d3748;
        }

        .vs-pct {
            font-size: 13.5px;
            font-weight: 800;
        }
        .pct-good {
            color: #16a34a;
        }
        .pct-mid  {
            color: #d97706;
        }
        .pct-low  {
            color: #dc2626;
        }
        .pct-na   {
            color: #d1d5db;
        }
        .dark .pct-na {
            color: #2d3748;
        }

        .vs-cat {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            padding: 2px 7px;
            border-radius: 99px;
            flex-shrink: 0;
        }
        .cat-outcome {
            background:#f3e8ff;
            color:#7c3aed;
        }
        .cat-output  {
            background:#dbeafe;
            color:#1d4ed8;
        }
        .cat-process {
            background:#fff7ed;
            color:#c2410c;
        }
        .cat-other   {
            background:#f3f4f6;
            color:#6b7280;
        }
        .dark .cat-outcome {
            background:rgba(124,58,237,.15);
            color:#c4b5fd;
        }
        .dark .cat-output  {
            background:rgba(29,78,216,.15);
            color:#93c5fd;
        }
        .dark .cat-process {
            background:rgba(194,65,12,.15);
            color:#fdba74;
        }

        /* Band rows */
        .vs-band-hdr {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: #f8fafc;
            border-top: 1px solid #f3f4f6;
        }
        .dark .vs-band-hdr {
            background: rgba(255,255,255,.025);
            border-color: #1f2937;
        }
        .vs-band-hdr-name {
            font-size: 12px;
            font-weight: 700;
            color: #4b5563;
        }
        .dark .vs-band-hdr-name {
            color: #9ca3af;
        }
        .vs-band-row {
            display: grid;
            grid-template-columns: 1fr 90px 90px 72px;
            border-top: 1px solid #f9fafb;
        }
        .dark .vs-band-row {
            border-color: rgba(255,255,255,.03);
        }
        .vs-band-row:hover {
            background: #fafafa;
        }
        .dark .vs-band-row:hover {
            background: rgba(255,255,255,.018);
        }
        .vs-band-label {
            padding: 8px 14px 8px 30px;
            font-size: 12.5px;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .dark .vs-band-label {
            color: #9ca3af;
        }

        /* Validator bar */
        .vs-validator-bar {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .dark .vs-validator-bar {
            background: #111827;
            border-color: #1f2937;
        }
        .vs-validator-title {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .dark .vs-validator-title {
            color: #f3f4f6;
        }
        .vs-validator-desc  {
            font-size: 12.5px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* DHIS2 */
        .vs-dhis2-pre {
            font-size: 12px;
            background: #0f172a;
            color: #94a3b8;
            border-radius: 10px;
            padding: 18px;
            overflow: auto;
            font-family: 'JetBrains Mono','Fira Code',monospace;
            line-height: 1.7;
            max-height: 320px;
        }
    </style>

    @php
        $isValidated = $period->isValidated();
        $isPushed    = $period->isPushed();
        $isRejected  = $period->isRejected();
        $isSubmitted = $period->isSubmitted();
        $pct         = $overallCompletion['percentage'];
        $pctColor    = $pct === 100 ? '#22c55e' : ($pct >= 50 ? '#3b82f6' : '#f59e0b');

        $heroClass = match(true) {
            $isPushed    => 'vs-hero-pushed',
            $isValidated => 'vs-hero-validated',
            $isRejected  => 'vs-hero-rejected',
            $isSubmitted => 'vs-hero-submitted',
            default      => 'vs-hero-draft',
        };
        $statusLabel = match(true) {
            $isPushed    => 'Pushed to DHIS2',
            $isValidated => 'Report Validated',
            $isRejected  => 'Report Rejected',
            $isSubmitted => 'Awaiting Validation',
            default      => 'Draft Report',
        };
        $r = 22; $circ = round(2*M_PI*$r, 2); $dash = round($circ*$pct/100, 2);
    @endphp

    {{-- ── Hero banner ─────────────────────────────────────────────────────── --}}
    <div class="vs-hero {{ $heroClass }}">
        <div class="vs-hero-icon">
            @if($isValidated || $isPushed)
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            @elseif($isRejected)
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            @elseif($isSubmitted)
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            @else
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            @endif
        </div>

        <div>
            <div class="vs-hero-title">{{ $statusLabel }}</div>
            <div class="vs-hero-breadcrumb">
                {{ $period->reportType?->name }} &nbsp;·&nbsp; {{ $period->period_label }} &nbsp;·&nbsp; {{ $period->facility?->name }}
            </div>
            @if($period->notes)
                <div class="vs-hero-note">
                    <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0;margin-top:1px;opacity:.7;"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    {{ $period->notes }}
                </div>
            @endif
        </div>

        <div class="vs-hero-meta">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px;">
                <div class="vs-ring-wrap">
                    <svg width="52" height="52" viewBox="0 0 52 52">
                    <circle cx="26" cy="26" r="{{ $r }}" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="5"/>
                    <circle cx="26" cy="26" r="{{ $r }}" fill="none" stroke="{{ $pctColor }}" stroke-width="5"
                            stroke-dasharray="{{ $dash }} {{ $circ }}"
                            stroke-dashoffset="{{ $circ/4 }}"
                            stroke-linecap="round"/>
                    </svg>
                    <div class="vs-ring-label">
                        <span style="font-size:10px;font-weight:800;">{{ $pct }}%</span>
                    </div>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:700;color:#fff;">{{ $pct }}% Complete</div>
                    <div style="font-size:11px;color:rgba(255,255,255,.55);">{{ $overallCompletion['filled'] }}/{{ $overallCompletion['total'] }} indicators</div>
                </div>
            </div>
            @if($period->submitted_at)
                <div class="vs-meta-row">
                    <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor" style="opacity:.55;flex-shrink:0;"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                    Submitted by <span class="vs-meta-val">{{ $period->submittedByUser?->name ?? '—' }}</span>
                </div>
                <div class="vs-meta-row">
                    <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor" style="opacity:.55;flex-shrink:0;"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                    <span class="vs-meta-val">{{ $period->submitted_at->format('d M Y, H:i') }}</span>
                </div>
            @endif
            @if($period->validated_at)
                <div class="vs-meta-row">
                    <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor" style="opacity:.55;flex-shrink:0;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Validated by <span class="vs-meta-val">{{ $period->validatedByUser?->name ?? '—' }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Rejection reason ─────────────────────────────────────────────────── --}}
    @if($isRejected && $period->rejection_reason)
        <div class="vs-alert vs-alert-rejection">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0;margin-top:1px;">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div><strong>Rejection Reason: </strong>{{ $period->rejection_reason }}</div>
        </div>
    @endif

    {{-- ── Validator actions ────────────────────────────────────────────────── --}}
    @if($isValidator && $isSubmitted)
        <div class="vs-validator-bar">
            <div>
                <div class="vs-validator-title">Validate this Report</div>
                <div class="vs-validator-desc">Review the indicator data below, then approve or return for corrections.</div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <x-filament::button color="danger" wire:click="mountAction('reject')" icon="heroicon-o-x-circle" size="sm">
                Reject
                </x-filament::button>
                <x-filament::button color="success" wire:click="mountAction('validate')" icon="heroicon-o-check-circle">
                Validate Report
                </x-filament::button>
            </div>
        </div>
    @endif

    {{-- ── Modules ──────────────────────────────────────────────────────────── --}}
    @foreach($groups as $gi => $group)
        @php
            $gs = $groupStats[$gi] ?? ['filled'=>0,'total'=>0,'percentage'=>0,'complete'=>false];
            $modPctColor  = $gs['complete'] ? '#16a34a' : ($gs['percentage'] > 0 ? '#3b82f6' : '#9ca3af');
            $modFillColor = $gs['complete'] ? '#22c55e' : ($gs['percentage'] > 0 ? '#3b82f6' : '#e5e7eb');
            $numClass = $gs['complete'] ? 'mod-done' : ($gs['filled'] > 0 ? 'mod-partial' : 'mod-empty');
        @endphp
        <div class="vs-card" x-data="{ open: {{ $gi < 2 ? 'true' : 'false' }} }">
            <div class="vs-mod-header" @click="open = !open">
                <span class="vs-mod-num {{ $numClass }}">
                    @if($gs['complete'])
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @else
                        {{ $gi + 1 }}
                    @endif
                </span>
                <span class="vs-mod-name">{{ $group['name'] }}</span>
                <div class="vs-mod-right">
                    <div class="vs-mini-track">
                        <div class="vs-mini-fill" style="width:{{ $gs['percentage'] }}%;background:{{ $modFillColor }};"></div>
                    </div>
                    <span style="font-size:12.5px;font-weight:800;color:{{ $modPctColor }};">{{ $gs['percentage'] }}%</span>
                    <span style="font-size:11px;color:#9ca3af;">{{ $gs['filled'] }}/{{ $gs['total'] }}</span>
                    <svg class="vs-chevron" width="16" height="16" viewBox="0 0 20 20" fill="#9ca3af"
                     :style="open ? 'transform:rotate(180deg)' : ''">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>

            <div x-show="open" x-transition x-cloak>
                @if(empty($group['indicators']))
                    <div style="padding:14px 20px;font-size:12.5px;color:#9ca3af;font-style:italic;border-top:1px solid #f3f4f6;">
                No indicators configured for this module.
                    </div>
                @else
                    <div class="vs-thead">
                        <div class="vs-th">Indicator</div>
                        <div class="vs-th vs-th-c">Numerator</div>
                        <div class="vs-th vs-th-c">Denominator</div>
                        <div class="vs-th vs-th-c">%</div>
                    </div>

                    @foreach($group['indicators'] as $ind)
                        @if(empty($ind['children']))
                            @php
                                $v    = $existingValues[$ind['id']] ?? null;
                                $p2   = $v['computed_percentage'] ?? null;
                                $pc   = $p2 === null ? 'pct-na' : ($p2 >= 80 ? 'pct-good' : ($p2 >= 50 ? 'pct-mid' : 'pct-low'));
                                $filled = $v && ($v['numerator'] !== null || $v['denominator'] !== null || $v['count'] !== null || $v['yes_no'] !== null);
                                $type = $ind['indicator_type'];
                                $catCls = match($ind['category'] ?? '') {
                                    'outcome' => 'cat-outcome', 'output' => 'cat-output',
                                    'process' => 'cat-process', default => 'cat-other',
                                };
                            @endphp
                            <div class="vs-row">
                                <div class="vs-cell vs-cell-name">
                                    <div style="display:flex;align-items:flex-start;gap:7px;width:100%;">
                                        <div class="vs-dot {{ $filled ? 'vs-dot-filled' : 'vs-dot-empty' }}">
                                            @if($filled)
                                                <svg width="7" height="7" viewBox="0 0 10 10" fill="none"><path d="M1.5 5l2.5 2.5 4.5-4.5" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            @endif
                                        </div>
                                        <div style="flex:1;min-width:0;">
                                            <div class="vs-name">
                                                <span class="vs-code">{{ $ind['code'] }}</span>{{ $ind['name'] }}
                                            </div>
                                            @if($ind['source_document'])
                                                <div class="vs-source">{{ $ind['source_document'] }}</div>
                                            @endif
                                            @if($v['comment'] ?? null)
                                                <div class="vs-comment">Note: {{ $v['comment'] }}</div>
                                            @endif
                                        </div>
                                        <span class="vs-cat {{ $catCls }}">{{ $ind['category'] ?? '' }}</span>
                                    </div>
                                </div>

                                <div class="vs-cell vs-cell-c">
                                    @if($type === 'yes_no')
                                        @php $yn = $v['yes_no'] ?? null; @endphp
                                        <span style="font-size:13px;font-weight:700;color:{{ $yn === null ? '#d1d5db' : ($yn ? '#16a34a' : '#dc2626') }};">
                                            {{ $yn === null ? '—' : ($yn ? 'Yes' : 'No') }}
                                        </span>
                                    @elseif($type === 'count')
                                        <span class="{{ ($v['count'] ?? null) !== null ? 'vs-val' : 'vs-empty' }}">{{ $v['count'] ?? '—' }}</span>
                                    @else
                                        <span class="{{ ($v['numerator'] ?? null) !== null ? 'vs-val' : 'vs-empty' }}">{{ $v['numerator'] ?? '—' }}</span>
                                    @endif
                                </div>

                                <div class="vs-cell vs-cell-c">
                                    <span class="{{ ($v['denominator'] ?? null) !== null ? 'vs-val' : 'vs-empty' }}">{{ $v['denominator'] ?? '—' }}</span>
                                </div>

                                <div class="vs-cell vs-cell-c">
                                    <span class="vs-pct {{ $pc }}">{{ $p2 !== null ? round($p2,1).'%' : '—' }}</span>
                                </div>
                            </div>

                        @else
                            <div class="vs-band-hdr">
                                <span class="vs-code">{{ $ind['code'] }}</span>
                                <span class="vs-band-hdr-name">{{ $ind['name'] }}</span>
                            </div>
                            @foreach($ind['children'] as $child)
                                @php
                                    $cv  = $existingValues[$child['id']] ?? null;
                                    $cp  = $cv['computed_percentage'] ?? null;
                                    $cpc = $cp === null ? 'pct-na' : ($cp >= 80 ? 'pct-good' : ($cp >= 50 ? 'pct-mid' : 'pct-low'));
                                @endphp
                                <div class="vs-band-row">
                                    <div class="vs-band-label">
                                        <svg width="11" height="11" viewBox="0 0 20 20" fill="#d1d5db" style="flex-shrink:0;"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                                        {{ $child['short_name'] ?? $child['name'] }}
                                    </div>
                                    <div class="vs-cell vs-cell-c"><span class="{{ ($cv['numerator'] ?? null) !== null ? 'vs-val' : 'vs-empty' }}">{{ $cv['numerator'] ?? '—' }}</span></div>
                                    <div class="vs-cell vs-cell-c"><span class="{{ ($cv['denominator'] ?? null) !== null ? 'vs-val' : 'vs-empty' }}</span>{{ $cv['denominator'] ?? '—' }}</span></div>
                                        <div class="vs-cell vs-cell-c"><span class="vs-pct {{ $cpc }}">{{ $cp !== null ? round($cp,1).'%' : '—' }}</span></div>
            </div>
                            @endforeach
                        @endif
                    @endforeach
                @endif
        </div>
    </div>
    @endforeach

    {{-- ── DHIS2 summary ────────────────────────────────────────────────────── --}}
    @if($period->dhis2_import_summary)
    <div class="vs-card">
        <div style="padding:14px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:9px;">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="#6366f1"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            <span style="font-size:13px;font-weight:700;color:#111827;" class="dark:text-gray-100">DHIS2 Import Summary</span>
                @if($period->dhis2_push_at)
            <span style="font-size:11px;padding:2px 9px;border-radius:99px;background:#e0e7ff;color:#3730a3;font-weight:600;margin-left:4px;">
                        {{ $period->dhis2_push_at->format('d M Y H:i') }}
            </span>
                @endif
        </div>
        <div style="padding:16px 20px;">
                                        <pre class="vs-dhis2-pre">{{ json_encode($period->dhis2_import_summary, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            </div>
                        @endif

</x-filament-panels::page>