<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-filament::card>
                <div class="text-sm text-gray-500">Total Mentees</div>
                <div class="text-2xl font-bold">{{ $this->table->getRecords()->count() }}</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500">Completed</div>
                <div class="text-2xl font-bold text-success-600">
                    {{ $this->table->getRecords()->filter(fn($r) => $r->moduleProgress->first()?->status === 'completed')->count() }}
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500">Exempted</div>
                <div class="text-2xl font-bold text-info-600">
                    {{ $this->table->getRecords()->filter(fn($r) => $r->moduleProgress->first()?->is_exempted)->count() }}
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500">Pending</div>
                <div class="text-2xl font-bold text-warning-600">
                    {{ $this->table->getRecords()->filter(fn($r) => in_array($r->moduleProgress->first()?->status, ['not_started', 'in_progress']))->count() }}
                </div>
            </x-filament::card>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>