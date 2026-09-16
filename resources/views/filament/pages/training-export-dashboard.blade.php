{{-- resources/views/filament/pages/training-export-dashboard.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600">{{ number_format($stats['total_trainings']) }}</div>
                    <div class="text-sm text-gray-600">Total Trainings/Mentorships</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-success-600">{{ number_format($stats['total_participants']) }}</div>
                    <div class="text-sm text-gray-600">Total Participants/Mentees</div>
                </div>
            </x-filament::section>

          
          
        </div>

        {{-- Main Content --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Export Types --}}
            <div class="lg:col-span-2">
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center space-x-2">
                            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                            </svg>
                            <span class="text-lg font-semibold">Available Export Types</span>
                        </div>
                    </x-slot>

                    <div class="space-y-4">
                        @foreach($availableExportTypes as $exportType)
                        <div class="border border-gray-200 rounded-lg p-6 hover:border-{{ $exportType['color'] }}-300 hover:bg-{{ $exportType['color'] }}-50/50 transition-all duration-200">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-{{ $exportType['color'] }}-100 rounded-lg flex items-center justify-center">
                                        <x-heroicon-o-users class="w-6 h-6 text-{{ $exportType['color'] }}-600" />
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                                        {{ $exportType['title'] }}
                                    </h3>
                                    <p class="text-sm text-gray-600 mb-3">
                                        {{ $exportType['description'] }}
                                    </p>
                                    
                                    <div class="mb-4">
                                        <h4 class="text-sm font-medium text-gray-900 mb-2">Common Use Cases:</h4>
                                        <ul class="text-sm text-gray-600 space-y-1">
                                            @foreach($exportType['use_cases'] as $useCase)
                                            <li class="flex items-center space-x-2">
                                                <svg class="w-4 h-4 text-{{ $exportType['color'] }}-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span>{{ $useCase }}</span>
                                            </li>
                                            @endforeach
                                        </ul>
                                    </div>

                                    <x-filament::button
                                        tag="a"
                                        href="{{ \App\Filament\Resources\TrainingExportResource::getUrl('create') }}"
                                        color="{{ $exportType['color'] }}"
                                        size="sm"
                                    >
                                        Create This Export
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </x-filament::section>
            </div>

            {{-- Recent Trainings & Quick Stats --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- Quick Actions --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span class="font-semibold">Quick Actions</span>
                        </div>
                    </x-slot>

                    <div class="space-y-3">
                        <x-filament::button
                            tag="a"
                            href="{{ \App\Filament\Resources\TrainingExportResource::getUrl('create') }}"
                            color="primary"
                            class="w-full justify-center"
                        >
                            <x-slot name="icon">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                            </x-slot>
                            New Export
                        </x-filament::button>

                        <x-filament::button
                            tag="a"
                            href="{{ \App\Filament\Resources\GlobalTrainingResource::getUrl('index') }}"
                            color="gray"
                            outlined
                            class="w-full justify-center"
                        >
                            View MOH Trainings
                        </x-filament::button>

                        <x-filament::button
                            tag="a"
                            href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('index') }}"
                            color="gray"
                            outlined
                            class="w-full justify-center"
                        >
                            View Mentorships
                        </x-filament::button>
                    </div>
                </x-filament::section>

                {{-- Export Statistics --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="font-semibold">Export Statistics</span>
                    </x-slot>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">MOH Global Trainings</span>
                            <span class="text-sm font-medium">{{ number_format($stats['global_trainings']) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Facility Mentorships</span>
                            <span class="text-sm font-medium">{{ number_format($stats['facility_mentorships']) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Trainings with Participants</span>
                            <span class="text-sm font-medium">{{ number_format($stats['trainings_with_participants']) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Trainings with Assessments</span>
                            <span class="text-sm font-medium">{{ number_format($stats['trainings_with_assessments']) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-sm text-gray-600">Completion Rate</span>
                            <span class="text-sm font-medium">
                                @if($stats['total_participants'] > 0)
                                    {{ round(($stats['completed_participants'] / $stats['total_participants']) * 100, 1) }}%
                                @else
                                    0%
                                @endif
                            </span>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Recent Trainings --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="font-semibold">Recent Trainings Available for Export</span>
                    </x-slot>

                    <div class="space-y-3">
                        @forelse($recentTrainings as $training)
                        <div class="border border-gray-200 rounded-lg p-3">
                            <h4 class="font-medium text-sm text-gray-900 mb-1">
                                {{ Str::limit($training->title, 40) }}
                            </h4>
                            <div class="flex items-center justify-between text-xs text-gray-600">
                                <span class="flex items-center space-x-1">
                                    @if($training->type === 'global_training')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            MOH
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                            Mentorship
                                        </span>
                                    @endif
                                </span>
                                <span>{{ $training->participants->count() }} participants</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $training->facility?->name ?? $training->county?->name ?? 'Various locations' }}
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-gray-500 py-4">
                            <p class="text-sm">No recent trainings found.</p>
                            <p class="text-xs mt-1">Create some trainings first to enable exports.</p>
                        </div>
                        @endforelse
                    </div>
                </x-filament::section>
            </div>
        </div>

        {{-- Help Section --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-semibold">How to Use Training Exports</span>
                </div>
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-primary-600">1</span>
                    </div>
                    <h3 class="font-medium text-gray-900 mb-2">Choose Export Type</h3>
                    <p class="text-sm text-gray-600">Select whether you want participant lists, training histories, or summary reports based on your needs.</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-primary-600">2</span>
                    </div>
                    <h3 class="font-medium text-gray-900 mb-2">Configure Filters</h3>
                    <p class="text-sm text-gray-600">Apply filters by county, facility, dates, or participant characteristics to get exactly the data you need.</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-primary-600">3</span>
                    </div>
                    <h3 class="font-medium text-gray-900 mb-2">Download & Use</h3>
                    <p class="text-sm text-gray-600">Get professional Excel files with multiple worksheets, ready for analysis, reporting, or printing.</p>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>