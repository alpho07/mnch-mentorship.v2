<div class="space-y-6">
    {{-- Mentee Header --}}
    <div class="bg-gray-50 rounded-lg p-4">
        <div class="flex items-center space-x-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-semibold text-lg">
                        {{ strtoupper(substr($participant->user->first_name, 0, 1)) }}{{ strtoupper(substr($participant->user->last_name, 0, 1)) }}
                    </span>
                </div>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900">{{ $participant->user->full_name }}</h3>
                <p class="text-sm text-gray-600">
                    {{ $participant->user->department?->name }} • {{ $participant->user->cadre?->name }}
                </p>
                <p class="text-xs text-gray-500">
                    Enrolled: {{ $participant->registration_date?->format('M j, Y') }}
                </p>
            </div>
            @php
                $results = $participant->user->assessmentResults()
                    ->whereHas('assessmentCategory', function($query) use ($training) {
                        $query->where('training_id', $training->id);
                    })
                    ->with('assessmentCategory')
                    ->get();
                
                $overallScore = null;
                if($results->count() > 0) {
                    $totalWeight = 0;
                    $weightedScore = 0;
                    foreach($results as $result) {
                        $weight = $result->assessmentCategory->weight_percentage / 100;
                        $weightedScore += $result->score * $weight;
                        $totalWeight += $weight;
                    }
                    $overallScore = $totalWeight > 0 ? $weightedScore / $totalWeight : 0;
                }
            @endphp
            
            @if($overallScore !== null)
                <div class="text-right">
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($overallScore, 1) }}%</div>
                    <div class="text-sm font-medium {{ $overallScore >= 70 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $overallScore >= 70 ? 'PASS' : 'FAIL' }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Assessment Results by Category --}}
    <div class="space-y-4">
        <h4 class="text-md font-medium text-gray-900">Assessment Results by Category</h4>
        
        @php
            $categories = $training->assessmentCategories()->orderBy('order_sequence')->get();
            $resultsByCategory = $results->keyBy('assessment_category_id');
        @endphp
        
        @foreach($categories as $category)
            @php
                $result = $resultsByCategory->get($category->id);
            @endphp
            
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3">
                            <h5 class="text-sm font-medium text-gray-900">{{ $category->name }}</h5>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $category->weight_percentage }}% weight
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">{{ $category->description }}</p>
                        <div class="mt-2 text-xs text-gray-500">
                            Pass Threshold: {{ $category->pass_threshold }}% • Method: {{ $category->assessment_method }}
                        </div>
                    </div>
                    
                    <div class="ml-4 text-right">
                        @if($result)
                            <div class="text-xl font-semibold text-gray-900">{{ number_format($result->score, 1) }}%</div>
                            <div class="text-sm font-medium {{ $result->score >= $category->pass_threshold ? 'text-green-600' : 'text-red-600' }}">
                                {{ $result->score >= $category->pass_threshold ? 'PASS' : 'FAIL' }}
                            </div>
                            @if($result->grade)
                                <div class="text-xs text-gray-500 mt-1">Grade: {{ $result->grade->name }}</div>
                            @endif
                        @else
                            <div class="text-sm text-gray-500">Not Assessed</div>
                        @endif
                    </div>
                </div>
                
                @if($result)
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Assessed by:</span>
                                <span class="ml-1 text-gray-900">{{ $result->assessor?->full_name ?? 'Unknown' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Date:</span>
                                <span class="ml-1 text-gray-900">{{ $result->assessment_date?->format('M j, Y g:i A') }}</span>
                            </div>
                            @if($result->time_taken_minutes)
                                <div>
                                    <span class="text-gray-500">Duration:</span>
                                    <span class="ml-1 text-gray-900">{{ $result->time_taken_minutes }} minutes</span>
                                </div>
                            @endif
                            @if($result->attempts > 1)
                                <div>
                                    <span class="text-gray-500">Attempts:</span>
                                    <span class="ml-1 text-gray-900">{{ $result->attempts }}</span>
                                </div>
                            @endif
                        </div>
                        
                        @if($result->feedback)
                            <div class="mt-3">
                                <span class="text-gray-500 text-sm">Feedback:</span>
                                <p class="mt-1 text-sm text-gray-900 bg-gray-50 rounded p-2">{{ $result->feedback }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Performance Summary --}}
    @if($results->count() > 0)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="text-md font-medium text-blue-900 mb-3">Performance Summary</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-900">{{ $results->count() }}/{{ $categories->count() }}</div>
                    <div class="text-sm text-blue-700">Categories Assessed</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-900">{{ number_format($results->avg('score'), 1) }}%</div>
                    <div class="text-sm text-blue-700">Average Score</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-900">
                        {{ $results->filter(fn($r) => $r->score >= $r->assessmentCategory->pass_threshold)->count() }}/{{ $results->count() }}
                    </div>
                    <div class="text-sm text-blue-700">Categories Passed</div>
                </div>
            </div>
        </div>
    @endif
</div>
