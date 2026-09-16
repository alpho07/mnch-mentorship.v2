<x-filament-panels::page>

    <style>
        /* ── Facility header ── */
        .ir-facility-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .dark .ir-facility-bar {
            background: #111827;
            border-color: #1f2937;
        }
        .ir-facility-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #dbeafe, #eff6ff);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dark .ir-facility-icon {
            background: rgba(59,130,246,.15);
        }
        .ir-facility-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: #9ca3af;
        }
        .ir-facility-name  {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            margin-top: 1px;
        }
        .dark .ir-facility-name {
            color: #f3f4f6;
        }
        .ir-facility-meta  {
            font-size: 12px;
            color: #6b7280;
            margin-top: 1px;
        }

        /* ── Period selector card ── */
        .ir-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .dark .ir-card {
            background: #111827;
            border-color: #1f2937;
        }
        .ir-card-header {
            padding: 16px 22px 14px;
            border-bottom: 1px solid #f3f4f6;
            background: linear-gradient(135deg, #f0f7ff 0%, #f8faff 100%);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dark .ir-card-header {
            background: linear-gradient(135deg, rgba(59,130,246,.06) 0%, rgba(255,255,255,.01) 100%);
            border-color: #1f2937;
        }
        .ir-card-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ir-card-title {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .dark .ir-card-title {
            color: #f3f4f6;
        }
        .ir-card-desc  {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }
        .ir-card-body  {
            padding: 22px;
        }

        /* ── Status result panel ── */
        .ir-status-panel {
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 18px;
        }
        .ir-status-panel.not_started {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
        }
        .ir-status-panel.draft       {
            background: #fffbeb;
            border: 1.5px solid #fde68a;
        }
        .ir-status-panel.submitted   {
            background: #eff6ff;
            border: 1.5px solid #bfdbfe;
        }
        .ir-status-panel.validated   {
            background: #f0fdf4;
            border: 1.5px solid #bbf7d0;
        }
        .ir-status-panel.rejected    {
            background: #fef2f2;
            border: 1.5px solid #fecaca;
        }
        .ir-status-panel.pushed_to_dhis2 {
            background: #f5f3ff;
            border: 1.5px solid #ddd6fe;
        }

        .dark .ir-status-panel.not_started {
            background: rgba(255,255,255,.03);
            border-color: #374151;
        }
        .dark .ir-status-panel.draft       {
            background: rgba(245,158,11,.07);
            border-color: rgba(245,158,11,.3);
        }
        .dark .ir-status-panel.submitted   {
            background: rgba(59,130,246,.07);
            border-color: rgba(59,130,246,.3);
        }
        .dark .ir-status-panel.validated   {
            background: rgba(22,163,74,.07);
            border-color: rgba(22,163,74,.3);
        }
        .dark .ir-status-panel.rejected    {
            background: rgba(239,68,68,.07);
            border-color: rgba(239,68,68,.3);
        }
        .dark .ir-status-panel.pushed_to_dhis2 {
            background: rgba(124,58,237,.07);
            border-color: rgba(124,58,237,.3);
        }

        .ir-status-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .ir-status-dot {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ir-status-period {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .dark .ir-status-period {
            color: #f3f4f6;
        }
        .ir-status-label  {
            font-size: 12.5px;
            margin-top: 2px;
        }
        .ir-prog-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 6px;
        }
        .ir-prog-track {
            height: 5px;
            flex: 1;
            background: rgba(0,0,0,.08);
            border-radius: 99px;
            overflow: hidden;
        }
        .dark .ir-prog-track {
            background: rgba(255,255,255,.1);
        }
        .ir-prog-fill {
            height: 100%;
            border-radius: 99px;
        }

        /* ── Recent reports ── */
        .ir-recent-header {
            padding: 14px 22px 12px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dark .ir-recent-header {
            border-color: #1f2937;
        }
        .ir-recent-title {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
        }
        .dark .ir-recent-title {
            color: #f3f4f6;
        }

        .ir-recent-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 22px;
            border-bottom: 1px solid #f9fafb;
            text-decoration: none;
            transition: background .12s;
            gap: 12px;
        }
        .ir-recent-row:last-child {
            border-bottom: none;
        }
        .ir-recent-row:hover {
            background: #f9fafb;
        }
        .dark .ir-recent-row {
            border-color: rgba(255,255,255,.04);
        }
        .dark .ir-recent-row:hover {
            background: rgba(255,255,255,.03);
        }

        .ir-recent-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .ir-recent-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
        }
        .ir-recent-type {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
        }
        .dark .ir-recent-type {
            color: #f3f4f6;
        }
        .ir-recent-meta {
            font-size: 11.5px;
            color: #9ca3af;
            margin-top: 1px;
        }

        .ir-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 99px;
            flex-shrink: 0;
        }
        .ir-badge-gray    {
            background:#f3f4f6;
            color:#4b5563;
        }
        .ir-badge-warning {
            background:#fef9c3;
            color:#a16207;
        }
        .ir-badge-info    {
            background:#dbeafe;
            color:#1d4ed8;
        }
        .ir-badge-success {
            background:#dcfce7;
            color:#15803d;
        }
        .ir-badge-danger  {
            background:#fee2e2;
            color:#b91c1c;
        }
        .ir-badge-purple  {
            background:#f3e8ff;
            color:#7c3aed;
        }
        .dark .ir-badge-gray    {
            background:rgba(255,255,255,.08);
            color:#9ca3af;
        }
        .dark .ir-badge-warning {
            background:rgba(245,158,11,.15);
            color:#fbbf24;
        }
        .dark .ir-badge-info    {
            background:rgba(59,130,246,.15);
            color:#93c5fd;
        }
        .dark .ir-badge-success {
            background:rgba(22,163,74,.15);
            color:#4ade80;
        }
        .dark .ir-badge-danger  {
            background:rgba(239,68,68,.15);
            color:#f87171;
        }
        .dark .ir-badge-purple  {
            background:rgba(124,58,237,.15);
            color:#c4b5fd;
        }

        /* ── Empty state ── */
        .ir-empty {
            padding: 44px 22px;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>

    {{-- ── Facility header ──────────────────────────────────────────────────── --}}
    <div class="ir-facility-bar">
        <div class="ir-facility-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 9l7-7 7 7v9a1 1 0 01-1 1H4a1 1 0 01-1-1V9z" stroke="#3b82f6" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M7 19V12h6v7" stroke="#3b82f6" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
        </div>
        <div>
            <div class="ir-facility-label">Reporting Facility</div>
            <div class="ir-facility-name">{{ $facilityName }}</div>
            <div class="ir-facility-meta">Select a period below to start or continue data entry</div>
        </div>
        <div style="margin-left:auto;text-align:right;flex-shrink:0;">
            <div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9ca3af;">Today</div>
            <div style="font-size:14px;font-weight:700;color:#374151;" class="dark:text-gray-300">
                {{ now()->format('d M Y') }}
            </div>
        </div>
    </div>

    {{-- ── Period selector ──────────────────────────────────────────────────── --}}
    <div class="ir-card">
        <div class="ir-card-header">
            <div class="ir-card-icon">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="white">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div>
                <div class="ir-card-title">Select Reporting Period</div>
                <div class="ir-card-desc">Choose the report type, frequency and period — similar to DHIS2 data entry</div>
            </div>
        </div>
        <div class="ir-card-body">
            {{ $this->form }}

            {{-- ── Dynamic status panel ─────────────────────────────────────── --}}
            @if($periodStatus)
                @php
                    $status     = $periodStatus['status'];
                    $exists     = $periodStatus['exists'];
                    $completion = $periodStatus['completion'];

                    $dotBg = match($status) {
                        'draft'            => '#fef9c3',
                        'submitted'        => '#dbeafe',
                        'validated'        => '#dcfce7',
                        'rejected'         => '#fee2e2',
                        'pushed_to_dhis2'  => '#f3e8ff',
                        default            => '#f3f4f6',
                    };
                    $dotColor = match($status) {
                        'draft'            => '#ca8a04',
                        'submitted'        => '#1d4ed8',
                        'validated'        => '#15803d',
                        'rejected'         => '#b91c1c',
                        'pushed_to_dhis2'  => '#7c3aed',
                        default            => '#6b7280',
                    };
                    $progColor = match($status) {
                        'validated','pushed_to_dhis2' => '#22c55e',
                        'submitted'  => '#3b82f6',
                        'draft'      => '#f59e0b',
                        'rejected'   => '#ef4444',
                        default      => '#d1d5db',
                    };
                    $statusLabel = match($status) {
                        'not_started'    => 'Not yet started — click to begin',
                        'draft'          => 'Draft in progress',
                        'submitted'      => 'Submitted · Awaiting validation',
                        'validated'      => 'Validated ✓',
                        'rejected'       => 'Rejected — corrections required',
                        'pushed_to_dhis2'=> 'Pushed to DHIS2 ✓',
                        default          => ucfirst($status),
                    };
                    $btnLabel = match($status) {
                        'not_started' => 'Start Data Entry',
                        'draft'       => 'Continue Editing',
                        'rejected'    => 'Review & Correct',
                        default       => 'View Report',
                    };
                    $btnColor = match($status) {
                        'not_started' => 'primary',
                        'draft'       => 'warning',
                        'rejected'    => 'danger',
                        default       => 'gray',
                    };
                @endphp

                <div class="ir-status-panel {{ $status }}">
                    <div class="ir-status-left">
                        {{-- Status icon dot --}}
                        <div class="ir-status-dot" style="background:{{ $dotBg }};">
                            @if($status === 'not_started')
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="{{ $dotColor }}"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                            @elseif($status === 'draft')
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="{{ $dotColor }}"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                            @elseif($status === 'submitted')
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="{{ $dotColor }}"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                            @elseif($status === 'validated' || $status === 'pushed_to_dhis2')
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="{{ $dotColor }}"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            @elseif($status === 'rejected')
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="{{ $dotColor }}"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            @endif
                        </div>

                        <div style="min-width:0;">
                            <div class="ir-status-period">
                                {{ $periodStatus['report_type'] }} — {{ $periodStatus['period_label'] }}
                            </div>
                            <div class="ir-status-label" style="color:{{ $dotColor }};">{{ $statusLabel }}</div>

                            @if($completion && $exists)
                                <div class="ir-prog-row">
                                    <div class="ir-prog-track">
                                        <div class="ir-prog-fill"
                                        style="width:{{ $completion['percentage'] }}%;background:{{ $progColor }};"></div>
                                    </div>
                                    <span style="font-size:11.5px;font-weight:700;color:{{ $progColor }};flex-shrink:0;">
                                        {{ $completion['percentage'] }}%
                                    </span>
                                    <span style="font-size:11px;color:#9ca3af;flex-shrink:0;">
                                        ({{ $completion['filled'] }}/{{ $completion['total'] }})
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <x-filament::button
                    wire:click="proceed"
                    wire:loading.attr="disabled"
                    wire:target="proceed"
                    color="{{ $btnColor }}"
                    size="sm"
                    >
                        <span wire:loading.remove wire:target="proceed">{{ $btnLabel }}</span>
                        <span wire:loading wire:target="proceed">Loading…</span>
                    </x-filament::button>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Recent reports ───────────────────────────────────────────────────── --}}
    <div class="ir-card">
        <div class="ir-recent-header">
            <div class="ir-recent-title" style="display:flex;align-items:center;gap:7px;">
                <svg width="15" height="15" viewBox="0 0 20 20" fill="#9ca3af"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                Recent Reports
            </div>
            @if($recentPeriods->isNotEmpty())
                <span style="font-size:11.5px;color:#9ca3af;">{{ $recentPeriods->count() }} report{{ $recentPeriods->count() !== 1 ? 's' : '' }}</span>
            @endif
        </div>

        @forelse($recentPeriods as $rp)
            @php
                $badgeClass = match($rp['status_color']) {
                    'success' => 'ir-badge-success',
                    'warning' => 'ir-badge-warning',
                    'danger'  => 'ir-badge-danger',
                    'info'    => 'ir-badge-info',
                    'purple'  => 'ir-badge-purple',
                    default   => 'ir-badge-gray',
                };
                $iconBg = match($rp['status_color']) {
                    'success' => '#dcfce7',
                    'warning' => '#fef9c3',
                    'danger'  => '#fee2e2',
                    'info'    => '#dbeafe',
                    default   => '#f3f4f6',
                };
                $iconColor = match($rp['status_color']) {
                    'success' => '#15803d',
                    'warning' => '#ca8a04',
                    'danger'  => '#b91c1c',
                    'info'    => '#1d4ed8',
                    default   => '#6b7280',
                };
                $initials = strtoupper(substr($rp['report_type'], 0, 2));
                $statusText = ucwords(str_replace(['_', 'pushed to dhis2'], [' ', 'DHIS2'], $rp['status']));
            @endphp

            <a href="{{ $rp['url'] }}" class="ir-recent-row">
                <div class="ir-recent-left">
                    <div class="ir-recent-icon" style="background:{{ $iconBg }};color:{{ $iconColor }};">
                        {{ $initials }}
                    </div>
                    <div style="min-width:0;">
                        <div class="ir-recent-type">{{ $rp['report_type'] }}</div>
                        <div class="ir-recent-meta">
                            {{ $rp['period_label'] }}
                            @if($rp['updated_at'])
                                &nbsp;·&nbsp; {{ $rp['updated_at'] }}
                            @endif
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                    <span class="ir-badge {{ $badgeClass }}">{{ $statusText }}</span>
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="#d1d5db">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </a>

        @empty
            <div class="ir-empty">
                <svg width="32" height="32" viewBox="0 0 20 20" fill="#e5e7eb" style="margin:0 auto 10px;display:block;">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                </svg>
            No reports submitted yet. Select a period above to get started.
            </div>
        @endforelse
    </div>

</x-filament-panels::page>