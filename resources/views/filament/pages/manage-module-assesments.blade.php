<x-filament-panels::page>
    <x-filament::tabs>
        <x-filament::tabs.item 
            :active="$this->activeTab === 'assessments'"
            wire:click="$set('activeTab', 'assessments')"
            >
            Assessments Configuration
        </x-filament::tabs.item>

        <x-filament::tabs.item 
            :active="$this->activeTab === 'results'"
            wire:click="$set('activeTab', 'results')"
            >
            Mentee Results
        </x-filament::tabs.item>
    </x-filament::tabs>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>