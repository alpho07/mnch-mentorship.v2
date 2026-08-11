<x-filament-panels::page>
    <style>
        /* ── Reset & Base ─────────────────────────────────────────────────────── */
        .md-container {
            font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
        }

        /* ── Profile Card ─────────────────────────────────────────────────────── */
        .md-profile-card {
            background: linear-gradient(135deg, #1e3a5f 0%, #1d4ed8 50%, #4f46e5 100%);
            border-radius: 20px;
            padding: 28px 32px;
            display: flex;
            align-items: center;
            gap: 24px;
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .md-profile-card::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
        }
        .md-profile-card::after {
            content: '';
            position: absolute;
            bottom: -40px;
            right: 100px;
            width: 140px;
            height: 140px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }
        .md-avatar {
            width: 80px;
            height: 80px;
            min-width: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: 3px solid rgba(255,255,255,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
        }
        .md-profile-info {
            flex: 1;
            position: relative;
            z-index: 1;
        }
        .md-profile-name {
            color: #fff;
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 4px;
            line-height: 1.2;
        }
        .md-profile-sub {
            color: rgba(255,255,255,0.8);
            font-size: 13px;
            margin: 0 0 12px;
        }
        .md-profile-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .md-tag {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 100px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .md-presence-badge {
            position: relative;
            z-index: 1;
            text-align: center;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            padding: 16px 20px;
            min-width: 120px;
        }
        .md-presence-rate {
            font-size: 32px;
            font-weight: 900;
            color: #fff;
            line-height: 1;
            margin-bottom: 4px;
        }
        .md-presence-label {
            font-size: 11px;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .md-presence-status {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 100px;
            background: rgba(255,255,255,0.2);
            color: #fff;
            display: inline-block;
        }

        /* ── Stat Grid ────────────────────────────────────────────────────────── */
        .md-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .md-stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            transition: box-shadow .15s ease, transform .15s ease;
        }
        .dark .md-stat-card {
            background: #1e293b;
            border-color: #334155;
        }
        .md-stat-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        .md-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .md-stat-value {
            font-size: 26px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 3px;
        }
        .md-stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            line-height: 1.4;
        }
        .dark .md-stat-label {
            color: #94a3b8;
        }

        /* ── Section Header ───────────────────────────────────────────────────── */
        .md-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .md-section-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dark .md-section-title {
            color: #f1f5f9;
        }

        /* ── Two-column Layout ────────────────────────────────────────────────── */
        .md-two-col {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 1100px) {
            .md-two-col {
                grid-template-columns: 1fr;
            }
        }

        /* ── Class Card ───────────────────────────────────────────────────────── */
        .md-class-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .dark .md-class-card {
            background: #1e293b;
            border-color: #334155;
        }
        .md-class-header {
            padding: 18px 22px 14px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        .dark .md-class-header {
            border-bottom-color: #334155;
        }
        .md-class-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 5px;
            flex-shrink: 0;
        }
        .md-class-info {
            flex: 1;
        }
        .md-class-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px;
        }
        .dark .md-class-name {
            color: #f1f5f9;
        }
        .md-class-meta {
            font-size: 12px;
            color: #64748b;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .md-class-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 100px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .md-progress-row {
            padding: 12px 22px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .md-progress-bar-wrap {
            flex: 1;
            height: 8px;
            background: #f1f5f9;
            border-radius: 100px;
            overflow: hidden;
        }
        .dark .md-progress-bar-wrap {
            background: #334155;
        }
        .md-progress-bar-fill {
            height: 100%;
            border-radius: 100px;
            background: linear-gradient(90deg, #1d4ed8, #4f46e5);
            transition: width .5s ease;
        }
        .md-progress-pct {
            font-size: 13px;
            font-weight: 700;
            color: #1d4ed8;
            min-width: 38px;
            text-align: right;
        }

        /* ── Module List ──────────────────────────────────────────────────────── */
        .md-module-list {
            padding: 0 22px 18px;
        }
        .md-module-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .dark .md-module-row {
            border-bottom-color: #1e293b;
        }
        .md-module-row:last-child {
            border-bottom: none;
        }
        .md-module-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .md-module-name {
            flex: 1;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.4;
        }
        .dark .md-module-name {
            color: #e2e8f0;
        }
        .md-module-desc {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 1px;
            font-weight: 400;
        }
        .md-module-status {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 100px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        .md-attend-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 8px;
            background: linear-gradient(135deg, #1d4ed8, #4f46e5);
            color: #fff;
            text-decoration: none;
            white-space: nowrap;
            transition: opacity .15s;
        }
        .md-attend-btn:hover {
            opacity: 0.88;
            color: #fff;
        }

        /* ── Sidebar cards ────────────────────────────────────────────────────── */
        .md-sidebar-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .dark .md-sidebar-card {
            background: #1e293b;
            border-color: #334155;
        }
        .md-sidebar-header {
            padding: 14px 18px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dark .md-sidebar-header {
            color: #f1f5f9;
            border-bottom-color: #334155;
        }
        .md-sidebar-body {
            padding: 14px 18px;
        }

        /* ── Recommendation card ──────────────────────────────────────────────── */
        .md-rec-card {
            background: #fafbff;
            border: 1px solid #e0e7ff;
            border-left: 4px solid #4f46e5;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 10px;
        }
        .dark .md-rec-card {
            background: #1e1b4b22;
            border-color: #4338ca55;
            border-left-color: #4f46e5;
        }
        .md-rec-card:last-child {
            margin-bottom: 0;
        }
        .md-rec-module {
            font-size: 12px;
            font-weight: 700;
            color: #3730a3;
            margin-bottom: 5px;
        }
        .dark .md-rec-module {
            color: #818cf8;
        }
        .md-rec-text {
            font-size: 12px;
            color: #475569;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .dark .md-rec-text {
            color: #94a3b8;
        }
        .md-rec-date {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 5px;
        }

        /* ── Activity Feed ────────────────────────────────────────────────────── */
        .md-feed-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .dark .md-feed-item {
            border-bottom-color: #334155;
        }
        .md-feed-item:last-child {
            border-bottom: none;
        }
        .md-feed-icon {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-top: 2px;
        }
        .md-feed-text {
            font-size: 12.5px;
            color: #475569;
            line-height: 1.5;
            flex: 1;
        }
        .dark .md-feed-text {
            color: #94a3b8;
        }
        .md-feed-text strong {
            color: #0f172a;
            font-weight: 600;
        }
        .dark .md-feed-text strong {
            color: #e2e8f0;
        }
        .md-feed-time {
            font-size: 10.5px;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* ── Suggested modules ────────────────────────────────────────────────── */
        .md-suggest-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .dark .md-suggest-item {
            border-bottom-color: #334155;
        }
        .md-suggest-item:last-child {
            border-bottom: none;
        }
        .md-suggest-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #dbeafe, #ede9fe);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .md-suggest-name {
            flex: 1;
            font-size: 12.5px;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.4;
        }
        .dark .md-suggest-name {
            color: #e2e8f0;
        }

        /* ── Empty state ──────────────────────────────────────────────────────── */
        .md-empty {
            text-align: center;
            padding: 48px 24px;
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 16px;
        }
        .dark .md-empty {
            background: #1e293b;
            border-color: #334155;
        }
        .md-empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .md-empty-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .dark .md-empty-title {
            color: #f1f5f9;
        }
        .md-empty-sub {
            font-size: 14px;
            color: #64748b;
        }
        .dark .md-empty-sub {
            color: #94a3b8;
        }

        /* ── Velocity badge ───────────────────────────────────────────────────── */
        .md-velocity-fast   {
            background: #dcfce7;
            color: #15803d;
        }
        .md-velocity-steady {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .md-velocity-slow   {
            background: #fef9c3;
            color: #854d0e;
        }
        .md-velocity-inactive {
            background: #f1f5f9;
            color: #64748b;
        }

        /* ── Donut mini chart ─────────────────────────────────────────────────── */
        .md-donut-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 8px 0;
        }
        .md-donut {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            flex-shrink: 0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .md-donut-pct {
            font-size: 14px;
            font-weight: 800;
            color: #1d4ed8;
            position: absolute;
        }
        .md-legend-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #475569;
            margin-bottom: 4px;
        }
        .dark .md-legend-row {
            color: #94a3b8;
        }
        .md-legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .md-legend-val {
            font-weight: 700;
            margin-left: auto;
            color: #1e293b;
        }
        .dark .md-legend-val {
            color: #e2e8f0;
        }

        /* ── Collapsed modules toggle ─────────────────────────────────────────── */
        .md-toggle-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            color: #1d4ed8;
            font-weight: 600;
            padding: 0 22px 14px;
            display: block;
            width: 100%;
            text-align: left;
        }
        .dark .md-toggle-btn {
            color: #818cf8;
        }
        .md-collapsible {
            display: none;
        }
        .md-collapsible.open {
            display: block;
        }

        /* ── Accordion header hover ──────────────────────────────────────────── */
        .md-accordion-trigger:hover {
            background: #f8fafc;
        }
        .dark .md-accordion-trigger:hover {
            background: #1e293b;
        }

        /* ── Responsive ───────────────────────────────────────────────────────── */
        @media (max-width: 640px) {
            .md-profile-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
            }
            .md-presence-badge {
                width: 100%;
                text-align: left;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .md-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .md-two-col {
                grid-template-columns: 1fr;
            }
            .md-profile-name {
                font-size: 18px;
            }
        }

        /* ── Hero next-action card ────────────────────────────────────────────── */
        .md-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            border-radius: 18px;
            padding: 22px 26px;
            margin-bottom: 14px;
            text-decoration: none;
            transition: transform .15s ease;
        }
        .md-hero:hover {
            transform: translateY(-1px);
        }
        .md-hero-urgent {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 1px solid #fca5a5;
        }
        .md-hero-active {
            background: linear-gradient(135deg, #dbeafe, #e0e7ff);
            border: 1px solid #93c5fd;
        }
        .md-hero-clear {
            background: linear-gradient(135deg, #dcfce7, #d1fae5);
            border: 1px solid #86efac;
        }
        .md-hero-headline {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .md-hero-subtext {
            font-size: 13px;
            color: #475569;
            font-weight: 500;
        }
        .md-hero-action {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            padding: 10px 18px;
            border-radius: 10px;
            background: rgba(255,255,255,0.6);
        }

        /* ── Secondary strip ──────────────────────────────────────────────────── */
        .md-secondary-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 12.5px;
            color: #64748b;
            margin-bottom: 20px;
            padding: 0 4px;
        }
        .dark .md-secondary-strip {
            color: #94a3b8;
        }
        .md-secondary-item strong {
            color: #1e293b;
            font-weight: 800;
        }
        .dark .md-secondary-item strong {
            color: #f1f5f9;
        }

        /* ── Collapsible sections ─────────────────────────────────────────────── */
        .md-collapse-section {
            margin-bottom: 16px;
        }
        .md-collapse-summary {
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            padding: 10px 4px;
            list-style: none;
            user-select: none;
        }
        .dark .md-collapse-summary {
            color: #94a3b8;
        }
        .md-collapse-summary::-webkit-details-marker {
            display: none;
        }
        .md-collapse-summary::before {
            content: '▸ ';
        }
        details[open] > .md-collapse-summary::before {
            content: '▾ ';
        }
        .md-collapse-body {
            padding-top: 6px;
        }
    </style>

    <div class="md-container">

        {{-- ══ PROFILE CARD ══════════════════════════════════════════════════════ --}}
        <div class="md-profile-card">
            {{-- Avatar --}}
            <div class="md-avatar">{{ $profile['initials'] }}</div>

            {{-- Info --}}
            <div class="md-profile-info">
                <h2 class="md-profile-name">{{ $profile['name'] }}</h2>
                <p class="md-profile-sub">
                    {{ $profile['email'] }}
            @if($profile['phone'])  · {{ $profile['phone'] }} @endif
                </p>
                <div class="md-profile-tags">
                    @if($profile['cadre'] !== '—')
                        <span class="md-tag">🩺 {{ $profile['cadre'] }}</span>
                    @endif
            @if($profile['department'] !== '—')
                        <span class="md-tag">🏢 {{ $profile['department'] }}</span>
                    @endif
            @if($profile['facility'] !== '—')
                        <span class="md-tag">🏥 {{ $profile['facility'] }}</span>
                    @endif
            @if($profile['county'] !== '—')
                        <span class="md-tag">📍 {{ $profile['county'] }}</span>
                    @endif
                    <span class="md-tag">🗓 Since {{ $profile['joined'] }}</span>
                    {{-- Velocity --}}
                    @php $vel = $globalStats['learning_velocity'] ?? 'Inactive'; $vClass = 'md-velocity-' . strtolower($vel); @endphp
                    <span class="md-tag {{ $vClass }}" style="border: none;">
                        ⚡ {{ $vel }} learner
                    </span>
            @if(($globalStats['streak_days'] ?? 0) > 0)
                        <span class="md-tag" style="background:rgba(251,191,36,0.25);border-color:rgba(251,191,36,0.4);">
                            🔥 {{ $globalStats['streak_days'] }}-day streak
                        </span>
                    @endif
                </div>
            </div>

    {{-- Presence badge --}}
            <div class="md-presence-badge">
                <div class="md-presence-rate">{{ $globalStats['attendance_rate'] ?? 0 }}%</div>
                <div class="md-presence-label">Attendance</div>
                <span class="md-presence-status">{{ $globalStats['presence_status']['label'] ?? 'No Data' }}</span>
            </div>
        </div>

{{-- ══ PENDING ENROLLMENT ══════════════════════════════════════════════════ --}}
        @php
            $pendingEnrollment = session('enrollment_intent');
        @endphp
        @if(is_array($pendingEnrollment) && ! empty($pendingEnrollment['class_id']))
            @php
                $pendingClass = \App\Models\MentorshipClass::with('training')->find($pendingEnrollment['class_id']);
            @endphp
            @if($pendingClass)
                <div class="md-card" style="background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 100%);border:1px solid #bbf7d0;margin-bottom:24px;">
                    <div style="display:flex;align-items:flex-start;gap:16px;">
                        <div style="width:44px;height:44px;border-radius:12px;background:#22c55e;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px;">⏳</div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:15px;font-weight:800;color:#14532d;">Pending Enrollment</div>
                            <div style="font-size:13px;color:#166534;margin-top:2px;">
                                You started joining <strong>{{ $pendingClass->name }}</strong> but didn't complete login.
                                @if(! empty($pendingEnrollment['email']))
                                    <span style="color:#15803d;">Email: {{ $pendingEnrollment['email'] }}</span>
                                @endif
                            </div>
                            <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                                <a href="{{ route('filament.admin.auth.login') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;background:#22c55e;color:#fff;font-size:12px;font-weight:700;text-decoration:none;transition:background .15s;">
                                    Complete Enrollment →
                                </a>
                                <button type="button" wire:click="clearPendingEnrollment" style="padding:8px 16px;border-radius:8px;background:#fff;border:1px solid #bbf7d0;color:#166534;font-size:12px;font-weight:600;cursor:pointer;">
                                    Dismiss
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if(empty($enrollments))
        <div class="md-empty">
                <div class="md-empty-icon">🎓</div>
                <div class="md-empty-title">You're not enrolled in any classes yet</div>
            <div class="md-empty-sub">Your mentor will send you an enrollment invitation. Check your email inbox.</div>
        </div>
        @else

        {{-- ══ HERO: NEXT BEST ACTION ═══════════════════════════════════════════ --}}
        @php
            $tier = $nextAction['tier'] ?? 6;
            $heroClass = $tier <= 2 ? 'md-hero-urgent' : ($tier <= 5 ? 'md-hero-active' : 'md-hero-clear');
        @endphp
        @if(!empty($nextAction))
            <a href="{{ $nextAction['url'] }}" class="md-hero {{ $heroClass }}">
                <div class="md-hero-text">
                    <div class="md-hero-headline">{{ $nextAction['headline'] }}</div>
                    <div class="md-hero-subtext">{{ $nextAction['subtext'] }}</div>
                </div>
                <div class="md-hero-action">{{ $nextAction['label'] }} →</div>
            </a>
        @endif

        {{-- ── Secondary strip ──────────────────────────────────────────────── --}}
        <div class="md-secondary-strip">
            <div class="md-secondary-item">
                <strong>{{ $globalStats['pending_attendance'] ?? 0 }}</strong> pending attendance
            </div>
            <div class="md-secondary-item">
                <strong>{{ count($recommendations) }}</strong> mentor feedback item{{ count($recommendations) !== 1 ? 's' : '' }}
            </div>
            <div class="md-secondary-item">
                <strong>{{ $globalStats['completion_rate'] ?? 0 }}%</strong> overall completion
            </div>
        </div>

        {{-- ══ GLOBAL STATS ═══════════════════════════════════════════════════════ --}}
        <details class="md-collapse-section" id="md-details-progress" open>
            <summary class="md-collapse-summary">📊 My Progress</summary>
            <div class="md-collapse-body">
@php
$stats = $globalStats;
$cards = [
    ['icon' => '📚', 'value' => $stats['total_classes'],       'label' => 'Classes Enrolled',   'bg' => '#eff6ff', 'ic' => '#dbeafe', 'vc' => '#1d4ed8'],
    ['icon' => '✅', 'value' => $stats['completed_classes'],   'label' => 'Classes Completed',   'bg' => '#f0fdf4', 'ic' => '#dcfce7', 'vc' => '#16a34a'],
    ['icon' => '🔵', 'value' => $stats['active_classes'],      'label' => 'Classes Active',      'bg' => '#f0f9ff', 'ic' => '#e0f2fe', 'vc' => '#0284c7'],
    ['icon' => '🧩', 'value' => $stats['total_modules'],       'label' => 'Total Modules',       'bg' => '#faf5ff', 'ic' => '#ede9fe', 'vc' => '#7c3aed'],
    ['icon' => '🏆', 'value' => $stats['completed_modules'],   'label' => 'Modules Completed',   'bg' => '#f0fdf4', 'ic' => '#dcfce7', 'vc' => '#16a34a'],
    ['icon' => '⏳', 'value' => $stats['in_progress_modules'], 'label' => 'Modules In Progress', 'bg' => '#fffbeb', 'ic' => '#fef9c3', 'vc' => '#d97706'],
    ['icon' => '📋', 'value' => $stats['pending_attendance'],  'label' => 'Pending Attendance',  'bg' => '#fef2f2', 'ic' => '#fee2e2', 'vc' => '#dc2626'],
    ['icon' => '⭐', 'value' => $stats['exempted_modules'],    'label' => 'Modules Exempted',    'bg' => '#f8fafc', 'ic' => '#f1f5f9', 'vc' => '#475569'],
];
        @endphp

        <div class="md-stats-grid">
            @foreach($cards as $c)
                <div class="md-stat-card">
                    <div class="md-stat-icon" style="background:{{ $c['ic'] }}">{{ $c['icon'] }}</div>
                    <div>
                        <div class="md-stat-value" style="color:{{ $c['vc'] }}">{{ $c['value'] }}</div>
                        <div class="md-stat-label">{{ $c['label'] }}</div>
                    </div>
            </div>
        @endforeach
        </div>
            </div>
        </details>

{{-- ══ MAIN CONTENT + SIDEBAR ══════════════════════════════════════════════ --}}
        <details class="md-collapse-section" id="md-details-classes" open>
            <summary class="md-collapse-summary">📚 My Classes</summary>
            <div class="md-collapse-body">

        <div class="md-two-col">

    {{-- ── LEFT: Classes (accordion) ────────────────────────────────── --}}
            <div>
                <div class="md-section-header">
                    <div class="md-section-title">
                        <span style="background:#eff6ff;color:#1d4ed8;width:26px;height:26px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;">📚</span>
                        My Classes
                    </div>
                    <span style="font-size:12px;color:#94a3b8;">{{ count($enrollments) }} enrollment{{ count($enrollments) !== 1 ? 's' : '' }}</span>
                </div>

                @foreach($enrollments as $i => $enrollment)
                    @include('filament.components.mentee-class-card', [
                        'enrollment'  => $enrollment,
                        'highlight'   => $enrollment['class_status'] === 'active',
                        'defaultOpen' => $i === 0,
                    ])
                @endforeach
            </div>

    {{-- ── RIGHT: Sidebar ─────────────────────────────────────────────── --}}
            <div>

        {{-- Progress Donut --}}
                <div class="md-sidebar-card">
                    <div class="md-sidebar-header">
                        📊 Overall Progress
                    </div>
                    <div class="md-sidebar-body">
                @php
                    $total     = $stats['total_modules'] ?: 1;
                    $done      = $stats['completed_modules'];
                    $inProg    = $stats['in_progress_modules'];
                    $notStart  = $stats['not_started_modules'];
                    $exempted  = $stats['exempted_modules'];
                    $donePct   = round($done / $total * 100);
                    $inProgPct = round($inProg / $total * 100);

                    $conic = "conic-gradient(
                        #16a34a 0% {$donePct}%,
                        #f59e0b {$donePct}% " . ($donePct + $inProgPct) . "%,
                        #e2e8f0 " . ($donePct + $inProgPct) . "% 100%
                    )";
                @endphp
                        <div class="md-donut-wrap">
                            <div class="md-donut" style="background: {{ $conic }};">
                                <div style="width:50px;height:50px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                    <span class="md-donut-pct">{{ $stats['completion_rate'] }}%</span>
                                </div>
                            </div>
                            <div style="flex:1">
                                <div class="md-legend-row">
                                    <span class="md-legend-dot" style="background:#16a34a"></span>
                                    Completed
                                    <span class="md-legend-val">{{ $done }}</span>
                                </div>
                                <div class="md-legend-row">
                                    <span class="md-legend-dot" style="background:#f59e0b"></span>
                                    In Progress
                                    <span class="md-legend-val">{{ $inProg }}</span>
                                </div>
                                <div class="md-legend-row">
                                    <span class="md-legend-dot" style="background:#94a3b8"></span>
                                    Not Started
                                    <span class="md-legend-val">{{ $notStart }}</span>
                                </div>
                        @if($exempted > 0)
                                <div class="md-legend-row">
                                    <span class="md-legend-dot" style="background:#818cf8"></span>
                                    Exempted
                                    <span class="md-legend-val">{{ $exempted }}</span>
                                </div>
                        @endif
                            </div>
                        </div>

                {{-- Attendance bar --}}
                        <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;">
                            <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:6px;">
                                <span>Attendance Rate</span>
                                <strong style="color:{{ $stats['presence_status']['color'] }}">
                            {{ $stats['attendance_rate'] }}% — {{ $stats['presence_status']['label'] }}
                                </strong>
                            </div>
                            <div style="height:8px;background:#f1f5f9;border-radius:100px;overflow:hidden;">
                                <div style="height:100%;width:{{ $stats['attendance_rate'] }}%;background:{{ $stats['presence_status']['color'] }};border-radius:100px;transition:width .5s ease;"></div>
                            </div>
                        </div>
                    </div>
                </div>

        {{-- Pending Attendance Actions --}}
        @if(!empty($suggestedModules))
                <div class="md-sidebar-card">
                    <div class="md-sidebar-header" style="color:#dc2626;">
                        🔔 Action Required
                        <span style="background:#fef2f2;color:#dc2626;font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px;margin-left:auto;">{{ count($suggestedModules) }} pending</span>
                    </div>
                    <div class="md-sidebar-body" style="padding-bottom:8px;">
                @foreach($suggestedModules as $mod)
                        <div class="md-suggest-item">
                            <div class="md-suggest-icon">📌</div>
                            <div>
                                <div class="md-suggest-name">{{ $mod['name'] }}</div>
                                <div style="font-size:11px;color:#94a3b8;">Attendance link is open</div>
                            </div>
                            <a href="{{ route('module.attend', ['token' => $mod['attendance_token']]) }}"
                               class="md-attend-btn">
                                ✓ Confirm
                            </a>
                        </div>
                @endforeach
                    </div>
                </div>
        @endif

        {{-- Mentor Recommendations + Video Review Feedback --}}
        @if(!empty($recommendations))
                <div class="md-sidebar-card">
                    <div class="md-sidebar-header">📋 Mentor Feedback</div>
                    <div class="md-sidebar-body" style="padding-bottom:8px;">
                @foreach($recommendations as $rec)
                        <div class="md-rec-card">
                            <div class="md-rec-module">
                                {{ $rec['name'] }}
                                @if($rec['type'] === 'video_review')
                                    <span style="margin-left:6px;font-size:9px;font-weight:700;padding:2px 7px;border-radius:100px;background:#fee2e2;color:#b91c1c;text-transform:uppercase;letter-spacing:.04em;">Needs Revision</span>
                                @endif
                            </div>
                            <div class="md-rec-text">{{ $rec['text'] }}</div>
                    @if($rec['at'])
                            <div class="md-rec-date">{{ $rec['at'] }}</div>
                    @endif
                        </div>
                @endforeach
                    </div>
                </div>
        @endif

        {{-- Activity Feed --}}
        @if(!empty($activityFeed))
                <div class="md-sidebar-card" x-data="{ expanded: false }">
                    <div class="md-sidebar-header">🕐 Recent Activity</div>
                    <div class="md-sidebar-body" style="padding-bottom:8px;">
                @foreach(array_slice($activityFeed, 0, 5) as $item)
                        <div class="md-feed-item">
                            <div class="md-feed-icon"
                                 style="background:{{ match($item['color']) {
                             'green'  => '#dcfce7',
                             'blue'   => '#dbeafe',
                             'purple' => '#ede9fe',
                             'indigo' => '#e0e7ff',
                             default  => '#f1f5f9',
                         } }}">{{ $item['icon'] }}</div>
                            <div style="flex:1">
                                <div class="md-feed-text">{!! $item['text'] !!}</div>
                                <div class="md-feed-time">{{ $item['time'] }}</div>
                            </div>
                        </div>
                @endforeach
                @if(count($activityFeed) > 5)
                    <div x-show="expanded" x-collapse>
                    @foreach(array_slice($activityFeed, 5) as $item)
                            <div class="md-feed-item">
                                <div class="md-feed-icon"
                                     style="background:{{ match($item['color']) {
                                 'green'  => '#dcfce7',
                                 'blue'   => '#dbeafe',
                                 'purple' => '#ede9fe',
                                 'indigo' => '#e0e7ff',
                                 default  => '#f1f5f9',
                             } }}">{{ $item['icon'] }}</div>
                                <div style="flex:1">
                                    <div class="md-feed-text">{!! $item['text'] !!}</div>
                                    <div class="md-feed-time">{{ $item['time'] }}</div>
                                </div>
                            </div>
                    @endforeach
                    </div>
                    <button
                        @click="expanded = !expanded"
                        style="display:block;width:100%;margin-top:8px;padding:7px 0;background:none;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;color:#3b82f6;cursor:pointer;transition:background .15s;"
                        onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='none'"
                        x-text="expanded ? 'Show Less' : 'View More ({{ count($activityFeed) - 5 }} more)'">
                    </button>
                @endif
                    </div>
                </div>
        @endif

            </div>{{-- /sidebar --}}
        </div>{{-- /two-col --}}
            </div>{{-- /md-collapse-body --}}
        </details>
        @endif

    </div>{{-- /container --}}

{{-- ── Inline partial: class card ──────────────────────────────────────────── --}}
@push('scripts')
    <script>
    function toggleClassCard(id) {
        const body = document.getElementById('class-body-' + id);
        const icon = document.getElementById('class-toggle-icon-' + id);
        if (!body) return;
        const isOpen = body.style.display !== 'none';
        body.style.display = isOpen ? 'none' : 'block';
        if (icon) {
            icon.style.transform = isOpen ? 'rotate(180deg)' : '';
        }
    }

    // "Memory foam" — remembers whichever open/closed state the mentee last
    // left each top-level section in, instead of always resetting to the
    // server-rendered default (both open) on every visit.
    (function () {
        const sections = ['md-details-progress', 'md-details-classes'];
        sections.forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            const storageKey = 'mnch_' + id + '_open';
            const saved = localStorage.getItem(storageKey);
            if (saved !== null) {
                el.open = saved === '1';
            }
            el.addEventListener('toggle', function () {
                localStorage.setItem(storageKey, el.open ? '1' : '0');
            });
        });
    })();
    </script>
@endpush
</x-filament-panels::page>