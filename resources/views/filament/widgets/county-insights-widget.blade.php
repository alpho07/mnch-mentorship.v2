<x-filament::widget>
    <x-filament::card>
        <div class="space-y-4">
            <h2 class="text-lg font-bold">County Insights</h2>

            {{-- Render stat cards --}}
            {{ $this->renderCards() }}

            {{-- Future: we can add facility type breakdown chart here --}}
        </div>
    </x-filament::card>
</x-filament::widget>
