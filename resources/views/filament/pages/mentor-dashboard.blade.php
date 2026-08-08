<x-filament-panels::page>
@php
$programFilter = request('program');
$programTitle  = match($programFilter) {
    'newborn' => 'Newborn Care',
    'infant'  => 'Infant and Child Care',
    default   => 'All Programs',
};
$user      = auth()->user();
$firstName = $user->first_name ?? (explode(' ', $user->name)[0] ?? 'Mentor');
$hour      = (int) now()->format('H');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// Insight cards
$insightCards = [];
if ($insights['stalled_modules'] > 0)
    $insightCards[] = ['color'=>'#6366f1','bg'=>'#eef2ff','text'=>"<strong>{$insights['stalled_modules']}</strong> module(s) haven't been started yet — consider activating them.",'icon'=>'M15.75 5.25v13.5m-7.5-13.5v13.5','ic'=>'#6366f1'];
if ($insights['recs_coverage'] < 50)
    $insightCards[] = ['color'=>'#8b5cf6','bg'=>'#f5f3ff','text'=>"Only <strong>{$insights['recs_coverage']}%</strong> of mentees have written recommendations — boost this for better outcomes.",'icon'=>'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.127 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z','ic'=>'#8b5cf6'];
if (empty($insightCards))
    $insightCards[] = ['color'=>'#10b981','bg'=>'#f0fdf4','text'=>'All key indicators look healthy. Excellent mentorship work — keep it up!','icon'=>'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z','ic'=>'#10b981'];
@endphp

<div style="font-family:'Inter',system-ui,sans-serif;">

{{-- ═══ HERO BANNER ════════════════════════════════════════════════════════ --}}
<div class="rv-animate" style="animation-delay:0s;border-radius:20px;overflow:hidden;margin-bottom:20px;background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 40%,#1d4ed8 75%,#2563eb 100%);position:relative;box-shadow:0 8px 32px rgba(15,23,42,.40);">
    <div style="position:absolute;top:-60px;right:-40px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,0.05);pointer-events:none;"></div>
    <div style="position:absolute;bottom:-80px;left:20%;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,0.04);pointer-events:none;"></div>

    <div style="position:relative;padding:28px 32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;">
        <div>
            <p style="font-size:12px;text-transform:uppercase;letter-spacing:0.10em;font-weight:700;color:rgba(255,255,255,0.5);margin:0 0 6px;">
                Mentor Dashboard
                @if($programFilter)
                    · {{ $programTitle }}
                @endif
            </p>
            <h1 style="font-size:26px;font-weight:800;color:#fff;margin:0 0 8px;letter-spacing:-0.4px;">{{ $greeting }}, {{ $firstName }}</h1>
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="width:7px;height:7px;border-radius:50%;background:#34d399;display:inline-block;box-shadow:0 0 6px #34d399;"></span>
                    <span style="font-size:12px;color:rgba(255,255,255,0.6);">Live · {{ now()->format('d M Y, H:i') }}</span>
                </div>
                <span style="color:rgba(255,255,255,0.2);">|</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.7);">
                    <strong style="color:#fff;">{{ $kpis['active_mentorships'] }}</strong> mentorship(s)
                </span>
                <span style="color:rgba(255,255,255,0.2);">|</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.7);">
                    <strong style="color:#fff;">{{ $kpis['total_mentees'] }}</strong> mentee(s)
                </span>
                @if(($kpis['pending_video_reviews'] ?? 0) > 0 || ($kpis['pending_mentor_approvals'] ?? 0) > 0)
                    <span style="color:rgba(255,255,255,0.2);">|</span>
                    <span style="display:inline-flex;align-items:center;gap:5px;background:#fef3c7;color:#92400e;border-radius:9999px;padding:3px 10px;font-size:11px;font-weight:700;">
                        {{ ($kpis['pending_video_reviews'] ?? 0) + ($kpis['pending_mentor_approvals'] ?? 0) }} action(s) pending
                    </span>
                @endif
            </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
            <div style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);border-radius:14px;padding:14px 20px;text-align:right;">
                <p style="font-size:28px;font-weight:800;color:#fff;margin:0;letter-spacing:-1px;">{{ $kpis['avg_completion'] }}%</p>
                <p style="font-size:11px;color:rgba(255,255,255,0.6);margin:2px 0 0;font-weight:500;">avg. completion</p>
            </div>
        </div>
    </div>
</div>

{{-- ═══ MNCH OVERVIEW ══════════════════════════════════════════════════════ --}}
<details class="md-collapse-section" open style="margin-bottom:16px;">
    <summary class="md-collapse-summary" style="cursor:pointer;font-size:13px;font-weight:700;color:#475569;padding:10px 4px;list-style:none;user-select:none;">📊 MNCH Overview</summary>
    <div class="md-collapse-body" style="padding-top:6px;">
{{-- ═══ KPI STRIP — all stats in one horizontal row ════════════════════════ --}}
<div class="md-kpi-strip rv-animate" style="animation-delay:0.08s;margin-bottom:24px;">

    {{-- Active Mentorships --}}
    <div class="md-kpi" style="min-width:160px;background:#fff;border:1.5px solid #dbeafe;border-top:3px solid #3b82f6;border-radius:16px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#3b82f6" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
        </div>
        <p style="font-size:26px;font-weight:800;color:#1d4ed8;margin:0;line-height:1;">{{ $kpis['active_mentorships'] }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Mentorships</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $kpis['active_classes'] }} active classes</p>
    </div>

    {{-- Completed Classes --}}
    <div class="md-kpi" style="min-width:160px;background:#fff;border:1.5px solid #d1fae5;border-top:3px solid #10b981;border-radius:16px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#10b981" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p style="font-size:26px;font-weight:800;color:#059669;margin:0;line-height:1;">{{ $kpis['completed_classes'] }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Completed Classes</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $kpis['total_modules'] }} modules total</p>
    </div>

    {{-- Total Mentees --}}
    <div class="md-kpi" style="min-width:160px;background:#fff;border:1.5px solid #e0e7ff;border-top:3px solid #6366f1;border-radius:16px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#6366f1" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
        </div>
        <p style="font-size:26px;font-weight:800;color:#4f46e5;margin:0;line-height:1;">{{ $kpis['total_mentees'] }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Total Mentees</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $kpis['total_enrollments'] }} enrollments</p>
    </div>

    {{-- Avg Completion --}}
    @php
        $compColor = $kpis['avg_completion'] >= 75 ? '#059669' : ($kpis['avg_completion'] >= 50 ? '#d97706' : '#dc2626');
        $compBg    = $kpis['avg_completion'] >= 75 ? '#f0fdf4' : ($kpis['avg_completion'] >= 50 ? '#fffbeb' : '#fef2f2');
        $compBdr   = $kpis['avg_completion'] >= 75 ? '#6ee7b7' : ($kpis['avg_completion'] >= 50 ? '#fde68a' : '#fca5a5');
        $compTop   = $kpis['avg_completion'] >= 75 ? '#10b981' : ($kpis['avg_completion'] >= 50 ? '#f59e0b' : '#ef4444');
    @endphp
    <div class="md-kpi" style="min-width:160px;background:{{ $compBg }};border:1.5px solid {{ $compBdr }};border-top:3px solid {{ $compTop }};border-radius:16px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.7);display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $compColor }}" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
        </div>
        <p style="font-size:26px;font-weight:800;color:{{ $compColor }};margin:0;line-height:1;">{{ $kpis['avg_completion'] }}%</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Avg Completion</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $kpis['module_completion_rate'] }}% modules done</p>
    </div>

    {{-- Attendance Rate --}}
    @php
        $attColor = $kpis['attendance_rate'] >= 75 ? '#059669' : ($kpis['attendance_rate'] >= 50 ? '#d97706' : '#dc2626');
        $attBdr2  = $kpis['attendance_rate'] >= 75 ? '#6ee7b7' : ($kpis['attendance_rate'] >= 50 ? '#fde68a' : '#fca5a5');
        $attTop2  = $kpis['attendance_rate'] >= 75 ? '#10b981' : ($kpis['attendance_rate'] >= 50 ? '#f59e0b' : '#ef4444');
        $attBg2   = $kpis['attendance_rate'] >= 75 ? '#f0fdf4' : ($kpis['attendance_rate'] >= 50 ? '#fffbeb' : '#fef2f2');
    @endphp
    <div class="md-kpi" style="min-width:160px;background:{{ $attBg2 }};border:1.5px solid {{ $attBdr2 }};border-top:3px solid {{ $attTop2 }};border-radius:16px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.7);display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $attColor }}" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z"/></svg>
        </div>
        <p style="font-size:26px;font-weight:800;color:{{ $attColor }};margin:0;line-height:1;">{{ $kpis['attendance_rate'] }}%</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Attendance Rate</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">across all classes</p>
    </div>

    {{-- Recommendations --}}
    <div class="md-kpi" style="min-width:160px;background:#faf5ff;border:1.5px solid #ddd6fe;border-top:3px solid #8b5cf6;border-radius:16px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#8b5cf6" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.127 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
        </div>
        <p style="font-size:26px;font-weight:800;color:#7c3aed;margin:0;line-height:1;">{{ $kpis['recommendations'] }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Recommendations</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $insights['recs_coverage'] ?? 0 }}% mentee coverage</p>
    </div>

    {{-- Pending Video Reviews --}}
    @php $pvr = $kpis['pending_video_reviews'] ?? 0; @endphp
    <div class="md-kpi" style="min-width:160px;background:{{ $pvr > 0 ? '#fffbeb' : '#f9fafb' }};border:1.5px solid {{ $pvr > 0 ? '#fde68a' : '#e5e7eb' }};border-top:3px solid {{ $pvr > 0 ? '#f59e0b' : '#d1d5db' }};border-radius:16px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:{{ $pvr > 0 ? '#fef3c7' : '#f3f4f6' }};display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $pvr > 0 ? '#f59e0b' : '#9ca3af' }}" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"/></svg>
        </div>
        <p style="font-size:26px;font-weight:800;color:{{ $pvr > 0 ? '#d97706' : '#6b7280' }};margin:0;line-height:1;">{{ $pvr }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Video Reviews</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">pending review</p>
    </div>

    {{-- Pending Approvals --}}
    @php $pma = $kpis['pending_mentor_approvals'] ?? 0; @endphp
    <div class="md-kpi" style="min-width:160px;background:{{ $pma > 0 ? '#eff6ff' : '#f9fafb' }};border:1.5px solid {{ $pma > 0 ? '#bfdbfe' : '#e5e7eb' }};border-top:3px solid {{ $pma > 0 ? '#3b82f6' : '#d1d5db' }};border-radius:16px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:{{ $pma > 0 ? '#dbeafe' : '#f3f4f6' }};display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $pma > 0 ? '#3b82f6' : '#9ca3af' }}" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
        </div>
        <p style="font-size:26px;font-weight:800;color:{{ $pma > 0 ? '#1d4ed8' : '#6b7280' }};margin:0;line-height:1;">{{ $pma }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Pending Approvals</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">awaiting mentor sign-off</p>
    </div>

</div>

{{-- ═══ PENDING VIDEO REVIEWS ACTION PANEL ════════════════════════════════ --}}
@if(!empty($pendingVideoReviews))
<div class="rv-animate md-card" style="animation-delay:0.14s;margin-bottom:24px;background:#fffbeb;border:1.5px solid #fde68a;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
    <div style="padding:16px 22px;border-bottom:1px solid #fde68a;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:10px;background:#fef3c7;display:flex;align-items:center;justify-content:center;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#f59e0b" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"/></svg>
            </div>
            <div>
                <h3 style="font-size:14px;font-weight:700;color:#92400e;margin:0;">Pending Video Reviews</h3>
                <p style="font-size:11px;color:#b45309;margin:2px 0 0;">These mentees are waiting for their video evaluation</p>
            </div>
        </div>
        <span style="background:#fef3c7;color:#92400e;border-radius:9999px;padding:3px 12px;font-size:12px;font-weight:700;">{{ count($pendingVideoReviews) }}</span>
    </div>
    <div>
        @foreach($pendingVideoReviews as $i => $review)
            <div class="md-row" style="padding:13px 22px;{{ !$loop->last ? 'border-bottom:1px solid #fef3c7;' : '' }}display:flex;align-items:center;justify-content:space-between;gap:16px;">
                <div style="display:flex;align-items:center;gap:12px;min-width:0;">
                    <div style="width:36px;height:36px;min-width:36px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#92400e;">
                        {{ strtoupper(substr($review['mentee']?->full_name ?? $review['mentee']?->name ?? 'M', 0, 1)) }}
                    </div>
                    <div style="min-width:0;">
                        <p style="font-size:13px;font-weight:700;color:#92400e;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $review['mentee']?->full_name ?? $review['mentee']?->name ?? 'Unknown Mentee' }}
                        </p>
                        <p style="font-size:11px;color:#b45309;margin:2px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $review['programModule']?->name ?? 'Module' }}
                            <span style="opacity:0.5;margin:0 3px;">·</span>
                            {{ $review['class']?->name ?? 'Class' }}
                            <span style="opacity:0.5;margin:0 3px;">·</span>
                            {{ $review['training']?->title ?? 'Mentorship' }}
                        </p>
                    </div>
                </div>
                <a href="{{ $review['url'] }}"
                   style="display:inline-flex;align-items:center;gap:6px;background:#f59e0b;color:#fff;border:none;border-radius:9px;padding:8px 16px;font-size:12px;font-weight:700;text-decoration:none;flex-shrink:0;">
                    Review
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ═══ EMPTY STATE ════════════════════════════════════════════════════════ --}}
@if($mentorships->isEmpty())
<div class="rv-animate" style="animation-delay:0.16s;text-align:center;padding:56px 24px;background:#f9fafb;border:2px dashed #e5e7eb;border-radius:20px;">
    <div style="width:72px;height:72px;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#9ca3af" style="width:36px;height:36px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
    </div>
    <h3 style="font-size:16px;font-weight:700;color:#374151;margin:0 0 6px;">No Active Mentorships</h3>
    <p style="font-size:13px;color:#9ca3af;margin:0;">You are not currently leading or co-mentoring any mentorships.</p>
</div>

@else

{{-- ═══ INSIGHTS ROW ═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-3 rv-animate" style="animation-delay:0.16s;margin-bottom:24px;">
    @foreach($insightCards as $ins)
    <div class="md-insight md-card" style="background:{{ $ins['bg'] }};border-radius:14px;padding:14px 18px;border-color:{{ $ins['color'] }};box-shadow:0 1px 4px rgba(0,0,0,.04);">
        <div style="display:flex;align-items:flex-start;gap:12px;">
            <div style="width:32px;height:32px;min-width:32px;border-radius:9px;background:rgba(255,255,255,0.8);display:flex;align-items:center;justify-content:center;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $ins['ic'] }}" style="width:16px;height:16px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $ins['icon'] }}"/>
                </svg>
            </div>
            <p style="font-size:13px;color:#374151;line-height:1.55;margin:4px 0 0;">{!! $ins['text'] !!}</p>
        </div>
    </div>
    @endforeach
</div>

{{-- ═══ MENTORSHIP PROGRAMS ════════════════════════════════════════════════ --}}
@php
$sortArrow = fn(string $f) => $mdSort === $f ? ($mdDir === 'asc' ? ' ↑' : ' ↓') : '';
@endphp
<div class="rv-animate" style="animation-delay:0.22s;margin-bottom:24px;">
    <div class="md-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">

        {{-- Header --}}
        <div style="padding:18px 24px;border-bottom:1px solid #f3f4f6;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#3b82f6" style="width:17px;height:17px;"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">Mentorship Programs</h3>
                        <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;">{{ $mentorshipsTotal }} program(s) found</p>
                    </div>
                </div>
                @if($programFilter)
                    <span style="background:#dbeafe;color:#1e40af;border-radius:9999px;padding:3px 12px;font-size:12px;font-weight:600;">{{ $programTitle }}</span>
                @endif
            </div>

            {{-- Filter + Sort bar --}}
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                {{-- Search --}}
                <div style="position:relative;flex:1;min-width:180px;">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#9ca3af" style="width:14px;height:14px;position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803 7.5 7.5 0 0015.803 15.803z"/></svg>
                    <input wire:model.live.debounce.300ms="mdSearch"
                           type="text"
                           placeholder="Search name or facility…"
                           style="width:100%;padding:7px 10px 7px 30px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#374151;background:#fafafa;outline:none;box-sizing:border-box;">
                </div>

                {{-- Status filter --}}
                <select wire:model.live="mdStatus"
                        style="padding:7px 28px 7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#374151;background:#fafafa;outline:none;appearance:none;min-width:130px;cursor:pointer;">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="draft">Draft</option>
                </select>

                {{-- Program filter --}}
                @if(count($programOptions) > 1)
                <select wire:model.live="mdProgram"
                        style="padding:7px 28px 7px 10px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#374151;background:#fafafa;outline:none;appearance:none;min-width:140px;cursor:pointer;">
                    <option value="">All programs</option>
                    @foreach($programOptions as $prog)
                        <option value="{{ $prog }}">{{ $prog }}</option>
                    @endforeach
                </select>
                @endif

                {{-- Sort buttons --}}
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button wire:click="setSort('title')"
                            style="padding:6px 12px;border:1.5px solid {{ $mdSort === 'title' ? '#3b82f6' : '#e5e7eb' }};background:{{ $mdSort === 'title' ? '#eff6ff' : '#fafafa' }};color:{{ $mdSort === 'title' ? '#1d4ed8' : '#6b7280' }};border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">
                        Name{{ $sortArrow('title') }}
                    </button>
                    <button wire:click="setSort('module_pct')"
                            style="padding:6px 12px;border:1.5px solid {{ $mdSort === 'module_pct' ? '#3b82f6' : '#e5e7eb' }};background:{{ $mdSort === 'module_pct' ? '#eff6ff' : '#fafafa' }};color:{{ $mdSort === 'module_pct' ? '#1d4ed8' : '#6b7280' }};border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">
                        Progress{{ $sortArrow('module_pct') }}
                    </button>
                    <button wire:click="setSort('mentees')"
                            style="padding:6px 12px;border:1.5px solid {{ $mdSort === 'mentees' ? '#3b82f6' : '#e5e7eb' }};background:{{ $mdSort === 'mentees' ? '#eff6ff' : '#fafafa' }};color:{{ $mdSort === 'mentees' ? '#1d4ed8' : '#6b7280' }};border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">
                        Mentees{{ $sortArrow('mentees') }}
                    </button>
                    <button wire:click="setSort('created_at')"
                            style="padding:6px 12px;border:1.5px solid {{ $mdSort === 'created_at' ? '#3b82f6' : '#e5e7eb' }};background:{{ $mdSort === 'created_at' ? '#eff6ff' : '#fafafa' }};color:{{ $mdSort === 'created_at' ? '#1d4ed8' : '#6b7280' }};border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">
                        Latest{{ $sortArrow('created_at') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Program rows --}}
        @forelse($mentorshipItems as $m)
        <div class="md-row" style="padding:20px 24px;{{ !$loop->last ? 'border-bottom:1px solid #f9fafb;' : '' }}">
            <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">

                {{-- Left: title + meta --}}
                <div style="flex:1;min-width:220px;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                        <span style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;background:#f3f4f6;color:#6b7280;border-radius:6px;padding:2px 8px;">{{ $m['program_name'] }}</span>
                        <span style="background:{{ $m['active_classes'] > 0 ? '#d1fae5' : '#f3f4f6' }};color:{{ $m['active_classes'] > 0 ? '#065f46' : '#6b7280' }};border-radius:9999px;padding:2px 9px;font-size:11px;font-weight:600;">
                            {{ $m['active_classes'] }} active
                        </span>
                        <span style="background:{{ $m['status'] === 'active' ? '#dbeafe' : '#f3f4f6' }};color:{{ $m['status'] === 'active' ? '#1e40af' : '#9ca3af' }};border-radius:9999px;padding:2px 9px;font-size:11px;font-weight:600;">{{ ucfirst($m['status']) }}</span>
                    </div>
                    <p style="font-size:14px;font-weight:700;color:#111827;margin:0 0 3px;">{{ $m['title'] }}</p>
                    <p style="font-size:12px;color:#9ca3af;margin:0 0 12px;">
                        {{ $m['facility'] }}
                        <span style="margin:0 4px;opacity:0.4;">·</span>
                        {{ $m['classes_count'] }} class{{ $m['classes_count'] !== 1 ? 'es' : '' }}
                        <span style="margin:0 4px;opacity:0.4;">·</span>
                        {{ $m['mentees'] }} mentee{{ $m['mentees'] !== 1 ? 's' : '' }}
                    </p>

                    {{-- Progress bar --}}
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <div style="flex:1;height:6px;background:#f3f4f6;border-radius:9999px;overflow:hidden;">
                            <div class="md-prog" style="height:100%;border-radius:9999px;background:{{ $m['module_pct'] >= 100 ? '#10b981' : ($m['module_pct'] >= 60 ? '#3b82f6' : '#f59e0b') }};width:{{ $m['module_pct'] }}%;"></div>
                        </div>
                        <span style="font-size:12px;font-weight:700;color:#111827;white-space:nowrap;">{{ $m['module_pct'] }}%</span>
                        <span style="font-size:11px;color:#9ca3af;white-space:nowrap;">{{ $m['modules_done'] }}/{{ $m['modules_total'] }} modules</span>
                    </div>

                    {{-- Distribution dots --}}
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#9ca3af;">
                            <span style="width:7px;height:7px;border-radius:50%;background:#d1d5db;display:inline-block;"></span>
                            {{ $m['dist_not_started'] }}% not started
                        </span>
                        <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#9ca3af;">
                            <span style="width:7px;height:7px;border-radius:50%;background:#60a5fa;display:inline-block;"></span>
                            {{ $m['dist_in_progress'] }}% in progress
                        </span>
                        <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#9ca3af;">
                            <span style="width:7px;height:7px;border-radius:50%;background:#10b981;display:inline-block;"></span>
                            {{ $m['dist_completed'] }}% done
                        </span>
                    </div>
                </div>

                {{-- Right: recs (only when > 0) + action --}}
                <div style="display:flex;align-items:center;gap:16px;flex-shrink:0;">
                    @if($m['recommendations'] > 0)
                    <div style="text-align:center;padding:10px 16px;background:#faf5ff;border-radius:12px;border:1px solid #ede9fe;">
                        <p style="font-size:18px;font-weight:800;color:#7c3aed;margin:0;">{{ $m['recommendations'] }}</p>
                        <p style="font-size:10px;color:#9ca3af;margin:2px 0 0;text-transform:uppercase;letter-spacing:0.05em;">Recs</p>
                    </div>
                    @endif
                    <a href="{{ $m['url'] }}"
                       style="display:inline-flex;align-items:center;gap:7px;background:#111827;color:#fff;border:none;border-radius:11px;padding:11px 20px;font-size:13px;font-weight:700;text-decoration:none;letter-spacing:0.01em;">
                        View
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </div>
        </div>
        @empty
        <div style="padding:48px 24px;text-align:center;">
            <p style="font-size:14px;color:#9ca3af;margin:0;">No mentorship programs match your filters.</p>
        </div>
        @endforelse

        {{-- Pagination --}}
        @if($mentorshipsTotal > $mentorshipsPerPage)
        @php
            $totalPages = (int) ceil($mentorshipsTotal / $mentorshipsPerPage);
            $curPage = max(1, $mentorshipsPage);
        @endphp
        <div style="padding:14px 24px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <p style="font-size:12px;color:#9ca3af;margin:0;">
                Showing {{ (($curPage - 1) * $mentorshipsPerPage) + 1 }}–{{ min($curPage * $mentorshipsPerPage, $mentorshipsTotal) }} of {{ $mentorshipsTotal }}
            </p>
            <div style="display:flex;gap:6px;">
                @if($curPage > 1)
                <button wire:click="setPage({{ $curPage - 1 }})"
                        style="padding:6px 14px;border:1.5px solid #e5e7eb;border-radius:8px;background:#fff;color:#374151;font-size:13px;font-weight:600;cursor:pointer;">
                    ← Prev
                </button>
                @endif
                @for($p = max(1, $curPage - 2); $p <= min($totalPages, $curPage + 2); $p++)
                <button wire:click="setPage({{ $p }})"
                        style="padding:6px 12px;border:1.5px solid {{ $p === $curPage ? '#3b82f6' : '#e5e7eb' }};border-radius:8px;background:{{ $p === $curPage ? '#3b82f6' : '#fff' }};color:{{ $p === $curPage ? '#fff' : '#374151' }};font-size:13px;font-weight:600;cursor:pointer;min-width:36px;">
                    {{ $p }}
                </button>
                @endfor
                @if($curPage < $totalPages)
                <button wire:click="setPage({{ $curPage + 1 }})"
                        style="padding:6px 14px;border:1.5px solid #e5e7eb;border-radius:8px;background:#fff;color:#374151;font-size:13px;font-weight:600;cursor:pointer;">
                    Next →
                </button>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
    </div>
</details>

{{-- ═══ RECENT RECOMMENDATIONS ════════════════════════════════════════════ --}}
@if(count($activityFeed) > 0)
<details class="md-collapse-section" style="margin-bottom:16px;">
    <summary class="md-collapse-summary" style="cursor:pointer;font-size:13px;font-weight:700;color:#475569;padding:10px 4px;list-style:none;user-select:none;">🕐 Recent Recommendations</summary>
    <div class="md-collapse-body" style="padding-top:6px;">
<div class="rv-animate" style="animation-delay:0.28s;">
    <div class="md-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="padding:18px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:10px;background:#faf5ff;display:flex;align-items:center;justify-content:center;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#8b5cf6" style="width:17px;height:17px;"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.127 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
            </div>
            <div>
                <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">Recent Recommendations</h3>
                <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;">Latest feedback written to mentees</p>
            </div>
        </div>
        @foreach($activityFeed as $activity)
        <div class="md-row" style="padding:14px 24px;{{ !$loop->last ? 'border-bottom:1px solid #faf5ff;' : '' }}display:flex;align-items:flex-start;gap:12px;">
            <div style="width:36px;height:36px;min-width:36px;border-radius:50%;background:#ede9fe;display:flex;align-items:center;justify-content:center;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#8b5cf6" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.127 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
            </div>
            <div style="flex:1;min-width:0;">
                <p style="font-size:13px;color:#374151;margin:0;">
                    <strong style="color:#111827;">{{ $activity['mentee'] }}</strong>
                    <span style="color:#d1d5db;margin:0 5px;">·</span>
                    <span style="color:#6b7280;">{{ $activity['module'] }}</span>
                </p>
                <p style="font-size:12px;color:#9ca3af;margin:3px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $activity['excerpt'] }}</p>
            </div>
            @if($activity['at'])
            <span style="font-size:11px;color:#9ca3af;white-space:nowrap;flex-shrink:0;padding-top:2px;">{{ \Carbon\Carbon::parse($activity['at'])->diffForHumans() }}</span>
            @endif
        </div>
        @endforeach
    </div>
</div>
    </div>
</details>
@endif

@endif {{-- end isEmpty check --}}

{{-- ═══ PRIORITY QUEUE (needs your attention) ═════════════════════════════ --}}
@if(!empty($priorityQueue))
@php $attnTotal = min(count($priorityQueue), 10); @endphp
<div class="rv-animate md-card" x-data="{ mdAttnExpanded: false }" style="animation-delay:0.32s;margin-bottom:24px;background:#fff;border:1.5px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
    <div style="padding:16px 22px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">{{ count($priorityQueue) }} item{{ count($priorityQueue) !== 1 ? 's' : '' }} need your attention</h3>
    </div>
    <div>
        @foreach(array_slice($priorityQueue, 0, 10) as $idx => $item)
            @php
                $tierColor = match(true) {
                    $item['tier'] <= 2 => '#f59e0b',
                    $item['tier'] === 3 => '#ef4444',
                    default => '#3b82f6',
                };
            @endphp
            <div class="md-row" @if($idx >= 5) x-show="mdAttnExpanded" x-cloak @endif style="padding:13px 22px;{{ !$loop->last ? 'border-bottom:1px solid #f9fafb;' : '' }}display:flex;align-items:center;justify-content:space-between;gap:16px;">
                <div style="display:flex;align-items:center;gap:12px;min-width:0;">
                    <span style="width:8px;height:8px;border-radius:50%;background:{{ $tierColor }};flex-shrink:0;"></span>
                    <div style="min-width:0;">
                        <p style="font-size:13px;font-weight:700;color:#111827;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item['headline'] }}</p>
                        <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item['subtext'] }}</p>
                    </div>
                </div>
                <a href="{{ $item['url'] }}"
                   style="display:inline-flex;align-items:center;gap:6px;background:{{ $tierColor }};color:#fff;border:none;border-radius:9px;padding:8px 16px;font-size:12px;font-weight:700;text-decoration:none;flex-shrink:0;">
                    {{ $item['label'] }}
                </a>
            </div>
        @endforeach
    </div>
    @if($attnTotal > 5)
    <div style="padding:10px 22px;border-top:1px solid #f9fafb;text-align:center;">
        <button type="button" @click="mdAttnExpanded = !mdAttnExpanded"
                style="background:none;border:none;color:#3b82f6;font-size:12px;font-weight:700;cursor:pointer;padding:4px 8px;">
            <span x-show="!mdAttnExpanded">Show {{ $attnTotal - 5 }} more ↓</span>
            <span x-show="mdAttnExpanded" x-cloak>Show less ↑</span>
        </button>
    </div>
    @endif
</div>
@else
<div class="rv-animate" style="animation-delay:0.32s;margin-bottom:24px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:18px;padding:18px 22px;">
    <p style="font-size:13px;font-weight:700;color:#166534;margin:0;">You're all caught up</p>
    <p style="font-size:12px;color:#15803d;margin:3px 0 0;">Nothing needs your attention right now.</p>
</div>
@endif

</div>
</x-filament-panels::page>
