<x-filament-panels::page>
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

    <div style="position:relative;z-index:20;">
        {{ $this->table }}
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px 20px;margin-top:20px;position:relative;z-index:1;">
        <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0 0 12px;">Most Visited Pages ({{ $range === 'today' ? 'Today' : ($range === '30d' ? 'Last 30 days' : 'Last 7 days') }})</h3>
        @forelse($topPages as $page)
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#374151;padding:6px 0;border-top:1px solid #f1f5f9;">
                <span>{{ $page->route_name ?? $page->path }}</span>
                <span style="color:#9ca3af;">{{ $page->visits }} visits</span>
            </div>
        @empty
            <p style="font-size:13px;color:#9ca3af;">No page visits in this range.</p>
        @endforelse
    </div>
</x-filament-panels::page>
