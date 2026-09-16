<x-filament-panels::page>

    <style>
        .fr-layout {
            display: grid;
            grid-template-columns: 272px 1fr;
            gap: 1.25rem;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .fr-layout {
                grid-template-columns: 1fr;
            }
        }

        /* ── Sidebar ── */
        .fr-sidebar {
            position: sticky;
            top: 1.5rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .dark .fr-sidebar {
            background: #111827;
            border-color: #1f2937;
        }

        .fr-sidebar-header {
            padding: 13px 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .dark .fr-sidebar-header {
            border-color: #1f2937;
        }

        .fr-module-btn {
            width: 100%;
            text-align: left;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-left: 3px solid transparent;
            transition: background .12s, border-color .12s;
        }
        .fr-module-btn + .fr-module-btn {
            border-top: 1px solid #f9fafb;
        }
        .dark .fr-module-btn + .fr-module-btn {
            border-top-color: rgba(255,255,255,.04);
        }
        .fr-module-btn:hover {
            background: #f9fafb;
        }
        .dark .fr-module-btn:hover {
            background: rgba(255,255,255,.03);
        }
        .fr-module-btn.active {
            background: #eff6ff;
            border-left-color: #3b82f6;
        }
        .dark .fr-module-btn.active {
            background: rgba(59,130,246,.09);
            border-left-color: #60a5fa;
        }

        .fr-dot {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .fr-dot.done    {
            background: #dcfce7;
            color: #16a34a;
        }
        .fr-dot.partial {
            background: #fef9c3;
            color: #ca8a04;
        }
        .fr-dot.empty   {
            background: #f3f4f6;
            color: #9ca3af;
        }
        .dark .fr-dot.done    {
            background: rgba(22,163,74,.18);
            color: #4ade80;
        }
        .dark .fr-dot.partial {
            background: rgba(202,138,4,.18);
            color: #fbbf24;
        }
        .dark .fr-dot.empty   {
            background: rgba(255,255,255,.05);
            color: #6b7280;
        }

        .fr-module-label {
            flex: 1;
            min-width: 0;
        }
        .fr-module-name {
            font-size: 12.5px;
            font-weight: 500;
            color: #374151;
            line-height: 1.35;
        }
        .fr-module-btn.active .fr-module-name {
            color: #1d4ed8;
            font-weight: 600;
        }
        .dark .fr-module-name {
            color: #d1d5db;
        }
        .dark .fr-module-btn.active .fr-module-name {
            color: #93c5fd;
        }
        .fr-module-count {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 1px;
        }

        /* ── Progress card ── */
        .fr-progress-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
            margin-bottom: 1.25rem;
        }
        .dark .fr-progress-card {
            background: #111827;
            border-color: #1f2937;
        }
        .fr-pct-track {
            height: 7px;
            background: #e5e7eb;
            border-radius: 99px;
            margin-top: 10px;
            overflow: hidden;
        }
        .dark .fr-pct-track {
            background: #1f2937;
        }
        .fr-pct-fill {
            height: 100%;
            border-radius: 99px;
            transition: width .5s ease;
        }

        /* ── Content card ── */
        .fr-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .dark .fr-card {
            background: #111827;
            border-color: #1f2937;
        }

        .fr-card-header {
            padding: 18px 22px 16px;
            border-bottom: 1px solid #f3f4f6;
            background: #f8faff;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .dark .fr-card-header {
            background: rgba(59,130,246,.04);
            border-color: #1f2937;
        }
        .fr-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        .dark .fr-card-title {
            color: #f9fafb;
        }
        .fr-card-desc {
            font-size: 12.5px;
            color: #6b7280;
            margin-top: 3px;
        }

        /* ── Indicator row ── */
        .fr-ind {
            padding: 18px 22px;
            border-bottom: 1px solid #f3f4f6;
            transition: background .12s;
        }
        .dark .fr-ind {
            border-color: rgba(255,255,255,.04);
        }
        .fr-ind:last-child {
            border-bottom: none;
        }
        .fr-ind:hover {
            background: #fafafa;
        }
        .dark .fr-ind:hover {
            background: rgba(255,255,255,.015);
        }
        .fr-ind.error-bg {
            background: #fef2f2 !important;
        }
        .dark .fr-ind.error-bg {
            background: rgba(239,68,68,.06) !important;
        }

        .fr-ind-header {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 14px;
        }
        .fr-status-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 3px;
        }
        .fr-status-dot.filled {
            background: #dcfce7;
            color: #16a34a;
        }
        .fr-status-dot.empty  {
            background: #f3f4f6;
            color: #d1d5db;
        }
        .dark .fr-status-dot.filled {
            background: rgba(22,163,74,.2);
            color: #4ade80;
        }
        .dark .fr-status-dot.empty  {
            background: rgba(255,255,255,.05);
            color: #374151;
        }

        .fr-ind-body {
            flex: 1;
            min-width: 0;
        }
        .fr-ind-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
        }
        .fr-ind-name {
            font-size: 13.5px;
            font-weight: 600;
            color: #111827;
            line-height: 1.45;
        }
        .dark .fr-ind-name {
            color: #f3f4f6;
        }

        .fr-code {
            display: inline-flex;
            background: #f3f4f6;
            color: #6b7280;
            font-size: 10.5px;
            font-weight: 700;
            font-family: monospace;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 5px;
            flex-shrink: 0;
        }
        .dark .fr-code {
            background: #1f2937;
            color: #9ca3af;
        }

        .fr-cat {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 99px;
            flex-shrink: 0;
        }
        .fr-cat-outcome {
            background:#f3e8ff;
            color:#7c3aed;
        }
        .fr-cat-output  {
            background:#dbeafe;
            color:#1d4ed8;
        }
        .fr-cat-process {
            background:#fff7ed;
            color:#c2410c;
        }
        .fr-cat-other   {
            background:#f3f4f6;
            color:#6b7280;
        }
        .dark .fr-cat-outcome {
            background:rgba(124,58,237,.15);
            color:#c4b5fd;
        }
        .dark .fr-cat-output  {
            background:rgba(29,78,216,.15);
            color:#93c5fd;
        }
        .dark .fr-cat-process {
            background:rgba(194,65,12,.15);
            color:#fdba74;
        }

        .fr-meta-row {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            color: #9ca3af;
            margin-top: 4px;
        }
        .fr-hint-row {
            display: flex;
            align-items: flex-start;
            gap: 5px;
            font-size: 12px;
            color: #3b82f6;
            margin-top: 4px;
            font-style: italic;
        }
        .dark .fr-hint-row {
            color: #60a5fa;
        }

        /* Input grid */
        .fr-input-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 88px;
            gap: 10px;
            align-items: end;
        }
        @media (max-width: 600px) {
            .fr-input-grid {
                grid-template-columns: 1fr;
            }
        }

        .fr-lbl {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            display: block;
            margin-bottom: 4px;
        }
        .fr-sublbl {
            font-size: 10.5px;
            font-weight: 400;
            color: #9ca3af;
            font-style: italic;
            display: block;
            margin-top: 1px;
            line-height: 1.25;
        }

        .fr-input {
            width: 100%;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 11px;
            font-size: 14px;
            color: #111827;
            background: #fff;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .fr-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.1);
        }
        .fr-input.err {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239,68,68,.08);
        }
        .dark .fr-input {
            background: #1f2937;
            border-color: #374151;
            color: #f3f4f6;
        }
        .dark .fr-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96,165,250,.12);
        }

        .fr-ro {
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 11px;
            font-size: 14px;
            color: #374151;
            background: #f9fafb;
        }
        .dark .fr-ro {
            background: #1f2937;
            border-color: #374151;
            color: #d1d5db;
        }

        .fr-pct-box {
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            padding: 7px 10px;
            text-align: center;
            background: #f8fafc;
        }
        .dark .fr-pct-box {
            background: #1f2937;
            border-color: #374151;
        }
        .fr-pct-val {
            font-size: 17px;
            font-weight: 800;
        }

        /* Yes/No */
        .fr-yn {
            display: flex;
            gap: 8px;
        }
        .fr-yn-opt {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13.5px;
            color: #374151;
            transition: all .15s;
            user-select: none;
        }
        .fr-yn-opt:hover {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .dark .fr-yn-opt {
            border-color: #374151;
            color: #d1d5db;
        }
        .dark .fr-yn-opt:hover {
            border-color: #60a5fa;
            background: rgba(59,130,246,.1);
        }

        /* Band table */
        .fr-band-wrap {
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .dark .fr-band-wrap {
            border-color: #1f2937;
        }
        .fr-band-table {
            width: 100%;
            font-size: 12.5px;
            border-collapse: collapse;
        }
        .fr-band-table thead {
            background: #f8fafc;
        }
        .dark .fr-band-table thead {
            background: rgba(255,255,255,.03);
        }
        .fr-band-table th {
            padding: 8px 11px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #6b7280;
            text-align: center;
        }
        .fr-band-table th:first-child {
            text-align: left;
        }
        .fr-band-table td {
            padding: 7px 10px;
            border-top: 1px solid #f3f4f6;
            color: #374151;
            vertical-align: middle;
        }
        .dark .fr-band-table td {
            border-color: #1f2937;
            color: #d1d5db;
        }
        .fr-band-inp {
            width: 100%;
            border: 1.5px solid #d1d5db;
            border-radius: 6px;
            padding: 5px 7px;
            font-size: 13px;
            text-align: center;
            background: #fff;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .fr-band-inp:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,.1);
        }
        .dark .fr-band-inp {
            background: #1f2937;
            border-color: #374151;
            color: #f3f4f6;
        }

        /* Comment */
        .fr-comment {
            margin-top: 10px;
            width: 100%;
            border: 1.5px dashed #e5e7eb;
            border-radius: 7px;
            padding: 6px 10px;
            font-size: 12px;
            color: #6b7280;
            background: transparent;
            outline: none;
            transition: border-color .15s;
        }
        .fr-comment:focus {
            border-style: solid;
            border-color: #9ca3af;
        }
        .dark .fr-comment {
            border-color: #374151;
            color: #9ca3af;
        }

        /* Error */
        .fr-err {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            color: #dc2626;
            margin-top: 7px;
        }

        /* Footer */
        .fr-footer {
            padding: 14px 22px;
            border-top: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fafafa;
        }
        .dark .fr-footer {
            border-color: #1f2937;
            background: rgba(255,255,255,.02);
        }

        /* Rejection banner */
        .fr-rej {
            border: 1.5px solid #fca5a5;
            background: #fef2f2;
            border-radius: 10px;
            padding: 11px 15px;
            margin-top: 11px;
        }
        .dark .fr-rej {
            border-color: rgba(239,68,68,.35);
            background: rgba(239,68,68,.07);
        }
    </style>

    {{-- ── Progress bar ─────────────────────────────────────────────────────── --}}
    <div class="fr-progress-card">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#9ca3af;">Overall Completion</span>
            <div style="display:flex;align-items:baseline;gap:6px;">
                <span style="font-size:13px;color:#6b7280;">{{ $overallCompletion['filled'] }}/{{ $overallCompletion['total'] }} indicators</span>
                <span style="font-size:16px;font-weight:800;color:{{ $overallCompletion['percentage'] === 100 ? '#16a34a' : ($overallCompletion['percentage'] >= 50 ? '#3b82f6' : '#d97706') }};">
                    {{ $overallCompletion['percentage'] }}%
                </span>
            </div>
        </div>
        <div class="fr-pct-track">
            <div class="fr-pct-fill" style="
                 width:{{ $overallCompletion['percentage'] }}%;
                 background:{{ $overallCompletion['percentage'] === 100 ? '#22c55e' : ($overallCompletion['percentage'] >= 50 ? '#3b82f6' : '#f59e0b') }};
            "></div>
        </div>
        @if($period->isRejected() && $period->rejection_reason)
            <div class="fr-rej">
                <p style="font-size:12.5px;font-weight:700;color:#b91c1c;margin-bottom:3px;">⚠ Report Returned for Corrections</p>
                <p style="font-size:12.5px;color:#dc2626;">{{ $period->rejection_reason }}</p>
            </div>
        @endif
    </div>

    {{-- ── Layout ───────────────────────────────────────────────────────────── --}}
    <div class="fr-layout">

        {{-- ── Sidebar ─────────────────────────────────────────────────────── --}}
        <div class="fr-sidebar">
            <div class="fr-sidebar-header">
                <svg width="12" height="12" viewBox="0 0 20 20" fill="none"><path d="M3 5h14M3 10h14M3 15h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Modules
            </div>
            <nav>
                @foreach($groups as $i => $group)
                    @php $s = $groupStats[$group['id']] ?? ['total'=>0,'filled'=>0,'complete'=>false,'percentage'=>0]; @endphp
                    <button wire:click="setActiveGroup({{ $i }})"
                        class="fr-module-btn {{ $i === $activeGroupIndex ? 'active' : '' }}">
                        <span class="fr-dot {{ $s['complete'] ? 'done' : ($s['filled'] > 0 ? 'partial' : 'empty') }}">
                            @if($s['complete'])
                                <svg width="9" height="9" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            @elseif($s['filled'] > 0)
                                <svg width="6" height="6" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" fill="currentColor"/></svg>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </span>
                        <div class="fr-module-label">
                            <div class="fr-module-name">{{ $group['name'] }}</div>
                            @if($s['total'] > 0)
                                <div class="fr-module-count">{{ $s['filled'] }}/{{ $s['total'] }} filled</div>
                            @endif
                        </div>
                        @if($s['total'] > 0 && $s['percentage'] > 0 && ! $s['complete'])
                            <svg width="24" height="24" viewBox="0 0 28 28" style="flex-shrink:0;transform:rotate(-90deg);">
                    <circle cx="14" cy="14" r="11" fill="none" stroke="#e5e7eb" stroke-width="2.5"/>
                    <circle cx="14" cy="14" r="11" fill="none" stroke="#f59e0b" stroke-width="2.5"
                            stroke-dasharray="{{ round(69.12 * $s['percentage'] / 100, 1) }} 69.12"
                            stroke-linecap="round"/>
                            </svg>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- ── Content ──────────────────────────────────────────────────────── --}}
        <div>
            @if($activeGroup)
                @php $ms = $groupStats[$activeGroup['id']] ?? ['filled'=>0,'total'=>0,'percentage'=>0]; @endphp
                <div class="fr-card">

                    {{-- Header --}}
                    <div class="fr-card-header">
                        <div>
                            <h2 class="fr-card-title">{{ $activeGroup['name'] }}</h2>
                            @if($activeGroup['description'])
                                <p class="fr-card-desc">{{ $activeGroup['description'] }}</p>
                            @endif
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:22px;font-weight:800;color:{{ $ms['percentage'] === 100 ? '#16a34a' : '#3b82f6' }};line-height:1;">
                                {{ $ms['percentage'] }}%
                            </div>
                            <div style="font-size:11px;color:#9ca3af;margin-top:2px;">{{ $ms['filled'] }}/{{ $ms['total'] }}</div>
                        </div>
                    </div>

                    {{-- Indicators --}}
                    <div>
                        @forelse($activeGroup['indicators'] as $indicator)
                            @if(empty($indicator['children']))
                                @include('filament.pages.indicators.partials.indicator-row', ['indicator' => $indicator])
                            @else
                                {{-- Banded --}}
                                <div class="fr-ind">
                                    <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;">
                                        <span class="fr-code">{{ $indicator['code'] }}</span>
                                        <div>
                                            <p class="fr-ind-name">{{ $indicator['name'] }}</p>
                                            @if($indicator['source_document'])
                                                <p class="fr-meta-row">
                                                    <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                                    {{ $indicator['source_document'] }}
                                                </p>
                                            @endif
                                            @if($indicator['display_hint'])
                                                <p class="fr-hint-row">
                                                    <svg width="11" height="11" viewBox="0 0 20 20" fill="currentColor" style="margin-top:1px;flex-shrink:0;"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                                    {{ $indicator['display_hint'] }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="fr-band-wrap">
                                        <table class="fr-band-table">
                                            <thead>
                                                <tr>
                                                    <th style="text-align:left;">Band</th>
                                                    <th>Numerator</th>
                                                    <th>Denominator</th>
                                                    <th>%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($indicator['children'] as $child)
                                                    @php
                                                        $cId = $child['id'];
                                                        $cv  = $values[$cId] ?? [];
                                                        $cErr = isset($valueErrors[$cId]);
                                                        $cn  = $cv['numerator']   ?? null;
                                                        $cd  = $cv['denominator'] ?? null;
                                                        $cp  = (is_numeric($cn) && is_numeric($cd) && $cd > 0) ? round(($cn/$cd)*100,1) : null;
                                                    @endphp
                                                    <tr style="{{ $cErr ? 'background:#fef2f2;' : '' }}">
                                                        <td style="font-weight:500;">{{ $child['short_name'] ?? $child['name'] }}</td>
                                                        <td style="text-align:center;">
                                                            @if($isEditable)
                                                                <input type="number" min="0" wire:model.lazy="values.{{ $cId }}.numerator"
                                                   class="fr-band-inp" placeholder="0"/>
                                                            @else
                                                                {{ $cn ?? '—' }}
                                                            @endif
                                                        </td>
                                                        <td style="text-align:center;">
                                                            @if($isEditable)
                                                                <input type="number" min="0" wire:model.lazy="values.{{ $cId }}.denominator"
                                                   class="fr-band-inp" placeholder="0"/>
                                                            @else
                                                                {{ $cd ?? '—' }}
                                                            @endif
                                                        </td>
                                                        <td style="text-align:center;font-weight:700;font-size:13px;
                                            color:{{ $cp !== null ? ($cp>=80?'#16a34a':($cp>=50?'#d97706':'#dc2626')) : '#d1d5db' }};">
                                                            {{ $cp !== null ? $cp.'%' : '—' }}
                                                        </td>
                                                    </tr>
                                                    @if($cErr)
                                                        <tr>
                                                            <td colspan="4"><p class="fr-err">⚠ {{ $valueErrors[$cId] }}</p></td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div style="padding:44px 22px;text-align:center;">
                                <p style="font-size:13px;color:#9ca3af;">No indicators configured for this module yet.</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Footer --}}
                    <div class="fr-footer">
                        <div>
                            @if(! $isFirstGroup)
                                <x-filament::button wire:click="goToPreviousGroup" color="gray" icon="heroicon-o-arrow-left" size="sm">
                            Previous
                                </x-filament::button>
                            @endif
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            @if($isEditable)
                                <x-filament::button wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" color="gray" icon="heroicon-o-document-check" size="sm">
                                    <span wire:loading.remove wire:target="saveDraft">Save Draft</span>
                                    <span wire:loading wire:target="saveDraft">Saving…</span>
                                </x-filament::button>
                            @endif
                            @if(! $isLastGroup)
                                <x-filament::button wire:click="goToNextGroup" color="primary" icon-position="after" icon="heroicon-o-arrow-right" size="sm">
                            Save & Next
                                </x-filament::button>
                            @elseif($isEditable)
                                <x-filament::button wire:click="submitForValidation" color="primary" icon="heroicon-o-paper-airplane" size="sm">
                            Review & Submit
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

</x-filament-panels::page>