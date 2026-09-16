<x-filament-panels::page>

<style>
    /* ── Layout ── */
    .rr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
    @media (max-width: 768px) { .rr-grid { grid-template-columns: 1fr; } }
    .rr-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
    @media (max-width: 900px) { .rr-grid-4 { grid-template-columns: repeat(2,1fr); } }
    @media (max-width: 500px) { .rr-grid-4 { grid-template-columns: 1fr; } }

    /* ── Cards ── */
    .rr-card {
        background: #fff; border: 1px solid #e5e7eb;
        border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.05);
        overflow: hidden;
    }
    .dark .rr-card { background: #111827; border-color: #1f2937; }

    .rr-card-body { padding: 20px 22px; }
    .rr-card-header {
        padding: 15px 22px 13px;
        border-bottom: 1px solid #f3f4f6;
        background: #f8faff;
        display: flex; align-items: center; justify-content: space-between;
    }
    .dark .rr-card-header { background: rgba(59,130,246,.04); border-color: #1f2937; }
    .rr-card-title { font-size: 14px; font-weight: 700; color: #111827; margin: 0; }
    .dark .rr-card-title { color: #f9fafb; }

    /* ── Stat tiles ── */
    .rr-stat {
        background: #fff; border: 1px solid #e5e7eb;
        border-radius: 14px; padding: 18px 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,.05);
    }
    .dark .rr-stat { background: #111827; border-color: #1f2937; }
    .rr-stat-label {
        font-size: 10.5px; font-weight: 700; letter-spacing: .07em;
        text-transform: uppercase; color: #9ca3af; margin-bottom: 6px;
    }
    .rr-stat-value { font-size: 15px; font-weight: 700; color: #111827; line-height: 1.2; }
    .dark .rr-stat-value { color: #f3f4f6; }
    .rr-stat-sub { font-size: 11.5px; color: #9ca3af; margin-top: 2px; }

    /* ── Progress ── */
    .rr-prog-track {
        width: 100%; height: 10px; background: #e5e7eb;
        border-radius: 99px; overflow: hidden; margin-top: 10px;
    }
    .dark .rr-prog-track { background: #1f2937; }
    .rr-prog-fill { height: 100%; border-radius: 99px; transition: width .6s ease; }

    /* Big completion ring */
    .rr-ring-wrap {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 28px 22px; position: relative;
    }
    .rr-ring-val {
        position: absolute; top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        text-align: center; line-height: 1;
    }
    .rr-ring-pct { font-size: 26px; font-weight: 800; }
    .rr-ring-sub { font-size: 11px; color: #9ca3af; margin-top: 3px; }

    /* ── Module table ── */
    .rr-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .rr-table thead { background: #f8fafc; }
    .dark .rr-table thead { background: rgba(255,255,255,.03); }
    .rr-table th {
        padding: 9px 16px; font-size: 10.5px; font-weight: 700;
        letter-spacing: .06em; text-transform: uppercase; color: #6b7280;
        border-bottom: 1px solid #f3f4f6;
    }
    .dark .rr-table th { border-color: #1f2937; }
    .rr-table th:first-child { text-align: left; }
    .rr-table th:not(:first-child) { text-align: center; }
    .rr-table td {
        padding: 10px 16px; border-bottom: 1px solid #f9fafb;
        color: #374151; vertical-align: middle;
    }
    .dark .rr-table td { border-color: rgba(255,255,255,.04); color: #d1d5db; }
    .rr-table tr:last-child td { border-bottom: none; }
    .rr-table tr:hover td { background: #fafafa; }
    .dark .rr-table tr:hover td { background: rgba(255,255,255,.015); }

    /* Mini progress bar */
    .rr-mini-track {
        height: 6px; background: #e5e7eb; border-radius: 99px;
        overflow: hidden; flex: 1;
    }
    .dark .rr-mini-track { background: #1f2937; }
    .rr-mini-fill { height: 100%; border-radius: 99px; }

    /* Status badge */
    .rr-badge {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 11px; font-weight: 600;
        padding: 3px 9px; border-radius: 99px;
    }
    .rr-badge-ok      { background: #dcfce7; color: #15803d; }
    .rr-badge-warn    { background: #fef9c3; color: #a16207; }
    .rr-badge-na      { background: #f3f4f6; color: #9ca3af; }
    .dark .rr-badge-ok   { background: rgba(22,163,74,.18); color: #4ade80; }
    .dark .rr-badge-warn { background: rgba(202,138,4,.18);  color: #fbbf24; }
    .dark .rr-badge-na   { background: rgba(255,255,255,.06); color: #6b7280; }

    /* Notes textarea */
    .rr-textarea {
        width: 100%; border: 1.5px solid #d1d5db; border-radius: 9px;
        padding: 10px 13px; font-size: 13.5px; color: #111827;
        background: #fff; outline: none; resize: vertical; min-height: 90px;
        transition: border-color .15s, box-shadow .15s; font-family: inherit;
        line-height: 1.55;
    }
    .rr-textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
    .dark .rr-textarea { background: #1f2937; border-color: #374151; color: #f3f4f6; }
    .dark .rr-textarea:focus { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96,165,250,.12); }

    /* Submit CTA */
    .rr-cta {
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
        border-radius: 14px; padding: 22px 26px;
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        box-shadow: 0 4px 14px rgba(37,99,235,.3);
    }
    .rr-cta-incomplete {
        background: linear-gradient(135deg, #b45309 0%, #d97706 100%);
        box-shadow: 0 4px 14px rgba(217,119,6,.3);
    }
    .rr-cta-title { font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 3px; }
    .rr-cta-desc  { font-size: 12.5px; color: rgba(255,255,255,.8); }

    /* Status banner */
    .rr-status-banner {
        border-radius: 14px; padding: 20px 22px;
        display: flex; align-items: flex-start; gap: 14px;
    }
    .rr-status-icon {
        width: 40px; height: 40px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .rr-status-validated { background: #f0fdf4; border: 1.5px solid #bbf7d0; }
    .rr-status-rejected  { background: #fef2f2; border: 1.5px solid #fecaca; }
    .rr-status-submitted { background: #eff6ff; border: 1.5px solid #bfdbfe; }
    .dark .rr-status-validated { background: rgba(22,163,74,.08);  border-color: rgba(22,163,74,.3); }
    .dark .rr-status-rejected  { background: rgba(239,68,68,.08);  border-color: rgba(239,68,68,.3); }
    .dark .rr-status-submitted { background: rgba(59,130,246,.08); border-color: rgba(59,130,246,.3); }

    /* Warning box */
    .rr-warning {
        display: flex; align-items: flex-start; gap: 10px;
        background: #fffbeb; border: 1.5px solid #fde68a;
        border-radius: 10px; padding: 12px 14px; margin-top: 12px;
        font-size: 12.5px; color: #92400e;
    }
    .dark .rr-warning { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.3); color: #fbbf24; }
</style>

    {{-- ── Top stat tiles ───────────────────────────────────────────────────── --}}
    <div class="rr-grid-4">
        <div class="rr-stat">
            <div class="rr-stat-label">Facility</div>
            <div class="rr-stat-value">{{ $period->facility?->name ?? '—' }}</div>
            @if($period->facility?->mfl_code)
                <div class="rr-stat-sub">MFL {{ $period->facility->mfl_code }}</div>
            @endif
        </div>
        <div class="rr-stat">
            <div class="rr-stat-label">Report Type</div>
            <div class="rr-stat-value">{{ $period->reportType?->name ?? '—' }}</div>
            <div class="rr-stat-sub">{{ ucfirst($period->frequency?->code ?? '') }}</div>
        </div>
        <div class="rr-stat">
            <div class="rr-stat-label">Reporting Period</div>
            <div class="rr-stat-value">{{ $period->period_label }}</div>
            <div class="rr-stat-sub" style="display:flex;align-items:center;gap:5px;">
                @php
                    $statusColors = [
                        'draft'           => ['#f59e0b','#fffbeb'],
                        'submitted'       => ['#3b82f6','#eff6ff'],
                        'validated'       => ['#16a34a','#f0fdf4'],
                        'rejected'        => ['#dc2626','#fef2f2'],
                        'pushed_to_dhis2' => ['#7c3aed','#f5f3ff'],
                    ];
                    [$sc, $sb] = $statusColors[$period->status] ?? ['#6b7280','#f3f4f6'];
                @endphp
                <span style="
                    display:inline-flex;align-items:center;gap:4px;
                    font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;
                    background:{{ $sb }};color:{{ $sc }};">
                    {{ ucfirst(str_replace('_',' ', $period->status)) }}
                </span>
            </div>
        </div>
        <div class="rr-stat">
            <div class="rr-stat-label">Submitted By</div>
            @if($period->submittedByUser)
                <div class="rr-stat-value">{{ $period->submittedByUser->name }}</div>
                <div class="rr-stat-sub">{{ $period->submitted_at?->format('d M Y, H:i') }}</div>
            @else
                <div class="rr-stat-value" style="color:#9ca3af;">Not yet submitted</div>
            @endif
        </div>
    </div>

    {{-- ── Completion + Module summary ─────────────────────────────────────── --}}
    <div class="rr-grid">

        {{-- Completion ring --}}
        <div class="rr-card">
            <div class="rr-card-header">
                <h3 class="rr-card-title">Overall Completion</h3>
                <span style="font-size:13px;font-weight:700;color:{{ $overallCompletion['percentage'] === 100 ? '#16a34a' : '#d97706' }};">
                    {{ $overallCompletion['filled'] }}/{{ $overallCompletion['total'] }} indicators
                </span>
            </div>
            <div class="rr-ring-wrap">
                @php $pct = $overallCompletion['percentage']; @endphp
                @php
                    $r = 60;
                    $circ = round(2 * M_PI * $r, 2);
                    $dash = round($circ * $pct / 100, 2);
                    $ringColor = $pct === 100 ? '#22c55e' : ($pct >= 50 ? '#3b82f6' : '#f59e0b');
                @endphp
                <svg width="160" height="160" viewBox="0 0 160 160">
                    <circle cx="80" cy="80" r="{{ $r }}" fill="none" stroke="#e5e7eb" stroke-width="12"/>
                    <circle cx="80" cy="80" r="{{ $r }}" fill="none"
                        stroke="{{ $ringColor }}" stroke-width="12"
                        stroke-dasharray="{{ $dash }} {{ $circ }}"
                        stroke-dashoffset="{{ $circ / 4 }}"
                        stroke-linecap="round"
                        style="transition:stroke-dasharray .8s ease;"/>
                </svg>
                <div class="rr-ring-val">
                    <div class="rr-ring-pct" style="color:{{ $ringColor }};">{{ $pct }}%</div>
                    <div class="rr-ring-sub">complete</div>
                </div>
            </div>

            @if($pct < 100)
                <div style="padding:0 22px 20px;">
                    <div class="rr-warning">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0;margin-top:1px;">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>
                            <strong>{{ $overallCompletion['total'] - $overallCompletion['filled'] }} indicator(s) unfilled.</strong>
                            You can still submit, but incomplete data may affect reporting quality.
                        </span>
                    </div>
                </div>
            @else
                <div style="padding:0 22px 20px;text-align:center;">
                    <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#16a34a;">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        All indicators filled — ready to submit
                    </span>
                </div>
            @endif
        </div>

        {{-- Module summary table --}}
        <div class="rr-card">
            <div class="rr-card-header">
                <h3 class="rr-card-title">Module Summary</h3>
                <span style="font-size:12px;color:#9ca3af;">{{ count($groupStats) }} modules</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="rr-table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Progress</th>
                            <th>Filled</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groupStats as $stat)
                            <tr>
                                <td style="font-weight:500;max-width:180px;">
                                    <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        {{ $stat['group_name'] }}
                                    </span>
                                </td>
                                <td style="min-width:120px;">
                                    <div style="display:flex;align-items:center;gap:7px;">
                                        <div class="rr-mini-track">
                                            <div class="rr-mini-fill" style="
                                                width:{{ $stat['percentage'] }}%;
                                                background:{{ $stat['complete'] ? '#22c55e' : ($stat['percentage'] > 0 ? '#3b82f6' : '#e5e7eb') }};
                                            "></div>
                                        </div>
                                        <span style="font-size:11.5px;font-weight:600;color:#6b7280;flex-shrink:0;">
                                            {{ $stat['percentage'] }}%
                                        </span>
                                    </div>
                                </td>
                                <td style="text-align:center;font-size:12.5px;color:#6b7280;">
                                    {{ $stat['filled'] }}/{{ $stat['total'] }}
                                </td>
                                <td style="text-align:center;">
                                    @if($stat['total'] === 0)
                                        <span class="rr-badge rr-badge-na">N/A</span>
                                    @elseif($stat['complete'])
                                        <span class="rr-badge rr-badge-ok">
                                            <svg width="10" height="10" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            Done
                                        </span>
                                    @else
                                        <span class="rr-badge rr-badge-warn">
                                            <svg width="10" height="10" viewBox="0 0 12 12" fill="currentColor"><circle cx="6" cy="6" r="5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M6 4v3M6 8.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            Partial
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Notes + Submit / Status ──────────────────────────────────────────── --}}
    @if($isSubmittable)

        {{-- Notes field --}}
        <div class="rr-card">
            <div class="rr-card-header">
                <h3 class="rr-card-title">Submission Notes</h3>
                <span style="font-size:11.5px;color:#9ca3af;">Optional</span>
            </div>
            <div class="rr-card-body">
                <p style="font-size:12.5px;color:#6b7280;margin-bottom:10px;">
                    Add any context, data quality observations, or explanations for the validator.
                </p>
                <textarea
                    wire:model="notes"
                    class="rr-textarea"
                    placeholder="e.g. Module 4 data sourced from paper registers — digital records being updated. Indicator 3.2 counts cross-checked against admission register."
                ></textarea>
            </div>
        </div>

        {{-- CTA --}}
        <div class="rr-cta {{ $overallCompletion['percentage'] < 100 ? 'rr-cta-incomplete' : '' }}">
            <div>
                <div class="rr-cta-title">
                    @if($overallCompletion['percentage'] === 100)
                        ✓ All indicators filled — ready to submit
                    @else
                        Submit with incomplete data?
                    @endif
                </div>
                <div class="rr-cta-desc">
                    @if($overallCompletion['percentage'] === 100)
                        Once submitted, this report is locked for editing and sent to your validator for review.
                    @else
                        {{ $overallCompletion['total'] - $overallCompletion['filled'] }} indicator(s) are unfilled.
                        You can still submit, but your validator may request corrections.
                    @endif
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <a href="{{ \App\Filament\Pages\Indicators\FillReport::getUrl(['period' => $period->id]) }}"
                    style="
                        display:inline-flex;align-items:center;gap:6px;
                        padding:9px 18px;border-radius:8px;
                        background:rgba(255,255,255,.15);color:#fff;
                        font-size:13px;font-weight:600;text-decoration:none;
                        border:1.5px solid rgba(255,255,255,.3);
                        transition:background .15s;
                    "
                    onmouseover="this.style.background='rgba(255,255,255,.25)'"
                    onmouseout="this.style.background='rgba(255,255,255,.15)'"
                >
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
                    Back to Edit
                </a>

                <x-filament::button
                    wire:click="confirmSubmit"
                    wire:loading.attr="disabled"
                    wire:target="confirmSubmit"
                    color="white"
                    style="font-weight:700;"
                >
                    <span wire:loading.remove wire:target="confirmSubmit" style="display:flex;align-items:center;gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>
                        Submit for Validation
                    </span>
                    <span wire:loading wire:target="confirmSubmit">Submitting…</span>
                </x-filament::button>
            </div>
        </div>

    @else
        {{-- Status banner for already-submitted/validated/rejected --}}
        @php
            $bannerClass = match(true) {
                $period->isValidated() => 'rr-status-validated',
                $period->isRejected()  => 'rr-status-rejected',
                default                => 'rr-status-submitted',
            };
            $iconColor = match(true) {
                $period->isValidated() => '#16a34a',
                $period->isRejected()  => '#dc2626',
                default                => '#3b82f6',
            };
            $titleColor = $iconColor;
            $statusTitle = match(true) {
                $period->isValidated() => 'Report Validated',
                $period->isRejected()  => 'Report Rejected',
                default                => 'Report Submitted — Awaiting Validation',
            };
        @endphp

        <div class="rr-status-banner {{ $bannerClass }}">
            <div class="rr-status-icon" style="background:{{ $iconColor }}20;">
                @if($period->isValidated())
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="{{ $iconColor }}"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                @elseif($period->isRejected())
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="{{ $iconColor }}"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                @else
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="{{ $iconColor }}"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                @endif
            </div>
            <div>
                <p style="font-size:15px;font-weight:700;color:{{ $titleColor }};margin-bottom:4px;">
                    {{ $statusTitle }}
                </p>
                @if($period->isValidated() && $period->validated_at)
                    <p style="font-size:12.5px;color:#6b7280;">
                        Validated on {{ $period->validated_at->format('d M Y \a\t H:i') }}
                    </p>
                @endif
                @if($period->isRejected() && $period->rejection_reason)
                    <p style="font-size:12.5px;color:#dc2626;margin-top:6px;">
                        <strong>Reason:</strong> {{ $period->rejection_reason }}
                    </p>
                    <div style="margin-top:10px;">
                        <a href="{{ \App\Filament\Pages\Indicators\FillReport::getUrl(['period' => $period->id]) }}"
                            style="
                                display:inline-flex;align-items:center;gap:6px;
                                padding:8px 16px;border-radius:8px;
                                background:#dc2626;color:#fff;font-size:13px;font-weight:600;
                                text-decoration:none;
                            ">
                            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                            Open & Correct
                        </a>
                    </div>
                @endif
                @if($period->submittedByUser && !$period->isValidated())
                    <p style="font-size:12px;color:#9ca3af;margin-top:4px;">
                        Submitted by {{ $period->submittedByUser->name }}
                        @if($period->submitted_at) · {{ $period->submitted_at->format('d M Y, H:i') }} @endif
                    </p>
                @endif
            </div>
        </div>
    @endif

</x-filament-panels::page>