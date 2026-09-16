<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Training Information Header -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                            <x-heroicon-o-users class="w-7 h-7 text-white" />
                        </div>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-blue-900">{{ $record->title }}</h2>
                        <p class="text-sm text-blue-700">
                            MOH Training • {{ $record->identifier }} •
                            {{ $record->start_date?->format('M j, Y') }} to {{ $record->end_date?->format('M j, Y') }}
                        </p>
                        <p class="text-sm text-blue-600 mt-1">{{ $record->location }}</p>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="text-right">
                        <div class="text-3xl font-bold text-blue-900">{{ $record->participants()->count() }}</div>
                        <div class="text-blue-600">Enrolled Participants</div>
                        @if ($record->max_participants)
                            <div class="text-sm text-blue-500">of {{ $record->max_participants }} maximum</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
