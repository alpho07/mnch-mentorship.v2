<x-filament-panels::page>
    <div id="currently-online" wire:poll.20s="refreshOnline" style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px 20px;margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0;">Currently Online ({{ $onlineUsers->count() }})</h3>
            <a href="{{ \App\Filament\Pages\ActivityLog::getUrl() }}" style="font-size:12px;color:#2563eb;font-weight:500;text-decoration:none;">View Activity Log →</a>
        </div>
        @forelse($onlineUsers as $user)
            <div style="font-size:13px;color:#374151;padding:8px 0;border-top:1px solid #f1f5f9;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <div>
                        <span style="font-weight:600;">{{ $user->name }}</span>
                        <span style="color:#9ca3af;"> — {{ $user->roles->pluck('name')->join(', ') ?: '—' }}</span>
                    </div>
                    <div style="text-align:right;">
                        <div>{{ $user->currentPageVisit?->route_name ?? $user->currentPageVisit?->path ?? '—' }}</div>
                        <div style="color:#9ca3af;font-size:11px;">seen {{ $user->last_seen_at->diffForHumans() }}</div>
                    </div>
                </div>

                @if($user->relationLoaded('recentPageVisits') && $user->recentPageVisits->isNotEmpty())
                    <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #e5e7eb;">
                        <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:4px;">Recent pages visited:</div>
                        @foreach($user->recentPageVisits as $visit)
                            <div style="font-size:11px;color:#6b7280;padding:2px 0;">
                                {{ $visit->route_name ?? $visit->path ?? '—' }}
                                <span style="color:#9ca3af;">· {{ $visit->created_at?->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p style="font-size:13px;color:#9ca3af;">No one else is online.</p>
        @endforelse
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px 20px;margin-bottom:20px;">
        <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0 0 12px;">Users — Last Active</h3>
        @forelse($activeUsers as $user)
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:13px;color:#374151;padding:8px 0;border-top:1px solid #f1f5f9;">
                <div>
                    <span style="font-weight:600;">{{ $user->name ?? $user->email }}</span>
                    <span style="color:#9ca3af;"> · last active {{ $user->last_seen_at?->diffForHumans() }}</span>
                </div>
                <a href="{{ \App\Filament\Pages\ActivityLog::getUrl(['user' => $user->id]) }}" style="font-size:12px;color:#2563eb;font-weight:500;text-decoration:none;white-space:nowrap;">View activities →</a>
            </div>
        @empty
            <p style="font-size:13px;color:#9ca3af;">No user activity in the last 7 days.</p>
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
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px 20px;grid-column:1/-1;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0;">Most Visited Pages</h3>
                <a href="{{ \App\Filament\Pages\ActivityLog::getUrl() }}" style="font-size:12px;color:#2563eb;font-weight:500;text-decoration:none;">View full activity log →</a>
            </div>
            @forelse($topPages as $page)
                <div style="display:flex;justify-content:space-between;font-size:13px;color:#374151;padding:6px 0;border-top:1px solid #f1f5f9;">
                    <span>{{ $page->route_name ?? $page->path }}</span>
                    <span style="color:#9ca3af;">{{ $page->visits }} visits</span>
                </div>
            @empty
                <p style="font-size:13px;color:#9ca3af;">No page visits in this range.</p>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
