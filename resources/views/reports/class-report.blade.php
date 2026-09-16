<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Class Report — {{ $class->name }}</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

            *, *::before, *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            :root {
                --primary:   #1d4ed8;
                --primary-light: #dbeafe;
                --success:   #16a34a;
                --success-light: #dcfce7;
                --danger:    #dc2626;
                --danger-light: #fee2e2;
                --warning:   #d97706;
                --warning-light: #fef3c7;
                --gray-50:   #f8fafc;
                --gray-100:  #f1f5f9;
                --gray-200:  #e2e8f0;
                --gray-400:  #94a3b8;
                --gray-600:  #475569;
                --gray-800:  #1e293b;
                --gray-900:  #0f172a;
            }

            body {
                font-family: 'Inter', 'DejaVu Sans', sans-serif;
                background: #f8fafc;
                color: var(--gray-900);
                font-size: 13px;
                line-height: 1.5;
            }

            /* ── Print / PDF wrapper ── */
            .page-wrapper {
                max-width: 1100px;
                margin: 0 auto;
                background: #fff;
            @if(isset($isPdf) && $isPdf)
                width: 100%;
                max-width: 100%;
            @endif
            }

            /* ── Header band ── */
            .report-header {
                background: linear-gradient(135deg, #1d4ed8 0%, #4f46e5 60%, #7c3aed 100%);
                color: #fff;
                padding: 32px 40px 28px;
                position: relative;
                overflow: hidden;
            }
            .report-header::after {
                content: '';
                position: absolute;
                right: -40px;
                top: -40px;
                width: 220px;
                height: 220px;
                border-radius: 50%;
                background: rgba(255,255,255,0.06);
            }
            .report-header::before {
                content: '';
                position: absolute;
                right: 60px;
                bottom: -60px;
                width: 160px;
                height: 160px;
                border-radius: 50%;
                background: rgba(255,255,255,0.04);
            }

            .header-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 24px;
                position: relative;
                z-index: 1;
            }

            .header-logo {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .logo-mark {
                width: 44px;
                height: 44px;
                background: rgba(255,255,255,0.2);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
            }
            .logo-text {
                line-height: 1.2;
            }
            .logo-title {
                font-size: 15px;
                font-weight: 700;
                letter-spacing: 0.04em;
            }
            .logo-sub   {
                font-size: 11px;
                opacity: 0.75;
                margin-top: 2px;
            }

            .header-meta {
                text-align: right;
                font-size: 11px;
                opacity: 0.8;
                line-height: 1.7;
            }

            .header-body {
                margin-top: 20px;
                position: relative;
                z-index: 1;
            }
            .report-type-badge {
                display: inline-block;
                background: rgba(255,255,255,0.2);
                border: 1px solid rgba(255,255,255,0.3);
                border-radius: 100px;
                padding: 3px 12px;
                font-size: 10px;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                margin-bottom: 10px;
            }
            .report-title {
                font-size: 26px;
                font-weight: 800;
                letter-spacing: -0.02em;
                line-height: 1.15;
                margin-bottom: 6px;
            }
            .report-subtitle {
                font-size: 13px;
                opacity: 0.82;
            }

            /* ── Summary cards ── */
            .summary-bar {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 0;
                border-bottom: 2px solid var(--gray-200);
            }
            .summary-card {
                padding: 18px 20px;
                border-right: 1px solid var(--gray-200);
                text-align: center;
            }
            .summary-card:last-child {
                border-right: none;
            }
            .summary-card-val {
                font-size: 26px;
                font-weight: 800;
                letter-spacing: -0.03em;
                line-height: 1;
                margin-bottom: 4px;
            }
            .summary-card-lbl {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.07em;
                color: var(--gray-400);
            }
            .c-blue   {
                color: var(--primary);
            }
            .c-green  {
                color: var(--success);
            }
            .c-orange {
                color: var(--warning);
            }
            .c-purple {
                color: #7c3aed;
            }
            .c-gray   {
                color: var(--gray-600);
            }

            /* ── Info grid ── */
            .info-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0;
                border-bottom: 2px solid var(--gray-200);
            }
            .info-block {
                padding: 20px 28px;
                border-right: 1px solid var(--gray-200);
            }
            .info-block:last-child {
                border-right: none;
            }
            .info-block-title {
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                color: var(--primary);
                margin-bottom: 10px;
                padding-bottom: 6px;
                border-bottom: 2px solid var(--primary-light);
            }
            .info-row {
                display: flex;
                gap: 8px;
                margin-bottom: 5px;
                font-size: 12px;
            }
            .info-label {
                color: var(--gray-400);
                font-weight: 500;
                min-width: 100px;
                flex-shrink: 0;
            }
            .info-value {
                color: var(--gray-800);
                font-weight: 600;
            }

            /* ── Module summary row ── */
            .modules-section {
                padding: 20px 28px;
                border-bottom: 2px solid var(--gray-200);
                background: var(--gray-50);
            }
            .section-title {
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                color: var(--primary);
                margin-bottom: 12px;
            }
            .module-chips {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .module-chip {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #fff;
                border: 1px solid var(--gray-200);
                border-radius: 8px;
                padding: 6px 12px;
                font-size: 11px;
                font-weight: 500;
                color: var(--gray-800);
            }
            .module-chip-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                flex-shrink: 0;
            }
            .dot-completed  {
                background: var(--success);
            }
            .dot-inprogress {
                background: var(--warning);
            }
            .dot-notstarted {
                background: var(--gray-400);
            }

            /* ── Attendance table ── */
            .table-section {
                padding: 0;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11.5px;
            }
            thead tr {
                background: var(--gray-900);
                color: #fff;
            }
            thead th {
                padding: 10px 12px;
                text-align: left;
                font-weight: 600;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.07em;
                white-space: nowrap;
            }
            thead th.center {
                text-align: center;
            }

            tbody tr {
                border-bottom: 1px solid var(--gray-100);
            }
            tbody tr:hover {
                background: var(--gray-50);
            }
            tbody tr.completed-row {
                background: #f0fdf4;
            }

            tbody td {
                padding: 9px 12px;
                color: var(--gray-800);
                vertical-align: middle;
            }
            tbody td.center {
                text-align: center;
            }

            .mentee-name {
                font-weight: 600;
                font-size: 12px;
            }
            .mentee-meta {
                font-size: 10px;
                color: var(--gray-400);
                margin-top: 1px;
            }

            /* Per-module cell */
            .mod-cell {
                text-align: center;
                font-size: 14px;
            }

            /* Attendance % bar */
            .pct-wrap {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .pct-bar-bg {
                flex: 1;
                height: 5px;
                background: var(--gray-100);
                border-radius: 100px;
                overflow: hidden;
            }
            .pct-bar-fill {
                height: 100%;
                border-radius: 100px;
                transition: width 0.3s;
            }
            .pct-val {
                font-size: 11px;
                font-weight: 700;
                min-width: 32px;
                text-align: right;
            }

            /* Status badge */
            .badge {
                display: inline-flex;
                align-items: center;
                padding: 2px 8px;
                border-radius: 100px;
                font-size: 10px;
                font-weight: 600;
                letter-spacing: 0.03em;
            }
            .badge-completed  {
                background: var(--success-light);
                color: var(--success);
            }
            .badge-active     {
                background: var(--warning-light);
                color: var(--warning);
            }
            .badge-enrolled   {
                background: var(--primary-light);
                color: var(--primary);
            }

            /* ── EmONC section ── */
            .emonc-section {
                padding: 20px 28px;
                border-bottom: 2px solid var(--gray-200);
                background: #fff;
            }
            .emonc-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
                margin-bottom: 18px;
            }
            .emonc-card {
                background: var(--gray-50);
                border: 1px solid var(--gray-200);
                border-radius: 8px;
                padding: 14px;
                text-align: center;
            }
            .emonc-card-val {
                font-size: 22px;
                font-weight: 800;
                line-height: 1;
                margin-bottom: 4px;
            }
            .emonc-card-lbl {
                font-size: 9px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: var(--gray-400);
            }
            .status-pill {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 8px;
                border-radius: 100px;
                font-size: 10px;
                font-weight: 600;
            }
            .pill-passed { background: var(--success-light); color: var(--success); }
            .pill-failed { background: var(--danger-light); color: var(--danger); }
            .pill-pending { background: var(--warning-light); color: var(--warning); }
            .pill-none { background: var(--gray-100); color: var(--gray-400); }

            /* ── Footer ── */
            .report-footer {
                padding: 16px 28px;
                border-top: 2px solid var(--gray-200);
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 10px;
                color: var(--gray-400);
                background: var(--gray-50);
            }

            /* ── Print / No-PDF action bar ── */
            .action-bar {
                padding: 12px 28px;
                background: var(--primary);
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
            .action-bar a {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 18px;
                background: rgba(255,255,255,0.15);
                border: 1px solid rgba(255,255,255,0.3);
                border-radius: 8px;
                color: #fff;
                text-decoration: none;
                font-size: 12px;
                font-weight: 600;
                transition: background 0.15s;
            }
            .action-bar a:hover {
                background: rgba(255,255,255,0.25);
            }
            .action-bar a.btn-primary {
                background: #fff;
                color: var(--primary);
                border-color: #fff;
            }
            .action-bar a.btn-primary:hover {
                background: #e0e7ff;
            }

            @media print {
                .action-bar {
                    display: none;
                }
                body {
                    background: #fff;
                }
                .page-wrapper {
                    max-width: 100%;
                    box-shadow: none;
                }
            }
        </style>
    </head>
    <body>
    <div class="page-wrapper">

        {{-- Action bar (web only) --}}
    @if(!isset($isPdf) || !$isPdf)
            <div class="action-bar">
                <a href="{{ route('reports.class.pdf', $class->id) }}">
                    ⬇ Download PDF
                </a>
                <a href="javascript:window.print()" class="btn-primary">
                    🖨 Print
                </a>
            </div>
        @endif

        {{-- Header --}}
        <div class="report-header">
            <div class="header-top">
                <div class="header-logo">
                    <div class="logo-mark">🏥</div>
                    <div class="logo-text">
                        <div class="logo-title">MNCH MENTORSHIP PLATFORM</div>
                        <div class="logo-sub">Ministry of Health · Kenya</div>
                    </div>
                </div>
                <div class="header-meta">
                    Generated: {{ now()->format('d M Y, H:i') }}<br>
                    Report ID: RPT-{{ str_pad($class->id, 5, '0', STR_PAD_LEFT) }}<br>
                    Prepared by: {{ Auth::user()->name ?? 'System' }}
                </div>
            </div>
            <div class="header-body">
                <div class="report-type-badge">Class Attendance & Completion Report</div>
                <div class="report-title">{{ $class->name }}</div>
                <div class="report-subtitle">
                    {{ $class->training->program->name ?? 'Program' }} &nbsp;·&nbsp;
                    {{ $class->training->facility->name ?? 'Facility' }}
                </div>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="summary-bar">
            <div class="summary-card">
                <div class="summary-card-val c-blue">{{ $totalEnrolled }}</div>
                <div class="summary-card-lbl">Enrolled Mentees</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-val c-green">{{ $totalCompleted }}</div>
                <div class="summary-card-lbl">Completed</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-val c-orange">{{ $totalEnrolled - $totalCompleted }}</div>
                <div class="summary-card-lbl">In Progress / Pending</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-val c-purple">{{ $modules->count() }}</div>
                <div class="summary-card-lbl">Modules</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-val c-gray">{{ round($avgAttendance ?? 0) }}%</div>
                <div class="summary-card-lbl">Avg Attendance</div>
            </div>
        </div>

        {{-- Info grid --}}
        <div class="info-section">
            <div class="info-block">
                <div class="info-block-title">Programme Information</div>
                <div class="info-row"><span class="info-label">Program</span><span class="info-value">{{ $class->training->program->name ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Mentorship</span><span class="info-value">{{ $class->training->title ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Class</span><span class="info-value">{{ $class->name }}</span></div>
                <div class="info-row"><span class="info-label">Class Status</span><span class="info-value">{{ ucfirst($class->status) }}</span></div>
            </div>
            <div class="info-block">
                <div class="info-block-title">Facility & Period</div>
                <div class="info-row"><span class="info-label">Facility</span><span class="info-value">{{ $class->training->facility->name ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Lead Mentor</span><span class="info-value">{{ $class->training->mentor->name ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Co-Mentor(s)</span><span class="info-value">{{ $coMentors->isNotEmpty() ? $coMentors->implode(', ') : '—' }}</span></div>
                <div class="info-row"><span class="info-label">Start Date</span><span class="info-value">{{ $class->start_date ? \Carbon\Carbon::parse($class->start_date)->format('d M Y') : '—' }}</span></div>
                <div class="info-row"><span class="info-label">End Date</span><span class="info-value">{{ $class->end_date ? \Carbon\Carbon::parse($class->end_date)->format('d M Y') : '—' }}</span></div>
            </div>
        </div>

        {{-- Modules --}}
        <div class="modules-section">
            <div class="section-title">Modules Covered ({{ $modules->count() }})</div>
            <div class="module-chips">
                @foreach($modules as $i => $module)
                    <div class="module-chip">
                        <span class="module-chip-dot dot-{{ $module->status === 'completed' ? 'completed' : ($module->status === 'in_progress' ? 'inprogress' : 'notstarted') }}"></span>
                        <span>M{{ $i+1 }}: {{ $module->programModule->name ?? 'Module' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- EmONC Progress & Certification --}}
        @if($isEmonc && $emoncReport)
            <div class="emonc-section">
                <div class="section-title">EmONC Progress & Certification</div>
                <div class="emonc-grid">
                    <div class="emonc-card">
                        <div class="emonc-card-val c-orange">{{ $emoncReport['pending_video_reviews'] }}</div>
                        <div class="emonc-card-lbl">Pending Video Reviews</div>
                    </div>
                    <div class="emonc-card">
                        <div class="emonc-card-val c-blue">{{ $emoncReport['pending_mentor_approvals'] }}</div>
                        <div class="emonc-card-lbl">Pending Mentor Approvals</div>
                    </div>
                    <div class="emonc-card">
                        <div class="emonc-card-val c-purple">{{ $emoncReport['pending_drmh_approvals'] }}</div>
                        <div class="emonc-card-lbl">Pending Head DRMH</div>
                    </div>
                    <div class="emonc-card">
                        <div class="emonc-card-val c-green">{{ $emoncReport['certified_count'] }}</div>
                        <div class="emonc-card-lbl">Certified</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Mentee</th>
                            <th class="center">Modules Done</th>
                            @foreach($emoncReport['modules'] as $module)
                                <th class="center" title="{{ $module->programModule?->name ?? '' }}">
                                    {{ $module->programModule?->parent ? ($module->programModule->parent->name . ' › ') : '' }}{{ Str::limit($module->programModule?->name, 18) }}
                                </th>
                            @endforeach
                            <th class="center">Video</th>
                            <th class="center">Mentor</th>
                            <th class="center">Head DRMH</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($emoncReport['mentees'] as $row)
                            <tr>
                                <td>
                                    <div class="mentee-name">{{ $row['user']->name ?? ($row['user']->first_name . ' ' . $row['user']->last_name) }}</div>
                                    <div class="mentee-meta">{{ $row['user']->email ?? '' }}</div>
                                </td>
                                <td class="center">
                                    <div class="pct-wrap">
                                        <div class="pct-bar-bg">
                                            <div class="pct-bar-fill" style="width:{{ $row['completion_pct'] }}%; background:{{ $row['completion_pct'] >= 80 ? '#16a34a' : ($row['completion_pct'] >= 40 ? '#d97706' : '#dc2626') }};"></div>
                                        </div>
                                        <span class="pct-val">{{ $row['completion_pct'] }}%</span>
                                    </div>
                                </td>
                                @foreach($row['modules'] as $modRow)
                                    <td class="center">
                                        @if($modRow['progress_status'] === 'completed' || $modRow['progress_status'] === 'exempted')
                                            <span class="status-pill pill-passed">✓ Done</span>
                                        @elseif($modRow['progress_status'] === 'in_progress')
                                            <span class="status-pill pill-pending">In Progress</span>
                                        @else
                                            <span class="status-pill pill-none">—</span>
                                        @endif
                                        <div style="font-size:9px;color:#94a3b8;margin-top:3px;">
                                            @if($modRow['post_test_score'] !== null)
                                                Post: {{ $modRow['post_test_score'] }}%
                                            @elseif($modRow['pre_test_score'] !== null)
                                                Pre: {{ $modRow['pre_test_score'] }}%
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                                <td class="center">
                                    @if($row['videos_passed'] === $row['modules_total'] && $row['modules_total'] > 0)
                                        <span class="status-pill pill-passed">Passed</span>
                                    @elseif($row['videos_pending'] > 0)
                                        <span class="status-pill pill-pending">{{ $row['videos_pending'] }} pending</span>
                                    @else
                                        <span class="status-pill pill-none">—</span>
                                    @endif
                                </td>
                                <td class="center">
                                    @if($row['mentor_approved'])
                                        <span class="status-pill pill-passed">Approved</span>
                                    @else
                                        <span class="status-pill pill-pending">Pending</span>
                                    @endif
                                </td>
                                <td class="center">
                                    @if($row['certified'])
                                        <span class="status-pill pill-passed">Certified</span>
                                    @elseif($row['head_drmh_approved'])
                                        <span class="status-pill pill-passed">Signed</span>
                                    @else
                                        <span class="status-pill pill-pending">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Attendance table --}}
        <div class="table-section">
            <table>
                <thead>
                    <tr>
                        <th style="width:28px">#</th>
                        <th>Mentee</th>
                        <th>Cadre</th>
                    @foreach($modules as $i => $module)
                            <th class="center" title="{{ $module->programModule->name ?? '' }}">M{{ $i+1 }}</th>
                        @endforeach
                        <th class="center" style="width:90px">Attendance</th>
                        <th class="center" style="width:70px">Status</th>
                    @if(!isset($isPdf) || !$isPdf)
                            <th class="center" style="width:80px">Certificate</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($mentees as $i => $row)
                        <tr class="{{ $row['class_complete'] ? 'completed-row' : '' }}">
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <div class="mentee-name">{{ $row['user']->name ?? ($row['user']->first_name . ' ' . $row['user']->last_name) }}</div>
                                <div class="mentee-meta">{{ $row['user']->email ?? '' }}</div>
                            </td>
                            <td>{{ $row['user']->cadre->name ?? '—' }}</td>

                            @foreach($modules as $module)
                                @php
                                $prog = $row['progress']->get($module->id);
                                $status = $prog ? $prog->status : 'not_started';
                                $icon = match($status) {
                                    'completed'  => '✅',
                                    'in_progress'=> '🔵',
                                    'exempted'   => '⭐',
                                    default      => '⬜',
                                };
                            @endphp
                                <td class="mod-cell">{{ $icon }}</td>
                            @endforeach

                            <td class="center">
                                @php
                                $pct = $row['attendance_pct'];
                                $barColor = $pct >= 80 ? '#16a34a' : ($pct >= 60 ? '#d97706' : '#dc2626');
                            @endphp
                                <div class="pct-wrap">
                                    <div class="pct-bar-bg">
                                        <div class="pct-bar-fill" style="width:{{ $pct }}%; background:{{ $barColor }};"></div>
                                    </div>
                                    <span class="pct-val" style="color:{{ $barColor }}">{{ $pct }}%</span>
                                </div>
                            </td>

                            <td class="center">
                                @if($row['class_complete'])
                                    <span class="badge badge-completed">Completed</span>
                                @elseif($row['participant']->status === 'active' || $row['participant']->status === 'enrolled')
                                    <span class="badge badge-active">In Progress</span>
                                @else
                                    <span class="badge badge-enrolled">Enrolled</span>
                                @endif
                            </td>

                            @if(!isset($isPdf) || !$isPdf)
                                <td class="center">
                                    @if($row['class_complete'])
                                        <div style="display:flex;flex-direction:column;gap:4px;align-items:center;">
                                            <a href="{{ route('reports.class.certificate.preview', [$class->id, $row['participant']->id]) }}"
                                       target="_blank"
                                       style="font-size:10px;color:#4f46e5;text-decoration:none;font-weight:600;
                                       background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;
                                       padding:2px 8px;display:inline-block;">
                                        👁 View
                                            </a>
                                            <a href="{{ route('reports.class.certificate', [$class->id, $row['participant']->id]) }}"
                                       style="font-size:10px;color:#16a34a;text-decoration:none;font-weight:600;
                                       background:#f0fdf4;border:1px solid #bbf7d0;border-radius:5px;
                                       padding:2px 8px;display:inline-block;">
                                        ⬇ PDF
                                            </a>
                                        </div>
                                    @else
                                        <span style="color:#94a3b8;font-size:10px;">—</span>
                                    @endif
                            </td>
@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="report-footer">
            <div>
                    MNCH Mentorship Platform &nbsp;·&nbsp; Ministry of Health, Kenya &nbsp;·&nbsp; Confidential
            </div>
            <div>
                    ✅ Completed &nbsp; 🔵 In Progress &nbsp; ⭐ Exempted &nbsp; ⬜ Not Started
            </div>
            <div>
                Page 1 of 1 &nbsp;·&nbsp; {{ now()->format('d M Y') }}
            </div>
        </div>

</div>
</body>
</html>