<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Filters Section --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    Dashboard Filters
                </h3>
                <form wire:submit="$refresh">
                    {{ $this->form }}
                    <div class="mt-6 flex justify-end space-x-3">
                        <x-filament::button type="submit" wire:click="$refresh">
                            Apply Filters
                        </x-filament::button>

                        {{-- <x-filament::button
                            color="gray"
                            wire:click="$wire.form.fill(['training_months' => @js($this->getDefaultMonths()), 'training_status' => ['completed']])"
                        > Reset Filters
                        </x-filament::button> --}}
                    </div>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6">
            @livewire(\App\Filament\Widgets\TrainingCoverageStatsWidget::class, ['page' => $this])
        </div>

           {{-- Charts Section --}}
        <div class="grid grid-cols-1 gap-6">
            {{-- Training Heatmap --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                <div class="p-6">
                    @livewire(\App\Filament\Widgets\TrainingHeatmapWidget::class, ['page' => $this])
                </div>
            </div>




</x-filament-panels::page>
