{{-- Subtitle and version under the product title in the admin sidebar (hidden when the sidebar is collapsed). --}}
<div x-data x-show="$store.sidebar ? $store.sidebar.isOpen : true" x-cloak class="mnch-brand-sub">
    <span class="mnch-brand-tagline">{{ config('app.tagline') }}</span>
    <span class="mnch-brand-version" title="Platform version {{ config('app.version') }}">v{{ ltrim((string) config('app.version'), 'vV') }}</span>
</div>
