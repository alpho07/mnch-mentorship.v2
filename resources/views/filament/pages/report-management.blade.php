<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            <x-filament::button
                type="button"
                wire:click="assignTemplate"
                color="primary"
                icon="heroicon-o-link"
            >
                Assign Template
            </x-filament::button>

            <x-filament::button
                type="button"
                wire:click="generateReports"
                color="success"
                icon="heroicon-o-document-plus"
            >
                Generate Reports
            </x-filament::button>
        </div>
    </form>

    <div class="mt-8">
        <x-filament::section>
            <x-slot name="heading">
                Current Template Assignments
            </x-slot>

            <x-slot name="description">
                Overview of active template assignments across facilities
            </x-slot>

            <div class="space-y-4">
                @php
                    $user = auth()->user();
                    $facilityIds = $user->isAboveSite()
                        ? \App\Models\Facility::pluck('id')
                        : $user->scopedFacilityIds();

                    $assignments = \App\Models\FacilityReportTemplate::with(['facility', 'reportTemplate'])
                        ->whereIn('facility_id', $facilityIds)
                        ->where(function($query) {
                            $query->whereNull('end_date')
                                  ->orWhere('end_date', '>=', now()->format('Y-m-d'));
                        })
                        ->get()
                        ->groupBy('reportTemplate.name');
                @endphp

                @forelse($assignments as $templateName => $templateAssignments)
                    <div class="border rounded-lg p-4 dark:border-gray-700">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">
                            {{ $templateName }}
                        </h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            Assigned to {{ $templateAssignments->count() }} facilities
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                            @foreach($templateAssignments->take(6) as $assignment)
                                <div class="text-xs bg-gray-100 dark:bg-gray-800 rounded px-2 py-1">
                                    {{ $assignment->facility->name }}
                                </div>
                            @endforeach
                            @if($templateAssignments->count() > 6)
                                <div class="text-xs text-gray-500 px-2 py-1">
                                    +{{ $templateAssignments->count() - 6 }} more...
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                        No active template assignments found.
                    </p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
