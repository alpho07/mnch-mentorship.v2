<x-filament::page>
    <div class="space-y-6">
        {{-- Show table if no user is loaded --}}
        @if (! $this->user)
            {{ $this->table }}
        @else
            {{-- Profile Card --}}
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">{{ $this->user->full_name }} - Profile</h2>
                <x-filament::button wire:click="$set('user', null)" color="gray">
                    ← Back to List
                </x-filament::button>
            </div>

            {{ $this->infolist }}

            {{-- Training Cards --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
                {{-- Trainings Card --}}
                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <x-heroicon-o-academic-cap class="w-5 h-5 mr-2 text-blue-500" />
                            Trainings ({{ $this->user->trainingParticipants->where('training.type', 'global_training')->count() }})
                        </h3>
                    </div>
                    
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        @forelse($this->user->trainingParticipants->where('training.type', 'global_training') as $participant)
                            <div class="border border-gray-100 rounded-lg p-3 hover:bg-gray-50">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-sm">{{ $participant->training->title }}</h4>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <span>{{ $participant->registration_date?->format('M d, Y') ?? 'Not set' }}</span>
                                            <span class="mx-2">•</span>
                                            <span class="px-2 py-1 bg-gray-100 rounded text-xs">{{ $participant->completion_status ?? 'Not set' }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex gap-2 mt-3">
                                    <x-filament::button 
                                        wire:click="viewAssessments({{ $participant->id }})"
                                        size="xs"
                                        color="primary"
                                    >
                                        Assessments
                                    </x-filament::button>
                                    
                                    <x-filament::button 
                                        wire:click="updateStatus({{ $participant->id }}, 'training')"
                                        size="xs"
                                        color="success"
                                    >
                                        Update Status
                                    </x-filament::button>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4 text-sm">No trainings found</p>
                        @endforelse
                    </div>
                </div>

                {{-- Mentorships Card --}}
                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <x-heroicon-o-user-group class="w-5 h-5 mr-2 text-orange-500" />
                            Mentorships ({{ $this->user->trainingParticipants->where('training.type', 'facility_mentorship')->count() }})
                        </h3>
                    </div>
                    
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        @forelse($this->user->trainingParticipants->where('training.type', 'facility_mentorship') as $participant)
                            <div class="border border-gray-100 rounded-lg p-3 hover:bg-gray-50">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-sm">{{ $participant->training->title }}</h4>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <span>{{ $participant->registration_date?->format('M d, Y') ?? 'Not set' }}</span>
                                            <span class="mx-2">•</span>
                                            <span class="px-2 py-1 bg-gray-100 rounded text-xs">{{ $participant->completion_status ?? 'Not set' }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex gap-2 mt-3">
                                    <x-filament::button 
                                        wire:click="viewAssessments({{ $participant->id }})"
                                        size="xs"
                                        color="primary"
                                    >
                                        Assessments
                                    </x-filament::button>
                                    
                                    <x-filament::button 
                                        wire:click="updateStatus({{ $participant->id }}, 'mentorship')"
                                        size="xs"
                                        color="warning"
                                    >
                                        Update Status
                                    </x-filament::button>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4 text-sm">No mentorships found</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament::page>