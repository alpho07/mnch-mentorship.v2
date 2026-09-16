<x-filament-panels::page>
    {{-- Overall Summary Card --}}
    <div class="mb-6">
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 text-white shadow-lg">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <h3 class="text-sm font-medium opacity-90">Overall Score</h3>
                    <p class="text-3xl font-bold mt-1">{{ number_format($assessment->overall_percentage, 1) }}%</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium opacity-90">Grade</h3>
                    <p class="text-3xl font-bold mt-1">
                        <span class="px-3 py-1 rounded-full bg-white/20">
                            {{ strtoupper($assessment->overall_grade ?? 'N/A') }}
                        </span>
                    </p>
                </div>
                <div>
                    <h3 class="text-sm font-medium opacity-90">Completion</h3>
                    <p class="text-3xl font-bold mt-1">{{ number_format($assessment->completion_percentage, 0) }}%</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium opacity-90">Status</h3>
                    <p class="text-2xl font-bold mt-1 capitalize">{{ $assessment->status }}</p>
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-t border-white/20">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="opacity-90">Assessment Date:</span>
                        <span class="font-semibold ml-2">{{ $assessment->assessment_date->format('d M Y') }}</span>
                    </div>
                    <div>
                        <span class="opacity-90">Assessor:</span>
                        <span class="font-semibold ml-2">{{ $assessment->assessor_name }}</span>
                        @if($assessment->assessor_contact)
                            <span class="opacity-75 ml-1">({{ $assessment->assessor_contact }})</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Facility Information --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                Facility Information
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Facility Name</p>
                    <p class="font-semibold">{{ $assessment->facility->name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">MFL Code</p>
                    <p class="font-semibold">{{ $assessment->facility->mfl_code }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Level</p>
                    <p class="font-semibold">{{ $assessment->facility->level }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Ownership</p>
                    <p class="font-semibold">{{ $assessment->facility->ownership }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">County</p>
                    <p class="font-semibold">{{ $assessment->facility->subcounty->county->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sub-County</p>
                    <p class="font-semibold">{{ $assessment->facility->subcounty->name ?? 'N/A' }}</p>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Section Breakdown --}}
    <div class="mb-6">
        <h2 class="text-xl font-bold mb-4">Section Breakdown</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($assessment->sectionScores as $sectionScore)
                @php
                    $section = $sectionScore->section;
                    $gradeColor = match($sectionScore->grade) {
                        'green' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                        'red' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
                    };
                    
                    $progressColor = match($sectionScore->grade) {
                        'green' => 'bg-green-500',
                        'yellow' => 'bg-yellow-500',
                        'red' => 'bg-red-500',
                        default => 'bg-gray-500',
                    };
                @endphp
                
                <x-filament::section>
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <x-filament::icon 
                                    :icon="$section->icon ?? 'heroicon-o-document-text'" 
                                    class="h-5 w-5 text-gray-400"
                                />
                                <h3 class="font-semibold">{{ $section->name }}</h3>
                            </div>
                            
                            <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                <span>{{ $sectionScore->answered_questions }}/{{ $sectionScore->total_questions }} questions answered</span>
                                @if($sectionScore->skipped_questions > 0)
                                    <span class="ml-2 text-yellow-600">({{ $sectionScore->skipped_questions }} skipped)</span>
                                @endif
                            </div>
                            
                            {{-- Progress Bar --}}
                            <div class="mb-3">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="{{ $progressColor }} h-2 rounded-full transition-all" 
                                         style="width: {{ $sectionScore->percentage }}%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ml-4 text-right">
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($sectionScore->percentage, 1) }}%
                            </p>
                            @if($sectionScore->grade)
                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded {{ $gradeColor }} mt-1">
                                    {{ strtoupper($sectionScore->grade) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>

    {{-- Timeline --}}
    <div>
        <x-filament::section>
            <x-slot name="heading">
                Assessment Timeline
            </x-slot>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Created</span>
                    <span class="font-semibold">{{ $assessment->created_at->format('d M Y, h:i A') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Last Updated</span>
                    <span class="font-semibold">{{ $assessment->updated_at->format('d M Y, h:i A') }}</span>
                </div>
                @if($assessment->completed_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Completed</span>
                        <span class="font-semibold">{{ $assessment->completed_at->format('d M Y, h:i A') }}</span>
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>