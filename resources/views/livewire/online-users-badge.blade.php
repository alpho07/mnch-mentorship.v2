<div
    id="online-users-badge"
    wire:poll.30s
    style="position:fixed;bottom:1rem;left:1rem;z-index:40;"
>
    <a
        href="{{ \App\Filament\Pages\ActivityDashboard::getUrl() }}#currently-online"
        style="
            display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;
            background:#111827;color:#fff;font-size:12px;font-weight:600;text-decoration:none;
            box-shadow:0 2px 8px rgba(0,0,0,0.15);
        "
    >
        <span style="width:8px;height:8px;border-radius:9999px;background:#22c55e;display:inline-block;"></span>
        {{ $onlineUsers->count() }} online
    </a>
</div>
