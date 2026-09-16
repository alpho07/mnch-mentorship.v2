<x-filament-panels::page>
<style>
    .hdd-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
    .hdd-tabs { display:flex; gap:6px; flex-wrap:wrap; }
    .hdd-filters { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .hdd-search-input { padding:7px 12px 7px 30px; border:1.5px solid #e5e7eb; border-radius:10px; font-size:13px; color:#374151; background:#fafafa; outline:none; width:230px; max-width:100%; }
    .hdd-select { padding:7px 28px 7px 10px; border:1.5px solid #e5e7eb; border-radius:10px; font-size:13px; color:#374151; background:#fafafa; outline:none; appearance:none; min-width:170px; cursor:pointer; }
    .hdd-row-grid { display:grid; grid-template-columns: 2fr 2fr 1.4fr auto; gap:16px; align-items:center; }
    .hdd-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; justify-content:flex-end; }
    @media (max-width: 900px) {
        .hdd-row-grid { grid-template-columns: 1fr 1fr; }
        .hdd-actions { grid-column: 1 / -1; justify-content:flex-start; }
    }
    @media (max-width: 560px) {
        .hdd-row-grid { grid-template-columns: 1fr; }
        .hdd-search-input { width:100%; }
        .hdd-toolbar { align-items:stretch; }
        .hdd-filters { width:100%; }
        .hdd-filters > * { flex:1; min-width:140px; }
    }
</style>
@php
$user      = auth()->user();
$firstName = $user->first_name ?? (explode(' ', $user->name)[0] ?? 'Head DRMH');
$hour      = (int) now()->format('H');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

<div style="font-family:'Inter',system-ui,sans-serif;">

{{-- ═══ HERO ════════════════════════════════════════════════════════════════ --}}
<div class="rv-animate" style="animation-delay:0s;border-radius:20px;overflow:hidden;margin-bottom:20px;background:linear-gradient(135deg,#0c1445 0%,#1a237e 40%,#283593 70%,#303f9f 100%);position:relative;box-shadow:0 8px 32px rgba(12,20,69,.40);">
    <div style="position:absolute;top:-50px;right:-30px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,0.05);pointer-events:none;"></div>
    <div style="position:absolute;bottom:-60px;left:15%;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.04);pointer-events:none;"></div>
    <div style="position:relative;padding:28px 32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;">
        <div>
            <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;font-weight:700;color:rgba(255,255,255,0.45);margin:0 0 6px;">Head DRMH · Certificate Authority</p>
            <h1 style="font-size:26px;font-weight:800;color:#fff;margin:0 0 8px;letter-spacing:-0.4px;">{{ $greeting }}, {{ $firstName }}</h1>
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="width:7px;height:7px;border-radius:50%;background:#34d399;display:inline-block;box-shadow:0 0 6px #34d399;"></span>
                    <span style="font-size:12px;color:rgba(255,255,255,0.6);">{{ now()->format('d M Y, H:i') }}</span>
                </div>
                @if($kpis['pending'] > 0)
                <span style="display:inline-flex;align-items:center;gap:5px;background:#fef3c7;color:#92400e;border-radius:9999px;padding:3px 10px;font-size:11px;font-weight:700;">
                    {{ $kpis['pending'] }} awaiting your signature
                </span>
                @endif
            </div>
        </div>
        <div style="background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.15);border-radius:14px;padding:14px 22px;text-align:center;">
            <p style="font-size:28px;font-weight:800;color:#fff;margin:0;letter-spacing:-1px;">{{ $kpis['certified_total'] }}</p>
            <p style="font-size:11px;color:rgba(255,255,255,0.6);margin:2px 0 0;">Total certified</p>
        </div>
    </div>
</div>

{{-- ═══ KPI CARDS ══════════════════════════════════════════════════════════ --}}
<div class="rv-animate" style="animation-delay:0.08s;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px;">

    {{-- Pending --}}
    <div class="md-kpi" style="background:#fff;border:1.5px solid #fde68a;border-top:3px solid #f59e0b;border-radius:16px;padding:18px 20px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:#fffbeb;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#f59e0b" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p style="font-size:28px;font-weight:800;color:#92400e;margin:0;line-height:1;">{{ $kpis['pending'] }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Pending Certification</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">Awaiting your approval</p>
    </div>

    {{-- This month --}}
    <div class="md-kpi" style="background:#fff;border:1.5px solid #a7f3d0;border-top:3px solid #10b981;border-radius:16px;padding:18px 20px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:#ecfdf5;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#10b981" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p style="font-size:28px;font-weight:800;color:#065f46;margin:0;line-height:1;">{{ $kpis['certified_this_month'] }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Certified This Month</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ now()->format('F Y') }}</p>
    </div>

    {{-- Total --}}
    <div class="md-kpi" style="background:#fff;border:1.5px solid #c7d2fe;border-top:3px solid #6366f1;border-radius:16px;padding:18px 20px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#6366f1" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
        </div>
        <p style="font-size:28px;font-weight:800;color:#312e81;margin:0;line-height:1;">{{ $kpis['certified_total'] }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Total Certified</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">All time</p>
    </div>

    {{-- Mentorships with pending --}}
    <div class="md-kpi" style="background:#fff;border:1.5px solid #fbcfe8;border-top:3px solid #ec4899;border-radius:16px;padding:18px 20px;box-shadow:0 1px 6px rgba(0,0,0,.05);">
        <div style="width:36px;height:36px;border-radius:10px;background:#fdf2f8;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#ec4899" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21"/></svg>
        </div>
        <p style="font-size:28px;font-weight:800;color:#9d174d;margin:0;line-height:1;">{{ $kpis['mentorships_with_pending'] }}</p>
        <p style="font-size:12px;font-weight:600;color:#374151;margin:4px 0 0;">Active Mentorships</p>
        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">With pending mentees</p>
    </div>
</div>

{{-- ═══ TABS + SEARCH ══════════════════════════════════════════════════════ --}}
<div class="rv-animate" style="animation-delay:0.14s;margin-bottom:24px;">
    <div class="md-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">

        {{-- Tab bar + filters + search --}}
        <div style="padding:16px 24px;border-bottom:1px solid #f3f4f6;" class="hdd-toolbar">
            <div class="hdd-tabs">
                <button wire:click="setTab('pending')"
                        style="padding:7px 18px;border-radius:10px;font-size:13px;font-weight:700;border:1.5px solid {{ $activeTab === 'pending' ? '#f59e0b' : '#e5e7eb' }};background:{{ $activeTab === 'pending' ? '#fffbeb' : '#fff' }};color:{{ $activeTab === 'pending' ? '#92400e' : '#6b7280' }};cursor:pointer;">
                    Pending
                    @if($kpis['pending'] > 0)
                        <span style="margin-left:5px;background:#f59e0b;color:#fff;border-radius:9999px;padding:1px 7px;font-size:11px;">{{ $kpis['pending'] }}</span>
                    @endif
                </button>
                <button wire:click="setTab('certified')"
                        style="padding:7px 18px;border-radius:10px;font-size:13px;font-weight:700;border:1.5px solid {{ $activeTab === 'certified' ? '#10b981' : '#e5e7eb' }};background:{{ $activeTab === 'certified' ? '#ecfdf5' : '#fff' }};color:{{ $activeTab === 'certified' ? '#065f46' : '#6b7280' }};cursor:pointer;">
                    Certified
                    <span style="margin-left:5px;background:#10b981;color:#fff;border-radius:9999px;padding:1px 7px;font-size:11px;">{{ $kpis['certified_total'] }}</span>
                </button>
            </div>

            <div class="hdd-filters">
                {{-- Program filter --}}
                @if(count($programOptions) > 1)
                <select wire:model.live="dProgram" class="hdd-select">
                    <option value="">All programs</option>
                    @foreach($programOptions as $prog)
                        <option value="{{ $prog }}">{{ $prog }}</option>
                    @endforeach
                </select>
                @endif

                {{-- Search --}}
                <div style="position:relative;">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#9ca3af" style="width:14px;height:14px;position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803 7.5 7.5 0 0015.803 15.803z"/></svg>
                    <input wire:model.live.debounce.300ms="dSearch"
                           type="text"
                           placeholder="Search name, facility, county…"
                           class="hdd-search-input">
                </div>
            </div>
        </div>

        {{-- PENDING TAB --}}
        @if($activeTab === 'pending')
            @forelse($paginated as $p)
            <div class="md-row" style="padding:18px 24px;{{ !$loop->last ? 'border-bottom:1px solid #f9fafb;' : '' }}">
                <div class="hdd-row-grid">

                    {{-- Avatar + name --}}
                    <div style="display:flex;align-items:center;gap:12px;min-width:0;">
                        <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <span style="font-size:15px;font-weight:800;color:#fff;">{{ $p['initials'] }}</span>
                        </div>
                        <div style="min-width:0;">
                            <p style="font-size:14px;font-weight:700;color:#111827;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $p['name'] }}</p>
                            <p style="font-size:12px;color:#6b7280;margin:2px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $p['cadre'] }} · {{ $p['facility'] }}</p>
                            <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $p['county'] }} county</p>
                        </div>
                    </div>

                    {{-- Class + Training --}}
                    <div style="min-width:0;">
                        <p style="font-size:12px;color:#6b7280;margin:0 0 2px;">
                            <span style="font-weight:600;color:#374151;">{{ $p['class'] }}</span>
                        </p>
                        <p style="font-size:11px;color:#9ca3af;margin:0;">{{ $p['training'] }}</p>
                        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $p['training_facility'] }}</p>
                    </div>

                    {{-- Modules + Mentor (mentor line only shown for EmONC — non-EmONC never goes through mentor approval) --}}
                    <div style="min-width:0;">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                            <div style="flex:1;height:5px;background:#f3f4f6;border-radius:9999px;overflow:hidden;min-width:40px;">
                                <div style="height:100%;background:#10b981;border-radius:9999px;width:{{ $p['modules_total'] > 0 ? round(($p['modules_done']/$p['modules_total'])*100) : 0 }}%;"></div>
                            </div>
                            <span style="font-size:11px;font-weight:600;color:#374151;white-space:nowrap;">{{ $p['modules_done'] }}/{{ $p['modules_total'] }}</span>
                        </div>
                        @if($p['is_emonc'])
                        <p style="font-size:11px;color:#9ca3af;margin:0;">Mentor: {{ $p['mentor_approved_by'] }}</p>
                        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $p['mentor_approved_at'] }}</p>
                        @else
                        <p style="font-size:11px;color:#9ca3af;margin:0;">{{ $p['program_name'] }}</p>
                        @endif
                    </div>

                    {{-- Waiting indicator --}}
                    <div class="hdd-actions">
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#fef3c7;color:#92400e;border-radius:9999px;padding:4px 10px;font-size:11px;font-weight:700;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Awaiting
                        </span>
                        <a href="{{ $p['review_url'] }}"
                           style="display:inline-flex;align-items:center;gap:6px;background:#0c1445;color:#fff;border-radius:10px;padding:9px 18px;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;">
                            Review
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            @empty
            <div style="padding:56px 24px;text-align:center;">
                <div style="width:56px;height:56px;border-radius:50%;background:#ecfdf5;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#10b981" style="width:26px;height:26px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p style="font-size:15px;font-weight:700;color:#111827;margin:0 0 4px;">All caught up!</p>
                <p style="font-size:13px;color:#9ca3af;margin:0;">No mentees pending certification{{ $dSearch || $dProgram ? ' for your filters' : '' }}.</p>
            </div>
            @endforelse
        @endif

        {{-- CERTIFIED TAB --}}
        @if($activeTab === 'certified')
            @forelse($paginated as $p)
            <div class="md-row" style="padding:18px 24px;{{ !$loop->last ? 'border-bottom:1px solid #f9fafb;' : '' }}">
                <div class="hdd-row-grid">

                    {{-- Avatar + name --}}
                    <div style="display:flex;align-items:center;gap:12px;min-width:0;">
                        <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <span style="font-size:15px;font-weight:800;color:#fff;">{{ $p['initials'] }}</span>
                        </div>
                        <div style="min-width:0;">
                            <p style="font-size:14px;font-weight:700;color:#111827;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $p['name'] }}</p>
                            <p style="font-size:12px;color:#6b7280;margin:2px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $p['cadre'] }} · {{ $p['facility'] }}</p>
                            <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $p['county'] }} county</p>
                        </div>
                    </div>

                    {{-- Class + Training --}}
                    <div style="min-width:0;">
                        <p style="font-size:12px;color:#6b7280;margin:0 0 2px;"><span style="font-weight:600;color:#374151;">{{ $p['class'] }}</span></p>
                        <p style="font-size:11px;color:#9ca3af;margin:0;">{{ $p['training'] }}</p>
                        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">{{ $p['training_facility'] }}</p>
                    </div>

                    {{-- Certified info --}}
                    <div style="min-width:0;">
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#d1fae5;color:#065f46;border-radius:9999px;padding:3px 10px;font-size:11px;font-weight:700;margin-bottom:5px;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:11px;height:11px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Certified
                        </span>
                        <p style="font-size:11px;color:#9ca3af;margin:0;">{{ $p['head_drmh_approved_at'] }}</p>
                        @if($p['head_drmh_approved_by'])
                        <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;">by {{ $p['head_drmh_approved_by'] }}</p>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="hdd-actions">
                        @if($p['cert_url'])
                        <a href="{{ $p['cert_url'] }}" target="_blank"
                           style="display:inline-flex;align-items:center;gap:5px;background:#ecfdf5;color:#065f46;border:1.5px solid #a7f3d0;border-radius:10px;padding:8px 14px;font-size:12px;font-weight:700;text-decoration:none;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            Certificate
                        </a>
                        @endif
                        <a href="{{ $p['review_url'] }}"
                           style="display:inline-flex;align-items:center;gap:5px;background:#f9fafb;color:#374151;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 14px;font-size:12px;font-weight:600;text-decoration:none;">
                            View
                        </a>
                    </div>
                </div>
            </div>
            @empty
            <div style="padding:56px 24px;text-align:center;">
                <p style="font-size:14px;color:#9ca3af;margin:0;">No certified mentees{{ $dSearch || $dProgram ? ' match your filters' : ' yet' }}.</p>
            </div>
            @endforelse
        @endif

        {{-- Pagination --}}
        @if($paginated->total() > $perPage)
        @php
            $totalPages = (int) ceil($paginated->total() / $perPage);
            $curPage = max(1, $dPage);
        @endphp
        <div style="padding:14px 24px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <p style="font-size:12px;color:#9ca3af;margin:0;">
                Showing {{ (($curPage - 1) * $perPage) + 1 }}–{{ min($curPage * $perPage, $paginated->total()) }} of {{ $paginated->total() }}
            </p>
            <div style="display:flex;gap:6px;">
                @if($curPage > 1)
                <button wire:click="setDPage({{ $curPage - 1 }})"
                        style="padding:6px 14px;border:1.5px solid #e5e7eb;border-radius:8px;background:#fff;color:#374151;font-size:13px;font-weight:600;cursor:pointer;">
                    ← Prev
                </button>
                @endif
                @for($pg = max(1, $curPage - 2); $pg <= min($totalPages, $curPage + 2); $pg++)
                <button wire:click="setDPage({{ $pg }})"
                        style="padding:6px 12px;border:1.5px solid {{ $pg === $curPage ? '#3b82f6' : '#e5e7eb' }};border-radius:8px;background:{{ $pg === $curPage ? '#3b82f6' : '#fff' }};color:{{ $pg === $curPage ? '#fff' : '#374151' }};font-size:13px;font-weight:600;cursor:pointer;min-width:36px;">
                    {{ $pg }}
                </button>
                @endfor
                @if($curPage < $totalPages)
                <button wire:click="setDPage({{ $curPage + 1 }})"
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
</x-filament-panels::page>
