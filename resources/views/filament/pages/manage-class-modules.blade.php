<x-filament::page>
    {{ $this->table }}

    @push('styles')
    <style>
        /* Force the Add Modules slide-over content to scroll vertically */
        .scrollable-module-slideover {
            display: flex !important;
            flex-direction: column !important;
            height: 100vh !important;
            max-height: 100vh !important;
        }
        .scrollable-module-slideover .fi-modal-content {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overflow-y: auto !important;
        }
    </style>
    @endpush
</x-filament::page>
