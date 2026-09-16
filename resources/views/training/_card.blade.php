{{-- Training Card Component --}}
<div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all duration-200">
    {{-- Header --}}
    <div class="flex items-start justify-between mb-4">
        <div class="flex-1">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $t->title }}</h3>
            <div class="flex flex-wrap gap-2 mb-3">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                    {{ $t->type === 'global_training' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                    @if($t->type === 'global_training')
                        <i class="fas fa-globe mr-1"></i> Global Training
                    @else
                        <i class="fas fa-hospital mr-1"></i> Facility Mentorship
                    @endif
                </span>
                
                @php
                    $statusColors = [
                        'draft' => 'bg-gray-100 text-gray-800',
                        'published' => 'bg-blue-100 text-blue-800',
                        'ongoing' => 'bg-yellow-100 text-yellow-800',
                        'completed' => 'bg-green-100 text-green-800',
                        'cancelled' => 'bg-red-100 text-red-800'
                    ];
                    $statusColor = $statusColors[$t->status] ?? 'bg-gray-100 text-gray-800';
                @endphp
                
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColor }}">
                    {{ ucfirst($t->status) }}
                </span>
            </div>
        </div>
        
        @if($t->identifier)
            <div class="text-right">
                <span class="text-sm font-mono text-gray-500">{{ $t->identifier }}</span>
            </div>
        @endif
    </div>

    {{-- Programs --}}
    @if($t->programs && $t->programs->count() > 0)
        <div class="mb-4">
            <div class="text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-graduation-cap mr-1"></i> Programs
            </div>
            <div class="flex flex-wrap gap-1">
                @foreach($t->programs->take(3) as $program)
                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs bg-gray-100 text-gray-700">
                        {{ $program->name }}
                    </span>
                @endforeach
                @if($t->programs->count() > 3)
                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs bg-gray-100 text-gray-700">
                        +{{ $t->programs->count() - 3 }} more
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Training Details --}}
    <div class="space-y-2 mb-4">
        {{-- Dates --}}
        @if($t->start_date)
            <div class="flex items-center text-sm text-gray-600">
                <i class="fas fa-calendar mr-2 text-gray-400"></i>
                <span>{{ $t->start_date->format('M j, Y') }}</span>
                @if($t->end_date && $t->end_date != $t->start_date)
                    <span class="mx-1">→</span>
                    <span>{{ $t->end_date->format('M j, Y') }}</span>
                @endif
            </div>
        @endif

        {{-- Location --}}
        @if($t->location)
            <div class="flex items-center text-sm text-gray-600">
                <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                <span>{{ $t->location }}</span>
            </div>
        @endif

        {{-- Facility (for mentorship) --}}
        @if($t->facility)
            <div class="flex items-center text-sm text-gray-600">
                <i class="fas fa-hospital mr-2 text-gray-400"></i>
                <span>{{ $t->facility->name }}</span>
            </div>
        @endif

        {{-- Organizer --}}
        @if($t->organizer)
            <div class="flex items-center text-sm text-gray-600">
                <i class="fas fa-user mr-2 text-gray-400"></i>
                <span>{{ $t->organizer->full_name }}</span>
            </div>
        @endif

        {{-- Mentor (for mentorship) --}}
        @if($t->mentor)
            <div class="flex items-center text-sm text-gray-600">
                <i class="fas fa-user-graduate mr-2 text-gray-400"></i>
                <span>Mentor: {{ $t->mentor->full_name }}</span>
            </div>
        @endif
    </div>

    {{-- Participants Summary --}}
    @if($t->participants && $t->participants->count() > 0)
        <div class="bg-gray-50 rounded-lg p-3 mb-4">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600">
                    <i class="fas fa-users mr-1"></i>
                    {{ $t->participants->count() }} Participants
                </span>
                @if($t->completion_rate > 0)
                    <span class="text-green-600 font-medium">
                        {{ $t->completion_rate }}% Complete
                    </span>
                @endif
            </div>
            
            @if($t->participants->count() > 0)
                <div class="mt-2">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: {{ $t->completion_rate }}%"></div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Training Approaches --}}
    @if($t->training_approaches && count($t->training_approaches) > 0)
        <div class="mb-4">
            <div class="text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-cogs mr-1"></i> Approaches
            </div>
            <div class="flex flex-wrap gap-1">
                @foreach(array_slice($t->training_approaches, 0, 3) as $approach)
                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs bg-indigo-100 text-indigo-700">
                        {{ ucfirst(str_replace('_', ' ', $approach)) }}
                    </span>
                @endforeach
                @if(count($t->training_approaches) > 3)
                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs bg-indigo-100 text-indigo-700">
                        +{{ count($t->training_approaches) - 3 }} more
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Action Buttons --}}
    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
        <div class="flex items-center space-x-2">
            {{-- Quick Actions --}}
            @if($t->status === 'ongoing' || $t->status === 'published')
                <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <i class="fas fa-eye mr-1"></i> View
                </a>
            @endif
            
            @if($t->participants->count() > 0)
                <a href="#" class="text-green-600 hover:text-green-800 text-sm font-medium">
                    <i class="fas fa-users mr-1"></i> Participants
                </a>
            @endif
        </div>
        
        <div class="flex items-center space-x-2">
            {{-- Training Type Specific Actions --}}
            @if($t->type === 'global_training')
                <a href="{{ route('training.heatmap.moh') }}" 
                   class="text-gray-500 hover:text-gray-700 text-sm" 
                   title="View on Map">
                    <i class="fas fa-map"></i>
                </a>
            @else
                <a href="{{ route('training.heatmap.mentorship') }}" 
                   class="text-gray-500 hover:text-gray-700 text-sm" 
                   title="View on Map">
                    <i class="fas fa-map-marked-alt"></i>
                </a>
            @endif
            
            {{-- Admin Actions (if user has permission) --}}
            @auth
                <a href="{{ url('admin/trainings/' . $t->id) }}" 
                   class="text-gray-500 hover:text-gray-700 text-sm" 
                   title="Edit Training">
                    <i class="fas fa-edit"></i>
                </a>
            @endauth
        </div>
    </div>
</div>