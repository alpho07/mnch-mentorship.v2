{{-- ================================================ --}}
{{-- resources/views/filament/components/mentee-performance-insights.blade.php --}}
{{-- ================================================ --}}

<div class="space-y-6">
    {{-- Mentee Header Section --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6">
        <div class="flex items-center space-x-4">
            <div class="flex-shrink-0">
                <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full flex items-center justify-center shadow-lg">
                    <span class="text-white font-bold text-xl">
                        {{ strtoupper(substr($mentee->first_name, 0, 1)) }}{{ strtoupper(substr($mentee->last_name, 0, 1)) }}
                    </span>
                </div>
            </div>
            <div class="flex-1">
                <h2 class="text-xl font-bold text-gray-900">{{ $mentee->full_name }}</h2>
                <div class="flex items-center space-x-4 mt-1">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ $mentee->facility?->name }}
                    </span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ $mentee->department?->name }}
                    </span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                        {{ $mentee->cadre?->name }}
                    </span>
                </div>
                <p class="text-sm text-gray-600 mt-2">
                    <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                    {{ $mentee->phone }}
                    @if($mentee->email)
                        <span class="mx-2">•</span>
                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        {{ $mentee->email }}
                    @endif
                </p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-500">Member since</div>
                <div class="text-lg font-semibold text-gray-900">{{ $mentee->created_at?->format('M Y') }}</div>
            </div>
        </div>
    </div>

    {{-- Performance Overview Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Overall Score</p>
                    <div class="flex items-baseline">
                        <p class="text-2xl font-bold text-gray-900">
                            {{ $mentee->overall_training_score ? number_format($mentee->overall_training_score, 1) : 'N/A' }}
                        </p>
                        @if($mentee->overall_training_score)
                            <p class="text-sm text-gray-500 ml-1">%</p>
                        @endif
                    </div>
                    @if($mentee->overall_training_score)
                        <div class="mt-1">
                            @if($mentee->overall_training_score >= 90)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Excellent
                                </span>
                            @elseif($mentee->overall_training_score >= 80)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Very Good
                                </span>
                            @elseif($mentee->overall_training_score >= 70)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Good
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Needs Improvement
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Completion Rate</p>
                    <div class="flex items-baseline">
                        <p class="text-2xl font-bold text-gray-900">{{ $mentee->training_completion_rate }}</p>
                        <p class="text-sm text-gray-500 ml-1">%</p>
                    </div>
                    <div class="mt-1 w-full bg-gray-200 rounded-full h-1.5">
                        <div class="bg-green-500 h-1.5 rounded-full transition-all duration-300" 
                             style="width: {{ $mentee->training_completion_rate }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Trainings</p>
                    <div class="flex items-baseline">
                        <p class="text-2xl font-bold text-gray-900">{{ $mentee->training_history_summary['total_trainings'] }}</p>
                    </div>
                    <p class="text-xs text-gray-500">
                        {{ $mentee->training_history_summary['completed'] }} completed • 
                        {{ $mentee->training_history_summary['passed'] }} passed
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center">
                <div class="p-2 
                    {{ $mentee->performance_trend === 'Improving' ? 'bg-green-100' : ($mentee->performance_trend === 'Declining' ? 'bg-red-100' : 'bg-yellow-100') }} 
                    rounded-lg">
                    @if($mentee->performance_trend === 'Improving')
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    @elseif($mentee->performance_trend === 'Declining')
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                        </svg>
                    @else
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    @endif
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Performance Trend</p>
                    <div class="flex items-center">
                        <p class="text-lg font-bold 
                            {{ $mentee->performance_trend === 'Improving' ? 'text-green-900' : ($mentee->performance_trend === 'Declining' ? 'text-red-900' : 'text-yellow-900') }}">
                            {{ $mentee->performance_trend }}
                        </p>
                    </div>
                    <p class="text-xs text-gray-500">Based on recent assessments</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Attrition Risk Assessment --}}
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">Risk Assessment</h3>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                {{ $mentee->attrition_risk === 'Low' ? 'bg-green-100 text-green-800' : '' }}
                {{ $mentee->attrition_risk === 'Medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                {{ $mentee->attrition_risk === 'High' ? 'bg-red-100 text-red-800' : '' }}">
                {{ $mentee->attrition_risk }} Risk
            </span>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-gray-900">{{ $mentee->current_status ? ucwords(str_replace('_', ' ', $mentee->current_status)) : 'Active' }}</div>
                <div class="text-sm text-gray-600">Current Status</div>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-gray-900">
                    {{ $mentee->trainingParticipations()->latest('registration_date')->first()?->registration_date?->diffInDays(now()) ?? 'N/A' }}
                </div>
                <div class="text-sm text-gray-600">Days Since Last Training</div>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-gray-900">
                    {{ $mentee->statusLogs()->where('created_at', '>=', now()->subMonths(6))->count() }}
                </div>
                <div class="text-sm text-gray-600">Status Changes (6M)</div>
            </div>
        </div>
    </div>

    {{-- AI-Powered Insights --}}
    <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
            AI-Powered Insights
        </h3>
        
        @foreach($insights as $insight)
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
                
                <div class="ml-3 flex-1">
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
                </div>
            </div>
        @endforeach
    </div>

    {{-- Training History Timeline --}}
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-6 flex items-center">
            <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Training Journey Timeline
        </h3>
        
        <div class="space-y-6">
            @forelse($mentee->trainingParticipations()->with(['training', 'objectiveResults'])->latest('registration_date')->get() as $participation)
                <div class="relative">
                    {{-- Timeline connector --}}
                    @if(!$loop->last)
                        <div class="absolute left-4 top-12 w-0.5 h-16 bg-gray-200"></div>
                    @endif
                    
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 bg-white
                                {{ $participation->completion_status === 'completed' ? 'border-green-500 text-green-600' : 'border-gray-300 text-gray-400' }}">
                                @if($participation->completion_status === 'completed')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    </svg>
                                @endif
                            </div>
                        </div>
                        
                        <div class="flex-1 bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900">{{ $participation->training->title }}</h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ $participation->training->facility?->name }}</p>
                                    <div class="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                                        <span>Started: {{ $participation->registration_date?->format('M j, Y') }}</span>
                                        @if($participation->completion_date)
                                            <span>Completed: {{ $participation->completion_date?->format('M j, Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="flex flex-col items-end space-y-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        {{ $participation->completion_status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $participation->completion_status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $participation->completion_status === 'dropped' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $participation->completion_status === 'registered' ? 'bg-gray-100 text-gray-800' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $participation->completion_status)) }}
                                    </span>
                                    
                                    @php
                                        $scores = $participation->objectiveResults->pluck('score');
                                        $avgScore = $scores->isEmpty() ? null : $scores->avg();
                                    @endphp
                                    
                                    @if($avgScore)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            {{ $avgScore >= 90 ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $avgScore >= 80 && $avgScore < 90 ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $avgScore >= 70 && $avgScore < 80 ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $avgScore < 70 ? 'bg-red-100 text-red-800' : '' }}">
                                            {{ number_format($avgScore, 1) }}%
                                        </span>
                                    @endif
                                </div>
                            </div>
                            
                            {{-- Assessment breakdown for recent training --}}
                            @if($loop->first && $participation->objectiveResults->count() > 0)
                                <div class="mt-3 pt-3 border-t border-gray-200">
                                    <p class="text-xs text-gray-600 mb-2">Recent Assessment Breakdown:</p>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        @foreach($participation->objectiveResults->take(4) as $result)
                                            <div class="text-center">
                                                <div class="text-xs text-gray-500">{{ Str::limit($result->objective?->objective_text ?? 'Assessment', 15) }}</div>
                                                <div class="text-sm font-medium {{ $result->score >= 70 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ number_format($result->score, 0) }}%
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <p class="mt-2 text-sm">No training history available yet.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Status History --}}
    @if($mentee->statusLogs->count() > 0)
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Status History
            </h3>
            
            <div class="space-y-3">
                @foreach($mentee->statusLogs->take(5) as $log)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                {{ in_array($log->new_status, ['active', 'study_leave']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucwords(str_replace('_', ' ', $log->new_status)) }}
                            </span>
                            <div>
                                <p class="text-sm text-gray-900">{{ $log->reason ?? 'Status updated' }}</p>
                                @if($log->notes)
                                    <p class="text-xs text-gray-500">{{ Str::limit($log->notes, 50) }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-900">{{ $log->effective_date?->format('M j, Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $log->changedBy?->full_name ?? 'System' }}</p>
                        </div>
                    </div>
                @endforeach
                
                @if($mentee->statusLogs->count() > 5)
                    <div class="text-center">
                        <button type="button" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                            View All {{ $mentee->statusLogs->count() }} Status Changes
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Performance Analysis Chart --}}
    @if($mentee->trainingParticipations->count() > 1)
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Performance Trend Analysis
            </h3>
            
            @php
                $performanceData = [];
                foreach($mentee->trainingParticipations->sortBy('registration_date') as $participation) {
                    $scores = $participation->objectiveResults->pluck('score');
                    if($scores->count() > 0) {
                        $performanceData[] = [
                            'training' => Str::limit($participation->training->title, 20),
                            'date' => $participation->registration_date?->format('M Y'),
                            'score' => round($scores->avg(), 1)
                        ];
                    }
                }
            @endphp
            
            @if(count($performanceData) >= 2)
                <div class="space-y-4">
                    {{-- Simple trend visualization --}}
                    <div class="flex items-end space-x-2 h-32">
                        @foreach($performanceData as $index => $data)
                            <div class="flex-1 flex flex-col items-center">
                                <div class="bg-blue-500 rounded-t transition-all duration-300 hover:bg-blue-600 w-full" 
                                     style="height: {{ ($data['score'] / 100) * 100 }}%"
                                     title="{{ $data['training'] }}: {{ $data['score'] }}%">
                                </div>
                                <div class="text-xs text-gray-600 mt-2 text-center">
                                    <div class="font-medium">{{ $data['score'] }}%</div>
                                    <div>{{ $data['date'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    {{-- Trend summary --}}
                    <div class="bg-gray-50 rounded-lg p-4">
                        @php
                            $firstScore = $performanceData[0]['score'];
                            $lastScore = end($performanceData)['score'];
                            $improvement = $lastScore - $firstScore;
                        @endphp
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Overall Trend</p>
                                <p class="text-xs text-gray-600">From {{ $performanceData[0]['date'] }} to {{ end($performanceData)['date'] }}</p>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center space-x-2">
                                    @if($improvement > 0)
                                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                        <span class="text-sm font-medium text-green-600">+{{ number_format($improvement, 1) }}%</span>
                                    @elseif($improvement < 0)
                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                        </svg>
                                        <span class="text-sm font-medium text-red-600">{{ number_format($improvement, 1) }}%</span>
                                    @else
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        <span class="text-sm font-medium text-gray-600">No change</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500">{{ count($performanceData) }} assessments</p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="mt-2 text-sm">Need at least 2 assessed trainings to show performance trends.</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Action Recommendations --}}
    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-indigo-900 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            Recommended Actions
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @if($mentee->overall_training_score && $mentee->overall_training_score >= 85)
                <div class="bg-white rounded-lg p-4 border border-indigo-200">
                    <h4 class="font-medium text-indigo-900 mb-2">🌟 High Performer Recognition</h4>
                    <p class="text-sm text-indigo-700">Consider {{ $mentee->first_name }} for advanced training programs or peer mentoring roles.</p>
                </div>
            @endif
            
            @if($mentee->performance_trend === 'Declining')
                <div class="bg-white rounded-lg p-4 border border-indigo-200">
                    <h4 class="font-medium text-indigo-900 mb-2">⚠️ Performance Support</h4>
                    <p class="text-sm text-indigo-700">Schedule one-on-one mentoring session to identify challenges and provide support.</p>
                </div>
            @endif
            
            @if($mentee->training_completion_rate < 70)
                <div class="bg-white rounded-lg p-4 border border-indigo-200">
                    <h4 class="font-medium text-indigo-900 mb-2">📚 Completion Focus</h4>
                    <p class="text-sm text-indigo-700">Investigate barriers to training completion and provide additional resources.</p>
                </div>
            @endif
            
            @if($mentee->attrition_risk === 'High')
                <div class="bg-white rounded-lg p-4 border border-indigo-200">
                    <h4 class="font-medium text-indigo-900 mb-2">🚨 Retention Priority</h4>
                    <p class="text-sm text-indigo-700">Immediate intervention required. Consider career counseling and workload review.</p>
                </div>
            @endif
            
            {{-- Default recommendation if no specific issues --}}
            @if($mentee->overall_training_score && $mentee->overall_training_score >= 70 && $mentee->performance_trend !== 'Declining' && $mentee->training_completion_rate >= 70 && $mentee->attrition_risk !== 'High')
                <div class="bg-white rounded-lg p-4 border border-indigo-200">
                    <h4 class="font-medium text-indigo-900 mb-2">✅ Continued Development</h4>
                    <p class="text-sm text-indigo-700">{{ $mentee->first_name }} is performing well. Consider specialized training in {{ $mentee->department?->name }} for career advancement.</p>
                </div>
            @endif
            
            <div class="bg-white rounded-lg p-4 border border-indigo-200">
                <h4 class="font-medium text-indigo-900 mb-2">📅 Regular Check-in</h4>
                <p class="text-sm text-indigo-700">Schedule monthly performance review to track progress and address any emerging issues.</p>
            </div>
        </div>
    </div>
</div>