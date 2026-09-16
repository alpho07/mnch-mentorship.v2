<div class="space-y-6">
    @php
        $results = $participant->objectiveResults()->with(['objective', 'grade', 'assessor'])->get();
        $averageScore = $results->avg('score');
        $passStatus = $averageScore >= 70;
    @endphp

    <!-- Overall Result -->
    <div class="bg-gradient-to-r {{ $passStatus ? 'from-green-50 to-emerald-50 border-green-200' : 'from-red-50 to-rose-50 border-red-200' }} rounded-lg p-6 border">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold {{ $passStatus ? 'text-green-900' : 'text-red-900' }}">
                    Overall Result: {{ $passStatus ? 'PASS' : 'FAIL' }}
                </h3>
                <p class="text-sm {{ $passStatus ? 'text-green-700' : 'text-red-700' }}">
                    Average Score: {{ $averageScore ? number_format($averageScore, 1) . '%' : 'Not assessed' }}
                </p>
            </div>
            <div class="text-right">
                <div class="text-3xl font-bold {{ $passStatus ? 'text-green-900' : 'text-red-900' }}">
                    {{ $averageScore ? number_format($averageScore, 1) : '--' }}%
                </div>
                <div class="text-sm {{ $passStatus ? 'text-green-600' : 'text-red-600' }}">
                    {{ $results->count() }} objectives assessed
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Objective Results -->
    <div class="space-y-4">
        <h4 class="text-lg font-medium text-gray-900">Individual Objective Results</h4>

        @forelse($results as $result)
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h5 class="font-medium text-gray-900">{{ $result->objective->objective_text }}</h5>
                        <div class="flex items-center space-x-4 mt-2 text-sm text-gray-600">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($result->objective->type === 'knowledge') bg-blue-100 text-blue-800
                                @elseif($result->objective->type === 'skill') bg-green-100 text-green-800
                                @elseif($result->objective->type === 'attitude') bg-purple-100 text-purple-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst($result->objective->type) }}
                            </span>
                            <span>Pass Criteria: {{ $result->objective->pass_criteria ?? 70 }}%</span>
                            @if($result->assessment_date)
                                <span>Assessed: {{ $result->assessment_date->format('M j, Y') }}</span>
                            @endif
                        </div>

                        @if($result->feedback)
                            <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-700">
                                    <strong>Feedback:</strong> {{ $result->feedback }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="text-right ml-4">
                        <div class="text-2xl font-bold
                            @if($result->score >= ($result->objective->pass_criteria ?? 70)) text-green-600
                            @else text-red-600
                            @endif">
                            {{ number_format($result->score, 1) }}%
                        </div>

                        @if($result->grade)
                            <div class="text-sm font-medium
                                @if($result->score >= ($result->objective->pass_criteria ?? 70)) text-green-600
                                @else text-red-600
                                @endif">
                                {{ $result->grade->name }}
                            </div>
                        @endif

                        <div class="text-xs text-gray-500 mt-1">
                            @if($result->score >= ($result->objective->pass_criteria ?? 70))
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    PASS
                                </span>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    FAIL
                                </span>
                            @endif
                        </div>

                        @if($result->assessor)
                            <div class="text-xs text-gray-500 mt-2">
                                Assessed by: {{ $result->assessor->full_name }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No assessments yet</h3>
                <p class="mt-1 text-sm text-gray-500">This participant has not been assessed against any objectives.</p>
            </div>
        @endforelse
    </div>

    <!-- Assessment Summary -->
    @if($results->isNotEmpty())
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-900 mb-3">Assessment Summary</h4>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-600">Objectives Passed:</span>
                    <span class="font-medium">{{ $results->where('score', '>=', 70)->count() }}/{{ $results->count() }}</span>
                </div>
                <div>
                    <span class="text-gray-600">Highest Score:</span>
                    <span class="font-medium">{{ $results->max('score') ? number_format($results->max('score'), 1) . '%' : 'N/A' }}</span>
                </div>
                <div>
                    <span class="text-gray-600">Lowest Score:</span>
                    <span class="font-medium">{{ $results->min('score') ? number_format($results->min('score'), 1) . '%' : 'N/A' }}</span>
                </div>
                <div>
                    <span class="text-gray-600">Assessment Status:</span>
                    <span class="font-medium">{{ $passStatus ? 'Completed Successfully' : 'Needs Improvement' }}</span>
                </div>
            </div>
        </div>
    @endif
</div>
