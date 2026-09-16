<div class="space-y-6">
    {{-- Performance Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-full">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-blue-600">Completion Rate</p>
                    <p class="text-lg font-semibold text-blue-900">{{ number_format($training->completion_rate ?? 0, 1) }}%</p>
                </div>
            </div>
        </div>

        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-full">
                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-600">Pass Rate</p>
                    <p class="text-lg font-semibold text-green-900">{{ number_format($insights['pass_rate'] ?? 0, 1) }}%</p>
                </div>
            </div>
        </div>

        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-full">
                    <svg class="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-purple-600">Mentees</p>
                    <p class="text-lg font-semibold text-purple-900">{{ $training->participants()->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-full">
                    <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.51-1.31c-.562-.649-1.413-1.076-2.353-1.253V5z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-yellow-600">Material Cost</p>
                    <p class="text-lg font-semibold text-yellow-900">${{ number_format($training->trainingMaterials->sum('actual_cost') ?? 0, 0) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Performance Distribution Chart --}}
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Performance Distribution</h3>
        <div class="space-y-3">
            @php
                $participants = $training->participants()->with('user.assessmentResults.assessmentCategory')->get();
                $performanceBuckets = [
                    'Excellent (90%+)' => 0,
                    'Very Good (80-89%)' => 0,
                    'Good (70-79%)' => 0,
                    'Needs Improvement (<70%)' => 0,
                    'Not Assessed' => 0
                ];
                
                foreach($participants as $participant) {
                    $results = $participant->user->assessmentResults()
                        ->whereHas('assessmentCategory', function($q) use ($training) {
                            $q->where('training_id', $training->id);
                        })->get();
                    
                    if($results->count() == 0) {
                        $performanceBuckets['Not Assessed']++;
                    } else {
                        $avgScore = $results->avg('score');
                        if($avgScore >= 90) $performanceBuckets['Excellent (90%+)']++;
                        elseif($avgScore >= 80) $performanceBuckets['Very Good (80-89%)']++;
                        elseif($avgScore >= 70) $performanceBuckets['Good (70-79%)']++;
                        else $performanceBuckets['Needs Improvement (<70%)']++;
                    }
                }
                
                $totalParticipants = $participants->count();
            @endphp
            
            @foreach($performanceBuckets as $label => $count)
                @php
                    $percentage = $totalParticipants > 0 ? ($count / $totalParticipants) * 100 : 0;
                    $colorClass = match($label) {
                        'Excellent (90%+)' => 'bg-green-500',
                        'Very Good (80-89%)' => 'bg-blue-500',
                        'Good (70-79%)' => 'bg-yellow-500',
                        'Needs Improvement (<70%)' => 'bg-red-500',
                        default => 'bg-gray-400'
                    };
                @endphp
                
                <div class="flex items-center">
                    <div class="w-32 text-sm text-gray-600">{{ $label }}</div>
                    <div class="flex-1 mx-4">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="{{ $colorClass }} h-2 rounded-full transition-all duration-300"
                                 style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                    <div class="w-16 text-sm text-gray-900 font-medium">
                        {{ $count }} ({{ number_format($percentage, 1) }}%)
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Smart Insights --}}
    <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900">Smart Insights & Recommendations</h3>
        
        @forelse($insights as $insight)
            <div class="flex items-start p-4 rounded-lg border 
                {{ $insight['type'] === 'success' ? 'bg-green-50 border-green-200' : '' }}
                {{ $insight['type'] === 'warning' ? 'bg-yellow-50 border-yellow-200' : '' }}
                {{ $insight['type'] === 'danger' ? 'bg-red-50 border-red-200' : '' }}
                {{ $insight['type'] === 'info' ? 'bg-blue-50 border-blue-200' : '' }}">
                
                <div class="flex-shrink-0">
                    @if($insight['type'] === 'success')
                        <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    @elseif($insight['type'] === 'warning')
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    @elseif($insight['type'] === 'danger')
                        <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    @endif
                </div>
                
                <div class="ml-3">
                    <h4 class="text-sm font-medium 
                        {{ $insight['type'] === 'success' ? 'text-green-800' : '' }}
                        {{ $insight['type'] === 'warning' ? 'text-yellow-800' : '' }}
                        {{ $insight['type'] === 'danger' ? 'text-red-800' : '' }}
                        {{ $insight['type'] === 'info' ? 'text-blue-800' : '' }}">
                        {{ $insight['title'] }}
                    </h4>
                    <p class="mt-1 text-sm 
                        {{ $insight['type'] === 'success' ? 'text-green-700' : '' }}
                        {{ $insight['type'] === 'warning' ? 'text-yellow-700' : '' }}
                        {{ $insight['type'] === 'danger' ? 'text-red-700' : '' }}
                        {{ $insight['type'] === 'info' ? 'text-blue-700' : '' }}">
                        {{ $insight['message'] }}
                    </p>
                    @if(isset($insight['action']))
                        <div class="mt-2 flex items-center text-xs font-medium 
                            {{ $insight['type'] === 'success' ? 'text-green-600' : '' }}
                            {{ $insight['type'] === 'warning' ? 'text-yellow-600' : '' }}
                            {{ $insight['type'] === 'danger' ? 'text-red-600' : '' }}
                            {{ $insight['type'] === 'info' ? 'text-blue-600' : '' }}">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M9.664 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                            </svg>
                            Recommended Action: {{ $insight['action'] }}
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                <p class="mt-2 text-sm">No insights available yet. Complete more assessments to generate recommendations.</p>
            </div>
        @endforelse
    </div>
</div>