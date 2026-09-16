@auth
@php
$user      = auth()->user();
$initials  = strtoupper(substr($user->first_name ?? 'U', 0, 1) . substr($user->last_name ?? '', 0, 1));
$role      = $user->getRoleNames()->first();
$roleLabel = $role ? ucwords(str_replace(['_', '::'], [' ', ' '], $role)) : 'No Role';
$facility  = $user->facility?->name;
$subcounty = $user->facility?->subcounty?->name ?? $user->subcounties->first()?->name;
$county    = $user->facility?->subcounty?->county?->name ?? $user->counties->first()?->name;
$statusColors = ['active'=>'#16a34a','inactive'=>'#6b7280','suspended'=>'#dc2626','trainee'=>'#d97706'];
$statusColor  = $statusColors[$user->status] ?? '#6b7280';
@endphp
<div style="
    padding: 14px 16px 10px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    background: linear-gradient(135deg,#0f172a 0%,#1e3a8a 60%,#2563eb 100%);
    border-radius: 10px 10px 0 0;
    margin: -8px -4px 4px;
">
    <div style="display:flex;align-items:center;gap:12px;">
        <div style="
            width:44px;height:44px;border-radius:50%;
            background:rgba(255,255,255,.18);
            display:flex;align-items:center;justify-content:center;
            font-size:16px;font-weight:700;color:#fff;
            border:2px solid rgba(255,255,255,.30);
            flex-shrink:0;
        ">{{ $initials }}</div>
        <div style="min-width:0;flex:1;">
            <div style="font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                {{ $user->full_name }}
            </div>
            <div style="font-size:11px;color:rgba(255,255,255,.65);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                {{ $user->email }}
            </div>
        </div>
    </div>

    <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:5px;">
        <span style="
            padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;
            letter-spacing:.4px;text-transform:uppercase;
            background:rgba(255,255,255,.18);color:#fff;
        ">{{ $roleLabel }}</span>
        <span style="
            padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;
            background:{{ $statusColor }};color:#fff;
        ">{{ ucfirst($user->status) }}</span>
    </div>

    @if($facility || $county)
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:3px;">
        @if($facility)
        <div style="font-size:11px;color:rgba(255,255,255,.75);display:flex;align-items:center;gap:5px;">
            <span>🏥</span><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $facility }}</span>
        </div>
        @endif
        @if($subcounty)
        <div style="font-size:11px;color:rgba(255,255,255,.65);display:flex;align-items:center;gap:5px;">
            <span>🗂</span><span>{{ $subcounty }} Subcounty</span>
        </div>
        @endif
        @if($county)
        <div style="font-size:11px;color:rgba(255,255,255,.65);display:flex;align-items:center;gap:5px;">
            <span>📍</span><span>{{ $county }} County</span>
        </div>
        @endif
    </div>
    @endif
</div>
@endauth
