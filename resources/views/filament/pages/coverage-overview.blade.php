<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Section -->
        <x-filament::card>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Filters</h3>
                <x-filament::button wire:click="clearFilters" color="gray" size="sm" icon="heroicon-o-x-mark">
                    Clear All
                </x-filament::button>
            </div>
            {{ $this->form }}
        </x-filament::card>

        <div class="grid grid-cols-1 gap-6">
            <livewire:training-coverage-stats-widget :program_id="$program_id" :period="$period" :county_id="$county_id"
                :subcounty_id="$subcounty_id" :facility_id="$facility_id" :department_id="$department_id" :cadre_id="$cadre_id" />
        </div>

        {{-- Notification with button --}}
        <x-filament::card class="flex items-center justify-between bg-blue-50 border border-blue-300 p-4">
            <div class="flex items-center">
                <x-heroicon-o-fire class="w-6 h-6 text-blue-500 mr-3" />
                <span class="font-semibold">Trainings have intensified over a period of time.</span>
                <span class="ml-2">View the heatmap for a visual overview.</span>
            </div>
            <x-filament::button tag="a" href="{{ route('training.heatmap') }}" color="primary" size="sm"
                class="ml-4" icon="heroicon-o-map">
                View Heatmap
            </x-filament::button>
        </x-filament::card>
    </div>
</x-filament-panels::page>
