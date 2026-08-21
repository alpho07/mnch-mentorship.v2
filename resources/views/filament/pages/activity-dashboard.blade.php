<x-filament-panels::page>
    <div id="currently-online" wire:poll.20s="refreshOnline" style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px 20px;margin-bottom:20px;">
        <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0 0 12px;">Currently Online ({{ $onlineUsers->count() }})</h3>
        @forelse($onlineUsers as $user)
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:13px;color:#374151;padding:8px 0;border-top:1px solid #f1f5f9;">
                <div>
                    <span style="font-weight:600;">{{ $user->name }}</span>
                    <span style="color:#9ca3af;"> — {{ $user->roles->first()?->name }}</span>
                </div>
                <div style="text-align:right;">
                    <div>{{ $user->currentPageVisit?->route_name ?? $user->currentPageVisit?->path ?? '—' }}</div>
                    <div style="color:#9ca3af;font-size:11px;">seen {{ $user->last_seen_at->diffForHumans() }}</div>
                </div>
            </div>
        @empty
            <p style="font-size:13px;color:#9ca3af;">No one else is online.</p>
        @endforelse
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;">
        @foreach(['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days'] as $key => $label)
            <button
                type="button"
                wire:click="setRange('{{ $key }}')"
                style="
                    padding:6px 14px;border-radius:9999px;font-size:13px;font-weight:600;cursor:pointer;
                    border:1px solid {{ $range === $key ? '#1C3A8A' : '#e5e7eb' }};
                    background:{{ $range === $key ? '#1C3A8A' : '#fff' }};
                    color:{{ $range === $key ? '#fff' : '#374151' }};
                "
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div style="display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px 20px;">
            <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0 0 12px;">Recent Logins</h3>
            @forelse($recentLogins as $login)
                <div style="display:flex;justify-content:space-between;font-size:13px;color:#374151;padding:6px 0;border-top:1px solid #f1f5f9;">
                    <span>{{ $login->user?->name ?? 'Unknown' }}</span>
                    <span style="color:#9ca3af;">{{ $login->logged_in_at->format('M j, H:i') }}</span>
                </div>
            @empty
                <p style="font-size:13px;color:#9ca3af;">No logins in this range.</p>
            @endforelse
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px 20px;">
            <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0 0 12px;">Most Visited Pages</h3>
            @forelse($topPages as $page)
                <div style="display:flex;justify-content:space-between;font-size:13px;color:#374151;padding:6px 0;border-top:1px solid #f1f5f9;">
                    <span>{{ $page->route_name ?? $page->path }}</span>
                    <span style="color:#9ca3af;">{{ $page->visits }}</span>
                </div>
            @empty
                <p style="font-size:13px;color:#9ca3af;">No page visits in this range.</p>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>

