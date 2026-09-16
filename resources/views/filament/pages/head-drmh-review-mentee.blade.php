<x-filament-panels::page>
@php
$mentee   = $participant->user;
$class    = $participant->mentorshipClass;
$training = $class?->training;
$initials = strtoupper(substr($mentee?->first_name ?? 'M', 0, 1) . substr($mentee?->last_name ?? 'E', 0, 1));
@endphp

{{-- Alpine confirmation modal wrapper --}}
<div
    x-data="{
        open: false, action: '', title: '', body: '', confirmLabel: 'Confirm',
        confirmColor: '#059669', iconBg: '#d1fae5', iconColor: '#059669',
        iconPath: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        openConfirm(action, title, body, confirmLabel, variant) {
            this.action = action;
            this.title = title;
            this.body = body;
            this.confirmLabel = confirmLabel;
            if (variant === 'danger') {
                this.confirmColor = '#dc2626'; this.iconBg = '#fee2e2'; this.iconColor = '#dc2626';
                this.iconPath = 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z';
            } else {
                this.confirmColor = '#059669'; this.iconBg = '#d1fae5'; this.iconColor = '#059669';
                this.iconPath = 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
            }
            this.open = true;
        },
        confirm() { this.open = false; $wire[this.action](); }
    }"
    x-cloak
    style="font-family:'Inter',system-ui,sans-serif;"
>

{{-- ── Modal overlay ──────────────────────────────────────────────────────── --}}
<div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(3px);" x-on:click="open=false"></div>
    <div x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         style="position:relative;background:#fff;border-radius:20px;padding:32px;max-width:440px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,0.22);">
        <div style="display:flex;align-items:flex-start;gap:16px;">
            <div :style="`width:44px;height:44px;border-radius:12px;background:${iconBg};display:flex;align-items:center;justify-content:center;flex-shrink:0;`">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" :stroke="iconColor" style="width:22px;height:22px;">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath"/>
                </svg>
            </div>
            <div style="flex:1;">
                <h3 x-text="title" style="font-size:17px;font-weight:800;color:#111827;margin:0 0 6px;"></h3>
                <p x-text="body" style="font-size:13px;color:#6b7280;margin:0;line-height:1.5;"></p>
            </div>
        </div>
        <div style="margin-top:24px;display:flex;gap:10px;justify-content:flex-end;">
            <button x-on:click="open=false"
                    style="padding:10px 20px;border:1.5px solid #e5e7eb;border-radius:10px;background:#fff;color:#374151;font-size:14px;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button x-on:click="confirm()"
                    :style="`padding:10px 22px;border:none;border-radius:10px;background:${confirmColor};color:#fff;font-size:14px;font-weight:700;cursor:pointer;`">
                <span x-text="confirmLabel"></span>
            </button>
        </div>
    </div>
</div>

{{-- ═══ BACK LINK ═══════════════════════════════════════════════════════════ --}}
<div style="margin-bottom:16px;">
    <a href="{{ \App\Filament\Pages\HeadDrmhDashboard::getUrl() }}"
       style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#6b7280;text-decoration:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Back to Head DRMH Dashboard
    </a>
</div>

{{-- ═══ HERO BANNER ══════════════════════════════════════════════════════════ --}}
<div class="rv-animate" style="animation-delay:0s;border-radius:20px;overflow:hidden;margin-bottom:20px;background:linear-gradient(135deg,#0c1445 0%,#1a237e 45%,#283593 100%);position:relative;box-shadow:0 8px 32px rgba(12,20,69,.35);">
    <div style="position:absolute;top:-40px;right:-20px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,0.04);pointer-events:none;"></div>
    <div style="position:relative;padding:28px 32px;">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            {{-- Initials avatar --}}
            <div style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,0.15);border:2.5px solid rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span style="font-size:22px;font-weight:800;color:#fff;">{{ $initials }}</span>
            </div>
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
                    <h1 style="font-size:22px;font-weight:800;color:#fff;margin:0;letter-spacing:-0.3px;">{{ $mentee?->full_name ?? '—' }}</h1>
                    @if($isCertified)
                        <span style="background:#d1fae5;color:#065f46;border-radius:9999px;padding:3px 12px;font-size:12px;font-weight:700;">Certified</span>
                    @elseif(! $isEmonc)
                        @if($canCertify)
                            <span style="background:#fef3c7;color:#92400e;border-radius:9999px;padding:3px 12px;font-size:12px;font-weight:700;">All Modules Complete · Pending DRMH</span>
                        @else
                            <span style="background:#fee2e2;color:#991b1b;border-radius:9999px;padding:3px 12px;font-size:12px;font-weight:700;">Modules In Progress</span>
                        @endif
                    @elseif($participant->isMentorApproved())
                        <span style="background:#fef3c7;color:#92400e;border-radius:9999px;padding:3px 12px;font-size:12px;font-weight:700;">Mentor Approved · Pending DRMH</span>
                    @else
                        <span style="background:#fee2e2;color:#991b1b;border-radius:9999px;padding:3px 12px;font-size:12px;font-weight:700;">Not Mentor Approved</span>
                    @endif
                </div>
                <p style="font-size:13px;color:rgba(255,255,255,0.70);margin:0 0 4px;">
                    {{ $mentee?->cadre?->name ?? '—' }}
                    <span style="opacity:0.4;margin:0 5px;">·</span>
                    {{ $mentee?->department?->name ?? '—' }}
                    <span style="opacity:0.4;margin:0 5px;">·</span>
                    {{ $mentee?->facility?->name ?? '—' }}
                </p>
                <p style="font-size:12px;color:rgba(255,255,255,0.50);margin:0;">
                    {{ $class?->name ?? '—' }}
                    <span style="opacity:0.4;margin:0 5px;">·</span>
                    {{ $training?->title ?? '—' }}
                    <span style="opacity:0.4;margin:0 5px;">·</span>
                    {{ $training?->facility?->name ?? '—' }}
                </p>
            </div>
            {{-- Program badge --}}
            <div style="text-align:right;flex-shrink:0;">
                <p style="font-size:11px;color:rgba(255,255,255,0.45);margin:0 0 4px;text-transform:uppercase;letter-spacing:0.08em;">Program</p>
                <p style="font-size:14px;font-weight:700;color:#fff;margin:0;">{{ $training?->program?->name ?? '—' }}</p>
                <p style="font-size:11px;color:rgba(255,255,255,0.50);margin:3px 0 0;">Mentor: {{ $training?->mentor?->full_name ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>

{{-- ═══ APPROVAL STATUS CARD ════════════════════════════════════════════════ --}}
<div class="rv-animate" style="animation-delay:0.08s;margin-bottom:20px;">
    @if($isCertified)
    {{-- Already certified --}}
    <div style="background:#f0fdf4;border:1.5px solid #a7f3d0;border-radius:16px;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:44px;height:44px;border-radius:50%;background:#d1fae5;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#059669" style="width:22px;height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p style="font-size:15px;font-weight:800;color:#065f46;margin:0;">Certificate Issued</p>
                <p style="font-size:12px;color:#059669;margin:2px 0 0;">
                    Approved by {{ $participant->headDrmhApprovedBy?->full_name ?? 'Head DRMH' }}
                    on {{ $participant->head_drmh_approved_at ? \Carbon\Carbon::parse($participant->head_drmh_approved_at)->format('d M Y, H:i') : '' }}
                </p>
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('reports.class.certificate', ['class' => $class?->id, 'participant' => $participant->id]) }}"
               target="_blank"
               style="display:inline-flex;align-items:center;gap:7px;background:#059669;color:#fff;border:none;border-radius:11px;padding:11px 22px;font-size:13px;font-weight:700;text-decoration:none;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Download Certificate
            </a>
            @if(auth()->user()->hasRole(['super_admin','admin']))
            <button x-on:click="openConfirm('revertCertification','Revert Certification','This will remove the Head DRMH certification. The mentee will need to be re-approved.','Revert','danger')"
                    style="display:inline-flex;align-items:center;gap:6px;background:#fff;color:#dc2626;border:1.5px solid #fca5a5;border-radius:11px;padding:10px 18px;font-size:13px;font-weight:600;cursor:pointer;">
                Revert
            </button>
            @endif
        </div>
    </div>

    @elseif($canCertify)
    {{-- Ready to certify --}}
    <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:16px;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:44px;height:44px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#d97706" style="width:22px;height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
            </div>
            <div>
                <p style="font-size:15px;font-weight:800;color:#92400e;margin:0;">Ready for Final Certification</p>
                <p style="font-size:12px;color:#b45309;margin:2px 0 0;">
                    @if($isEmonc)
                        Mentor approved by {{ $participant->mentorApprovedBy?->full_name ?? '—' }}
                        on {{ $participant->mentor_approved_at ? \Carbon\Carbon::parse($participant->mentor_approved_at)->format('d M Y') : '' }}
                    @else
                        All {{ $training?->program?->name ?? 'program' }} modules are complete.
                    @endif
                </p>
            </div>
        </div>
        <button x-on:click="openConfirm('certify','Issue Certificate','Issue the final Head DRMH certificate to {{ addslashes($mentee?->full_name ?? '') }}? This action will generate a downloadable certificate and notify the mentee.','Issue Certificate')"
                style="display:inline-flex;align-items:center;gap:8px;background:#0c1445;color:#fff;border:none;border-radius:11px;padding:13px 28px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(12,20,69,.25);">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Issue Certificate
        </button>
    </div>

    @elseif($isEmonc)
    {{-- Mentor not yet approved (EmONC only) --}}
    <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:16px;padding:20px 24px;display:flex;align-items:center;gap:14px;">
        <div style="width:44px;height:44px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#dc2626" style="width:22px;height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        </div>
        <div>
            <p style="font-size:15px;font-weight:800;color:#991b1b;margin:0;">Mentor Approval Pending</p>
            <p style="font-size:12px;color:#dc2626;margin:2px 0 0;">The assigned mentor has not yet approved this mentee. Head DRMH certification is not available until mentor approval is complete.</p>
        </div>
    </div>
    @else
    {{-- Non-EmONC: modules not yet complete across the mentee's enrollments in this program --}}
    <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:16px;padding:20px 24px;display:flex;align-items:center;gap:14px;">
        <div style="width:44px;height:44px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#dc2626" style="width:22px;height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        </div>
        <div>
            <p style="font-size:15px;font-weight:800;color:#991b1b;margin:0;">Modules Not Yet Complete</p>
            <p style="font-size:12px;color:#dc2626;margin:2px 0 0;">This mentee hasn't completed every {{ $training?->program?->name ?? 'program' }} module yet. Head DRMH certification is not available until all modules are done.</p>
        </div>
    </div>
    @endif
</div>

{{-- ═══ TWO-COLUMN LAYOUT ════════════════════════════════════════════════════ --}}
<div style="display:grid;grid-template-columns:1fr 2fr;gap:18px;" class="rv-animate" style="animation-delay:0.14s;">

    {{-- Left column: Mentee info --}}
    <div style="display:flex;flex-direction:column;gap:16px;">

        {{-- Mentee profile --}}
        <div class="md-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 1px 5px rgba(0,0,0,.05);">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;background:#fafafa;">
                <p style="font-size:12px;font-weight:700;color:#374151;margin:0;text-transform:uppercase;letter-spacing:0.06em;">Mentee Profile</p>
            </div>
            <div style="padding:16px 18px;">
                <dl style="margin:0;display:grid;gap:10px;">
                    @foreach([
                        ['ID/No', $mentee?->id_number ?? '—'],
                        ['Cadre', $mentee?->cadre?->name ?? '—'],
                        ['Department', $mentee?->department?->name ?? '—'],
                        ['Facility', $mentee?->facility?->name ?? '—'],
                        ['Subcounty', $mentee?->facility?->subcounty?->name ?? '—'],
                        ['County', $mentee?->facility?->subcounty?->county?->name ?? '—'],
                        ['Phone', $mentee?->phone ?? '—'],
                        ['Email', $mentee?->email ?? '—'],
                    ] as [$label, $value])
                    <div style="display:flex;gap:8px;align-items:baseline;">
                        <dt style="font-size:11px;color:#9ca3af;min-width:80px;flex-shrink:0;">{{ $label }}</dt>
                        <dd style="font-size:12px;font-weight:600;color:#374151;margin:0;word-break:break-all;">{{ $value }}</dd>
                    </div>
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- Mentorship info --}}
        <div class="md-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 1px 5px rgba(0,0,0,.05);">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;background:#fafafa;">
                <p style="font-size:12px;font-weight:700;color:#374151;margin:0;text-transform:uppercase;letter-spacing:0.06em;">Mentorship</p>
            </div>
            <div style="padding:16px 18px;">
                <dl style="margin:0;display:grid;gap:10px;">
                    @foreach([
                        ['Program', $training?->program?->name ?? '—'],
                        ['Training', $training?->title ?? '—'],
                        ['Facility', $training?->facility?->name ?? '—'],
                        ['Class', $class?->name ?? '—'],
                        ['Status', ucfirst($training?->status ?? '—')],
                        ['Lead Mentor', $training?->mentor?->full_name ?? '—'],
                        ['Start', $training?->start_date?->format('d M Y') ?? '—'],
                        ['End', $training?->end_date?->format('d M Y') ?? '—'],
                    ] as [$label, $value])
                    <div style="display:flex;gap:8px;align-items:baseline;">
                        <dt style="font-size:11px;color:#9ca3af;min-width:80px;flex-shrink:0;">{{ $label }}</dt>
                        <dd style="font-size:12px;font-weight:600;color:#374151;margin:0;">{{ $value }}</dd>
                    </div>
                    @endforeach
                </dl>
            </div>
        </div>

    </div>

    {{-- Right column: Modules --}}
    <div style="display:flex;flex-direction:column;gap:16px;">

        @if(empty($modules))
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:40px 24px;text-align:center;">
            <p style="font-size:14px;color:#9ca3af;margin:0;">No modules found for this class.</p>
        </div>
        @endif

        @foreach($modules as $mod)
        @php
        $statusColor = match($mod['status']) {
            'completed'  => '#10b981',
            'in_progress'=> '#3b82f6',
            'exempted'   => '#8b5cf6',
            default      => '#d1d5db',
        };
        $statusBg = match($mod['status']) {
            'completed'  => '#ecfdf5',
            'in_progress'=> '#eff6ff',
            'exempted'   => '#f5f3ff',
            default      => '#f9fafb',
        };
        $videoStatus = $mod['video']['review_status'] ?? 'pending';
        $videoColor  = match($videoStatus) { 'passed' => '#10b981', 'failed' => '#ef4444', default => '#f59e0b' };
        $videoBg     = match($videoStatus) { 'passed' => '#ecfdf5', 'failed' => '#fef2f2', default => '#fffbeb' };
        @endphp

        <div class="md-card rv-animate" style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 1px 5px rgba(0,0,0,.05);">
            {{-- Module header --}}
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px;background:#fafafa;">
                <div style="width:28px;height:28px;border-radius:8px;background:{{ $statusBg }};border:1px solid {{ $statusColor }}20;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="{{ $statusColor }}" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h4 style="font-size:13px;font-weight:700;color:#111827;margin:0;flex:1;">{{ $mod['name'] }}</h4>
                <span style="background:{{ $statusBg }};color:{{ $statusColor }};border-radius:9999px;padding:2px 10px;font-size:11px;font-weight:700;">{{ ucfirst(str_replace('_',' ',$mod['status'])) }}</span>
            </div>

            @if($isEmonc)
            <div style="padding:16px 18px;display:grid;gap:14px;">

                {{-- Activities --}}
                @if(!empty($mod['activities']))
                <div>
                    <p style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 8px;">Activities</p>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach($mod['activities'] as $act)
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:9999px;font-size:11px;font-weight:600;background:{{ $act['completed'] ? '#d1fae5' : ($act['enrolled'] ? '#dbeafe' : '#f3f4f6') }};color:{{ $act['completed'] ? '#065f46' : ($act['enrolled'] ? '#1e40af' : '#6b7280') }};">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:10px;height:10px;">
                                @if($act['completed'])
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                @elseif($act['enrolled'])
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                @endif
                            </svg>
                            {{ $act['name'] }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Tests --}}
                <div>
                    <p style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 8px;">Knowledge Tests</p>
                    <div style="display:grid;gap:10px;">

                        @foreach([['pre_test','Pre-Test'], ['post_test','Post-Test']] as [$key, $label])
                        @php $t = $mod[$key]; @endphp
                        <div x-data="{ open: false }"
                             style="border:1.5px solid {{ $t['exists'] ? '#bfdbfe' : '#e5e7eb' }};border-radius:12px;overflow:hidden;background:{{ $t['exists'] ? '#eff6ff' : '#f9fafb' }};">

                            {{-- Score row (always visible, click to expand) — score only, no
                                 pass/fail language: that stays exclusive to the practical/
                                 rubric assessment below (team decision 2026-07-24). --}}
                            <div style="padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;{{ $t['exists'] && count($t['questions']) > 0 ? 'cursor:pointer;' : '' }}"
                                 @if($t['exists'] && count($t['questions']) > 0) x-on:click="open = !open" @endif>
                                <div style="display:flex;align-items:center;gap:14px;">
                                    <div>
                                        <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.08em;margin:0 0 4px;">{{ $label }}</p>
                                        @if($t['exists'])
                                            <div style="display:flex;align-items:baseline;gap:4px;">
                                                <p style="font-size:28px;font-weight:800;color:#2563eb;margin:0;line-height:1;">{{ number_format($t['score'] ?? 0, 0) }}</p>
                                                <p style="font-size:14px;font-weight:700;color:#2563eb;margin:0;">%</p>
                                            </div>
                                        @else
                                            <p style="font-size:13px;color:#9ca3af;margin:0;font-weight:500;">Not taken</p>
                                        @endif
                                    </div>
                                </div>
                                @if($t['exists'] && count($t['questions']) > 0)
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span style="font-size:11px;color:#6b7280;" x-text="open ? 'Hide answers' : 'View answers ({{ count($t['questions']) }} Qs)'"></span>
                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#6b7280" style="width:14px;height:14px;transition:transform 0.2s;" :style="open ? 'transform:rotate(180deg)' : ''">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                    </svg>
                                </div>
                                @endif
                            </div>

                            {{-- Q&A breakdown (collapsible) --}}
                            @if($t['exists'] && count($t['questions']) > 0)
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-2"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 style="border-top:1px solid {{ $t['passed'] ? '#bbf7d0' : '#fca5a5' }};background:#fff;">
                                @foreach($t['questions'] as $qi => $q)
                                <div style="padding:14px 16px;{{ !$loop->last ? 'border-bottom:1px solid #f3f4f6;' : '' }}">
                                    {{-- Question text --}}
                                    <p style="font-size:12px;font-weight:600;color:#111827;margin:0 0 10px;">
                                        <span style="color:#9ca3af;font-weight:500;margin-right:4px;">{{ $qi + 1 }}.</span>
                                        {{ $q['text'] }}
                                    </p>
                                    {{-- Options --}}
                                    <div style="display:grid;gap:5px;">
                                        @foreach($q['all_options'] as $opt)
                                        @php
                                            $optBg     = $opt['is_correct'] ? '#f0fdf4' : ($opt['was_chosen'] && !$opt['is_correct'] ? '#fef2f2' : '#f9fafb');
                                            $optBorder = $opt['is_correct'] ? '#86efac' : ($opt['was_chosen'] && !$opt['is_correct'] ? '#fca5a5' : '#e5e7eb');
                                            $optText   = $opt['is_correct'] ? '#15803d' : ($opt['was_chosen'] && !$opt['is_correct'] ? '#dc2626' : '#6b7280');
                                        @endphp
                                        <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:8px;border:1px solid {{ $optBorder }};background:{{ $optBg }};">
                                            <div style="width:18px;height:18px;border-radius:50%;border:2px solid {{ $optBorder }};background:{{ $opt['is_correct'] ? '#86efac' : ($opt['was_chosen'] && !$opt['is_correct'] ? '#fca5a5' : '#fff') }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                @if($opt['is_correct'])
                                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="#15803d" style="width:10px;height:10px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                                @elseif($opt['was_chosen'] && !$opt['is_correct'])
                                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="#dc2626" style="width:10px;height:10px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                @endif
                                            </div>
                                            <span style="font-size:12px;color:{{ $optText }};font-weight:{{ ($opt['was_chosen'] || $opt['is_correct']) ? '600' : '400' }};">{{ $opt['text'] }}</span>
                                            @if($opt['was_chosen'] && $opt['is_correct'])
                                                <span style="margin-left:auto;font-size:10px;font-weight:700;color:#15803d;">✓ Correct</span>
                                            @elseif($opt['was_chosen'] && !$opt['is_correct'])
                                                <span style="margin-left:auto;font-size:10px;font-weight:700;color:#dc2626;">✗ Wrong answer</span>
                                            @elseif($opt['is_correct'])
                                                <span style="margin-left:auto;font-size:10px;font-weight:700;color:#15803d;">Correct answer</span>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif

                        </div>
                        @endforeach

                    </div>
                </div>

                {{-- Hands-on video --}}
                <div>
                    <p style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 8px;">Hands-on Video</p>

                    @if($mod['video']['has_video'])
                    <div style="border:1.5px solid {{ $videoColor }}40;border-radius:12px;overflow:hidden;background:#000;">

                        {{-- Inline player --}}
                        @if($mod['video']['embed_url'])
                            <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">
                                <iframe
                                    src="{{ $mod['video']['embed_url'] }}"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                    style="position:absolute;top:0;left:0;width:100%;height:100%;">
                                </iframe>
                            </div>
                        @elseif($mod['video']['is_direct'])
                            <video controls style="width:100%;max-height:320px;display:block;background:#000;">
                                <source src="{{ $mod['video']['video_url'] }}">
                                Your browser does not support the video tag.
                            </video>
                        @else
                            {{-- Unknown URL type — show link --}}
                            <div style="padding:20px;text-align:center;background:#1f2937;">
                                <a href="{{ $mod['video']['video_url'] }}" target="_blank"
                                   style="display:inline-flex;align-items:center;gap:7px;background:#3b82f6;color:#fff;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700;text-decoration:none;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/></svg>
                                    Open Video
                                </a>
                            </div>
                        @endif

                        {{-- Review status bar --}}
                        <div style="padding:12px 16px;background:#fff;border-top:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:700;background:{{ $videoBg }};color:{{ $videoColor }};">
                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:10px;height:10px;">
                                        @if($videoStatus === 'passed')<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                        @elseif($videoStatus === 'failed')<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        @else<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>@endif
                                    </svg>
                                    Mentor review: {{ ucfirst($videoStatus) }}
                                </span>
                                @if($mod['video']['reviewed_by'])
                                <span style="font-size:11px;color:#9ca3af;">by {{ $mod['video']['reviewed_by'] }}</span>
                                @endif
                            </div>
                        </div>
                        @if($mod['video']['review_notes'])
                        <div style="padding:10px 16px 14px;background:#fff;border-top:1px solid #f3f4f6;">
                            <p style="font-size:12px;color:#374151;margin:0;font-style:italic;padding:10px;background:#f9fafb;border-radius:8px;border-left:3px solid {{ $videoColor }};">
                                "{{ $mod['video']['review_notes'] }}"
                            </p>
                        </div>
                        @endif
                    </div>
                    @else
                    <div style="border:1.5px dashed #e5e7eb;border-radius:12px;padding:20px;text-align:center;">
                        <p style="font-size:13px;color:#9ca3af;margin:0;">No video submitted for this module</p>
                    </div>
                    @endif
                </div>

            </div>
            @endif
        </div>
        @endforeach

    </div>
</div>

</div>{{-- end Alpine x-data --}}
</x-filament-panels::page>
