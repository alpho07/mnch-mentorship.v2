<x-filament-panels::page>
@php
$user      = auth()->user();
$firstName = $user->first_name ?? (explode(' ', $user->name)[0] ?? 'Mentee');
$certifiedCount = collect($programs)->where('is_certified', true)->count();
@endphp

<div style="font-family:'Inter',system-ui,sans-serif;">

{{-- ═══ HERO ════════════════════════════════════════════════════════════════ --}}
<div class="rv-animate" style="animation-delay:0s;border-radius:20px;overflow:hidden;margin-bottom:20px;background:linear-gradient(135deg,#1e3a5f 0%,#1d4ed8 50%,#4f46e5 100%);position:relative;box-shadow:0 8px 32px rgba(29,78,216,.35);">
    <div style="position:absolute;top:-50px;right:-30px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,0.05);pointer-events:none;"></div>
    <div style="position:relative;padding:28px 32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;">
        <div>
            <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;font-weight:700;color:rgba(255,255,255,0.45);margin:0 0 6px;">My Certificates</p>
            <h1 style="font-size:26px;font-weight:800;color:#fff;margin:0 0 8px;letter-spacing:-0.4px;">{{ $firstName }}'s Progress</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.7);margin:0;">Track your progress toward certification in each program you're enrolled in.</p>
        </div>
        <div style="display:flex;gap:14px;flex-wrap:wrap;">
            <div style="background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.15);border-radius:14px;padding:14px 22px;text-align:center;">
                <p style="font-size:28px;font-weight:800;color:#fff;margin:0;letter-spacing:-1px;">{{ $certifiedCount }}</p>
                <p style="font-size:11px;color:rgba(255,255,255,0.6);margin:2px 0 0;">Certified</p>
            </div>
            <div style="background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.15);border-radius:14px;padding:14px 22px;text-align:center;">
                <p style="font-size:28px;font-weight:800;color:#fff;margin:0;letter-spacing:-1px;">{{ $cpd['total'] ?? 0 }}</p>
                <p style="font-size:11px;color:rgba(255,255,255,0.6);margin:2px 0 0;">CPD Points · {{ $cpd['level']['name'] ?? 'Foundation' }}</p>
            </div>
        </div>
    </div>
</div>

{{-- ═══ PROGRAM CARDS ══════════════════════════════════════════════════════ --}}
@if(empty($programs))
<div class="rv-animate" style="text-align:center;padding:56px 24px;background:#f9fafb;border:2px dashed #e5e7eb;border-radius:20px;">
    <div style="width:72px;height:72px;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#9ca3af" style="width:36px;height:36px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
    </div>
    <h3 style="font-size:16px;font-weight:700;color:#374151;margin:0 0 6px;">No Enrollments Yet</h3>
    <p style="font-size:13px;color:#9ca3af;margin:0;">You're not currently enrolled in any facility mentorship program.</p>
</div>
@else
<div class="rv-animate" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;">
    @foreach($programs as $p)
    @php
        $barColor = $p['is_certified'] ? '#10b981' : ($p['percent'] >= 60 ? '#3b82f6' : '#f59e0b');
    @endphp
    <div class="md-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="padding:18px 22px;border-bottom:1px solid #f3f4f6;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px;">
                <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">{{ $p['program_name'] }}</h3>
                @if($p['is_certified'])
                <span style="display:inline-flex;align-items:center;gap:4px;background:#d1fae5;color:#065f46;border-radius:9999px;padding:3px 10px;font-size:11px;font-weight:700;white-space:nowrap;">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:11px;height:11px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Certified
                </span>
                @else
                <span style="background:#fffbeb;color:#92400e;border-radius:9999px;padding:3px 10px;font-size:11px;font-weight:700;white-space:nowrap;">In Progress</span>
                @endif
            </div>
            <p style="font-size:11px;color:#9ca3af;margin:0;">
                {{ count($p['classes']) }} class{{ count($p['classes']) !== 1 ? 'es' : '' }}
                @if($p['is_emonc'])<span style="margin:0 4px;opacity:0.4;">·</span>Requires mentor approval @endif
            </p>
        </div>

        <div style="padding:18px 22px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                <div style="flex:1;height:8px;background:#f3f4f6;border-radius:9999px;overflow:hidden;">
                    <div style="height:100%;border-radius:9999px;background:{{ $barColor }};width:{{ $p['percent'] }}%;"></div>
                </div>
                <span style="font-size:14px;font-weight:800;color:#111827;white-space:nowrap;">{{ $p['percent'] }}%</span>
            </div>
            <p style="font-size:12px;color:#6b7280;margin:0 0 14px;">{{ $p['modules_done'] }} of {{ $p['modules_total'] }} modules complete</p>

            @if($p['is_certified'])
            <a href="{{ $p['cert_url'] }}" target="_blank"
               style="display:inline-flex;align-items:center;gap:7px;background:#059669;color:#fff;border:none;border-radius:11px;padding:10px 20px;font-size:13px;font-weight:700;text-decoration:none;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Download Certificate
            </a>
            @else
            <p style="font-size:12px;color:#9ca3af;margin:0;">Complete every module to unlock your certificate.</p>
            @endif

            @if(count($p['classes']) > 0)
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f3f4f6;display:grid;gap:6px;">
                @foreach($p['classes'] as $c)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <span style="font-size:11px;color:#6b7280;">{{ $c['name'] }} <span style="opacity:0.5;">· {{ $c['facility'] }}</span></span>
                    <span style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;">{{ $c['status'] }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endif

</div>
</x-filament-panels::page>
