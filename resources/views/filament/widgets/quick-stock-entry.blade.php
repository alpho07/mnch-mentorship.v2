{{-- resources/views/filament/widgets/quick-stock-entry.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <form wire:submit="addStock">
                {{ $this->form }}
                
                <div class="flex justify-end mt-6">
                    <x-filament::button 
                        type="submit"
                        color="primary"
                        icon="heroicon-o-plus"
                        size="lg">
                        Add Stock
                    </x-filament::button>
                </div>
            </form>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>