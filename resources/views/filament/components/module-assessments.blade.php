<div class="space-y-4">
    @if($assessments->count() > 0)
        @foreach($assessments as $assessment)
            @php
                $result = $results->firstWhere('module_assessment_id', $assessment->id);
            @endphp

            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ $assessment->title }}</h4>
                        @if($assessment->description)
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $assessment->description }}</p>
                        @endif

                        <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Type:</span>
                                <span class="ml-1 font-medium text-gray-900 dark:text-white">{{ ucfirst($assessment->assessment_type) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Pass Threshold:</span>
                                <span class="ml-1 font-medium text-gray-900 dark:text-white">{{ $assessment->pass_threshold }}%</span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Max Score:</span>
                                <span class="ml-1 font-medium text-gray-900 dark:text-white">{{ $assessment->max_score }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Weight:</span>
                                <span class="ml-1 font-medium text-gray-900 dark:text-white">{{ $assessment->weight_percentage }}%</span>
                            </div>
                        </div>
                    </div>

                    <div class="ml-4">
                        @if($result)
                            <div class="text-right">
                                <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $result->status === 'passed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                    {{ ucfirst($result->status) }}
                                </div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white mt-2">
                                    {{ $result->score }}%
                                </div>
                                @if($result->assessed_at)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $result->assessed_at->format('M d, Y') }}
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                    Not Attempted
                            </div>
                        @endif
                    </div>
                </div>

                @if($result && $result->feedback)
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Feedback:</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $result->feedback }}</p>
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
        No assessments available for this module.
        </div>
    @endif
</div>