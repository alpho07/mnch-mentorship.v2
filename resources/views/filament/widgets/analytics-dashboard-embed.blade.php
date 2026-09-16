@if($this->isActive())
{{-- ── Analytics Home Dashboard ─────────────────────────────────────────── --}}
<style>
    /* Hide table + empty-state + filter bar when analytics home is active */
    .fi-ta,
    .fi-ta-content,
    .fi-ta-header-toolbar,
    .fi-ta-empty-state {
        display: none !important;
    }
    /* Remove extra padding around the widget area */
    .fi-wi-analytics-dashboard-embed {
        padding: 0 !important;
        margin: 0 !important;
    }
    .analytics-home-frame {
        width: 100%;
        height: calc(100vh - 140px);
        min-height: 620px;
        border: none;
        display: block;
        border-radius: 0 0 12px 12px;
    }
</style>

<div class="relative">
    {{-- Loading overlay while iframe loads --}}
    <div
        id="analytics-loading-{{ $this->getMode() }}"
        style="position:absolute;inset:0;background:#f8fafc;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;z-index:10;min-height:200px;border-radius:12px;"
    >
        <svg style="width:40px;height:40px;stroke:#0097A7;fill:none;animation:spin .9s linear infinite" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" style="opacity:.2;stroke:#0097A7;stroke-width:2.5"/>
            <path d="M4 12a8 8 0 018-8" style="stroke:#0097A7;stroke-width:2.5;stroke-linecap:round"/>
        </svg>
        <span style="font-size:.9rem;font-weight:600;color:#64748b;">Loading {{ ucfirst($this->getMode()) }} Analytics…</span>
    </div>

    <iframe
        src="{{ $this->getIframeUrl() }}"
        class="analytics-home-frame"
        loading="eager"
        onload="document.getElementById('analytics-loading-{{ $this->getMode() }}').style.display='none'"
        title="{{ ucfirst($this->getMode()) }} Analytics Dashboard"
    ></iframe>
</div>

<style>
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
@endif
