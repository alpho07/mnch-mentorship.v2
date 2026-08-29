<div style="space-y-3">
    <h4 style="font-size:13px;font-weight:600;color:#111827;margin:0 0 4px;">
        {{ $userName ?? 'User' }} — {{ $visits->count() }} page {{ \Illuminate\Support\Str::plural('visit', $visits->count()) }}
    </h4>

    @forelse($visits as $visit)
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#374151;padding:6px 10px;background:#f9fafb;border-radius:6px;border:1px solid #f1f5f9;">
            <div style="flex:1;min-width:0;">
                <span style="font-weight:500;color:#111827;">{{ $visit->route_name ?? $visit->path ?? '—' }}</span>
                @if($visit->path && $visit->route_name !== $visit->path)
                    <span style="color:#9ca3af;"> — {{ $visit->path }}</span>
                @endif
            </div>
            <span style="color:#9ca3af;white-space:nowrap;margin-left:12px;">{{ $visit->created_at?->format('M j, H:i:s') }}</span>
        </div>
    @empty
        <p style="font-size:12px;color:#9ca3af;font-style:italic;">No page visits recorded for this user.</p>
    @endforelse
</div>
